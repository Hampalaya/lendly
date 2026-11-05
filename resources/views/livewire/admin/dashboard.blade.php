<div>
    <x-page-header :panel="true" eyebrow="Administration" title="Admin dashboard" subtitle="Platform overview and user management." />

    <div class="w-full space-y-8 px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <x-stat-card label="Total users" :value="$totalUsers" hint="{{ $suspendedUsers }} suspended" icon="user-circle" accent="blue" />
            <x-stat-card label="Active listings" :value="$activeListingsCount" hint="{{ $pendingListingsCount }} pending approval" icon="list" accent="blue" />
            <x-stat-card label="Active rentals" :value="$activeRentalsCount" hint="{{ $overdueRentalsCount }} overdue" icon="archive" accent="emerald" />
            <x-stat-card label="Completed rentals" :value="$completedRentalsCount" icon="archive" accent="emerald" />
            <x-stat-card label="Accrued processing fees" :value="'₱' . number_format($platformRevenue, 2)" hint="Earned fees; owner-wallet remittances are handled manually" icon="archive" accent="indigo" />
            <x-stat-card label="Open disputes" :value="$openDisputesCount" icon="scale" accent="rose" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <x-icon name="chart-bar" class="h-4 w-4" />
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Trend</p>
                        <h2 class="text-base font-bold text-slate-900">Accrued processing fees, last 6 months</h2>
                    </div>
                </div>
                <div class="mt-5">
                    <x-line-chart :data="$monthlyRevenue" color="#2563eb" />
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="user-circle" class="h-4 w-4" />
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Breakdown</p>
                        <h2 class="text-base font-bold text-slate-900">Member activity</h2>
                    </div>
                </div>
                <div class="mt-5">
                    <x-bar-chart :data="$usersByRole" />
                </div>
            </div>
        </div>

        @if ($rentalsByStatus->isNotEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <x-icon name="archive" class="h-4 w-4" />
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Pipeline</p>
                        <h2 class="text-base font-bold text-slate-900">Rentals by status</h2>
                    </div>
                </div>
                <div class="mt-5">
                    <x-pie-chart :data="$rentalsByStatus" />
                </div>
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <x-icon name="user-circle" class="h-4 w-4" />
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Directory</p>
                        <h2 class="text-base font-bold text-slate-900">Users</h2>
                    </div>
                </div>
            </div>

            <x-record-toolbar class="mt-3" :filter="$userStatus" :sort="$recordSort" :filters="['all' => 'All Accounts', 'active' => 'Active', 'suspended' => 'Suspended']" search-model="search" filter-model="userStatus" placeholder="Search by name or email..." search-label="Search users" label="User directory controls" />
            <div class="mt-4 overflow-hidden overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="border-b-2 border-indigo-100 bg-indigo-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-indigo-700">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-indigo-700">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-indigo-700">Role</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-indigo-700">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-indigo-700">Joined</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-indigo-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($users as $user)
                            <tr wire:key="user-{{ $user->id }}" class="hover:bg-slate-50/75">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">
                                            {{ Str::of($user->name)->substr(0, 1)->upper() }}
                                        </span>
                                        <span class="font-medium text-slate-800">{{ $user->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ $user->email }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $user->role->label() }}</td>
                                <td class="px-4 py-3">
                                    @if ($user->isSuspended())
                                        <x-badge color="red">Suspended</x-badge>
                                    @else
                                        <x-badge color="green">Active</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ $user->created_at->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if (! $user->isAdmin() && $user->id !== auth()->id())
                                        <button
                                            type="button"
                                            wire:click="toggleSuspension({{ $user->id }})"
                                            wire:confirm="Are you sure you want to {{ $user->isSuspended() ? 'reactivate' : 'suspend' }} {{ $user->name }}?"
                                            class="text-xs font-medium {{ $user->isSuspended() ? 'text-blue-600 hover:text-blue-800' : 'text-rose-600 hover:text-rose-800' }}"
                                        >
                                            {{ $user->isSuspended() ? 'Reactivate' : 'Suspend' }}
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</div>
