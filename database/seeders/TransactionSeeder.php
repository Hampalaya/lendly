<?php

namespace Database\Seeders;

use App\Enums\ConditionRecordType;
use App\Enums\FulfillmentMethod;
use App\Enums\ListingCondition;
use App\Enums\RentalRequestStatus;
use App\Enums\RentalStatus;
use App\Enums\ReviewType;
use App\Enums\SecurityDepositStatus;
use App\Models\CommissionSetting;
use App\Models\ConditionRecord;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\Review;
use App\Models\SecurityDeposit;
use App\Models\User;

/**
 * Seeds a realistic mix of rental requests/bookings across every status
 * (pending, rejected, awaiting payment, paid, active, due-soon, overdue,
 * returned, completed with reviews, and cancelled with/without a fee) so
 * the admin transactions page, owner earnings, rental history, and
 * notifications all have real content to demo rather than being empty.
 */
class TransactionSeeder extends DemoSeeder
{
    protected float $commissionRate;

    protected array $renterCursor = [];

    public function run(): void
    {
        $this->ensureDemoEnvironment();
        if (RentalRequest::exists() || Rental::exists()) {
            return;
        }
        $this->commissionRate = (float) CommissionSetting::current()->commission_rate;

        $listings = Listing::with('owner')->get();
        $members = User::where('role', 'member')->get();

        if ($listings->isEmpty() || $members->count() < 2) {
            return;
        }

        $this->renterCursor = $members->pluck('id')->all();

        $listing = fn (int $i) => $listings[$i % $listings->count()];

        // Pending — awaiting owner's decision, no Rental yet.
        $this->createPendingRequest($listing(0), $this->renterFor($listing(0)));
        $this->createPendingRequest($listing(1), $this->renterFor($listing(1)));
        $this->createPendingRequest($listing(2), $this->renterFor($listing(2)));

        // Rejected by the owner.
        $this->createRejectedRequest($listing(3), $this->renterFor($listing(3)), 'Item is already booked for those dates.');
        $this->createRejectedRequest($listing(4), $this->renterFor($listing(4)), 'Not comfortable renting to a new account yet.');

        // Approved, awaiting simulated payment.
        $this->createRental($listing(5), $this->renterFor($listing(5)), RentalStatus::PaymentPending, now()->addDays(5), 3);
        $this->createRental($listing(6), $this->renterFor($listing(6)), RentalStatus::PaymentPending, now()->addDays(7), 2);
        $this->createRental($listing(7), $this->renterFor($listing(7)), RentalStatus::PaymentPending, now()->addDays(4), 4);

        // Paid, awaiting hand-over.
        $this->createRental($listing(8), $this->renterFor($listing(8)), RentalStatus::Paid, now()->addDays(3), 3);
        $this->createRental($listing(9), $this->renterFor($listing(9)), RentalStatus::Paid, now()->addDays(6), 2);
        $this->createRental($listing(10), $this->renterFor($listing(10)), RentalStatus::Paid, now()->addDays(2), 5);

        // Active — ongoing, one of them due back within 2 days ("Due Soon").
        $this->createRental($listing(11), $this->renterFor($listing(11)), RentalStatus::Active, now()->subDays(2), 3, endOverride: now()->addDay());
        $this->createRental($listing(12), $this->renterFor($listing(12)), RentalStatus::Active, now()->subDay(), 6);
        $this->createRental($listing(13), $this->renterFor($listing(13)), RentalStatus::Active, now()->subDays(3), 10);

        // Overdue — past the end date, with an accruing late fee.
        $this->createRental($listing(14), $this->renterFor($listing(14)), RentalStatus::Overdue, now()->subDays(6), 3, overdueDays: 2);
        $this->createRental($listing(15), $this->renterFor($listing(15)), RentalStatus::Overdue, now()->subDays(10), 5, overdueDays: 4);

        // Returned — waiting for the owner's after-rental inspection.
        $this->createRental($listing(16), $this->renterFor($listing(16)), RentalStatus::Returned, now()->subDays(8), 3);
        $this->createRental($listing(17), $this->renterFor($listing(17)), RentalStatus::Returned, now()->subDays(12), 4);

        // Completed — with condition records, a released deposit, and reviews both ways.
        foreach (range(18, 22) as $i) {
            $this->createCompletedRental($listing($i), $this->renterFor($listing($i)));
        }

        // Cancelled — one before payment (no fee) and one after payment, close to the start date (fee applies).
        $this->createCancelledRental($listing(23), $this->renterFor($listing(23)), paid: false);
        $this->createCancelledRental($listing(24), $this->renterFor($listing(24)), paid: true);
    }

