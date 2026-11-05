@props(['showCart'])

<livewire:notifications.bell />

@if ($showCart)
    @php($cartCount = auth()->user()->cartItems()->count())
    <a href="{{ route('renter.cart.index') }}" wire:navigate aria-label="My cart, {{ $cartCount }} items" title="My cart" class="page-header__icon" @if (request()->routeIs('renter.cart.*')) aria-current="page" @endif>
        <x-icon name="cart" class="h-6 w-6" aria-hidden="true" />
        @if ($cartCount)
            <span class="page-header__count">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
        @endif
    </a>
@endif
