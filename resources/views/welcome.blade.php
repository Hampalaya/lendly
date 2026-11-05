<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Lendly') }} — Rent anything, from anyone nearby</title>
        <link rel="icon" type="image/png" href="{{ asset('images/lendly-icon.png') }}">
        <meta name="description" content="Find useful items nearby, rent for only as long as you need, or earn from the things you already own with Lendly.">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white font-sans text-slate-950 antialiased">
        <main class="overflow-hidden">
            <section class="relative isolate min-h-[calc(100vh-5rem)] overflow-hidden bg-[#f8fbff]">
                <div class="pointer-events-none absolute inset-0 -z-20 bg-[radial-gradient(circle_at_12%_18%,rgba(147,197,253,0.24),transparent_30%),linear-gradient(135deg,#ffffff_0%,#f8fbff_48%,#edf6ff_100%)]"></div>
                <div class="pointer-events-none absolute -bottom-72 -right-48 -z-10 h-[44rem] w-[44rem] rounded-full bg-gradient-to-br from-sky-300/55 via-blue-500/45 to-blue-800/65 blur-3xl"></div>
                <div class="pointer-events-none absolute bottom-0 right-0 -z-10 h-3/5 w-3/5 bg-[radial-gradient(ellipse_at_bottom_right,rgba(29,78,216,0.28),transparent_66%)]"></div>
                <div class="pointer-events-none absolute left-[42%] top-24 -z-10 h-24 w-24 rounded-full border border-blue-200/60"></div>

                <x-public-header />

                <div class="mx-auto grid min-h-[calc(100vh-5rem)] max-w-7xl items-center gap-14 px-4 py-14 sm:px-6 lg:grid-cols-[0.94fr_1.06fr] lg:gap-16 lg:px-8 lg:py-20">
                    <div class="relative z-10 max-w-2xl">
                        <div class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-white/80 px-3.5 py-2 text-xs font-bold uppercase tracking-[0.16em] text-blue-700 shadow-sm backdrop-blur">
                            <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-blue-600"></span></span>
                            Your neighborhood rental marketplace
                        </div>

                        <h1 class="mt-7 text-5xl font-extrabold leading-[0.98] tracking-[-0.055em] text-[#071a3d] sm:text-6xl lg:text-[4.5rem]">
                            Why buy it when you can
                            <span class="relative inline-block text-[#075cf5]">
                                borrow better?
                                <svg class="absolute -bottom-2 left-0 h-3 w-full text-sky-300" viewBox="0 0 280 12" fill="none" aria-hidden="true"><path d="M3 8.5C67 2.5 169 1.5 277 6" stroke="currentColor" stroke-width="5" stroke-linecap="round"/></svg>
                            </span>
                        </h1>

                        <p class="mt-8 max-w-xl text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">Find the things you need nearby, use them for as long as you need, and give them back. Or turn the items sitting at home into extra income.</p>

                        <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <a href="{{ route('listings.index') }}" class="group inline-flex items-center justify-center gap-2 rounded-full bg-[#075cf5] px-6 py-3.5 text-sm font-bold text-white shadow-[0_12px_30px_rgba(7,92,245,0.28)] transition duration-300 hover:-translate-y-1 hover:bg-blue-700 hover:shadow-[0_18px_38px_rgba(7,92,245,0.34)]">
                                Explore nearby items
                                <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4.5 10h11m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white/75 px-6 py-3.5 text-sm font-bold text-slate-700 shadow-sm backdrop-blur transition duration-300 hover:-translate-y-1 hover:border-blue-200 hover:bg-white hover:text-blue-700 hover:shadow-lg">List an item for free</a>
                        </div>

                        <div class="mt-10 flex flex-wrap items-center gap-x-7 gap-y-3 text-sm font-medium text-slate-500">
                            <span class="inline-flex items-center gap-2"><span class="grid h-6 w-6 place-items-center rounded-full bg-emerald-100 text-emerald-700">✓</span> Nearby listings</span>
                            <span class="inline-flex items-center gap-2"><span class="grid h-6 w-6 place-items-center rounded-full bg-emerald-100 text-emerald-700">✓</span> Secure requests</span>
                            <span class="inline-flex items-center gap-2"><span class="grid h-6 w-6 place-items-center rounded-full bg-emerald-100 text-emerald-700">✓</span> Smart pricing</span>
                        </div>
                    </div>

                    <div id="hero-visual" class="relative mx-auto w-full max-w-[620px] lg:mx-0 lg:ml-auto" aria-label="Popular rental items available on Lendly">
                        <div class="absolute -inset-8 rounded-[3rem] bg-blue-400/15 blur-3xl"></div>
                        <div class="hero-frame relative aspect-square overflow-hidden rounded-[2.25rem] border-[10px] border-white bg-cover bg-center shadow-[0_35px_90px_rgba(8,46,112,0.24)] transition-transform duration-300 will-change-transform" style="background-image: url('{{ asset('images/rental-hero-square.png') }}')">
                            <div class="absolute inset-0 bg-gradient-to-tr from-blue-950/20 via-transparent to-white/10"></div>
                            <div class="absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-blue-950/40 to-transparent"></div>
                            <div class="absolute bottom-6 left-6 right-6 flex items-end justify-between gap-3 text-white">
                                <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-100">Popular nearby</p><p class="mt-1 text-lg font-bold sm:text-xl">Borrow more. Own less.</p></div>
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-white/20 backdrop-blur-md ring-1 ring-white/40"><svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4.5 10h11m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            </div>
                        </div>

                        <div class="animate-float-slow absolute -left-5 top-10 rounded-2xl border border-white/70 bg-white/90 p-3.5 shadow-[0_18px_45px_rgba(15,45,95,0.16)] backdrop-blur sm:-left-10 sm:top-16">
                            <div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-100 text-xl">📍</span><span><span class="block text-xs font-bold text-slate-900">Right around the corner</span><span class="mt-0.5 block text-[11px] text-slate-500">Search by distance</span></span></div>
                        </div>

                        <div class="animate-float-reverse absolute -bottom-5 right-2 rounded-2xl border border-white/70 bg-white/90 p-3.5 shadow-[0_18px_45px_rgba(15,45,95,0.18)] backdrop-blur sm:-right-5 sm:bottom-12">
                            <div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-100 text-xl">★</span><span><span class="block text-xs font-bold text-slate-900">Community powered</span><span class="mt-0.5 block text-[11px] text-slate-500">Rent with confidence</span></span></div>
                        </div>
                    </div>
                </div>

                <a href="#how-it-works" class="absolute bottom-5 left-1/2 hidden -translate-x-1/2 items-center gap-2 text-xs font-semibold text-slate-400 transition hover:text-blue-600 xl:flex">See how it works <svg class="h-4 w-4 animate-bounce" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
            </section>

            <section id="how-it-works" class="relative bg-white py-20 sm:py-24">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">One neighborhood, more possibilities</p>
                        <h2 class="mt-3 text-3xl font-extrabold tracking-[-0.035em] text-[#071a3d] sm:text-4xl">Everything you need—without the clutter.</h2>
                        <p class="mt-4 text-base leading-7 text-slate-500">A simpler way to get things done and make the most of what your community already has.</p>
                    </div>

                    <div class="mt-12 grid grid-cols-1 gap-5 md:grid-cols-3">
                        <article class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-7 shadow-[0_12px_40px_rgba(15,23,42,0.06)] transition duration-300 hover:-translate-y-2 hover:border-blue-200 hover:shadow-[0_24px_55px_rgba(37,99,235,0.13)]">
                            <div class="absolute -right-8 -top-8 h-28 w-28 rounded-full bg-blue-100/70 transition duration-500 group-hover:scale-150"></div><span class="relative grid h-12 w-12 place-items-center rounded-2xl bg-blue-600 text-xl text-white shadow-lg shadow-blue-200">⌕</span>
                            <p class="mt-7 text-xs font-bold uppercase tracking-[0.16em] text-blue-600">Discover</p><h3 class="mt-2 text-xl font-bold text-slate-900">Find it nearby</h3><p class="mt-3 text-sm leading-6 text-slate-500">Browse practical items on the map and filter by distance, category, and price.</p>
                        </article>
                        <article class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-7 shadow-[0_12px_40px_rgba(15,23,42,0.06)] transition duration-300 hover:-translate-y-2 hover:border-blue-200 hover:shadow-[0_24px_55px_rgba(37,99,235,0.13)]">
                            <div class="absolute -right-8 -top-8 h-28 w-28 rounded-full bg-sky-100/70 transition duration-500 group-hover:scale-150"></div><span class="relative grid h-12 w-12 place-items-center rounded-2xl bg-sky-500 text-xl text-white shadow-lg shadow-sky-200">↗</span>
                            <p class="mt-7 text-xs font-bold uppercase tracking-[0.16em] text-sky-600">Share</p><h3 class="mt-2 text-xl font-bold text-slate-900">Put idle items to work</h3><p class="mt-3 text-sm leading-6 text-slate-500">Create a listing in minutes and use smart market insight to choose a fair price.</p>
                        </article>
                        <article class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-7 shadow-[0_12px_40px_rgba(15,23,42,0.06)] transition duration-300 hover:-translate-y-2 hover:border-blue-200 hover:shadow-[0_24px_55px_rgba(37,99,235,0.13)]">
                            <div class="absolute -right-8 -top-8 h-28 w-28 rounded-full bg-indigo-100/70 transition duration-500 group-hover:scale-150"></div><span class="relative grid h-12 w-12 place-items-center rounded-2xl bg-indigo-600 text-xl text-white shadow-lg shadow-indigo-200">✓</span>
                            <p class="mt-7 text-xs font-bold uppercase tracking-[0.16em] text-indigo-600">Connect</p><h3 class="mt-2 text-xl font-bold text-slate-900">Rent with confidence</h3><p class="mt-3 text-sm leading-6 text-slate-500">Clear requests, built-in messaging, and accountable transactions keep things simple.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="bg-[#071a3d] py-16 text-white sm:py-20">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-8 px-4 text-center sm:px-6 lg:flex-row lg:px-8 lg:text-left">
                    <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-300">Ready when you are</p><h2 class="mt-3 text-3xl font-extrabold tracking-[-0.035em] sm:text-4xl">There’s more nearby than you think.</h2></div>
                    <a href="{{ route('register') }}" class="group inline-flex shrink-0 items-center gap-2 rounded-full bg-white px-6 py-3.5 text-sm font-bold text-[#071a3d] shadow-xl transition duration-300 hover:-translate-y-1 hover:bg-blue-50">Join Lendly for free <span class="transition-transform duration-300 group-hover:translate-x-1">→</span></a>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white py-7">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 text-center text-sm text-slate-400 sm:flex-row sm:px-6 sm:text-left lg:px-8">
                <p>&copy; {{ date('Y') }} Lendly. Rent smarter, together.</p>
                <div class="flex items-center gap-5"><a href="{{ route('listings.index') }}" class="transition hover:text-blue-600">Browse items</a><a href="{{ route('login') }}" class="transition hover:text-blue-600">Log in</a></div>
            </div>
        </footer>

        <script>
            const heroVisual = document.getElementById('hero-visual');
            const heroFrame = heroVisual?.querySelector('.hero-frame');
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (heroVisual && heroFrame && !reduceMotion && window.matchMedia('(pointer: fine)').matches) {
                heroVisual.addEventListener('pointermove', (event) => {
                    const rect = heroVisual.getBoundingClientRect();
                    const x = (event.clientX - rect.left) / rect.width - 0.5;
                    const y = (event.clientY - rect.top) / rect.height - 0.5;
                    heroFrame.style.transform = `perspective(900px) rotateX(${y * -4}deg) rotateY(${x * 4}deg) translateY(-3px)`;
                });
                heroVisual.addEventListener('pointerleave', () => { heroFrame.style.transform = ''; });
            }
        </script>
    </body>
</html>
