<?php

namespace App\Livewire\Owner\RentalRequests;

use App\Enums\FulfillmentMethod;
use App\Enums\NotificationType;
use App\Enums\RentalRequestStatus;
use App\Livewire\Concerns\FiltersRecords;
use App\Models\Listing;
use App\Models\RentalRequest;
use App\Models\User;
use App\Notifications\TalaNotification;
use App\Services\RentalAgreement;
use App\Services\RentalPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use FiltersRecords;
    use WithPagination;

    #[Url]
    public string $filter = 'requested';

    public ?int $rejecting = null;

    public string $rejection_reason = '';

    #[Locked]
    public ?int $approving = null;

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function startApproving(int $rentalRequestId): void
    {
        $request = RentalRequest::with('listing')->findOrFail($rentalRequestId);
        $this->authorize('moderate', $request);
        abort_unless($request->isPending(), 403);
        $this->resetValidation('approve');
        $this->reset('rejecting', 'rejection_reason');
        $this->approving = $rentalRequestId;
    }

    public function cancelApproving(): void
    {
        $this->reset('approving');
        $this->resetValidation('approve');
    }

    public function approve(int $rentalRequestId): void
    {
        $snapshot = RentalRequest::with('listing')->findOrFail($rentalRequestId);
        $this->authorize('moderate', $snapshot);
        $approved = DB::transaction(function () use ($snapshot, $rentalRequestId) {
            $participants = User::whereIn('id', [$snapshot->listing->owner_id, $snapshot->renter_id])->orderBy('id')->lockForUpdate()->get();
            abort_unless($participants->count() === 2 && $participants->every(fn (User $user) => ! $user->isSuspended() && $user->hasVerifiedIdentity()), 403);
            Listing::withTrashed()->whereKey($snapshot->listing_id)->lockForUpdate()->firstOrFail();
            $rentalRequest = RentalRequest::with('listing')->lockForUpdate()->findOrFail($rentalRequestId);

            $this->authorize('moderate', $rentalRequest);

            abort_unless($rentalRequest->isPending(), 403);

            if (! $rentalRequest->listing->acceptsNewRequests()
                || ! $rentalRequest->listing->includesAvailableDates($rentalRequest->start_date, $rentalRequest->end_date)) {
                $this->addError('approve', 'This item is unavailable for the requested dates. Update the listing or decline the request.');

                return;
            }

            if (($rentalRequest->fulfillment_method === FulfillmentMethod::Pickup && ! $rentalRequest->listing->pickup_available)
                || ($rentalRequest->fulfillment_method === FulfillmentMethod::Delivery && ! $rentalRequest->listing->delivery_available)
                || ($rentalRequest->rental_unit !== 'hour' && $rentalRequest->rental_days > $rentalRequest->listing->max_rental_duration_days)) {
                $this->addError('approve', 'The requested fulfillment option or duration is no longer available. Ask the renter to submit an updated request.');

                return false;
            }

            if ($rentalRequest->start_date->toDateString() < now('Asia/Manila')->toDateString() || $rentalRequest->starts_at?->lte(now()) || $rentalRequest->listing->hasApprovedOverlap($rentalRequest->start_date, $rentalRequest->end_date, excludingRequestId: $rentalRequest->id, startsAt: $rentalRequest->starts_at, endsAt: $rentalRequest->ends_at)) {
                $this->addError('approve', 'These dates were already approved for another renter.');

                return;
            }

            RentalPeriod::attributes($rentalRequest->listing, RentalPeriod::details($rentalRequest), newRequest: false);
            $rentalRequest->update([
                'status' => RentalRequestStatus::AwaitingAgreement,
                'renter_terms_accepted_at' => null,
                'owner_terms_accepted_at' => null,
                'agreement_terms' => RentalAgreement::termsFor($rentalRequest),
            ]);

            // A save callback can approve another request before this update reaches storage.
            if ($rentalRequest->listing->hasApprovedOverlap($rentalRequest->start_date, $rentalRequest->end_date, excludingRequestId: $rentalRequest->id, startsAt: $rentalRequest->starts_at, endsAt: $rentalRequest->ends_at)) {
                $rentalRequest->update([
                    'status' => RentalRequestStatus::Rejected,
                    'rejection_reason' => 'These dates were booked by another renter.',
                    'renter_terms_accepted_at' => null,
                    'owner_terms_accepted_at' => null,
                    'agreement_terms' => null,
                ]);
                $this->addError('approve', 'These dates were already approved for another renter.');

                return false;
            }

            $rentalRequest->renter->notify(new TalaNotification(
                NotificationType::AgreementReady->value,
                'Rental agreement ready',
                "The owner selected your request for \"{$rentalRequest->listing->name}\". Dates are on hold. Both parties must review and accept the rental agreement before the request is approved and the booking is finalized.",
                route('renter.rental-requests.agreement', $rentalRequest),
            ));

            $rentalRequest->listing->owner->notify(new TalaNotification(
                NotificationType::AgreementReady->value,
                'Rental agreement ready',
                "You selected {$rentalRequest->renter->name}'s request for \"{$rentalRequest->listing->name}\". Dates are on hold. Both parties must review and accept the rental agreement before the request is approved and the booking is finalized.",
                route('owner.rental-requests.agreement', $rentalRequest),
            ));

            // Approving one request confirms the dates, so any other pending
            // request for the same listing that overlaps is no longer viable.
            $overlapping = RentalRequest::where('listing_id', $rentalRequest->listing_id)->where(function ($query) use ($rentalRequest) {
                RentalPeriod::applyOverlap($query, $rentalRequest->start_date, $rentalRequest->end_date, $rentalRequest->starts_at, $rentalRequest->ends_at);
            })
                ->where('status', RentalRequestStatus::Requested)
                ->whereKeyNot($rentalRequest->id)
                ->lockForUpdate()
                ->get();

            foreach ($overlapping as $other) {
                $other->update([
                    'status' => RentalRequestStatus::Rejected,
                    'rejection_reason' => 'These dates were booked by another renter.',
                ]);

                $other->renter->notify(new TalaNotification(
                    NotificationType::RequestRejected->value,
                    'Request declined',
                    "Your request for \"{$rentalRequest->listing->name}\" was declined: {$other->rejection_reason}",
                    route('renter.rental-requests.index'),
                ));
                $rentalRequest->listing->owner->notify(new TalaNotification(
                    NotificationType::RequestRejected->value,
                    'Request automatically declined',
                    "{$other->renter->name}'s request for \"{$rentalRequest->listing->name}\" was automatically declined: {$other->rejection_reason}",
                    route('owner.rental-requests.index'),
                ));
            }

            return true;
        }, 3);

        if ($approved) {
            $this->reset('approving');
            $this->redirect(route('owner.rental-requests.agreement', $rentalRequestId), navigate: true);
        }
    }

    public function startRejecting(int $rentalRequestId): void
    {
        $this->cancelApproving();
        $this->rejecting = $rentalRequestId;
        $this->rejection_reason = '';
    }

    public function confirmReject(): void
    {
        $this->validate(['rejection_reason' => ['required', 'string', 'max:255']]);

        DB::transaction(function () {
            $rentalRequest = RentalRequest::with('listing')->lockForUpdate()->findOrFail($this->rejecting);

            $this->authorize('moderate', $rentalRequest);

            abort_unless($rentalRequest->isPending(), 403);

            $rentalRequest->update([
                'status' => RentalRequestStatus::Rejected,
                'rejection_reason' => $this->rejection_reason,
            ]);

            $rentalRequest->renter->notify(new TalaNotification(
                NotificationType::RequestRejected->value,
                'Request declined',
                "Your request for \"{$rentalRequest->listing->name}\" was declined: {$this->rejection_reason}",
                route('renter.rental-requests.index'),
            ));
            $rentalRequest->listing->owner->notify(new TalaNotification(
                NotificationType::RequestRejected->value,
                'Request declined',
                "You declined {$rentalRequest->renter->name}'s request for \"{$rentalRequest->listing->name}\": {$this->rejection_reason}",
                route('owner.rental-requests.index'),
            ));
        }, 3);

        $this->rejecting = null;
        $this->rejection_reason = '';
    }

    public function render(): View
    {
        $rentalRequests = $this->filterRecords(RentalRequest::query()
            ->whereHas('listing', fn ($query) => $query->where('owner_id', auth()->id()))
            ->when($this->filter !== 'all', fn ($query) => $query->where('status', $this->filter))
            ->with(['listing.images', 'renter', 'rental'])
            ->orderByDesc('created_at')
            ->orderByDesc('id'), ['id'], ['listing' => ['name'], 'renter' => ['name', 'email']])->paginate(10);

        $approvalRequest = $this->approving ? RentalRequest::with(['listing', 'renter'])->findOrFail($this->approving) : null;
        if ($approvalRequest) {
            $this->authorize('moderate', $approvalRequest);
        }

        return view('livewire.owner.rental-requests.index', ['rentalRequests' => $rentalRequests, 'approvalRequest' => $approvalRequest]);
    }
}
