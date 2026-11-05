@props(['interface', 'requestProgress', 'memberRentalsByStatus', 'awaitingAgreementCount', 'identityProgress', 'proofsUnderReviewCount'])

@php
    $isRenter = $interface === 'renter';
    $needsIdentitySubmission = $isRenter && $identityProgress === 'not submitted';
    $statusCardStyle = $isRenter ? 'border-blue-200 hover:bg-blue-50' : 'border-slate-200';
@endphp

<section aria-label="Account and request progress" class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-3">
        <a href="{{ route('identity.verify') }}" wire:navigate @class([
            'rounded-xl border p-4 shadow-sm',
            'border-amber-200 bg-amber-50 hover:bg-amber-100' => $needsIdentitySubmission,
            "bg-white $statusCardStyle" => ! $needsIdentitySubmission,
        ])>
            <p class="text-xs font-medium {{ $needsIdentitySubmission ? 'text-amber-900' : 'text-slate-500' }}">Identity verification</p>
            <p class="mt-2 font-semibold capitalize {{ $needsIdentitySubmission ? 'text-amber-950' : 'text-slate-900' }}">{{ $identityProgress }}</p>
            <p class="mt-1 text-xs {{ $needsIdentitySubmission ? 'font-semibold text-amber-900' : 'text-blue-600' }}">
                {{ $needsIdentitySubmission ? 'Start identity verification' : 'View verification progress' }} →
            </p>
        </a>
        <a href="{{ route($interface.'.rental-requests.index', ['filter' => 'awaiting_agreement']) }}" wire:navigate class="rounded-xl border bg-white p-4 shadow-sm {{ $statusCardStyle }}">
            <p class="text-xs font-medium text-slate-500">Awaiting agreement</p>
            <p class="mt-2 text-xl font-semibold text-slate-900">{{ $awaitingAgreementCount }}</p>
            <p class="mt-1 text-xs text-blue-600">Review agreement requests →</p>
        </a>
        <a href="{{ route($interface.'.rentals.index') }}" wire:navigate class="rounded-xl border bg-white p-4 shadow-sm {{ $statusCardStyle }}">
            <p class="text-xs font-medium text-slate-500">Payment proofs under review</p>
            <p class="mt-2 text-xl font-semibold text-slate-900">{{ $proofsUnderReviewCount }}</p>
            <p class="mt-1 text-xs text-blue-600">{{ $isRenter ? 'View payment review progress' : 'Check proof and wallet receipt' }} →</p>
        </a>
    </div>
    <div @class(['grid gap-4', 'xl:grid-cols-2' => $isRenter])>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-semibold text-slate-900">Rental request progress</h2>
            <x-pie-chart :data="$requestProgress" />
            <p class="mt-3 text-xs text-slate-500">All your requests, grouped by current status.</p>
        </div>
        @if ($isRenter)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 font-semibold text-slate-900">Your rentals by status</h2>
                <x-pie-chart :data="$memberRentalsByStatus" />
                <p class="mt-3 text-xs text-slate-500">Approved bookings, grouped by their current rental status.</p>
            </div>
        @endif
    </div>
</section>
