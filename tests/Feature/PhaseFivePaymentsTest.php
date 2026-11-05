<?php

namespace Tests\Feature;

use App\Enums\RentalStatus;
use App\Livewire\Renter\Rentals\Index;
use App\Livewire\Renter\Rentals\Show;
use App\Models\Category;
use App\Models\Listing;
use App\Models\OfflinePaymentSetting;
use App\Models\OwnerPaymentSetting;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\SecurityDeposit;
use App\Models\User;
use App\Services\CancellationPolicy;
use App\Services\RentalAgreement;
use App\Services\RentalLifecycle;
use App\Services\RentalPayments;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\CreatesPaymentProof;
use Tests\TestCase;

class PhaseFivePaymentsTest extends TestCase
{
    use CreatesPaymentProof;
    use RefreshDatabase;

    private function rental(bool $wallet = true): Rental
    {
        Storage::fake('local');
        $owner = User::factory()->identityVerified()->create();
        $renter = User::factory()->identityVerified()->create();
        $listing = Listing::create(['owner_id' => $owner->id,
            'category_id' => Category::firstOrCreate(['slug' => 'phase-five'], ['name' => 'Tools'])->id,
            'name' => 'Payment camera', 'description' => 'Camera', 'condition' => 'good',
            'price_per_day' => 100, 'location' => 'Manila', 'status' => 'published']);
        $details = ['listing_id' => $listing->id, 'renter_id' => $renter->id,
            'start_date' => now()->addDays(3), 'end_date' => now()->addDays(5), 'rental_days' => 3,
            'fulfillment_method' => 'pickup', 'rental_fee' => 300, 'commission_rate' => 10,
            'commission_amount' => 30, 'security_deposit' => 500, 'total_amount' => 830];
        $request = RentalRequest::create($details + ['status' => 'approved', 'owner_terms_accepted_at' => now(), 'renter_terms_accepted_at' => now()]);
        $request->update(['agreement_terms' => RentalAgreement::termsFor($request)]);
        $rental = Rental::create($details + ['owner_id' => $owner->id, 'rental_request_id' => $request->id, 'payment_due_at' => now()->addDay()]);
        if ($wallet) {
            Storage::disk('local')->put('owner-payment-qrs/test.png', 'private test QR');
            OwnerPaymentSetting::create(['user_id' => $owner->id, 'provider' => 'gcash', 'account_name' => 'Test Owner', 'qr_path' => 'owner-payment-qrs/test.png', 'enabled' => true]);
        }

        return $rental;
    }

    public function test_new_agreements_never_silently_fall_back_to_platform_collection(): void
    {
        $rental = $this->rental(false);
        OfflinePaymentSetting::create(['method' => 'bank_transfer', 'instructions' => 'Old platform account', 'enabled' => true]);
        $this->assertSame('owner', $rental->rentalRequest->agreement_terms['payment_review']);
        $this->assertNull(RentalPayments::destinationFor($rental, $rental->renter));
        $this->assertNull($rental->fresh()->payment_destination);
    }

    public function test_pending_proof_hides_pay_now_and_owner_confirmation_alone_finalizes_payment_once(): void
    {
        $rental = $this->rental();
        $admin = User::factory()->admin()->create();
        $submission = RentalPayments::submit($rental, $rental->renter, 'OWNER-PAID', $this->paymentProof());
        Livewire::actingAs($rental->renter)->test(Index::class)->assertSee('Awaiting Payment Confirmation')->assertDontSee('Pay Now');
        Livewire::test(Show::class, ['rental' => $rental])->assertSee('awaiting owner confirmation')->assertDontSee('Do you want to pay now?')->assertSee('Processing fee');
        $this->assertFalse(Gate::forUser($admin)->allows('adminVerifyPayment', $rental->fresh()));
        $this->assertFalse(Gate::forUser($admin)->allows('adminRejectPayment', $rental->fresh()));
        $this->assertSame(0, $admin->notifications()->count());
        RentalPayments::confirmOwnerReceipt($rental, $rental->owner, $submission->id, true);
        $this->assertSame(RentalStatus::Paid, $rental->fresh()->status);
        $this->assertSame($rental->owner_id, $rental->fresh()->payment->verified_by);
        $this->assertSame('verified', $submission->fresh()->status);
        RentalPayments::confirmOwnerReceipt($rental, $rental->owner, $submission->id, true);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('security_deposits', 1);
        $this->assertSame(1, $rental->renter->notifications()->where('data->type', 'payment_confirmed')->count());
        $this->assertEquals(830, $rental->fresh()->payment->amount);
    }