    /**
     * Round-robins a renter that isn't the listing's own owner, so no
     * seeded transaction has someone renting their own item.
     */
    protected function renterFor(Listing $listing): User
    {
        static $pointer = 0;

        do {
            $id = $this->renterCursor[$pointer % count($this->renterCursor)];
            $pointer++;
        } while ($id === $listing->owner_id);

        return User::find($id);
    }

    protected function costBreakdown(Listing $listing, int $days): array
    {
        $rentalFee = round((float) $listing->price_per_day * $days, 2);
        $commissionAmount = round($rentalFee * ($this->commissionRate / 100), 2);
        $deposit = (float) $listing->security_deposit;

        return [
            'rental_fee' => $rentalFee,
            'commission_rate' => $this->commissionRate,
            'commission_amount' => $commissionAmount,
            'security_deposit' => $deposit,
            'total_amount' => round($rentalFee + $commissionAmount + $deposit, 2),
        ];
    }

    protected function createPendingRequest(Listing $listing, User $renter): RentalRequest
    {
        $days = 3;
        $cost = $this->costBreakdown($listing, $days);

        return RentalRequest::create([
            'listing_id' => $listing->id,
            'renter_id' => $renter->id,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(10 + $days - 1),
            'rental_days' => $days,
            'fulfillment_method' => FulfillmentMethod::Pickup,
            'status' => RentalRequestStatus::Requested,
            'renter_terms_accepted_at' => now()->subHours(2),
            ...$cost,
        ]);
    }

    protected function createRejectedRequest(Listing $listing, User $renter, string $reason): RentalRequest
    {
        $days = 2;
        $cost = $this->costBreakdown($listing, $days);

        return RentalRequest::create([
            'listing_id' => $listing->id,
            'renter_id' => $renter->id,
            'start_date' => now()->addDays(8),
            'end_date' => now()->addDays(8 + $days - 1),
            'rental_days' => $days,
            'fulfillment_method' => FulfillmentMethod::Pickup,
            'status' => RentalRequestStatus::Rejected,
            'rejection_reason' => $reason,
            'renter_terms_accepted_at' => now()->subDays(1),
            ...$cost,
        ]);
    }

    protected function createRental(
        Listing $listing,
        User $renter,
        RentalStatus $status,
        $startDate,
        int $days,
        ?int $overdueDays = null,
        $endOverride = null,
    ): Rental {
        $cost = $this->costBreakdown($listing, $days);
        $endDate = $endOverride ?? (clone $startDate)->addDays($days - 1);

        $request = RentalRequest::create([
            'listing_id' => $listing->id,
            'renter_id' => $renter->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rental_days' => $days,
            'fulfillment_method' => FulfillmentMethod::Pickup,
            'status' => RentalRequestStatus::Approved,
            'renter_terms_accepted_at' => (clone $startDate)->subDays(2),
            'owner_terms_accepted_at' => (clone $startDate)->subDay(),
            ...$cost,
        ]);

        $rental = Rental::create([
            'rental_request_id' => $request->id,
            'listing_id' => $listing->id,
            'owner_id' => $listing->owner_id,
            'renter_id' => $renter->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rental_days' => $days,
            'fulfillment_method' => FulfillmentMethod::Pickup,
            'status' => $status,
            'paid_at' => $status !== RentalStatus::PaymentPending ? (clone $startDate)->subDay() : null,
            ...$cost,
        ]);

        if ($status !== RentalStatus::PaymentPending) {
            $this->attachPayment($rental);
            SecurityDeposit::create(['rental_id' => $rental->id, 'amount' => $cost['security_deposit']]);
        }

        if (in_array($status, [RentalStatus::Active, RentalStatus::Overdue, RentalStatus::Returned], true)) {
            $rental->update([
                'pickup_confirmed_by_owner_at' => (clone $startDate)->subHours(2),
                'pickup_confirmed_by_renter_at' => (clone $startDate)->subHour(),
            ]);

            $this->attachConditionRecord($rental, ConditionRecordType::Before, $listing->owner_id);
        }

        if ($overdueDays) {
            $rental->update([
                'days_overdue' => $overdueDays,
                'late_fee' => round($rental->agreedDailyRate() * $overdueDays, 2),
            ]);
        }

        if ($status === RentalStatus::Returned) {
            $rental->update([
                'return_confirmed_by_owner_at' => now()->subHours(3),
                'return_confirmed_by_renter_at' => now()->subHours(4),
            ]);
        }

        return $rental;
    }

