@props(['id', 'title', 'message', 'action', 'confirmLabel' => 'Confirm'])

<div x-data>
    {{ $slot }}
    <dialog x-ref="confirmation" wire:ignore.self aria-labelledby="{{ $id }}-title" aria-describedby="{{ $id }}-message" class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-2xl border-0 bg-white p-6 text-slate-900 shadow-xl backdrop:bg-slate-900/50">
        <h2 id="{{ $id }}-title" class="text-lg font-bold">{{ $title }}</h2>
        <p id="{{ $id }}-message" class="mt-3 text-sm leading-6 text-slate-600">{{ $message }}</p>
        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <x-secondary-button type="button" x-on:click="$refs.confirmation.close()" autofocus>Cancel</x-secondary-button>
            <x-primary-button type="button" wire:click="{{ $action }}" wire:loading.attr="disabled" wire:target="{{ $action }}" x-on:click="$refs.confirmation.close()">{{ $confirmLabel }}</x-primary-button>
        </div>
    </dialog>
</div>
