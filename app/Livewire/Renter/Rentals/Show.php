<?php

namespace App\Livewire\Renter\Rentals;

use App\Enums\DisputeReason;
use App\Enums\NotificationType;
use App\Enums\ReviewType;
use App\Models\Dispute;
use App\Models\OfflinePaymentSetting;
use App\Models\Rental;
use App\Notifications\TalaNotification;
use App\Services\CancellationPolicy;
use App\Services\RentalLifecycle;
use App\Services\RentalPayments;
use App\Services\RentalReviews;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    public Rental $rental;

    public string $payment_reference = '';

    public $payment_proof;

    public string $damage_response_notes = '';

    public int $owner_rating = 5;

    public string $owner_comment = '';

    public int $listing_rating = 5;

    public string $listing_comment = '';

    public string $dispute_reason = '';

    public string $dispute_description = '';

    public bool $showDisputeForm = false;

    public bool $showCancelForm = false;

    public string $cancellation_reason = '';

    public function mount(Rental $rental): void
    {
        $this->authorize('view', $rental);

        $this->rental = $rental->load([
            'listing', 'owner', 'renter', 'payment', 'securityDeposit',
            'beforeConditionRecord.photos', 'afterConditionRecord.photos', 'damageReport.photos',
            'reviewFromRenterToOwner', 'reviewFromRenterToListing', 'disputes',
        ]);
    }

    public function submitPaymentProof(): void
    {
        $this->rental->refresh();
        $this->authorize('pay', $this->rental);
        $this->payment_reference = strtoupper(trim($this->payment_reference));
        $this->validate([
            'payment_reference' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9][A-Z0-9._\/-]*$/'],
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        RentalPayments::submit($this->rental, auth()->user(), $this->payment_reference, $this->payment_proof);
        $this->reset('payment_reference', 'payment_proof');
        session()->flash('status', 'Payment proof submitted. Your booking remains unpaid until receipt of the full amount is verified.');
    }

    public function selectWallet(int $walletId): void
    {
        $this->rental->refresh();
        $this->authorize('pay', $this->rental);
        RentalPayments::selectWallet($this->rental, auth()->user(), $walletId);
        $this->resetValidation('payment_wallet');
        $this->rental->refresh();
    }

    public function cancellationPreview(): ?array
    {
        return $this->rental->isCancellableByRenter()
            ? CancellationPolicy::evaluate($this->rental)
            : null;
    }

    public function cancelRental(): void
    {
        $this->authorize('cancel', $this->rental);

        $this->validate(['cancellation_reason' => ['nullable', 'string', 'max:500']]);

        $result = RentalLifecycle::cancel($this->rental, $this->cancellation_reason);
        session()->flash('status', $result['requires_review'] ?? false ? 'Cancellation requested. Your reservation remains in place until reviewed.' : 'Booking cancelled. Any refund must be arranged manually.');

        $this->reset('cancellation_reason', 'showCancelForm');
        $this->rental->refresh()->load(['rentalRequest', 'securityDeposit']);
    }

    public function confirmPickup(): void
    {
        $this->authorize('confirmPickup', $this->rental);

        RentalLifecycle::confirmPickup($this->rental, auth()->user());

        $this->rental->refresh();
    }

    public function confirmDepositReceipt(): void
    {
        RentalLifecycle::confirmDepositReceipt($this->rental, auth()->user());
        $this->rental->refresh();
        session()->flash('status', 'Deposit receipt confirmed.');
    }

    public function confirmReturn(): void
    {
        $this->authorize('confirmReturn', $this->rental);

        RentalLifecycle::confirmReturn($this->rental, auth()->user());

        $this->rental->refresh();
    }

    public function acceptDamageClaim(): void
    {
        $damageReport = $this->rental->damageReport;

        $this->authorize('respond', $damageReport);

        RentalLifecycle::acceptDamageClaim($damageReport);

        $this->rental->refresh()->load(['damageReport', 'securityDeposit']);
    }

    public function disputeDamageClaim(): void
    {
        $damageReport = $this->rental->damageReport;

        $this->authorize('respond', $damageReport);

        $this->validate(['damage_response_notes' => ['required', 'string', 'max:1000']]);

        RentalLifecycle::disputeDamageClaim($damageReport, $this->damage_response_notes);

        $this->rental->refresh()->load('damageReport');
        $this->reset('damage_response_notes');
    }

    public function submitOwnerReview(): void
    {
        $this->rental->refresh();
        $this->authorize('review', [$this->rental, ReviewType::RenterToOwner]);

        $this->validate([
            'owner_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'owner_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        RentalReviews::submit($this->rental, auth()->user(), ReviewType::RenterToOwner, $this->owner_rating, $this->owner_comment);

        $this->rental->refresh()->load('reviewFromRenterToOwner');
    }

    public function submitListingReview(): void
    {
        $this->rental->refresh();
        $this->authorize('review', [$this->rental, ReviewType::RenterToListing]);

        $this->validate([
            'listing_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'listing_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        RentalReviews::submit($this->rental, auth()->user(), ReviewType::RenterToListing, $this->listing_rating, $this->listing_comment);

        $this->rental->refresh()->load('reviewFromRenterToListing');
    }

    public function createDispute(): void
    {
        $this->rental->refresh();
        $this->authorize('view', $this->rental);
        abort_unless(auth()->id() === $this->rental->renter_id, 403);

        $this->validate([
            'dispute_reason' => ['required', Rule::enum(DisputeReason::class)],
            'dispute_description' => ['required', 'string', 'max:1000'],
        ]);

        Dispute::create([
            'rental_id' => $this->rental->id,
            'raised_by' => auth()->id(),
            'reason' => $this->dispute_reason,
            'description' => $this->dispute_description,
        ]);

        $this->rental->owner->notify(new TalaNotification(
            NotificationType::DisputeUpdate->value,
            'New dispute raised',
            "The renter raised a dispute for \"{$this->rental->listing->name}\".",
            route('owner.rentals.show', $this->rental),
        ));

        session()->flash('status', 'Your dispute has been submitted. An admin will review it.');

        $this->reset('dispute_reason', 'dispute_description', 'showDisputeForm');
        $this->rental->refresh()->load('disputes');
    }

    public function render(): View
    {
        $this->rental->refresh();
        $this->authorize('view', $this->rental);
        RentalLifecycle::synchronizeRentalStatus($this->rental);
        $this->rental->refresh()->load([
            'listing', 'owner', 'renter', 'payment', 'securityDeposit',
            'beforeConditionRecord.photos', 'afterConditionRecord.photos', 'damageReport.photos',
            'reviewFromRenterToOwner', 'reviewFromRenterToListing', 'disputes',
            'paymentSubmissions',
        ]);

        $paymentDestination = $this->rental->isPaymentPending()
            ? RentalPayments::destinationFor($this->rental, auth()->user())
            : $this->rental->payment_destination;

        $this->rental->refresh();

        return view('livewire.renter.rentals.show', [
            'paymentSettings' => OfflinePaymentSetting::current(),
            'paymentDestination' => $paymentDestination,
            'paymentWallets' => $this->rental->isPaymentPending()
                && ($paymentDestination['source'] ?? null) === 'owner_wallet'
                && auth()->user()->can('pay', $this->rental)
                && $this->rental->paymentSubmissions->isEmpty()
                    ? RentalPayments::availableWalletsFor($this->rental->owner_id)
                    : collect(),
        ]);
    }

    public function refreshRental(): void
    {
        $this->rental->refresh();
        $this->authorize('view', $this->rental);
        $this->rental->load(['payment', 'securityDeposit', 'damageReport.photos', 'afterConditionRecord.photos', 'disputes']);
    }
}
