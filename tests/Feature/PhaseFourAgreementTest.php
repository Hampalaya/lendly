<?php

namespace Tests\Feature;

use App\Enums\RentalRequestStatus;
use App\Livewire\Owner\RentalRequests\Index;
use App\Livewire\RentalRequests\Agreement;
use App\Livewire\Renter\RentalRequests\Index as RenterRequests;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Rental;
use App\Models\User;
use App\Services\RentalAgreement;
use App\Services\RentalRequestSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PhaseFourAgreementTest extends TestCase
{
    use RefreshDatabase;

    private function requestFixture(): array
    {
        $owner = User::factory()->identityVerified()->create();
        $renter = User::factory()->identityVerified()->create();
        $listing = Listing::create(['owner_id' => $owner->id,
            'category_id' => Category::firstOrCreate(['slug' => 'phase-four'], ['name' => 'Tools'])->id,
            'name' => 'Agreement camera', 'description' => 'With case', 'condition' => 'good',
            'price_per_day' => 200, 'price_per_hour' => 25, 'location' => 'Manila',
            'available_from' => now()->toDateString(), 'available_until' => now()->addMonth()->toDateString(),
            'status' => 'published']);
        $details = ['start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00', 'end_time' => '11:00', 'rental_unit' => 'hour', 'fulfillment_method' => 'pickup'];
        $request = RentalRequestSubmission::submit($renter, $listing->id, $details, RentalRequestSubmission::termsFor($listing), true);

        return [$owner, $renter, $listing, $request];
    }

    public function test_opening_and_cancelling_the_confirmation_modal_does_not_change_the_request(): void
    {
        [$owner, , , $request] = $this->requestFixture();
        Livewire::actingAs($owner)->test(Index::class)->call('startApproving', $request->id)
            ->assertSeeHtml('<dialog')->assertSee('Confirm request for agreement')->assertSee('Agreement camera')
            ->call('cancelApproving')->assertSet('approving', null)->assertDontSeeHtml('<dialog');
        $this->assertSame(RentalRequestStatus::Requested, $request->fresh()->status);
        $this->assertNull($request->fresh()->agreement_terms);
        $this->assertDatabaseCount('rentals', 0);
    }

    public static function acceptanceOrders(): array
    {
        return ['owner first' => [true], 'renter first' => [false]];
    }

    #[DataProvider('acceptanceOrders')]
    public function test_request_is_approved_and_one_booking_created_only_after_both_saved_acceptances(bool $ownerFirst): void
    {
        [$owner, $renter, $listing, $request] = $this->requestFixture();
        Livewire::actingAs($owner)->test(Index::class)->call('startApproving', $request->id)->call('approve', $request->id)->assertHasNoErrors();
        $this->assertSame(RentalRequestStatus::AwaitingAgreement, $request->fresh()->status);
        $this->assertNull($request->fresh()->owner_terms_accepted_at);
        $this->assertNull($request->fresh()->renter_terms_accepted_at);
        $this->assertTrue($listing->hasApprovedOverlap($request->start_date, $request->end_date));
        $this->assertDatabaseCount('rentals', 0);
        $this->assertSame(0, $renter->notifications()->where('data->type', 'request_approved')->count());
        $first = $ownerFirst ? $owner : $renter;
        $second = $ownerFirst ? $renter : $owner;
        Livewire::actingAs($first)->test(Agreement::class, ['rentalRequest' => $request->fresh()])->call('acceptTerms')->assertHasErrors('accept_terms');
        $this->assertNull(RentalAgreement::accept($request, $first));
        $this->assertSame(RentalRequestStatus::AwaitingAgreement, $request->fresh()->status);
        $booking = RentalAgreement::accept($request, $second);
        $this->assertNotNull($booking);
        $this->assertSame(RentalRequestStatus::Approved, $request->fresh()->status);
        $this->assertNotNull($request->fresh()->renter_terms_accepted_at);
        $this->assertNotNull($request->fresh()->owner_terms_accepted_at);
        $this->assertSame($booking->id, RentalAgreement::accept($request, $first)->id);
        $this->assertDatabaseCount('rentals', 1);
        $this->assertSame(1, $renter->notifications()->where('data->type', 'booking_confirmed')->count());
    }

    public function test_modal_and_acceptance_cannot_access_another_members_request(): void
    {
        [, , , $request] = $this->requestFixture();
        $stranger = User::factory()->identityVerified()->create();
        Livewire::actingAs($stranger)->test(Index::class)->call('startApproving', $request->id)->assertForbidden();
        Livewire::actingAs($stranger)->test(Agreement::class, ['rentalRequest' => $request])->assertForbidden();
    }

    public function test_awaiting_agreement_filter_and_renter_cancellation_release_the_hold_without_a_booking(): void
    {
        [$owner, $renter, $listing, $request] = $this->requestFixture();
        Livewire::actingAs($owner)->test(Index::class)->call('approve', $request->id)->assertHasNoErrors();
        Livewire::test(Index::class)->set('filter', 'awaiting_agreement')->assertSee('Agreement camera')->assertSee('Review Rental Agreement');
        Livewire::actingAs($renter)->test(RenterRequests::class)->call('cancel', $request->id)->assertHasNoErrors();
        $this->assertSame(RentalRequestStatus::Cancelled, $request->fresh()->status);
        $this->assertFalse($listing->hasApprovedOverlap($request->start_date, $request->end_date));
        $this->assertEquals(0, $request->fresh()->cancellation_fee);
        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_booking_failure_rolls_back_final_status_and_second_acceptance(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        Livewire::actingAs($owner)->test(Index::class)->call('approve', $request->id);
        RentalAgreement::accept($request, $owner);
        Rental::creating(fn () => throw new RuntimeException('Booking storage failed'));
        try {
            RentalAgreement::accept($request, $renter);
            $this->fail('Expected booking failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Booking storage failed', $exception->getMessage());
        } finally {
            Rental::flushEventListeners();
        }
        $this->assertSame(RentalRequestStatus::AwaitingAgreement, $request->fresh()->status);
        $this->assertNull($request->fresh()->renter_terms_accepted_at);
        $this->assertNotNull($request->fresh()->owner_terms_accepted_at);
        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_owner_can_send_a_24_hour_request_for_agreement_with_a_one_day_cap(): void
    {
        [$owner, $renter, $listing] = $this->requestFixture();
        $listing->update(['max_rental_duration_days' => 1]);
        $details = ['start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(6)->toDateString(),
            'start_time' => '09:00', 'end_time' => '09:00', 'rental_unit' => 'hour', 'fulfillment_method' => 'pickup'];
        $request = RentalRequestSubmission::submit($renter, $listing->id, $details, RentalRequestSubmission::termsFor($listing), true);
        Livewire::actingAs($owner)->test(Index::class)->call('approve', $request->id)->assertHasNoErrors();
        $this->assertSame(RentalRequestStatus::AwaitingAgreement, $request->fresh()->status);
        $this->assertSame(24, $request->fresh()->rental_hours);
    }

    public function test_migration_relabels_only_unfinalized_legacy_requests_and_preserves_saved_terms(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        $request->update(['status' => 'approved', 'agreement_terms' => RentalAgreement::termsFor($request), 'owner_terms_accepted_at' => now()]);
        $before = $request->fresh()->getAttributes();
        [$otherOwner, $otherRenter, , $finished] = $this->requestFixture();
        $finished->update(['status' => 'approved', 'agreement_terms' => RentalAgreement::termsFor($finished)]);
        RentalAgreement::accept($finished, $otherOwner);
        RentalAgreement::accept($finished, $otherRenter);
        $migration = require database_path('migrations/2026_11_01_000002_mark_unfinalized_requests_as_awaiting_agreement.php');
        $migration->up();
        $this->assertSame(RentalRequestStatus::AwaitingAgreement, $request->fresh()->status);
        $this->assertSame($before['agreement_terms'], $request->fresh()->getAttributes()['agreement_terms']);
        $this->assertSame($before['owner_terms_accepted_at'], $request->fresh()->getAttributes()['owner_terms_accepted_at']);
        $this->assertSame(RentalRequestStatus::Approved, $finished->fresh()->status);
        $this->assertDatabaseCount('rentals', 1);
    }
}
