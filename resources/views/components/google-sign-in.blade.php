@props(['href' => null])

@php
    $available = filled(config('services.google.client_id'))
        && filled(config('services.google.client_secret'))
        && filled(config('services.google.redirect'));
    $buttonClasses = 'inline-flex min-h-12 w-full items-center justify-center gap-3 rounded-full border border-[#747775] bg-white px-5 py-3 text-sm font-medium tracking-[0.01em] text-[#1f1f1f] shadow-sm transition duration-200 hover:bg-slate-50 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-500 disabled:shadow-none disabled:hover:bg-white motion-reduce:transition-none';
@endphp

<x-input-error :messages="$errors->get('google')" class="mb-5" role="alert" />

    <div class="mb-7">
        @if ($available && $href)
            <a href="{{ $href }}" aria-describedby="google-sign-in-note" {{ $attributes->merge(['class' => $buttonClasses]) }}>
        @else
            <button type="button" @disabled(! $available) aria-describedby="google-sign-in-note" {{ $attributes->merge(['class' => $buttonClasses]) }}>
        @endif
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5Z" />
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6C44.4 38.02 46.98 31.89 46.98 24.55Z" />
                <path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 0 1 9.77 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.87 23.87 0 0 0 0 24c0 3.87.93 7.53 2.56 10.78l7.97-6.19Z" />
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.73-6c-2.15 1.45-4.92 2.3-8.18 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48Z" />
            </svg>
            <span>Continue with Google</span>
        @if ($available && $href)
            </a>
        @else
            </button>
        @endif
        <p id="google-sign-in-note" class="mx-auto mt-3 max-w-sm text-center text-xs leading-5 text-slate-500">{{ $available ? 'Use your personal Gmail or school or work Google account. You can submit a photo ID and selfie for administrator review after signing in.' : 'Google sign-in will be available soon. You can continue with email.' }}</p>
        <div class="mt-6 flex items-center gap-4 text-xs text-slate-500"><span class="h-px flex-1 bg-slate-200" aria-hidden="true"></span><span>or continue with email</span><span class="h-px flex-1 bg-slate-200" aria-hidden="true"></span></div>
    </div>
