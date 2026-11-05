<?php

namespace Tests\Feature;

use App\Livewire\Owner\Payments\Settings;
use App\Models\Category;
use App\Models\Listing;
use App\Models\OwnerPaymentSetting;
use App\Models\PaymentSubmission;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class OwnerWalletSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::set('local', Storage::fake('owner-wallet-settings-'.Str::random(12)));
    }

    private function qrImage(int $width = 1): UploadedFile
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, 1, 8, 6, 0, 0, 0))
            .$chunk('IDAT', gzcompress("\0".str_repeat("\0\0\0\xff", $width)))
            .$chunk('IEND', '');

        return UploadedFile::fake()->createWithContent('wallet-qr.png', $png);
    }

    private function savedSettings(User $owner, string $path = 'owner-payment-qr/original.png'): OwnerPaymentSetting
    {
        Storage::disk('local')->put($path, $this->qrImage()->getContent());

        return OwnerPaymentSetting::create([
            'user_id' => $owner->id, 'provider' => 'maya', 'account_name' => 'Original payee',
            'instructions' => 'Check the recipient name.', 'qr_path' => $path, 'enabled' => true,
        ]);
    }

    private function rental(User $owner): Rental
    {
        $renter = User::factory()->create();
        $category = Category::create(['slug' => 'wallet-settings-'.Str::random(6), 'name' => 'Tools']);
        $listing = Listing::create([
            'owner_id' => $owner->id, 'category_id' => $category->id, 'name' => 'Wallet test item',
            'description' => 'Synthetic item', 'condition' => 'good', 'price_per_day' => 100,
            'security_deposit' => 0, 'location' => 'Manila', 'status' => 'published', 'max_rental_duration_days' => 10,
        ]);
        $terms = [
            'listing_id' => $listing->id, 'renter_id' => $renter->id,
            'start_date' => now()->addDays(3), 'end_date' => now()->addDays(4), 'rental_days' => 2,
            'fulfillment_method' => 'pickup', 'rental_fee' => 200, 'commission_rate' => 10,
            'commission_amount' => 20, 'security_deposit' => 0, 'total_amount' => 220,
        ];
        $request = RentalRequest::create($terms + ['status' => 'approved']);

        return Rental::create($terms + ['rental_request_id' => $request->id, 'owner_id' => $owner->id]);
    }

    public function test_save_creates_only_the_authenticated_members_private_wallet_settings(): void
    {
        $owner = User::factory()->create();
        $other = $this->savedSettings(User::factory()->create(), 'owner-payment-qr/other.png');

        Livewire::actingAs($owner)->test(Settings::class, ['user_id' => $other->user_id])
            ->set('provider', 'gcash')->set('account_name', '  Synthetic owner  ')
            ->set('instructions', '  Confirm the name before paying.  ')->set('enabled', true)
            ->set('qr_image', $this->qrImage())->call('save')->assertHasNoErrors()
            ->assertSet('qr_image', null)->assertSee('Payment QR settings saved.')
            ->assertDontSee($other->qr_path);

        $settings = OwnerPaymentSetting::currentFor($owner->id);
        $this->assertSame('gcash', $settings->provider);
        $this->assertSame('Synthetic owner', $settings->account_name);
        $this->assertSame('Confirm the name before paying.', $settings->instructions);
        $this->assertTrue($settings->enabled);
        Storage::disk('local')->assertExists($settings->qr_path);
        $this->assertStringStartsWith('owner-payment-qr/', $settings->qr_path);
        $this->assertSame('Original payee', $other->fresh()->account_name);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_user_id_is_not_a_client_editable_component_property(): void
    {
        $owner = User::factory()->create();
        $other = $this->savedSettings(User::factory()->create());
        $this->expectException(PublicPropertyNotFoundException::class);

        Livewire::actingAs($owner)->test(Settings::class)->set('user_id', $other->user_id);
    }

    public function test_form_starts_closed_and_edit_reloads_only_the_selected_wallet(): void
    {
        $owner = User::factory()->create();
        $settings = $this->savedSettings($owner);
        $component = Livewire::actingAs($owner)->test(Settings::class)
            ->assertSet('showForm', false)->assertSet('editingId', null)
            ->call('edit', $settings->id)->assertSet('showForm', true)
            ->assertSet('account_name', 'Original payee')->assertSet('enabled', true)
            ->assertDontSee($settings->qr_path);
        $settings->update(['account_name' => 'Updated in another tab']);
        $component->call('$refresh')->assertViewHas('wallets', fn ($wallets) => $wallets->find($settings->id)->account_name === 'Updated in another tab');
    }

    public function test_ineligible_accounts_cannot_mount_or_mutate_wallet_settings(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->unverified()->create(), User::factory()->suspended()->create()] as $user) {
            Livewire::actingAs($user)->test(Settings::class)->assertForbidden();
        }

        foreach (['save', 'removeQr'] as $action) {
            foreach (['suspended', 'unverified', 'deleted', 'admin'] as $change) {
                $owner = User::factory()->create();
                $settings = $this->savedSettings($owner, 'owner-payment-qr/'.Str::random(12).'.png');
                $component = Livewire::actingAs($owner)->test(Settings::class);
                match ($change) {
                    'suspended' => $owner->update(['status' => 'suspended']),
                    'unverified' => $owner->forceFill(['email_verified_at' => null])->save(),
                    'deleted' => $owner->delete(),
                    'admin' => $owner->update(['role' => 'admin']),
                };
                $component->call($action, ...($action === 'removeQr' ? [$settings->id] : []))->assertForbidden();
                if ($change === 'deleted') {
                    $this->assertNull($settings->fresh());
                    Storage::disk('local')->assertMissing($settings->qr_path);
                } else {
                    $this->assertSame($settings->qr_path, $settings->fresh()->qr_path);
                    Storage::disk('local')->assertExists($settings->qr_path);
                }
            }
        }
    }

    public function test_only_bounded_real_png_and_jpeg_images_can_be_saved(): void
    {
        $owner = User::factory()->create();
        $images = [
            UploadedFile::fake()->createWithContent('fake.png', 'not a real image'),
            UploadedFile::fake()->createWithContent('wallet.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>'),
            UploadedFile::fake()->createWithContent('large.png', $this->qrImage()->getContent().str_repeat('0', 5 * 1024 * 1024)),
            $this->qrImage(4097),
        ];
        foreach ($images as $image) {
            Livewire::actingAs($owner)->test(Settings::class)->set('account_name', 'Synthetic owner')
                ->set('qr_image', $image)->call('save')->assertHasErrors('qr_image');
        }
        $this->assertDatabaseCount('owner_payment_settings', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('owner-payment-qr'));
    }

    public function test_enabled_settings_require_a_qr_and_payment_details_are_validated(): void
    {
        $owner = User::factory()->create();
        $component = Livewire::actingAs($owner)->test(Settings::class)
            ->set('provider', 'unknown')->set('account_name', '')->set('instructions', str_repeat('a', 1001))
            ->call('save')->assertHasErrors(['provider', 'account_name', 'instructions']);
        $component->set('provider', 'maya')->set('account_name', 'Synthetic owner')->set('instructions', '')
            ->set('enabled', true)->call('save')->assertHasErrors('qr_image');
        $this->assertDatabaseCount('owner_payment_settings', 0);
        $component->set('enabled', false)->call('save')->assertHasErrors('qr_image');
        $component->set('qr_image', $this->qrImage())->call('save')->assertHasNoErrors();
        $this->assertFalse(OwnerPaymentSetting::currentFor($owner->id)->enabled);
    }

    public function test_replacement_deletes_an_unreferenced_qr_after_saving_the_new_private_image(): void
    {
        $owner = User::factory()->create();
        $settings = $this->savedSettings($owner);
        $oldPath = $settings->qr_path;
        Livewire::actingAs($owner)->test(Settings::class)->call('edit', $settings->id)
            ->set('qr_image', $this->qrImage())->call('save')->assertHasNoErrors();
        $newPath = $settings->fresh()->qr_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertExists($newPath);
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_removing_wallet_deletes_only_that_saved_wallet_and_its_unreferenced_image(): void
    {
        $owner = User::factory()->create();
        $settings = $this->savedSettings($owner);
        $oldPath = $settings->qr_path;
        $other = $this->savedSettings($owner, 'owner-payment-qr/second.png');
        Livewire::actingAs($owner)->test(Settings::class)->call('edit', $settings->id)
            ->call('removeQr', $settings->id)->assertHasNoErrors()->assertSet('showForm', false);
        $this->assertNull($settings->fresh());
        $this->assertNotNull($other->fresh());
        Storage::disk('local')->assertExists($other->qr_path);
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_replacement_and_removal_retain_qrs_referenced_by_rentals_or_payment_submissions(): void
    {
        foreach (['rental', 'submission'] as $reference) {
            $owner = User::factory()->create();
            $settings = $this->savedSettings($owner, 'owner-payment-qr/'.Str::random(12).'.png');
            $oldPath = $settings->qr_path;
            $rental = $this->rental($owner);
            $destination = ['source' => 'owner_wallet', 'method' => 'maya', 'payee_user_id' => $owner->id, 'qr_path' => $oldPath];
            if ($reference === 'rental') {
                DB::table('rentals')->where('id', $rental->id)->update(['payment_destination' => json_encode($destination)]);
            } else {
                $submission = PaymentSubmission::create([
                    'rental_id' => $rental->id, 'submitted_by' => $rental->renter_id, 'method' => 'maya',
                    'instructions' => 'Synthetic', 'external_reference' => Str::random(12), 'amount' => 220,
                    'proof_path' => 'synthetic-proof.png', 'status' => 'pending',
                ]);
                DB::table('payment_submissions')->where('id', $submission->id)->update(['payment_destination' => json_encode($destination)]);
            }
            Livewire::actingAs($owner)->test(Settings::class)->call('edit', $settings->id)
                ->set('qr_image', $this->qrImage())->call('save')->assertHasNoErrors();
            Storage::disk('local')->assertExists($oldPath);

            // Restore a historical path to exercise removal's independent cleanup boundary.
            $settings->refresh()->update(['qr_path' => $oldPath]);
            Livewire::actingAs($owner)->test(Settings::class)->call('removeQr', $settings->id)->assertHasNoErrors();
            Storage::disk('local')->assertExists($oldPath);
            $this->assertNull($settings->fresh());
        }
    }

    public function test_failed_database_save_removes_the_new_upload_and_keeps_the_previous_qr(): void
    {
        $owner = User::factory()->create();
        $settings = $this->savedSettings($owner);
        $component = Livewire::actingAs($owner)->test(Settings::class)->call('edit', $settings->id)->set('qr_image', $this->qrImage());
        Event::listen('eloquent.saving: '.OwnerPaymentSetting::class, fn () => throw new RuntimeException('Synthetic database failure'));
        try {
            $component->call('save');
            $this->fail('The database failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic database failure', $exception->getMessage());
        } finally {
            Event::forget('eloquent.saving: '.OwnerPaymentSetting::class);
        }
        $this->assertSame($settings->qr_path, $settings->fresh()->qr_path);
        $this->assertSame([$settings->qr_path], Storage::disk('local')->allFiles('owner-payment-qr'));
    }

    public function test_owners_can_add_multiple_wallets_including_more_than_one_of_the_same_provider(): void
    {
        $owner = User::factory()->create();
        $component = Livewire::actingAs($owner)->test(Settings::class);
        foreach ([...array_keys(OwnerPaymentSetting::PROVIDERS), 'gcash'] as $index => $provider) {
            $component->call('addWallet')->assertSet('editingId', null)->assertSet('account_name', '')
                ->set('provider', $provider)->set('account_name', 'Wallet '.$index)
                ->set('qr_image', $this->qrImage())->call('save')->assertHasNoErrors()->assertSet('showForm', false);
        }

        $this->assertSame(8, OwnerPaymentSetting::where('user_id', $owner->id)->count());
        $this->assertSame(2, OwnerPaymentSetting::where('user_id', $owner->id)->where('provider', 'gcash')->count());
        $wallet = OwnerPaymentSetting::where('user_id', $owner->id)->latest('id')->first();
        $component->call('edit', $wallet->id)->set('account_name', 'Updated wallet')->call('save')->assertHasNoErrors();
        $this->assertSame('Updated wallet', $wallet->fresh()->account_name);
        $this->assertSame(8, OwnerPaymentSetting::where('user_id', $owner->id)->count());
    }

    public function test_edit_and_remove_cannot_target_another_owners_wallet(): void
    {
        $owner = User::factory()->create();
        $other = $this->savedSettings(User::factory()->create());
        foreach (['edit', 'removeQr'] as $action) {
            try {
                Livewire::actingAs($owner)->test(Settings::class)->call($action, $other->id);
                $this->fail('Another owner\'s wallet must not be editable.');
            } catch (ModelNotFoundException $exception) {
                $this->assertSame(OwnerPaymentSetting::class, $exception->getModel());
            }
            $this->assertNotNull($other->fresh());
            Storage::disk('local')->assertExists($other->qr_path);
        }
    }
}
