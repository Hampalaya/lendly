@php
    $accentSolid = [
        'amber' => 'border-transparent bg-amber-600 text-white shadow-sm',
        'violet' => 'border-transparent bg-violet-600 text-white shadow-sm',
        'fuchsia' => 'border-transparent bg-fuchsia-600 text-white shadow-sm',
        'emerald' => 'border-transparent bg-emerald-600 text-white shadow-sm',
        'rose' => 'border-transparent bg-rose-600 text-white shadow-sm',
        'cyan' => 'border-transparent bg-cyan-600 text-white shadow-sm',
        'teal' => 'border-transparent bg-teal-600 text-white shadow-sm',
        'slate' => 'border-transparent bg-slate-600 text-white shadow-sm',
    ];
    $accentText = [
        'amber' => 'text-amber-600',
        'violet' => 'text-violet-600',
        'fuchsia' => 'text-fuchsia-600',
        'emerald' => 'text-emerald-600',
        'rose' => 'text-rose-600',
        'cyan' => 'text-cyan-600',
        'teal' => 'text-teal-600',
        'slate' => 'text-slate-600',
    ];
@endphp

<div x-data="{
    filtersOpen: false,
    headerHeight: 128,
    searchPanelHeight: 80,
    resizeObserver: null,
    init() {
        this.$nextTick(() => {
            this.resizeObserver = new ResizeObserver(() => {
                this.headerHeight = this.$root.querySelector('header').offsetHeight;
                this.searchPanelHeight = this.$refs.searchPanel.offsetHeight;
            });
            this.resizeObserver.observe(this.$root.querySelector('header'));
            this.resizeObserver.observe(this.$refs.searchPanel);
        });
    },
    destroy() {
        this.resizeObserver?.disconnect();
    },
}">
    <x-page-header
        :panel="true"
        eyebrow="Lendly marketplace"
        title="Find what you need to rent"
        :subtitle="$listings->total() . ' item' . ($listings->total() === 1 ? '' : 's') . ' found. Check each item’s availability.'"
    />

    <div class="w-full px-4 py-6 sm:px-6 lg:px-8">
        <div x-ref="searchPanel" :style="{ top: (headerHeight + 16) + 'px' }"
             class="sticky z-10 rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-[0_12px_36px_rgba(15,45,95,0.14)] backdrop-blur-xl">
            <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap">
                <div class="relative min-w-0 flex-1 lg:min-w-64">
                    <x-icon name="search" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                    <input type="text" wire:model.live.debounce.400ms="keyword" placeholder="Search for tools, cameras, tents..." aria-label="Search items"
                           class="w-full rounded-lg border-slate-300 py-3 pl-11 pr-4 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <select wire:model.live="rentalUnit" aria-label="Rental pricing unit" class="rounded-lg border-slate-300 text-sm lg:w-40"><option value="day">Rent by day</option><option value="hour">Rent by hour</option></select>
                <div class="relative min-w-0 lg:w-56">
                    <x-icon name="map-pin" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                    <input type="text" wire:model.live.debounce.400ms="location" placeholder="City or area" aria-label="Filter by city or area"
                           class="w-full rounded-lg border-slate-300 py-3 pl-11 pr-4 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button type="button" @click="filtersOpen = ! filtersOpen" :aria-expanded="filtersOpen" aria-controls="listing-filters"
                            class="flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        <x-icon name="filter" class="h-4 w-4" />
                        Filters
                    </button>

                    <div class="flex gap-4">
                        <button type="button" wire:click="$refresh"
                                class="flex-1 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-3 text-sm font-semibold text-white shadow-sm hover:from-blue-700 hover:to-indigo-700 sm:flex-none">
                            Search
                        </button>

                        <a href="{{ route('map') }}" wire:navigate class="flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 sm:flex-none">
                            <x-icon name="map-pin" class="h-4 w-4" />
                            View on Map
                        </a>
                    </div>
                </div>
            </div>

            <div id="listing-filters" x-show="filtersOpen" x-transition class="mt-4 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_minmax(18rem,1.5fr)]" style="display: none;">
                <select wire:model.live="condition" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Any condition</option>
                    @foreach ($conditions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>

                <select wire:model.live="brand" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Any brand</option>
                    @foreach ($brands as $brandOption)
                        <option value="{{ $brandOption }}">{{ $brandOption }}</option>
                    @endforeach
                </select>

                <select wire:model.live="sort" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="recent">Recently added</option>
                    <option value="cheapest">Cheapest</option>
                    <option value="popular">Most popular</option>
                </select>

                <div class="flex min-w-0 items-center gap-2">
                    <input type="number" wire:model.live.debounce.400ms="minPrice" placeholder="Min ₱/{{ $rentalUnit === 'hour' ? 'hour' : 'day' }}" aria-label="Minimum price per selected rental unit" class="min-w-0 w-full rounded-lg border-slate-300 px-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <span class="text-slate-400">–</span>
                    <input type="number" wire:model.live.debounce.400ms="maxPrice" placeholder="Max ₱/{{ $rentalUnit === 'hour' ? 'hour' : 'day' }}" aria-label="Maximum price per selected rental unit" class="min-w-0 w-full rounded-lg border-slate-300 px-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <button type="button" wire:click="resetFilters" aria-label="Clear filters" title="Clear filters"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-500 shadow-sm hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <x-icon name="x-mark" class="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-6 lg:grid lg:grid-cols-[14rem_minmax(0,1fr)] lg:items-start lg:gap-6">
            <aside :style="{ top: (headerHeight + searchPanelHeight + 32) + 'px' }" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:sticky">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-600">Explore</p>
                        <h2 class="mt-1 text-base font-bold text-[#071a3d]">Categories</h2>
                    </div>
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600">
                        <x-icon name="filter" class="h-4 w-4" />
                    </span>
                </div>

                <div class="-mx-1 mt-4 flex gap-2 overflow-x-auto px-1 pb-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0">
                    <button type="button" wire:click="$set('category', '')"
                            class="group flex shrink-0 items-center gap-2.5 rounded-xl border px-3.5 py-2.5 text-left text-xs font-semibold transition duration-200 lg:w-full {{ $category === '' ? 'border-transparent bg-blue-600 text-white shadow-[0_7px_18px_rgba(37,99,235,0.24)]' : 'border-slate-200 bg-white text-slate-600 hover:-translate-y-0.5 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 lg:hover:translate-x-1 lg:hover:translate-y-0' }}">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg {{ $category === '' ? 'bg-white/15' : 'bg-slate-50 text-blue-500 group-hover:bg-white' }}">
                            <x-icon name="sparkles" class="h-3.5 w-3.5" />
                        </span>
                        <span>All items</span>
                    </button>

                    @foreach ($categories as $cat)
                        @php($style = $cat->style())
                        <button type="button" wire:click="$set('category', '{{ $cat->id }}')"
                                class="group flex shrink-0 items-center gap-2.5 rounded-xl border px-3.5 py-2.5 text-left text-xs font-semibold transition duration-200 lg:w-full {{ (string) $category === (string) $cat->id ? ($accentSolid[$style['accent']] ?? $accentSolid['slate']) : 'border-slate-200 bg-white text-slate-600 hover:-translate-y-0.5 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 lg:hover:translate-x-1 lg:hover:translate-y-0' }}">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg {{ (string) $category === (string) $cat->id ? 'bg-white/15 text-white' : 'bg-slate-50 ' . ($accentText[$style['accent']] ?? $accentText['slate']) . ' group-hover:bg-white' }}">
                                <x-icon :name="$style['icon']" class="h-3.5 w-3.5" />
                            </span>
                            <span>{{ $cat->name }}</span>
                        </button>
                    @endforeach
                </div>

                @if ($category !== '')
                    <button type="button" wire:click="$set('category', '')" class="mt-4 hidden w-full items-center justify-center gap-1.5 border-t border-slate-100 pt-3 text-xs font-semibold text-slate-400 transition hover:text-blue-600 lg:flex">
                        Clear category
                        <span aria-hidden="true">×</span>
                    </button>
                @endif
            </aside>

            <section class="mt-5 min-w-0 lg:mt-0">
            @if ($listings->isEmpty())
                <x-empty-state title="No listings match your filters" message="Try widening your search or clearing some filters.">
                    <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-300">
                        <x-icon name="search" class="h-6 w-6" />
                    </span>
                </x-empty-state>
            @else
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($listings as $listing)
                        @php($style = $listing->category->style())
                        <a href="{{ route('listings.show', $listing) }}" wire:navigate wire:key="listing-{{ $listing->id }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                            <div class="relative aspect-[4/3] w-full overflow-hidden bg-slate-100">
                                @if ($listing->images->first())
                                    <img src="{{ $listing->images->first()->url() }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-slate-300">No photo</div>
                                @endif
                                <div class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/40 to-transparent"></div>
                                <x-category-badge :category="$listing->category" class="absolute left-3 top-3 shadow-sm" />
                                <span class="absolute bottom-3 right-3 rounded-full bg-white/95 px-3 py-1 text-sm font-bold text-slate-900 shadow-sm">
                                    ₱{{ number_format($rentalUnit === 'hour' ? $listing->price_per_hour : $listing->price_per_day, 0) }}<span class="text-xs font-medium text-slate-400">/{{ $rentalUnit === 'hour' ? 'hour' : 'day' }}</span>
                                </span>
                            </div>
                            <div class="p-4">
                                <h3 class="truncate font-semibold text-slate-900">{{ $listing->name }}</h3>
                                @php($availability = $listing->availabilityStatus())
                                <div class="mt-2"><x-badge :color="$availability->badgeColor()">{{ $availability->label() }}</x-badge></div>
                                <p class="mt-1 flex items-center gap-1 text-sm text-slate-500">
                                    <x-icon name="map-pin" class="h-3.5 w-3.5 text-slate-400" />
                                    {{ $listing->location }}
                                </p>
                                <div class="mt-3 flex items-center justify-between">
                                    <x-badge color="slate">{{ $listing->condition->label() }}</x-badge>
                                    <span class="flex items-center gap-1 text-xs font-semibold {{ $accentText[$style['accent']] ?? $accentText['slate'] }}">
                                        View details
                                        <x-icon name="chevron-down" class="h-3.5 w-3.5 -rotate-90 transition group-hover:translate-x-0.5" />
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $listings->links() }}
                </div>
            @endif
            </section>
        </div>
    </div>
</div>
