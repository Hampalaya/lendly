<?php

namespace Tests\Audit;

use App\Livewire\Listings\Map;
use App\Livewire\Owner\RentalRequests\Index as OwnerRequests;
use App\Livewire\Owner\Rentals\Index as OwnerHistory;
use App\Livewire\RentalRequests\Create;
use App\Livewire\Renter\RentalRequests\Index as RenterRequests;
use App\Livewire\Renter\Rentals\Show as RenterRental;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\Review;
use App\Models\User;
use App\Services\RentalLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Audit probes assert observed gaps, not the desired requirements. In-memory database only. */
class RequirementGapTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(string $status = 'approved'): array
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $category = Category::create(['name' => 'Audit Tools', 'slug' => 'audit-tools']);
        $listing = Listing::create([
            'owner_id' => $owner->id, 'category_id' => $category->id,
            'name' => 'Audit drill', 'description' => 'Audit fixture', 'condition' => 'good',
            'price_per_day' => 100, 'location' => 'Manila', 'max_rental_duration_days' => 10,
            'status' => 'published', 'is_available' => true, 'latitude' => 0, 'longitude' => 120,
        ]);
        $values = [
            'listing_id' => $listing->id, 'renter_id' => $renter->id,
            'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(2)->toDateString(),
            'rental_days' => 2, 'fulfillment_method' => 'pickup', 'rental_fee' => 200,
            'commission_rate' => 10, 'commission_amount' => 20, 'security_deposit' => 0, 'total_amount' => 220,
        ];
        $request = RentalRequest::create($values + ['status' => $status]);
        $rental = null;
        if ($status === 'approved') {
            $rental = Rental::create($values + ['owner_id' => $owner->id, 'rental_request_id' => $request->id]);
        }
        return [$owner, $renter, $listing, $request, $rental];
    }

    public function test_approved_request_cancellation_leaves_booking_payable_and_no_owner_notice(): void
    {
        [$owner, $renter, $listing, $request, $rental] = $this->scenario();
        Livewire::actingAs($renter)->test(RenterRequests::class)->call('cancel', $request->id)->assertHasNoErrors();
        $this->assertSame('cancelled', $request->fresh()->status->value);
        $this->assertSame('payment_pending', $rental->fresh()->status->value);
        $this->assertEquals(0, $rental->fresh()->cancellation_fee);
        $this->assertSame(0, $owner->notifications()->count());
        $this->assertFalse($listing->hasApprovedOverlap($request->start_date, $request->end_date));
        Livewire::actingAs($renter)->test(RenterRental::class, ['rental' => $rental->fresh()])->call('confirmPayment')->assertHasNoErrors();
        $this->assertSame('paid', $rental->fresh()->status->value);
    }

    public function test_booking_approval_does_not_check_renter_acceptance_or_notify_owner(): void
    {
        [$owner, $renter, $listing, $request] = $this->scenario('requested');
        $this->assertNull($request->renter_terms_accepted_at);
        Livewire::actingAs($owner)->test(OwnerRequests::class)->set('accept_terms', true)->call('approve', $request->id)->assertHasNoErrors();
        $this->assertSame(1, Rental::count());
        $this->assertSame(0, $owner->notifications()->count());
        $this->assertSame(1, $renter->notifications()->count());
    }

    public function test_owner_can_submit_a_review_as_renter_through_renter_component(): void
    {
        [$owner, $renter, $listing, $request, $rental] = $this->scenario();
        $rental->update(['status' => 'completed', 'completed_at' => now()]);
        Livewire::actingAs($owner)->test(RenterRental::class, ['rental' => $rental])->set('owner_rating', 5)->call('submitOwnerReview')->assertHasNoErrors();
        $this->assertSame('renter_to_owner', Review::first()->type->value);
    }

    public function test_zero_latitude_disables_distance_computation_and_filter(): void
    {
        [$owner, $renter, $listing] = $this->scenario('requested');
        $markers = Livewire::test(Map::class)->set('centerLat', 0)->set('centerLng', 120)->set('radiusKm', 5)->get('markers');
        $this->assertCount(1, $markers);
        $this->assertNull($markers[0]['distanceKm']);
    }

    public function test_cancellation_still_allowed_after_owner_has_logged_handover(): void
    {
        [$owner, $renter, $listing, $request, $rental] = $this->scenario();
        $rental->update(['status' => 'paid', 'paid_at' => now()]);
        RentalLifecycle::confirmPickup($rental, $owner);
        $this->assertNotNull($rental->fresh()->pickup_confirmed_by_owner_at);
        $this->assertTrue($rental->fresh()->isCancellableByRenter());
    }

    public function test_deleting_listing_removes_normal_listing_relation_used_by_history(): void
    {
        [$owner, $renter, $listing, $request, $rental] = $this->scenario();
        $listing->delete();
        $this->assertSame(1, Rental::count());
        $this->assertNull($rental->fresh()->listing);
        $this->assertNull($request->fresh()->listing);
    }

    public function test_request_submission_does_not_recheck_listing_availability_after_mount(): void
    {
        [$owner, $renter, $listing] = $this->scenario('requested');
        $component = Livewire::actingAs($renter)->test(Create::class, ['listing' => $listing]);
        $listing->update(['is_available' => false]);
        $component->set('accept_terms', true)->call('submit')->assertHasNoErrors();
        $this->assertSame(2, RentalRequest::count());
    }
}
