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

    public static function memberName(int $number): string
    {
        $first = ['Miguel', 'Sofia', 'Gabriel', 'Isabella', 'Joshua', 'Andrea', 'Paolo', 'Camille', 'Rafael', 'Bianca'];
        $last = ['Santos', 'Reyes', 'Cruz', 'Garcia', 'Mendoza', 'Ramos', 'Torres', 'Flores', 'Villanueva', 'Castillo'];

        return $first[($number - 1) % 10].' '.$last[intdiv($number - 1, 10) % 10];
    }

    private bool $confirmed = false;

    private int $credentialLimit = 0;

    private array $newCredentials = [];

    public function seedConfirmed(int $credentialLimit = 0): array
    {
        $this->credentialLimit = max(0, min(10, $credentialLimit));
        $this->newCredentials = [];
        $this->confirmed = true;
        try {
            $this->run();

            return $this->newCredentials;
        } finally {
            $this->confirmed = false;
            $this->newCredentials = [];
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
        $attributes['password'] = Str::random(24);
        preg_match('/demo(\d+)@/', $attributes['email'], $match);
        $attributes['name'] = self::memberName((int) $match[1]);
        $attributes['role'] = UserRole::Member;
        $attributes['identity_reference'] = self::REFERENCE;

        $user = User::unguarded(fn () => parent::demoUser($attributes, $identityVerified));
        if ($user->wasRecentlyCreated && count($this->newCredentials) < $this->credentialLimit) {
            $this->newCredentials[] = [$user->email, $attributes['password']];
        }

        return $user;
    }

    protected function attachDemoImage(Listing $listing): void
    {
        // This asset ships with the app; no writable/ephemeral upload disk is needed.
        $listing->images()->create(['path' => self::IMAGE, 'sort_order' => 0]);
    }
}
