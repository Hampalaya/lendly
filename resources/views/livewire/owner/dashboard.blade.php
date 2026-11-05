<div>
    <x-page-header :panel="true" eyebrow="Owner interface" title="Owner dashboard" subtitle="Manage your listings, rental requests, and earnings.">
        <x-slot:shortcuts>
            <a href="{{ route('owner.listings.create') }}" wire:navigate class="page-header__icon page-header__icon--label">
                <x-icon name="tag" class="h-5 w-5" aria-hidden="true" />
                <span>New listing</span>
            </a>
        </x-slot:shortcuts>
    </x-page-header>
    <div class="w-full space-y-8 px-4 py-8 sm:px-6 lg:px-8">
        <x-dashboard-progress :interface="$interface" :request-progress="$requestProgress" :member-rentals-by-status="$memberRentalsByStatus" :awaiting-agreement-count="$awaitingAgreementCount" :identity-progress="$identityProgress" :proofs-under-review-count="$proofsUnderReviewCount" />
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-stat-card label="Active listings" :value="$activeListingsCount" icon="tag" accent="blue" />
            <x-stat-card label="Listings awaiting approval" :value="$pendingListingsCount" icon="inbox" accent="amber" />
        </div>
        <section>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Your owner activity</h2>
                <a href="{{ route('owner.listings.index') }}" wire:navigate class="text-xs font-medium text-blue-600 hover:text-blue-800">Manage listings</a>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat-card label="Total earnings" :value="'₱' . number_format($totalEarnings, 2)" hint="After rental refunds and cancellation adjustments" icon="archive" accent="indigo" />
                <x-stat-card label="Pending rental requests" :value="$pendingRequestsCount" icon="inbox" accent="amber" />
                <x-stat-card label="Active rentals" :value="$activeRentalsCount" icon="tag" accent="emerald" />
                <x-stat-card label="Average rating" :value="$averageRating !== null ? number_format($averageRating, 1) . ' ★' : '—'" hint="{{ $totalListingViews }} total listing views" icon="star" accent="rose" />
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-stat-card label="Completed rentals" :value="$completedRentalsCount" icon="archive" accent="emerald" />
                <x-stat-card label="Completed rental earnings" :value="'₱' . number_format($completedRentalEarnings, 2)" hint="Excludes deposits and processing fees" icon="calculator" accent="indigo" />
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-slate-700">Recent completed transactions</h3>
                    <a href="{{ route('owner.rentals.index', ['filter' => 'completed']) }}" wire:navigate class="text-xs font-medium text-blue-600 hover:text-blue-800">View all completed rentals</a>
                </div>
                <div class="mt-3">
                    @if ($completedRentals->isEmpty())
                        <x-empty-state title="No completed rentals yet" message="Your completed transactions and their net earnings will appear here." />
                    @else
                        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <caption class="sr-only">Your five most recently completed rental transactions</caption>
                                <thead class="bg-slate-50 text-left text-xs text-slate-500">
                                    <tr>
                                        <th scope="col" class="px-4 py-3">Rental</th>
                                        <th scope="col" class="px-4 py-3">Renter</th>
                                        <th scope="col" class="px-4 py-3">Completed</th>
                                        <th scope="col" class="px-4 py-3 text-right">Net earnings</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($completedRentals as $rental)
                                        <tr wire:key="completed-rental-{{ $rental->id }}">
                                            <td class="px-4 py-3"><a href="{{ route('owner.rentals.show', $rental) }}" wire:navigate class="font-medium text-blue-600 hover:text-blue-800">#{{ $rental->id }} · {{ $rental->listing->name }}</a></td>
                                            <td class="px-4 py-3 text-slate-600">{{ $rental->renter->name }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $rental->completed_at?->format('M d, Y') ?? '—' }}</td>
                                            <td class="px-4 py-3 text-right font-medium text-slate-800">₱{{ number_format($rental->ownerEarnings(), 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Pipeline</p>
                    <h3 class="mt-0.5 text-base font-bold text-slate-900">Rentals by status</h3>
                    <p class="mt-1 text-xs text-slate-500">Where your rentals sit right now.</p>
                    <div class="mt-5">
                        <x-pie-chart :data="$rentalsByStatus" />
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Breakdown</p>
                    <h3 class="mt-0.5 text-base font-bold text-slate-900">Listings by category</h3>
                    <p class="mt-1 text-xs text-slate-500">What you have listed, across all statuses.</p>
                    <div class="mt-5">
                        <x-stacked-bar-chart :data="$listingsByCategory" />
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Trend</p>
                <h3 class="mt-0.5 text-base font-bold text-slate-900">Earnings, last 6 months</h3>
                <p class="mt-1 text-xs text-slate-500">Net earnings by payment month, adjusted for rental refunds and cancellations. Deposits and processing fees are excluded.</p>
                <div class="mt-5">
                    <x-line-chart :data="$monthlyEarnings" color="#2563eb" />
                </div>
            </div>

            @if ($mostRentedListing || $mostProfitableListing)
                <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @if ($mostRentedListing)
                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                <x-icon name="archive" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="text-xs font-medium text-slate-400">Most rented item</p>
                                <p class="font-semibold text-slate-800">{{ $mostRentedListing->name }}</p>
                                <p class="text-sm text-slate-500">{{ $mostRentedListing->rentals_count }} rental{{ $mostRentedListing->rentals_count === 1 ? '' : 's' }}</p>
                            </div>
                        </div>
                    @endif
                    @if ($mostProfitableListing)
                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                                <x-icon name="tag" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="text-xs font-medium text-slate-400">Most profitable item</p>
                                <p class="font-semibold text-slate-800">{{ $mostProfitableListing->name }}</p>
                                <p class="text-sm text-slate-500">₱{{ number_format($mostProfitableListing->revenue, 2) }} earned</p>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700">Your listings</h3>
                    <a href="{{ route('owner.listings.index') }}" wire:navigate class="text-xs font-medium text-blue-600 hover:text-blue-800">View all</a>
                </div>
                <div class="mt-3">
                    @if ($recentListings->isEmpty())
                        <x-empty-state title="No listings yet" message="Create your first listing to start earning from items you rarely use." />
                    @else
                        <div class="overflow-hidden overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($recentListings as $listing)
                                        <tr wire:key="recent-{{ $listing->id }}">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-3">
                                                    @if ($listing->images->first())
                                                        <img src="{{ $listing->images->first()->url() }}" class="h-10 w-10 rounded-lg object-cover">
                                                    @else
                                                        <div class="h-10 w-10 rounded-lg bg-slate-100"></div>
                                                    @endif
                                                    <span class="font-medium text-slate-800">{{ $listing->name }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <x-category-badge :category="$listing->category" />
                                            </td>
                                            <td class="px-4 py-3">
                                                <x-badge :color="$listing->status->badgeColor()">{{ $listing->status->label() }}</x-badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700">Pending rental requests</h3>
                    <a href="{{ route('owner.rental-requests.index') }}" wire:navigate class="text-xs font-medium text-blue-600 hover:text-blue-800">View all</a>
                </div>
                <div class="mt-3">
                    @if ($pendingRequests->isEmpty())
                        <x-empty-state title="No pending requests" message="Requests from renters will appear here for you to approve or reject." />
                    @else
                        <div class="overflow-hidden overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($pendingRequests as $request)
                                        <tr wire:key="pending-request-{{ $request->id }}">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-3">
                                                    @if ($request->listing->images->first())
                                                        <img src="{{ $request->listing->images->first()->url() }}" class="h-10 w-10 rounded-lg object-cover">
                                                    @else
                                                        <div class="h-10 w-10 rounded-lg bg-slate-100"></div>
                                                    @endif
                                                    <span class="font-medium text-slate-800">{{ $request->listing->name }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-slate-500">{{ $request->renter->name }}</td>
                                            <td class="px-4 py-3 text-slate-500"><x-rental-period :booking="$request" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </section>

    </div>
</div>
