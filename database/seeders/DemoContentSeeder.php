<?php

namespace Database\Seeders;

use App\Enums\DamageReportStatus;
use App\Enums\DisputeReason;
use App\Enums\DisputeStatus;
use App\Enums\RentalStatus;
use App\Models\DamageReport;
use App\Models\Dispute;
use App\Models\Message;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\User;
use App\Notifications\TalaNotification;
use Illuminate\Support\Facades\DB;

/**
 * Fills in the demo-facing content that TransactionSeeder doesn't touch —
 * damage reports, disputes, message threads, and notifications — so the
 * Admin Disputes/Damage Reports pages, the Messages module, and every
 * account's notification bell have real rows to show instead of empty
 * states when the app is demoed.
 */
class DemoContentSeeder extends DemoSeeder
{
    public function run(): void
    {
        $this->ensureDemoEnvironment();
        if (DamageReport::exists() || Dispute::exists() || Message::exists() || DB::table('notifications')->exists()) {
            return;
        }
        $this->seedDamageReportsAndDisputes();
        $this->seedMessages();
        $this->seedNotifications();
    }

    private function seedDamageReportsAndDisputes(): void
    {
        $completedRentals = Rental::query()
            ->where('status', RentalStatus::Completed)
            ->with('afterConditionRecord')
            ->inRandomOrder()
            ->limit(3)
            ->get();

        if ($completedRentals->count() < 2) {
            return;
        }

        // A routine damage report the renter accepted — no dispute.
        $rentalA = $completedRentals[0];
        if ($recordA = $rentalA->afterConditionRecord) {
            $recordA->update(['has_damage' => true, 'notes' => 'Small scuff noticed on return, otherwise functional.']);

            DamageReport::create([
                'rental_id' => $rentalA->id,
                'condition_record_id' => $recordA->id,
                'damage_type' => 'Cosmetic scratch',
                'description' => 'A visible scratch on the casing was noted during the return inspection. Item still functions normally.',
                'estimated_repair_cost' => 800,
                'proposed_deduction' => 500,
                'status' => DamageReportStatus::Accepted,
                'renter_response_notes' => 'I agree, that happened during transport. Fine with the deduction.',
            ]);
        }

        // A disputed damage report — becomes an open Dispute for admin to resolve.
        $rentalB = $completedRentals[1];
        if ($recordB = $rentalB->afterConditionRecord) {
            $recordB->update(['has_damage' => true, 'notes' => 'Item returned with a cracked component.']);

            $damageReport = DamageReport::create([
                'rental_id' => $rentalB->id,
                'condition_record_id' => $recordB->id,
                'damage_type' => 'Cracked housing',
                'description' => 'The item was returned with a visible crack that was not present at handoff. Repair estimate attached.',
                'estimated_repair_cost' => 2500,
                'proposed_deduction' => 2000,
                'status' => DamageReportStatus::Disputed,
                'renter_response_notes' => 'This damage was already there when I picked it up — I have photos from before the rental.',
            ]);

            Dispute::create([
                'rental_id' => $rentalB->id,
                'damage_report_id' => $damageReport->id,
                'raised_by' => $rentalB->renter_id,
                'reason' => DisputeReason::ItemDamaged,
                'description' => 'Renter disputes the damage deduction, claiming the item was already damaged before pickup.',
                'status' => DisputeStatus::Open,
            ]);
        }

        // A general dispute unrelated to damage.
        if ($rentalC = $completedRentals->get(2)) {
            Dispute::create([
                'rental_id' => $rentalC->id,
                'raised_by' => $rentalC->renter_id,
                'reason' => DisputeReason::IncorrectCharge,
                'description' => 'Renter believes the late fee applied does not match the actual return time.',
                'status' => DisputeStatus::Open,
            ]);
        }
    }

    private function seedMessages(): void
    {
        $owner = User::where('email', 'owner@tala.test')->first();
        $renter = User::where('email', 'renter@tala.test')->first();

        $requestIds = collect();

        if ($owner && $renter) {
            $shared = RentalRequest::where('renter_id', $renter->id)
                ->whereHas('listing', fn ($q) => $q->where('owner_id', $owner->id))
                ->value('id');

            if ($shared) {
                $requestIds->push($shared);
            }
        }

        $requestIds = $requestIds
            ->merge(
                RentalRequest::whereNotIn('id', $requestIds)
                    ->inRandomOrder()
                    ->limit(4)
                    ->pluck('id')
            )
            ->unique()
            ->take(5)
            ->values();

        $conversation = [
            ['from' => 'renter', 'text' => 'Hi! Is this still available for the dates I requested?'],
            ['from' => 'owner', 'text' => "Yes, it's available! I can have it ready for pickup."],
            ['from' => 'renter', 'text' => 'Great, what time works best for you?'],
            ['from' => 'owner', 'text' => 'Anytime after 2pm works on my end.'],
            ['from' => 'renter', 'text' => 'Perfect, see you then. Thank you!'],
            ['from' => 'owner', 'text' => 'Sounds good, looking forward to it.'],
        ];

        foreach ($requestIds as $index => $requestId) {
            $request = RentalRequest::with('listing.owner', 'renter')->find($requestId);

            if (! $request || ! $request->listing || ! $request->listing->owner || ! $request->renter) {
                continue;
            }

            $renterUser = $request->renter;
            $ownerUser = $request->listing->owner;

            $lineCount = fake()->numberBetween(3, count($conversation));
            $baseTime = now()->subDays(5 - min($index, 4))->subHours(fake()->numberBetween(1, 6));

            foreach (array_slice($conversation, 0, $lineCount) as $i => $line) {
                $sender = $line['from'] === 'renter' ? $renterUser : $ownerUser;
                $receiver = $line['from'] === 'renter' ? $ownerUser : $renterUser;
                $sentAt = $baseTime->copy()->addMinutes($i * fake()->numberBetween(5, 40));
                $isLast = $i === $lineCount - 1;

                $message = Message::create([
                    'rental_request_id' => $request->id,
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'body' => $line['text'],
                    'read_at' => $isLast ? null : $sentAt->copy()->addMinutes(2),
                ]);

                $message->forceFill(['created_at' => $sentAt, 'updated_at' => $sentAt])->save();
            }
        }
    }

    private function seedNotifications(): void
    {
        $admin = User::where('email', 'admin@tala.test')->first();
        $owner = User::where('email', 'owner@tala.test')->first();
        $renter = User::where('email', 'renter@tala.test')->first();

        if ($owner) {
            $owner->notify(new TalaNotification('rental_request', 'New rental request', 'Demo Renter requested to rent one of your listings.', route('owner.rental-requests.index')));
            $owner->notify(new TalaNotification('payment_received', 'Payment received', 'A renter completed payment for an approved rental.', route('owner.rentals.index')));
            $owner->notify(new TalaNotification('damage_report', 'Damage report needs your review', 'A renter responded to a damage report you filed.', route('owner.rentals.index')));
        }

        if ($renter) {
            $renter->notify(new TalaNotification('request_approved', 'Request approved', 'Your rental request was approved. Complete payment to confirm.', route('renter.rentals.index')));
            $renter->notify(new TalaNotification('rental_reminder', 'Rental starting soon', 'One of your rentals starts within the next 2 days.', route('renter.rentals.index')));
        }

        if ($admin) {
            $admin->notify(new TalaNotification('dispute_opened', 'New dispute opened', 'A renter opened a dispute that needs admin review.', route('admin.disputes.index')));
            $admin->notify(new TalaNotification('listing_pending', 'Listing pending approval', 'A new listing was submitted and is awaiting moderation.', route('admin.listings.index')));
        }
    }
}
