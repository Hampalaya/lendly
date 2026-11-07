<?php

namespace Tests\Feature;

use App\Livewire\Admin\Listings\Index as AdminListings;
use App\Livewire\Owner\Listings\Form;
use App\Livewire\Owner\Listings\Index as OwnerListings;
use App\Livewire\Owner\Rentals\Show;
use App\Livewire\RentalRequests\Create;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\User;
use App\Services\RentalPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QaFindingsRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function listing(array $attributes = []): Listing
    {
        return Listing::create($attributes + [
            'owner_id' => User::factory()->identityVerified()->owner()->create()->id,
            'category_id' => Category::firstOrCreate(['slug' => 'qa-regression'], ['name' => 'QA equipment'])->id,
            'name' => 'QA camera', 'description' => 'Camera with case', 'condition' => 'good',
            'location' => 'Manila', 'latitude' => 14.6, 'longitude' => 120.98,
            'price_per_day' => 100, 'price_per_hour' => 50, 'security_deposit' => 500,
            'max_rental_duration_days' => 1, 'status' => 'published', 'is_available' => true,
            'pickup_available' => true, 'available_from' => '2026-11-01', 'available_until' => '2026-12-31',
            'available_time_from' => '00:00', 'available_time_until' => '23:59',
        ]);
    }

    public static function requestDefaults(): array
    {
        return [
            'before late night' => ['21:30', '00:00', '23:59', true, '2026-11-05', '22:00', '23:00'],
            'ten pm' => ['22:00', '00:00', '23:59', true, '2026-11-06', '00:00', '01:00'],
            'ten thirty hourly' => ['22:30', '00:00', '23:59', false, '2026-11-06', '00:00', '01:00'],
            'ten fifty nine' => ['22:59', '00:00', '23:59', true, '2026-11-06', '00:00', '01:00'],
            'after eleven' => ['23:30', '00:00', '23:59', false, '2026-11-06', '00:00', '01:00'],
            'short remaining window' => ['16:30', '08:00', '17:30', false, '2026-11-06', '08:00', '09:00'],
            'before opening' => ['06:30', '08:00', '18:00', true, '2026-11-05', '08:00', '09:00'],
        ];
    }

    #[DataProvider('requestDefaults')]
    public function test_default_request_period_is_valid_in_philippine_time(string $now, string $opening, string $closing, bool $daily, string $date, string $start, string $end): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 '.$now, 'Asia/Manila'));
        $listing = $this->listing(['available_time_from' => $opening, 'available_time_until' => $closing, 'allows_daily_rentals' => $daily]);
        $page = Livewire::actingAs(User::factory()->identityVerified()->renter()->create())
            ->test(Create::class, ['listing' => $listing])
            ->assertSet('start_date', $date)->assertSet('end_date', $date)
            ->assertSet('start_time', $start)->assertSet('end_time', $end);
        $period = RentalPeriod::attributes($listing, [
            'start_date' => $page->get('start_date'), 'end_date' => $page->get('end_date'),
            'start_time' => $page->get('start_time'), 'end_time' => $page->get('end_time'),
            'rental_unit' => $page->get('rental_unit'),
        ]);
        $this->assertTrue(Carbon::parse($period['ends_at'])->gt(Carbon::parse($period['starts_at'])));
        $this->assertSame(1, $period['rental_days']);
    }

    public function test_last_available_day_uses_remaining_daily_window_without_advancing_outside_dates(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 22:30', 'Asia/Manila'));
        $listing = $this->listing(['available_until' => '2026-11-05']);
        Livewire::actingAs(User::factory()->identityVerified()->renter()->create())
            ->test(Create::class, ['listing' => $listing])->assertSet('start_date', '2026-11-05')
            ->assertSet('end_date', '2026-11-05')->assertSet('start_time', '23:00')->assertSet('end_time', '23:59');
    }

    public function test_all_receipts_convert_utc_to_philippine_time_including_the_date_rollover(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:00', 'Asia/Manila'));
        $listing = $this->listing();
        $renter = User::factory()->renter()->create();
        $details = ['listing_id' => $listing->id, 'renter_id' => $renter->id,
            'start_date' => '2026-11-07', 'end_date' => '2026-11-07', 'rental_days' => 1,
            'fulfillment_method' => 'pickup', 'rental_fee' => 100, 'commission_rate' => 10,
            'commission_amount' => 10, 'security_deposit' => 500, 'total_amount' => 610];
        $request = RentalRequest::create($details + ['status' => 'approved']);
        $rental = Rental::create($details + ['rental_request_id' => $request->id, 'owner_id' => $listing->owner_id,
            'status' => 'paid', 'paid_at' => '2026-11-03 19:25:00']);
        Payment::create(['rental_id' => $rental->id, 'transaction_reference' => 'QA-TIMEZONE', 'amount' => 610, 'paid_at' => '2026-11-03 19:25:00']);
        Livewire::actingAs($listing->owner)->test(Show::class, ['rental' => $rental])
            ->assertSee('Nov 04, 2026 3:25 AM')->assertSee('(Philippine time)');
        Livewire::actingAs($renter)->test(\App\Livewire\Renter\Rentals\Show::class, ['rental' => $rental])
            ->assertSee('Nov 04, 2026 at 3:25 AM')->assertSee('(Philippine time)');
        Livewire::actingAs(User::factory()->admin()->create())->test(\App\Livewire\Admin\Rentals\Show::class, ['rental' => $rental])
            ->assertSee('Nov 04, 2026 3:25 AM')->assertSee('(Philippine time)');
        $this->assertSame('2026-11-03 19:25:00', $rental->fresh()->paid_at->format('Y-m-d H:i:s'));
    }

    public function test_missing_pin_message_appears_once_in_the_summary_and_once_at_the_map(): void
    {
        $page = Livewire::actingAs(User::factory()->identityVerified()->owner()->create())->test(Form::class)
            ->call('save')->assertHasErrors(['latitude', 'longitude']);
        $this->assertSame(2, substr_count($page->html(), 'Place a pin on the map for your item.'));
    }

    public function test_admin_removal_requires_reason_and_shares_it_with_the_owner(): void
    {
        $listing = $this->listing();
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(AdminListings::class)
            ->call('remove', $listing->id)->assertSee('Reason for removal');
        $this->assertSame('published', $listing->fresh()->status->value);
        $page->set('removal_reason', '   ')->call('confirmRemoval')->assertHasErrors('removal_reason');
        $this->assertSame('published', $listing->fresh()->status->value);
        $page->set('removal_reason', '  Please replace the misleading photos.  ')->call('confirmRemoval')->assertHasNoErrors();
        $this->assertSame('inactive', $listing->fresh()->status->value);
        $this->assertSame('Please replace the misleading photos.', $listing->fresh()->rejection_reason);
        $this->assertStringContainsString('Please replace the misleading photos.', $listing->owner->notifications()->first()->data['message']);
        Livewire::actingAs($listing->owner)->test(OwnerListings::class)->assertSee('Please replace the misleading photos.');
        Livewire::test(Form::class, ['listing' => $listing->fresh()])->assertSee('Please replace the misleading photos.');
    }

    public function test_cancelling_removal_and_stale_confirmations_do_not_change_a_listing(): void
    {
        $listing = $this->listing();
        $admin = User::factory()->admin()->create();
        $page = Livewire::actingAs($admin)->test(AdminListings::class)->call('remove', $listing->id)
            ->set('removal_reason', 'Do not save this')->call('cancelRemoving')->assertSet('removing', null);
        $this->assertSame('published', $listing->fresh()->status->value);
        $page->call('remove', $listing->id)->set('removal_reason', 'Stale decision');
        $listing->update(['status' => 'inactive']);
        $page->call('confirmRemoval')->assertStatus(409);
        $this->assertNull($listing->fresh()->rejection_reason);
        $this->assertSame(0, $listing->owner->notifications()->count());
        Livewire::actingAs($listing->owner)->test(AdminListings::class)->assertForbidden();
    }
}
