<?php

namespace Database\Seeders;

use App\Enums\ListingCondition;
use App\Enums\ListingStatus;
use App\Enums\RentalStatus;
use App\Models\Category;
use App\Models\CommissionSetting;
use App\Models\Listing;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\User;

/**
 * TransactionSeeder spreads its rentals/requests across every member, so
 * the two named demo accounts (owner@tala.test, renter@tala.test) — the
 * ones actually logged into during a demo — ended up with only 2-4 rows
 * in their own role-specific modules (My Listings, Rental Requests, My
 * Rentals, My Requests). Tops each up to ~10 so those pages look like a
 * real, lived-in account instead of a near-empty one. Extends
 * TransactionSeeder purely to reuse its request/rental-building helpers
 * (cost breakdown, payments, security deposits, condition records) rather
 * than re-deriving that bookkeeping.
 */
class DemoAccountVolumeSeeder extends TransactionSeeder
{
    public function run(): void
    {
        $this->ensureDemoEnvironment();
        $this->commissionRate = (float) CommissionSetting::current()->commission_rate;

        $owner = User::where('email', 'owner@tala.test')->first();
        $renter = User::where('email', 'renter@tala.test')->first();
        $category = Category::whereNull('parent_id')->orderBy('id')->first();

        if (! $owner || ! $renter || ! $category || ! $owner->hasVerifiedIdentity() || ! $renter->hasVerifiedIdentity()) {
            return;
        }

        $this->topUpOwnerListings($owner, $category);

        $ownerListings = Listing::where('owner_id', $owner->id)->get();
        $otherMembers = User::where('role', 'member')
            ->whereNotIn('id', [$owner->id, $renter->id])
            ->inRandomOrder()
            ->take(6)
            ->get();

        $this->topUpReceivedRequests($owner, $ownerListings, $renter, $otherMembers);
        $this->topUpOwnerRentals($owner, $ownerListings, $renter, $otherMembers);

        $otherListings = Listing::where('owner_id', '!=', $renter->id)->inRandomOrder()->take(10)->get();

        $this->topUpSentRequests($renter, $otherListings);
        $this->topUpRenterRentals($renter, $otherListings);
    }

    private function topUpOwnerListings(User $owner, Category $category): void
    {
        $itemNames = [
            'Camping Tent (4-person)', 'DSLR Camera Kit', 'Electric Drill Set',
            'Portable Projector', 'Folding Table Set', 'Bluetooth Speaker',
            'Mountain Bike', 'Pressure Washer', 'Party Tent (10x10)', 'Karaoke Machine',
        ];

        $existing = Listing::where('owner_id', $owner->id)->count();

        for ($i = $existing; $i < 10; $i++) {
            $listing = Listing::create([
                'owner_id' => $owner->id,
                'category_id' => $category->id,
                'name' => $itemNames[$i % count($itemNames)],
                'description' => 'Well-maintained and ready to rent. Message me with any questions before booking.',
                'condition' => ListingCondition::Good,
                'price_per_day' => fake()->randomElement([150, 250, 350, 500, 800]),
                'security_deposit' => fake()->randomElement([500, 1000, 1500]),
                'location' => 'Quezon City, Metro Manila',
                'latitude' => 14.676,
                'longitude' => 121.043,
                'max_rental_duration_days' => 14,
                'status' => ListingStatus::Published,
                'available_from' => now('Asia/Manila')->toDateString(),
                'available_until' => now('Asia/Manila')->addDays(90)->toDateString(),
            ]);
            $this->attachDemoImage($listing);
        }
    }

    private function topUpReceivedRequests(User $owner, $ownerListings, User $renter, $otherMembers): void
    {
        $existing = RentalRequest::whereHas('listing', fn ($q) => $q->where('owner_id', $owner->id))->count();

        for ($i = $existing; $i < 10; $i++) {
            $listing = $ownerListings[$i % $ownerListings->count()];
            $requestingRenter = $otherMembers->isEmpty() || $i % 3 === 0 ? $renter : $otherMembers[$i % $otherMembers->count()];

            $this->createPendingRequest($listing, $requestingRenter);
        }
    }

    private function topUpOwnerRentals(User $owner, $ownerListings, User $renter, $otherMembers): void
    {
        $existing = Rental::where('owner_id', $owner->id)->count();
        $statusCycle = [RentalStatus::Active, RentalStatus::Paid, RentalStatus::Completed, RentalStatus::Returned];

        for ($i = $existing; $i < 10; $i++) {
            $listing = $ownerListings[$i % $ownerListings->count()];
            $requestingRenter = $otherMembers->isEmpty() || $i % 3 === 0 ? $renter : $otherMembers[$i % $otherMembers->count()];
            $status = $statusCycle[$i % count($statusCycle)];

            if ($status === RentalStatus::Completed) {
                $this->createCompletedRental($listing, $requestingRenter);
            } else {
                $this->createRental($listing, $requestingRenter, $status, now()->subDays(fake()->numberBetween(1, 15)), 3);
            }
        }
    }

    private function topUpSentRequests(User $renter, $otherListings): void
    {
        if ($otherListings->isEmpty()) {
            return;
        }
        $existing = RentalRequest::where('renter_id', $renter->id)->count();

        for ($i = $existing; $i < 10; $i++) {
            $listing = $otherListings[$i % max($otherListings->count(), 1)];

            $this->createPendingRequest($listing, $renter);
        }
    }

    private function topUpRenterRentals(User $renter, $otherListings): void
    {
        if ($otherListings->isEmpty()) {
            return;
        }
        $existing = Rental::where('renter_id', $renter->id)->count();
        $statusCycle = [RentalStatus::Active, RentalStatus::Paid, RentalStatus::Completed, RentalStatus::Returned];

        for ($i = $existing; $i < 10; $i++) {
            $listing = $otherListings[$i % max($otherListings->count(), 1)];
            $status = $statusCycle[$i % count($statusCycle)];

            if ($status === RentalStatus::Completed) {
                $this->createCompletedRental($listing, $renter);
            } else {
                $this->createRental($listing, $renter, $status, now()->subDays(fake()->numberBetween(1, 15)), 3);
            }
        }
    }
}
