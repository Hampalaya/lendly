<?php

namespace Tests\Feature;

use App\Enums\RentalRequestStatus;
use App\Enums\RentalStatus;
use App\Livewire\Owner\RentalRequests\Index as OwnerRequests;
use App\Livewire\Owner\Rentals\Show as OwnerBooking;
use App\Livewire\Renter\RentalRequests\Index as RenterRequests;
use App\Livewire\Renter\Rentals\Show as RenterBooking;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\SecurityDeposit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CancellationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function requestFixture(string $status = 'requested'): array
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $category = Category::create(['name' => 'Tools', 'slug' => 'tools']);
        $listing = Listing::create([
            'owner_id' => $owner->id, 'category_id' => $category->id,
            'name' => 'History drill', 'description' => 'A drill', 'condition' => 'good',
            'price_per_day' => 100, 'security_deposit' => 500, 'location' => 'Manila',
            'max_rental_duration_days' => 10, 'status' => 'published',
            'is_available' => true, 'pickup_available' => true,
        ]);
        $request = RentalRequest::create([
            'listing_id' => $listing->id, 'renter_id' => $renter->id,
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(3), 'rental_days' => 3,
            'fulfillment_method' => 'pickup', 'rental_fee' => 1000, 'commission_rate' => 10,
            'commission_amount' => 100, 'security_deposit' => 500, 'total_amount' => 1600,
            'status' => $status,
        ]);

        return [$owner, $renter, $listing, $request];
    }

    private function bookingFor(User $owner, RentalRequest $request, string $status = 'paid'): Rental
    {
        $request->update(['status' => RentalRequestStatus::Approved]);
        $rental = Rental::create([
            ...$request->only([
                'listing_id', 'renter_id', 'start_date', 'end_date', 'rental_days',
                'fulfillment_method', 'rental_fee', 'commission_rate', 'commission_amount',
                'security_deposit', 'total_amount',
            ]),
            'rental_request_id' => $request->id, 'owner_id' => $owner->id, 'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
        if ($status === 'paid') {
            SecurityDeposit::create(['rental_id' => $rental->id, 'amount' => 500]);
        }

        return $rental;
    }

    private function assertBothRequestHistories(User $owner, User $renter, RentalRequest $request): void
    {
        foreach (['owner' => $owner, 'renter' => $renter] as $interface => $user) {
            if ($user->trashed()) {
                continue;
            }
            $page = Livewire::actingAs($user)->test($interface === 'owner' ? OwnerRequests::class : RenterRequests::class);
            if ($interface === 'owner') {
                $page->set('filter', 'cancelled');
            }
            $page->assertSee('History drill')
                ->assertSee($request->cancellation_reason ?? 'No reason provided.')
                ->assertSee('Cancellation fee: ₱'.number_format($request->cancellation_fee, 2))
                ->assertSee('Cancelled '.$request->cancelled_at->copy()->timezone('Asia/Manila')->format('M d, Y \a\t g:i A'));
            $this->assertSame($request->id, $page->viewData('rentalRequests')->first()->id);
        }
    }

    public static function requestCancellations(): array
    {
        return [
            'pending with reason' => ['requested', '  Plans changed  ', 'Plans changed'],
            'pending without reason' => ['requested', '', null],
            'approved without booking' => ['approved', 'No longer needed', 'No longer needed'],
            'whitespace reason' => ['requested', '   ', null],
            'zero reason' => ['requested', '0', '0'],
            'full length reason' => ['requested', str_repeat('a', 500), str_repeat('a', 500)],
        ];
    }

    #[DataProvider('requestCancellations')]
    public function test_request_cancellation_records_details_in_both_histories(string $status, string $reason, ?string $savedReason): void
    {
        $this->travelTo(now()->startOfSecond());
        [$owner, $renter, , $request] = $this->requestFixture($status);
        Livewire::actingAs($renter)->test(RenterRequests::class)
            ->call('startCancelling', $request->id)
            ->assertSee('Reason for cancellation (optional)')
            ->set('cancellation_reason', $reason)
            ->call('cancel', $request->id)->assertHasNoErrors()
            ->assertSet('cancelling', null)->assertSet('cancellation_reason', '');

        $request->refresh();
        $this->assertSame(RentalRequestStatus::Cancelled, $request->status);
        $this->assertSame($savedReason, $request->cancellation_reason);
        $this->assertTrue($request->cancelled_at->equalTo(now()));
        $this->assertSame('0.00', $request->cancellation_fee);
        $this->assertNull($request->rental);
        $this->assertBothRequestHistories($owner, $renter, $request);
    }

    public function test_invalid_reason_and_unauthorized_cancellation_do_not_record_history(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        Livewire::actingAs($renter)->test(RenterRequests::class)
            ->set('cancellation_reason', str_repeat('a', 501))
            ->call('cancel', $request->id)->assertHasErrors(['cancellation_reason']);
        Livewire::actingAs($owner)->test(RenterRequests::class)
            ->call('cancel', $request->id)->assertForbidden();
        $request->refresh();
        $this->assertSame(RentalRequestStatus::Requested, $request->status);
        $this->assertNull($request->cancelled_at);
        $this->assertNull($request->cancellation_reason);
        $this->assertNull($request->cancellation_fee);
    }

    public function test_repeated_cancellation_cannot_overwrite_original_history(): void
    {
        [, $renter, , $request] = $this->requestFixture();
        $page = Livewire::actingAs($renter)->test(RenterRequests::class)
            ->set('cancellation_reason', 'Original reason')->call('cancel', $request->id)->assertHasNoErrors();
        $saved = $request->fresh()->getAttributes();
        $this->travel(2)->hours();
        $page->set('cancellation_reason', 'Replacement reason')->call('cancel', $request->id)->assertForbidden();
        $this->assertSame($saved, $request->fresh()->getAttributes());
    }

    public static function bookingCancellations(): array
    {
        return [['payment_pending', '', 0], ['paid', 'Changed plans', 200], ['paid', str_repeat('b', 500), 200]];
    }

    #[DataProvider('bookingCancellations')]
    public function test_booking_cancellation_keeps_request_and_booking_history_identical(string $status, string $reason, int $expectedFee): void
    {
        $this->travelTo(now()->startOfDay());
        [$owner, $renter, , $request] = $this->requestFixture();
        // Save the policy accepted by the parties; do not use the current listing.
        $request->update(['agreement_terms' => ['cancellation_window_hours' => 48, 'cancellation_fee_percentage' => 20]]);
        $rental = $this->bookingFor($owner, $request, $status);
        if ($status === 'payment_pending') {
            $rental->update(['start_date' => now()->addDays(10)]);
        }
        Livewire::actingAs($renter)->test(RenterBooking::class, ['rental' => $rental])
            ->set('cancellation_reason', $reason)->call('cancelRental')->assertHasNoErrors();
        $rental->refresh();
        $request->refresh();
        $this->assertSame(RentalStatus::Cancelled, $rental->status);
        $this->assertSame($rental->cancellation_reason, $request->cancellation_reason);
        $this->assertSame($rental->cancellation_fee, $request->cancellation_fee);
        $this->assertTrue($request->cancelled_at->equalTo($rental->cancelled_at));
        $this->assertEquals($expectedFee, (float) $request->cancellation_fee);
        $this->assertBothRequestHistories($owner, $renter, $request);
        foreach ([$owner, $renter] as $user) {
            Livewire::actingAs($user)->test($user->is($owner) ? OwnerBooking::class : RenterBooking::class, ['rental' => $rental])
                ->assertSee($reason !== '' ? $reason : 'No reason provided.')
                ->assertSee('Cancellation fee: ₱'.number_format($expectedFee, 2))
                ->assertSee('Cancelled '.$rental->cancelled_at->copy()->timezone('Asia/Manila')->format('M d, Y \a\t g:i A'));
        }
    }

    public function test_confirmed_booking_cannot_bypass_fee_recording_through_request_cancellation(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        $rental = $this->bookingFor($owner, $request);
        Livewire::actingAs($renter)->test(RenterRequests::class)
            ->call('cancel', $request->id)->assertForbidden();
        $this->assertSame(RentalStatus::Paid, $rental->fresh()->status);
        $this->assertNull($request->fresh()->cancelled_at);
    }

    public static function deletionCases(): array
    {
        return [
            ['owner', false], ['renter', false], ['listing', false],
            ['owner', true], ['renter', true], ['listing', true],
        ];
    }

    #[DataProvider('deletionCases')]
    public function test_cancellation_history_survives_account_or_listing_removal(string $target, bool $confirmed): void
    {
        [$owner, $renter, $listing, $request] = $this->requestFixture();
        $rental = $confirmed ? $this->bookingFor($owner, $request) : null;
        if ($confirmed) {
            Livewire::actingAs($renter)->test(RenterBooking::class, ['rental' => $rental])
                ->set('cancellation_reason', 'Retained cancellation')->call('cancelRental')->assertHasNoErrors();
        } else {
            Livewire::actingAs($renter)->test(RenterRequests::class)
                ->set('cancellation_reason', 'Retained cancellation')->call('cancel', $request->id)->assertHasNoErrors();
        }
        $saved = $request->fresh()->getAttributes();
        ($target === 'listing' ? $listing : ($target === 'owner' ? $owner : $renter))->delete();
        $request->refresh();
        $this->assertSame($saved, $request->getAttributes());
        $this->assertBothRequestHistories($owner, $renter, $request);
        if ($confirmed) {
            foreach ([$owner, $renter] as $user) {
                if (! $user->trashed()) {
                    Livewire::actingAs($user)->test($user->is($owner) ? OwnerBooking::class : RenterBooking::class, ['rental' => $rental])
                        ->assertSee('Retained cancellation')->assertSee('Cancellation fee: ₱200.00');
                }
            }
        }
    }

    #[DataProvider('deletionCases')]
    public function test_physical_deletion_cannot_erase_cancellation_history(string $target, bool $confirmed): void
    {
        [$owner, $renter, $listing, $request] = $this->requestFixture();
        if ($confirmed) {
            $rental = $this->bookingFor($owner, $request);
            Livewire::actingAs($renter)->test(RenterBooking::class, ['rental' => $rental])
                ->call('cancelRental')->assertHasNoErrors();
        } else {
            Livewire::actingAs($renter)->test(RenterRequests::class)->call('cancel', $request->id)->assertHasNoErrors();
        }
        $saved = $request->fresh()->getAttributes();
        try {
            ($target === 'listing' ? $listing : ($target === 'owner' ? $owner : $renter))->forceDelete();
            $this->fail('Shared cancellation history must be retained.');
        } catch (QueryException $exception) {
            $this->assertSame($saved, $request->fresh()->getAttributes());
        }
    }

    public function test_cancellation_reason_is_escaped_and_hidden_from_other_users(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        $reason = '<script>alert("reason")</script>';
        Livewire::actingAs($renter)->test(RenterRequests::class)
            ->set('cancellation_reason', $reason)->call('cancel', $request->id)->assertHasNoErrors();
        $this->assertBothRequestHistories($owner, $renter, $request->fresh());
        Livewire::actingAs($owner)->test(OwnerRequests::class)->set('filter', 'cancelled')->assertDontSeeHtml($reason);
        $stranger = User::factory()->create();
        foreach ([OwnerRequests::class, RenterRequests::class] as $component) {
            $page = Livewire::actingAs($stranger)->test($component);
            if ($component === OwnerRequests::class) {
                $page->set('filter', 'cancelled');
            }
            $page->assertDontSee('History drill')->assertDontSee($reason);
        }
    }
}