    protected function createCompletedRental(Listing $listing, User $renter): Rental
    {
        $days = 3;
        $startDate = now()->subDays(25);

        $rental = $this->createRental($listing, $renter, RentalStatus::Returned, $startDate, $days);

        $rental->update([
            'status' => RentalStatus::Completed,
            'completed_at' => $startDate->copy()->addDays($days + 1),
        ]);

        $this->attachConditionRecord($rental, ConditionRecordType::After, $listing->owner_id);

        $rental->securityDeposit?->update(['status' => SecurityDepositStatus::Released]);

        Review::create([
            'rental_id' => $rental->id,
            'type' => ReviewType::RenterToOwner,
            'rating' => fake()->numberBetween(4, 5),
            'comment' => fake()->randomElement([
                'Great communication, item was exactly as described.',
                'Smooth transaction, would rent from again.',
                'Very accommodating with pickup times.',
            ]),
        ]);

        Review::create([
            'rental_id' => $rental->id,
            'type' => ReviewType::RenterToListing,
            'rating' => fake()->numberBetween(4, 5),
            'comment' => fake()->randomElement([
                'Item was in excellent condition, worked perfectly.',
                'Exactly what I needed for the weekend.',
                'Clean and well-maintained, no issues at all.',
            ]),
        ]);

        Review::create([
            'rental_id' => $rental->id,
            'type' => ReviewType::OwnerToRenter,
            'rating' => fake()->numberBetween(4, 5),
            'comment' => fake()->randomElement([
                'Returned the item on time and in great shape.',
                'Easy to coordinate with, no problems.',
                'Would rent to again anytime.',
            ]),
        ]);

        return $rental;
    }

    protected function createCancelledRental(Listing $listing, User $renter, bool $paid): Rental
    {
        $days = 3;
        $startDate = $paid ? now()->addHours(20) : now()->addDays(10);

        $rental = $this->createRental($listing, $renter, $paid ? RentalStatus::Paid : RentalStatus::PaymentPending, $startDate, $days);

        $fee = $paid ? round((float) $rental->rental_fee * 0.20, 2) : 0.0;

        $rental->update([
            'status' => RentalStatus::Cancelled,
            'cancelled_at' => now()->subHours(1),
            'cancellation_reason' => $paid ? 'Change of plans, no longer needed.' : 'Found a cheaper option nearby.',
            'cancellation_fee' => $fee,
        ]);

        $rental->rentalRequest?->update(['status' => RentalRequestStatus::Cancelled]);
        $rental->securityDeposit?->update(['status' => SecurityDepositStatus::Refunded]);

        return $rental;
    }

    protected function attachPayment(Rental $rental): void
    {
        Payment::create([
            'rental_id' => $rental->id,
            'transaction_reference' => Payment::generateReference(),
            'amount' => $rental->total_amount,
            'paid_at' => $rental->paid_at ?? now(),
        ]);
    }

    protected function attachConditionRecord(Rental $rental, ConditionRecordType $type, int $ownerId): void
    {
        ConditionRecord::create([
            'rental_id' => $rental->id,
            'recorded_by' => $ownerId,
            'type' => $type,
            'condition' => ListingCondition::Good,
            'notes' => $type === ConditionRecordType::Before ? 'Item handed over in good condition.' : 'Returned in the same condition as handed over.',
            'has_damage' => false,
        ]);
    }
}
