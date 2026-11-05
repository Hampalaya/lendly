@props(['icon', 'label', 'href' => null, 'tone' => 'default'])

@php
    $colors = match ($tone) {
        'success' => 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
        'danger' => 'bg-rose-50 text-rose-700 hover:bg-rose-100',
        default => 'bg-slate-100 text-slate-600 hover:bg-blue-50 hover:text-blue-700',
    };
    $actionAttributes = $attributes->class([
        'grid h-10 w-10 shrink-0 place-items-center rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60',
        $colors,
    ])->merge(['aria-label' => $label, 'title' => $label]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $actionAttributes }}>
        <x-icon :name="$icon" aria-hidden="true" />
    </a>
@else
    <button type="button" {{ $actionAttributes }}>
        <x-icon :name="$icon" aria-hidden="true" />
    </button>
@endif
