<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Lendly') }} — {{ request()->routeIs('register') ? 'Create your account' : 'Welcome back' }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/lendly-icon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#f8fbff] font-sans text-slate-950 antialiased">
        <main class="relative min-h-dvh overflow-hidden lg:grid lg:grid-cols-[1.02fr_0.98fr]">
            <section class="auth-poster relative hidden gap-8 overflow-hidden px-10 py-9 text-white lg:flex lg:flex-col xl:px-16">
                <a href="/" wire:navigate class="relative z-10 inline-flex w-fit items-center transition duration-300 hover:-translate-y-0.5 hover:scale-[1.03]" aria-label="Return to Lendly home">
                    <img src="{{ asset('images/lendlylogo_transparent.png') }}" alt="Lendly" class="h-24 w-auto object-contain" />
                </a>

                <div class="relative z-10 my-auto grid items-center gap-8 xl:grid-cols-[0.9fr_1.1fr] xl:gap-10">
                    <div>
                        <p class="inline-flex items-center gap-2 rounded-full border border-white/70 bg-white/95 px-3 py-2 text-[11px] font-bold uppercase tracking-[0.16em] text-blue-900">
                            <span class="h-2 w-2 shrink-0 rounded-full bg-blue-600" aria-hidden="true"></span>
                            Your neighborhood marketplace
                        </p>
                        <h1 class="mt-6 text-4xl font-extrabold leading-[1.03] tracking-[-0.045em] xl:text-5xl">
                            Borrow what you need.
                            <span class="mt-2 block text-sky-300">Share what you have.</span>
                        </h1>
                        <p class="mt-5 max-w-md text-sm leading-6 text-blue-100/75 xl:text-base xl:leading-7">Useful things are already nearby. Lendly helps your community rent smarter, save more, and own less.</p>

                        <div class="mt-7 space-y-3 text-sm font-medium text-blue-50/90">
                            <div class="flex items-center gap-3"><span class="grid h-7 w-7 place-items-center rounded-full bg-emerald-400/15 text-emerald-300 ring-1 ring-emerald-300/20">✓</span> Find items close to home</div>
                            <div class="flex items-center gap-3"><span class="grid h-7 w-7 place-items-center rounded-full bg-emerald-400/15 text-emerald-300 ring-1 ring-emerald-300/20">✓</span> Make extra income from idle gear</div>
                            <div class="flex items-center gap-3"><span class="grid h-7 w-7 place-items-center rounded-full bg-emerald-400/15 text-emerald-300 ring-1 ring-emerald-300/20">✓</span> Rent with a connected community</div>
                        </div>
                    </div>

                    <div class="relative mx-auto hidden w-full max-w-[23rem] xl:block">
                        <div class="absolute -inset-5 rounded-[2.5rem] bg-blue-400/20 blur-2xl"></div>
                        <div class="relative aspect-square overflow-hidden rounded-[2rem] border-[8px] border-white/90 bg-cover bg-center shadow-[0_30px_70px_rgba(0,0,0,0.35)]" style="background-image: url('{{ asset('images/rental-hero-square.png') }}')">
                            <div class="absolute inset-0 bg-gradient-to-t from-blue-950/55 via-transparent to-white/5"></div>
                            <div class="absolute bottom-5 left-5 right-5">
                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-sky-200">Popular nearby</p>
                                <p class="mt-1 text-lg font-bold">Borrow more. Own less.</p>
                            </div>
                        </div>
                        <div class="animate-float-slow absolute -left-8 top-8 rounded-2xl border border-white/20 bg-white/90 px-3 py-2.5 text-slate-900 shadow-xl backdrop-blur">
                            <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-blue-600">Nearby</p>
                            <p class="mt-0.5 text-xs font-semibold">Ready when you are</p>
                        </div>
                    </div>
                </div>

                <p class="relative z-10 text-xs text-blue-200/55">© {{ date('Y') }} Lendly · Rent smarter, together.</p>
            </section>

            <section class="relative flex min-h-dvh items-center justify-center px-4 py-8 sm:px-8 lg:px-10 xl:px-16">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_90%_100%,rgba(59,130,246,0.16),transparent_36%),radial-gradient(circle_at_12%_8%,rgba(186,230,253,0.35),transparent_27%)]"></div>

                <div class="relative z-10 w-full {{ request()->routeIs('register') ? 'max-w-2xl' : 'max-w-md' }}">
                    <div class="mb-7 flex items-center justify-between lg:hidden">
                        <a href="/" wire:navigate class="inline-flex items-center transition duration-300 hover:scale-[1.03]" aria-label="Return to Lendly home">
                            <img src="{{ asset('images/lendlylogo_transparent.png') }}" alt="Lendly" class="h-20 w-auto object-contain drop-shadow-[0_4px_10px_rgba(30,64,175,0.16)]" />
                        </a>
                        <a href="/" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition hover:text-blue-700">
                            <span aria-hidden="true">←</span> Back home
                        </a>
                    </div>

                    <div class="rounded-[2rem] border border-white bg-white/90 p-6 shadow-[0_28px_80px_rgba(15,45,95,0.13)] backdrop-blur-xl sm:p-8 xl:p-10">
                        {{ $slot }}
                    </div>

                    <p class="mt-6 text-center text-xs text-slate-400 lg:hidden">© {{ date('Y') }} Lendly · Rent smarter, together.</p>
                </div>
            </section>
        </main>
    </body>
</html>
