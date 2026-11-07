<div class="admin-rental-detail" wire:poll.5s.keep-alive x-data="adminRentalDetail">
    <x-page-header eyebrow="Administration · Transactions" :title="'Transaction #'.$rental->id" :subtitle="$rental->listing->name" />

    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('admin.rentals.index') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-lg text-sm font-semibold text-slate-500 transition hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">
                <x-icon name="chevron-down" class="h-4 w-4 rotate-90" aria-hidden="true" /> Back to transactions
            </a>
            <button type="button" x-ref="historyTrigger" @click="historyOpen = true" :aria-expanded="historyOpen" aria-controls="admin-history-panel" aria-haspopup="dialog" class="admin-rental-history-trigger inline-flex min-h-11 items-center gap-2.5 rounded-full border border-blue-100 bg-white px-5 py-2.5 text-sm font-semibold text-blue-700 shadow-[0_6px_24px_rgba(37,99,235,0.12)] transition hover:-translate-y-0.5 hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-blue-100">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 11a9 9 0 1 1 2.6 7.4M3 4v7h7M12 7v5l3 2" />
                </svg>
                History <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-600">{{ $actions->total() }}</span>
            </button>
        </div>
        @if (session('status'))
            <p class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif

        <x-booking-progress :rental="$rental" :reviewer="true" />
        <div class="admin-rental-layout">
            <aside class="min-w-0" aria-label="Rental item and booking summary">
                <section aria-label="Transaction summary" class="admin-rental-summary overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="relative grid aspect-square max-h-[24rem] place-items-center bg-slate-100 p-5">
                        <div class="flex flex-col items-center gap-3 text-slate-400">
                            <x-icon name="archive" class="!h-12 !w-12" aria-hidden="true" />
                            <p class="text-sm">No item photo available</p>
                        </div>
                        @if ($rental->listing->images->isNotEmpty())
                            <img src="{{ $rental->listing->images->first()->url() }}" alt="{{ $rental->listing->name }}" x-data="{ imageFailed: false }" x-init="imageFailed = $el.complete && $el.naturalWidth === 0" x-on:error="imageFailed = true" x-show="!imageFailed" class="absolute inset-0 h-full w-full bg-slate-100 object-contain p-5">
                        @endif
                    </div>
                    <div class="p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Rental item</p>
                            <x-badge :color="$rental->displayStatusColor()">{{ $rental->displayStatusLabel() }}</x-badge>
                        </div>
                        <h2 class="mt-4 break-words text-2xl font-bold tracking-tight text-slate-900">{{ $rental->listing->name }}</h2>
                        <p class="mt-3 text-sm text-slate-500">{{ $rental->fulfillment_method->label() }} · {{ $rental->rental_unit === 'hour' ? $rental->rental_hours.' hours' : $rental->rental_days.' days' }}</p>
                        @if ($rental->listing->trashed())
                            <p class="mt-4 text-sm text-slate-500">Listing removed. This transaction and its evidence are retained.</p>
                        @else
                            <a href="{{ route('listings.show', $rental->listing) }}" wire:navigate class="mt-5 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">View listing <span aria-hidden="true">↗</span></a>
                        @endif
                    </div>
                    <dl class="space-y-5 border-t border-slate-100 bg-slate-50/70 p-6">
                        <div>
                            <dt class="text-xs text-slate-500">Rental period</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-800"><x-rental-period :booking="$rental" /></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Booking total</dt>
                            <dd class="mt-1 text-xl font-bold text-slate-900">₱{{ number_format($rental->total_amount, 2) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Security deposit</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $rental->securityDeposit?->status->label() ?? 'No deposit record' }}</dd>
                        </div>
                    </dl>
                </section>
            </aside>
            <div class="admin-rental-workspace min-w-0 space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm">
                    <nav x-ref="tabs" role="tablist" aria-label="Transaction details" class="admin-rental-tabs flex gap-1 overflow-x-auto" @keydown.right.prevent="moveTab(1)" @keydown.left.prevent="moveTab(-1)" @keydown.home.prevent="selectTab('overview', true)" @keydown.end.prevent="selectTab('actions', true)">
                        @foreach ([
                            'overview' => ['Overview', 'archive'],
                            'payments' => ['Payments', 'calculator'],
                            'agreement' => ['Agreement & exchange', 'clipboard-list'],
                            'evidence' => ['Evidence & reviews', 'camera'],
                            'actions' => ['Admin actions', 'cog'],
                            ] as $tabId => [$label, $icon])
                            <button type="button" id="rental-tab-{{ $tabId }}" role="tab" aria-controls="rental-panel-{{ $tabId }}" :aria-selected="activeTab === '{{ $tabId }}'" :tabindex="activeTab === '{{ $tabId }}' ? 0 : -1" @click="selectTab('{{ $tabId }}')" :class="activeTab === '{{ $tabId }}' ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-100' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'" class="inline-flex min-h-12 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-xl px-4 py-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:flex-1 sm:px-3">
                                <x-icon :name="$icon" class="h-4 w-4 shrink-0" aria-hidden="true" /><span>{{ $label }}</span>
                                @if ($tabId === 'payments' && $rental->paymentSubmissions->where('status', 'pending')->isNotEmpty())
                                    <span class="h-2 w-2 rounded-full bg-amber-500" role="img" aria-label="Payment verification pending">
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </nav>
                </div>
                <section id="rental-panel-overview" role="tabpanel" aria-labelledby="rental-tab-overview" tabindex="0" x-show="activeTab === 'overview'" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded-2xl">
                    <div class="admin-rental-grid">
                        <x-rental-panel title="Booking and participants" icon="archive">
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <p class="font-semibold text-slate-900">{{ $rental->listing->name }}</p>
                                <x-badge :color="$rental->displayStatusColor()">{{ $rental->displayStatusLabel() }}</x-badge>
                            </div>
                            @if ($rental->listing->trashed())
                                <p class="mt-2 text-sm text-slate-500">Listing removed. This transaction and its evidence are retained.</p>
                            @endif
                            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                                @foreach (['Owner' => $rental->owner, 'Renter' => $rental->renter] as $label => $party)
                                    <div>
                                        <dt class="text-slate-500">{{ $label }}</dt>
                                        <dd class="mt-1 font-semibold text-slate-800">{{ $party->name }}</dd>
                                        @unless ($party->trashed())
                                            <dd class="mt-1 break-words text-slate-500">{{ $party->email }}</dd>
                                            <dd class="mt-1 text-slate-500">Phone: {{ $party->phone ?: 'Not provided' }}</dd>
                                            <dd class="mt-1 break-words text-slate-500">Address: {{ $party->address ?: 'Not provided' }}</dd>
                                            <dd class="mt-2"><a href="{{ route('admin.dashboard', ['search' => $party->email]) }}" wire:navigate class="font-medium text-blue-700 hover:underline">Manage {{ strtolower($label) }} account</a></dd>
                                        @else
                                            <dd class="mt-1 text-slate-500">Account closed</dd>
                                        @endunless
                                    </div>
                                @endforeach
                                <div>
                                    <dt class="text-slate-500">Rental period</dt>
                                    <dd class="mt-1 font-semibold"><x-rental-period :booking="$rental" /></dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Duration and fulfillment</dt>
                                    <dd class="mt-1 font-semibold">{{ $rental->rental_days }} days · {{ $rental->fulfillment_method->label() }}</dd>
                                </div>
                                <div><dt class="text-slate-500">Agreed {{ $rental->rental_unit === 'hour' ? 'hourly' : 'daily' }} rate</dt><dd class="mt-1 font-semibold">₱{{ number_format($rental->rental_unit === 'hour' ? (float) $rental->rental_fee / max(1, $rental->rental_hours) : $rental->agreedDailyRate(), 2) }}</dd></div>
                                <div><dt class="text-slate-500">Commission rate</dt><dd class="mt-1 font-semibold">{{ number_format($rental->commission_rate, 2) }}%</dd></div>
                                <div><dt class="text-slate-500">Days overdue</dt><dd class="mt-1 font-semibold">{{ $rental->currentOverdueDays() }}</dd></div>
                                <div><dt class="text-slate-500">Renter accepted terms</dt><dd class="mt-1 font-semibold">{{ $rental->rentalRequest?->renter_terms_accepted_at?->format('M d, Y g:i A') ?? 'Not recorded' }}</dd></div>
                                <div><dt class="text-slate-500">Owner accepted terms</dt><dd class="mt-1 font-semibold">{{ $rental->rentalRequest?->owner_terms_accepted_at?->format('M d, Y g:i A') ?? 'Not recorded' }}</dd></div>
                            </dl>
                            @if ($rental->cancellation_reason)
                                <p class="mt-4 text-sm text-slate-600">Cancellation reason: {{ $rental->cancellation_reason }}</p>
                            @endif
                        </x-rental-panel>
                        <x-rental-panel title="Transaction timeline" icon="clipboard-list">
                            <dl class="admin-rental-timeline mt-5 text-sm">
                                @foreach ($timeline as $label => $date)
                                    <div class="relative flex flex-wrap justify-between gap-x-3 gap-y-1 pb-4 pl-6">
                                        <dt class="text-slate-600">
                                            <span class="absolute left-0 top-1.5 h-2.5 w-2.5 rounded-full {{ $date ? 'bg-blue-600 ring-4 ring-blue-50' : 'bg-slate-200 ring-4 ring-white' }}" aria-hidden="true">
                                            </span>{{ $label }}</dt>
                                        <dd class="text-xs font-medium {{ $date ? 'text-slate-800' : 'text-slate-400' }}">{{ $date?->format('M d, Y · g:i A') ?? 'Not recorded' }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </x-rental-panel>
                    </div>
                </section>
                <section id="rental-panel-payments" role="tabpanel" aria-labelledby="rental-tab-payments" tabindex="0" x-show="activeTab === 'payments'" x-cloak style="display: none;" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded-2xl">
                    <div class="admin-rental-grid">
                        <x-rental-panel title="Charges, payment and deposit" icon="calculator">
                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                @foreach (['Rental fee' => $rental->rental_fee, 'Commission' => $rental->commission_amount, 'Security deposit' => $rental->security_deposit, 'Booking total' => $rental->total_amount, 'Late fee' => $rental->currentLateFee(), 'Cancellation fee' => $rental->cancellation_fee ?? 0] as $label => $amount)
                                    <div>
                                        <dt class="text-slate-500">{{ $label }}</dt>
                                        <dd class="mt-1 font-semibold">₱{{ number_format($amount, 2) }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                            @if ($rental->payment)
                                <p class="mt-4 break-words text-sm text-slate-600">Payment reference: {{ $rental->payment->transaction_reference }}</p>
                                <p class="mt-1 text-sm text-slate-600">Recorded payment: ₱{{ number_format($rental->payment->amount, 2) }} · {{ ucfirst($rental->payment->status) }}</p>
                                <p class="mt-1 text-sm text-slate-600">Paid on {{ $rental->payment->paid_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Not recorded' }} (Philippine time)</p>
                            @else
                                <p class="mt-4 text-sm text-slate-500">No payment recorded.</p>
                            @endif
                            @if ($rental->securityDeposit)
                                <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                                    <span>Deposit:</span>
                                    <x-badge :color="$rental->securityDeposit->status->badgeColor()">{{ $rental->securityDeposit->status->label() }}</x-badge>
                                    <span>₱{{ number_format($rental->securityDeposit->amount, 2) }} · Deducted: ₱{{ number_format($rental->securityDeposit->deducted_amount, 2) }}</span>
                                </div>
                            @else
                                <p class="mt-4 text-sm text-slate-500">No deposit recorded.</p>
                            @endif
                        </x-rental-panel>
                        <div class="min-w-0 space-y-6">
                            @if ($rental->paymentSubmissions->isNotEmpty())
                                <x-rental-panel title="Offline payment verification" icon="calculator">
                                    @foreach ($rental->paymentSubmissions as $submission)
                                        <div class="mt-4 space-y-3 border-t border-slate-100 pt-4 text-sm" wire:key="admin-payment-proof-{{ $submission->id }}">
                                            <p class="font-semibold">Submission #{{ $submission->id }} · {{ ucfirst($submission->status) }} · {{ \App\Models\OfflinePaymentSetting::methodLabel($submission->method) }}</p>
                                            <p>Expected payment: ₱{{ number_format($submission->amount, 2) }} · Reference: {{ $submission->external_reference }}</p>
                                            <p class="whitespace-pre-line text-slate-500">Payment instructions used: {{ $submission->instructions }}</p>
                                            @if (($submission->payment_destination['source'] ?? null) === 'owner_wallet')
                                                <div class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-blue-900">
                                                    <p>Payment destination: owner's {{ \App\Models\OfflinePaymentSetting::methodLabel($submission->method) }} account, {{ $submission->payment_destination['account_name'] }}.</p>
                                                    <p class="mt-1">{{ $submission->recipient_confirmed_at ? 'Owner acknowledged receipt on '.$submission->recipient_confirmed_at->setTimezone('Asia/Manila')->format('M d, Y · g:i A').' (Philippine time).' : 'Waiting for the owner to acknowledge receipt of the full amount.' }}</p>
                                                    <p class="mt-1 text-xs">The owner holds these funds. Platform-fee remittance and deposit refunds are handled manually.</p>
                                                </div>
                                            @endif
                                            <a href="{{ route('payment-proofs.show', $submission) }}" class="inline-flex min-h-10 items-center font-semibold text-blue-700">Download private payment proof</a>
                                            @if ($submission->reviewed_at)
                                                <p class="text-slate-500">Reviewed by {{ $submission->reviewer->name }} · {{ $submission->reviewed_at->format('M d, Y · g:i A') }}</p>
                                                <p class="whitespace-pre-line">{{ $submission->review_notes }}</p>
                                            @endif
                                            @if ($submission->status === 'pending')
                                                <x-input-label required :for="'payment-review-'.$submission->id" value="Verification notes or rejection reason (shared with both parties)" />
                                                <textarea id="payment-review-{{ $submission->id }}" wire:model="reason" maxlength="1000" rows="3" class="w-full rounded-lg border-slate-300">
                                                </textarea>
                                                <x-input-error :messages="$errors->get('reason')" />
                                                @can('adminVerifyPayment', $rental)
                                                    @if (($submission->payment_destination['source'] ?? null) === 'owner_wallet' && ! $submission->recipient_confirmed_at)
                                                        <p class="text-amber-700">Owner receipt confirmation is required before payment approval.</p>
                                                    @else
                                                    <label class="flex items-start gap-2">
                                                        <input type="checkbox" wire:model="funds_received" class="mt-1 rounded border-slate-300">
                                                        <span>I matched this reference and proof to the collection records and confirmed the full ₱{{ number_format($rental->total_amount, 2) }} was received, including the processing fee and security deposit.</span>
                                                    </label>
                                                    <x-input-error :messages="$errors->get('funds_received')" />
                                                    <button type="button" wire:click="verifyPayment({{ $submission->id }})" wire:confirm="Confirm receipt of the full amount and mark this booking paid?" wire:loading.attr="disabled" class="rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white">Verify received payment</button>
                                                    @endif
                                                @else
                                                    <p class="text-amber-700">This booking is not eligible for payment completion. If funds were received, arrange their return before rejecting this proof.</p>
                                                @endcan
                                                @can('adminRejectPayment', $rental)
                                                    <button type="button" wire:click="rejectPayment({{ $submission->id }})" wire:confirm="Reject this proof and notify both parties with your reason?" wire:loading.attr="disabled" class="rounded-lg border border-rose-200 px-4 py-3 font-semibold text-rose-700">Reject payment proof</button>
                                                @endcan
                                            @endif
                                        </div>
                                    @endforeach
                                </x-rental-panel>
                            @else
                                <x-rental-panel title="Offline payment verification" icon="calculator">
                                    <div class="mt-5 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">No payment proofs have been submitted for this transaction.</div>
                                </x-rental-panel>
                            @endif
                        </div>
                    </div>
                </section>
                <section id="rental-panel-agreement" role="tabpanel" aria-labelledby="rental-tab-agreement" tabindex="0" x-show="activeTab === 'agreement'" x-cloak style="display: none;" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded-2xl">
                    <div class="admin-rental-grid">
                        @if ($rental->rentalRequest?->agreement_terms)
                            <x-rental-panel title="Accepted agreement" icon="clipboard-list">
                                <div class="mt-4">
                                    <x-rental-agreement :terms="$rental->rentalRequest->agreement_terms" :rental-request="$rental->rentalRequest" />
                                </div>
                            </x-rental-panel>
                        @else
                            <x-rental-panel title="Accepted agreement">
                                <p class="mt-4 text-sm text-slate-500">No saved agreement is available.</p>
                            </x-rental-panel>
                        @endif
                        <div class="min-w-0 space-y-6">
                            @if ($rental->exchangeSchedule)
                                <x-rental-panel title="Agreed exchange arrangements" icon="clipboard-list">
                                    <p class="mt-4 text-sm font-semibold">{{ $rental->exchangeSchedule->fulfillment_method->label() }} · {{ $rental->exchangeSchedule->scheduled_at->setTimezone('Asia/Manila')->format('M d, Y · g:i A') }} (Philippine time)</p>
                                    <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $rental->exchangeSchedule->address }}</p>
                                    <p class="mt-2 text-sm text-slate-500">{{ $rental->exchangeSchedule->confirmed_at ? 'Confirmed by both parties' : 'Awaiting both confirmations' }}</p>
                                    @if ($rental->exchangeSchedule->notes)
                                        <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $rental->exchangeSchedule->notes }}</p>
                                    @endif
                                </x-rental-panel>
                            @else
                                <x-rental-panel title="Exchange arrangements" icon="map-pin">
                                    <p class="mt-4 text-sm text-slate-500">No exchange arrangements have been recorded yet.</p>
                                </x-rental-panel>
                            @endif
                        </div>
                    </div>
                </section>
                <section id="rental-panel-evidence" role="tabpanel" aria-labelledby="rental-tab-evidence" tabindex="0" x-show="activeTab === 'evidence'" x-cloak style="display: none;" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded-2xl">
                    <div class="admin-rental-grid">
                        <x-rental-panel title="Condition and damage evidence" icon="clipboard-list">
                            @forelse ($rental->conditionRecords as $record)
                                <div class="mt-4 border-t border-slate-100 pt-4">
                                    <p class="text-sm font-semibold">{{ $record->type->label() }} · {{ $record->condition->label() }} · {{ $record->recordedBy->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Recorded on {{ $record->created_at->format('M d, Y g:i A') }}</p>
                                    <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $record->notes ?: 'No notes recorded.' }}</p>
                                    <p class="mt-1 text-sm text-slate-600">Damage recorded: {{ $record->has_damage ? 'Yes' : 'No' }}</p>
                                    <div class="mt-3 flex flex-wrap gap-3">
                                        @foreach ($record->photos as $photo)
                                            <a href="{{ $photo->url() }}" target="_blank" rel="noopener">
                                                <img src="{{ $photo->url() }}" alt="Condition evidence" class="h-24 w-24 rounded-lg object-cover">
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <p class="mt-4 text-sm text-slate-500">No condition records.</p>
                            @endforelse
                            @if ($rental->damageReport)
                                <div class="mt-4 border-t border-slate-100 pt-4 text-sm">
                                    <p class="font-semibold">Damage claim: {{ $rental->damageReport->damage_type }} · {{ $rental->damageReport->status->label() }}</p>
                                    <p class="mt-2 whitespace-pre-line text-slate-600">{{ $rental->damageReport->description }}</p>
                                    <p class="mt-2 text-slate-600">Proposed deduction: ₱{{ number_format($rental->damageReport->proposed_deduction, 2) }}</p>
                                    <p class="mt-1 text-slate-600">Estimated repair: ₱{{ number_format($rental->damageReport->estimated_repair_cost, 2) }}</p>
                                    @if ($rental->damageReport->renter_response_notes)
                                        <p class="mt-2 whitespace-pre-line text-slate-600">Renter response: {{ $rental->damageReport->renter_response_notes }}</p>
                                    @endif
                                    <div class="mt-3 flex flex-wrap gap-3">
                                        @foreach ($rental->damageReport->photos as $photo)
                                            <a href="{{ $photo->url() }}" target="_blank" rel="noopener">
                                                <img src="{{ $photo->url() }}" alt="Damage evidence" class="h-24 w-24 rounded-lg object-cover">
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <p class="mt-4 text-sm text-slate-500">No damage claim.</p>
                            @endif
                        </x-rental-panel>
                        <x-rental-panel title="Disputes and reviews" icon="chat">
                            @forelse ($rental->disputes as $dispute)
                                <div class="mt-4 border-t border-slate-100 pt-4 text-sm">
                                    <p class="font-semibold">Dispute #{{ $dispute->id }} · {{ $dispute->reason->label() }} · {{ $dispute->status->label() }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Raised by {{ $dispute->raisedBy->name }} on {{ $dispute->created_at->format('M d, Y g:i A') }}</p>
                                    <p class="mt-2 whitespace-pre-line text-slate-600">{{ $dispute->description }}</p>
                                    @if ($dispute->isOpen())
                                        <a href="{{ route('admin.disputes.index', ['rental' => $rental->id, 'filter' => 'all']) }}" wire:navigate class="mt-3 inline-flex min-h-10 items-center font-medium text-blue-700">Review and resolve dispute #{{ $dispute->id }}</a>
                                    @else
                                        <p class="mt-2 text-xs text-slate-500">Resolved by {{ $dispute->resolvedBy?->name ?? 'Not recorded' }} on {{ $dispute->resolved_at?->format('M d, Y g:i A') ?? 'Not recorded' }}</p>
                                    @endif
                                    @if ($dispute->resolution)
                                        <p class="mt-2 text-slate-600">Resolution: {{ $dispute->resolutionLabel() }} · {{ $dispute->resolution_notes }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="mt-4 text-sm text-slate-500">No disputes.</p>
                            @endforelse
                            @foreach ($rental->reviews as $review)
                                <p class="mt-4 text-sm font-semibold">{{ $review->type->label() }} · {{ $review->rating }}/5</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $review->comment }}</p>
                            @endforeach
                            @if ($rental->reviews->isEmpty())
                                <p class="mt-4 text-sm text-slate-500">No reviews yet.</p>
                            @endif
                        </x-rental-panel>
                    </div>
                </section>
                <section id="rental-panel-actions" role="tabpanel" aria-labelledby="rental-tab-actions" tabindex="0" x-show="activeTab === 'actions'" x-cloak style="display: none;" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded-2xl">
                    <div class="admin-rental-grid">
                        <x-rental-panel title="Manage this transaction" icon="clipboard-list">
                            <p class="mt-4 text-sm text-slate-600">Available actions follow the recorded payment, hand-over, return and inspection history. Each change records the administrator and reason.</p>
                            @if (auth()->user()->can('adminCancel', $rental) || auth()->user()->can('adminCompleteInspection', $rental) || auth()->user()->can('adminReleaseDeposit', $rental))
                                <div class="mt-4 space-y-3">
                                    <x-input-label required for="admin-reason" value="Reason (shared with both parties)" />
                                    <textarea id="admin-reason" wire:model="reason" rows="3" maxlength="1000" class="block w-full rounded-lg border-slate-300 text-sm"></textarea>
                                    <x-input-error :messages="$errors->get('reason')" />
                                    @can('adminCancel', $rental)
                                        <p class="text-sm text-slate-600">Cancellation fee under the saved terms: ₱{{ number_format($cancellation['fee'], 2) }}. Cancelling frees the approved dates and marks any held deposit as refunded.</p>
                                        <button type="button" wire:click="cancelBooking" wire:confirm="Cancel this booking under the saved cancellation terms?" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Cancel booking</button>
                                    @endcan
                                    @can('adminCompleteInspection', $rental)
                                        <p class="text-sm text-slate-600">The owner confirmed receipt and recorded the after-condition. Complete any outstanding inspection to close and archive this rental.</p>
                                        <button type="button" wire:click="completeInspection" wire:confirm="Complete inspection and archive this rental?" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Complete inspection</button>
                                    @endcan
                                    @can('adminReleaseDeposit', $rental)
                                        <p class="text-sm text-slate-600">Record the full deposit release after arranging the agreed refund.</p>
                                        <button type="button" wire:click="releaseDeposit" wire:confirm="Record full release of the eligible security deposit?" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Record deposit release</button>
                                    @endcan
                                </div>
                            @else
                                <p class="mt-4 text-sm text-slate-500">No lifecycle action is available in the current state. Review the evidence and any disputes, or record an admin note below.</p>
                            @endif
                            <a href="{{ route('admin.disputes.index', ['rental' => $rental->id, 'filter' => 'all']) }}" wire:navigate class="mt-4 inline-flex min-h-10 items-center text-sm font-semibold text-blue-700">Manage this transaction’s disputes →</a>

                        </x-rental-panel>
                        <x-rental-panel title="Private admin note" icon="pencil" subtitle="Keep internal context attached to this transaction.">
                            <form wire:submit="addNote" class="mt-5 space-y-3">
                                <x-input-label required for="admin-note" value="Admin note (visible to admins only)" />
                                <textarea id="admin-note" wire:model="note" rows="5" maxlength="1000" placeholder="Add context for the next administrator…" class="block w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                <x-input-error :messages="$errors->get('note')" />
                                <x-primary-button wire:loading.attr="disabled">Save admin note</x-primary-button>
                            </form>
                            <p class="mt-4 text-xs leading-5 text-slate-500">Saved notes appear in History. They are visible to administrators only.</p>
                        </x-rental-panel>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <template x-teleport="body">
        <div x-cloak x-show="historyOpen" style="display: none;" class="admin-rental-history fixed inset-0 z-[80]" @keydown.escape.window="historyOpen = false">
            <div x-show="historyOpen" x-transition.opacity.duration.300ms class="absolute inset-0 bg-slate-900/25 backdrop-blur-sm" @click="historyOpen = false" aria-hidden="true" data-history-backdrop>
            </div>
            <section id="admin-history-panel" role="dialog" aria-modal="true" aria-labelledby="admin-history-title" aria-describedby="admin-history-description" tabindex="-1" x-show="historyOpen" x-trap.inert.noscroll="historyOpen"
            x-transition:enter="transition ease-out duration-300 motion-reduce:transition-none" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200 motion-reduce:transition-none" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
            class="absolute inset-y-0 right-0 flex w-[calc(100%-1rem)] max-w-xl flex-col border-l border-slate-200 bg-white shadow-2xl sm:w-full">
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-slate-50/80 px-5 py-6 sm:px-7">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-blue-600">Transaction #{{ $rental->id }} · {{ $actions->total() }} entries</p>
                        <h2 id="admin-history-title" class="mt-2 text-xl font-bold tracking-tight text-slate-900">Admin action history</h2>
                        <p id="admin-history-description" class="mt-2 text-sm leading-6 text-slate-500">Changes and private notes, most recent first.</p>
                    </div>
                    <button type="button" @click="historyOpen = false" aria-label="Close admin action history" class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-4 focus:ring-blue-100">
                        <x-icon name="x-mark" class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-6 sm:px-7">
                    @forelse ($actions as $entry)
                        <article class="relative ml-2 border-l border-slate-200 pb-7 pl-6 last:border-transparent" wire:key="admin-action-{{ $entry->id }}">
                            <span class="absolute -left-2 top-0 grid h-4 w-4 place-items-center rounded-full bg-white ring-4 ring-white" aria-hidden="true">
                                <span class="h-2.5 w-2.5 rounded-full bg-blue-500">
                                </span>
                            </span>
                            <p class="text-xs text-slate-400">
                                <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->format('M d, Y · g:i A') }}</time>
                            </p>
                            <h3 class="mt-1.5 text-sm font-bold text-slate-900">{{ $entry->action->label() }}</h3>
                            <p class="mt-1 text-xs text-slate-500">By {{ $entry->administrator->name }}</p>
                            <p class="mt-3 whitespace-pre-line break-words rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm leading-6 text-slate-600">{{ $entry->notes }}</p>
                            @if ($entry->before_state['status'] !== $entry->after_state['status'])
                                <p class="mt-3 text-xs font-medium text-blue-700">{{ \App\Enums\RentalStatus::from($entry->before_state['status'])->label() }} → {{ \App\Enums\RentalStatus::from($entry->after_state['status'])->label() }}</p>
                            @endif
                            @if ($entry->before_state['deposit_status'] !== $entry->after_state['deposit_status'])
                                <p class="mt-2 text-xs font-medium text-blue-700">Deposit: {{ \App\Enums\SecurityDepositStatus::tryFrom($entry->before_state['deposit_status'] ?? '')?->label() ?? 'No record' }} → {{ \App\Enums\SecurityDepositStatus::tryFrom($entry->after_state['deposit_status'] ?? '')?->label() ?? 'No record' }}</p>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center">
                            <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-blue-50 text-blue-500">
                                <x-icon name="clipboard-list" class="h-6 w-6" aria-hidden="true" />
                            </span>
                            <h3 class="mt-4 text-sm font-semibold text-slate-800">No admin actions recorded.</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Admin changes and saved notes will appear here.</p>
                        </div>
                    @endforelse
                    <div class="mt-2 border-t border-slate-100 pt-4">{{ $actions->links(data: ['scrollTo' => false]) }}</div>
                </div>
                <div class="shrink-0 border-t border-slate-100 px-5 py-4 text-xs text-slate-400 sm:px-7">Visible to administrators only</div>
            </section>
        </div>
    </template>
</div>
