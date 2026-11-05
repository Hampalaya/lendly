<div wire:poll.15s.visible>
    <x-page-header eyebrow="Owning" title="My listings" subtitle="Manage the items you've listed for rent.">
        <x-slot:actions>
            <a href="{{ route('owner.listings.create') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-blue-600 px-3 text-xs font-semibold text-white transition hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 sm:text-sm">
                <x-icon name="tag" class="h-4 w-4" aria-hidden="true" />
                <span>New listing</span>
            </a>
        </x-slot:actions>
    </x-page-header>
    <div class="px-4 pt-3 sm:px-6 lg:px-8">
        <x-record-toolbar :filter="$filter" :sort="$recordSort" :filters="['all' => 'All Statuses'] + collect(\App\Enums\ListingStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" placeholder="Search listings..." search-label="Search listings" label="Listing controls" />
    </div>

    <div class="w-full px-4 pb-6 pt-3 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="mb-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                <p class="font-semibold">Edit your listing to complete the required details before resuming.</p>
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if ($listings->isEmpty())
            <x-empty-state title="No listings yet" message="Create your first listing to start earning from items you rarely use." />
        @else
            <div class="record-list">
                @foreach ($listings as $listing)
                    @php
                        $availability = $listing->availabilityStatus();
                        $requestsOpen = $listing->is_available && ! $listing->reopen_required;
                    @endphp
                    <x-record-row :listing="$listing" class="record-row--listing" wire:key="listing-{{ $listing->id }}">
                        <h2 class="text-sm font-semibold leading-5 text-slate-800">{{ $listing->name }}</h2>
                        <div class="mt-1"><x-category-badge :category="$listing->category" /></div>
                        <p class="mt-1 text-xs font-semibold text-slate-700">
                            &#8369;{{ number_format($listing->allows_daily_rentals ? $listing->price_per_day : $listing->price_per_hour, 2) }}<span class="font-normal text-slate-500"> / {{ $listing->allows_daily_rentals ? 'day' : 'hour' }}</span>
                        </p>
                        <div class="mt-1"><x-badge :color="$listing->status->badgeColor()">{{ $listing->status->label() }}</x-badge></div>

                        <x-slot:aside>
                            <div class="mb-1 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                <x-badge :color="$availability->badgeColor()">{{ $availability->label() }}</x-badge>
                                <button type="button" role="switch" aria-checked="{{ $requestsOpen ? 'true' : 'false' }}" aria-label="{{ $listing->name }} availability" wire:click="setAvailability({{ $listing->id }}, {{ $requestsOpen ? 'false' : 'true' }})" wire:loading.attr="disabled" wire:target="setAvailability" class="inline-flex min-h-9 items-center gap-2 rounded-lg px-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                                    <span aria-hidden="true" @class(['relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors', 'bg-blue-600' => $requestsOpen, 'bg-slate-300' => ! $requestsOpen])>
                                        <span @class(['h-4 w-4 rounded-full bg-white shadow-sm transition-transform', 'translate-x-4' => $requestsOpen, 'translate-x-0.5' => ! $requestsOpen])></span>
                                    </span>
                                    <span>{{ $requestsOpen ? 'Accepting requests' : 'Requests paused' }}</span>
                                </button>
                            </div>
                            @if ($listing->available_from && $listing->available_until)
                                <p class="mt-1 text-xs text-slate-500">Available {{ $listing->available_from->format('M d, Y') }} &ndash; {{ $listing->available_until->format('M d, Y') }}</p>
                            @endif
                            <p class="mt-1 text-xs text-slate-500">Opening hours: {{ $listing->available_time_from }}&ndash;{{ $listing->available_time_until }} &middot; Philippine time</p>
                            <p class="mt-1 text-xs leading-5 text-slate-600">Unbooked periods today: {{ implode(', ', \App\Services\ListingAvailability::freePeriodsToday($listing)) ?: 'None within the opening window.' }} <span class="text-slate-500">Return, inspection, and reopening are still required before reuse.</span></p>
                            @foreach ($listing->rentalRequests as $reservation)
                                <p class="mt-1 text-xs text-slate-500">{{ $listing->paidReservations->contains('rental_request_id', $reservation->id) ? 'Reserved' : 'On hold' }} {{ $reservation->starts_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y H:i') ?? $reservation->start_date->format('M d, Y') }} &ndash; {{ $reservation->ends_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y H:i') ?? $reservation->end_date->format('M d, Y') }}</p>
                            @endforeach
                            @if ($listing->reopen_required)
                                <p class="mt-2 text-xs font-medium text-amber-800">Return and inspection must be complete before you reopen requests.</p>
                                <button type="button" wire:click="setAvailability({{ $listing->id }}, true)" wire:loading.attr="disabled" class="mt-2 min-h-9 rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-800 hover:bg-blue-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">Reopen After Inspection</button>
                            @endif
                        </x-slot:aside>

                        <x-slot:actions>
                            <x-record-action :href="route('owner.listings.edit', $listing)" icon="pencil" label="Edit Listing" wire:navigate aria-label="Edit {{ $listing->name }}" />
                            @if ($listing->isPublished())
                                <x-record-action icon="pause" label="Pause Listing" wire:click="deactivate({{ $listing->id }})" wire:loading.attr="disabled" aria-label="Pause {{ $listing->name }}" />
                            @elseif ($listing->status === \App\Enums\ListingStatus::Inactive)
                                <x-record-action icon="play" label="Resume Listing" tone="success" wire:click="reactivate({{ $listing->id }})" wire:loading.attr="disabled" aria-label="Resume {{ $listing->name }}" />
                            @endif
                            <x-record-action icon="trash" label="Remove Listing" tone="danger" wire:click="delete({{ $listing->id }})" wire:confirm="Remove this listing from the marketplace? Existing bookings and transaction history will be retained." wire:loading.attr="disabled" aria-label="Remove {{ $listing->name }}" />
                        </x-slot:actions>
                    </x-record-row>
                @endforeach
            </div>
            <div class="mt-4">{{ $listings->links() }}</div>
        @endif
    </div>
</div>
