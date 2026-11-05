<?php

namespace Tests\Feature;

use App\Livewire\Admin\Rentals\Show;
use App\Models\Category;
use App\Models\ConditionRecord;
use App\Models\ConditionRecordPhoto;
use App\Models\DamageReport;
use App\Models\DamageReportPhoto;
use App\Models\Dispute;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\SecurityDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTransactionDetailTest extends TestCase
{
    use RefreshDatabase;

    private function booking(): Rental
    {
        $owner = User::factory()->create(['name' => 'Receipt Owner', 'email' => 'receipt-owner@gmail.com']);
        $renter = User::factory()->create(['name' => 'Receipt Renter', 'email' => 'receipt-renter@gmail.com']);
        $category = Category::create(['name' => 'Cameras', 'slug' => 'receipt-cameras']);
        $listing = Listing::create([
            'owner_id' => $owner->id, 'category_id' => $category->id,
            'name' => 'Booked camera', 'description' => 'Camera rental.',
            'condition' => 'good', 'price_per_day' => 100, 'security_deposit' => 500,
            'location' => 'Manila', 'max_rental_duration_days' => 10, 'status' => 'published',
        ]);
        $request = RentalRequest::create([
            'listing_id' => $listing->id, 'renter_id' => $renter->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-03',
            'rental_days' => 3, 'fulfillment_method' => 'pickup',
            'rental_fee' => 300, 'commission_rate' => 10, 'commission_amount' => 30,
            'security_deposit' => 500, 'total_amount' => 830, 'status' => 'approved',
            'renter_terms_accepted_at' => '2026-09-28 09:00:00', 'owner_terms_accepted_at' => '2026-09-29 09:00:00',
        ]);

        return Rental::create(array_merge($request->only([
            'listing_id', 'renter_id', 'start_date', 'end_date', 'rental_days',
            'fulfillment_method', 'rental_fee', 'commission_rate', 'commission_amount',
            'security_deposit', 'total_amount',
        ]), ['rental_request_id' => $request->id, 'owner_id' => $owner->id, 'status' => 'payment_pending']));
    }

    public function test_admin_can_inspect_receipt_evidence_lifecycle_and_management_links(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Resolving Admin']);
        $rental = $this->booking();
        $rental->listing->update(['price_per_day' => 750]);
        $rental->update([
            'status' => 'completed', 'paid_at' => '2026-09-30 10:00:00',
            'pickup_confirmed_by_owner_at' => '2026-10-01 08:00:00',
            'pickup_confirmed_by_renter_at' => '2026-10-01 08:10:00',
            'return_confirmed_by_owner_at' => '2026-10-03 17:10:00',
            'return_confirmed_by_renter_at' => '2026-10-03 17:00:00',
            'completed_at' => '2026-10-04 09:00:00',
        ]);
        Payment::create(['rental_id' => $rental->id, 'transaction_reference' => 'RECEIPT-12345', 'amount' => 830, 'paid_at' => '2026-09-30 10:00:00']);
        SecurityDeposit::create(['rental_id' => $rental->id, 'amount' => 500, 'deducted_amount' => 50, 'status' => 'deducted']);
        $condition = ConditionRecord::create(['rental_id' => $rental->id, 'recorded_by' => $rental->owner_id, 'type' => 'after', 'condition' => 'good', 'notes' => 'Small scratch on return', 'has_damage' => true]);
        ConditionRecordPhoto::create(['condition_record_id' => $condition->id, 'path' => 'conditions/receipt-evidence.jpg']);
        $damage = DamageReport::create(['rental_id' => $rental->id, 'condition_record_id' => $condition->id, 'damage_type' => 'Scratch', 'description' => 'Lens cover scratch', 'estimated_repair_cost' => 75, 'proposed_deduction' => 50, 'status' => 'accepted', 'renter_response_notes' => 'Accepted the repair']);
        DamageReportPhoto::create(['damage_report_id' => $damage->id, 'path' => 'damage/receipt-evidence.jpg']);
        $dispute = Dispute::create([
            'rental_id' => $rental->id, 'damage_report_id' => $damage->id, 'raised_by' => $rental->renter_id,
            'reason' => 'deposit_disagreement', 'description' => 'Please review the deduction',
            'status' => 'resolved', 'resolution' => 'partial_compensation',
            'resolution_notes' => 'Evidence supports a partial deduction', 'resolved_by' => $admin->id,
            'resolved_at' => '2026-10-05 12:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/rentals/'.$rental->id);

        $response->assertOk()
            ->assertSee('Booked camera')->assertSee('Receipt Owner')->assertSee('Receipt Renter')
            ->assertSee('receipt-owner@gmail.com')->assertSee('receipt-renter@gmail.com')
            ->assertSee('₱100.00')->assertSee('₱300.00')->assertSee('₱30.00')->assertSee('₱500.00')->assertSee('₱830.00')
            ->assertSee('RECEIPT-12345')->assertSee('Sep 30, 2026')->assertSee('Pickup')
            ->assertSee('Oct 01, 2026')->assertSee('Oct 03, 2026')->assertSee('Small scratch on return')
            ->assertSee('Lens cover scratch')->assertSee('Accepted the repair')
            ->assertSee('₱50.00')->assertSee('₱75.00')
            ->assertSee(asset('storage/conditions/receipt-evidence.jpg'), false)
            ->assertSee(asset('storage/damage/receipt-evidence.jpg'), false)
            ->assertSee('Please review the deduction')->assertSee('Evidence supports a partial deduction')
            ->assertSee('Partial compensation')->assertSee('Resolving Admin')
            ->assertSee(route('admin.disputes.index', ['rental' => $rental->id, 'filter' => 'all']))
            ->assertSee(route('admin.dashboard', ['search' => $rental->owner->email]))
            ->assertSee(route('admin.dashboard', ['search' => $rental->renter->email]))
            ->assertDontSee($rental->owner->getAuthPassword());
        $this->assertDatabaseHas('disputes', ['id' => $dispute->id, 'status' => 'resolved']);
        $response->assertSeeInOrder(['Payment confirmed', 'Owner confirmed pickup', 'Renter confirmed pickup', 'Renter confirmed return', 'Owner confirmed return', 'Inspection completed']);
        $this->get(route('admin.disputes.index', ['rental' => $rental->id, 'filter' => 'all']))
            ->assertOk()->assertSee('Evidence supports a partial deduction');
        $this->get(route('admin.dashboard', ['search' => $rental->owner->email]))
            ->assertOk()->assertSee('Receipt Owner')->assertDontSee('Receipt Renter');
    }

    public function test_open_disputes_link_to_the_existing_resolution_action(): void
    {
        $rental = $this->booking();
        $dispute = Dispute::create(['rental_id' => $rental->id, 'raised_by' => $rental->renter_id, 'reason' => 'incorrect_charge', 'description' => 'Review this transaction']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/rentals/'.$rental->id)
            ->assertOk()->assertSee('Review and resolve dispute #'.$dispute->id)
            ->assertSee(route('admin.disputes.index', ['rental' => $rental->id, 'filter' => 'all']));
        $this->get(route('admin.disputes.index', ['rental' => $rental->id, 'filter' => 'all']))
            ->assertOk()->assertSee('Review this transaction')->assertSee('Resolve');
    }

    public function test_admin_can_inspect_cancellation_reason_and_fee(): void
    {
        $rental = $this->booking();
        $rental->update(['status' => 'cancelled', 'cancelled_at' => '2026-09-30 12:00:00', 'cancellation_reason' => 'Plans changed before pickup', 'cancellation_fee' => 60]);

        $this->actingAs(User::factory()->admin()->create())->get('/admin/rentals/'.$rental->id)
            ->assertOk()->assertSee('Cancelled')->assertSee('Plans changed before pickup')->assertSee('₱60.00')->assertSee('Sep 30, 2026');
    }

    public function test_detail_handles_a_booking_without_optional_records(): void
    {
        $rental = $this->booking();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/rentals/'.$rental->id)
            ->assertOk()->assertSee('Payment Pending')->assertSee('No payment recorded')
            ->assertSee('No deposit recorded')->assertSee('No condition records')->assertSee('No damage claim')->assertSee('No disputes');
    }

    public function test_removed_listing_and_closed_participant_remain_readable_to_admin(): void
    {
        $rental = $this->booking();
        $rental->update(['status' => 'completed', 'completed_at' => now()]);
        $rental->listing->delete();
        $rental->renter->closeAccount();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/rentals/'.$rental->id)
            ->assertOk()->assertSee('Booked camera')->assertSee('Listing removed')->assertSee('Deleted account')->assertSee('Account closed');
    }

    public function test_non_admin_cannot_open_transaction_details(): void
    {
        $rental = $this->booking();

        $this->actingAs($rental->owner)->get('/admin/rentals/'.$rental->id)->assertForbidden();
        $this->actingAs($rental->renter)->get('/admin/rentals/'.$rental->id)->assertForbidden();
        Livewire::actingAs($rental->renter)->test(Show::class, ['rental' => $rental])->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/rentals/1')->assertRedirect(route('login'));
    }

    public function test_transaction_index_links_to_details(): void
    {
        $rental = $this->booking();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.rentals.index'))
            ->assertOk()->assertSee(route('admin.rentals.show', $rental))->assertSee('View Transaction');
    }
}
