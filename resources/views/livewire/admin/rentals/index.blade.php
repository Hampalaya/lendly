<div wire:poll.5s.keep-alive x-data="{ previousBodyOverflow: '' }">
    <x-page-header eyebrow="Administration" title="Transactions" subtitle="All rental bookings on the platform, across every owner and renter." />

    <div class="px-4 pt-3 sm:px-6 lg:px-8">
        <x-record-toolbar
            :filter="$status"
            :sort="$recordSort"
            :filters="['' => 'All Statuses'] + collect($statuses)->mapWithKeys(fn ($option) => [$option->value => $option->label()])->all()"
            search-model="search"
            filter-model="status"
            placeholder="Search transactions..."
            search-label="Search transactions"
            label="Transaction controls"
        />
    </div>

    <div class="w-full px-4 pb-6 pt-3 sm:px-6 lg:px-8">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex w-full max-w-sm items-center gap-3 rounded-xl border border-slate-200 border-l-4 border-l-indigo-600 bg-white p-4 shadow-sm">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                    <x-icon name="archive" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-xs font-medium text-slate-500">Total transaction value (paid, matching filters)</p>
                    <p class="mt-0.5 text-lg font-bold text-slate-900">&#8369;{{ number_format($totalTransactionValue, 2) }}</p>
                </div>
            </div>
            <button type="button" aria-haspopup="dialog" aria-controls="offline-payment-modal" x-on:click="previousBodyOverflow = document.body.style.overflow; $refs.offlinePaymentModal.showModal(); document.body.style.overflow = 'hidden'" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:border-blue-500 hover:bg-blue-100 focus:outline-none focus:ring-4 focus:ring-blue-100">
                <x-icon name="cog" class="h-4 w-4 shrink-0" aria-hidden="true" />
                <span>Configure offline payment instructions</span>
            </button>
        </div>

        @if ($rentals->isEmpty())
            <x-empty-state title="No transactions found" message="Try a different search term or status filter." />
        @else
            <div class="record-list">
                @foreach ($rentals as $rental)
                    <x-record-row :listing="$rental->listing" wire:key="rental-{{ $rental->id }}">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <h2 class="text-sm font-semibold text-slate-800">{{ $rental->listing->name }}</h2>
                            <x-badge :color="$rental->displayStatusColor()">{{ $rental->displayStatusLabel() }}</x-badge>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500"><x-rental-period :booking="$rental" /></p>
                        <p class="mt-0.5 text-xs text-slate-500">Owner: {{ $rental->owner->name }} &middot; Renter: {{ $rental->renter->name }} &middot; Total &#8369;{{ number_format($rental->total_amount, 2) }}</p>

                        <x-slot:actions>
                            <x-record-action :href="route('admin.rentals.show', $rental)" icon="search" label="View Transaction" wire:navigate />
                        </x-slot:actions>
                    </x-record-row>
                @endforeach
            </div>
            <div class="mt-4">{{ $rentals->links() }}</div>
        @endif
    </div>

    <dialog
        id="offline-payment-modal"
        x-ref="offlinePaymentModal"
        wire:ignore.self
        aria-labelledby="offline-payment-title"
        aria-describedby="offline-payment-description"
        x-on:close-offline-payment.stop="$el.close()"
        x-on:close="document.body.style.overflow = previousBodyOverflow"
        x-on:livewire:navigating.window="if ($el.open) { $el.close(); document.body.style.overflow = previousBodyOverflow; }"
        x-on:click="if ($event.target === $el) { const bounds = $el.getBoundingClientRect(); if ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom) $el.close(); }"
        class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-2xl border-0 bg-slate-50 p-0 text-slate-900 shadow-xl backdrop:bg-slate-900/50"
    >
        <livewire:admin.payments.settings :modal="true" />
    </dialog>
</div>
