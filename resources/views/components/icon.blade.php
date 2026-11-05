@props(['name'])

@php
$paths = [
    'cart' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l2.4 12h10.8l2-8H6M8 15l-1 3h12" /><circle cx="9" cy="21" r="1" /><circle cx="18" cy="21" r="1" />',
    'bell' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />',
    'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 11.5 12 4l9 7.5M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9" />',
    'search' => '<circle cx="10.5" cy="10.5" r="6.5" /><path stroke-linecap="round" d="m20 20-4.8-4.8" />',
    'map-pin' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-7.2 7-12.5A7 7 0 1 0 5 8.5C5 13.8 12 21 12 21Z" /><circle cx="12" cy="8.5" r="2.25" />',
    'sparkles' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v4m0 10v4M4.5 12h4m7 0h4M6.5 6.5l2.8 2.8m5.4 5.4 2.8 2.8m0-11-2.8 2.8m-5.4 5.4-2.8 2.8" />',
    'calculator' => '<rect x="5" y="3" width="14" height="18" rx="2" /><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01" />',
    'clipboard-list' => '<rect x="6" y="4" width="12" height="17" rx="1.5" /><path stroke-linecap="round" d="M9 3.5h6v2H9zM9 10h6M9 13h6M9 16h4" />',
    'inbox' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 12h4l2 3h4l2-3h4M4 12l1.4-6.3A1 1 0 0 1 6.4 5h11.2a1 1 0 0 1 1 .7L20 12M4 12v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6" />',
    'archive' => '<rect x="4" y="4" width="16" height="4.5" rx="1" /><path stroke-linecap="round" stroke-linejoin="round" d="M5 8.5V19a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8.5M10 13h4" />',
    'tag' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.6 3.5H6a1 1 0 0 0-1 1v5.6a1 1 0 0 0 .3.7l9 9a1 1 0 0 0 1.4 0l5.6-5.6a1 1 0 0 0 0-1.4l-9-9a1 1 0 0 0-.7-.3Z" /><circle cx="8.5" cy="8.5" r="1.25" />',
    'pencil' => '<path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4M4 20l4.5-1L20 7.5a2.8 2.8 0 0 0-4-4L4.5 15 4 20Z" />',
    'trash' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v5M14 11v5" />',
    'folder' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.5 7a1.5 1.5 0 0 1 1.5-1.5h4l2 2h9a1.5 1.5 0 0 1 1.5 1.5v9a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 17.5Z" />',
    'list' => '<path stroke-linecap="round" d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01" />',
    'exclamation-triangle' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4 3 20h18L12 4Z" /><path stroke-linecap="round" d="M12 10v4m0 3h.01" />',
    'scale' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M7 6h10M4 6l-2 5a3 3 0 0 0 6 0L6 6M20 6l-2 5a3 3 0 0 0 6 0L22 6" />',
    'chart-bar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 20V10m7 10V4m7 16v-7" />',
    'cog' => '<circle cx="12" cy="12" r="3" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 13.5a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.04 1.56V19.5a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.04-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.56-1.04H4.5a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 6.15 8.4a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34H10.6A1.7 1.7 0 0 0 11.64 2.6V2.5a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1.04 1.56 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V8.6a1.7 1.7 0 0 0 1.56 1.04h.09a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.56 1.04Z" />',
    'user-circle' => '<circle cx="12" cy="12" r="9" /><circle cx="12" cy="10" r="3" /><path stroke-linecap="round" d="M6.5 18.5a6 6 0 0 1 11 0" />',
    'logout' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 8V6a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-6a2 2 0 0 1-2-2v-2M13 12H3m0 0 3-3m-3 3 3 3" />',
    'menu' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />',
    'x-mark' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />',
    'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />',
    'pause' => '<path stroke-linecap="round" d="M9 5v14m6-14v14" />',
    'play' => '<path stroke-linejoin="round" d="M8 5v14l11-7L8 5Z" />',
    'wallet' => '<rect x="3" y="5" width="18" height="14" rx="2" /><path stroke-linecap="round" d="M21 10h-6a2 2 0 0 0 0 4h6m-6-2h.01" />',
    'clock' => '<circle cx="12" cy="12" r="8.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5v5l3 2" />',
    'chevron-down' => '<path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />',
    'filter' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10M10 19h4" />',
    'chat' => '<rect x="3.5" y="5" width="17" height="11" rx="2.5" /><path d="M7 16 L7 20 L11 16 Z" fill="currentColor" stroke="none" /><path stroke-linecap="round" d="M7.5 9h9M7.5 12.5h6" />',
    'paper-airplane' => '<path stroke-linecap="round" stroke-linejoin="round" d="m21 3-7 18-4-7-7-4 18-7ZM10 14 21 3" />',
    'wrench' => '<circle cx="7" cy="7" r="3" /><circle cx="17" cy="17" r="3" /><path stroke-linecap="round" d="M9.1 9.1 14.9 14.9" />',
    'camera' => '<rect x="3" y="7" width="18" height="13" rx="2" /><path stroke-linecap="round" stroke-linejoin="round" d="M8 7 9.5 4.5h5L16 7" /><circle cx="12" cy="13.5" r="3.5" />',
    'hand-tool' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.5 5.5a5 5 0 0 0-6.4 6.4L3.7 16.3a2.8 2.8 0 0 0 4 4l4.4-4.4a5 5 0 0 0 6.4-6.4L16 12l-4-4 2.5-2.5Z" />',
    'tent' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 20 12 5l9 15H3Zm5 0 4-7 4 7M10 3l2 2 2-2" />',
    'mountain' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 19 9 8l4 6 2-3 6 8H3Z" />',
    'car' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 16v-3.5a2 2 0 0 1 .4-1.2L6 8.5A2 2 0 0 1 7.6 7.7h8.8A2 2 0 0 1 18 8.5l1.6 2.8a2 2 0 0 1 .4 1.2V16" /><rect x="3" y="16" width="18" height="3" rx="1" /><circle cx="7.5" cy="19" r="1.5" /><circle cx="16.5" cy="19" r="1.5" />',
    'cpu' => '<rect x="7" y="7" width="10" height="10" rx="1.5" /><rect x="10" y="10" width="4" height="4" /><path stroke-linecap="round" d="M12 3v3M12 18v3M3 12h3M18 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2" />',
    'star' => '<path stroke-linejoin="round" d="m12 3 2.6 5.7 6.2.6-4.6 4.2 1.3 6.1L12 16.8 6.5 19.6l1.3-6.1L3.2 9.3l6.2-.6Z" />',
];

$path = $paths[$name] ?? $paths['home'];
@endphp

<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
    {!! $path !!}
</svg>
