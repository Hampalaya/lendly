<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Services\ListingPublication;
use Database\Seeders\BulkDemoSeeder;
use Database\Seeders\HostedMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class HostedDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_unconfirmed_apply_never_write_records(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('demo:seed-hosted')->expectsOutputToContain('Preview only')->assertSuccessful();
        $this->artisan('demo:seed-hosted', ['--apply' => true])->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_confirmed_hosted_seed_is_repeatable_and_has_no_known_password_or_financial_records(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('demo:seed-hosted', ['--apply' => true, '--confirm-production' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 100);
        $this->assertDatabaseCount('listings', 250);
        $this->assertDatabaseCount('listing_images', 250);
        $this->assertDatabaseCount('rental_requests', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('notifications', 0);
        $owner = User::firstOrFail();
        $this->assertFalse($owner->isAdmin());
        $this->assertFalse(Hash::check('password', $owner->password));
        $this->assertSame(HostedMarketplaceSeeder::REFERENCE, $owner->identity_reference);
        $password = $owner->password;
        $listing = Listing::firstOrFail();
        ListingPublication::validate($listing);
        $this->assertSame(asset('images/hosted-demo-placeholder.svg'), $listing->images->first()->url());
        $listing->update(['price_per_day' => 777, 'status' => 'inactive']);
        $this->artisan('demo:seed-hosted', ['--apply' => true, '--confirm-production' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 100);
        $this->assertDatabaseCount('listings', 250);
        $this->assertDatabaseCount('listing_images', 250);
        $this->assertSame($password, $owner->fresh()->password);
        $this->assertEquals(777, $listing->fresh()->price_per_day);
        $this->assertSame('inactive', $listing->fresh()->status->value);
        $this->assertSame(0, DB::table('users')->where('role', 'admin')->count());
    }

    public function test_existing_email_collision_rolls_back_the_whole_hosted_seed(): void
    {
        $member = User::factory()->create(['email' => 'hosted.demo50@tala.test']);
        $before = $member->fresh()->getAttributes();
        $this->app->instance('env', 'production');
        $this->artisan('demo:seed-hosted', ['--apply' => true, '--confirm-production' => true])->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('listings', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertSame($before, $member->fresh()->getAttributes());
    }

    public function test_normal_bulk_seed_still_refuses_production_and_hosted_seed_refuses_implicit_invocation(): void
    {
        $this->app->instance('env', 'production');
        foreach ([BulkDemoSeeder::class, HostedMarketplaceSeeder::class] as $seeder) {
            try {
                app($seeder)->run();
                $this->fail('Implicit production seeding was allowed.');
            } catch (RuntimeException) {
                $this->assertDatabaseCount('users', 0);
            }
        }
    }

    public function test_requested_credentials_only_belong_to_new_accounts_and_are_not_reset_on_repeat(): void
    {
        $this->app->instance('env', 'production');
        $credentials = DB::transaction(fn () => app(HostedMarketplaceSeeder::class)->seedConfirmed(3));
        $this->assertCount(3, $credentials);
        foreach ($credentials as [$email, $password]) {
            $this->assertTrue(Hash::check($password, User::where('email', $email)->firstOrFail()->password));
        }
        $this->assertSame([], DB::transaction(fn () => app(HostedMarketplaceSeeder::class)->seedConfirmed(3)));
        foreach ($credentials as [$email, $password]) {
            $this->assertTrue(Hash::check($password, User::where('email', $email)->firstOrFail()->password));
        }
    }

    public function test_normal_upload_image_paths_keep_their_existing_url(): void
    {
        $image = new ListingImage(['path' => 'listings/example.jpg']);
        $this->assertSame(asset('storage/listings/example.jpg'), $image->url());
    }
}
