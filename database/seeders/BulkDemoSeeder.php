<?php

namespace Database\Seeders;

use App\Enums\ListingCondition;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;

class BulkDemoSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->ensureDemoEnvironment();
        $this->call(CategorySeeder::class);

        $members = collect();

        for ($i = 1; $i <= 100; $i++) {
            $members->push($this->demoUser(['email' => "demo{$i}@tala.test", 'name' => "Demo Member {$i}"], identityVerified: true));
        }

        $categories = Category::whereNotNull('parent_id')->orderBy('id')->get();
        $conditions = ListingCondition::cases();
        $locations = [
            ['Batangas City', 13.7565, 121.0583],
            ['Lipa City', 13.9411, 121.1622],
            ['Tanauan City', 14.0866, 121.1497],
            ['Malvar', 14.0522, 121.1583],
            ['Santo Tomas', 14.1078, 121.1414],
            ['Nasugbu', 14.0722, 120.6317],
        ];

        $target = 250;

        for ($i = 1; $i <= $target; $i++) {
            $category = $categories[($i - 1) % $categories->count()];
            $owner = $members[($i - 1) % $members->count()];
            if (! $owner->hasVerifiedIdentity() || Listing::withTrashed()->where('owner_id', $owner->id)->where('name', 'like', "Demo Item {$i} - %")->exists()) {
                continue;
            }
            $price = 150 + (($i * 73) % 1800);
            [$location, $latitude, $longitude] = $locations[($i - 1) % count($locations)];

            $listing = Listing::create([
                'owner_id' => $owner->id,
                'category_id' => $category->parent_id,
                'subcategory_id' => $category->id,
                'name' => "Demo Item {$i} - {$category->name}",
                'brand' => ['Tala', 'Lendly', 'DemoTech', 'Batangas Rentals'][$i % 4],
                'description' => "Demo listing {$i} for testing search, browsing, listing details, and rental workflows.",
                'condition' => $conditions[($i - 1) % count($conditions)],
                'estimated_original_price' => $price * 20,
                'price_per_day' => $price,
                'price_per_week' => $price * 5,
                'security_deposit' => $price * 2,
                'location' => $location,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'max_rental_duration_days' => 3 + ($i % 12),
                'pickup_available' => true,
                'delivery_available' => $i % 3 === 0,
                'is_available' => true,
                'status' => ListingStatus::Published,
                'available_from' => now('Asia/Manila')->toDateString(),
                'available_until' => now('Asia/Manila')->addDays(90)->toDateString(),
                'views_count' => $i * 7,
            ]);
            $this->attachDemoImage($listing);
        }
    }
}
