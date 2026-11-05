<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfflinePaymentMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_upgrade_and_rollback_preserve_existing_payment_and_receipt_data(): void
    {
        $multipleWalletMigration = require database_path('migrations/2026_11_04_000001_allow_multiple_owner_payment_wallets.php');
        $multipleWalletMigration->down();
        $walletMigration = require database_path('migrations/2026_10_20_000002_add_owner_wallet_payments.php');
        $walletMigration->down();
        $migration = require database_path('migrations/2026_10_07_000006_add_verified_offline_payments.php');
        $migration->down();
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $category = Category::create(['name' => 'Tools', 'slug' => 'payment-migration']);
        $listing = Listing::create([
            'owner_id' => $owner->id, 'category_id' => $category->id, 'name' => 'Historical item',
            'description' => 'Retained details', 'condition' => 'good', 'price_per_day' => 100,
            'security_deposit' => 500, 'location' => 'Manila', 'max_rental_duration_days' => 10,
            'status' => 'published', 'is_available' => true, 'pickup_available' => true,
        ]);
        $terms = [
            'listing_id' => $listing->id, 'renter_id' => $renter->id,
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(3), 'rental_days' => 3,
            'fulfillment_method' => 'pickup', 'rental_fee' => 300, 'commission_rate' => 10,
            'commission_amount' => 30, 'security_deposit' => 500, 'total_amount' => 830,
        ];
        $request = RentalRequest::create($terms + ['status' => 'approved']);
        $rental = Rental::create($terms + ['rental_request_id' => $request->id, 'owner_id' => $owner->id, 'status' => 'paid', 'paid_at' => now()]);
        $payment = Payment::create(['rental_id' => $rental->id, 'transaction_reference' => 'LEGACY-RECEIPT', 'amount' => 830, 'paid_at' => now()]);
        $saved = $payment->fresh()->getAttributes();
        $migration->up();
        $expected = $saved + ['method' => null, 'external_reference' => null, 'payment_submission_id' => null, 'verified_by' => null];
        $this->assertEquals($expected, $payment->fresh()->getAttributes());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $migration->down();
        $this->assertSame($saved, $payment->fresh()->getAttributes());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $migration->up();
        $walletMigration->up();
        $multipleWalletMigration->up();
    }
}
