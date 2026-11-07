<?php

namespace Database\Seeders;

/**
 * Extra demo member accounts (beyond admin@tala.test/owner@tala.test/
 * renter@tala.test) so seeded listings aren't all owned by one account —
 * names are Cartoon Network characters per the user's request, purely for
 * memorable demo data.
 */
class MemberSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->ensureDemoEnvironment();
        $members = [
            ['name' => 'Ben Tennyson', 'email' => 'ben.tennyson@lendly.test'],
            ['name' => 'Gumball Watterson', 'email' => 'gumball.watterson@lendly.test'],
            ['name' => 'Finn Mertens', 'email' => 'finn.mertens@lendly.test'],
            ['name' => 'Marceline Abadeer', 'email' => 'marceline.abadeer@lendly.test'],
            ['name' => 'Steven Universe', 'email' => 'steven.universe@lendly.test'],
            ['name' => 'Nigel Uno', 'email' => 'nigel.uno@lendly.test'],
            ['name' => 'Wallabee Beetles', 'email' => 'wallabee.beetles@lendly.test'],
            ['name' => 'Blossom Utonium', 'email' => 'blossom.utonium@lendly.test'],
            ['name' => 'Johnny Bravo', 'email' => 'johnny.bravo@lendly.test'],
            ['name' => 'Samurai Jack', 'email' => 'samurai.jack@lendly.test'],
            ['name' => 'Dexter', 'email' => 'dexter@lendly.test'],
            ['name' => 'Mordecai', 'email' => 'mordecai@lendly.test'],
            ['name' => 'Rigby', 'email' => 'rigby@lendly.test'],
            ['name' => 'Robin', 'email' => 'robin@lendly.test'],
            ['name' => 'Starfire', 'email' => 'starfire@lendly.test'],
            ['name' => 'Courage', 'email' => 'courage@lendly.test'],
            ['name' => 'Eddy', 'email' => 'eddy@lendly.test'],
            ['name' => 'Double D', 'email' => 'double.d@lendly.test'],
            ['name' => 'Mac', 'email' => 'mac@lendly.test'],
            ['name' => 'Bubbles Utonium', 'email' => 'bubbles.utonium@lendly.test'],
        ];

        foreach ($members as $member) {
            $this->demoUser($member, identityVerified: true);
        }
    }
}
