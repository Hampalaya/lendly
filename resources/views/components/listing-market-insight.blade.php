@props(['insight', 'originalPrice' => null, 'highPrice' => false])

<div role="region" aria-label="Market insight" class="mt-5 rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50/90 to-sky-50/70 p-4">
    <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-600">Market insight</p>
    @if ($originalPrice > 0)
        <p class="mt-2 text-sm text-slate-700">Based on original price: <span class="font-semibold">₱{{ number_format($originalPrice, 2) }}</span></p>
    @endif
    @if ($insight['suggested'] === null)
        <p class="mt-2 text-sm text-slate-600">Enter the original price above to get a suggested daily rental rate.</p>
    @endif
    @if ($insight['suggested'] !== null)
        <div class="mt-3 border-t border-blue-100 pt-3">
            <p class="text-sm font-semibold text-slate-800">Suggested daily rate: ₱{{ number_format($insight['suggested'], 2) }}</p>
            @if ($insight['basis'] === 'original-price-ratio')
                <p class="mt-1 text-xs leading-5 text-slate-600">Your original price × {{ number_format($insight['ratio'] * 100, 2) }}%, the median daily-to-original-price ratio of {{ $insight['ratio_count'] }} comparable listings.</p>
            @elseif ($insight['basis'] === 'starting-estimate')
                <p class="mt-1 text-xs leading-5 text-slate-600">Starting estimate: 1% of your original price per day. There is not enough original-price data for a market-based estimate.</p>
            @else
                <p class="mt-1 text-xs leading-5 text-slate-600">Based on average daily rates. Add the original price to tailor this suggestion.</p>
            @endif
            <button type="button" wire:click="useSuggestedPrice" class="mt-3 rounded-lg bg-white px-3 py-2 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">Use suggested daily rate</button>
            <p class="mt-2 text-xs text-slate-500">Your rate changes only when you choose to apply the suggestion.</p>
        </div>
    @endif
    @if ($insight['count'] > 0)
        <div class="mt-3 grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-xs leading-5 text-slate-500">Similar listings</p>
                <p class="font-semibold text-slate-800">{{ $insight['count'] }}</p>
            </div>
            <div>
                <p class="text-xs leading-5 text-slate-500">Average</p>
                <p class="font-semibold text-slate-800">₱{{ number_format($insight['average'], 0) }}/day</p>
            </div>
            <div>
                <p class="text-xs leading-5 text-slate-500">Price range</p>
                <p class="font-semibold text-slate-800">₱{{ number_format($insight['min'], 0) }}&ndash;{{ number_format($insight['max'], 0) }}</p>
            </div>
            <div>
                <p class="text-xs leading-5 text-slate-500">Suggested price</p>
                <p class="font-semibold text-slate-800">₱{{ number_format($insight['suggested'], 0) }}/day</p>
            </div>
        </div>
        <p class="mt-3 text-xs leading-5 text-slate-500">This is an estimated market insight based on similar listings on the platform. You can still choose your own price.</p>

        @if ($highPrice)
            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                <p class="font-semibold">Your price is significantly higher than similar listings.</p>
                <p class="mt-1">Similar items range from ₱{{ number_format($insight['min'], 0) }} to ₱{{ number_format($insight['max'], 0) }}/day.</p>
                <p class="mt-1">Suggested price: ₱{{ number_format($insight['suggested'], 0) }}/day.</p>
            </div>
        @endif
    @else
        <p class="mt-2 text-sm text-slate-500">No similar published listings yet to compare against.</p>
    @endif

</div>
