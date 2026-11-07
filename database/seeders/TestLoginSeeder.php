<?php

namespace Database\Seeders;

class TestLoginSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->demoUser([
            'email' => 'test@lendly.test',
            'name' => 'Test User',
            'phone' => '0917 000 0000',
            'address' => 'Manila, Philippines',
        ]);
    }
}
