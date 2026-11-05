<header class="relative z-10 bg-blue-200/60 shadow-sm shadow-blue-900/5 backdrop-blur-md">
    <nav class="mx-auto flex min-h-20 w-full max-w-7xl flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-2 sm:px-6 lg:px-8" aria-label="Primary navigation">
        <a href="/" wire:navigate class="inline-flex items-center gap-2 font-bold tracking-tight text-[#071a3d]" aria-label="Lendly home">
            <img src="{{ asset('images/lendlylogo.png') }}" alt="" class="h-10 w-10 rounded-lg object-contain" />
            <span>Lendly</span>
        </a>

        <div class="flex flex-wrap items-center gap-1 text-sm font-semibold sm:gap-2">
            <a href="{{ route('listings.index') }}" wire:navigate class="hidden rounded-lg px-3 py-2 text-slate-700 hover:bg-blue-50 sm:inline-flex">Browse items</a>
            @auth
                <a href="{{ route(auth()->user()->dashboardRouteName()) }}" wire:navigate class="rounded-lg px-3 py-2 text-blue-700 hover:bg-blue-50">Dashboard</a>
            @else
                <a href="{{ route('login') }}" wire:navigate class="rounded-lg px-3 py-2 text-slate-700 hover:bg-blue-50">Log in</a>
                <a href="{{ route('register') }}" wire:navigate class="rounded-lg px-3 py-2 text-blue-700 hover:bg-blue-50">Get started</a>
            @endauth
        </div>
    </nav>
</header>
