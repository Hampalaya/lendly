<?php

namespace Tests\Feature;

use App\Enums\ListingAvailabilityStatus;
use App\Enums\ListingStatus;
use App\Enums\RentalAdminActionType;
use App\Enums\RentalRequestStatus;
use App\Enums\RentalStatus;
use App\Livewire\Listings\Show as ListingShow;
use App\Livewire\Owner\Listings\Index as OwnerListingsIndex;
use App\Models\Category;
use App\Models\Listing;
use App\Models\OfflinePaymentSetting;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\SecurityDeposit;
use App\Models\User;
use App\Services\RentalAdministration;
use App\Services\RentalAgreement;
use App\Services\RentalLifecycle;
use App\Services\RentalPayments;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class PaymentReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay()->addHours(12));
        Storage::fake('local');
        OfflinePaymentSetting::create([
            'enabled' => true, 'method' => 'bank_transfer', 'instructions' => 'Test bank transfer instructions.',
        ]);
    }

    private function fixture(): array
    {
        $owner = User::factory()->owner()->create();
        $renter = User::factory()->renter()->create();
        $admin = User::factory()->admin()->create();
        $category = Category::create(['name' => 'Reservation tools', 'slug' => 'reservation-tools']);
        $listing = Listing::create([
            'owner_id' => $owner->id, 'category_id' => $category->id,
            'name' => 'Reserved item', 'description' => 'A rentable item.',
            'condition' => 'good', 'price_per_day' => 100, 'security_deposit' => 500,
            'location' => 'Manila', 'max_rental_duration_days' => 30,
            'status' => ListingStatus::Published, 'is_available' => true,
        ]);

        return [$owner, $renter, $admin, $listing, $this->unpaidRental($listing, $renter)];
    }

    private function unpaidRental(Listing $listing, User $renter, int $daysAhead = 3): Rental
    {
        $request = RentalRequest::create([
            'listing_id' => $listing->id, 'renter_id' => $renter->id,
            'start_date' => now()->addDays($daysAhead), 'end_date' => now()->addDays($daysAhead + 2),
            'rental_days' => 3, 'fulfillment_method' => 'pickup',
            'rental_fee' => 300, 'commission_rate' => 10, 'commission_amount' => 30,
            'security_deposit' => 500, 'total_amount' => 830,
            'status' => RentalRequestStatus::Approved,
            'owner_terms_accepted_at' => now(), 'renter_terms_accepted_at' => now(),
        ]);
        $request->update(['agreement_terms' => ['payment_review' => 'platform_legacy'] + RentalAgreement::termsFor($request)]);

        return Rental::create([
            'rental_request_id' => $request->id, 'listing_id' => $listing->id,
            'owner_id' => $listing->owner_id, 'renter_id' => $renter->id,
            ...$request->only([
                'start_date', 'end_date', 'rental_days', 'fulfillment_method',
                'rental_fee', 'commission_rate', 'commission_amount', 'security_deposit', 'total_amount',
            ]),
            'status' => RentalStatus::PaymentPending,
        ]);
    }

    private function submit(Rental $rental, User $renter): PaymentSubmission
    {
        $proof = UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");

        return RentalPayments::submit($rental, $renter, 'RESERVATION-'.$rental->id, $proof);
    }

    private function verify(Rental $rental, User $renter, User $admin): void
    {
        $submission = $this->submit($rental, $renter);
        RentalAdministration::perform($rental, $admin, RentalAdminActionType::PaymentVerified, 'Full payment received.', $submission->id, true);
    }

    public function test_recorded_payment_changes_a_hold_to_reserved_and_displays_the_agreed_dates(): void
    {
        [$owner, $renter, $admin, $listing, $rental] = $this->fixture();
        $page = Livewire::test(ListingShow::class, ['listing' => $listing])
            ->assertSee('wire:poll.15s.visible', false)
            ->assertViewHas('availability', ListingAvailabilityStatus::OnHold)
            ->assertViewHas('paidReservationRanges', fn ($ranges) => $ranges->isEmpty());
        $this->assertTrue($listing->hasApprovedOverlap($rental->start_date, $rental->end_date));
        $submission = $this->submit($rental, $renter);
        $this->assertSame(ListingAvailabilityStatus::OnHold, $listing->fresh()->availabilityStatus());
        $this->assertSame(0, Payment::count());

        RentalAdministration::perform($rental, $admin, RentalAdminActionType::PaymentVerified, 'Full payment received.', $submission->id, true);

        $rental->refresh();
        $this->assertSame(RentalStatus::Paid, $rental->status);
        $this->assertSame(ListingAvailabilityStatus::Reserved, $listing->fresh()->availabilityStatus());
        $this->assertSame($rental->paid_at->toDateTimeString(), $rental->payment->paid_at->toDateTimeString());
        $this->assertSame([$rental->id], $listing->paidReservations()->pluck('id')->all());
        $this->assertSame(ListingStatus::Published, $listing->fresh()->status);
        $this->assertTrue($listing->fresh()->is_available);
        $page->call('$refresh')
            ->assertViewHas('availability', ListingAvailabilityStatus::Reserved)
            ->assertViewHas('paidReservationRanges', fn ($ranges) => $ranges->count() === 1
                && $ranges->first()->start_date->equalTo($rental->start_date)
                && $ranges->first()->end_date->equalTo($rental->end_date))
            ->assertSee('Reserved Oct 18, 2026 – Oct 20, 2026');
        $this->withSession(['active_interface' => 'owner']);
        $ownerPage = Livewire::actingAs($owner)->test(OwnerListingsIndex::class)
            ->assertSee('wire:poll.15s.visible', false)
            ->assertViewHas('listings', fn ($listings) => $listings->first()->availabilityStatus() === ListingAvailabilityStatus::Reserved);
        $this->assertStringContainsString('Reserved Oct 18, 2026 – Oct 20, 2026',
            html_entity_decode(strip_tags($ownerPage->html()), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function test_rejected_payment_proof_does_not_reserve_the_item(): void
    {
        [$owner, $renter, $admin, $listing, $rental] = $this->fixture();
        $submission = $this->submit($rental, $renter);
        RentalAdministration::perform($rental, $admin, RentalAdminActionType::PaymentRejected, 'Funds were not received.', $submission->id);

        $this->assertSame('rejected', $submission->fresh()->status);
        $this->assertSame(RentalStatus::PaymentPending, $rental->fresh()->status);
        $this->assertSame(ListingAvailabilityStatus::OnHold, $listing->fresh()->availabilityStatus());
        $this->assertFalse($listing->paidReservations()->exists());
        $this->assertSame(0, Payment::count());
    }

    public function test_a_paid_status_or_timestamp_without_a_transaction_is_not_a_reservation(): void
    {
        [$owner, $renter, $admin, $listing, $rental] = $this->fixture();
        $rental->update(['status' => RentalStatus::Paid, 'paid_at' => now()]);

        $this->assertSame(ListingAvailabilityStatus::OnHold, $listing->fresh()->availabilityStatus());
        $this->assertFalse($listing->paidReservations()->exists());
        $payment = Payment::create([
            'rental_id' => $rental->id, 'transaction_reference' => Payment::generateReference(),
            'amount' => $rental->total_amount, 'paid_at' => now(),
        ]);
        $this->assertSame(ListingAvailabilityStatus::Reserved, $listing->fresh()->availabilityStatus());
        $payment->update(['status' => 'refunded']);
        $this->assertSame(ListingAvailabilityStatus::OnHold, $listing->fresh()->availabilityStatus());
    }

    public function test_reserved_status_covers_the_full_agreed_duration_including_the_end_date(): void
    {
        [$owner, $renter, $admin, $listing, $rental] = $this->fixture();
        $this->verify($rental, $renter, $admin);
        $this->assertSame(ListingAvailabilityStatus::Reserved, $listing->fresh()->availabilityStatus());
        $this->travelTo($rental->start_date->copy()->startOfDay());
        $this->assertSame(ListingAvailabilityStatus::Reserved, $listing->fresh()->availabilityStatus());
        $rental->update(['status' => RentalStatus::Active]);
        $this->travelTo(Carbon::parse($rental->end_date->toDateString(), 'Asia/Manila')->endOfDay());
        $this->assertSame(ListingAvailabilityStatus::Reserved, $listing->fresh()->availabilityStatus());
        $this->assertTrue($listing->hasApprovedOverlap($rental->end_date, $rental->end_date));

        $this->travelTo(Carbon::parse($rental->end_date->toDateString(), 'Asia/Manila')->addDay()->startOfDay());
        $this->assertFalse($listing->paidReservations()->exists());
        $this->assertSame(ListingAvailabilityStatus::Rented, $listing->fresh()->availabilityStatus(), 'An unreturned item must not become Available when its reservation ends.');
    }

    public function test_an_unused_paid_reservation_expires_after_its_agreed_end_date(): void
    {
        [$owner, $renter, $admin, $listing, $rental] = $this->fixture();
        $this->verify($rental, $renter, $admin);
        $this->travelTo(Carbon::parse($rental->end_date->toDateString(), 'Asia/Manila')->addDay()->startOfDay());

        $this->assertSame(ListingAvailabilityStatus::Available, $listing->fresh()->availabilityStatus());
        $this->assertFalse($listing->paidReservations()->exists());
    }

    public function test_cancellation_releases_only_the_cancelled_paid_reservation(): void
    {
        [$owner, $renter, $admin, $listing, $first] = $this->fixture();
        $second = $this->unpaidRental($listing, $renter, 10);
        $this->verify($first, $renter, $admin);
        $this->verify($second, $renter, $admin);
        $this->actingAs($renter);
        $page = Livewire::test(ListingShow::class, ['listing' => $listing]);

        RentalLifecycle::cancel($first);
        $this->assertSame(ListingAvailabilityStatus::Reserved, $listing->fresh()->availabilityStatus());
        $this->assertSame([$second->id], $listing->paidReservations()->pluck('id')->all());
        $this->assertFalse($listing->hasApprovedOverlap($first->start_date, $first->end_date));
        $this->assertTrue($listing->hasApprovedOverlap($second->start_date, $second->end_date));
        $page->call('$refresh')->assertViewHas('paidReservationRanges', fn ($ranges) => $ranges->count() === 1
            && $ranges->first()->start_date->equalTo($second->start_date));

        RentalLifecycle::cancel($second);
        $this->assertSame(ListingAvailabilityStatus::Available, $listing->fresh()->availabilityStatus());
        $this->assertFalse($listing->paidReservations()->exists());
        $this->assertSame(2, Payment::count(), 'Recorded transactions remain in history after cancellation.');
        $page->call('$refresh')
            ->assertViewHas('availability', ListingAvailabilityStatus::Available)
            ->assertViewHas('paidReservationRanges', fn ($ranges) => $ranges->isEmpty());
    }

    public function test_confirmed_return_releases_the_reservation_even_before_the_agreed_end_date(): void
    {
        [$owner, $renter, $admin, $listing, $rental] = $this->fixture();
        $this->verify($rental, $renter, $admin);
        RentalLifecycle::confirmPickup($rental, $owner);
        RentalLifecycle::confirmPickup($rental, $renter);
        $this->assertSame(ListingAvailabilityStatus::Reserved, $listing->fresh()->availabilityStatus());
        RentalLifecycle::confirmReturn($rental, $owner);

        $this->assertSame(RentalStatus::Returned, $rental->fresh()->status);
        $this->assertNull($rental->fresh()->return_confirmed_by_renter_at);
        $this->assertSame(ListingAvailabilityStatus::Unavailable, $listing->fresh()->availabilityStatus());
        $this->assertTrue($listing->fresh()->reopen_required);
        $this->assertFalse($listing->hasApprovedOverlap($rental->start_date, $rental->end_date));
    }

    public function test_pausing_new_requests_does_not_erase_a_paid_reservation(): void
    {
        [$owner, $renter, $admin, $listing, $rental] = $this->fixture();
        $listing->update(['is_available' => false]);
        $this->verify($rental, $renter, $admin);

        $this->assertSame(ListingAvailabilityStatus::Reserved, $listing->fresh()->availabilityStatus());
        $this->assertFalse($listing->fresh()->is_available);
        $this->actingAs($renter);
        RentalLifecycle::cancel($rental);
        $this->assertSame(ListingAvailabilityStatus::Unavailable, $listing->fresh()->availabilityStatus());
        $this->assertFalse($listing->fresh()->is_available);
    }

    public function test_failed_payment_verification_rolls_back_the_transaction_and_reservation(): void
    {
        [$owner, $renter, $admin, $listing, $rental] = $this->fixture();
        $submission = $this->submit($rental, $renter);
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Cannot store payment notification'));

        try {
            RentalAdministration::perform($rental, $admin, RentalAdminActionType::PaymentVerified, 'Full payment received.', $submission->id, true);
            $this->fail('Payment notification failure must roll back verification.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Cannot store payment notification', $exception->getMessage());
        }

        $this->assertSame(RentalStatus::PaymentPending, $rental->fresh()->status);
        $this->assertNull($rental->fresh()->paid_at);
        $this->assertSame('pending', $submission->fresh()->status);
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, SecurityDeposit::count());
        $this->assertSame(0, $rental->adminActions()->count());
        $this->assertSame(ListingAvailabilityStatus::OnHold, $listing->fresh()->availabilityStatus());
        $this->assertFalse($listing->paidReservations()->exists());
    }
}
