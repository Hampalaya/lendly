<?php

namespace Tests\Feature;

use App\Enums\DisputeResolution;
use App\Enums\ListingStatus;
use App\Enums\RentalRequestStatus;
use App\Enums\RentalStatus;
use App\Livewire\Owner\RentalRequests\Index as OwnerRequestsIndex;
use App\Livewire\Owner\Rentals\Index as OwnerRentalsIndex;
use App\Livewire\Owner\Rentals\Show;
use App\Livewire\Renter\RentalRequests\Index as RenterRequestsIndex;
use App\Livewire\Renter\Rentals\Index as RenterRentalsIndex;
use App\Models\Category;
use App\Models\ConditionRecord;
use App\Models\ConditionRecordPhoto;
use App\Models\Dispute;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\Review;
use App\Models\SecurityDeposit;
use App\Models\User;
use App\Services\RentalLifecycle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// FR-24: users can view their complete history of past bookings and transactions.
class RentalHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function listingFor(User $owner): Listing
    {
        $category = Category::firstOrCreate(['slug' => 'history-tools'], ['name' => 'History Tools']);

        return Listing::create([
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'History Item',
            'description' => 'x',
            'condition' => 'good',
            'price_per_day' => 100,
            'security_deposit' => 500,
            'location' => 'Manila',
            'max_rental_duration_days' => 10,
            'status' => ListingStatus::Published,
            'is_available' => true,
            'pickup_available' => true,
        ]);
    }

    private function rentalWithStatus(User $owner, User $renter, Listing $listing, RentalStatus $status, $startDate, $endDate): Rental
    {
        $request = RentalRequest::create([
            'listing_id' => $listing->id,
            'renter_id' => $renter->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rental_days' => 3,
            'fulfillment_method' => 'pickup',
            'rental_fee' => 300,
            'commission_rate' => 10,
            'commission_amount' => 30,
            'security_deposit' => 500,
            'total_amount' => 830,
            'status' => 'approved',
        ]);

        return Rental::create([
            'rental_request_id' => $request->id,
            'listing_id' => $listing->id,
            'owner_id' => $owner->id,
            'renter_id' => $renter->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rental_days' => 3,
            'fulfillment_method' => 'pickup',
            'rental_fee' => 300,
            'commission_rate' => 10,
            'commission_amount' => 30,
            'security_deposit' => 500,
            'total_amount' => 830,
            'status' => $status,
            'paid_at' => $status !== RentalStatus::PaymentPending ? now() : null,
            'completed_at' => $status === RentalStatus::Completed ? now() : null,
            'archived_at' => $status === RentalStatus::Completed ? now() : null,
            'cancelled_at' => $status === RentalStatus::Cancelled ? now() : null,
            'cancellation_reason' => $status === RentalStatus::Cancelled ? 'Booking cancelled' : null,
        ]);
    }

    public function test_history_includes_every_rental_status_for_both_parties(): void
    {
        $this->freezeTime();
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $listing = $this->listingFor($owner);
        $rentalIds = [];

        foreach (RentalStatus::cases() as $status) {
            $rentalIds[] = $this->rentalWithStatus($owner, $renter, $listing, $status,
                $status === RentalStatus::Overdue ? now()->subDays(5) : now()->addDays(5),
                $status === RentalStatus::Overdue ? now()->subDays(2) : now()->addDays(8))->id;
        }

        $renterView = Livewire::actingAs($renter)->test(RenterRentalsIndex::class);
        $this->assertSame(count(RentalStatus::cases()), $renterView->viewData('rentals')->total());
        $this->assertEqualsCanonicalizing(RentalStatus::cases(), $renterView->viewData('rentals')->getCollection()->pluck('status')->all());
        $renterView
            ->assertViewHas('rentals', fn ($rows) => $rows->pluck('id')->all() === array_reverse($rentalIds))
            ->call('setPage', 2)
            ->call('toggleRecordSort')
            ->assertSet('recordSort', 'oldest')
            ->assertSet('paginators.page', 1)
            ->assertViewHas('rentals', fn ($rows) => $rows->pluck('id')->all() === $rentalIds)
            ->call('toggleRecordSort')
            ->assertSet('recordSort', 'newest')
            ->assertViewHas('rentals', fn ($rows) => $rows->pluck('id')->all() === array_reverse($rentalIds));

        $ownerView = Livewire::actingAs($owner)->test(OwnerRentalsIndex::class);
        $this->assertSame(count(RentalStatus::cases()), $ownerView->viewData('rentals')->total());
        $this->assertEqualsCanonicalizing(RentalStatus::cases(), $ownerView->viewData('rentals')->getCollection()->pluck('status')->all());
        $ownerView
            ->assertViewHas('rentals', fn ($rows) => $rows->pluck('id')->all() === array_reverse($rentalIds))
            ->call('setPage', 2)
            ->call('toggleRecordSort')
            ->assertSet('recordSort', 'oldest')
            ->assertSet('paginators.page', 1)
            ->assertViewHas('rentals', fn ($rows) => $rows->pluck('id')->all() === $rentalIds)
            ->call('toggleRecordSort')
            ->assertSet('recordSort', 'newest')
            ->assertViewHas('rentals', fn ($rows) => $rows->pluck('id')->all() === array_reverse($rentalIds));
        $ownerView->set('filter', 'completed');
        $this->assertSame(1, $ownerView->viewData('rentals')->total());
        $this->assertSame(RentalStatus::Completed, $ownerView->viewData('rentals')->first()->status);
    }

    public function test_a_renter_cannot_see_another_renters_history(): void
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $otherRenter = User::factory()->create();
        $listing = $this->listingFor($owner);

        $this->rentalWithStatus($owner, $renter, $listing, RentalStatus::Completed, now()->subDays(10), now()->subDays(7));

        $otherView = Livewire::actingAs($otherRenter)->test(RenterRentalsIndex::class);
        $this->assertSame(0, $otherView->viewData('rentals')->total());
    }

    public function test_renter_history_displays_the_owner_recorded_on_the_booking(): void
    {
        $originalOwner = User::factory()->create(['name' => 'Original booking owner']);
        $renter = User::factory()->create();
        $listing = $this->listingFor($originalOwner);
        $rental = $this->rentalWithStatus($originalOwner, $renter, $listing, RentalStatus::Completed, now()->subDays(10), now()->subDays(7));
        $newOwner = User::factory()->create(['name' => 'Current listing owner']);
        $listing->update(['owner_id' => $newOwner->id]);

        Livewire::actingAs($renter)->test(RenterRentalsIndex::class)
            ->assertSee('Original booking owner')->assertDontSee('Current listing owner');
        $this->assertSame($originalOwner->id, $rental->fresh()->owner_id);
    }

    private function archivedRental(User $owner, User $renter): Rental
    {
        $listing = $this->listingFor($owner);
        $rental = $this->rentalWithStatus($owner, $renter, $listing, RentalStatus::Active, now()->subDays(3), now());
        Payment::create(['rental_id' => $rental->id, 'transaction_reference' => Payment::generateReference(), 'amount' => 830, 'paid_at' => now()]);
        SecurityDeposit::create(['rental_id' => $rental->id, 'amount' => 500]);
        foreach (['before', 'after'] as $type) {
            $record = ConditionRecord::create(['rental_id' => $rental->id, 'recorded_by' => $owner->id, 'type' => $type, 'condition' => 'good', 'notes' => 'Inspection evidence']);
            ConditionRecordPhoto::create(['condition_record_id' => $record->id, 'path' => 'condition-photos/evidence.jpg', 'sort_order' => 0]);
        }
        Message::create(['rental_request_id' => $rental->rental_request_id, 'sender_id' => $owner->id, 'receiver_id' => $renter->id, 'body' => 'Return at the agreed location.']);
        $rental->exchangeSchedules()->create(['proposed_by' => $owner->id, 'fulfillment_method' => 'pickup', 'scheduled_at' => now()->subDays(3), 'address' => 'Agreed pickup address', 'owner_confirmed_at' => now(), 'renter_confirmed_at' => now(), 'confirmed_at' => now()]);
        Dispute::create(['rental_id' => $rental->id, 'raised_by' => $renter->id, 'reason' => 'incorrect_charge', 'description' => 'Retained dispute evidence']);

        RentalLifecycle::confirmReturn($rental, $renter);
        $this->assertSame(RentalStatus::Active, $rental->fresh()->status);
        $this->assertNull($rental->fresh()->archived_at);
        RentalLifecycle::confirmReturn($rental, $owner);
        $this->assertSame(RentalStatus::Completed, $rental->fresh()->status);
        $this->assertNotNull($rental->fresh()->archived_at);
        Livewire::actingAs($owner)->test(Show::class, ['rental' => $rental->fresh()])
            ->assertSee('Archived on')->call('completeInspection')->assertForbidden();

        Review::create(['rental_id' => $rental->id, 'type' => 'renter_to_owner', 'rating' => 5, 'comment' => 'Retained review']);

        return $rental->fresh();
    }

    public static function deletionTargets(): array
    {
        return [['owner'], ['renter'], ['listing']];
    }

    #[DataProvider('deletionTargets')]
    public function test_archived_transaction_and_evidence_survive_deletion(string $target): void
    {
        $owner = User::factory()->create(['phone' => '09171234567', 'address' => 'Private owner address']);
        $renter = User::factory()->create(['phone' => '09171234568', 'address' => 'Private renter address']);
        $rental = $this->archivedRental($owner, $renter);
        if ($target !== 'listing') {
            $this->actingAs($owner);
            RentalLifecycle::resolveDispute($rental->disputes()->sole(), DisputeResolution::AccountWarning, 'Reviewed and settled', User::factory()->admin()->create());
            RentalLifecycle::releaseDeposit($rental);
            RentalLifecycle::confirmDepositReceipt($rental, $renter);
            $rental->refresh();
        }
        $saved = $rental->getAttributes();
        $listing = $rental->listing;

        if ($target === 'listing') {
            $listing->delete();
        } else {
            $deletedUser = $target === 'owner' ? $owner : $renter;
            $originalEmail = $deletedUser->email;
            Volt::actingAs($deletedUser)->test('profile.delete-user-form')
                ->set('password', 'password')->call('deleteUser')->assertHasNoErrors()->assertRedirect('/');
            $this->assertGuest();
            $this->assertNull(User::find($deletedUser->id));
            $deletedUser->refresh();
            $this->assertTrue($deletedUser->trashed());
            $this->assertSame('Deleted account', $deletedUser->name);
            $this->assertNotSame($originalEmail, $deletedUser->email);
            $this->assertNull($deletedUser->phone);
            $this->assertNull($deletedUser->address);
            $this->assertNull($deletedUser->email_verified_at);
            $this->assertNull($deletedUser->remember_token);
            $this->assertFalse(Auth::attempt(['email' => $originalEmail, 'password' => 'password']));
        }

        $rental->refresh();
        $this->assertSame($saved, $rental->getAttributes());
        $this->assertSame(RentalStatus::Completed, $rental->status);
        $this->assertTrue($rental->archived_at->equalTo($rental->completed_at));
        $this->assertSame($target === 'listing' ? 'return_eligible' : 'released', $rental->securityDeposit->status->value);
        $this->assertSame('830.00', $rental->payment->amount);
        $this->assertSame('History Item', $rental->listing->name);
        $this->assertSame($listing->id, $rental->rentalRequest->listing->id);
        $this->assertSame($owner->id, $rental->owner->id);
        $this->assertSame($renter->id, $rental->renter->id);
        $this->assertSame($renter->id, $rental->rentalRequest->renter->id);
        $this->assertSame(2, $rental->conditionRecords->count());
        foreach ($rental->conditionRecords as $record) {
            $this->assertCount(1, $record->photos);
            $this->assertSame($owner->id, $record->recordedBy->id);
        }
        $this->assertSame($owner->id, $rental->exchangeSchedule->proposer->id);
        $this->assertSame($renter->id, $rental->disputes->first()->raisedBy->id);
        $this->assertCount(1, $rental->reviews);
        $message = $rental->rentalRequest->messages->first();
        $this->assertSame($owner->id, $message->sender->id);
        $this->assertSame($renter->id, $message->receiver->id);

        foreach (['owner' => $owner, 'renter' => $renter] as $interface => $user) {
            if ($user->trashed()) {
                continue;
            }
            $this->actingAs($user)->get(route($interface.'.rentals.index'))->assertOk()->assertSee('History Item');
            $response = $this->get(route($interface.'.rentals.show', $rental));
            $response->assertOk()->assertSee('Archived on')->assertSee('Inspection evidence');
            if ($listing->fresh()->trashed()) {
                $response->assertSee('This listing has been removed')->assertDontSee('View listing');
            }
            if ($target !== 'listing') {
                $response->assertSee('Deleted account');
            }

            $requests = Livewire::actingAs($user)->test($interface === 'owner' ? OwnerRequestsIndex::class : RenterRequestsIndex::class);
            if ($interface === 'owner') {
                $requests->set('filter', 'all');
            }
            $this->assertSame(1, $requests->viewData('rentalRequests')->total());
            $this->assertSame($rental->rental_request_id, $requests->viewData('rentalRequests')->first()->id);
            $requests->assertSee('History Item')->assertSeeHtml(route($interface.'.rentals.show', $rental));
            if ($target !== 'listing') {
                $requests->assertSee('Deleted account')->assertDontSee('@example.invalid');
            }
            $this->get(route('rental-requests.chat', $rental->rental_request_id))->assertOk()->assertSee('Return at the agreed location.');
        }
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.rentals.index'))->assertOk()->assertSee('History Item');
        if ($target !== 'renter') {
            $this->get(route('listings.show', $listing->id))->assertNotFound();
            $this->assertNull(Listing::find($listing->id));
        }
    }

    #[DataProvider('deletionTargets')]
    public function test_physical_deletion_cannot_cascade_away_shared_history(string $target): void
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $rental = $this->archivedRental($owner, $renter);
        try {
            if ($target === 'listing') {
                $rental->listing->forceDelete();
            } else {
                ($target === 'owner' ? $owner : $renter)->forceDelete();
            }
            $this->fail('Referenced transaction participants and items must be retained.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        $this->assertNotNull($rental->fresh());
        $this->assertSame(2, $rental->conditionRecords()->count());
        $this->assertNotNull(User::find($owner->id));
        $this->assertNotNull(User::find($renter->id));
        $this->assertNotNull(Listing::find($rental->listing_id));
    }

    public function test_archived_rental_cannot_be_reopened_by_stale_return_or_inspection(): void
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $rental = $this->archivedRental($owner, $renter);
        $saved = $rental->getAttributes();
        $notifications = DB::table('notifications')->count();

        $rental->status = RentalStatus::Paid;
        try {
            RentalLifecycle::confirmPickup($rental, $renter);
            $this->fail('An archived rental cannot be picked up again.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(403, $exception->status() ?? 403);
        }
        $this->actingAs($renter);
        try {
            RentalLifecycle::cancel($rental, 'Stale cancellation');
            $this->fail('An archived rental cannot be cancelled.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(403, $exception->status() ?? 403);
        }
        $this->actingAs($owner);
        $rental->status = RentalStatus::Active;
        try {
            RentalLifecycle::confirmReturn($rental, $renter);
            $this->fail('An archived rental cannot be returned again.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(403, $exception->status() ?? 403);
        }
        $rental->status = RentalStatus::Returned;
        try {
            RentalLifecycle::completeInspection($rental);
            $this->fail('An archived rental cannot be inspected again.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(403, $exception->status() ?? 403);
        }
        $this->assertSame($saved, $rental->fresh()->getAttributes());
        $this->assertSame($notifications, DB::table('notifications')->count());
    }

    public function test_inspection_cannot_archive_without_persisted_return_confirmations_or_condition_evidence(): void
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $rental = $this->rentalWithStatus($owner, $renter, $this->listingFor($owner), RentalStatus::Returned, now()->subDays(3), now());
        $record = ConditionRecord::create(['rental_id' => $rental->id, 'recorded_by' => $owner->id, 'type' => 'after', 'condition' => 'good']);
        $this->actingAs($owner);

        // A saved Returned label alone does not prove owner-confirmed return.
        Livewire::test(Show::class, ['rental' => $rental])->call('completeInspection')->assertForbidden();
        $this->assertNull($rental->fresh()->archived_at);

        $rental->update(['return_confirmed_by_owner_at' => now(), 'return_confirmed_by_renter_at' => now()]);
        $record->delete();
        Livewire::test(Show::class, ['rental' => $rental->fresh()])->call('completeInspection')->assertForbidden();
        $this->assertNull($rental->fresh()->completed_at);
        $this->assertNull($rental->fresh()->archived_at);
    }

    public static function historyPages(): array
    {
        return [
            'owner bookings' => [OwnerRentalsIndex::class, 'rentals', 'owner'],
            'renter bookings' => [RenterRentalsIndex::class, 'rentals', 'renter'],
            'owner requests' => [OwnerRequestsIndex::class, 'rentalRequests', 'owner'],
            'renter requests' => [RenterRequestsIndex::class, 'rentalRequests', 'renter'],
        ];
    }

    #[DataProvider('historyPages')]
    public function test_complete_retained_history_is_paginated_in_a_stable_order_and_scoped_to_the_user(string $component, string $collection, string $interface): void
    {
        // All rows share a timestamp, exercising the secondary ordering across page boundaries.
        $this->travelTo(now()->startOfSecond());
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $listing = $this->listingFor($owner);
        $ids = [];
        for ($index = 0; $index < 23; $index++) {
            $rental = $this->rentalWithStatus($owner, $renter, $listing, RentalStatus::Completed, now()->subDays(10), now()->subDays(7));
            $ids[] = $collection === 'rentals' ? $rental->id : $rental->rental_request_id;
        }

        $otherOwner = User::factory()->create();
        $otherRenter = User::factory()->create();
        $otherListing = $this->listingFor($otherOwner);
        $otherListing->update(['name' => 'Unrelated history item']);
        $otherRental = $this->rentalWithStatus($otherOwner, $otherRenter, $otherListing, RentalStatus::Completed, now()->subDays(10), now()->subDays(7));

        $user = $interface === 'owner' ? $owner : $renter;
        ($interface === 'owner' ? $renter : $owner)->delete();
        if (! $listing->fresh()->trashed()) {
            $listing->delete();
        }
        $history = Livewire::actingAs($user)->test($component);
        if ($component === OwnerRequestsIndex::class) {
            $history->set('filter', 'all');
        }
        $seen = [];
        $expected = array_reverse($ids);
        foreach ([1, 2, 3] as $page) {
            $history->call('gotoPage', $page)->assertSee('History Item')->assertSee('Deleted account')->assertDontSee('Unrelated history item');
            $rows = $history->viewData($collection);
            $this->assertSame(23, $rows->total());
            $this->assertSame($page, $rows->currentPage());
            $pageIds = $rows->getCollection()->pluck('id')->all();
            $this->assertSame(array_slice($expected, ($page - 1) * 10, 10), $pageIds);
            $seen = array_merge($seen, $pageIds);
        }
        $this->assertSame($expected, $seen);
        $this->assertCount(23, array_unique($seen));

        if ($interface === 'owner') {
            $history->set('filter', $collection === 'rentals' ? 'completed' : 'approved');
            $this->assertSame(1, $history->viewData($collection)->currentPage());
        }
        $this->get(route($interface.'.rentals.show', $otherRental))->assertForbidden();
        $this->get(route('rental-requests.chat', $otherRental->rental_request_id))->assertForbidden();
    }

    #[DataProvider('deletionTargets')]
    public function test_all_unbooked_request_statuses_remain_in_the_surviving_partys_history(string $target): void
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $listing = $this->listingFor($owner);
        $requests = [];
        foreach (RentalRequestStatus::cases() as $status) {
            $requests[] = RentalRequest::create([
                'listing_id' => $listing->id, 'renter_id' => $renter->id,
                'start_date' => now()->addDays(5), 'end_date' => now()->addDays(8), 'rental_days' => 4,
                'fulfillment_method' => 'pickup', 'rental_fee' => 400, 'commission_rate' => 10,
                'commission_amount' => 40, 'security_deposit' => 500, 'total_amount' => 940,
                'status' => $status, 'rejection_reason' => $status === RentalRequestStatus::Rejected ? 'Dates unavailable' : null,
            ]);
        }
        if ($target === 'listing') {
            $listing->delete();
        } else {
            ($target === 'owner' ? $owner : $renter)->delete();
        }

        $this->assertDatabaseCount('rentals', 0);
        $this->assertDatabaseCount('rental_requests', count(RentalRequestStatus::cases()));
        foreach (['owner' => $owner, 'renter' => $renter] as $interface => $user) {
            if ($user->trashed()) {
                continue;
            }
            $history = Livewire::actingAs($user)->test($interface === 'owner' ? OwnerRequestsIndex::class : RenterRequestsIndex::class);
            if ($interface === 'owner') {
                $history->set('filter', 'all');
            }
            $rows = $history->viewData('rentalRequests');
            $this->assertSame(count($requests), $rows->total());
            $this->assertSame(array_reverse(array_map(fn ($request) => $request->id, $requests)), $rows->getCollection()->pluck('id')->all());
            $this->assertEqualsCanonicalizing(RentalRequestStatus::cases(), $rows->getCollection()->pluck('status')->all());
            $history->assertSee('History Item')->assertSee('Dates unavailable')->assertSee('940.00');
            if ($target !== 'listing') {
                $history->assertSee('Deleted account')->assertDontSee('@example.invalid');
            }
            if ($listing->fresh()->trashed()) {
                $history->assertSee($interface === 'owner' ? 'Listing removed' : 'listing removed');
            }
        }
    }
}
