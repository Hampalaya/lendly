<div>
    <x-page-header eyebrow="Owning" title="Rental requests" subtitle="Review requests and view the complete request history for your listings." />
    <div class="px-4 pt-3 sm:px-6 lg:px-8">
        <x-record-toolbar :filter="$filter" :sort="$recordSort" :filters="['all' => 'All Statuses', 'requested' => 'Pending', 'awaiting_agreement' => 'Awaiting Agreement', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled']" placeholder="Search requests..." search-label="Search rental requests" label="Rental request controls" />
    </div>

    <div class="w-full px-4 pb-6 pt-3 sm:px-6 lg:px-8">
        @error('approve')
            <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $message }}</div>
        @enderror

        <div>
            @if ($filter === 'requested')
                <p class="mb-3 text-xs leading-5 text-slate-500"><strong>Review queue:</strong> {{ $recordSort === 'oldest' ? 'Oldest first.' : 'Newest first.' }} Select a request to send for agreement. Confirming holds its dates and declines overlapping requests. Approval follows both parties accepting.</p>
            @endif
            @if ($rentalRequests->isEmpty())
                <x-empty-state title="Nothing here" message="No rental requests match this filter right now." />
            @else
                <div class="record-list">
                    @foreach ($rentalRequests as $request)
                        <x-record-row :listing="$request->listing" wire:key="request-{{ $request->id }}">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <p class="text-sm font-semibold text-slate-800">{{ $request->listing->name }}</p>
                                <x-badge :color="$request->status->badgeColor()">{{ $request->status->label() }}</x-badge>
                            </div>
                            @if ($request->listing->trashed())
                                <p class="mt-0.5 text-xs text-slate-500">Listing removed</p>
                            @endif
                            <p class="mt-0.5 text-xs text-slate-500">Submitted {{ $request->created_at->timezone('Asia/Manila')->format('M d, Y · g:i A') }} (PHT)</p>
                            <p class="mt-0.5 text-xs text-slate-500"><x-rental-period :booking="$request" /></p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Renter: {{ $request->renter->name }}@unless ($request->renter->trashed()) ({{ $request->renter->email }})@endunless &middot; {{ ucfirst($request->fulfillment_method->value) }} &middot; Total &#8369;{{ number_format($request->total_amount, 2) }}
                            </p>
                            @if ($request->status->value === 'rejected' && $request->rejection_reason)
                                <p class="mt-0.5 text-xs text-rose-600">Reason: {{ $request->rejection_reason }}</p>
                            @endif
                            @if ($request->status->value === 'cancelled')
                                <x-cancellation-details :record="$request" />
                            @endif

                            @if ($request->isPending() || $request->rental || $request->canReviewAgreement())
                                <x-slot:actions>
                                    @if ($request->isPending())
                                        <x-record-action icon="check" label="Review & Confirm" tone="success" wire:click="startApproving({{ $request->id }})" wire:loading.attr="disabled" wire:target="startApproving({{ $request->id }}), startRejecting({{ $request->id }}), approve({{ $request->id }}), confirmReject" />
                                        <x-record-action icon="x-mark" label="Reject Request" tone="danger" wire:click="startRejecting({{ $request->id }})" wire:loading.attr="disabled" wire:target="startApproving({{ $request->id }}), startRejecting({{ $request->id }}), approve({{ $request->id }}), confirmReject" />
                                    @elseif ($request->rental)
                                        <x-record-action :href="route('owner.rentals.show', $request->rental)" icon="search" label="View Booking" wire:navigate />
                                    @elseif ($request->canReviewAgreement())
                                        <x-record-action :href="route('owner.rental-requests.agreement', $request)" icon="clipboard-list" label="Review Rental Agreement" wire:navigate />
                                    @endif
                                </x-slot:actions>
                            @endif

                            @if ($rejecting === $request->id)
                                <x-slot:footer>
                                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-3">
                                        <x-input-label required for="rejection_reason" value="Reason for rejection" />
                                        <textarea wire:model="rejection_reason" id="rejection_reason" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-rose-500 focus:ring-rose-500"></textarea>
                                        <x-input-error :messages="$errors->get('rejection_reason')" class="mt-2" />
                                        <div class="mt-3 flex flex-wrap justify-end gap-2">
                                            <button type="button" wire:click="$set('rejecting', null)" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">Cancel</button>
                                            <button type="button" wire:click="confirmReject" wire:loading.attr="disabled" wire:target="confirmReject" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                                                <span wire:loading.remove wire:target="confirmReject">Confirm Rejection</span>
                                                <span wire:loading wire:target="confirmReject">Rejecting&hellip;</span>
                                            </button>
                                        </div>
                                    </div>
                                </x-slot:footer>
                            @endif
                        </x-record-row>
                    @endforeach
                </div>
                <div class="mt-6">{{ $rentalRequests->links() }}</div>
            @endif
        </div>
    </div>
    @if ($approvalRequest)
        <dialog wire:key="approval-modal-{{ $approvalRequest->id }}" wire:ignore.self x-data x-init="$el.showModal()" x-on:cancel.prevent="$el.close()" x-on:close="$wire.cancelApproving()" x-on:click="if ($event.target === $el) { const bounds = $el.getBoundingClientRect(); if ($event.clientX < bounds.left || $event.clientX > bounds.right || $event.clientY < bounds.top || $event.clientY > bounds.bottom) $el.close(); }" aria-labelledby="approval-modal-title" aria-describedby="approval-modal-description" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-2xl border-0 bg-white p-6 text-slate-900 shadow-xl backdrop:bg-slate-900/50">
            <h2 id="approval-modal-title" class="text-lg font-bold">Confirm request for agreement</h2>
            <p id="approval-modal-description" class="mt-2 text-sm leading-6 text-slate-600">This holds the selected dates and sends the saved agreement for both parties to review. The request remains Awaiting agreement until both parties accept. No payment is taken now.</p>
            <dl class="mt-5 space-y-3 rounded-xl bg-slate-50 p-4 text-sm">
                <div><dt class="font-medium text-slate-500">Item</dt><dd class="mt-1 font-semibold">{{ $approvalRequest->listing->name }}</dd></div>
                <div><dt class="font-medium text-slate-500">Renter</dt><dd class="mt-1">{{ $approvalRequest->renter->name }}</dd></div>
                <div><dt class="font-medium text-slate-500">Rental period</dt><dd class="mt-1"><x-rental-period :booking="$approvalRequest" /></dd></div>
                <div><dt class="font-medium text-slate-500">Handover</dt><dd class="mt-1">{{ ucfirst($approvalRequest->fulfillment_method->value) }}</dd></div>
                <div><dt class="font-medium text-slate-500">Total</dt><dd class="mt-1 font-semibold">₱{{ number_format($approvalRequest->total_amount, 2) }}</dd></div>
            </dl>
            <p class="mt-4 text-sm text-amber-800">Overlapping pending requests will be declined when you confirm. Confirmation does not accept the agreement for either party.</p>
            <x-input-error :messages="$errors->get('approve')" class="mt-4" role="alert" />
            @unless ($approvalRequest->isPending())<p role="alert" class="mt-4 text-sm text-rose-700">This request is no longer pending. Close this dialog and refresh the list.</p>@endunless
            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <button type="button" x-on:click="$el.closest('dialog').close()" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700">Cancel</button>
                <button type="button" wire:click="approve({{ $approvalRequest->id }})" wire:loading.attr="disabled" wire:target="approve" @disabled(! $approvalRequest->isPending()) class="min-h-11 rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white disabled:opacity-60">Confirm &amp; Review Agreement</button>
            </div>
        </dialog>
    @endif
</div>
