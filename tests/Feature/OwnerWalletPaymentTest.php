<?php

namespace Tests\Feature;

use App\Enums\RentalAdminActionType;
use App\Enums\RentalStatus;
use App\Livewire\Renter\Rentals\Show as RenterShow;
use App\Models\Category;
use App\Models\Listing;
use App\Models\OfflinePaymentSetting;
use App\Models\OwnerPaymentSetting;
use App\Models\PaymentSubmission;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\User;
use App\Services\RentalAdministration;
use App\Services\RentalAgreement;
use App\Services\RentalPayments;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesPaymentProof;
use Tests\TestCase;

class OwnerWalletPaymentTest extends TestCase
{
    use CreatesPaymentProof;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::set('local', Storage::fake('wallet-payments-'.Str::random(12)));
        OfflinePaymentSetting::create(['id' => 1, 'method' => 'bank_transfer', 'instructions' => 'Platform bank instructions', 'enabled' => true]);
    }

    public function test_first_display_freezes_this_owners_private_wallet_destination(): void
    {
        $rental = $this->rental();
        $settings = $this->wallet($rental->owner);
        $other = $this->rental();
        $this->wallet($other->owner, ['provider' => 'gcash', 'account_name' => 'Another owner']);

        $destination = RentalPayments::destinationFor($rental, $rental->renter);

        $this->assertSame([
            'source' => 'owner_wallet', 'method' => 'maya', 'payee_user_id' => $rental->owner_id,
            'account_name' => 'Test Owner', 'instructions' => 'Include booking reference', 'qr_path' => $settings->qr_path,
        ], $destination);
        $this->assertSame($destination, $rental->fresh()->payment_destination);
        $settings->update(['provider' => 'gcash', 'account_name' => 'Changed owner', 'qr_path' => 'owner-wallet-qr/new.png', 'enabled' => false]);
        $this->assertSame($destination, RentalPayments::destinationFor($rental->fresh(), $rental->renter));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_submission_copies_frozen_destination_and_notifies_only_its_recipient(): void
    {
        $rental = $this->rental();
        $this->wallet($rental->owner);
        $destination = RentalPayments::destinationFor($rental, $rental->renter);
        OwnerPaymentSetting::currentFor($rental->owner_id)->update(['provider' => 'gcash', 'account_name' => 'New payee']);
        OfflinePaymentSetting::current()->update(['enabled' => false]);
        $admin = User::factory()->admin()->create();
        $stranger = User::factory()->create();

        $submission = $this->submit($rental);

        $this->assertSame($destination, $submission->payment_destination);
        $this->assertSame('maya', $submission->method);
        $this->assertSame('Include booking reference', $submission->instructions);
        $this->assertSame('830.00', $submission->amount);
        $this->assertNull($submission->recipient_confirmed_at);
        $this->assertSame(RentalStatus::PaymentPending, $rental->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('security_deposits', 0);
        $this->assertSame(1, $rental->owner->notifications()->where('data->type', 'payment_submitted')->count());
        $this->assertSame(0, $admin->notifications()->where('data->type', 'payment_submitted')->count());
        $this->assertSame(0, $stranger->notifications()->count());
    }

    public function test_optional_owner_instructions_can_be_blank_when_submitting_proof(): void
    {
        $rental = $this->rental();
        $this->wallet($rental->owner, ['instructions' => null]);

        $submission = $this->submit($rental);

        $this->assertSame('', $submission->instructions);
        $this->assertSame('', $submission->payment_destination['instructions']);
        $this->assertSame('owner_wallet', $submission->payment_destination['source']);
        $this->assertSame(RentalStatus::PaymentPending, $rental->fresh()->status);
    }

    #[DataProvider('unavailableWallets')]
    public function test_unavailable_owner_wallet_falls_back_to_platform_without_exposing_qr(string $reason): void
    {
        $rental = $this->rental();
        $settings = $this->wallet($rental->owner);
        match ($reason) {
            'disabled' => $settings->update(['enabled' => false]),
            'missing qr' => Storage::disk('local')->delete($settings->qr_path),
            'empty qr' => Storage::disk('local')->put($settings->qr_path, ''),
            'invalid provider' => $settings->update(['provider' => 'unknown']),
            'missing account' => $settings->update(['account_name' => '']),
            'unverified' => $rental->owner->forceFill(['email_verified_at' => null])->save(),
            'suspended' => $rental->owner->update(['status' => 'suspended']),
            'deleted' => $rental->owner->delete(),
        };

        $destination = RentalPayments::destinationFor($rental, $rental->renter);

        $this->assertSame(['source' => 'platform', 'method' => 'bank_transfer', 'instructions' => 'Platform bank instructions'], $destination);
        $this->assertArrayNotHasKey('qr_path', $destination);
    }

    public static function unavailableWallets(): array
    {
        return array_map(fn ($reason) => [$reason], ['disabled', 'missing qr', 'empty qr', 'invalid provider', 'missing account', 'unverified', 'suspended', 'deleted']);
    }

    public function test_unavailable_destinations_do_not_bind_and_can_be_configured_later(): void
    {
        $rental = $this->rental();
        OfflinePaymentSetting::current()->update(['enabled' => false]);

        $this->assertNull(RentalPayments::destinationFor($rental, $rental->renter));
        $this->assertNull($rental->fresh()->payment_destination);
        $this->wallet($rental->owner);
        $this->assertSame('owner_wallet', RentalPayments::destinationFor($rental, $rental->renter)['source']);
    }

    public function test_no_other_viewer_or_unaccepted_or_cancelled_booking_can_bind_a_destination(): void
    {
        $rental = $this->rental();
        $this->wallet($rental->owner);
        foreach ([$rental->owner, User::factory()->create(), User::factory()->admin()->create()] as $viewer) {
            $this->assertNull(RentalPayments::destinationFor($rental, $viewer));
        }
        $rental->rentalRequest->update(['owner_terms_accepted_at' => null]);
        $this->assertNull(RentalPayments::destinationFor($rental, $rental->renter));
        $rental->rentalRequest->update(['owner_terms_accepted_at' => now()]);
        $rental->update(['status' => RentalStatus::Cancelled]);
        $this->assertNull(RentalPayments::destinationFor($rental, $rental->renter));
        $this->assertNull($rental->fresh()->payment_destination);
    }

    public function test_platform_snapshot_stays_platform_when_owner_later_enables_wallet(): void
    {
        $rental = $this->rental();
        $destination = RentalPayments::destinationFor($rental, $rental->renter);
        $this->wallet($rental->owner);
        OfflinePaymentSetting::current()->update(['method' => 'cash', 'instructions' => 'New platform instructions']);

        $submission = $this->submit($rental);

        $this->assertSame($destination, $submission->payment_destination);
        $this->assertSame('bank_transfer', $submission->method);
        $this->assertSame('Platform bank instructions', $submission->instructions);
    }

    public function test_disabled_platform_settings_block_new_proof_even_after_instructions_were_frozen(): void
    {
        $rental = $this->rental();
        $destination = RentalPayments::destinationFor($rental, $rental->renter);
        OfflinePaymentSetting::current()->update(['enabled' => false]);

        $this->assertNull(RentalPayments::destinationFor($rental, $rental->renter));
        $this->assertSame($destination, $rental->fresh()->payment_destination);

        $this->validationRejects(fn () => $this->submit($rental));

        $this->assertDatabaseCount('payment_submissions', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_legacy_platform_submission_can_be_reviewed_without_owner_receipt(): void
    {
        $rental = $this->rental();
        $submission = $this->submit($rental);
        $submission->forceFill(['payment_destination' => null])->save();
        $this->wallet($rental->owner);

        RentalAdministration::perform($rental, User::factory()->admin()->create(), RentalAdminActionType::PaymentVerified, 'Legacy bank transfer received', $submission->id, true);

        $this->assertSame(RentalStatus::Paid, $rental->fresh()->status);
        $this->assertSame('bank_transfer', $rental->fresh()->payment->method);
        $this->assertNull($submission->fresh()->recipient_confirmed_at);
    }

    public function test_owner_must_explicitly_confirm_receipt_to_finalize_payment(): void
    {
        $rental = $this->rental();
        $this->wallet($rental->owner);
        $submission = $this->submit($rental);

        $this->validationRejects(fn () => RentalPayments::confirmOwnerReceipt($rental, $rental->owner, $submission->id, false));
        $this->assertNull($submission->fresh()->recipient_confirmed_at);
        RentalPayments::confirmOwnerReceipt($rental, $rental->owner, $submission->id, true);

        $this->assertNotNull($submission->fresh()->recipient_confirmed_at);
        $this->assertSame('verified', $submission->fresh()->status);
        $this->assertSame(RentalStatus::Paid, $rental->fresh()->status);
        $this->assertNotNull($rental->fresh()->paid_at);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('security_deposits', 1);
        $confirmed = $submission->fresh()->recipient_confirmed_at;
        $this->travel(1)->minutes();
        RentalPayments::confirmOwnerReceipt($rental, $rental->owner, $submission->id, true);
        $this->assertTrue($confirmed->equalTo($submission->fresh()->recipient_confirmed_at));
    }

    public function test_only_owner_confirmation_finalizes_wallet_payment_without_admin(): void
    {
        $rental = $this->rental();
        $this->wallet($rental->owner);
        $submission = $this->submit($rental);
        $admin = User::factory()->admin()->create();

        try {
            RentalAdministration::perform($rental, $admin, RentalAdminActionType::PaymentVerified, 'Matched wallet proof', $submission->id, true);
            $this->fail('Admin must not verify owner payments');
        } catch (AuthorizationException) {
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('rental_admin_actions', 0);
        RentalPayments::confirmOwnerReceipt($rental, $rental->owner, $submission->id, true);

        $this->assertSame(RentalStatus::Paid, $rental->fresh()->status);
        $this->assertSame('maya', $rental->fresh()->payment->method);
        $this->assertSame('verified', $submission->fresh()->status);
        $this->assertDatabaseCount('rental_admin_actions', 0);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('security_deposits', 1);
    }

    public function test_owner_can_reject_wallet_proof_without_confirming_receipt(): void
    {
        $rental = $this->rental();
        $this->wallet($rental->owner);
        $submission = $this->submit($rental);

        RentalPayments::rejectOwnerProof($rental, $rental->owner, $submission->id, 'Unreadable proof');

        $this->assertSame('rejected', $submission->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    #[DataProvider('invalidReceipts')]
    public function test_invalid_or_stale_receipt_acknowledgements_cannot_change_money_state(string $reason): void
    {
        $rental = $this->rental();
        $this->wallet($rental->owner);
        $submission = $this->submit($rental);
        $owner = $rental->owner;
        match ($reason) {
            'renter' => $owner = $rental->renter,
            'stranger' => $owner = User::factory()->create(),
            'admin' => $owner = User::factory()->admin()->create(),
            'suspended' => $owner->update(['status' => 'suspended']),
            'stale owner' => $owner->fresh()->update(['status' => 'suspended']),
            'unverified' => $owner->forceFill(['email_verified_at' => null])->save(),
            'deleted' => $owner->delete(),
            'missing proof' => Storage::disk('local')->delete($submission->proof_path),
            'empty proof' => Storage::disk('local')->put($submission->proof_path, ''),
            'wrong amount' => $submission->update(['amount' => 829]),
            'reviewed' => $submission->update(['status' => 'rejected']),
            'cancelled' => $rental->update(['status' => RentalStatus::Cancelled]),
            'unaccepted' => $rental->rentalRequest->update(['owner_terms_accepted_at' => null]),
        };

        try {
            RentalPayments::confirmOwnerReceipt($rental, $owner, $submission->id, true);
            $this->fail('Receipt acknowledgement must be rejected.');
        } catch (ValidationException|HttpException) {
            $this->assertNull($submission->fresh()->recipient_confirmed_at);
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('security_deposits', 0);
    }

    public static function invalidReceipts(): array
    {
        return array_map(fn ($reason) => [$reason], ['renter', 'stranger', 'admin', 'suspended', 'stale owner', 'unverified', 'deleted', 'missing proof', 'empty proof', 'wrong amount', 'reviewed', 'cancelled', 'unaccepted']);
    }

    public function test_centralized_proof_cannot_be_acknowledged_by_owner(): void
    {
        $rental = $this->rental();
        $submission = $this->submit($rental);

        try {
            RentalPayments::confirmOwnerReceipt($rental, $rental->owner, $submission->id, true);
            $this->fail('Centralized payment must not be acknowledged by an owner.');
        } catch (HttpException) {
            $this->assertNull($submission->fresh()->recipient_confirmed_at);
        }
    }

    public function test_owner_cannot_confirm_another_bookings_submission(): void
    {
        $rental = $this->rental();
        $this->wallet($rental->owner);
        $submission = $this->submit($rental);
        $other = $this->rental();

        try {
            RentalPayments::confirmOwnerReceipt($other, $rental->owner, $submission->id, true);
            $this->fail('Submission must belong to the selected booking.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
            $this->assertNull($submission->fresh()->recipient_confirmed_at);
        }
    }

    public function test_new_bookings_use_an_available_wallet_when_the_first_wallet_is_disabled_or_missing(): void
    {
        $rental = $this->rental();
        $this->wallet($rental->owner, ['enabled' => false]);
        $missing = $this->wallet($rental->owner);
        Storage::disk('local')->delete($missing->qr_path);
        $available = $this->wallet($rental->owner, ['provider' => 'grabpay']);

        $destination = RentalPayments::destinationFor($rental, $rental->renter);

        $this->assertSame('grabpay', $destination['method']);
        $this->assertSame($available->qr_path, $destination['qr_path']);
    }

    public function test_renter_can_choose_another_owners_wallet_and_submit_proof_for_that_destination(): void
    {
        $rental = $this->rental();
        $first = $this->wallet($rental->owner);
        $second = $this->wallet($rental->owner, ['provider' => 'shopeepay', 'account_name' => 'Second recipient']);
        $stranger = $this->wallet(User::factory()->create(), ['account_name' => 'Private stranger wallet']);

        $component = Livewire::actingAs($rental->renter)->test(RenterShow::class, ['rental' => $rental])
            ->assertSee('ShopeePay')->assertDontSee($stranger->account_name)
            ->call('selectWallet', $second->id)->assertHasNoErrors()->assertSee('Second recipient');
        $this->assertSame($second->qr_path, $rental->fresh()->payment_destination['qr_path']);
        $component->call('selectWallet', $stranger->id)->assertHasErrors('payment_wallet');
        $this->assertSame($second->qr_path, $rental->fresh()->payment_destination['qr_path']);

        $component->set('payment_reference', 'SECOND-WALLET')->set('payment_proof', $this->paymentProof())
            ->call('submitPaymentProof')->assertHasNoErrors();
        $submission = $rental->paymentSubmissions()->sole();
        $this->assertSame('shopeepay', $submission->method);
        $this->assertSame($second->qr_path, $submission->payment_destination['qr_path']);
        $component->call('selectWallet', $first->id)->assertForbidden();
        $this->assertSame($second->qr_path, $rental->fresh()->payment_destination['qr_path']);
    }

    public function test_changing_wallet_is_rejected_after_any_payment_proof_or_for_another_renter(): void
    {
        $rental = $this->rental();
        $this->wallet($rental->owner);
        $second = $this->wallet($rental->owner, ['provider' => 'coins_ph']);
        $submission = $this->submit($rental);
        $submission->update(['status' => 'rejected']);
        $original = $rental->fresh()->payment_destination;

        $this->validationRejects(fn () => RentalPayments::selectWallet($rental, $rental->renter, $second->id));
        $this->assertSame($original, $rental->fresh()->payment_destination);
        $this->expectException(AuthorizationException::class);
        RentalPayments::selectWallet($rental, User::factory()->create(), $second->id);
    }

    private function wallet(User $owner, array $attributes = []): OwnerPaymentSetting
    {
        $path = 'owner-wallet-qr/'.$owner->id.'-'.Str::random(12).'.png';
        Storage::disk('local')->put($path, 'Synthetic wallet QR image');

        return OwnerPaymentSetting::create(array_replace([
            'user_id' => $owner->id, 'provider' => 'maya', 'account_name' => 'Test Owner',
            'instructions' => 'Include booking reference', 'qr_path' => $path, 'enabled' => true,
        ], $attributes));
    }

    private function rental(): Rental
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $category = Category::firstOrCreate(['slug' => 'owner-wallet'], ['name' => 'Tools']);
        $listing = Listing::create([
            'owner_id' => $owner->id, 'category_id' => $category->id, 'name' => 'Wallet payment item',
            'description' => 'Item', 'condition' => 'good', 'price_per_day' => 100,
            'security_deposit' => 500, 'location' => 'Manila', 'max_rental_duration_days' => 10,
            'status' => 'published', 'is_available' => true, 'pickup_available' => true,
        ]);
        $terms = [
            'listing_id' => $listing->id, 'renter_id' => $renter->id,
            'start_date' => now()->addDays(3), 'end_date' => now()->addDays(5), 'rental_days' => 3,
            'fulfillment_method' => 'pickup', 'rental_fee' => 300, 'commission_rate' => 10,
            'commission_amount' => 30, 'security_deposit' => 500, 'total_amount' => 830,
        ];
        $request = RentalRequest::create($terms + ['status' => 'approved', 'renter_terms_accepted_at' => now(), 'owner_terms_accepted_at' => now()]);
        $request->update(['agreement_terms' => ['payment_review' => 'platform_legacy'] + RentalAgreement::termsFor($request)]);

        return Rental::create($terms + ['rental_request_id' => $request->id, 'owner_id' => $owner->id]);
    }

    private function submit(Rental $rental): PaymentSubmission
    {
        return RentalPayments::submit($rental, $rental->renter, 'WALLET-'.$rental->id, $this->paymentProof());
    }

    private function validationRejects(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Payment validation must fail.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
