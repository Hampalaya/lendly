@props(['threads', 'activeId' => null, 'search' => '', 'threadFilter' => 'all', 'threadSort' => 'latest'])

<div class="messages-workspace flex min-h-0 flex-1 bg-white">
    <div class="flex min-h-0 min-w-0 flex-1 overflow-hidden">
        <aside aria-label="Conversation list" class="min-h-0 w-full shrink-0 flex-col border-r border-slate-200 md:w-80 xl:w-[22rem] {{ $activeId ? 'hidden md:flex' : 'flex' }}">
            <div class="shrink-0 space-y-4 px-5 pb-3 pt-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-xl font-bold tracking-tight text-slate-900">Chats</h2>
                </div>
                <div class="relative">
                    <label for="conversation-search" class="sr-only">Search conversations</label>
                    <x-icon name="search" class="pointer-events-none absolute left-3.5 top-3 h-4 w-4 text-slate-400" aria-hidden="true" />
                    <input id="conversation-search" type="search" wire:model.live.debounce.250ms="search" placeholder="Search people or items" autocomplete="off" class="h-10 w-full rounded-full border-0 bg-slate-100 pl-10 pr-4 text-sm text-slate-700 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-blue-500" />
                </div>
            </div>

            <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-5 pb-3">
                <div class="relative flex h-10 w-14 items-center justify-center gap-1 rounded-xl {{ $threadFilter === 'all' ? 'bg-slate-100 text-slate-600' : 'bg-blue-50 text-blue-600' }} focus-within:ring-2 focus-within:ring-blue-500 hover:bg-blue-50">
                    <x-icon name="filter" class="pointer-events-none h-5 w-5" aria-hidden="true" />
                    <x-icon name="chevron-down" class="pointer-events-none h-3 w-3" aria-hidden="true" />
                    <label for="conversation-filter" class="sr-only">Filter chats</label>
                    <select id="conversation-filter" wire:model.live="threadFilter" title="Filter chats" class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                        <option value="all">All chats</option>
                        <option value="unread">Unread</option>
                        <option value="rentals">Rental requests</option>
                        <option value="listings">Item inquiries</option>
                    </select>
                    @if ($threadFilter !== 'all')
                        <span class="pointer-events-none absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-blue-600" aria-hidden="true"></span>
                    @endif
                </div>
                <button
                    type="button"
                    wire:click="toggleThreadSort"
                    aria-label="{{ $threadSort === 'latest' ? 'Latest activity first. Show oldest activity first' : 'Oldest activity first. Show latest activity first' }}"
                    title="{{ $threadSort === 'latest' ? 'Latest activity first' : 'Oldest activity first' }}"
                    wire:loading.attr="disabled"
                    wire:target="toggleThreadSort"
                    class="grid h-10 w-10 place-items-center rounded-xl bg-slate-100 text-slate-600 transition hover:bg-blue-50 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                >
                    <svg class="h-5 w-5 {{ $threadSort === 'oldest' ? 'rotate-180' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m-6-6 6 6 6-6" />
                    </svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain px-2 py-3">
                @forelse ($threads as $thread)
                    <a
                        href="{{ $thread['url'] }}"
                        wire:navigate
                        wire:key="thread-{{ $thread['id'] }}"
                        aria-current="{{ $activeId === $thread['id'] ? 'page' : 'false' }}"
                        class="flex items-center gap-3 rounded-xl px-3 py-3 transition focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 {{ $activeId === $thread['id'] ? 'bg-blue-50' : 'hover:bg-slate-50' }}"
                    >
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-blue-100 to-indigo-100 text-base font-semibold text-blue-700">
                            @if ($thread['otherParty']->avatarUrl())
                                <img src="{{ $thread['otherParty']->avatarUrl() }}" alt="" class="h-full w-full object-cover" />
                            @else
                                {{ Str::of($thread['otherParty']->name)->substr(0, 1)->upper() }}
                            @endif
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $thread['otherParty']->name }}</p>
                                @if ($thread['lastMessage'])
                                    <span class="shrink-0 text-[10px] text-slate-400">{{ $thread['lastMessage']->created_at->diffForHumans(null, true, true) }}</span>
                                @endif
                            </div>
                            <p class="truncate text-xs text-slate-500">{{ $thread['listing']->name }}</p>
                            @if ($thread['lastMessage'])
                                <p class="mt-1 truncate text-xs {{ $thread['unreadCount'] > 0 ? 'font-semibold text-slate-800' : 'text-slate-500' }}">{{ $thread['lastMessage']->sender_id === auth()->id() ? 'You: ' : '' }}{{ $thread['lastMessage']->body }}</p>
                            @endif
                        </div>

                        @if ($thread['unreadCount'] > 0)
                            <span class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-blue-600 px-1.5 text-[11px] font-semibold text-white">
                                {{ $thread['unreadCount'] }}
                            </span>
                        @endif
                    </a>
                @empty
                    <div class="p-6">
                        @if (trim($search) !== '')
                            <div class="py-8 text-center" role="status">
                                <x-icon name="search" class="mx-auto h-8 w-8 text-slate-300" aria-hidden="true" />
                                <p class="mt-3 text-sm font-semibold text-slate-700">No conversations found</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Try another name or item.</p>
                                <button type="button" wire:click="$set('search', '')" class="mt-3 rounded-lg px-3 py-2 text-xs font-semibold text-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500">Clear search</button>
                            </div>
                        @else
                            <x-empty-state title="No matching conversations" message="Messages will appear here. Adjust the filters if you cannot find a chat." />
                        @endif
                    </div>
                @endforelse
            </div>
        </aside>

        <section aria-label="Conversation" class="min-h-0 min-w-0 flex-1 flex-col {{ $activeId ? 'flex' : 'hidden md:flex' }}">
            {{ $slot }}
        </section>
    </div>
</div>
