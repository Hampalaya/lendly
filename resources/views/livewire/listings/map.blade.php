<div
    class="map-page flex min-h-0 flex-1 flex-col overflow-hidden bg-slate-100"
    x-data="listingsMap(@js($markers), @js($centerLat), @js($centerLng))"
    @locate-user.window="useMyLocation()"
    @location-found.window="locating = false"
>
    <x-page-header eyebrow="Lendly marketplace" title="Explore the map" subtitle="Find items to rent in your neighborhood." />
    <div class="relative isolate z-0 grid min-h-0 flex-1 grid-cols-[minmax(0,1fr)_minmax(9rem,38%)] overflow-hidden sm:grid-cols-[minmax(0,1fr)_18rem] xl:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="relative min-h-0 min-w-0 overflow-hidden">
    <div wire:ignore class="absolute inset-0">
        <div x-ref="map" class="h-full w-full"></div>
    </div>

    <div x-cloak x-show="locationNotice" x-transition role="status" aria-live="polite" class="absolute left-3 right-16 top-40 z-[540] flex max-w-sm items-start gap-3 rounded-xl border border-emerald-200 bg-white p-4 text-sm text-emerald-900 shadow-lg sm:left-4 sm:top-32">
        <p x-text="locationNotice"></p>
        <button type="button" @click="locationNotice = ''" aria-label="Dismiss location update" class="shrink-0 rounded px-1 font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500">×</button>
    </div>

    <div class="pointer-events-none absolute inset-x-0 top-0 z-[550] grid grid-cols-1 items-start gap-2 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-4">
        <div class="pointer-events-auto flex min-w-0 gap-2">
            <div class="flex h-11 min-w-0 flex-1 overflow-hidden rounded-md border border-slate-300 bg-white shadow-lg shadow-slate-900/10">
                <input
                    type="text"
                    wire:model.live.debounce.400ms="keyword"
                    placeholder="Search"
                    class="h-full min-w-0 flex-1 border-0 px-3 text-sm text-slate-800 shadow-none focus:ring-0"
                >
                <button
                    type="button"
                    class="grid h-11 w-11 shrink-0 place-items-center bg-indigo-500 text-white transition hover:bg-indigo-600"
                    title="Search listings"
                    aria-label="Search listings"
                >
                    <x-icon name="search" class="h-5 w-5" />
                </button>
            </div>

        </div>

        <select wire:model.live="category" aria-label="Category" class="pointer-events-auto row-start-2 h-11 min-w-0 rounded-md border-slate-300 bg-white px-3 text-sm shadow-lg shadow-slate-900/10 focus:border-blue-500 focus:ring-blue-100 sm:w-44">
            <option value="">All categories</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </select>
        <div
            class="pointer-events-auto relative min-w-0 sm:col-start-2 sm:row-start-1"
            @click.outside="layersOpen = false"
            @keydown.escape.stop.prevent="layersOpen = false; $refs.layersToggle.focus()"
        >
            <button
                x-ref="layersToggle"
                type="button"
                @click="layersOpen = ! layersOpen"
                :aria-expanded="layersOpen"
                aria-controls="map-layer-options"
                class="flex h-11 w-full items-center justify-between gap-2 rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 shadow-lg shadow-slate-900/10 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <span>Map layers</span>
                <x-icon name="chevron-down" class="h-4 w-4 shrink-0 transition-transform" x-bind:class="layersOpen && 'rotate-180'" aria-hidden="true" />
            </button>

            <div
                id="map-layer-options"
                x-cloak
                x-show="layersOpen"
                x-transition.origin.top.right
                class="absolute right-0 top-full mt-2 max-h-[calc(100dvh-12rem)] w-72 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-3 shadow-xl max-sm:w-full"
                role="group"
                aria-label="Map layers"
            >
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Map style</p>
                <div class="space-y-2">
                    <template x-for="layer in layers" :key="layer.id">
                        <button
                            type="button"
                            @click="setBaseLayer(layer.id); layersOpen = false; $refs.layersToggle.focus()"
                            :aria-pressed="activeLayer === layer.id"
                            class="relative flex min-h-11 w-full items-center overflow-hidden rounded-md border bg-slate-100 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            :class="activeLayer === layer.id ? 'border-indigo-500 ring-2 ring-indigo-400' : 'border-slate-200 hover:border-slate-400'"
                        >
                            <img :src="layer.preview" alt="" aria-hidden="true" decoding="async" class="absolute inset-0 h-full w-full object-cover object-center">
                            <span class="relative m-1 rounded-sm bg-white/90 px-2 py-1 text-sm font-bold text-slate-900" x-text="layer.name"></span>
                        </button>
                    </template>
                </div>

                <div class="mt-3 space-y-2 border-t border-slate-200 pt-3">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" x-model="showListingPins" @change="toggleListingPins()" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        Listing pins
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" x-model="showMyLocation" @change="toggleMyLocation()" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        My location
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div
        class="pointer-events-none absolute right-3 top-44 z-[530] flex flex-col gap-2 sm:right-4 sm:top-32"
    >
        <div class="pointer-events-auto overflow-hidden rounded bg-slate-700 text-white shadow-lg">
            <button type="button" @click="map?.zoomIn()" class="grid h-10 w-10 place-items-center text-2xl leading-none transition hover:bg-slate-800" title="Zoom in" aria-label="Zoom in">+</button>
            <button type="button" @click="map?.zoomOut()" class="grid h-10 w-10 place-items-center border-t border-white/15 text-2xl leading-none transition hover:bg-slate-800" title="Zoom out" aria-label="Zoom out">-</button>
        </div>
        <button type="button" @click="useMyLocation()" :disabled="locating" class="pointer-events-auto grid h-10 w-10 place-items-center rounded bg-slate-700 text-white shadow-lg transition hover:bg-slate-800 disabled:opacity-60" title="Use my location" aria-label="Use my location">
            <x-icon name="map-pin" class="h-5 w-5" />
        </button>
    </div>

    <div class="absolute bottom-20 left-3 right-3 z-[500] w-fit max-w-[calc(100%-1.5rem)] rounded bg-white/90 px-3 py-2 text-xs font-semibold text-slate-700 shadow-lg sm:bottom-4 sm:left-4 sm:right-auto sm:max-w-xs">
        {{ $this->hasLocation() ? 'Nearest available' : 'Available listings' }}
        <span class="font-normal text-slate-500">({{ count($markers) }})</span>
        <p class="mt-1 hidden font-normal sm:block">Hover or focus a pin for a preview. Select a pin to open item details.</p>
    </div>

    @if ($this->hasLocation())
        <div class="absolute bottom-4 right-4 z-[500] flex gap-2">
            <select wire:model.live="radiusKm" aria-label="Distance from your location" class="h-10 rounded-md border-slate-300 bg-white text-sm shadow-lg focus:border-blue-500 focus:ring-blue-100">
                <option value="">Any distance</option>
                <option value="5">Within 5 km</option>
                <option value="10">Within 10 km</option>
                <option value="25">Within 25 km</option>
                <option value="50">Within 50 km</option>
            </select>

            <button type="button" wire:click="clearLocation" @click="locationNotice = ''; locationError = ''" class="grid h-10 w-10 place-items-center rounded-md bg-white text-rose-600 shadow-lg transition hover:bg-rose-50" title="Clear location" aria-label="Clear location">
                <x-icon name="x-mark" class="h-5 w-5" />
            </button>
        </div>
    @endif
    </div>

    <section class="flex min-h-0 min-w-0 flex-col overflow-y-auto border-l border-slate-200 bg-white" aria-labelledby="map-results-title">
        <div class="shrink-0 border-b border-slate-100 p-3 sm:p-4">
            <h1 id="map-results-title" class="text-lg font-bold text-slate-900">{{ $this->hasLocation() ? 'Nearest listings' : 'Listing results' }}</h1>
            <p x-show="locationError" x-text="locationError" x-cloak role="alert" class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900"></p>
             @if ($this->hasLocation())
                 <p class="mt-2 text-xs font-semibold text-blue-700">Location set</p>
             @endif
            <p class="mt-1 text-sm text-slate-500" role="status" aria-live="polite">
                {{ count($markers) }} {{ count($markers) === 1 ? 'listing' : 'listings' }}{{ $this->hasLocation() ? ', sorted nearest to farthest.' : '.' }}
            </p>
            @unless ($this->hasLocation())
                <p class="mt-2 text-xs leading-5 text-slate-500">Set your location to see distances and sort by proximity.</p>
                <button type="button" @click="useMyLocation()" :disabled="locating" class="mt-3 inline-flex min-h-11 items-center gap-2 rounded-lg bg-blue-600 px-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:opacity-60 sm:px-4">
                    <x-icon name="map-pin" class="h-4 w-4" aria-hidden="true" />
                    <span x-text="locating ? 'Finding location…' : 'Use my location'">Use my location</span>
                </button>
            @endunless
        </div>
        @if (count($markers))
            <ol class="min-h-0 flex-1 divide-y divide-slate-100 overflow-y-auto" aria-label="{{ $this->hasLocation() ? 'Listings sorted nearest to farthest' : 'Listing results' }}">
                @foreach ($markers as $marker)
                    <li wire:key="map-result-{{ $marker['id'] }}">
                        <a href="{{ $marker['url'] }}" wire:navigate class="flex min-h-24 flex-col gap-3 p-3 transition hover:bg-blue-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 sm:flex-row sm:p-4">
                            @if ($marker['image'])
                                <img src="{{ $marker['image'] }}" alt="" loading="lazy" class="h-16 w-16 shrink-0 rounded-lg object-cover">
                            @else
                                <span class="grid h-16 w-16 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-400"><x-icon name="archive" class="h-6 w-6" aria-hidden="true" /></span>
                            @endif
                            <span class="min-w-0">
                                <span class="block break-words text-sm font-semibold text-slate-900">{{ $marker['name'] }}</span>
                                <span class="mt-1 block text-sm text-slate-600">₱{{ $marker['price'] }}/{{ $marker['priceUnit'] ?? 'day' }}</span>
                                @if ($marker['distance'] !== null)
                                    <span class="mt-1 block text-xs font-semibold text-blue-700">{{ $marker['distance'] }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        @else
            <div class="p-5 text-sm text-slate-500">
                <p class="font-semibold text-slate-800">No matching listings</p>
                <p class="mt-2">{{ $this->hasLocation() && $radiusKm !== null ? 'Try a larger radius or adjust your search filters.' : 'Try another search or category.' }}</p>
            </div>
        @endif
    </section>

    </div>
    @vite('resources/js/map.js')
</div>
