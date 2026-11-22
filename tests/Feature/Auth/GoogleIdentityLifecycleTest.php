<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleIdentityLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_account_identifier_has_persistent_storage(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'google_id'));
    }

    public function test_google_account_identifier_cannot_be_mass_assigned_or_serialized(): void
    {
        $user = User::factory()->create();
        $user->fill(['google_id' => 'browser-supplied-id'])->save();
        $this->assertNull($user->fresh()->google_id);
        $user->forceFill(['google_id' => 'provider-subject'])->save();
        $this->assertSame('provider-subject', $user->fresh()->google_id);
        $this->assertArrayNotHasKey('google_id', $user->fresh()->toArray());
    }

    public function test_email_change_from_a_stale_profile_removes_the_previous_google_link(): void
    {
        $user = User::factory()->create();
        $staleProfile = $user->fresh();
        $user->forceFill(['google_id' => 'previous-google-owner'])->save();

        $staleProfile->update(['email' => 'changedaddress@gmail.com']);

        $this->assertNull($user->fresh()->google_id);
    }

    public function test_correcting_the_legal_name_keeps_the_same_google_link(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['google_id' => 'same-google-account'])->save();

        $user->update(['name' => 'Maria Santos Reyes']);

        $this->assertSame('same-google-account', $user->fresh()->google_id);
    }

    public function test_closing_the_account_removes_its_google_identifier(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['google_id' => 'closed-google-account'])->save();

        $user->closeAccount();

        $this->assertNull(User::withTrashed()->findOrFail($user->id)->google_id);
        $this->assertSoftDeleted($user);
    }

    public function test_google_members_can_choose_google_confirmation_from_the_account_deletion_form(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['google_id' => 'google-member'])->save();

        $this->actingAs($user)->withSession([
            'password_hash_web' => Auth::guard('web')->hashPasswordForCookie($user->getAuthPassword()),
        ])->get(route('profile'))->assertOk()
            ->assertSee('Delete with Google')
            ->assertSee(route('profile.delete-with-google'), false);
    }

    public function test_identity_step_links_to_correcting_the_account_name(): void
    {
        config(['services.identity' => [
            'base_url' => 'https://identity.example.test', 'client_id' => 'test-client',
            'client_secret' => 'test-secret', 'public_key' => 'test-public',
            'sdk_url' => 'https://sdk.example.test/liveness.js', 'environment' => 'sandbox',
        ]]);
        $this->actingAs(User::factory()->create())->get(route('identity.verify'))->assertOk()
            ->assertSee('Correct your name')->assertSee(route('profile'), false);
    }
}
