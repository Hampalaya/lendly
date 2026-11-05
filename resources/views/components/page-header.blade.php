@props(['eyebrow' => null, 'title', 'subtitle' => null, 'maxWidth' => 'w-full', 'panel' => false])

@php
    $showCart = auth()->check() && ! auth()->user()->isAdmin() && auth()->user()->activeInterface() === 'renter';
@endphp

@if (! $panel)
    <div {{ $attributes->class(['mx-auto flex w-full shrink-0 flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 sm:flex-nowrap sm:px-6 lg:px-8', $maxWidth]) }}>
        <button type="button" @click="$dispatch('toggle-sidebar')" class="page-header__menu lg:hidden" aria-label="Open navigation">
            <x-icon name="menu" class="h-5 w-5" aria-hidden="true" />
        </button>

        <div class="min-w-0 flex-1">
            <h1 class="break-words text-lg font-bold tracking-tight text-[#071a3d] sm:text-xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-0.5 break-words text-sm text-slate-600">{{ $subtitle }}</p>
            @endif
        </div>

        @if (isset($actions) || auth()->check())
            <div @class(['ml-auto flex shrink-0 items-center justify-end gap-2', 'max-sm:basis-full' => isset($actions)])>
                {{ $actions ?? '' }}
                @auth
                    <nav class="page-heading__shortcuts relative z-20 flex items-center gap-1.5" aria-label="Account shortcuts">
                        <x-page-account-shortcuts :show-cart="$showCart" />
                    </nav>
                @endauth
            </div>
        @endif
    </div>
@else
<header {{ $attributes->class(['page-header', 'page-header--renter' => $showCart, 'page-header--guest' => ! auth()->check(), 'page-header--with-shortcuts' => isset($shortcuts)]) }}>
    <x-header-background />

    <div class="page-header__inner">
        <div class="page-header__content">
            <button type="button" @click="$dispatch('toggle-sidebar')" class="page-header__menu lg:hidden" aria-label="Open navigation">
                <x-icon name="menu" class="h-5 w-5" aria-hidden="true" />
            </button>

            <div class="min-w-0 {{ $maxWidth }}">
                @if ($eyebrow)
                    <p class="page-header__eyebrow"><span aria-hidden="true"></span>{{ $eyebrow }}</p>
                @endif
                <h1 class="page-header__title">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="page-header__subtitle">{{ $subtitle }}</p>
                @endif
                @isset($actions)
                    <div class="mt-1 text-xs">{{ $actions }}</div>
                @endisset
            </div>
        </div>

        <nav @class(['page-header__actions', 'page-header__actions--shortcuts' => auth()->check()]) aria-label="{{ auth()->check() ? 'Account shortcuts' : 'Account access' }}">
            @auth
                @isset($shortcuts)
                    {{ $shortcuts }}
                @endisset
                <x-page-account-shortcuts :show-cart="$showCart" />
            @else
                <a href="{{ route('login') }}" wire:navigate class="page-header__login">Log in</a>
                <a href="{{ route('register') }}" wire:navigate class="page-header__signup">Join Lendly</a>
            @endauth
        </nav>
    </div>
</header>
@endif
