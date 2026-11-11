@props(['record'])

<div class="mt-3 space-y-1 text-sm text-slate-600">
    <p class="whitespace-pre-line break-words">Reason: {{ $record->cancellation_reason ?? 'No reason provided.' }}</p>
    <p>Cancellation fee: {{ $record->cancellation_fee !== null ? '₱'.number_format($record->cancellation_fee, 2) : 'Not recorded' }}</p>
    <p class="text-xs text-slate-500">
        @if ($record->cancelled_at)
            Cancelled {{ $record->cancelled_at->copy()->timezone('Asia/Manila')->format('M d, Y \a\t g:i A') }} (Philippine time)
        @else
            Cancellation time not recorded.
        @endif
    </p>
</div>