    public function test_owner_can_request_corrected_proof_then_confirm_a_valid_resubmission(): void
    {
        $rental = $this->rental();
        $first = RentalPayments::submit($rental, $rental->renter, 'NEEDS-CORRECTION', $this->paymentProof());
        RentalPayments::rejectOwnerProof($rental, $rental->owner, $first->id, 'Transfer reference does not match funds received');
        $this->assertSame('rejected', $first->fresh()->status);
        $this->assertTrue(Gate::forUser($rental->renter)->allows('pay', $rental->fresh()));
        $second = RentalPayments::submit($rental, $rental->renter, 'CORRECTED-PROOF', $this->paymentProof());
        RentalPayments::confirmOwnerReceipt($rental, $rental->owner, $second->id, true);
        $this->assertSame(RentalStatus::Paid, $rental->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_failed_deposit_creation_rolls_back_payment_receipt_status_and_notification(): void
    {
        $rental = $this->rental();
        $submission = RentalPayments::submit($rental, $rental->renter, 'ROLLBACK', $this->paymentProof());
        SecurityDeposit::creating(fn () => throw new RuntimeException('Deposit storage failed'));
        try {
            RentalPayments::confirmOwnerReceipt($rental, $rental->owner, $submission->id, true);
            $this->fail('Expected storage failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Deposit storage failed', $exception->getMessage());
        } finally {
            app('events')->forget('eloquent.creating: '.SecurityDeposit::class);
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertNull($submission->fresh()->recipient_confirmed_at);
        $this->assertSame('pending', $submission->fresh()->status);
        $this->assertSame(RentalStatus::PaymentPending, $rental->fresh()->status);
    }

    public function test_release_and_receipt_are_separate_confirmations_by_the_correct_parties(): void
    {
        $rental = $this->rental();
        $rental->update(['status' => 'completed']);
        $deposit = SecurityDeposit::create(['rental_id' => $rental->id, 'amount' => 500, 'status' => 'return_eligible']);
        $this->actingAs($rental->owner);
        RentalLifecycle::releaseDeposit($rental);
        $this->assertNotNull($deposit->fresh()->released_at);
        $this->assertNull($deposit->fresh()->received_at);
        Livewire::actingAs($rental->renter)->test(Show::class, ['rental' => $rental])->assertSee('Confirm deposit received')->call('confirmDepositReceipt')->assertHasNoErrors();
        $this->assertNotNull($deposit->fresh()->received_at);
        $this->assertFalse(Gate::forUser($rental->renter)->allows('confirmDepositReceipt', $rental->fresh()));
        $this->assertSame(1, $rental->owner->notifications()->where('data->title', 'Deposit receipt confirmed')->count());
    }

    public function test_another_member_cannot_confirm_receipt_and_held_deposits_cannot_be_acknowledged(): void
    {
        $rental = $this->rental();
        SecurityDeposit::create(['rental_id' => $rental->id, 'amount' => 500, 'status' => 'held']);
        $this->assertFalse(Gate::forUser($rental->renter)->allows('confirmDepositReceipt', $rental->fresh()));
        $rental->securityDeposit->update(['status' => 'released']);
        $this->expectException(AuthorizationException::class);
        RentalLifecycle::confirmDepositReceipt($rental, User::factory()->create());
    }

    public function test_cancellation_preview_does_not_add_a_fee_to_the_booking_and_only_approved_cancellation_records_it(): void
    {
        $rental = $this->rental();
        $terms = $rental->rentalRequest->agreement_terms;
        $rental->rentalRequest->update(['agreement_terms' => array_replace($terms, ['cancellation_reference_at' => now()->addHour()->toIso8601String(), 'cancellation_fee_percentage' => 20])]);
        $this->assertEquals(60, CancellationPolicy::evaluate($rental->fresh())['fee']);
        $this->assertEquals(0, $rental->fresh()->cancellation_fee);
        $this->assertEquals(830, $rental->fresh()->total_amount);
        $this->actingAs($rental->renter);
        RentalLifecycle::cancel($rental, 'Changed plans');
        $this->assertEquals(0, $rental->fresh()->cancellation_fee);
        RentalLifecycle::reviewCancellation($rental, $rental->owner, true, 'Cancellation confirmed');
        $this->assertEquals(60, $rental->fresh()->cancellation_fee);
        $this->assertSame(RentalStatus::Cancelled, $rental->fresh()->status);
    }
}
