<div wire:poll.5s.visible="refresh" x-data x-on:click.outside="$wire.open = false" @keydown.escape.window="$wire.open = false">
    <button
        type="button"
        wire:click="toggle"
        class="page-header__icon group"
        aria-label="Open notifications"
        title="Notifications"
        :aria-expanded="$wire.open ? 'true' : 'false'">
        <x-icon name="bell" class="h-6 w-6" aria-hidden="true" />
        @if ($unreadCount > 0)
            <span class="page-header__count">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="$wire.open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 scale-95"
        class="absolute right-2 top-full z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] origin-top-right overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_22px_65px_rgba(15,45,95,0.20)] sm:right-4">
        <div class="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-white to-blue-50/80 px-4 py-3.5">
            <div>
                <p class="text-sm font-bold text-[#071a3d]">Notifications</p>
                <p class="mt-0.5 text-[11px] text-slate-400">Recent activity on your account</p>
            </div>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="rounded-full bg-blue-50 px-2.5 py-1.5 text-[11px] font-bold text-blue-600 transition hover:bg-blue-100 hover:text-blue-800">Mark all read</button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto overscroll-contain">
            @forelse ($notifications as $notification)
                <a
                    href="{{ $notification->data['url'] ?? '#' }}"
                    wire:navigate
                    wire:click="markAsRead('{{ $notification->id }}')"
                    class="group relative block border-b border-slate-100 px-4 py-3.5 transition hover:bg-blue-50/70 {{ $notification->read_at ? 'opacity-60' : '' }}">
                    @if (! $notification->read_at)
                        <span class="absolute left-1.5 top-5 h-1.5 w-1.5 rounded-full bg-blue-600"></span>
                    @endif
                    <p class="text-sm font-semibold text-slate-800 transition group-hover:text-blue-800">{{ $notification->data['title'] }}</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $notification->data['message'] }}</p>
                    <p class="mt-1.5 text-[11px] font-medium text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
                </a>
            @empty
                <div class="px-4 py-9 text-center">
                    <span class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-blue-50 text-blue-500">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-700">You're all caught up</p>
                    <p class="mt-1 text-xs text-slate-400">New activity will appear here.</p>
                </div>
            @endforelse
        </div>

        <a href="{{ route('notifications.index') }}" wire:navigate class="group flex items-center justify-center gap-2 border-t border-slate-100 bg-slate-50/70 px-4 py-3 text-xs font-bold text-blue-600 transition hover:bg-blue-50 hover:text-blue-800">
            View all notifications
            <span class="transition-transform duration-200 group-hover:translate-x-1">→</span>
        </a>
    </div>
</div>
