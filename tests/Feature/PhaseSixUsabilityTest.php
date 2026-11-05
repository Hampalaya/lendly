<?php

namespace Tests\Feature;

use App\Enums\DisputeResolution;
use App\Enums\NotificationType;
use App\Livewire\Admin\Disputes\Index as Disputes;
use App\Livewire\Admin\Listings\Index;
use App\Livewire\Member\Dashboard;
use App\Livewire\Notifications\Index as Notifications;
use App\Livewire\Owner\Listings\Index as Listings;
use App\Livewire\Renter\RentalRequests\Index as Requests;
use App\Models\Category;
use App\Models\DamageReport;
use App\Models\Dispute;
use App\Models\IdentitySubmission;
use App\Models\Listing;
use App\Models\ListingConversation;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\User;
use App\Notifications\TalaNotification;
use App\Services\RentalLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PhaseSixUsabilityTest extends TestCase
{
    use RefreshDatabase;

    private function listing(User $owner, string $name = 'Camera'): Listing
    {
        return Listing::create([
            'owner_id' => $owner->id,
            'category_id' => Category::firstOrCreate(['slug' => 'phase-six'], ['name' => 'Photography'])->id,
            'name' => $name, 'description' => 'Camera in good condition', 'condition' => 'good',
            'price_per_day' => 100, 'security_deposit' => 500, 'location' => 'Manila',
            'status' => 'published', 'pickup_available' => true,
        ]);
    }

    private function request(Listing $listing, User $renter, string $status = 'requested'): RentalRequest
    {
        return RentalRequest::create([
            'listing_id' => $listing->id, 'renter_id' => $renter->id,
            'start_date' => now()->addDays(3), 'end_date' => now()->addDays(5), 'rental_days' => 3,
            'fulfillment_method' => 'pickup', 'rental_fee' => 300, 'commission_rate' => 10,
            'commission_amount' => 30, 'security_deposit' => 500, 'total_amount' => 830, 'status' => $status,
        ]);
    }

    private function dispute(): Dispute
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $request = $this->request($this->listing($owner), $renter, 'approved');
        $rental = Rental::create(collect($request->getAttributes())->except(['id', 'created_at', 'updated_at', 'status'])->all()
            + ['owner_id' => $owner->id, 'rental_request_id' => $request->id, 'status' => 'completed']);

        return Dispute::create(['rental_id' => $rental->id, 'raised_by' => $renter->id,
            'reason' => 'incorrect_charge', 'description' => 'Please review the charge']);
    }

    public function test_interface_switch_keeps_the_authenticated_member_and_sets_the_destination(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->post(route('interface.switch'), ['interface' => 'owner'])->assertRedirect(route('owner.dashboard'));
        $this->assertAuthenticatedAs($member);
        $this->assertSame('owner', session('active_interface'));
        $this->get(route('owner.dashboard'))->assertOk();
        $this->post(route('interface.switch'), ['interface' => 'renter'])->assertRedirect(route('renter.dashboard'));
        $this->assertAuthenticatedAs($member);
        $this->assertSame('renter', session('active_interface'));
        $this->get(route('renter.dashboard'))->assertOk();
    }

    public function test_switching_to_admin_or_using_an_admin_account_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->post(route('interface.switch'), ['interface' => 'admin'])->assertSessionHasErrors('interface');
        $this->actingAs(User::factory()->admin()->create())->post(route('interface.switch'), ['interface' => 'owner'])->assertForbidden();
    }

    public function test_admin_sidebar_has_no_demo_switcher_browse_or_map_tabs(): void
    {
        Volt::actingAs(User::factory()->admin()->create())->test('layout.sidebar')
            ->assertDontSee('switchDemoUser', false)->assertDontSee('Switch to owner')
            ->assertDontSee('>Browse<', false)->assertDontSee('>Map<', false);
    }

    public function test_listing_search_keeps_the_owner_scope_even_when_a_category_matches(): void
    {
        $owner = User::factory()->create();
        $own = $this->listing($owner, 'Own item');
        $this->listing(User::factory()->create(), 'Private other item');
        Livewire::actingAs($owner)->test(Listings::class)->set('recordSearch', 'Photography')
            ->assertViewHas('listings', fn ($rows) => $rows->pluck('id')->all() === [$own->id])
            ->set('recordSearch', 'Private other item')->assertViewHas('listings', fn ($rows) => $rows->isEmpty());
    }

    public function test_listing_search_filter_and_sort_work_together_and_reset_pagination(): void
    {
        $this->freezeTime();
        $owner = User::factory()->create();
        $first = $this->listing($owner, 'Camera one');
        $second = $this->listing($owner, 'Camera two');
        $second->update(['status' => 'inactive']);
        Livewire::actingAs($owner)->test(Listings::class)->set('recordSearch', 'Camera')
            ->assertViewHas('listings', fn ($rows) => $rows->pluck('id')->all() === [$second->id, $first->id])
            ->call('toggleRecordSort')->assertSet('recordSort', 'oldest')
            ->assertViewHas('listings', fn ($rows) => $rows->pluck('id')->all() === [$first->id, $second->id])
            ->set('filter', 'inactive')->assertViewHas('listings', fn ($rows) => $rows->pluck('id')->all() === [$second->id])
            ->call('setPage', 2)->call('toggleRecordSort')->assertSet('recordSort', 'newest')->assertSet('paginators.page', 1)
            ->assertViewHas('listings', fn ($rows) => $rows->pluck('id')->all() === [$second->id])
            ->call('setPage', 2)->set('recordSearch', 'two')->assertSet('paginators.page', 1);
    }

    public function test_renter_requests_honor_link_filters_and_cannot_search_other_renters_records(): void
    {
        $this->freezeTime();
        $member = User::factory()->create();
        $listing = $this->listing(User::factory()->create());
        $own = $this->request($listing, $member, 'awaiting_agreement');
        $recent = $this->request($listing, $member, 'awaiting_agreement');
        $this->request($listing, User::factory()->create());
        Livewire::actingAs($member)->test(Requests::class)->set('filter', 'awaiting_agreement')->set('recordSearch', 'Camera')
            ->assertViewHas('rentalRequests', fn ($rows) => $rows->pluck('id')->all() === [$recent->id, $own->id])
            ->call('setPage', 2)->call('toggleRecordSort')->assertSet('recordSort', 'oldest')->assertSet('paginators.page', 1)
            ->assertViewHas('rentalRequests', fn ($rows) => $rows->pluck('id')->all() === [$own->id, $recent->id])
            ->call('toggleRecordSort')->assertSet('recordSort', 'newest')
            ->assertViewHas('rentalRequests', fn ($rows) => $rows->pluck('id')->all() === [$recent->id, $own->id])
            ->set('filter', 'requested')->assertViewHas('rentalRequests', fn ($rows) => $rows->isEmpty());
    }

    public function test_notifications_search_read_and_category_filters_preserve_account_and_interface_scope(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $member->notify(new TalaNotification('verification_update', 'Identity pending', 'Review in progress', route('identity.verify')));
        $member->notify(new TalaNotification('listing_update', 'Owner camera update', 'Published', route('owner.listings.index')));
        $other->notify(new TalaNotification('verification_update', 'Private identity pending', 'Other account', route('identity.verify')));
        $this->withSession(['active_interface' => 'renter']);
        Livewire::actingAs($member)->test(Notifications::class)->set('recordSearch', 'pending')->set('typeFilter', 'verification')->set('filter', 'unread')
            ->assertViewHas('notifications', fn ($rows) => $rows->count() === 1 && $rows->first()->notifiable_id === $member->id)
            ->call('markAllAsRead')->set('filter', 'read')->assertViewHas('notifications', fn ($rows) => $rows->count() === 1)
            ->set('typeFilter', 'listings')->set('recordSearch', '')->assertViewHas('notifications', fn ($rows) => $rows->isEmpty());
        $this->assertSame(1, $other->unreadNotifications()->count());
        $this->assertSame(1, $member->unreadNotifications()->count());
    }

    public function test_other_resolution_requires_text_and_persists_trimmed_explanation_visible_to_both_parties(): void
    {
        $dispute = $this->dispute();
        $admin = User::factory()->admin()->create();
        Livewire::actingAs($admin)->test(Disputes::class)->call('startResolving', $dispute->id)
            ->set('resolution', 'other')->call('confirmResolve')->assertHasErrors('other_resolution')
            ->set('other_resolution', '  Arrange a replacement collection  ')->call('confirmResolve')->assertHasNoErrors();
        $this->assertSame('Arrange a replacement collection', $dispute->fresh()->resolutionLabel());
        $this->assertSame($admin->id, $dispute->fresh()->resolved_by);
        foreach ([$dispute->rental->owner, $dispute->rental->renter] as $participant) {
            $this->assertStringContainsString('Arrange a replacement collection', $participant->notifications()->first()->data['message']);
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_whitespace_other_resolution_cannot_close_a_dispute(): void
    {
        $dispute = $this->dispute();
        try {
            RentalLifecycle::resolveDispute($dispute, DisputeResolution::Other, '', User::factory()->admin()->create(), null, '   ');
            $this->fail('Blank custom resolutions must fail');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('other_resolution', $exception->errors());
        }
        $this->assertTrue($dispute->fresh()->isOpen());
    }

    public function test_resolved_disputes_cannot_be_overwritten_by_a_stale_admin_screen(): void
    {
        $dispute = $this->dispute();
        $admin = User::factory()->admin()->create();
        RentalLifecycle::resolveDispute($dispute, DisputeResolution::AccountWarning, 'First decision', $admin);
        Livewire::actingAs($admin)->test(Disputes::class)->call('startResolving', $dispute->id)
            ->set('resolution', 'other')->set('other_resolution', 'Overwrite')->call('confirmResolve')->assertForbidden();
        $this->assertSame('First decision', $dispute->fresh()->resolution_notes);
    }

    public function test_custom_resolution_is_escaped_in_the_admin_list(): void
    {
        $dispute = $this->dispute();
        $admin = User::factory()->admin()->create();
        RentalLifecycle::resolveDispute($dispute, DisputeResolution::Other, null, $admin, null, '<script>alert(1)</script>');
        Livewire::actingAs($admin)->test(Disputes::class)->set('filter', 'resolved')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_dashboard_counts_only_selected_interfaces_requests_and_reports_latest_identity_progress(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $this->request($this->listing($other), $member, 'awaiting_agreement');
        $this->request($this->listing($member, 'Owner item'), $other);
        IdentitySubmission::create(['user_id' => $member->id, 'status' => 'rejected', 'profile_hash' => IdentitySubmission::profileHash($member), 'consented_at' => now()]);
        $this->withSession(['active_interface' => 'renter']);
        Livewire::actingAs($member)->test(Dashboard::class)->assertViewHas('awaitingAgreementCount', 1)
            ->assertViewHas('identityProgress', 'rejected')->assertSee('Rental request progress')->assertSee('Your rentals by status');
        $this->withSession(['active_interface' => 'owner']);
        Livewire::test(Dashboard::class)->assertViewHas('awaitingAgreementCount', 0)
            ->assertViewHas('requestProgress', fn ($rows) => $rows->sum('value') === 1);
    }

    public function test_verification_cancellation_notifies_only_once_and_contains_no_private_paths(): void
    {
        $member = User::factory()->create();
        IdentitySubmission::create(['user_id' => $member->id, 'status' => 'pending', 'profile_hash' => IdentitySubmission::profileHash($member), 'consented_at' => now()]);
        IdentitySubmission::cancelPending($member->id);
        IdentitySubmission::cancelPending($member->id);
        $this->assertSame(1, $member->notifications()->count());
        $notification = $member->notifications()->first()->data;
        $this->assertSame(NotificationType::VerificationUpdate->value, $notification['type']);
        $this->assertStringNotContainsString('identity-documents/', json_encode($notification));
    }

    public function test_admin_listing_decision_notifies_owner_and_cannot_be_called_by_a_member(): void
    {
        $owner = User::factory()->create();
        $listing = $this->listing($owner);
        Livewire::actingAs(User::factory()->admin()->create())->test(Index::class)
            ->call('startRejecting', $listing->id)->set('rejection_reason', 'Please provide clearer photos')->call('confirmReject')->assertHasNoErrors();
        $this->assertSame('listing_update', $owner->notifications()->first()->data['type']);
        $this->assertStringContainsString('clearer photos', $owner->notifications()->first()->data['message']);
        Livewire::actingAs($owner)->test(Index::class)->assertForbidden();
    }

    public function test_nested_admin_searches_keep_selected_status_filters(): void
    {
        $dispute = $this->dispute();
        $dispute->rental->owner->update(['name' => 'Searchable owner']);
        $report = DamageReport::create(['rental_id' => $dispute->rental_id,
            'damage_type' => 'Scratch', 'description' => 'Lens scratch', 'estimated_repair_cost' => 100,
            'proposed_deduction' => 100, 'status' => 'disputed']);
        Livewire::actingAs(User::factory()->admin()->create())->test(\App\Livewire\Admin\DamageReports\Index::class)
            ->set('recordSearch', 'Searchable owner')->set('recordSort', 'oldest')
            ->assertViewHas('damageReports', fn ($rows) => $rows->pluck('id')->all() === [$report->id])
            ->set('filter', 'accepted')->assertViewHas('damageReports', fn ($rows) => $rows->isEmpty());
        Livewire::test(Disputes::class)->set('recordSearch', 'Searchable owner')->set('recordSort', 'newest')
            ->assertViewHas('disputes', fn ($rows) => $rows->pluck('id')->all() === [$dispute->id]);
        Livewire::test(\App\Livewire\Admin\Rentals\Index::class)->set('search', 'Searchable owner')->set('recordSort', 'oldest')
            ->assertViewHas('rentals', fn ($rows) => $rows->pluck('id')->all() === [$dispute->rental_id]);
    }

    public function test_member_rental_search_cannot_leak_a_matching_nonparticipant_booking(): void
    {
        $rental = $this->dispute()->rental;
        Livewire::actingAs($rental->renter)->test(\App\Livewire\Renter\Rentals\Index::class)
            ->set('recordSearch', 'Camera')->set('filter', 'completed')->set('recordSort', 'oldest')
            ->assertViewHas('rentals', fn ($rows) => $rows->pluck('id')->all() === [$rental->id]);
        Livewire::actingAs(User::factory()->create())->test(\App\Livewire\Renter\Rentals\Index::class)
            ->set('recordSearch', 'Camera')->assertViewHas('rentals', fn ($rows) => $rows->isEmpty());
        Livewire::actingAs($rental->owner)->test(\App\Livewire\Owner\Rentals\Index::class)
            ->set('recordSearch', $rental->renter->name)->set('recordSort', 'newest')
            ->assertViewHas('rentals', fn ($rows) => $rows->pluck('id')->all() === [$rental->id]);
    }

    public function test_chat_filters_combine_with_search_and_activity_sort(): void
    {
        $member = User::factory()->create();
        $owner = User::factory()->create();
        $listing = $this->listing($owner);
        $request = $this->request($listing, $member);
        $request->messages()->create(['sender_id' => $owner->id, 'receiver_id' => $member->id, 'body' => 'Rental chat', 'created_at' => now()->subHour()]);
        $conversation = ListingConversation::create(['listing_id' => $listing->id, 'owner_id' => $owner->id, 'renter_id' => $member->id]);
        $conversation->messages()->create(['sender_id' => $owner->id, 'receiver_id' => $member->id, 'body' => 'Item inquiry', 'read_at' => now()]);
        $this->withSession(['active_interface' => 'renter']);
        Livewire::actingAs($member)->test(\App\Livewire\Messages\Index::class)->set('search', 'Camera')
            ->assertViewHas('threads', fn ($rows) => $rows->pluck('id')->all() === ['listing-'.$conversation->id, $request->id])
            ->set('threadSort', 'oldest')->assertViewHas('threads', fn ($rows) => $rows->first()['id'] === $request->id)
            ->set('threadFilter', 'unread')->assertViewHas('threads', fn ($rows) => $rows->count() === 1 && $rows->first()['id'] === $request->id)
            ->set('threadFilter', 'listings')->assertViewHas('threads', fn ($rows) => $rows->count() === 1 && $rows->first()['id'] === 'listing-'.$conversation->id);
    }
}
