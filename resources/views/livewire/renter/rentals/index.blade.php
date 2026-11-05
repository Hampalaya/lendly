<div wire:poll.5s.keep-alive>
    <x-page-header eyebrow="Renting" title="My rentals" subtitle="View current rentals and your complete booking and transaction history." />
    <div class="px-4 pt-3 sm:px-6 lg:px-8">
        <x-record-toolbar :filter="$filter" :sort="$recordSort" :filters="['all' => 'All Statuses'] + collect(\App\Enums\RentalStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" placeholder="Search rentals..." search-label="Search rentals" label="Rental controls" />
    </div>

    <div class="w-full px-4 pb-6 pt-3 sm:px-6 lg:px-8">
        @if ($rentals->isEmpty())
            <x-empty-state title="No rentals yet" message="Once both parties accept the rental agreement, the request is approved and the booking appears here for payment." />
        @else
            <div class="record-list">
                @foreach ($rentals as $rental)
                    <x-record-row :listing="$rental->listing" wire:key="rental-{{ $rental->id }}">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p class="text-sm font-semibold text-slate-800">{{ $rental->listing->name }}</p>
                            <x-badge :color="$rental->displayStatusColor()">{{ $rental->displayStatusLabel() }}</x-badge>
                        </div>
                        @if ($rental->listing->trashed())
                            <p class="mt-0.5 text-xs text-slate-500">Listing removed</p>
                        @endif
                        <p class="mt-0.5 text-xs text-slate-500"><x-rental-period :booking="$rental" /></p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Owner: {{ $rental->owner->name }} &middot; Total &#8369;{{ number_format($rental->total_amount, 2) }}
                        </p>
                        <x-slot:actions>
                            @php
                                [$actionLabel, $actionIcon] = match (true) {
                                    $rental->isPaymentPending() && $rental->paymentSubmissions->contains('status', 'pending') => ['Awaiting Payment Confirmation', 'clock'],
                                    $rental->isPaymentPending() => ['Pay Now', 'wallet'],
                                    $rental->awaitingPickupConfirmation() => ['Confirm Pickup', 'inbox'],
                                    $rental->awaitingReturnConfirmation() => ['Confirm Return', 'archive'],
                                    default => ['View Details', 'search'],
                                };
                            @endphp
                            <x-record-action :href="route('renter.rentals.show', $rental)" :icon="$actionIcon" :label="$actionLabel" wire:navigate />
                        </x-slot:actions>
                    </x-record-row>
                @endforeach
            </div>

            <div class="mt-6">{{ $rentals->links() }}</div>
        @endif
    </div>
</div>
