<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\HostedMarketplaceSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NameHostedDemo extends Command
{
    protected $signature = 'demo:name-hosted {--apply : Replace untouched numbered fixture names with fictional Filipino names}';

    protected $description = 'Preview or rename only the hosted demonstration members';

    public function handle(): int
    {
        $count = DB::transaction(function () {
            $count = 0;
            $users = User::where('email', 'like', 'hosted.demo%@tala.test')->lockForUpdate()->get();
            foreach ($users as $user) {
                if (! preg_match('/^hosted\.demo([1-9][0-9]?|100)@tala\.test$/', $user->email, $match)
                    || $user->identity_reference !== HostedMarketplaceSeeder::REFERENCE
                    || $user->name !== 'Hosted Demo Member '.$match[1]) {
                    continue;
                }
                $count++;
                if ($this->option('apply')) {
                    // Explicit fixture-only correction: retain the synthetic verification
                    // and login credentials; ordinary profile edits still clear verification.
                    DB::table('users')->where('id', $user->id)->where('name', $user->name)
                        ->update(['name' => HostedMarketplaceSeeder::memberName((int) $match[1]), 'updated_at' => now()]);
                }
            }

            return $count;
        });
        $this->info(($this->option('apply') ? 'Renamed ' : 'Would rename ').$count.' hosted demo members.');
        $this->line('Fictional Filipino names only. Existing custom names, credentials and verification are preserved.');

        return self::SUCCESS;
    }
}
