<?php

namespace Tests\Feature;

use App\Livewire\Owner\RentalRequests\Index;
use App\Models\Category;
use App\Models\Listing;
use App\Models\RentalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RentalQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_default_to_newest_first_and_toggle_oldest_first_with_id_ties(): void
    {
        $owner = User::factory()->identityVerified()->create();
        $listing = $this->listing($owner);
        $old = $this->request($listing, 'First arrival', now()->subHours(2));
        $tie = $this->request($listing, 'Second arrival', now()->subHours(2));
        $new = $this->request($listing, 'Third arrival', now()->subHour());
        $other = $this->request($this->listing(User::factory()->identityVerified()->create()), 'Other owner request', now()->subDay());

        Livewire::actingAs($owner)->test(Index::class)
            ->assertViewHas('rentalRequests', fn ($rows) => $rows->pluck('id')->all() === [$new->id, $tie->id, $old->id])
            ->assertSee('Newest first')
            ->assertDontSee($other->renter->name)
            ->call('setPage', 2)
            ->call('toggleRecordSort')
            ->assertSet('recordSort', 'oldest')
            ->assertSet('paginators.page', 1)
            ->assertSee('Oldest first')
            ->assertViewHas('rentalRequests', fn ($rows) => $rows->pluck('id')->all() === [$old->id, $tie->id, $new->id])
            ->call('toggleRecordSort')
            ->assertSet('recordSort', 'newest')
            ->assertSee('Newest first')
            ->assertViewHas('rentalRequests', fn ($rows) => $rows->pluck('id')->all() === [$new->id, $tie->id, $old->id])
            ->set('filter', 'all')
            ->assertViewHas('rentalRequests', fn ($rows) => $rows->pluck('id')->all() === [$new->id, $tie->id, $old->id]);
    }

    public function test_owner_can_choose_later_request_and_existing_overlap_rejection_still_applies(): void
    {
        $owner = User::factory()->identityVerified()->create();
        $listing = $this->listing($owner);
        $earlier = $this->request($listing, 'Early renter', now()->subHours(2));
        $later = $this->request($listing, 'Later renter', now()->subHour());

        Livewire::actingAs($owner)->test(Index::class)->call('approve', $later->id)
            ->assertHasNoErrors()->assertRedirect(route('owner.rental-requests.agreement', $later));

        $this->assertSame('awaiting_agreement', $later->fresh()->status->value);
        $this->assertSame('rejected', $earlier->fresh()->status->value);
        $this->assertDatabaseCount('rentals', 0);
    }

    private function listing(User $owner): Listing
    {
        $category = Category::firstOrCreate(['slug' => 'queue-tools'], ['name' => 'Tools']);

        return Listing::create([
            'owner_id' => $owner->id, 'category_id' => $category->id, 'name' => 'Queue tool',
            'description' => 'Tool', 'condition' => 'good', 'location' => 'Manila',
            'price_per_day' => 100, 'security_deposit' => 200, 'max_rental_duration_days' => 10,
            'status' => 'published', 'is_available' => true, 'pickup_available' => true, 'delivery_available' => false,
        ]);
    }

    private function request(Listing $listing, string $name, $created): RentalRequest
    {
        return RentalRequest::create([
            'listing_id' => $listing->id, 'renter_id' => User::factory()->identityVerified()->create(['name' => $name])->id,
            'start_date' => now()->addDays(3), 'end_date' => now()->addDays(4), 'rental_days' => 2,
            'fulfillment_method' => 'pickup', 'rental_fee' => 200, 'commission_rate' => 10,
            'commission_amount' => 20, 'security_deposit' => 200, 'total_amount' => 420,
            'status' => 'requested', 'created_at' => $created,
        ]);
    }
}
