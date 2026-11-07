<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\RentalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends DemoSeeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->ensureDemoEnvironment();

        DB::transaction(function () {
            $freshDemo = ! User::withTrashed()->exists() && ! Listing::withTrashed()->exists() && ! RentalRequest::exists();
            $this->demoUser(['name' => 'Tala Admin', 'email' => 'admin@tala.test', 'role' => UserRole::Admin]);
            $this->demoUser(['name' => 'Demo Owner', 'email' => 'owner@tala.test'], identityVerified: true);
            $this->demoUser(['name' => 'Demo Renter', 'email' => 'renter@tala.test'], identityVerified: true);

            $this->call([TestLoginSeeder::class, CategorySeeder::class, MemberSeeder::class, ListingSeeder::class]);

            // Historical examples are a fresh-database fixture, never a top-up
            // of an existing user's bookings, damage reports or conversations.
            if ($freshDemo) {
                $this->call([TransactionSeeder::class, DemoContentSeeder::class]);
            }

            $this->call(MarketplaceDemoSeeder::class);
        });
    }
}
