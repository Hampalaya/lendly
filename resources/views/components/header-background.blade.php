@php($gradientId = 'header-sky-'.str_replace('.', '-', uniqid('', true)))

<div class="page-header__background" aria-hidden="true">
    <svg class="page-header__curve" viewBox="0 0 1000 112" preserveAspectRatio="none" fill="none">
        <defs>
            <linearGradient id="{{ $gradientId }}" x1="0" y1="56" x2="1000" y2="56" gradientUnits="userSpaceOnUse">
                <stop stop-color="#f0f9ff" />
                <stop offset="0.55" stop-color="#bdddf2" />
                <stop offset="1" stop-color="#7eb6db" />
            </linearGradient>
        </defs>
        <path d="M0 0H920C931 26 981 35 992 60C1007 85 973 108 960 112H0V0Z" fill="url(#{{ $gradientId }})" />
    </svg>

    <div class="page-header__items">
        <svg class="page-header__item-trail" viewBox="0 0 160 80" fill="none">
            <path d="M5 58C31 70 50 17 82 20s43 41 71 19" stroke="currentColor" stroke-linecap="round" stroke-dasharray="2 5" />
        </svg>
        <span class="page-header__item page-header__item--tools">
            <x-icon name="hand-tool" class="h-5 w-5" />
        </span>
        <span class="page-header__item page-header__item--camera">
            <x-icon name="camera" class="h-6 w-6" />
        </span>
        <span class="page-header__item page-header__item--outdoors">
            <x-icon name="tent" class="h-5 w-5" />
        </span>
        <x-icon name="sparkles" class="page-header__item-sparkle" />
    </div>
</div>
