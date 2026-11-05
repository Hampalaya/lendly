<div wire:poll.5s.visible>
    <x-page-header title="Notifications" subtitle="Updates about your requests, rentals, and disputes." maxWidth="max-w-3xl" />
    <div class="mx-auto max-w-3xl px-4 pt-3 sm:px-6 lg:px-8">
        <x-record-toolbar :filter="$filter" :sort="$recordSort" :filters="['all' => 'All Notifications', 'unread' => 'Unread', 'read' => 'Read']" placeholder="Search notifications..." search-label="Search notifications" label="Notification controls">
            <x-slot:additionalFilters>
                <label class="min-w-0">
                    <span class="sr-only">Notification category</span>
                    <select wire:model.live="typeFilter" class="block h-10 w-44 max-w-full rounded-lg border-slate-300 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach (['all' => 'All Categories', 'verification' => 'Verification', 'listings' => 'Listings', 'requests' => 'Requests and approvals', 'payments' => 'Payments and deposits', 'messages' => 'Messages', 'rentals' => 'Rentals and disputes'] as $value => $category)
                            <option value="{{ $value }}" @selected($typeFilter === $value)>{{ $category }}</option>
                        @endforeach
                    </select>
                </label>
            </x-slot:additionalFilters>
        </x-record-toolbar>
    </div>

    <div class="mx-auto max-w-3xl px-4 pb-6 pt-3 sm:px-6 lg:px-8">
        <div class="mb-4 flex justify-end">
            <button type="button" wire:click="markAllAsRead" class="text-sm font-medium text-blue-600 hover:text-blue-800">Mark all read</button>
        </div>

        @if ($notifications->isEmpty())
            <x-empty-state title="You're all caught up" message="Notifications about your requests and rentals will appear here." />
        @else
            <div class="space-y-2">
                @foreach ($notifications as $notification)
                    <a
                        href="{{ $notification->data['url'] ?? '#' }}"
                        wire:navigate
                        wire:click="markAsRead('{{ $notification->id }}')"
                        class="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:bg-slate-50 {{ $notification->read_at ? 'opacity-60' : '' }}"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-800">{{ $notification->data['title'] }}</p>
                            @if (! $notification->read_at)
                                <span class="h-2 w-2 rounded-full bg-blue-600"></span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $notification->data['message'] }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
