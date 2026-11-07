<div class="renter-rental-detail rental-detail-page min-h-screen bg-slate-50" wire:poll.5s.keep-alive="refreshRental">
    <x-page-header eyebrow="Renting · My rentals" title="Rental details" :subtitle="'Rental #' . $rental->id . ' · ' . $rental->listing->name" />

    <div class="rental-detail-content w-full px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        @if (session('status'))<p role="status" class="mb-5 rounded-xl bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</p>@endif
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('renter.rentals.index') }}" wire:navigate class="inline-flex min-h-10 items-center gap-2 rounded-lg text-sm font-semibold text-slate-600 transition hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">
                <x-icon name="chevron-down" class="h-4 w-4 rotate-90" aria-hidden="true" /> Back to my rentals
            </a>
            <span class="text-xs font-medium text-slate-500">Rental #{{ $rental->id }}</span>
        </div>

        <x-booking-progress :rental="$rental" />
        <div class="rental-detail-grid">
            <aside class="renter-rental-sidebar" aria-label="Rented item and payment receipt">
                <section class="renter-rental-item rental-panel overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    @php $itemPhotos = $rental->listing->images; @endphp
                    @if ($itemPhotos->isNotEmpty())
                        <div x-data="{ selectedPhoto: @js($itemPhotos->first()->url()) }">
                            <div class="aspect-square max-h-[30rem] bg-slate-100 p-4">
                                <img src="{{ $itemPhotos->first()->url() }}" :src="selectedPhoto" alt="{{ $rental->listing->name }}" class="h-full w-full object-contain" />
                            </div>
                            @if ($itemPhotos->count() > 1)
                                <div class="flex flex-wrap gap-2 border-b border-slate-100 p-4" aria-label="Item photos">
                                    @foreach ($itemPhotos as $image)
                                        <button type="button" @click="selectedPhoto = @js($image->url())" :aria-pressed="selectedPhoto === @js($image->url())" :class="selectedPhoto === @js($image->url()) ? 'border-blue-500 ring-2 ring-blue-100' : 'border-slate-200'" aria-label="View item photo {{ $loop->iteration }}" class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border-2 bg-slate-50 p-1 transition hover:border-blue-300 focus:outline-none focus:ring-4 focus:ring-blue-100">
                                            <img src="{{ $image->url() }}" alt="{{ $rental->listing->name }} photo {{ $loop->iteration }}" class="h-full w-full object-contain" loading="lazy" />
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="flex aspect-square max-h-[30rem] flex-col items-center justify-center gap-3 bg-slate-100 text-slate-500">
                            <x-icon name="archive" class="h-12 w-12 text-slate-400" aria-hidden="true" />
                            <p class="text-sm">No item photo</p>
                        </div>
                    @endif
                    <div class="p-5 sm:p-6">
                        <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Your rented item</p>
                        <h2 class="mt-2 break-words text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $rental->listing->name }}</h2>
                        <div class="mt-3"><x-category-badge :category="$rental->listing->category" /></div>
                        <dl class="mt-5 space-y-3 border-t border-slate-100 pt-5 text-sm">
                            <div class="flex items-start justify-between gap-4"><dt class="text-slate-500">Location</dt><dd class="break-words text-right font-medium text-slate-700">{{ $rental->listing->location }}</dd></div>
                            <div class="flex items-start justify-between gap-4"><dt class="text-slate-500">Handover</dt><dd class="font-medium text-slate-700">{{ $rental->fulfillment_method->label() }}</dd></div>
                        </dl>
                        @if ($rental->listing->trashed())
                            <p class="mt-5 text-sm text-slate-500">This listing has been removed. Its rental record is retained here.</p>
                        @else
                            <a href="{{ route('listings.show', $rental->listing) }}" wire:navigate class="mt-5 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">View listing <span aria-hidden="true">↗</span></a>
                        @endif
                    </div>
                </section>

                @if ($rental->paid_at && $rental->payment?->status === 'paid')
                    <x-rental-panel title="Transaction receipt" icon="calculator" subtitle="Your payment and rental charges." class="renter-rental-receipt">
                        <div class="mt-5 flex flex-wrap justify-between gap-x-4 gap-y-1 text-sm">
                            <span class="text-slate-400">Transaction ID</span>
                            <span class="font-mono font-medium text-slate-800">{{ $rental->payment->transaction_reference }}</span>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 border-t border-slate-100 pt-4 text-sm">
                            <div>
                                <p class="text-slate-400">Renter</p>
                                <p class="font-medium text-slate-800">{{ $rental->renter->name }}</p>
                            </div>
                            <div>
                                <p class="text-slate-400">Owner</p>
                                <p class="font-medium text-slate-800">{{ $rental->owner->name }}</p>
                            </div>
                            <div>
                                <p class="text-slate-400">Item</p>
                                <p class="font-medium text-slate-800">{{ $rental->listing->name }}</p>
                            </div>
                            <div>
                                <p class="text-slate-400">Rental dates</p>
                                <p class="font-medium text-slate-800">{{ $rental->start_date->format('M d, Y') }} &ndash; {{ $rental->end_date->format('M d, Y') }}</p>
                            </div>
                        </div>

                        <dl class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                <dt class="text-slate-500">Rental fee</dt>
                                <dd class="font-medium text-slate-800">₱{{ number_format($rental->rental_fee, 2) }}</dd>
                            </div>
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                <dt class="text-slate-500">Processing fee</dt>
                                <dd class="font-medium text-slate-800">₱{{ number_format($rental->commission_amount, 2) }}</dd>
                            </div>
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                <dt class="text-slate-500">Security deposit</dt>
                                <dd class="font-medium text-slate-800">₱{{ number_format($rental->security_deposit, 2) }}</dd>
                            </div>
                            @if ($rental->currentLateFee() > 0)
                                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 text-rose-600">
                                    <dt>Late fee</dt>
                                    <dd class="font-medium">₱{{ number_format($rental->currentLateFee(), 2) }}</dd>
                                </div>
                            @endif
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 border-t border-slate-100 pt-2 text-base">
                                <dt class="font-semibold text-slate-900">Total paid</dt>
                                <dd class="font-semibold text-slate-900">₱{{ number_format($rental->payment->amount, 2) }}</dd>
                            </div>
                        </dl>

                        <p class="mt-4 text-xs text-slate-400">Paid on {{ $rental->paid_at->copy()->setTimezone('Asia/Manila')->format('M d, Y \a\t g:i A') }} (Philippine time)</p>
                        @if ($rental->payment->method)
                            <p class="mt-2 text-xs text-slate-500">Verified {{ \App\Models\OfflinePaymentSetting::methodLabel($rental->payment->method) }} · Reference: {{ $rental->payment->external_reference }}</p>
                        @endif
                        @if (($rental->payment_destination['source'] ?? null) === 'owner_wallet')
                            <p class="mt-3 text-xs leading-5 text-slate-500">The owner received the full booking amount and holds the security deposit. Any deposit refund is arranged manually with the owner after return and inspection.</p>
                        @endif
                    </x-rental-panel>
                @endif
            </aside>

            <div class="renter-rental-actions">
                <livewire:rentals.exchange-schedule :rental="$rental" :key="'renter-exchange-'.$rental->id" />
                <section class="renter-rental-overview rental-panel rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Rental overview</p><h2 class="mt-2 text-xl font-bold tracking-tight text-slate-900">Booking details</h2></div>
                        <x-badge :color="$rental->displayStatusColor()">{{ $rental->displayStatusLabel() }}</x-badge>
                    </div>
                    @if ($rental->archived_at)
                        <p class="mt-3 text-sm text-slate-500">Archived on {{ $rental->archived_at->format('M d, Y') }}. This completed transaction remains in your rental history.</p>
                    @endif
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-5">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600"><x-icon name="user-circle" class="h-6 w-6" aria-hidden="true" /></span>
                            <div class="min-w-0"><p class="text-xs text-slate-500">Owned by</p><p class="mt-1 break-words text-sm font-semibold text-slate-800">{{ $rental->owner->name }}</p>
                                @if (! $rental->owner->trashed() && ! $rental->owner->isAdmin() && ! $rental->owner->isSuspended() && $rental->owner->hasVerifiedEmail())
                                    <a href="{{ route('users.show', $rental->owner) }}" wire:navigate class="text-xs font-semibold text-blue-700 hover:underline">View public profile</a>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('rental-requests.chat', $rental->rental_request_id) }}" wire:navigate class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100"><x-icon name="chat" class="h-4 w-4" aria-hidden="true" /> Message owner</a>
                    </div>
                    <dl class="rental-detail-dates mt-5 grid gap-4">
                        <div><dt class="text-xs text-slate-500">Start date</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $rental->start_date->format('M d, Y') }}</dd></div>
                        <div><dt class="text-xs text-slate-500">End date</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $rental->end_date->format('M d, Y') }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Duration</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $rental->rental_unit === 'hour' ? $rental->rental_hours.' hours' : $rental->rental_days.' days' }}</dd></div>
                    </dl>
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3"><p class="text-sm font-medium text-blue-700">Total amount</p><p class="text-xl font-bold tracking-tight text-blue-700">₱{{ number_format($rental->total_amount, 2) }}</p></div>
                </section>

                @if ($rental->isPaymentPending())
                    <x-rental-panel title="Payment summary" icon="calculator" subtitle="Complete an offline payment and submit proof for verification.">
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                <dt class="text-slate-500">Rental fee</dt>
                                <dd class="font-medium text-slate-800">₱{{ number_format($rental->rental_fee, 2) }}</dd>
                            </div>
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                <dt class="text-slate-500">Processing fee</dt>
                                <dd class="font-medium text-slate-800">₱{{ number_format($rental->commission_amount, 2) }}</dd>
                            </div>
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                <dt class="text-slate-500">Security deposit (refundable)</dt>
                                <dd class="font-medium text-slate-800">₱{{ number_format($rental->security_deposit, 2) }}</dd>
                            </div>
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 border-t border-slate-100 pt-2 text-base">
                                <dt class="font-semibold text-slate-900">Total</dt>
                                <dd class="font-semibold text-slate-900">₱{{ number_format($rental->total_amount, 2) }}</dd>
                            </div>
                        </dl>

                        @php $pendingPayment = $rental->paymentSubmissions->firstWhere('status', 'pending'); @endphp
                        @if ($pendingPayment)
                            <div role="status" class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">
                                @if (($pendingPayment->payment_destination['source'] ?? null) === 'owner_wallet')
                                    @if ($pendingPayment->recipient_confirmed_at)
                                        Your payment proof is awaiting the owner's final receipt confirmation. Please do not pay again.
                                    @else
                                        Your payment proof is awaiting owner confirmation. Please do not pay again. Hand-over becomes available once the owner confirms the full amount.
                                    @endif
                                @else
                                    Your payment proof is awaiting verification. Please do not pay again. Hand-over becomes available after an admin verifies receipt of the full booking amount.
                                @endif
                            </div>
                        @elseif ($paymentDestination && auth()->user()->can('pay', $rental))
                            <p class="mt-4 text-sm font-semibold text-slate-800">Do you want to pay now?</p>
                            <p class="mt-1 text-sm text-slate-500">You can return later before the payment deadline. Check the recipient and amount before transferring.</p>
                            <details class="mt-3 rounded-xl border border-blue-100 p-4" open>
                                <summary class="cursor-pointer rounded text-sm font-semibold text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">Pay now — view payment instructions</summary>
                            @php $selectedWallet = $paymentWallets->first(fn ($wallet) => $wallet->qr_path === ($paymentDestination['qr_path'] ?? null) && $wallet->provider === $paymentDestination['method'] && $wallet->account_name === ($paymentDestination['account_name'] ?? null)); @endphp
                            @if ($paymentWallets->count() > 1 || ($paymentWallets->isNotEmpty() && ! $selectedWallet))
                                <div class="mt-4">
                                    <x-input-label for="payment-wallet" value="Pay with an owner's e-wallet" />
                                    <select id="payment-wallet" wire:change="selectWallet($event.target.value)" wire:loading.attr="disabled" wire:target="selectWallet,submitPaymentProof" class="mt-2 min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                        @if (! $selectedWallet)
                                            <option value="" selected disabled>Current booking QR ({{ \App\Models\OfflinePaymentSetting::methodLabel($paymentDestination['method']) }})</option>
                                        @endif
                                        @foreach ($paymentWallets as $wallet)
                                            <option value="{{ $wallet->id }}" @selected($selectedWallet?->id === $wallet->id)>{{ $wallet->providerLabel() }} — {{ $wallet->account_name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1.5 text-xs leading-5 text-slate-500">Choose before transferring. The wallet cannot be changed after you submit payment proof.</p>
                                    <x-input-error :messages="$errors->get('payment_wallet')" class="mt-2" />
                                </div>
                            @endif
                            <div wire:key="booking-payment-qr-{{ md5(json_encode($paymentDestination)) }}">
                                <x-payment-destination :destination="$paymentDestination" :rental="$rental" />
                            </div>
                            @if (($paymentDestination['source'] ?? null) === 'owner_wallet')
                                <p class="mt-3 text-sm leading-6 text-slate-600">Open your e-wallet app and scan or import this QR. Confirm the recipient account name and the full total above before paying. Complete the payment in your e-wallet, then upload its transfer confirmation below. The owner confirms the full amount to mark the booking paid. No admin approval is required. Submitting proof does not mark this booking paid.</p>
                            @else
                                <p class="mt-3 text-sm text-slate-500">Pay the full total above using these instructions. Upload the transfer confirmation or official cash receipt. Proof submission does not mark this booking paid.</p>
                            @endif
                            <form wire:submit="submitPaymentProof" class="mt-4 space-y-3">
                                <div><x-input-label required for="payment-reference" value="Transfer or receipt reference" /><x-text-input id="payment-reference" wire:model="payment_reference" maxlength="100" class="mt-1 w-full" /><x-input-error :messages="$errors->get('payment_reference')" /></div>
                                <div><x-input-label required for="payment-proof" value="Payment proof (JPG, PNG or PDF, up to 5 MB)" /><input id="payment-proof" type="file" wire:model="payment_proof" accept="image/jpeg,image/png,application/pdf" class="mt-2 block w-full text-sm" /><x-input-error :messages="$errors->get('payment_proof')" /></div>
                                <p wire:loading wire:target="payment_proof" class="text-sm text-slate-500">Uploading proof…</p>
                                <x-primary-button wire:loading.attr="disabled">Submit payment proof</x-primary-button>
                            </form>
                            </details>
                            <a href="{{ route('renter.rentals.index') }}" wire:navigate class="mt-3 inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-slate-600 focus:outline-none focus:ring-4 focus:ring-blue-100">Pay later — return to my rentals</a>
                        @else
                            <p class="mt-4 text-sm text-slate-600">{{ ($rental->rentalRequest?->agreement_terms['payment_review'] ?? null) === 'owner' ? 'Ask the owner to enable their Payment QR before sending funds. Payment is available after both parties accept the agreement.' : 'Payment is unavailable until support provides the legacy payment instructions and the booking agreement is confirmed. Contact support before sending any funds.' }}</p>
                        @endif

                        @if ($rental->isCancellableByRenter() && $rental->cancellationRequest?->status !== 'pending')
                            <button type="button" wire:click="$toggle('showCancelForm')" class="mt-3 w-full text-center text-xs font-medium text-slate-500 hover:text-slate-700">
                                Cancel this booking instead
                            </button>
                        @endif
                    </x-rental-panel>

                    @include('livewire.renter.rentals._cancel-form')
                @else
                    @if ($rental->isOverdue())
                        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm leading-6 text-rose-700" role="status">
                            <p class="font-semibold">@if ($rental->rental_unit === 'hour')This rental is overdue. Late fees apply per started hour.@else This rental is overdue by {{ $rental->currentOverdueDays() }} day{{ $rental->currentOverdueDays() === 1 ? '' : 's' }}.@endif</p>
                            <p class="mt-1">Late fee so far: ₱{{ number_format($rental->currentLateFee(), 2) }}</p>
                        </div>
                    @endif

                    @if ($rental->isCancelled())
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm leading-6" role="status">
                            <p class="font-semibold text-slate-700">This booking was cancelled.</p>
                            <x-cancellation-details :record="$rental" />
                        </div>
                    @elseif ($rental->isCancellableByRenter() && $rental->cancellationRequest?->status !== 'pending')
                        <x-rental-panel title="Booking options" icon="x-mark" subtitle="Review any cancellation fees before cancelling.">
                            <button type="button" wire:click="$toggle('showCancelForm')" class="mt-5 inline-flex min-h-10 items-center rounded-lg border border-rose-200 px-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-4 focus:ring-rose-100">
                                Cancel this booking
                            </button>
                        </x-rental-panel>

                        @include('livewire.renter.rentals._cancel-form')
                    @endif

                    @if ($rental->awaitingPickupConfirmation())
                        <x-rental-panel :title="$rental->fulfillment_method->label().' confirmation'" icon="inbox" subtitle="Both you and the owner need to confirm the handover before the rental starts.">
                            <ul class="mt-4 space-y-2 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm">
                                <li class="{{ $rental->pickup_confirmed_by_owner_at ? 'text-blue-700' : 'text-slate-500' }}">
                                    {{ $rental->pickup_confirmed_by_owner_at ? '✓ Owner confirmed' : 'Waiting on owner confirmation' }}
                                </li>
                                <li class="{{ $rental->pickup_confirmed_by_renter_at ? 'text-blue-700' : 'text-slate-500' }}">
                                    {{ $rental->pickup_confirmed_by_renter_at ? '✓ You confirmed' : 'Waiting on your confirmation' }}
                                </li>
                            </ul>

                            @if (! $rental->pickup_confirmed_by_renter_at)
                                <button type="button" wire:click="confirmPickup" wire:loading.attr="disabled" wire:target="confirmPickup" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:cursor-wait disabled:opacity-60">
                                    {{ $rental->fulfillment_method->value === 'delivery' ? 'Confirm delivery received' : 'Confirm I picked up the item' }}
                                </button>
                            @endif
                        </x-rental-panel>
                    @endif

                    @if ($rental->awaitingReturnConfirmation())
                        <x-rental-panel title="Return confirmation" icon="archive" subtitle="The owner confirms receipt and records the returned condition to close the rental. You can also acknowledge your return below.">
                            <ul class="mt-4 space-y-2 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm">
                                <li class="{{ $rental->return_confirmed_by_owner_at ? 'text-blue-700' : 'text-slate-500' }}">
                                    {{ $rental->return_confirmed_by_owner_at ? '✓ Owner confirmed' : 'Waiting on owner confirmation' }}
                                </li>
                                <li class="{{ $rental->return_confirmed_by_renter_at ? 'text-blue-700' : 'text-slate-500' }}">
                                    {{ $rental->return_confirmed_by_renter_at ? '✓ You confirmed' : 'Your acknowledgment is optional' }}
                                </li>
                            </ul>

                            @if (! $rental->return_confirmed_by_renter_at)
                                <button type="button" wire:click="confirmReturn" wire:loading.attr="disabled" wire:target="confirmReturn" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:cursor-wait disabled:opacity-60">
                                    Confirm I returned the item
                                </button>
                            @endif
                        </x-rental-panel>
                    @endif

                    @if ($rental->isReturned() && ! $rental->afterConditionRecord)
                        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm leading-6 text-blue-700" role="status">
                            Item returned. Waiting for the owner to record the returned condition; the rental will then close automatically.
                        </div>
                    @endif

                    @if ($rental->beforeConditionRecord || $rental->afterConditionRecord)
                        <div class="space-y-6">
                            @if ($rental->beforeConditionRecord)
                                <x-rental-panel title="Condition before rental" icon="clipboard-list">
                                    <p class="mt-2 text-sm text-slate-600">{{ $rental->beforeConditionRecord->condition->label() }}</p>
                                    @if ($rental->beforeConditionRecord->notes)
                                        <p class="mt-1 text-sm text-slate-500">{{ $rental->beforeConditionRecord->notes }}</p>
                                    @endif
                                    @if ($rental->beforeConditionRecord->photos->isNotEmpty())
                                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                            @foreach ($rental->beforeConditionRecord->photos as $photo)
                                                <img src="{{ $photo->url() }}" alt="Rental evidence photo {{ $loop->iteration }}" loading="lazy" class="aspect-square w-full rounded-lg object-cover">
                                            @endforeach
                                        </div>
                                    @endif
                                </x-rental-panel>
                            @endif

                            @if ($rental->afterConditionRecord)
                                <x-rental-panel title="Condition after rental" icon="clipboard-list">
                                    <p class="mt-2 text-sm text-slate-600">{{ $rental->afterConditionRecord->condition->label() }}</p>
                                    @if ($rental->afterConditionRecord->notes)
                                        <p class="mt-1 text-sm text-slate-500">{{ $rental->afterConditionRecord->notes }}</p>
                                    @endif
                                    @if ($rental->afterConditionRecord->photos->isNotEmpty())
                                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                            @foreach ($rental->afterConditionRecord->photos as $photo)
                                                <img src="{{ $photo->url() }}" alt="Rental evidence photo {{ $loop->iteration }}" loading="lazy" class="aspect-square w-full rounded-lg object-cover">
                                            @endforeach
                                        </div>
                                    @endif
                                </x-rental-panel>
                            @endif
                        </div>
                    @endif

                    @if ($rental->securityDeposit)
                        <x-rental-panel title="Security deposit" icon="calculator">
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <x-badge :color="$rental->securityDeposit->status->badgeColor()">{{ $rental->securityDeposit->status->label() }}</x-badge>
                                <span class="text-sm text-slate-500">₱{{ number_format($rental->securityDeposit->amount, 2) }} deposit amount</span>
                            </div>

                            @if ($rental->damageReport)
                                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                                    <p class="text-sm font-semibold text-amber-800">Damage claim: {{ $rental->damageReport->damage_type }}</p>
                                    <p class="mt-1 text-sm text-amber-700">{{ $rental->damageReport->description }}</p>
                                    <p class="mt-1 text-sm text-amber-700">
                                        Estimated repair: ₱{{ number_format($rental->damageReport->estimated_repair_cost, 2) }} &middot;
                                        Proposed deduction: ₱{{ number_format($rental->damageReport->proposed_deduction, 2) }}
                                    </p>

                                    @if ($rental->damageReport->photos->isNotEmpty())
                                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                            @foreach ($rental->damageReport->photos as $photo)
                                                <img src="{{ $photo->url() }}" alt="Rental evidence photo {{ $loop->iteration }}" loading="lazy" class="aspect-square w-full rounded-lg object-cover">
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($rental->damageReport->isPending() && $rental->isCompleted())
                                        <div class="mt-4 space-y-3">
                                            <button type="button" wire:click="acceptDamageClaim" wire:loading.attr="disabled" wire:target="acceptDamageClaim" wire:confirm="Accept this claim? ₱{{ number_format($rental->damageReport->proposed_deduction, 2) }} will be deducted from your deposit." class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:cursor-wait disabled:opacity-60">
                                                Accept claim
                                            </button>

                                            <div>
                                                <x-input-label required for="damage_response_notes" value="Or dispute it — explain why" />
                                                <textarea wire:model="damage_response_notes" id="damage_response_notes" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-rose-500 focus:ring-rose-500"></textarea>
                                                <x-input-error :messages="$errors->get('damage_response_notes')" class="mt-2" />
                                                <button type="button" wire:click="disputeDamageClaim" wire:loading.attr="disabled" wire:target="disputeDamageClaim" class="mt-3 inline-flex min-h-11 items-center justify-center rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-4 focus:ring-rose-100 disabled:opacity-60">
                                                    Dispute claim
                                                </button>
                                            </div>
                                        </div>
                                    @elseif ($rental->damageReport->status->value === 'disputed')
                                        <p class="mt-3 text-sm text-amber-700">You disputed this claim. An admin will review it.</p>
                                    @elseif ($rental->damageReport->status->value === 'accepted')
                                        <p class="mt-3 text-sm text-amber-700">
                                            You accepted this claim. Refund: ₱{{ number_format($rental->securityDeposit->amount - $rental->securityDeposit->deducted_amount, 2) }}
                                        </p>
                                    @endif
                                </div>
                            @elseif ($rental->securityDeposit->status->value === 'released')
                                <p class="mt-3 text-sm text-slate-600">The owner recorded release of your deposit. Confirm below only after the money reaches you.</p>
                            @elseif ($rental->securityDeposit->status->value === 'return_eligible')
                                <p class="mt-3 text-sm text-slate-600">No damage reported. Waiting for the owner to release your deposit.</p>
                            @endif
                            @if ($rental->securityDeposit->received_at)
                                <p role="status" class="mt-4 text-sm font-semibold text-emerald-700">Receipt confirmed {{ $rental->securityDeposit->received_at->setTimezone('Asia/Manila')->format('M d, Y g:i A') }} (Philippine time).</p>
                            @else
                                @can('confirmDepositReceipt', $rental)
                                    <x-action-confirmation id="deposit-receipt" title="Confirm deposit received" message="Have you actually received the refundable deposit balance? This records receipt; it does not transfer money." action="confirmDepositReceipt" confirm-label="Confirm deposit received">
                                        <button type="button" x-on:click="$refs.confirmation.showModal()" wire:loading.attr="disabled" wire:target="confirmDepositReceipt" class="mt-4 min-h-11 rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white">Confirm deposit received</button>
                                    </x-action-confirmation>
                                @endcan
                            @endif
                        </x-rental-panel>
                    @endif

                    @if ($rental->isCompleted() && auth()->id() === $rental->renter_id && $rental->owner_id !== $rental->renter_id)
                        <div id="reviews" class="renter-rental-reviews grid gap-6">
                            <x-rental-panel title="Rate the owner" icon="star" subtitle="Optional. Your rating and comment will be published on the owner's public profile.">
                                @if ($rental->reviewFromRenterToOwner)
                                    <p class="mt-2 text-sm text-slate-600">You rated {{ $rental->reviewFromRenterToOwner->rating }}/5 stars</p>
                                    @if ($rental->reviewFromRenterToOwner->comment)
                                        <p class="mt-1 text-sm text-slate-500">"{{ $rental->reviewFromRenterToOwner->comment }}"</p>
                                    @endif
                                @else
                                    <form wire:submit="submitOwnerReview" class="mt-3 space-y-3">
                                        <x-input-label required for="owner_rating" value="Rating" />
                                        <select id="owner_rating" wire:model="owner_rating" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            @for ($i = 5; $i >= 1; $i--)
                                                <option value="{{ $i }}">{{ $i }} star{{ $i === 1 ? '' : 's' }}</option>
                                            @endfor
                                        </select>
                                        <x-input-error :messages="$errors->get('owner_rating')" />
                                        <x-input-label for="owner_comment" value="Comment (optional)" />
                                        <textarea id="owner_comment" wire:model="owner_comment" rows="2" placeholder="Optional comment" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                        <x-input-error :messages="$errors->get('owner_comment')" />
                                        <x-primary-button class="min-h-11 rounded-xl" wire:loading.attr="disabled">Submit rating</x-primary-button>
                                    </form>
                                @endif
                            </x-rental-panel>

                            <x-rental-panel title="Rate the item" icon="star">
                                @if ($rental->reviewFromRenterToListing)
                                    <p class="mt-2 text-sm text-slate-600">You rated {{ $rental->reviewFromRenterToListing->rating }}/5 stars</p>
                                    @if ($rental->reviewFromRenterToListing->comment)
                                        <p class="mt-1 text-sm text-slate-500">"{{ $rental->reviewFromRenterToListing->comment }}"</p>
                                    @endif
                                @else
                                    <form wire:submit="submitListingReview" class="mt-3 space-y-3">
                                        <x-input-label required for="listing_rating" value="Rating" />
                                        <select id="listing_rating" wire:model="listing_rating" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            @for ($i = 5; $i >= 1; $i--)
                                                <option value="{{ $i }}">{{ $i }} star{{ $i === 1 ? '' : 's' }}</option>
                                            @endfor
                                        </select>
                                        <x-input-error :messages="$errors->get('listing_rating')" />
                                        <x-input-label for="listing_comment" value="Comment (optional)" />
                                        <textarea id="listing_comment" wire:model="listing_comment" rows="2" placeholder="Optional comment" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                        <x-input-error :messages="$errors->get('listing_comment')" />
                                        <x-primary-button class="min-h-11 rounded-xl" wire:loading.attr="disabled">Submit rating</x-primary-button>
                                    </form>
                                @endif
                            </x-rental-panel>
                        </div>
                    @endif

                    <x-rental-panel title="Disputes" icon="chat" subtitle="Keep any issues and resolutions in one place.">
                        @if (session('status'))
                            <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">{{ session('status') }}</div>
                        @endif

                        @forelse ($rental->disputes as $dispute)
                            <div class="mb-3 rounded-lg border border-slate-200 p-3" wire:key="dispute-{{ $dispute->id }}">
                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-slate-800">{{ $dispute->reason->label() }}</p>
                                    <x-badge :color="$dispute->status->badgeColor()">{{ $dispute->status->label() }}</x-badge>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">{{ $dispute->description }}</p>
                                @if ($dispute->status->value === 'resolved')
                                    <p class="mt-1 text-sm text-blue-700">Resolution: {{ $dispute->resolutionLabel() }}</p>
                                    @if ($dispute->resolution_notes)
                                        <p class="mt-1 text-sm text-slate-500">{{ $dispute->resolution_notes }}</p>
                                    @endif
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">No disputes filed for this rental.</p>
                        @endforelse

                        @if (! $showDisputeForm)
                            <button type="button" wire:click="$set('showDisputeForm', true)" class="mt-4 inline-flex min-h-10 items-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100">
                                Report an issue
                            </button>
                        @else
                            <form wire:submit="createDispute" class="mt-3 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div>
                                    <x-input-label required for="dispute_reason" value="Reason" />
                                    <select wire:model="dispute_reason" id="dispute_reason" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">Select a reason</option>
                                        @foreach (\App\Enums\DisputeReason::cases() as $reason)
                                            <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('dispute_reason')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label required for="dispute_description" value="Describe what happened" />
                                    <textarea wire:model="dispute_description" id="dispute_description" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                    <x-input-error :messages="$errors->get('dispute_description')" class="mt-2" />
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <x-primary-button class="min-h-11 rounded-xl" wire:loading.attr="disabled">Submit dispute</x-primary-button>
                                    <button type="button" wire:click="$set('showDisputeForm', false)" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</button>
                                </div>
                            </form>
                        @endif
                    </x-rental-panel>

                @endif

                @if ($rental->paymentSubmissions->isNotEmpty())
                    <x-rental-panel title="Payment proof history" icon="calculator">
                        @foreach ($rental->paymentSubmissions as $submission)
                            <div class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm" wire:key="renter-proof-{{ $submission->id }}">
                                <p class="font-semibold">{{ ucfirst($submission->status) }} · {{ $submission->external_reference }} · ₱{{ number_format($submission->amount, 2) }}</p>
                                <a href="{{ route('payment-proofs.show', $submission) }}" class="font-semibold text-blue-700">Download submitted proof</a>
                                @if ($submission->review_notes)<p class="whitespace-pre-line text-slate-600">{{ $submission->review_notes }}</p>@endif
                                @if ($submission->status === 'rejected' && $rental->isPaymentPending())<p class="text-slate-500">Correct the issue described above and submit new proof. Confirm whether funds were received before making another payment.</p>@endif
                            </div>
                        @endforeach
                    </x-rental-panel>
                @endif
            </div>
        </div>
    </div>
</div>
