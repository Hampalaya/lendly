<?php

namespace Tests\Feature;

use App\Enums\RentalRequestStatus;
use App\Livewire\Owner\RentalRequests\Index as OwnerRequests;
use App\Livewire\RentalRequests\Agreement;
use App\Livewire\RentalRequests\Create;
use App\Livewire\Renter\RentalRequests\Index as RenterRequests;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\User;
use App\Notifications\TalaNotification;
use App\Services\CancellationPolicy;
use App\Services\RentalAgreement;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TermsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function requestFixture(bool $approve = true): array
    {
        $owner = User::factory()->identityVerified()->owner()->create();
        $renter = User::factory()->identityVerified()->renter()->create();
        $category = Category::create(['name' => 'Tools', 'slug' => 'tools']);
        $listing = Listing::create([
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Terms Test Item',
            'description' => 'An item for rent.',
            'condition' => 'good',
            'price_per_day' => 100,
            'security_deposit' => 500,
            'location' => 'Manila',
            'max_rental_duration_days' => 10,
            'status' => 'published',
            'is_available' => true,
            'pickup_available' => true,
            'rental_rules' => 'Clean the item before returning it.',
        ]);

        Livewire::actingAs($renter)->test(Create::class, ['listing' => $listing])->set('accept_terms', true)
            ->set('start_date', now()->addDays(3)->toDateString())
            ->set('end_date', now()->addDays(4)->toDateString())
            ->call('submit')->assertHasNoErrors();
        $request = RentalRequest::firstOrFail();

        if ($approve) {
            Livewire::actingAs($owner)->test(OwnerRequests::class)
                ->call('approve', $request->id)->assertHasNoErrors()
                ->assertRedirect(route('owner.rental-requests.agreement', $request));
        }

        return [$owner, $renter, $listing, $request->fresh()];
    }

    private function acceptAs(User $user, RentalRequest $request): void
    {
        Livewire::actingAs($user)->test(Agreement::class, ['rentalRequest' => $request])
            ->set('accept_terms', true)->call('acceptTerms')->assertHasNoErrors();
    }

    public function test_submission_and_approval_precede_both_parties_terms_acceptance(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture(false);
        $this->assertSame(RentalRequestStatus::Requested, $request->status);
        $this->assertNull($request->renter_terms_accepted_at);
        $this->assertNull($request->owner_terms_accepted_at);
        $this->assertSame(0, Rental::count());

        // Pre-approval acceptance cannot substitute for accepting the saved
        // agreement offered after approval.
        $request->update(['renter_terms_accepted_at' => now(), 'owner_terms_accepted_at' => now()]);
        Livewire::actingAs($owner)->test(OwnerRequests::class)->call('approve', $request->id)->assertHasNoErrors();
        $request->refresh();

        $this->assertSame(RentalRequestStatus::AwaitingAgreement, $request->status);
        $this->assertNull($request->renter_terms_accepted_at);
        $this->assertNull($request->owner_terms_accepted_at);
        $this->assertNotNull($request->agreement_terms);
        $this->assertSame(0, Rental::count());
        Livewire::actingAs($owner)->test(OwnerRequests::class)->set('filter', 'awaiting_agreement')->assertSee('Review Rental Agreement');
        Livewire::actingAs($renter)->test(RenterRequests::class)->assertSee('Review Rental Agreement');
    }

    public function test_both_parties_must_explicitly_check_the_acceptance_box(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        Notification::fake();

        foreach ([$owner, $renter] as $user) {
            Livewire::actingAs($user)->test(Agreement::class, ['rentalRequest' => $request])
                ->call('acceptTerms')->assertHasErrors(['accept_terms'])
                ->assertSee('You must read and accept the rental terms and agreement');
        }

        $this->assertNull($request->fresh()->owner_terms_accepted_at);
        $this->assertNull($request->fresh()->renter_terms_accepted_at);
        $this->assertSame(0, Rental::count());
        Notification::assertNothingSent();
    }

    public static function acceptanceOrders(): array
    {
        return ['owner first' => [true], 'renter first' => [false]];
    }

    #[DataProvider('acceptanceOrders')]
    public function test_booking_is_created_only_after_both_saved_acceptances_in_either_order(bool $ownerFirst): void
    {
        [$owner, $renter, $listing, $request] = $this->requestFixture();
        Notification::fake();
        $first = $ownerFirst ? $owner : $renter;
        $second = $ownerFirst ? $renter : $owner;
        $firstField = $ownerFirst ? 'owner_terms_accepted_at' : 'renter_terms_accepted_at';
        $secondField = $ownerFirst ? 'renter_terms_accepted_at' : 'owner_terms_accepted_at';

        $this->acceptAs($first, $request);
        $request->refresh();
        $firstAcceptedAt = $request->$firstField->toDateTimeString();
        $this->assertNull($request->$secondField);
        $this->assertSame(0, Rental::count());
        Notification::assertNothingSent();
        Livewire::actingAs($first)->test(Agreement::class, ['rentalRequest' => $request])
            ->assertSee('Waiting for the other party')->assertDontSee('View booking and pay');

        $this->acceptAs($second, $request);
        $request->refresh();
        $this->assertNotNull($request->owner_terms_accepted_at);
        $this->assertNotNull($request->renter_terms_accepted_at);
        $this->assertSame(1, Rental::count());
        $booking = $request->rental;
        $this->assertSame($request->id, $booking->rental_request_id);
        $this->assertSame($listing->id, $booking->listing_id);
        $this->assertSame($owner->id, $booking->owner_id);
        $this->assertSame($renter->id, $booking->renter_id);
        $this->assertSame($request->start_date->toDateString(), $booking->start_date->toDateString());
        $this->assertSame($request->end_date->toDateString(), $booking->end_date->toDateString());
        foreach (['rental_days', 'fulfillment_method', 'rental_fee', 'commission_rate', 'commission_amount', 'security_deposit', 'total_amount'] as $field) {
            $this->assertSame($request->$field, $booking->$field);
        }
        $this->assertSame('payment_pending', $booking->status->value);
        foreach ([$owner, $renter] as $user) {
            $interface = $user->id === $owner->id ? 'owner' : 'renter';
            Notification::assertSentTo($user, TalaNotification::class, fn ($notice) => $notice->type === 'booking_confirmed' && $notice->url === route($interface.'.rentals.show', $booking));
        }

        // A delayed second click preserves timestamps and the single booking.
        $this->travel(1)->hours();
        $this->acceptAs($first, $request);
        $this->acceptAs($second, $request);
        $this->assertSame($firstAcceptedAt, $request->fresh()->$firstField->toDateTimeString());
        $this->assertSame(1, Rental::count());
        Notification::assertCount(2);

        Livewire::actingAs($renter)->test(Agreement::class, ['rentalRequest' => $request])
            ->assertSee('Booking confirmed')->assertSee('View booking and pay')->assertDontSee('Accept rental agreement');
    }

    public function test_unsaved_renter_acceptance_cannot_finalize_a_booking(): void
    {
        [$owner, , , $request] = $this->requestFixture();
        $request->renter_terms_accepted_at = now();
        $this->assertNull(RentalAgreement::accept($request, $owner));
        $this->assertNull($request->fresh()->renter_terms_accepted_at);
        $this->assertSame(0, Rental::count());
    }

    #[DataProvider('acceptanceOrders')]
    public function test_final_acceptance_rechecks_the_other_partys_persisted_acceptance(bool $ownerFirst): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        $first = $ownerFirst ? $owner : $renter;
        $second = $ownerFirst ? $renter : $owner;
        $firstField = $ownerFirst ? 'owner_terms_accepted_at' : 'renter_terms_accepted_at';
        $secondField = $ownerFirst ? 'renter_terms_accepted_at' : 'owner_terms_accepted_at';
        $this->acceptAs($first, $request);
        $form = Livewire::actingAs($second)->test(Agreement::class, ['rentalRequest' => $request->fresh()])
            ->set('accept_terms', true);

        // The finalizer must read the saved state, even when the form was
        // opened before the other party's acceptance was cleared.
        RentalRequest::whereKey($request->id)->update([$firstField => null]);
        Notification::fake();
        $form->call('acceptTerms')->assertHasNoErrors()->assertSee('Waiting for the other party');

        $this->assertNull($request->fresh()->$firstField);
        $this->assertNotNull($request->fresh()->$secondField);
        $this->assertSame(0, Rental::count());
        Notification::assertNothingSent();
        $this->acceptAs($first, $request);
        $this->assertSame(1, Rental::count());
        Notification::assertCount(2);
    }

    public function test_both_parties_see_the_same_saved_cancellation_fees_rules_and_charges(): void
    {
        [$owner, $renter, $listing, $request] = $this->requestFixture();
        $terms = $request->agreement_terms;
        $listing->update(['rental_rules' => 'A new rule after approval.', 'price_per_day' => 999, 'security_deposit' => 9999]);

        foreach ([$owner, $renter] as $user) {
            Livewire::actingAs($user)->test(Agreement::class, ['rentalRequest' => $request])
                ->assertSee('Cancellation policy and fees')->assertSee('at least 24 hours')
                ->assertSee('20% of the rental fee')->assertSee('40.00')
                ->assertSee('Clean the item before returning it.')->assertDontSee('A new rule after approval.')
                ->assertSee('720.00')->assertSee('Return, damages and security deposit');
        }

        $this->acceptAs($owner, $request);
        $this->acceptAs($renter, $request);
        $this->assertSame($terms, $request->fresh()->agreement_terms);
        $this->assertSame('720.00', $request->fresh()->rental->total_amount);
    }

    public function test_approval_persists_detailed_clauses_for_both_parties(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        $clauses = $request->agreement_terms['clauses'] ?? [];

        $this->assertNotEmpty($clauses);
        $this->assertContains('Owner responsibilities', array_column($clauses, 'title'));
        $this->assertContains('Renter responsibilities', array_column($clauses, 'title'));
        foreach ($clauses as $clause) {
            $this->assertNotEmpty($clause['title']);
            $this->assertNotEmpty($clause['paragraphs']);
        }

        foreach ([$owner, $renter] as $user) {
            $preview = Livewire::actingAs($user)->test(Agreement::class, ['rentalRequest' => $request]);
            foreach ($clauses as $clause) {
                $preview->assertSee($clause['title']);
                foreach ($clause['paragraphs'] as $paragraph) {
                    $this->assertNotEmpty($paragraph);
                    $preview->assertSee($paragraph);
                }
            }
        }
    }

    public function test_agreement_renders_only_saved_clauses_and_escapes_their_content(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        $title = 'Saved <title> & conditions';
        $paragraph = 'Return this <script>alert("saved")</script> item & keep the signed terms.';
        $terms = $request->agreement_terms;
        $terms['clauses'] = [['title' => $title, 'paragraphs' => [$paragraph]]];
        $request->update(['agreement_terms' => $terms]);

        foreach ([$owner, $renter] as $user) {
            Livewire::actingAs($user)->test(Agreement::class, ['rentalRequest' => $request])
                ->assertSee($title)->assertSee($paragraph)
                ->assertDontSee('<script>alert("saved")</script>', false)
                ->assertDontSee('Owner responsibilities')->assertDontSee('Renter responsibilities');
        }
    }

    public function test_legacy_agreements_keep_their_saved_terms_without_new_clauses(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        $terms = $request->agreement_terms;
        unset($terms['clauses']);
        $terms['rental_rules'] = 'The originally approved item rule.';
        $terms['cancellation_window_hours'] = 72;
        $terms['cancellation_fee_percentage'] = 15;
        $request->update(['agreement_terms' => $terms]);

        foreach ([$owner, $renter] as $user) {
            Livewire::actingAs($user)->test(Agreement::class, ['rentalRequest' => $request])
                ->assertSee('The originally approved item rule.')
                ->assertSee('at least 72 hours')->assertSee('15% of the rental fee')
                ->assertDontSee('Owner responsibilities')->assertDontSee('Renter responsibilities');
        }
    }

    public function test_agreement_document_records_the_parties_and_each_saved_acceptance(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 10, 20)->setTime(1, 5));
        [$owner, $renter, , $request] = $this->requestFixture();

        foreach ([null, $owner, $renter] as $participant) {
            if ($participant !== null) {
                $this->acceptAs($participant, $request);
            }

            $preview = Livewire::actingAs($renter)->test(Agreement::class, ['rentalRequest' => $request]);
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$preview->html());
            $agreement = (new \DOMXPath($document))->query('//article[@aria-label="Rental agreement document"]')->item(0);
            $this->assertNotNull($agreement);
            $text = $agreement->textContent;
            $this->assertStringContainsString($owner->name, $text);
            $this->assertStringContainsString($renter->name, $text);
            $this->assertStringContainsString('Recorded acceptance', $text);
            $this->assertStringContainsString('Owner acceptance', $text);
            $this->assertStringContainsString('Renter acceptance', $text);
            $this->assertSame($participant === null ? 2 : ($participant->is($owner) ? 1 : 0), substr_count($text, 'Awaiting acceptance'));

            if ($participant !== null) {
                $this->assertStringContainsString('9:05 AM', $text);
                $this->assertStringContainsString('Philippine time', $text);
            }
            if ($participant?->is($renter)) {
                $this->assertStringContainsString('9:22 AM', $text);
            }

            if ($participant?->is($owner)) {
                $this->travel(17)->minutes();
            }
        }
    }

    public function test_unrelated_users_cannot_view_or_accept_an_agreement(): void
    {
        [, , , $request] = $this->requestFixture();
        foreach ([User::factory()->identityVerified()->owner()->create(), User::factory()->identityVerified()->renter()->create(), User::factory()->admin()->create()] as $user) {
            Livewire::actingAs($user)->test(Agreement::class, ['rentalRequest' => $request])->assertForbidden();
        }
        $this->assertNull($request->fresh()->owner_terms_accepted_at);
        $this->assertNull($request->fresh()->renter_terms_accepted_at);
        $this->assertSame(0, Rental::count());
    }

    public static function nonApprovedStates(): array
    {
        return ['pending' => ['requested'], 'declined' => ['rejected'], 'cancelled' => ['cancelled']];
    }

    #[DataProvider('nonApprovedStates')]
    public function test_agreement_cannot_be_accepted_if_request_is_no_longer_approved(string $status): void
    {
        [$owner, , , $request] = $this->requestFixture();
        $form = Livewire::actingAs($owner)->test(Agreement::class, ['rentalRequest' => $request])->set('accept_terms', true);
        $request->update(['status' => $status]);
        Notification::fake();

        $form->call('acceptTerms')->assertForbidden();
        $this->assertNull($request->fresh()->owner_terms_accepted_at);
        $this->assertSame(0, Rental::count());
        Notification::assertNothingSent();
    }

    public function test_booking_creation_failure_rolls_back_the_final_acceptance(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        $this->acceptAs($owner, $request);
        Notification::fake();
        $eventName = 'eloquent.creating: '.Rental::class;
        Event::listen($eventName, fn () => throw new RuntimeException('Booking storage failed.'));

        try {
            RentalAgreement::accept($request, $renter);
            $this->fail('The simulated booking storage failure should be raised.');
        } catch (RuntimeException $error) {
            $this->assertSame('Booking storage failed.', $error->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $this->assertNotNull($request->fresh()->owner_terms_accepted_at);
        $this->assertNull($request->fresh()->renter_terms_accepted_at);
        $this->assertSame(0, Rental::count());
        Notification::assertNothingSent();
    }

    public function test_cancellation_before_finalization_blocks_later_acceptance(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        $this->acceptAs($owner, $request);
        Livewire::actingAs($renter)->test(RenterRequests::class)->call('cancel', $request->id)->assertHasNoErrors();

        Livewire::actingAs($renter)->test(Agreement::class, ['rentalRequest' => $request->fresh()])->assertForbidden();
        $this->assertSame(0, Rental::count());
    }

    public function test_missing_saved_agreement_cannot_finalize_a_legacy_request(): void
    {
        [$owner, , , $request] = $this->requestFixture();
        $request->update(['agreement_terms' => null, 'renter_terms_accepted_at' => now()]);
        Livewire::actingAs($owner)->test(Agreement::class, ['rentalRequest' => $request])
            ->set('accept_terms', true)->call('acceptTerms')->assertHasErrors(['agreement']);
        $this->assertNull($request->fresh()->owner_terms_accepted_at);
        $this->assertSame(0, Rental::count());
    }

    public function test_cancellation_policy_matches_the_saved_agreement_at_the_chargeable_window_boundary(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        $this->acceptAs($owner, $request);
        $this->acceptAs($renter, $request);
        $booking = $request->fresh()->rental;
        $this->travelTo(Carbon::parse($booking->rentalRequest->agreement_terms['cancellation_reference_at'])->subHours(24));
        $this->assertSame(0.0, CancellationPolicy::evaluate($booking)['fee']);
        $this->travel(1)->seconds();
        $this->assertSame(40.0, CancellationPolicy::evaluate($booking)['fee']);
    }

    public function test_final_acceptance_rechecks_the_other_partys_account_status(): void
    {
        [$owner, $renter, , $request] = $this->requestFixture();
        $this->acceptAs($owner, $request);
        $owner->update(['status' => 'suspended']);
        Livewire::actingAs($renter)->test(Agreement::class, ['rentalRequest' => $request])
            ->set('accept_terms', true)->call('acceptTerms')->assertForbidden();
        $this->assertNull($request->fresh()->renter_terms_accepted_at);
        $this->assertDatabaseCount('rentals', 0);
    }
}
