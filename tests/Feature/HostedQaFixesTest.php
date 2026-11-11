<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class HostedQaFixesTest extends TestCase
{
    public function test_cancellation_displays_philippine_time_and_does_not_mutate_the_stored_date(): void
    {
        $record = (object) ['cancellation_reason' => 'Changed plans', 'cancellation_fee' => 0,
            'cancelled_at' => Carbon::parse('2026-11-07 18:30:00', 'UTC')];
        $html = Blade::render('<x-cancellation-details :record="$record" />', ['record' => $record]);
        $this->assertStringContainsString('Cancelled Nov 08, 2026 at 2:30 AM', $html);
        $this->assertStringContainsString('(Philippine time)', $html);
        $this->assertSame('2026-11-07 18:30:00 +00:00', $record->cancelled_at->format('Y-m-d H:i:s P'));
    }

    public function test_https_login_and_missing_pages_have_security_headers(): void
    {
        config(['session.driver' => 'array']);
        foreach (['/login', '/qa-nonexistent-page'] as $path) {
            $this->get('https://localhost'.$path)
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Strict-Transport-Security', 'max-age=31536000')
                ->assertHeaderMissing('X-Powered-By');
        }
    }

    public function test_http_local_development_does_not_receive_hsts(): void
    {
        config(['session.driver' => 'array']);
        $this->get('http://localhost/login')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_face_runtime_command_propagates_health_failure_without_private_output(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'PRIVATE SERVER DETAILS', exitCode: 1)]);
        $this->artisan('identity:check-runtime')->expectsOutputToContain('Face runtime unavailable')->assertFailed();
    }
}
