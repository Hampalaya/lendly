<?php

namespace Tests\Feature;

use App\Livewire\Admin\Rentals\Show as AdminShow;
use App\Livewire\Owner\Rentals\Show as OwnerShow;
use App\Livewire\Renter\Rentals\Show as RenterShow;
use App\Models\Category;
use App\Models\Listing;
use App\Models\OwnerPaymentSetting;
use App\Models\PaymentSubmission;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\User;
use App\Services\RentalAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesPaymentProof;
use Tests\TestCase;

class OwnerWalletAccessTest extends TestCase
{
    use CreatesPaymentProof, RefreshDatabase;

    public function test_owner_qr_preview_is_private_and_resolves_only_the_signed_in_members_image(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $path = $this->paymentProof()->store('owner-payment-qrs', 'local');
        OwnerPaymentSetting::create(['user_id' => $owner->id, 'provider' => 'maya',
            'account_name' => 'Sample recipient', 'qr_path' => $path, 'enabled' => true]);

        $this->get(route('owner.payments.qr'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('owner.payments.qr'))->assertNotFound();
        $this->actingAs($owner)->get(route('owner.payments.qr'))->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_each_saved_wallet_preview_is_scoped_to_its_owner(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        foreach (['maya', 'gcash', 'palawanpay'] as $provider) {
            $wallet = OwnerPaymentSetting::create(['user_id' => $owner->id, 'provider' => $provider,
                'account_name' => 'Sample recipient', 'qr_path' => $this->paymentProof()->store('wallets', 'local'), 'enabled' => true]);
            $url = route('owner.payments.qr', ['wallet' => $wallet->id]);
            $this->actingAs($owner)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
            $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        }
    }

    public function test_only_booking_participants_and_active_admins_can_view_its_snapshotted_qr(): void
    {
        Storage::fake('local');
        $rental = $this->rental();
        $path = $this->paymentProof()->store('owner-payment-qrs', 'local');
        $rental->forceFill(['payment_destination' => ['source' => 'owner_wallet', 'method' => 'maya',
            'payee_user_id' => $rental->owner_id, 'account_name' => 'Sample recipient', 'qr_path' => $path]])->save();
        $url = route('rentals.payment-qr', $rental);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        foreach ([$rental->renter, $rental->owner, User::factory()->admin()->create()] as $viewer) {
            $response = $this->actingAs($viewer)->get($url)->assertOk();
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        Storage::disk('local')->delete($path);
        $this->actingAs($rental->renter)->get($url)->assertNotFound();
    }

    public function test_only_the_snapshotted_wallet_recipient_gets_additional_payment_proof_access(): void
    {
        Storage::fake('local');
        $rental = $this->rental();
        $submission = $rental->paymentSubmissions()->create([
            'submitted_by' => $rental->renter_id, 'method' => 'maya', 'instructions' => 'Test payment',
            'external_reference' => 'SAMPLE-REFERENCE', 'amount' => $rental->total_amount,
            'proof_path' => $this->paymentProof()->store('payment-proofs', 'local'), 'status' => 'pending',
        ]);
        $url = route('payment-proofs.show', $submission);
        $this->actingAs($rental->owner)->get($url)->assertForbidden();
        $submission->forceFill(['payment_destination' => ['source' => 'owner_wallet',
            'payee_user_id' => $rental->owner_id]])->save();

        $this->actingAs($rental->owner)->get($url)->assertOk();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_renter_payment_proof_and_owner_confirmation_finalize_without_admin_approval(): void
    {
        Storage::fake('local');
        $rental = $this->rental();
        OwnerPaymentSetting::create(['user_id' => $rental->owner_id, 'provider' => 'maya',
            'account_name' => 'Sample recipient', 'instructions' => '',
            'qr_path' => $this->paymentProof()->store('owner-payment-qrs', 'local'), 'enabled' => true]);
        $form = Livewire::actingAs($rental->renter)->test(RenterShow::class, ['rental' => $rental])
            ->assertSee('Maya')->assertSee('Sample recipient')
            ->assertSeeHtml(route('rentals.payment-qr', $rental));
        $form->set('payment_reference', 'WALLET-123')->set('payment_proof', $this->paymentProof())
            ->call('submitPaymentProof')->assertHasNoErrors();
        $submission = PaymentSubmission::sole();
        $this->assertSame('payment_pending', $rental->fresh()->status->value);
        $owner = Livewire::actingAs($rental->owner)->test(OwnerShow::class, ['rental' => $rental])
            ->assertSee('Confirm funds received');
        $owner->call('confirmPaymentReceipt', $submission->id)->assertHasErrors('funds_received');
        $owner->set('owner_funds_received', true)->call('confirmPaymentReceipt', $submission->id)
            ->assertHasNoErrors()->assertDontSee('Final admin review is still required');
        $this->assertNotNull($submission->fresh()->recipient_confirmed_at);
        $this->assertSame('paid', $rental->fresh()->status->value);
        $this->assertDatabaseCount('payments', 1);

        Livewire::actingAs(User::factory()->admin()->create())->test(AdminShow::class, ['rental' => $rental])
            ->set('reason', 'Receipt and full amount checked with receiving owner')
            ->set('funds_received', true)->call('verifyPayment', $submission->id)->assertForbidden();

        $this->assertSame('paid', $rental->fresh()->status->value);
        $this->assertDatabaseHas('payments', ['rental_id' => $rental->id, 'method' => 'maya', 'amount' => 830]);
    }

    public function test_account_deletion_removes_current_wallet_details_but_preserves_transaction_evidence(): void
    {
        Storage::fake('local');
        foreach ([false, true] as $referenced) {
            $rental = $this->rental();
            $path = $this->paymentProof()->store('owner-payment-qrs', 'local');
            OwnerPaymentSetting::create(['user_id' => $rental->owner_id, 'provider' => 'maya',
                'account_name' => 'Private wallet name', 'qr_path' => $path, 'enabled' => true]);
            $extraPath = $this->paymentProof()->store('owner-payment-qrs', 'local');
            OwnerPaymentSetting::create(['user_id' => $rental->owner_id, 'provider' => 'gcash',
                'account_name' => 'Second wallet', 'qr_path' => $extraPath, 'enabled' => true]);
            if ($referenced) {
                $rental->forceFill(['payment_destination' => ['source' => 'owner_wallet', 'method' => 'maya',
                    'payee_user_id' => $rental->owner_id, 'account_name' => 'Private wallet name', 'qr_path' => $path]])->save();
            }

            $rental->owner->delete();

            $this->assertNull(OwnerPaymentSetting::currentFor($rental->owner_id));
            $this->assertSame(0, OwnerPaymentSetting::where('user_id', $rental->owner_id)->count());
            Storage::disk('local')->assertMissing($extraPath);
            $this->assertSame($referenced, Storage::disk('local')->exists($path));
            if ($referenced) {
                $this->actingAs($rental->renter)->get(route('rentals.payment-qr', $rental))->assertOk();
            }
        }
    }

    private function rental(): Rental
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $category = Category::firstOrCreate(['slug' => 'wallet-access'], ['name' => 'Tools']);
        $listing = Listing::create(['owner_id' => $owner->id, 'category_id' => $category->id,
            'name' => 'Sample drill', 'description' => 'Sample item', 'condition' => 'good',
            'price_per_day' => 100, 'security_deposit' => 500, 'location' => 'Manila',
            'max_rental_duration_days' => 10, 'status' => 'published', 'is_available' => true]);
        $values = ['listing_id' => $listing->id, 'renter_id' => $renter->id,
            'start_date' => now()->addDays(2), 'end_date' => now()->addDays(4), 'rental_days' => 3,
            'fulfillment_method' => 'pickup', 'rental_fee' => 300, 'commission_rate' => 10,
            'commission_amount' => 30, 'security_deposit' => 500, 'total_amount' => 830];
        $request = RentalRequest::create($values + ['status' => 'approved',
            'owner_terms_accepted_at' => now(), 'renter_terms_accepted_at' => now()]);
        $request->update(['agreement_terms' => RentalAgreement::termsFor($request)]);

        return Rental::create($values + ['rental_request_id' => $request->id, 'owner_id' => $owner->id]);
    }
}
