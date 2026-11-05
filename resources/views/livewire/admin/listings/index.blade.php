<div>
    <x-page-header eyebrow="Administration" title="Listings" subtitle="Review pending listings and moderate published ones." />
    <div class="px-4 pt-3 sm:px-6 lg:px-8">
        <x-record-toolbar
            :filter="$filter"
            :sort="$recordSort"
            :filters="['pending_approval' => 'Pending ('.$pendingCount.')', 'published' => 'Published', 'rejected' => 'Rejected', 'inactive' => 'Inactive', 'all' => 'All Statuses']"
            placeholder="Search listings..."
            search-label="Search listings"
            label="Listing controls"
        />
    </div>

    <div class="w-full px-4 pb-6 pt-3 sm:px-6 lg:px-8">
        @if ($listings->isEmpty())
            <x-empty-state title="Nothing here" message="No listings match this filter right now." />
        @else
            <div class="record-list">
                @foreach ($listings as $listing)
                    <x-record-row :listing="$listing" wire:key="listing-{{ $listing->id }}">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <h2 class="text-sm font-semibold leading-5 text-slate-800">{{ $listing->name }}</h2>
                            <x-badge :color="$listing->status->badgeColor()">{{ $listing->status->label() }}</x-badge>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                            <x-category-badge :category="$listing->category" />
                            <span class="text-xs text-slate-600">&#8369;{{ number_format($listing->allows_daily_rentals ? $listing->price_per_day : $listing->price_per_hour, 2) }}/{{ $listing->allows_daily_rentals ? 'day' : 'hour' }} &middot; {{ $listing->location }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Listed by {{ $listing->owner->name }} ({{ $listing->owner->email }})</p>
                        @if ($listing->status->value === 'rejected' && $listing->rejection_reason)
                            <p class="mt-1 text-xs text-rose-600">Reason: {{ $listing->rejection_reason }}</p>
                        @endif

                        <x-slot:actions>
                            <x-record-action :href="route('listings.show', $listing)" icon="search" label="View Listing" aria-label="View {{ $listing->name }}" target="_blank" rel="noopener" />
                            @if ($listing->status->value === 'pending_approval')
                                <x-record-action icon="check" label="Approve Listing" tone="success" aria-label="Approve {{ $listing->name }}" wire:click="approve({{ $listing->id }})" wire:loading.attr="disabled" wire:target="approve({{ $listing->id }}), startRejecting({{ $listing->id }}), confirmReject" />
                                <x-record-action icon="x-mark" label="Reject Listing" tone="danger" aria-label="Reject {{ $listing->name }}" wire:click="startRejecting({{ $listing->id }})" wire:loading.attr="disabled" wire:target="approve({{ $listing->id }}), startRejecting({{ $listing->id }}), confirmReject" />
                            @elseif ($listing->status->value === 'published')
                                <x-record-action icon="trash" label="Remove Listing" tone="danger" aria-label="Remove {{ $listing->name }}" wire:click="remove({{ $listing->id }})" wire:confirm="Remove this listing from the marketplace?" wire:loading.attr="disabled" wire:target="remove({{ $listing->id }})" />
                            @endif
                        </x-slot:actions>

                        @if ($rejecting === $listing->id)
                            <x-slot:footer>
                                <div class="rounded-lg border border-rose-200 bg-rose-50 p-3">
                                    <x-input-label required :for="'listing-rejection-'.$listing->id" value="Reason for rejection" />
                                    <textarea wire:model="rejection_reason" id="listing-rejection-{{ $listing->id }}" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-rose-500 focus:ring-rose-500"></textarea>
                                    <x-input-error :messages="$errors->get('rejection_reason')" class="mt-2" />
                                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                                        <button type="button" wire:click="$set('rejecting', null)" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">Cancel</button>
                                        <button type="button" wire:click="confirmReject" wire:loading.attr="disabled" wire:target="confirmReject" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                                            <span wire:loading.remove wire:target="confirmReject">Confirm rejection</span>
                                            <span wire:loading wire:target="confirmReject">Rejecting&hellip;</span>
                                        </button>
                                    </div>
                                </div>
                            </x-slot:footer>
                        @endif
                    </x-record-row>
                @endforeach
            </div>
            <div class="mt-4">{{ $listings->links() }}</div>
        @endif
    </div>
</div>
