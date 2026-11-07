<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/** Explicit opt-in fixture for the user's public demonstration deployment. */
class HostedMarketplaceSeeder extends BulkDemoSeeder
{
    public const REFERENCE = 'hosted-marketplace-demo-v1';

    public const IMAGE = 'hosted-demo-placeholder';

    private bool $confirmed = false;

    public function seedConfirmed(): void
    {
        $this->confirmed = true;
        try {
            $this->run();
        } finally {
            $this->confirmed = false;
        }
    }

    protected function ensureDemoEnvironment(): void
    {
        if (! app()->runningInConsole() || ! $this->confirmed) {
            throw new RuntimeException('Use demo:seed-hosted --apply --confirm-production to create the hosted demo dataset.');
        }
    }

    protected function demoUser(array $attributes, bool $identityVerified = false): User
    {
        $this->ensureDemoEnvironment();
        $attributes['email'] = 'hosted.'.$attributes['email'];
        $existing = User::withTrashed()->where('email', $attributes['email'])->first();
        if ($existing && $existing->identity_reference !== self::REFERENCE) {
            throw new RuntimeException('A target demo email already exists outside this fixture. Existing accounts were not changed.');
        }

        // Do not expose the local seeders' well-known password on a public app.
        $attributes['password'] = Str::random(64);
        $attributes['name'] = 'Hosted '.$attributes['name'];
        $attributes['role'] = UserRole::Member;
        $attributes['identity_reference'] = self::REFERENCE;

        return User::unguarded(fn () => parent::demoUser($attributes, $identityVerified));
    }

    protected function attachDemoImage(Listing $listing): void
    {
        // This asset ships with the app; no writable/ephemeral upload disk is needed.
        $listing->images()->create(['path' => self::IMAGE, 'sort_order' => 0]);
    }
}
