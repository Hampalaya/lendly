<?php

namespace App\Livewire\Owner\Listings;

use App\Enums\ListingStatus;
use App\Livewire\Concerns\FiltersRecords;
use App\Models\Listing;
use App\Services\ListingAvailability;
use App\Services\ListingProgress;
use App\Services\ListingPublication;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use FiltersRecords;
    use WithPagination;

    public function setAvailability(int $listingId, bool $available): void
    {
        $listing = Listing::findOrFail($listingId);

        $this->authorize('update', $listing);

        ListingAvailability::set($listing, $available);
        ListingProgress::notify($listing->fresh(), $available ? 'reopened' : 'paused');
        session()->flash('status', $available ? 'Requests reopened. Existing reservations remain protected.' : 'Requests paused.');
    }

    public function deactivate(int $listingId): void
    {
        $listing = Listing::findOrFail($listingId);

        abort_unless($listing->isOwnedBy(auth()->user()), 403);
        abort_if($listing->status !== ListingStatus::Published, 403);

        $listing->update(['status' => ListingStatus::Inactive]);
        ListingProgress::notify($listing, 'status');
    }

    public function reactivate(int $listingId): void
    {
        DB::transaction(function () use ($listingId) {
            ListingPublication::ensureVerifiedOwner(auth()->id());
            $listing = Listing::lockForUpdate()->findOrFail($listingId);

            abort_unless($listing->isOwnedBy(auth()->user()), 403);
            abort_if($listing->status !== ListingStatus::Inactive, 403);

            ListingPublication::validate($listing);
            $listing->update(['status' => ListingStatus::Published]);
            ListingProgress::notify($listing, 'status');
        });
    }

    public function delete(int $listingId): void
    {
        $listing = Listing::findOrFail($listingId);

        abort_unless($listing->isOwnedBy(auth()->user()), 403);

        $listing->delete();
        ListingProgress::notify($listing, 'removed');
        session()->flash('status', 'Listing removed. Existing bookings and transaction history are retained.');
    }

    #[Url]
    public string $filter = 'all';

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $listings = $this->filterRecords(Listing::query()
            ->where('owner_id', auth()->id())
            ->when($this->filter !== 'all', fn ($query) => $query->where('status', $this->filter))
            ->withAvailability()
            ->with(['category', 'images', 'paidReservations', 'rentalRequests' => fn ($query) => $query->whereIn('status', ['awaiting_agreement', 'approved'])->where('end_date', '>=', now('Asia/Manila')->toDateString())->whereDoesntHave('rental', fn ($rental) => $rental->whereIn('status', ['completed', 'returned', 'cancelled']))])
            ->orderByDesc('created_at')
            ->orderByDesc('id'), ['name', 'brand', 'location'], ['category' => ['name']])->paginate(10);

        return view('livewire.owner.listings.index', ['listings' => $listings]);
    }
}
