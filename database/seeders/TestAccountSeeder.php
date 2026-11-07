<?php

namespace Database\Seeders;

class TestAccountSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->ensureDemoEnvironment();
        $accounts = [
            ['name' => 'Tala Admin', 'email' => 'admin@tala.test', 'role' => 'admin'],
            ['name' => 'Demo Member', 'email' => 'member@tala.test', 'role' => 'member'],
        ];

        foreach ($accounts as $account) {
            $this->demoUser($account);
        }
    }
}
