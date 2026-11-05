<div>
    <x-page-header :panel="true" eyebrow="Renter interface" title="Renter dashboard" subtitle="Find items to rent and keep track of your requests and rentals." />
    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section aria-label="Rental overview" class="lg:w-1/2">
            <dl class="grid grid-cols-3 gap-2.5 sm:gap-3">
                @foreach ([
                    ['label' => 'Approved requests', 'value' => $approvedCount, 'icon' => 'check'],
                    ['label' => 'Pending requests', 'value' => $pendingCount, 'icon' => 'clock'],
                    ['label' => 'Active rentals', 'value' => $activeRentalsCount, 'icon' => 'archive'],
                ] as $stat)
                    <div class="flex min-h-28 min-w-0 flex-col justify-between rounded-xl border border-[#b7d1ed] bg-[#c9dff5] p-3 sm:min-h-32 sm:p-4">
                        <dt class="order-2 mt-2 text-xs font-medium text-blue-950">{{ $stat['label'] }}</dt>
                        <dd class="flex flex-wrap items-center justify-between gap-1">
                            <span class="text-xl font-bold tabular-nums text-blue-950 sm:text-2xl">{{ $stat['value'] }}</span>
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-white/50 text-blue-700">
                                <x-icon :name="$stat['icon']" class="h-4 w-4" aria-hidden="true" />
                            </span>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <x-dashboard-progress :interface="$interface" :request-progress="$requestProgress" :member-rentals-by-status="$memberRentalsByStatus" :awaiting-agreement-count="$awaitingAgreementCount" :identity-progress="$identityProgress" :proofs-under-review-count="$proofsUnderReviewCount" />

        @if ($rentalsAwaitingPayment->isNotEmpty())
            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                    <x-icon name="exclamation-triangle" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-sm font-semibold text-amber-800">
                        You have {{ $rentalsAwaitingPayment->count() }} approved rental{{ $rentalsAwaitingPayment->count() === 1 ? '' : 's' }} waiting for payment
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($rentalsAwaitingPayment as $rental)
                            <a href="{{ route('renter.rentals.show', $rental) }}" wire:navigate class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                                Pay for {{ $rental->listing->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <section aria-labelledby="recent-rental-requests" class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 px-4 py-4 sm:px-5">
                <h2 id="recent-rental-requests" class="font-semibold text-slate-900">Recent rental requests</h2>
                <a href="{{ route('renter.rental-requests.index') }}" wire:navigate class="shrink-0 text-sm font-medium text-blue-700 hover:underline">View all</a>
            </div>

            @if ($recentRequests->isEmpty())
                <div class="px-4 pb-4 sm:px-5 sm:pb-5">
                    <x-empty-state title="No rental requests yet" message="Browse listings and request to rent an item to see it here." />
                </div>
            @else
                <div class="overflow-x-auto rounded-b-xl">
                    <table aria-labelledby="recent-rental-requests" class="w-full text-left text-sm">
                        <thead class="border-y border-blue-100 bg-blue-50 text-xs text-blue-950">
                            <tr>
                                <th scope="col" class="px-3 py-2.5 sm:px-5">Item</th>
                                <th scope="col" class="hidden px-5 py-2.5 sm:table-cell">Rental period</th>
                                <th scope="col" class="px-3 py-2.5 sm:px-5">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentRequests as $request)
                                <tr wire:key="recent-request-{{ $request->id }}" class="even:bg-slate-50/70">
                                    <td class="px-3 py-3 sm:px-5">
                                        <div class="flex items-start gap-2 sm:items-center sm:gap-3">
                                            @if ($image = $request->listing->images->first())
                                                <img src="{{ $image->url() }}" alt="" class="h-8 w-8 shrink-0 rounded-lg object-cover sm:h-10 sm:w-10" loading="lazy">
                                            @else
                                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-600 sm:h-10 sm:w-10">
                                                    <x-icon name="archive" class="h-4 w-4" aria-hidden="true" />
                                                </span>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="font-medium text-slate-800">{{ $request->listing->name }}</p>
                                                <p class="mt-1 text-xs text-slate-500 sm:hidden"><x-rental-period :booking="$request" /></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="hidden px-5 py-3 text-slate-500 sm:table-cell"><x-rental-period :booking="$request" /></td>
                                    <td class="px-3 py-3 sm:px-5">
                                        <x-badge :color="$request->status->badgeColor()">{{ $request->status->label() }}</x-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</div>
