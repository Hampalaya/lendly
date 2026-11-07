<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\User;
use Database\Seeders\HostedMarketplaceSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SeedHostedDemo extends Command
{
    protected $signature = 'demo:seed-hosted
        {--apply : Create the 100 demo members and up to 250 demo listings}
        {--confirm-production : Explicitly approve this synthetic dataset on a hosted application}';

    protected $description = 'Preview or explicitly create the hosted marketplace demonstration dataset';

    public function handle(): int
    {
        $this->info('Hosted demo: 100 synthetic members and up to 250 labelled listings.');
        $this->line('New members have synthetic verification for demonstration and unexposed random passwords. No admins, bookings or payments are created.');
        $this->line('Existing accounts, listings, passwords and transactions are preserved.');
        $this->line('Environment: '.app()->environment());
        if (! $this->option('apply')) {
            $this->info('Preview only. No records were changed. Use --apply --confirm-production to proceed.');

            return self::SUCCESS;
        }
        if (! $this->option('confirm-production')) {
            $this->error('Confirmation is required: add --confirm-production after reviewing the dataset.');

            return self::FAILURE;
        }

        try {
            DB::transaction(fn () => app(HostedMarketplaceSeeder::class)->seedConfirmed());
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Hosted demo seeding failed and database changes were rolled back. Check the application log for details.');

            return self::FAILURE;
        }

        $members = User::withTrashed()->where('identity_reference', HostedMarketplaceSeeder::REFERENCE);
        $count = Listing::withTrashed()->whereIn('owner_id', (clone $members)->select('id'))->where('name', 'like', 'Demo Item %')->count();
        $this->info('Hosted demo records: '.$members->count().' members, '.$count.' listings (including existing/soft-deleted fixtures).');
        $this->line('Inactive or edited existing demo members are not reactivated; their missing listings may be skipped.');

        return self::SUCCESS;
    }
}
