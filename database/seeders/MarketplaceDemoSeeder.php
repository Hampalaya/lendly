<?php

namespace Database\Seeders;

use App\Enums\FulfillmentMethod;
use App\Enums\ListingCondition;
use App\Enums\ListingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Services\RentalRequestSubmission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Marketplace demo data can only be seeded in local and testing environments.');
        }

        DB::transaction(function () {
            $owner = $this->demoMember('marketplace.owner@lendly.test', 'Marketplace Demo Owner');
            $renter = $this->demoMember('marketplace.renter@lendly.test', 'Marketplace Demo Renter');

            $this->call(CategorySeeder::class);

            $camera = $this->demoListing($owner, 'Mirrorless Camera Kit', 'cameras', [
                'description' => 'Mirrorless camera with a kit lens, battery, charger, and carrying case.',
                'price_per_day' => 850,
                'security_deposit' => 2000,
                'location' => 'Tagaytay City, Cavite',
                'latitude' => 14.1153,
                'longitude' => 120.9621,
            ]);

            $this->demoListing($owner, 'Cordless Drill and Bit Set', 'power-tools', [
                'description' => 'Cordless drill with two batteries and a set of wood and metal bits.',
                'price_per_day' => 280,
                'security_deposit' => 500,
                'location' => 'Trece Martires City, Cavite',
                'latitude' => 14.2818,
                'longitude' => 120.8684,
            ]);

            $this->demoListing($owner, 'Portable Projector and Screen', 'projectors', [
                'description' => 'Portable projector with a folding screen and HDMI cable.',
                'price_per_day' => 650,
                'security_deposit' => 1200,
                'location' => 'Maragondon, Cavite',
                'latitude' => 14.2739,
                'longitude' => 120.7370,
                'status' => ListingStatus::PendingApproval,
            ]);

            if ($camera->wasRecentlyCreated) {
                $startsOn = now('Asia/Manila')->addDays(10)->toImmutable();

                RentalRequestSubmission::submit($renter, $camera->id, [
                    'start_date' => $startsOn->toDateString(),
                    'end_date' => $startsOn->addDays(2)->toDateString(),
                    'fulfillment_method' => FulfillmentMethod::Pickup->value,
                ], RentalRequestSubmission::termsFor($camera), true);
            }
        }, 3);
    }

    private function demoMember(string $email, string $name): User
    {
        return User::withTrashed()->lockForUpdate()->firstOrCreate(['email' => $email], [
            'name' => $name,
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
            'identity_verified_at' => now(),
            'identity_verification_environment' => 'production',
        ]);
    }

    private function demoListing(User $owner, string $name, string $subcategorySlug, array $attributes): Listing
    {
        $subcategory = Category::where('slug', $subcategorySlug)->firstOrFail();

        return Listing::withTrashed()->firstOrCreate([
            'owner_id' => $owner->id,
            'name' => $name,
        ], $attributes + [
            'category_id' => $subcategory->parent_id,
            'subcategory_id' => $subcategory->id,
            'condition' => ListingCondition::Good,
            'status' => ListingStatus::Published,
        ]);
    }
}
