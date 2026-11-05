<div class="owner-rental-detail rental-detail-page min-h-screen bg-slate-50" wire:poll.5s.keep-alive="refreshRental">
    <x-page-header eyebrow="Owning · My rentals" title="Manage rental" :subtitle="'Rental #' . $rental->id . ' · ' . $rental->listing->name" />

    <div class="owner-rental-content w-full px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('owner.rentals.index') }}" wire:navigate class="inline-flex min-h-10 items-center gap-2 rounded-lg text-sm font-semibold text-slate-600 transition hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">
                <x-icon name="chevron-down" class="h-4 w-4 rotate-90" aria-hidden="true" /> Back to my rentals
            </a>
            <span class="text-xs font-medium text-slate-500">Rental #{{ $rental->id }}</span>
        </div>

        <x-booking-progress :rental="$rental" :owner="true" :reviewer="true" />
        <div class="owner-rental-grid">
            <aside class="min-w-0 space-y-6" aria-label="Rented item and earnings">
                <section class="owner-rental-panel overflow-hidden rounded-2xl border border-slate-200 bg-white">
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
                        <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Your listed item</p>
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

                @if ($rental->paid_at)
                    @if ($rental->payment)
                        <x-rental-panel title="Transaction receipt" icon="calculator">
                            <p class="mt-4 break-all font-mono text-sm">{{ $rental->payment->transaction_reference }}</p>
                            <p class="mt-1 text-sm text-slate-500">Paid {{ $rental->payment->paid_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Not recorded' }} (Philippine time)</p>
                            <p class="mt-2 text-sm">Owner: {{ $rental->owner->name }} · Renter: {{ $rental->renter->name }}</p>
                            <p class="mt-2 font-semibold">Payment total: ₱{{ number_format($rental->payment->amount, 2) }}</p>
                            @if (($rental->payment_destination['source'] ?? null) === 'owner_wallet')
                                <p class="mt-3 text-sm leading-6 text-slate-600">You received the full booking amount in your e-wallet, including the processing fee and refundable security deposit. You hold the deposit and arrange any refund manually after return and inspection. Remit the processing fee manually; it is not automatically deducted from your wallet.</p>
                            @endif
                        </x-rental-panel>
                    @else
                        <p class="text-sm text-slate-500">No receipt is available for this payment.</p>
                    @endif
                    <x-rental-panel title="Earnings breakdown" icon="calculator" subtitle="Rental income and the refundable deposit.">
                        <dl class="mt-5 space-y-4 text-sm">
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                <dt class="text-slate-500">Net rental earnings</dt>
                                <dd class="font-medium text-slate-800">₱{{ number_format($rental->ownerEarnings(), 2) }}</dd>
                            </div>
                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                <dt class="text-slate-500">Security deposit (not rental earnings)</dt>
                                <dd class="font-medium text-slate-800">₱{{ number_format($rental->security_deposit, 2) }}</dd>
                            </div>
                            @if ($rental->currentLateFee() > 0)
                                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 text-rose-600">
                                    <dt>Late fee charged to renter</dt>
                                    <dd class="font-medium">₱{{ number_format($rental->currentLateFee(), 2) }}</dd>
                                </div>
                            @endif
                        </dl>
                    </x-rental-panel>
                @endif
            </aside>

            <div class="min-w-0 space-y-6">
                <livewire:rentals.exchange-schedule :rental="$rental" :key="'owner-exchange-'.$rental->id" />
                <section class="owner-rental-panel rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
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
                            <div class="min-w-0"><p class="text-xs text-slate-500">Rented by</p><p class="mt-1 break-words text-sm font-semibold text-slate-800">{{ $rental->renter->name }}</p>
                                @if (! $rental->renter->trashed() && ! $rental->renter->isAdmin() && ! $rental->renter->isSuspended() && $rental->renter->hasVerifiedEmail())
                                    <a href="{{ route('users.show', $rental->renter) }}" wire:navigate class="text-xs font-semibold text-blue-700 hover:underline">View public profile</a>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('rental-requests.chat', $rental->rental_request_id) }}" wire:navigate class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100"><x-icon name="chat" class="h-4 w-4" aria-hidden="true" /> Message renter</a>
                    </div>
                    <dl class="owner-rental-dates mt-5 grid gap-4">
                        <div><dt class="text-xs text-slate-500">Start date</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $rental->start_date->format('M d, Y') }}</dd></div>
                        <div><dt class="text-xs text-slate-500">End date</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $rental->end_date->format('M d, Y') }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Duration</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $rental->rental_unit === 'hour' ? $rental->rental_hours.' hours' : $rental->rental_days.' days' }}</dd></div>
                    </dl>
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3"><p class="text-sm font-medium text-blue-700">Your earnings</p><p class="text-xl font-bold tracking-tight text-blue-700">₱{{ number_format($rental->ownerEarnings(), 2) }}</p></div>
                </section>

                @if ($rental->isPaymentPending())
                    @if (($rental->payment_destination['source'] ?? null) === 'owner_wallet')
                        <x-rental-panel title="Confirm rental payment" icon="calculator" subtitle="Check your e-wallet balance and transaction history before confirming receipt.">
                            @forelse ($rental->paymentSubmissions->where('status', 'pending')->filter(fn ($submission) => ($submission->payment_destination['source'] ?? null) === 'owner_wallet') as $submission)
                                <div class="mt-4 space-y-4 border-t border-slate-100 pt-4 text-sm" wire:key="owner-payment-{{ $submission->id }}">
                                    <x-payment-destination :destination="$submission->payment_destination" :rental="$rental" :show-qr="false" />
                                    <dl class="space-y-2">
                                        <div class="flex flex-wrap justify-between gap-2"><dt class="text-slate-500">Full amount submitted</dt><dd class="font-semibold text-slate-900">₱{{ number_format($submission->amount, 2) }}</dd></div>
                                        <div><dt class="text-slate-500">Transfer reference</dt><dd class="break-all font-medium text-slate-800">{{ $submission->external_reference }}</dd></div>
                                    </dl>
                                    <a href="{{ route('payment-proofs.show', $submission) }}" class="inline-flex min-h-11 items-center rounded-lg font-semibold text-blue-700 hover:underline focus:outline-none focus:ring-4 focus:ring-blue-100">Download submitted proof</a>

                                    @if ((int) ($submission->payment_destination['payee_user_id'] ?? 0) === auth()->id())
                                        <x-action-confirmation :id="'payment-receipt-'.$submission->id" title="Confirm payment received" message="Confirm that the full booking amount has arrived in your e-wallet? This will mark the booking paid." :action="'confirmPaymentReceipt('.$submission->id.')'" confirm-label="Confirm funds received">
                                        <form x-on:submit.prevent="$refs.confirmation.showModal()" class="space-y-3">
                                            <label class="flex items-start gap-3 leading-6 text-slate-700">
                                                <input type="checkbox" wire:model="owner_funds_received" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                                <span>I checked my e-wallet and received the full ₱{{ number_format($submission->amount, 2) }} for this booking.</span>
                                            <x-required-mark /></label>
                                            <x-input-error :messages="$errors->get('owner_funds_received')" />
                                            <x-input-error :messages="$errors->get('funds_received')" />
                                            <x-input-error :messages="$errors->get('payment')" />
                                            <p class="text-slate-500">Confirm only after checking that the full payment reached your account. This marks the booking paid without admin approval.</p>
                                            <x-primary-button wire:loading.attr="disabled" wire:target="confirmPaymentReceipt">Confirm funds received</x-primary-button>
                                        </form>
                                        </x-action-confirmation>
                                        @if (! $submission->recipient_confirmed_at)
                                        <form wire:submit="rejectPaymentProof({{ $submission->id }})" class="mt-4 space-y-3 border-t border-slate-100 pt-4" wire:confirm="Reject this proof and ask the renter to correct it? Only reject when the full payment has not been received.">
                                            <x-input-label required :for="'payment-rejection-'.$submission->id" value="Reason payment proof needs correction" />
                                            <textarea id="payment-rejection-{{ $submission->id }}" wire:model="payment_rejection_reason" required maxlength="1000" class="w-full rounded-lg border-slate-300"></textarea>
                                            <x-input-error :messages="$errors->get('payment_rejection_reason')" />
                                            <x-secondary-button type="submit" wire:loading.attr="disabled">Request corrected proof</x-secondary-button>
                                        </form>
                                        @endif
                                    @endif
                                </div>
                            @empty
                                <p role="status" class="mt-4 rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-800">Waiting for the renter to submit payment proof. Acknowledge receipt only after the full booking amount reaches your e-wallet. Your confirmation marks the booking paid.</p>
                            @endforelse
                        </x-rental-panel>
                    @else
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-800" role="status">
                            @if (($rental->rentalRequest?->agreement_terms['payment_review'] ?? null) === 'owner')
                                Add or enable your payment QR so the renter can pay you directly. You will confirm the payment yourself.
                                <a href="{{ route('owner.payments.settings') }}" wire:navigate class="font-semibold underline">Manage Payment QR</a>
                            @else
                                This legacy booking uses the saved platform payment instructions. Its existing collection record remains available for review.
                            @endif
                        </div>
                    @endif
                @endif

                @if ($rental->isOverdue())
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm leading-6 text-rose-800" role="status">
                        <p class="font-semibold">@if ($rental->rental_unit === 'hour')This rental is overdue. Late fees apply per started hour.@else This rental is overdue by {{ $rental->currentOverdueDays() }} day{{ $rental->currentOverdueDays() === 1 ? '' : 's' }}.@endif</p>
                        <p class="mt-1">Late fee so far: ₱{{ number_format($rental->currentLateFee(), 2) }}</p>
                    </div>
                @endif

                @if ($rental->isCancelled())
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm leading-6" role="status">
                        <p class="font-semibold text-slate-700">The renter cancelled this booking.</p>
                        <x-cancellation-details :record="$rental" />
                    </div>
                @endif

                <!-- Before-rental condition -->
                @if ($rental->beforeConditionRecord)
                    <x-rental-panel title="Condition before rental" icon="clipboard-list">

                        <p class="mt-2 text-sm text-slate-600">Condition: <span class="font-medium">{{ $rental->beforeConditionRecord->condition->label() }}</span></p>
                        @if ($rental->beforeConditionRecord->notes)
                            <p class="mt-1 text-sm text-slate-500">{{ $rental->beforeConditionRecord->notes }}</p>
                        @endif
                        @if ($rental->beforeConditionRecord->photos->isNotEmpty())
                            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($rental->beforeConditionRecord->photos as $photo)
                                    <img src="{{ $photo->url() }}" alt="Item condition photo {{ $loop->iteration }}" loading="lazy" class="aspect-square w-full rounded-lg object-cover">
                                @endforeach
                            </div>
                        @endif
                    </x-rental-panel>
                @elseif (! $rental->isPaymentPending())
                    <x-rental-panel title="Record condition before rental" icon="clipboard-list" subtitle="Document the item's condition before handing it over.">

                        <form wire:submit="recordBeforeCondition" class="mt-4 space-y-4">
                            <div>
                                <x-input-label required for="before_condition" value="Condition" />
                                <select wire:model="before_condition" id="before_condition" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @foreach (\App\Enums\ListingCondition::cases() as $option)
                                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="before_notes" value="Notes (optional)" />
                                <textarea wire:model="before_notes" id="before_notes" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="e.g. Minor scratch on handle."></textarea>
                            </div>
                            <div>
                                <x-input-label for="before_photos" value="Photos (optional)" />
                                <input id="before_photos" type="file" wire:model="before_photos" multiple accept="image/*" class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100">
                                <x-input-error :messages="$errors->get('before_photos.*')" class="mt-2" />
                            </div>
                            <x-primary-button class="min-h-11 rounded-xl" wire:loading.attr="disabled">Save condition record</x-primary-button>
                        </form>
                    </x-rental-panel>
                @endif

                @if ($rental->awaitingPickupConfirmation())
                    <x-rental-panel :title="$rental->fulfillment_method->label().' confirmation'" icon="inbox" subtitle="Both you and the renter need to confirm the handover before the rental starts.">
                        <ul class="mt-4 space-y-2 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm">
                            <li class="{{ $rental->pickup_confirmed_by_owner_at ? 'text-blue-700' : 'text-slate-500' }}">
                                {{ $rental->pickup_confirmed_by_owner_at ? '✓ You confirmed' : 'Waiting on your confirmation' }}
                            </li>
                            <li class="{{ $rental->pickup_confirmed_by_renter_at ? 'text-blue-700' : 'text-slate-500' }}">
                                {{ $rental->pickup_confirmed_by_renter_at ? '✓ Renter confirmed' : 'Waiting on renter confirmation' }}
                            </li>
                        </ul>

                        @if (! $rental->pickup_confirmed_by_owner_at)
                            <button type="button" wire:click="confirmPickup" wire:loading.attr="disabled" wire:target="confirmPickup" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:cursor-wait disabled:opacity-60">
                                Confirm item handed over
                            </button>
                        @endif
                    </x-rental-panel>
                @endif

                @if ($rental->awaitingReturnConfirmation())
                    <x-rental-panel title="Return confirmation" icon="archive" subtitle="Confirm you received the item. The rental closes automatically once you also save its returned condition.">
                        <ul class="mt-4 space-y-2 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm">
                            <li class="{{ $rental->return_confirmed_by_owner_at ? 'text-blue-700' : 'text-slate-500' }}">
                                {{ $rental->return_confirmed_by_owner_at ? '✓ You confirmed' : 'Waiting on your confirmation' }}
                            </li>
                        </ul>

                        @if (! $rental->return_confirmed_by_owner_at)
                            <button type="button" wire:click="confirmReturn" wire:loading.attr="disabled" wire:target="confirmReturn" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:cursor-wait disabled:opacity-60">
                                Confirm item returned
                            </button>
                        @endif
                    </x-rental-panel>
                @endif

                <!-- After-rental condition -->
                @if ($rental->afterConditionRecord)
                    <x-rental-panel title="Condition after rental" icon="clipboard-list">

                        <p class="mt-2 text-sm text-slate-600">Condition: <span class="font-medium">{{ $rental->afterConditionRecord->condition->label() }}</span></p>
                        @if ($rental->afterConditionRecord->notes)
                            <p class="mt-1 text-sm text-slate-500">{{ $rental->afterConditionRecord->notes }}</p>
                        @endif
                        @if ($rental->afterConditionRecord->photos->isNotEmpty())
                            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($rental->afterConditionRecord->photos as $photo)
                                    <img src="{{ $photo->url() }}" alt="Item condition photo {{ $loop->iteration }}" loading="lazy" class="aspect-square w-full rounded-lg object-cover">
                                @endforeach
                            </div>
                        @endif

                        @if ($rental->damageReport)
                            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-semibold text-amber-800">Damage reported</p>
                                    <x-badge :color="$rental->damageReport->status->badgeColor()">{{ $rental->damageReport->status->label() }}</x-badge>
                                </div>
                                <p class="mt-1 text-sm text-amber-700">{{ $rental->damageReport->damage_type }}: {{ $rental->damageReport->description }}</p>
                                <p class="mt-1 text-sm text-amber-700">Estimated repair: ₱{{ number_format($rental->damageReport->estimated_repair_cost, 2) }} &middot; Proposed deduction: ₱{{ number_format($rental->damageReport->proposed_deduction, 2) }}</p>
                                @if ($rental->damageReport->renter_response_notes)
                                    <p class="mt-2 text-sm text-amber-700"><span class="font-medium">Renter's response:</span> {{ $rental->damageReport->renter_response_notes }}</p>
                                @endif
                            </div>
                        @endif
                    </x-rental-panel>
                @elseif ($rental->awaitingReturnConfirmation() || $rental->isReturned())
                    <x-rental-panel title="Record condition after rental" icon="clipboard-list" subtitle="Document the returned item's condition. Saving this record closes the rental once you confirm its return.">

                        <form wire:submit="recordAfterCondition" class="mt-4 space-y-4">
                            <div>
                                <x-input-label required for="after_condition" value="Condition" />
                                <select wire:model="after_condition" id="after_condition" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @foreach (\App\Enums\ListingCondition::cases() as $option)
                                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="after_notes" value="Notes (optional)" />
                                <textarea wire:model="after_notes" id="after_notes" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>
                            <div>
                                <x-input-label for="after_photos" value="Photos (optional)" />
                                <input id="after_photos" type="file" wire:model="after_photos" multiple accept="image/*" class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100">
                                <x-input-error :messages="$errors->get('after_photos.*')" class="mt-2" />
                            </div>

                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model.live="after_has_damage" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                New damage found on this item
                            </label>

                            @if ($after_has_damage)
                                <div class="space-y-4 rounded-lg border border-rose-200 bg-rose-50 p-4">
                                    <div>
                                        <x-input-label required for="damage_type" value="Damage type" />
                                        <x-text-input wire:model="damage_type" id="damage_type" type="text" placeholder="e.g. Cracked casing" class="mt-1 block w-full" />
                                        <x-input-error :messages="$errors->get('damage_type')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label required for="damage_description" value="Description" />
                                        <textarea wire:model="damage_description" id="damage_description" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-rose-500 focus:ring-rose-500"></textarea>
                                        <x-input-error :messages="$errors->get('damage_description')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label required for="damage_estimated_cost" value="Estimated repair cost (₱)" />
                                        <x-text-input wire:model="damage_estimated_cost" id="damage_estimated_cost" type="number" step="0.01" class="mt-1 block w-40" />
                                        <x-input-error :messages="$errors->get('damage_estimated_cost')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="damage_photos" value="Damage photos" />
                                        <input id="damage_photos" type="file" wire:model="damage_photos" multiple accept="image/*" class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-white file:px-4 file:py-2 file:text-sm file:font-medium file:text-rose-700 hover:file:bg-rose-100">
                                        <x-input-error :messages="$errors->get('damage_photos.*')" class="mt-2" />
                                    </div>
                                </div>
                            @endif

                            <x-primary-button class="min-h-11 rounded-xl" wire:loading.attr="disabled">Save condition record</x-primary-button>
                        </form>
                    </x-rental-panel>
                @endif


                @if ($rental->isCompleted())
                    <x-rental-panel title="Security deposit" icon="calculator">

                        <div class="mt-2 flex items-center gap-2">
                            <x-badge :color="$rental->securityDeposit->status->badgeColor()">{{ $rental->securityDeposit->status->label() }}</x-badge>
                            <span class="text-sm text-slate-500">₱{{ number_format($rental->securityDeposit->amount, 2) }} deposit amount</span>
                        </div>

                        @if ($rental->securityDeposit->released_at)
                            <p class="mt-3 text-sm text-slate-600">Release recorded {{ $rental->securityDeposit->released_at->setTimezone('Asia/Manila')->format('M d, Y g:i A') }} (Philippine time). {{ $rental->securityDeposit->received_at ? 'The renter confirmed receipt.' : ($rental->securityDeposit->amount > $rental->securityDeposit->deducted_amount ? 'Awaiting renter receipt confirmation.' : 'No refundable balance is due.') }}</p>
                        @endif
                        @can('releaseDeposit', $rental)
                            <x-action-confirmation id="deposit-release" title="Confirm deposit release" message="Have you sent the full deposit back to the renter? This records release only; it does not transfer money. The renter will confirm receipt separately." action="releaseDeposit" confirm-label="Record deposit release">
                            <button type="button" x-on:click="$refs.confirmation.showModal()" wire:loading.attr="disabled" wire:target="releaseDeposit" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:cursor-wait disabled:opacity-60">
                                Release deposit
                            </button>
                            </x-action-confirmation>
                        @endcan
                        @if ($rental->securityDeposit->status->value === 'deducted')
                            <p class="mt-2 text-sm text-slate-600">
                                Deducted ₱{{ number_format($rental->securityDeposit->deducted_amount, 2) }} &middot;
                                Refunded to renter: ₱{{ number_format($rental->securityDeposit->amount - $rental->securityDeposit->deducted_amount, 2) }}
                            </p>
                        @elseif ($rental->securityDeposit->status->value === 'damage_claim')
                            <p class="mt-2 text-sm text-slate-600">Waiting for the renter to accept or dispute the damage claim above.</p>
                        @endif
                    </x-rental-panel>

                    @if (auth()->id() === $rental->owner_id && $rental->owner_id !== $rental->renter_id)
                        <x-rental-panel id="reviews" title="Rate the renter" icon="star" subtitle="Optional. Your rating and comment will be published on the renter's public profile.">
                            @if ($rental->reviewFromOwnerToRenter)
                                <p class="mt-2 text-sm text-slate-600">You rated {{ $rental->reviewFromOwnerToRenter->rating }}/5 stars</p>
                                @if ($rental->reviewFromOwnerToRenter->comment)
                                    <p class="mt-1 text-sm text-slate-500">"{{ $rental->reviewFromOwnerToRenter->comment }}"</p>
                                @endif
                            @else
                                <form wire:submit="submitRenterReview" class="mt-3 space-y-3">
                                    <x-input-label required for="renter_rating" value="Rating" />
                                    <select id="renter_rating" wire:model="renter_rating" class="block w-full max-w-xs rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        @for ($i = 5; $i >= 1; $i--)
                                            <option value="{{ $i }}">{{ $i }} star{{ $i === 1 ? '' : 's' }}</option>
                                        @endfor
                                    </select>
                                    <x-input-error :messages="$errors->get('renter_rating')" />
                                    <x-input-label for="renter_comment" value="Comment (optional)" />
                                    <textarea id="renter_comment" wire:model="renter_comment" rows="2" placeholder="Optional comment" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                    <x-input-error :messages="$errors->get('renter_comment')" />
                                    <x-primary-button class="min-h-11 rounded-xl" wire:loading.attr="disabled">Submit rating</x-primary-button>
                                </form>
                            @endif
                        </x-rental-panel>
                    @endif
                @endif

                <x-rental-panel title="Disputes" icon="chat" subtitle="Keep any issues and resolutions in one place.">
                    @if (session('status'))
                        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">{{ session('status') }}</div>
                    @endif

                    @forelse ($rental->disputes as $dispute)
                        <div class="mb-3 rounded-lg border border-slate-200 p-3" wire:key="dispute-{{ $dispute->id }}">
                            <div class="flex items-center gap-2">
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
                        <p class="mt-4 text-sm text-slate-500">No disputes filed for this rental.</p>
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
                            <div class="flex gap-2">
                                <x-primary-button class="min-h-11 rounded-xl" wire:loading.attr="disabled">Submit dispute</x-primary-button>
                                <button type="button" wire:click="$set('showDisputeForm', false)" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</button>
                            </div>
                        </form>
                    @endif
                </x-rental-panel>
            </div>
        </div>
    </div>
</div>
