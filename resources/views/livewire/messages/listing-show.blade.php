<div>
    <x-page-header title="Messages" subtitle="Your conversation about this listing." />
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
    <a href="{{ route('messages.index') }}" wire:navigate class="flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
        <x-icon name="chevron-down" class="h-3.5 w-3.5 rotate-90" /> Back to messages
    </a>

    <h2 class="mt-3 text-xl font-semibold text-slate-900">Chat with {{ $otherParty->name }}</h2>
    <p class="mt-1 text-sm text-slate-500">About: {{ $conversation->listing->name }}</p>

    <div class="mt-6 flex h-96 flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex-1 space-y-3 overflow-y-auto">
            @forelse ($conversation->messages as $message)
                <div class="flex {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}" wire:key="message-{{ $message->id }}">
                    <div class="max-w-xs rounded-lg px-3 py-2 text-sm {{ $message->sender_id === auth()->id() ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-800' }}">
                        <p>{{ $message->body }}</p>
                        <p class="mt-1 text-xs {{ $message->sender_id === auth()->id() ? 'text-blue-100' : 'text-slate-400' }}">
                            {{ $message->created_at->format('M d, g:i A') }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-center text-sm text-slate-400">No messages yet. Ask the owner about this listing.</p>
            @endforelse
        </div>

        @if ($canSend)
            <form wire:submit="send" class="mt-4 flex gap-2 border-t border-slate-100 pt-4">
                <label for="inquiry-body" class="text-xs font-medium text-slate-600">Message <x-required-mark /></label>
                <input id="inquiry-body" type="text" wire:model="body" maxlength="2000" placeholder="Type a message..." class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <x-primary-button>Send</x-primary-button>
            </form>
            <x-input-error :messages="$errors->get('body')" class="mt-2" />
        @else
            <p class="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-500">This member is unavailable. You can still read your conversation history.</p>
        @endif
    </div>
    </div>
</div>
