<?php

namespace Tests\Feature;

use App\Livewire\Identity\Verify;
use App\Models\IdentityVerificationAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IdentityVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_email_verified_members_can_open_identity_verification(): void
    {
        $this->get('/identity/verify')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get('/identity/verify')
            ->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->admin()->create())->get('/identity/verify')->assertForbidden();
    }

    public function test_both_member_interfaces_share_the_manual_id_review_form(): void
    {
        config(['services.identity.client_id' => null]);
        $member = User::factory()->create();

        foreach (['owner', 'renter'] as $interface) {
            $this->actingAs($member)->withSession(['active_interface' => $interface])->get('/identity/verify')
                ->assertOk()->assertSee('Submit your ID for review.')
                ->assertSee('Privacy and consent')->assertSee('Photo ID front')
                ->assertDontSee('Identity verified');
        }

        $this->assertDatabaseCount('identity_verification_attempts', 0);
        $this->assertFalse($member->fresh()->hasVerifiedIdentity());
    }

    public function test_consent_is_required_before_a_verification_attempt_is_created(): void
    {
        $member = User::factory()->create();

        Livewire::actingAs($member)->test(Verify::class)->call('submit')
            ->assertHasErrors('consent');

        $this->assertDatabaseCount('identity_submissions', 0);
    }

    public function test_name_changes_clear_identity_verification_and_cancel_pending_attempts(): void
    {
        $member = User::factory()->create();
        $member->forceFill([
            'identity_verified_at' => now(), 'identity_reference' => 'PRIVATE-REFERENCE',
            'identity_verification_environment' => 'production',
        ])->save();
        $attempt = IdentityVerificationAttempt::create([
            'user_id' => $member->id, 'status' => 'pending', 'input_hash' => str_repeat('a', 64),
            'expires_at' => now()->addMinutes(10),
        ]);

        $member->update(['name' => 'A different name']);

        $this->assertNull($member->fresh()->identity_verified_at);
        $this->assertNull($member->fresh()->identity_reference);
        $this->assertSame('cancelled', $attempt->fresh()->status);
    }

    public function test_private_identity_reference_is_encrypted_and_not_serialized(): void
    {
        $member = User::factory()->create();
        $member->forceFill(['identity_reference' => 'PRIVATE-IDENTITY-REFERENCE'])->save();

        $this->assertSame('PRIVATE-IDENTITY-REFERENCE', $member->fresh()->identity_reference);
        $this->assertNotSame('PRIVATE-IDENTITY-REFERENCE', $member->fresh()->getRawOriginal('identity_reference'));
        $this->assertArrayNotHasKey('identity_reference', $member->fresh()->toArray());
        $this->assertArrayNotHasKey('identity_verified_at', $member->fresh()->toArray());
    }

    public function test_a_stale_profile_edit_clears_identity_verified_by_another_request(): void
    {
        $member = User::factory()->create();
        $staleProfile = $member->fresh();
        $member->forceFill([
            'identity_verified_at' => now(), 'identity_reference' => 'PRIVATE-REFERENCE',
            'identity_verification_environment' => 'production',
        ])->save();

        $staleProfile->update(['name' => 'Updated account name']);

        $this->assertNull($member->fresh()->identity_verified_at);
        $this->assertNull($member->fresh()->identity_reference);
        $this->assertNull($member->fresh()->identity_verification_environment);
    }

    public function test_public_profile_only_shows_production_verification_without_private_reference(): void
    {
        $member = User::factory()->create();
        $member->forceFill([
            'identity_verified_at' => now(), 'identity_reference' => 'SECRET-REFERENCE',
            'identity_verification_environment' => 'sandbox',
        ])->save();
        $this->get(route('users.show', $member))->assertOk()->assertDontSee('Identity verified');

        $member->forceFill(['identity_verification_environment' => 'production'])->save();
        $this->get(route('users.show', $member))->assertOk()->assertSee('Identity verified')
            ->assertDontSee('SECRET-REFERENCE');
    }
}
