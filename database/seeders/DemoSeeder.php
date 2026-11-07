<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

abstract class DemoSeeder extends Seeder
{
    private static ?string $passwordHash = null;

    protected function ensureDemoEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo data can only be seeded in local and testing environments.');
        }
    }

    /** Seed defaults only when creating an account; preserve later account changes. */
    protected function demoUser(array $attributes, bool $identityVerified = false): User
    {
        $this->ensureDemoEnvironment();

        return User::withTrashed()->firstOrCreate(['email' => $attributes['email']], $attributes + [
            'password' => self::$passwordHash ??= Hash::make('password'),
            'email_verified_at' => now(),
            'role' => UserRole::Member,
            'status' => UserStatus::Active,
        ] + ($identityVerified ? [
            'identity_verified_at' => now(),
            'identity_verification_environment' => 'production',
        ] : []));
    }

    protected function attachDemoImage(Listing $listing): void
    {
        $path = 'listings/demo-'.$listing->id.'.svg';
        $label = htmlspecialchars($listing->name, ENT_QUOTES | ENT_XML1, 'UTF-8');
        Storage::disk('public')->put($path, '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600"><rect width="800" height="600" fill="#1e40af"/><text x="400" y="280" text-anchor="middle" fill="white" font-family="sans-serif" font-size="28">'.$label.'</text><text x="400" y="330" text-anchor="middle" fill="white" font-family="sans-serif" font-size="20">Demo item — placeholder photo</text></svg>');
        $listing->images()->create(['path' => $path, 'sort_order' => 0]);
    }
}
