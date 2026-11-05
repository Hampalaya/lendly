<div wire:poll.5s.keep-alive>
    <x-page-header eyebrow="Owning" title="My rentals" subtitle="View current rentals and your complete booking and transaction history." />
    <div class="px-4 pt-3 sm:px-6 lg:px-8">
        <x-record-toolbar :filter="$filter" :sort="$recordSort" :filters="[
            'all' => 'All Statuses',
            'payment_pending' => 'Payment Pending',
            'paid' => 'Paid',
            'active' => 'Active',
            'overdue' => 'Overdue',
            'returned' => 'Returned',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ]" placeholder="Search rentals..." search-label="Search rentals" label="Rental controls" />
    </div>

    <div class="w-full px-4 pb-6 pt-3 sm:px-6 lg:px-8">
        @if ($rentals->isEmpty())
            <x-empty-state :title="$filter === 'all' ? 'No rentals yet' : 'Nothing here'" :message="$filter === 'all' ? 'Once both parties accept the rental agreement, the request is approved and the booking appears here.' : 'No rentals match this status filter right now.'" />
        @else
            <div class="record-list">
                @foreach ($rentals as $rental)
                    <x-record-row :listing="$rental->listing" wire:key="rental-{{ $rental->id }}">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <h2 class="text-sm font-semibold text-slate-800">{{ $rental->listing->name }}</h2>
                            <x-badge :color="$rental->displayStatusColor()">{{ $rental->displayStatusLabel() }}</x-badge>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500">Rental #{{ $rental->id }} @if ($rental->listing->trashed()) &middot; Listing removed @endif</p>
                        <p class="mt-0.5 text-xs text-slate-500"><x-rental-period :booking="$rental" /></p>
                        <p class="mt-0.5 text-xs text-slate-500">Renter: {{ $rental->renter->name }} &middot; Earnings &#8369;{{ number_format($rental->rental_fee, 2) }}</p>

                        <x-slot:actions>
                            <x-record-action :href="route('owner.rentals.show', $rental)" icon="cog" label="Manage Rental" wire:navigate />
                        </x-slot:actions>
                    </x-record-row>
                @endforeach
            </div>
            <div class="mt-4">{{ $rentals->links() }}</div>
        @endif
    </div>
</div>
