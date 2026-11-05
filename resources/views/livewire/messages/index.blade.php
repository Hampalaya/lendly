<div class="messages-page" wire:poll.5s.visible>
    <x-page-header title="Messages" />

    <x-messages.shell :threads="$threads" :search="$search" :thread-filter="$threadFilter" :thread-sort="$threadSort">
        <div class="flex min-h-0 flex-1 flex-col items-center justify-center bg-slate-50/50 p-8 text-center">
            <span class="grid h-20 w-20 place-items-center rounded-full bg-blue-50 text-blue-600"><x-icon name="chat" class="h-9 w-9" aria-hidden="true" /></span>
            <h2 class="mt-5 text-xl font-bold text-slate-900">Your messages</h2>
            <p class="mt-2 max-w-xs text-sm leading-6 text-slate-500">Select a conversation to start chatting.</p>
        </div>
    </x-messages.shell>
</div>
