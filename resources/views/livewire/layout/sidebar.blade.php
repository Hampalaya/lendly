<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    // Reject calls from stale pages; demo account impersonation is retired.
    public function switchDemoUser(string $role): void
    {
        abort(403);
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div
    class="app-sidebar"
    x-data="{
        open: false,
        accountMenuOpen: false,
        collapsed: localStorage.getItem('sidebar-collapsed') === 'true',
        toggleCollapsed() {
            this.accountMenuOpen = false;
            this.collapsed = ! this.collapsed;
            localStorage.setItem('sidebar-collapsed', this.collapsed);
        },
    }"
    @toggle-sidebar.window="open = ! open; accountMenuOpen = false"
    @close-sidebar.window="open = false; accountMenuOpen = false">
    <!-- Mobile overlay -->
    <div x-show="open" x-transition.opacity @click="open = false; accountMenuOpen = false" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" style="display: none;"></div>

    <aside
        :class="[open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', collapsed ? 'lg:w-20' : 'lg:w-60']"
        class="fixed inset-y-0 left-0 z-40 flex w-60 max-w-[calc(100vw-5rem)] shrink-0 flex-col overflow-visible border-r border-indigo-900 bg-gradient-to-b from-indigo-950 to-indigo-900 transition-all duration-200 ease-in-out lg:translate-x-0">
        <div class="relative flex h-20 shrink-0 items-center justify-between border-b border-indigo-900 px-4" :class="collapsed && 'lg:justify-center lg:px-0'">
            <a href="{{ auth()->check() ? route(auth()->user()->dashboardRouteName()) : route('listings.index') }}" wire:navigate class="group flex min-w-0 items-center gap-2.5" aria-label="Lendly dashboard">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-white/80 bg-white p-0.5 shadow-[0_5px_16px_rgba(37,99,235,0.35)] transition duration-200 group-hover:-translate-y-0.5 group-hover:shadow-[0_8px_20px_rgba(59,130,246,0.45)]">
                    <img src="{{ asset('images/lendlylogo_transparent.png') }}" alt="Lendly logo" class="h-full w-full scale-[1.35] object-contain" />
                </span>
                <span class="min-w-0" :class="collapsed && 'lg:hidden'">
                    <span class="block text-lg font-bold leading-6 tracking-tight text-white">Lendly</span>
                    <span class="block truncate text-[10px] font-medium leading-4 tracking-wide text-indigo-300">{{ auth()->check() ? (auth()->user()->isAdmin() ? __('Admin interface') : ucfirst(auth()->user()->activeInterface()).' interface') : __('Rent · Share · Earn') }}</span>
                </span>
            </a>
            <button type="button" @click="open = false; accountMenuOpen = false" class="rounded-md p-1 text-indigo-300 hover:bg-indigo-900 hover:text-white lg:hidden" aria-label="Close sidebar">
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>

        <button
            type="button"
            @click="toggleCollapsed()"
            class="absolute -right-3 top-7 z-10 hidden h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-400 shadow-sm hover:text-blue-600 lg:flex"
            :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
            <x-icon name="chevron-down" class="h-3.5 w-3.5 transition-transform" x-bind:class="{ '-rotate-90': !collapsed, 'rotate-90': collapsed }" />
        </button>

        @if (auth()->check() && ! auth()->user()->isAdmin())
            <form method="POST" action="{{ route('interface.switch') }}" class="border-b border-indigo-900 px-3 py-3">
                @csrf
                <input type="hidden" name="interface" value="{{ auth()->user()->activeInterface() === 'owner' ? 'renter' : 'owner' }}" />
                <button type="submit" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-indigo-600 bg-indigo-800 px-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60" aria-label="Switch to {{ auth()->user()->activeInterface() === 'owner' ? 'renter' : 'owner' }} interface" title="Switch interface">
                    <span aria-hidden="true">⇄</span><span x-show="!collapsed" x-cloak>Switch to {{ auth()->user()->activeInterface() === 'owner' ? 'renter' : 'owner' }}</span>
                </button>
            </form>
        @endif

        <nav class="flex-1 space-y-1 overflow-y-auto overflow-x-hidden px-3 py-4">
            @if (! auth()->check() || (! auth()->user()->isAdmin() && auth()->user()->activeInterface() === 'renter'))
            <x-sidebar-link :href="route('listings.index')" :active="request()->routeIs('listings.*') && ! request()->routeIs('listings.message', 'listings.contact')" icon="search" wire:navigate>
                {{ __('Browse') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('map')" :active="request()->routeIs('map')" icon="map-pin" wire:navigate>
                {{ __('Map') }}
            </x-sidebar-link>
            @endif

            @auth
            <x-sidebar-link :href="route(auth()->user()->dashboardRouteName())" :active="request()->routeIs('*.dashboard') || request()->routeIs('dashboard')" icon="home" wire:navigate>
                {{ __('Dashboard') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')" icon="bell" wire:navigate>
                {{ __('Notifications') }}
            </x-sidebar-link>

            @if (auth()->user()->isRenter() || auth()->user()->isOwner())
            <x-sidebar-link :href="route('messages.index')" :active="request()->routeIs('messages.*', 'listings.message', 'listings.contact', 'listing-conversations.show', 'rental-requests.chat')" icon="chat" wire:navigate>
                {{ __('Messages') }}
                @if (auth()->user()->unreadMessagesCount() > 0)
                <x-slot:badge>{{ auth()->user()->unreadMessagesCount() > 9 ? '9+' : auth()->user()->unreadMessagesCount() }}</x-slot:badge>
                @endif
            </x-sidebar-link>
            @endif

            @if (auth()->user()->activeInterface() === 'renter')
            <p class="flex items-center gap-1.5 px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wider text-indigo-400" x-show="!collapsed" x-cloak>
                <span class="h-1.5 w-1.5 rounded-full bg-blue-400"></span> Renting
            </p>
            <x-sidebar-link :href="route('renter.cart.index')" :active="request()->routeIs('renter.cart.*')" icon="cart" wire:navigate>
                {{ __('My Cart') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('renter.rental-requests.index')" :active="request()->routeIs('renter.rental-requests.*')" icon="inbox" wire:navigate>
                {{ __('My Requests') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('renter.rentals.index')" :active="request()->routeIs('renter.rentals.*')" icon="archive" wire:navigate>
                {{ __('My Rentals') }}
            </x-sidebar-link>
            @endif

            @if (auth()->user()->activeInterface() === 'owner')
            <p class="flex items-center gap-1.5 px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wider text-indigo-400" x-show="!collapsed" x-cloak>
                <span class="h-1.5 w-1.5 rounded-full bg-violet-400"></span> Owning
            </p>
            <x-sidebar-link :href="route('owner.listings.index')" :active="request()->routeIs('owner.listings.*')" icon="tag" wire:navigate>
                {{ __('My Listings') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('owner.rental-requests.index')" :active="request()->routeIs('owner.rental-requests.*')" icon="inbox" wire:navigate>
                {{ __('Rental Requests') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('owner.rentals.index')" :active="request()->routeIs('owner.rentals.*')" icon="archive" wire:navigate>
                {{ __('My Rentals') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('owner.payments.settings')" :active="request()->routeIs('owner.payments.*')" icon="calculator" wire:navigate>
                {{ __('Payment QR') }}
            </x-sidebar-link>
            @endif

            @if (auth()->user()->isAdmin())
            <p class="flex items-center gap-1.5 px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wider text-indigo-400" x-show="!collapsed" x-cloak>
                <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span> Administration
            </p>
            <x-sidebar-link :href="route('admin.listings.index')" :active="request()->routeIs('admin.listings.*')" icon="list" wire:navigate>
                {{ __('Listings') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('admin.identity-submissions.index')" :active="request()->routeIs('admin.identity-submissions.*')" icon="user-circle" wire:navigate>
                {{ __('ID Reviews') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('admin.rentals.index')" :active="request()->routeIs('admin.rentals.*')" icon="archive" wire:navigate>
                {{ __('Transactions') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('admin.damage-reports.index')" :active="request()->routeIs('admin.damage-reports.*')" icon="exclamation-triangle" wire:navigate>
                {{ __('Damage Reports') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('admin.disputes.index')" :active="request()->routeIs('admin.disputes.*')" icon="scale" wire:navigate>
                {{ __('Disputes') }}
            </x-sidebar-link>
            @endif
            @endauth
        </nav>

        <div class="relative shrink-0 border-t border-indigo-900 p-3">
            @auth
            <div
                x-data="{{ json_encode(['name' => auth()->user()->name]) }}"
                x-id="['sidebar-account-menu']"
                @profile-updated.window="name = $event.detail.name"
                @click.outside="accountMenuOpen = false"
                @keydown.escape.stop.prevent="if (accountMenuOpen) { accountMenuOpen = false; $refs.accountMenuTrigger.focus() }"
                @focusout="if (!$el.contains($event.relatedTarget)) accountMenuOpen = false">
                <div class="flex items-center gap-1" :class="collapsed && 'lg:flex-col'">
                    <a href="{{ route('profile') }}" wire:navigate aria-label="{{ __('View profile') }}" title="{{ __('View profile') }}" class="flex min-w-0 flex-1 items-center gap-2.5 rounded-xl p-2 transition hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300" :class="collapsed && 'lg:w-full lg:justify-center lg:px-0'">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-100 text-sm font-semibold text-blue-700 ring-2 ring-white/10">
                            @if (auth()->user()->avatar_path)
                                <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ __('Your profile photo') }}" class="h-full w-full object-cover">
                            @else
                                <span x-text="name.trim().charAt(0).toLocaleUpperCase()">{{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                            @endif
                        </span>
                        <span class="min-w-0 flex-1" :class="collapsed && 'lg:hidden'">
                            <span class="block truncate text-sm font-semibold text-white" x-text="name"></span>
                            <span class="mt-0.5 block truncate text-[10px] leading-4 text-indigo-300">{{ auth()->user()->email }}</span>
                        </span>
                    </a>
                    <button
                        x-ref="accountMenuTrigger"
                        type="button"
                        aria-label="{{ __('Account menu') }}"
                        title="{{ __('Account menu') }}"
                        aria-haspopup="menu"
                        :aria-controls="$id('sidebar-account-menu')"
                        :aria-expanded="accountMenuOpen"
                        @click="accountMenuOpen = !accountMenuOpen; if (accountMenuOpen) $nextTick(() => $refs.profileAction.focus())"
                        @keydown.arrow-down.prevent="accountMenuOpen = true; $nextTick(() => $refs.profileAction.focus())"
                        @keydown.arrow-up.prevent="accountMenuOpen = true; $nextTick(() => $refs.logoutAction.focus())"
                        class="flex h-9 w-7 shrink-0 items-center justify-center rounded-lg text-indigo-300 transition hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300"
                        :class="[accountMenuOpen && 'bg-white/10 text-white', collapsed && 'lg:h-6 lg:w-10']">
                        <x-icon name="chevron-down" class="h-4 w-4 transition-transform" ::class="accountMenuOpen ? 'rotate-90' : '-rotate-90'" />
                    </button>
                </div>

                <div
                    x-show="accountMenuOpen"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="translate-x-1 opacity-0"
                    x-transition:enter-end="translate-x-0 opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="translate-x-0 opacity-100"
                    x-transition:leave-end="translate-x-1 opacity-0"
                    :id="$id('sidebar-account-menu')"
                    role="menu"
                    aria-label="{{ __('Account menu') }}"
                    aria-orientation="vertical"
                    @keydown.home.prevent="$refs.profileAction.focus()"
                    @keydown.end.prevent="$refs.logoutAction.focus()"
                    class="absolute bottom-3 left-full z-50 ml-2 flex w-14 flex-col items-center gap-1 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-indigo-950/15">
                    <a
                        x-ref="profileAction"
                        href="{{ route('profile') }}"
                        wire:navigate
                        role="menuitem"
                        aria-label="{{ __('Profile') }}"
                        title="{{ __('Profile') }}"
                        @click="accountMenuOpen = false"
                        @keydown.arrow-down.prevent="$refs.logoutAction.focus()"
                        @keydown.arrow-up.prevent="$refs.logoutAction.focus()"
                        class="flex h-10 w-10 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50 focus:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500">
                        <x-icon name="user-circle" class="h-5 w-5" aria-hidden="true" />
                    </a>
                    <button
                        x-ref="logoutAction"
                        type="button"
                        wire:click="logout"
                        wire:loading.attr="disabled"
                        wire:target="logout"
                        role="menuitem"
                        aria-label="{{ __('Log Out') }}"
                        title="{{ __('Log Out') }}"
                        @click="accountMenuOpen = false"
                        @keydown.arrow-down.prevent="$refs.profileAction.focus()"
                        @keydown.arrow-up.prevent="$refs.profileAction.focus()"
                        class="flex h-10 w-10 items-center justify-center rounded-lg text-rose-600 transition hover:bg-rose-50 focus:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-rose-500 disabled:opacity-50">
                        <x-icon name="logout" class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>
            </div>

            @else
            <div class="space-y-2" x-show="!collapsed" x-cloak>
                <a href="{{ route('login') }}" wire:navigate class="block rounded-lg border border-indigo-700 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-indigo-900">
                    {{ __('Log in') }}
                </a>
                <a href="{{ route('register') }}" wire:navigate class="block rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                    {{ __('Get started') }}
                </a>
            </div>

            <a href="{{ route('login') }}" wire:navigate x-show="collapsed" x-cloak class="hidden items-center justify-center rounded-lg p-2 text-indigo-300 hover:bg-indigo-900" title="{{ __('Log in') }}">
                <x-icon name="user-circle" class="h-6 w-6" />
            </a>
            @endauth
        </div>
    </aside>
</div>
