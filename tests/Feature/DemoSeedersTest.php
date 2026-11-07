<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\RentalRequest;
use App\Models\User;
use App\Services\ListingPublication;
use Carbon\Carbon;
use Database\Seeders\BulkDemoSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountVolumeSeeder;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\ListingSeeder;
use Database\Seeders\MarketplaceDemoSeeder;
use Database\Seeders\MemberSeeder;
use Database\Seeders\TestAccountSeeder;
use Database\Seeders\TestLoginSeeder;
use Database\Seeders\TransactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoSeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->travelTo(Carbon::parse('2026-11-07 22:30', 'Asia/Manila'));
    }

    private function counts(): array
    {
        return collect(['users', 'categories', 'listings', 'listing_images', 'rental_requests', 'rentals',
            'payments', 'security_deposits', 'condition_records', 'reviews', 'damage_reports', 'disputes', 'messages', 'notifications'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    }

    public function test_marketplace_seed_has_valid_listings_photos_and_an_exact_priced_request(): void
    {
        $this->seed(MarketplaceDemoSeeder::class);
        $this->assertSame(2, User::count());
        $this->assertSame(3, Listing::count());
        foreach (Listing::all() as $listing) {
            ListingPublication::validate($listing);
            $this->assertTrue($listing->owner->hasVerifiedIdentity());
            $this->assertSame($listing->category_id, $listing->subcategory->parent_id);
            Storage::disk('public')->assertExists($listing->images->first()->path);
        }
        $request = RentalRequest::sole();
        $this->assertSame('2026-11-17', $request->start_date->toDateString());
        $this->assertSame('2026-11-19', $request->end_date->toDateString());
        $this->assertSame('09:00', $request->starts_at->copy()->setTimezone('Asia/Manila')->format('H:i'));
        $this->assertSame('17:00', $request->ends_at->copy()->setTimezone('Asia/Manila')->format('H:i'));
        $this->assertSame(3, $request->rental_days);
        $this->assertEquals(4805, $request->total_amount);
    }

    public function test_marketplace_rerun_does_not_reset_a_changed_account_listing_or_request(): void
    {
        $this->seed(MarketplaceDemoSeeder::class);
        $owner = User::where('email', 'marketplace.owner@lendly.test')->firstOrFail();
        $owner->update(['password' => 'custom-demo-password']);
        $password = $owner->fresh()->password;
        $listing = Listing::where('name', 'Mirrorless Camera Kit')->firstOrFail();
        $listing->update(['price_per_day' => 999, 'status' => 'inactive']);
        RentalRequest::sole()->update(['status' => 'cancelled']);
        $before = $this->counts();
        $this->travel(1)->days();
        $this->seed(MarketplaceDemoSeeder::class);
        $this->assertSame($before, $this->counts());
        $this->assertSame($password, $owner->fresh()->password);
        $this->assertEquals(999, $listing->fresh()->price_per_day);
        $this->assertSame('inactive', $listing->fresh()->status->value);
        $this->assertSame('cancelled', RentalRequest::sole()->status->value);
    }

    public function test_marketplace_does_not_reverify_or_reactivate_an_existing_demo_account(): void
    {
        $owner = User::factory()->suspended()->create(['email' => 'marketplace.owner@lendly.test']);
        $before = $owner->fresh()->getAttributes();
        $this->seed(MarketplaceDemoSeeder::class);
        $this->assertSame($before, $owner->fresh()->getAttributes());
        $this->assertSame(0, Listing::count());
        $this->assertSame(0, RentalRequest::count());
    }

    public function test_default_seed_runs_twice_without_duplicates_and_keeps_a_valid_catalogue(): void
    {
        $this->seed(DatabaseSeeder::class);
        $before = $this->counts();
        $this->assertGreaterThan(20, $before['listings']);
        $this->assertGreaterThan(0, $before['rentals']);
        $this->assertGreaterThan(0, $before['messages']);
        foreach (Listing::all() as $listing) {
            ListingPublication::validate($listing);
        }
        $owner = User::where('email', 'owner@tala.test')->firstOrFail();
        $owner->update(['password' => 'keep-this-password']);
        $password = $owner->fresh()->password;
        $listing = Listing::where('owner_id', $owner->id)->firstOrFail();
        $listing->update(['price_per_day' => 321, 'status' => 'inactive']);
        $this->travel(1)->days();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, $this->counts());
        $this->assertSame($password, $owner->fresh()->password);
        $this->assertEquals(321, $listing->fresh()->price_per_day);
        $this->assertSame('inactive', $listing->fresh()->status->value);
    }

    public function test_existing_database_gets_no_synthetic_financial_history_or_account_resets(): void
    {
        $member = User::factory()->suspended()->create(['email' => 'test@lendly.test', 'name' => 'Existing member']);
        $before = $member->fresh()->getAttributes();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, $member->fresh()->getAttributes());
        $this->assertSame(0, DB::table('rentals')->count());
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(0, DB::table('damage_reports')->count());
        $this->assertSame(1, RentalRequest::count());
    }

    public function test_account_seeders_keep_existing_password_role_and_suspension(): void
    {
        $admin = User::factory()->suspended()->create(['email' => 'admin@tala.test']);
        $login = User::factory()->suspended()->create(['email' => 'test@lendly.test']);
        $snapshots = [$admin->fresh()->getAttributes(), $login->fresh()->getAttributes()];
        $this->seed([TestAccountSeeder::class, TestLoginSeeder::class, MemberSeeder::class]);
        $this->assertSame($snapshots[0], $admin->fresh()->getAttributes());
        $this->assertSame($snapshots[1], $login->fresh()->getAttributes());
        $this->assertTrue(Hash::check('password', User::where('email', 'member@tala.test')->firstOrFail()->password));
    }

    public function test_bulk_demo_seed_is_repeatable_even_with_soft_deleted_items(): void
    {
        $this->seed(BulkDemoSeeder::class);
        $this->assertSame(250, Listing::count());
        $listing = Listing::firstOrFail();
        ListingPublication::validate($listing);
        $listing->delete();
        $before = $this->counts();
        $this->seed(BulkDemoSeeder::class);
        $this->assertSame($before, $this->counts());
        $this->assertTrue($listing->fresh()->trashed());
    }

    public function test_demo_volume_handles_two_members_without_extra_renters(): void
    {
        User::factory()->identityVerified()->create(['email' => 'owner@tala.test']);
        User::factory()->identityVerified()->create(['email' => 'renter@tala.test']);
        $this->seed(CategorySeeder::class);
        $this->seed(DemoAccountVolumeSeeder::class);
        $before = $this->counts();
        $this->assertGreaterThanOrEqual(10, $before['listings']);
        $this->assertGreaterThan(0, $before['rentals']);
        $this->seed(DemoAccountVolumeSeeder::class);
        $this->assertSame($before, $this->counts());
        $this->assertFalse(DB::table('rentals')->whereColumn('owner_id', 'renter_id')->exists());
    }

    public function test_category_conflict_never_reparents_an_existing_category(): void
    {
        $other = Category::create(['slug' => 'custom-tools', 'name' => 'Custom tools']);
        $camera = Category::create(['slug' => 'cameras', 'name' => 'Custom cameras', 'parent_id' => $other->id]);
        try {
            $this->seed(CategorySeeder::class);
            $this->fail('Expected a conflicting slug to be reported.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('different category', $exception->getMessage());
        }
        $this->assertSame($other->id, $camera->fresh()->parent_id);
        $this->assertSame('Custom cameras', $camera->fresh()->name);
    }

    public function test_every_demo_entry_point_is_disabled_outside_local_and_testing(): void
    {
        $this->app->instance('env', 'production');
        foreach ([DatabaseSeeder::class, MarketplaceDemoSeeder::class, BulkDemoSeeder::class,
            DemoAccountVolumeSeeder::class, DemoContentSeeder::class, ListingSeeder::class,
            TransactionSeeder::class, MemberSeeder::class, TestAccountSeeder::class, TestLoginSeeder::class] as $seeder) {
            try {
                $this->app->make($seeder)->run();
                $this->fail('Production demo seeding was allowed: '.$seeder);
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('local and testing', $exception->getMessage());
            }
        }
        $this->assertSame(0, User::count());
    }
}
