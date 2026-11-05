<?php

namespace App\Livewire\Messages\Concerns;

use App\Models\ListingConversation;
use App\Models\RentalRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * Shared between Index and Show so both can render the same left-hand
 * conversation list in the Messenger-style split view — Show needs it
 * alongside the active thread, not just Index.
 */
trait ListsThreads
{
    #[Url]
    public string $search = '';

    public string $threadFilter = 'all';

    public string $threadSort = 'latest';

    public function toggleThreadSort(): void
    {
        $this->threadSort = $this->threadSort === 'latest' ? 'oldest' : 'latest';
    }

    protected function threadsForCurrentUser(): Collection
    {
        $rentalThreads = RentalRequest::query()
            ->where(function ($query) {
                if (session('active_interface') === 'owner') {
                    $query->whereHas('listing', fn ($q) => $q->where('owner_id', auth()->id()));
                } elseif (session('active_interface') === 'renter') {
                    $query->where('renter_id', auth()->id());
                } else {
                    $query->where('renter_id', auth()->id())
                        ->orWhereHas('listing', fn ($q) => $q->where('owner_id', auth()->id()));
                }
            })
            ->whereHas('messages')
            // RentalRequest::messages() orders ascending by default (oldest
            // first, for the chat thread view) — reorder() clears that
            // before applying a descending order, otherwise the extra
            // ORDER BY on the same column is a no-op and limit(1) grabs the
            // oldest message. Ordered by id, not created_at, since two
            // messages sent in the same second (routine in a fast test run,
            // possible in production too) would otherwise tie and make the
            // "latest" pick non-deterministic.
            ->with(['listing.owner', 'renter', 'messages' => fn ($q) => $q->reorder('id', 'desc')->limit(1)])
            ->get()
            ->map(function (RentalRequest $rentalRequest) {
                return [
                    'rentalRequest' => $rentalRequest,
                    'id' => $rentalRequest->id,
                    'url' => route('rental-requests.chat', $rentalRequest),
                    'listing' => $rentalRequest->listing,
                    'otherParty' => $rentalRequest->otherPartyFor(auth()->user()),
                    'lastMessage' => $rentalRequest->messages->first(),
                    'unreadCount' => $rentalRequest->messages()
                        ->where('receiver_id', auth()->id())
                        ->whereNull('read_at')
                        ->count(),
                ];
            });

        $listingThreads = ListingConversation::query()
            ->where(function ($query) {
                if (session('active_interface') === 'owner') {
                    $query->where('owner_id', auth()->id());
                } elseif (session('active_interface') === 'renter') {
                    $query->where('renter_id', auth()->id());
                } else {
                    $query->where('renter_id', auth()->id())->orWhere('owner_id', auth()->id());
                }
            })
            ->whereHas('messages')
            ->with(['listing', 'owner', 'renter', 'messages' => fn ($query) => $query->reorder('id', 'desc')->limit(1)])
            ->withCount(['messages as unread_count' => fn ($query) => $query->where('receiver_id', auth()->id())->whereNull('read_at')])
            ->get()
            ->map(fn (ListingConversation $conversation) => [
                'listingConversation' => $conversation,
                'id' => 'listing-'.$conversation->id,
                'url' => route('listing-conversations.show', $conversation),
                'listing' => $conversation->listing,
                'otherParty' => $conversation->otherPartyFor(auth()->user()),
                'lastMessage' => $conversation->messages->first(),
                'unreadCount' => $conversation->unread_count,
            ]);

        return $rentalThreads->concat($listingThreads)
            ->when(trim($this->search) !== '', fn ($threads) => $threads->filter(fn ($thread) => Str::contains($thread['otherParty']->name.' '.$thread['listing']->name, trim($this->search), ignoreCase: true)))
            ->filter(fn ($thread) => match ($this->threadFilter) {
                'unread' => $thread['unreadCount'] > 0,
                'rentals' => isset($thread['rentalRequest']),
                'listings' => isset($thread['listingConversation']),
                default => true,
            })
            ->sortBy(fn ($thread) => [$thread['lastMessage']?->created_at?->format('Y-m-d H:i:s.u'), $thread['lastMessage']?->id, (string) $thread['id']], SORT_REGULAR, $this->threadSort !== 'oldest')
            ->values();
    }
}
