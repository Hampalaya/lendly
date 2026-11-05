@props([
    'filters' => [],
    'filter' => 'all',
    'sort' => 'default',
    'placeholder' => 'Search records...',
    'searchLabel' => 'Search records',
    'label' => 'Record controls',
    'searchModel' => 'recordSearch',
    'filterModel' => 'filter',
])

<div {{ $attributes->class(['grid grid-cols-[minmax(0,1fr)_2.5rem] items-center gap-2 rounded-xl border border-slate-200 bg-white p-3 md:grid-cols-[minmax(10rem,14rem)_minmax(0,1fr)_2.5rem]']) }} role="search" aria-label="{{ $label }}">
    <label class="relative col-span-2 min-w-0 md:col-span-1">
        <span class="sr-only">{{ $searchLabel }}</span>
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
        <input type="search" wire:model.live.debounce.350ms="{{ $searchModel }}" maxlength="120" placeholder="{{ $placeholder }}" class="block h-10 w-full rounded-lg border-slate-300 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-blue-500" />
    </label>

    <div class="flex min-w-0 flex-wrap items-center justify-end gap-2" role="group" aria-label="Filter records">
        <label class="min-w-0">
            <span class="sr-only">Filter records</span>
            <select wire:model.live="{{ $filterModel }}" class="block h-10 w-44 max-w-full rounded-lg border-slate-300 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                @foreach ($filters as $value => $statusLabel)
                    <option value="{{ $value }}" @selected($filter === $value)>{{ $statusLabel }}</option>
                @endforeach
            </select>
        </label>
        {{ $additionalFilters ?? '' }}
    </div>

    <button type="button" wire:click="toggleRecordSort" wire:loading.attr="disabled" wire:target="toggleRecordSort" aria-label="Oldest records first" aria-pressed="{{ $sort === 'oldest' ? 'true' : 'false' }}" title="{{ $sort === 'oldest' ? 'Show newest records first' : 'Show oldest records first' }}" class="grid h-10 w-10 place-items-center rounded-lg border border-slate-200 text-slate-500 transition-colors hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 focus-visible:border-blue-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
        <svg class="pointer-events-none h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path class="{{ $sort === 'oldest' ? 'text-blue-600' : 'text-slate-300' }}" stroke-linecap="round" stroke-linejoin="round" d="M8 19V5m-4 4 4-4 4 4" />
            <path class="{{ $sort === 'oldest' ? 'text-slate-300' : 'text-blue-600' }}" stroke-linecap="round" stroke-linejoin="round" d="M16 5v14m-4-4 4 4 4-4" />
        </svg>
    </button>
</div>
