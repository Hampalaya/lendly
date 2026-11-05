<div class="messages-page" wire:poll.5s.visible>
    <x-page-header title="Messages" />

    <x-messages.shell :threads="$threads" :active-id="$activeId" :search="$search" :thread-filter="$threadFilter" :thread-sort="$threadSort">
        @if (! $listing->trashed() && $listing->isPublished())
            <a href="{{ route('listings.show', $listing) }}" wire:navigate class="flex min-h-11 shrink-0 items-center gap-2 border-b border-slate-100 px-5 py-3 text-sm font-semibold text-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                <x-icon name="chevron-down" class="h-4 w-4 rotate-90" aria-hidden="true" /> Back to listing
            </a>
        @endif
        <a href="{{ route('messages.index') }}" wire:navigate class="flex items-center gap-1 border-b border-slate-100 px-4 py-3 text-sm font-medium text-slate-500 hover:text-slate-700 md:hidden">
            <x-icon name="chevron-down" class="h-3.5 w-3.5 rotate-90" /> Back to conversations
        </a>

        <header class="flex shrink-0 items-center gap-3 border-b border-slate-100 bg-white px-5 py-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-blue-100 to-indigo-100 text-base font-semibold text-blue-700">
                @if ($otherParty->avatarUrl())
                    <img src="{{ $otherParty->avatarUrl() }}" alt="" class="h-full w-full object-cover" />
                @else
                    {{ Str::of($otherParty->name)->substr(0, 1)->upper() }}
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $otherParty->name }}</p>
                <p class="mt-0.5 truncate text-xs text-slate-500">{{ $listing->name }}</p>
            </div>
            @if (! $otherParty->trashed())
                <a href="{{ route('users.show', $otherParty) }}" wire:navigate aria-label="View {{ $otherParty->name }}’s profile" title="View profile" class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-blue-600 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500"><x-icon name="user-circle" class="h-6 w-6" aria-hidden="true" /></a>
            @endif
        </header>

        @if (session('status'))
            <div class="mx-4 mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">{{ session('status') }}</div>
        @endif

        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto bg-slate-50/60 p-5 sm:p-6" x-data x-init="$el.scrollTop = $el.scrollHeight" x-on:message-sent.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)" wire:key="thread-body-{{ $activeId }}">
            <x-messages.item-card :listing="$listing" />
            @forelse ($messages as $message)
                <div class="flex items-end gap-2 {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}" wire:key="message-{{ $message->id }}">
                    @if ($message->sender_id !== auth()->id())
                        <span class="mb-5 flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-100 text-xs font-semibold text-blue-700" aria-hidden="true">
                            @if ($otherParty->avatarUrl())
                                <img src="{{ $otherParty->avatarUrl() }}" alt="" class="h-full w-full object-cover" />
                            @else
                                {{ Str::of($otherParty->name)->substr(0, 1)->upper() }}
                            @endif
                        </span>
                    @endif
                    <div class="min-w-0 max-w-[85%] sm:max-w-md">
                        <p class="whitespace-pre-wrap break-words rounded-2xl px-4 py-2.5 text-sm leading-6 {{ $message->sender_id === auth()->id() ? 'rounded-br-md bg-gradient-to-r from-blue-600 to-indigo-600 text-white' : 'rounded-bl-md border border-slate-200/70 bg-white text-slate-800' }}">{{ $message->body }}</p>
                        <p class="mt-1 px-1 text-[10px] text-slate-400 {{ $message->sender_id === auth()->id() ? 'text-right' : '' }}">
                            {{ $message->created_at->format('M d, g:i A') }}@if ($message->sender_id === auth()->id() && $message->read_at) · Seen @endif
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">Ask a question about this item to start the conversation.</p>
            @endforelse
        </div>

        <form wire:submit="send" class="flex shrink-0 items-center gap-3 border-t border-slate-100 bg-white px-5 py-4">
            <label for="chat-message-body" class="text-xs font-medium text-slate-600">Message <x-required-mark /></label>
            <input id="chat-message-body" type="text" wire:model="body" aria-label="Message" maxlength="2000" placeholder="Type a message..." autocomplete="off" class="min-w-0 flex-1 rounded-full border-0 bg-slate-100 px-5 py-3 text-sm text-slate-800 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-blue-500">
            <button type="submit" aria-label="Send message" title="Send message" wire:loading.attr="disabled" wire:target="send" class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-sm transition hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:opacity-50"><x-icon name="paper-airplane" class="h-5 w-5" aria-hidden="true" /></button>
        </form>
        <x-input-error :messages="$errors->get('body')" class="mx-4 mb-3" />
    </x-messages.shell>
</div>
