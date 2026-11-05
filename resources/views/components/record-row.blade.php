@props(['listing'])

<div {{ $attributes->class(['record-row']) }}>
    <div class="record-row__main">
        <div class="record-row__summary">
            <div class="record-row__thumbnail" x-data="{ failed: false }" aria-hidden="true">
                <x-icon name="archive" class="h-5 w-5 text-slate-400" />
                @if ($image = $listing->images->first())
                    <img src="{{ $image->url() }}" alt="" loading="lazy" x-show="!failed" x-on:error="failed = true" class="absolute inset-0 h-full w-full object-cover">
                @endif
            </div>
            <div class="min-w-0 flex-1">{{ $slot }}</div>
        </div>
        @isset($aside)
            <div class="record-row__aside">{{ $aside }}</div>
        @endisset
        @isset($actions)
            <div class="record-row__actions">{{ $actions }}</div>
        @endisset
    </div>
    @isset($footer)
        <div class="record-row__footer">{{ $footer }}</div>
    @endisset
</div>
