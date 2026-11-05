<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\RentalRequestStatus;
use App\Enums\RentalStatus;
use App\Models\Listing;
use App\Models\OfflinePaymentSetting;
use App\Models\OwnerPaymentSetting;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\Rental;
use App\Models\SecurityDeposit;
use App\Models\User;
use App\Notifications\TalaNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RentalPayments
{
    public static function availableWalletsFor(int $ownerId, bool $lock = false): Collection
    {
        $query = OwnerPaymentSetting::where('user_id', $ownerId)->where('enabled', true)
            ->whereIn('provider', array_keys(OwnerPaymentSetting::PROVIDERS))->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->filter(fn ($wallet) => trim($wallet->account_name) !== '' && self::storedFile($wallet->qr_path));
    }

    public static function selectWallet(Rental $rental, User $renter, int $walletId): void
    {
        self::expireReservation($rental);

        DB::transaction(function () use ($rental, $renter, $walletId) {
            $rental = Rental::lockForUpdate()->findOrFail($rental->id);
            $renter = User::withTrashed()->findOrFail($renter->id);
            Gate::forUser($renter)->authorize('pay', $rental);
            abort_unless(self::activeMember($renter) && $renter->id === $rental->renter_id, 403);
            if ($rental->paymentSubmissions()->exists()) {
                throw ValidationException::withMessages(['payment_wallet' => 'The wallet cannot be changed after payment proof has been submitted.']);
            }

            $owner = User::withTrashed()->find($rental->owner_id);
            $wallet = self::availableWalletsFor($rental->owner_id, lock: true)->firstWhere('id', $walletId);
            if (! self::activeMember($owner) || ! $wallet) {
                throw ValidationException::withMessages(['payment_wallet' => 'This e-wallet is no longer available. Choose another saved wallet.']);
            }

            $rental->forceFill(['payment_destination' => self::walletDestination($wallet)])->save();
        });
    }

    private static function walletDestination(OwnerPaymentSetting $wallet): array
    {
        return [
            'source' => 'owner_wallet', 'method' => $wallet->provider, 'payee_user_id' => $wallet->user_id,
            'account_name' => $wallet->account_name, 'instructions' => $wallet->instructions ?? '',
            'qr_path' => $wallet->qr_path,
        ];
    }

    public static function expireReservations(): int
    {
        $count = 0;
        Rental::where('status', RentalStatus::PaymentPending)->whereNotNull('payment_due_at')->where('payment_due_at', '<=', now())
            ->eachById(function (Rental $rental) use (&$count) {
                $count += self::expireReservation($rental) ? 1 : 0;
            });

        return $count;
    }

    public static function expireReservation(Rental $rental): bool
    {
        return DB::transaction(function () use ($rental) {
            Listing::withTrashed()->whereKey($rental->listing_id)->lockForUpdate()->firstOrFail();
            $rental = Rental::lockForUpdate()->findOrFail($rental->id);
            if (! $rental->isPaymentPending() || ! $rental->payment_due_at || $rental->payment_due_at->isFuture()
                || $rental->paid_at || $rental->payment()->exists() || $rental->paymentSubmissions()->where('status', 'pending')->exists()) {
                return false;
            }
            $data = ['cancelled_at' => now(), 'cancellation_reason' => 'The payment reservation expired.', 'cancellation_fee' => 0];
            $rental->update($data + ['status' => RentalStatus::Cancelled, 'cancellation_refund_amount' => 0]);
            $rental->rentalRequest?->update($data + ['status' => RentalRequestStatus::Cancelled]);
            $rental->cancellationRequest()->where('status', 'pending')->update(['status' => 'rejected', 'review_notes' => 'The unpaid reservation expired without a fee.', 'reviewed_at' => now()]);
            foreach ([$rental->owner, $rental->renter] as $party) {
                if (! $party->trashed()) {
                    $party->notify(new TalaNotification(NotificationType::RentalCancelled->value, 'Payment reservation expired', 'Booking #'.$rental->id.' expired without a fee because payment was not submitted by the deadline.',
                        route(($party->id === $rental->owner_id ? 'owner' : 'renter').'.rentals.show', $rental)));
                }
            }

            return true;
        }, 3);
    }

    public static function destinationFor(Rental $rental, User $renter): ?array
    {
        self::expireReservation($rental);

        return DB::transaction(function () use ($rental, $renter) {
            $rental = Rental::lockForUpdate()->findOrFail($rental->id);
            $renter = User::withTrashed()->find($renter->id);
            if (! self::activeMember($renter) || $renter->id !== $rental->renter_id) {
                return null;
            }
            if ($rental->payment_destination !== null) {
                return $rental->payment_destination['source'] === 'platform' && ! OfflinePaymentSetting::current()?->enabled
                    ? null : $rental->payment_destination;
            }
            if (! Gate::forUser($renter)->allows('pay', $rental)) {
                return null;
            }

            // Settings replacement locks this same row before checking whether an old QR is referenced.
            $settings = self::availableWalletsFor($rental->owner_id, lock: true)->first();
            $owner = User::withTrashed()->find($rental->owner_id);
            if (self::activeMember($owner) && $settings) {
                $destination = self::walletDestination($settings);
            } else {
                if (($rental->rentalRequest?->agreement_terms['payment_review'] ?? null) === 'owner') {
                    return null;
                }
                $platform = OfflinePaymentSetting::current();
                if (! $platform?->enabled || trim($platform->instructions ?? '') === '') {
                    return null;
                }
                $destination = ['source' => 'platform', 'method' => $platform->method, 'instructions' => $platform->instructions];
            }
            $rental->forceFill(['payment_destination' => $destination])->save();

            return $destination;
        });
    }

    public static function submit(Rental $rental, User $renter, string $reference, UploadedFile $proof): PaymentSubmission
    {
        self::expireReservation($rental);
        $path = null;
        try {
            return DB::transaction(function () use ($rental, $renter, $reference, $proof, &$path) {
                $rental = Rental::lockForUpdate()->findOrFail($rental->id);
                Gate::forUser($renter)->authorize('pay', $rental);
                $destination = self::destinationFor($rental, $renter);
                $rental->refresh();
                Gate::forUser($renter)->authorize('pay', $rental);
                $platform = OfflinePaymentSetting::current();
                if (! $destination || ($destination['source'] === 'platform' && ! $platform?->enabled)
                    || ($destination['source'] === 'owner_wallet' && ! self::storedFile($destination['qr_path'] ?? null))) {
                    throw ValidationException::withMessages(['payment_proof' => 'Payment instructions are not available yet. Please contact support.']);
                }
                $reference = strtoupper(trim($reference));
                validator(['payment_reference' => $reference, 'payment_proof' => $proof], [
                    'payment_reference' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9][A-Z0-9._\/-]*$/'],
                    'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                ])->validate();
                if (Payment::where('external_reference', $reference)->exists()
                    || PaymentSubmission::where('external_reference', $reference)->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages(['payment_reference' => 'This reference is already recorded or awaiting verification.']);
                }
                $path = $proof->store('payment-proofs', 'local');
                if (! $path || ! Storage::disk('local')->exists($path) || Storage::disk('local')->size($path) === 0) {
                    throw ValidationException::withMessages(['payment_proof' => 'Unable to store payment proof. Please try again.']);
                }
                $submission = $rental->paymentSubmissions()->make([
                    'submitted_by' => $renter->id, 'method' => $destination['method'],
                    'instructions' => $destination['instructions'], 'external_reference' => $reference,
                    'amount' => $rental->total_amount, 'proof_path' => $path, 'status' => 'pending',
                ]);
                $submission->forceFill(['payment_destination' => $destination])->save();
                foreach (($destination['source'] === 'platform' ? User::where('role', 'admin')->whereNotNull('email_verified_at')->get() : collect()) as $admin) {
                    if (Gate::forUser($admin)->allows('viewAny', Rental::class)) {
                        $admin->notify(new TalaNotification(NotificationType::PaymentSubmitted->value, 'Payment proof awaiting verification',
                            'Review payment proof for booking #'.$rental->id.'.', route('admin.rentals.show', $rental)));
                    }
                }
                if ($destination['source'] === 'owner_wallet') {
                    $recipient = User::find($destination['payee_user_id']);
                    if (self::activeMember($recipient)) {
                        $recipient->notify(new TalaNotification(NotificationType::PaymentSubmitted->value, 'Payment proof awaiting receipt confirmation',
                            'Check your wallet for the full payment for booking #'.$rental->id.'.', route('owner.rentals.show', $rental)));
                    }
                }

                return $submission;
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public static function confirmOwnerReceipt(Rental $rental, User $owner, int $submissionId, bool $fundsReceived): void
    {
        DB::transaction(function () use ($rental, $owner, $submissionId, $fundsReceived) {
            $owner = User::withTrashed()->whereKey($owner->id)->lockForUpdate()->first();
            $rental = Rental::lockForUpdate()->findOrFail($rental->id);
            $submission = $rental->paymentSubmissions()->lockForUpdate()->find($submissionId);
            abort_unless($submission, 404);
            $destination = $submission->payment_destination;
            abort_unless(self::activeMember($owner) && ($destination['source'] ?? null) === 'owner_wallet'
                && ($destination['payee_user_id'] ?? null) === $owner->id && $rental->owner_id === $owner->id, 403);
            if ($submission->status === 'verified' && $submission->recipient_confirmed_at && $rental->payment()->where('payment_submission_id', $submission->id)->exists()) {
                return;
            }
            abort_unless($submission->status === 'pending' && $rental->isPaymentPending()
                && $rental->paid_at === null && $rental->hasAcceptedAgreement()
                && ! $rental->payment()->exists() && ! $rental->securityDeposit()->exists(), 403);
            validator(['funds_received' => $fundsReceived], ['funds_received' => ['accepted']])->validate();
            if ($submission->amount !== $rental->total_amount || ! self::storedFile($submission->proof_path)
                || Payment::where('external_reference', $submission->external_reference)->exists()) {
                throw ValidationException::withMessages(['funds_received' => 'The full booking amount and stored payment proof are required before acknowledging receipt.']);
            }
            $paidAt = now();
            Payment::create(['rental_id' => $rental->id, 'transaction_reference' => Payment::generateReference(),
                'amount' => $rental->total_amount, 'status' => 'paid', 'paid_at' => $paidAt,
                'method' => $submission->method, 'external_reference' => $submission->external_reference,
                'payment_submission_id' => $submission->id, 'verified_by' => $owner->id]);
            SecurityDeposit::create(['rental_id' => $rental->id, 'amount' => $rental->security_deposit]);
            $submission->forceFill(['recipient_confirmed_at' => $paidAt, 'status' => 'verified',
                'reviewed_by' => $owner->id, 'reviewed_at' => $paidAt, 'review_notes' => 'Owner confirmed receipt of the full booking amount.'])->save();
            $rental->update(['status' => RentalStatus::Paid, 'paid_at' => $paidAt]);
            foreach ([$rental->renter, $rental->owner] as $party) {
                if (self::activeMember($party)) {
                    $party->notify(new TalaNotification(NotificationType::PaymentConfirmed->value, 'Payment confirmed',
                        'The owner confirmed the full payment for booking #'.$rental->id.'. The booking is paid.',
                        route(($party->id === $rental->owner_id ? 'owner' : 'renter').'.rentals.show', $rental)));
                }
            }
        });
    }

    public static function rejectOwnerProof(Rental $rental, User $owner, int $submissionId, string $reason): void
    {
        DB::transaction(function () use ($rental, $owner, $submissionId, $reason) {
            $owner = User::withTrashed()->find($owner->id);
            $rental = Rental::lockForUpdate()->findOrFail($rental->id);
            $submission = $rental->paymentSubmissions()->lockForUpdate()->findOrFail($submissionId);
            abort_unless(self::activeMember($owner) && $owner->id === $rental->owner_id
                && ($submission->payment_destination['source'] ?? null) === 'owner_wallet'
                && ($submission->payment_destination['payee_user_id'] ?? null) === $owner->id
                && $submission->status === 'pending' && $submission->recipient_confirmed_at === null
                && $rental->isPaymentPending() && ! $rental->payment()->exists(), 403);
            validator(['payment_rejection_reason' => trim($reason)], ['payment_rejection_reason' => ['required', 'string', 'max:1000']])->validate();
            $submission->update(['status' => 'rejected', 'reviewed_by' => $owner->id, 'reviewed_at' => now(), 'review_notes' => trim($reason)]);
            $rental->renter->notify(new TalaNotification(NotificationType::PaymentRejected->value, 'Payment proof needs correction',
                trim($reason), route('renter.rentals.show', $rental)));
        });
    }

    /** Called inside the audited administrator transaction. */
    public static function review(Rental $rental, User $admin, ?int $submissionId, bool $approve, string $notes, bool $fundsReceived): void
    {
        DB::transaction(function () use ($rental, $admin, $submissionId, $approve, $notes, $fundsReceived) {
            Listing::withTrashed()->whereKey($rental->listing_id)->lockForUpdate()->firstOrFail();
            $rental = Rental::lockForUpdate()->findOrFail($rental->id);
            Gate::forUser($admin)->authorize($approve ? 'adminVerifyPayment' : 'adminRejectPayment', $rental);
            validator(['notes' => trim($notes)], ['notes' => ['required', 'max:1000']])->validate();
            $submission = $rental->paymentSubmissions()->lockForUpdate()->find($submissionId);
            abort_unless($submission, 404);
            abort_unless($submission->status === 'pending', 403, 'This submission has already been reviewed.');
            if ($approve) {
                validator(['funds_received' => $fundsReceived], ['funds_received' => ['accepted']])->validate();
                if (($submission->payment_destination['source'] ?? null) === 'owner_wallet' && $submission->recipient_confirmed_at === null) {
                    throw ValidationException::withMessages(['reason' => 'The wallet recipient must confirm receipt of the full amount before payment can be verified.']);
                }
                if (! Storage::disk('local')->exists($submission->proof_path)) {
                    throw ValidationException::withMessages(['reason' => 'The uploaded proof is missing. Request new proof before verifying payment.']);
                }
                if ($submission->amount !== $rental->total_amount || Payment::where('external_reference', $submission->external_reference)->exists()) {
                    throw ValidationException::withMessages(['reason' => 'The amount or payment reference does not match an eligible unpaid booking.']);
                }
                $paidAt = now();
                Payment::create([
                    'rental_id' => $rental->id, 'transaction_reference' => Payment::generateReference(),
                    'amount' => $rental->total_amount, 'status' => 'paid', 'paid_at' => $paidAt,
                    'method' => $submission->method, 'external_reference' => $submission->external_reference,
                    'payment_submission_id' => $submission->id, 'verified_by' => $admin->id,
                ]);
                SecurityDeposit::create(['rental_id' => $rental->id, 'amount' => $rental->security_deposit]);
                $rental->update(['status' => RentalStatus::Paid, 'paid_at' => $paidAt]);
            }
            $submission->update([
                'status' => $approve ? 'verified' : 'rejected', 'reviewed_by' => $admin->id,
                'review_notes' => trim($notes), 'reviewed_at' => now(),
            ]);
        });
    }

    private static function activeMember(?User $user): bool
    {
        return $user && ! $user->isAdmin() && ! $user->isSuspended() && ! $user->trashed() && $user->hasVerifiedEmail();
    }

    private static function storedFile(?string $path): bool
    {
        return $path && Storage::disk('local')->exists($path) && Storage::disk('local')->size($path) > 0;
    }
}
