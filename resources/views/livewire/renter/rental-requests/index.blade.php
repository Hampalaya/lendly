<div>
    <x-page-header eyebrow="Renting" title="My rental requests" subtitle="View current and past rental requests." />
    <div class="px-4 pt-3 sm:px-6 lg:px-8">
        <x-record-toolbar :filter="$filter" :sort="$recordSort" :filters="['all' => 'All Statuses'] + collect(\App\Enums\RentalRequestStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" placeholder="Search requests..." search-label="Search rental requests" label="Rental request controls" />
    </div>

    <div class="w-full px-4 pb-6 pt-3 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">{{ session('status') }}</div>
        @endif

        @if ($rentalRequests->isEmpty())
            <x-empty-state title="No rental requests yet" message="Browse listings and request to rent an item to see it here." />
        @else
            <div class="record-list">
                @foreach ($rentalRequests as $request)
                    <x-record-row :listing="$request->listing" wire:key="request-{{ $request->id }}">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            @if ($request->listing->trashed())
                                <span class="text-sm font-semibold text-slate-800">{{ $request->listing->name }} (listing removed)</span>
                            @else
                                <a href="{{ route('listings.show', $request->listing) }}" wire:navigate class="text-sm font-semibold text-slate-800 hover:text-blue-700">{{ $request->listing->name }}</a>
                            @endif
                            <x-badge :color="$request->status->badgeColor()">{{ $request->status->label() }}</x-badge>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500"><x-rental-period :booking="$request" /></p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Owner: {{ $request->listing->owner->name }} &middot; {{ ucfirst($request->fulfillment_method->value) }} &middot; Total &#8369;{{ number_format($request->total_amount, 2) }}
                        </p>
                        @if ($request->status->value === 'rejected' && $request->rejection_reason)
                            <p class="mt-0.5 text-xs text-rose-600">Reason: {{ $request->rejection_reason }}</p>
                        @endif
                        @if ($request->status->value === 'cancelled')
                            <x-cancellation-details :record="$request" />
                        @endif

                        @if ($request->rental || $request->canReviewAgreement() || $request->isCancellableByRenter())
                            <x-slot:actions>
                                @if ($request->rental)
                                    <x-record-action :href="route('renter.rentals.show', $request->rental)" icon="search" label="View Booking" wire:navigate />
                                @elseif ($request->canReviewAgreement())
                                    <x-record-action :href="route('renter.rental-requests.agreement', $request)" icon="clipboard-list" label="Review Rental Agreement" wire:navigate />
                                @endif
                                @if (! $request->rental && $request->isCancellableByRenter())
                                    <x-record-action icon="x-mark" label="Cancel Request" tone="danger" wire:click="startCancelling({{ $request->id }})" />
                                @endif
                            </x-slot:actions>
                        @endif

                        @if ($cancelling === $request->id && ! $request->rental && $request->isCancellableByRenter())
                            <x-slot:footer>
                                <form wire:submit="cancel({{ $request->id }})" class="rounded-lg border border-rose-200 bg-rose-50 p-3">
                                    <p class="mb-3 text-sm text-rose-700">Cancelling a request before the booking is finalized is free.</p>
                                    <x-input-label :for="'cancellation-reason-'.$request->id" value="Reason for cancellation (optional)" />
                                    <textarea wire:model="cancellation_reason" id="cancellation-reason-{{ $request->id }}" rows="2" maxlength="500" class="mt-1 block w-full rounded-lg border-rose-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500"></textarea>
                                    <x-input-error :messages="$errors->get('cancellation_reason')" class="mt-2" />
                                    <div class="mt-3 flex justify-end gap-3">
                                        <button type="button" wire:click="$set('cancelling', null)" class="min-h-11 px-3 text-sm font-medium text-slate-600">Keep Request</button>
                                        <button type="submit" wire:loading.attr="disabled" wire:target="cancel" wire:confirm="Cancel this rental request?" class="min-h-11 rounded-lg bg-rose-600 px-4 text-sm font-semibold text-white disabled:opacity-60">Confirm Cancellation</button>
                                    </div>
                                </form>
                            </x-slot:footer>
                        @endif
                    </x-record-row>
                @endforeach
            </div>

            <div class="mt-6">{{ $rentalRequests->links() }}</div>
        @endif
    </div>
</div>
