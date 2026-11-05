<?php

namespace App\Livewire\Admin\Listings;

use App\Enums\ListingStatus;
use App\Livewire\Concerns\FiltersRecords;
use App\Models\Listing;
use App\Models\User;
use App\Services\ListingProgress;
use App\Services\ListingPublication;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use FiltersRecords;
    use WithPagination;

    public string $filter = 'published';

    public ?int $rejecting = null;

    public string $rejection_reason = '';

    public ?int $removing = null;

    public string $removal_reason = '';

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    private function admin(): void
    {
        $admin = User::find(auth()->id());
        abort_unless($admin && $admin->isAdmin() && ! $admin->isSuspended() && $admin->hasVerifiedEmail(), 403);
    }

    public function approve(int $listingId): void
    {
        $admin = User::find(auth()->id());
        abort_unless($admin && $admin->isAdmin() && ! $admin->isSuspended() && $admin->hasVerifiedEmail(), 403);
        DB::transaction(function () use ($listingId) {
            $snapshot = Listing::findOrFail($listingId);
            ListingPublication::ensureVerifiedOwner($snapshot->owner_id);
            $listing = Listing::lockForUpdate()->findOrFail($listingId);
            abort_unless($listing->owner_id === $snapshot->owner_id, 409);
            if ($listing->status !== ListingStatus::Published || $listing->rejection_reason !== null) {
                $listing->update(['status' => ListingStatus::Published, 'rejection_reason' => null]);
                ListingProgress::notify($listing, 'status');
            }
        });
    }

    public function startRejecting(int $listingId): void
    {
        $this->admin();
        $this->rejecting = $listingId;
        $this->rejection_reason = '';
    }

    public function confirmReject(): void
    {
        $this->admin();
        $this->validate(['rejection_reason' => ['required', 'string', 'max:255']]);

        $listing = Listing::findOrFail($this->rejecting);

        $listing->update(['status' => ListingStatus::Rejected, 'rejection_reason' => $this->rejection_reason]);
        ListingProgress::notify($listing, 'status');

        $this->rejecting = null;
        $this->rejection_reason = '';
    }

    public function remove(int $listingId): void
    {
        $this->admin();
        $listing = Listing::findOrFail($listingId);
        abort_unless($listing->status === ListingStatus::Published, 409);
        $this->resetValidation('removal_reason');
        $this->removing = $listing->id;
        $this->removal_reason = '';
    }

    public function cancelRemoving(): void
    {
        $this->reset('removing', 'removal_reason');
        $this->resetValidation('removal_reason');
    }

    public function confirmRemoval(): void
    {
        $this->admin();
        $this->removal_reason = trim($this->removal_reason);
        $this->validate(['removal_reason' => ['required', 'string', 'max:255']]);

        DB::transaction(function () {
            $listing = Listing::lockForUpdate()->findOrFail($this->removing);
            abort_unless($listing->status === ListingStatus::Published, 409);
            $listing->update(['status' => ListingStatus::Inactive, 'rejection_reason' => $this->removal_reason]);
            ListingProgress::notify($listing, 'status');
        });

        $this->cancelRemoving();
    }

    public function render(): View
    {
        $this->admin();
        $listings = $this->filterRecords(Listing::query()
            ->when($this->filter !== 'all', fn ($query) => $query->where('status', $this->filter))
            ->with(['category', 'owner', 'images'])
            ->orderByDesc('created_at')
            ->orderByDesc('id'), ['name', 'brand', 'location'], ['owner' => ['name', 'email'], 'category' => ['name']])->paginate(10);

        return view('livewire.admin.listings.index', [
            'listings' => $listings,
            'pendingCount' => Listing::pendingApproval()->count(),
        ]);
    }
}
