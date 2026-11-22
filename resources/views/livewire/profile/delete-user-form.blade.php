<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        Auth::user()->closeAccount();
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section id="profile-account" class="account-profile-section rounded-2xl border border-rose-200 bg-white p-6 shadow-sm sm:p-7" aria-labelledby="profile-account-title">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600"><x-icon name="exclamation-triangle" class="h-5 w-5" aria-hidden="true" /></span>
            <header class="min-w-0">
                <h2 id="profile-account-title" class="text-lg font-bold text-slate-900 sm:text-xl">{{ __('Account options') }}</h2>
                <h3 class="mt-3 text-sm font-semibold text-rose-700">{{ __('Delete account') }}</h3>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
                    {{ __('Deleting your account removes your access and personal profile details, and takes your listings off the marketplace. Shared transaction and conversation history remains available to the other party. Settle all requests, rentals, deposits, damage claims, and disputes first. Download any information you wish to keep before continuing.') }}
                </p>
            </header>
        </div>

        <button type="button" x-data x-on:click="$dispatch('open-modal', 'confirm-user-deletion')" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 self-start rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:border-rose-300 hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100 xl:self-center">
            <x-icon name="trash" class="h-4 w-4" aria-hidden="true" /> {{ __('Delete account') }}
        </button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        @if (auth()->user()->google_id)
            <form id="confirm-google-account-deletion" method="POST" action="{{ route('profile.delete-with-google') }}">
                @csrf
            </form>
        @endif
        <form wire:submit="deleteUser" class="p-6 sm:p-8" role="dialog" aria-modal="true" aria-labelledby="delete-account-title" aria-describedby="delete-account-description">

            <h2 id="delete-account-title" class="text-lg font-bold text-slate-900">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p id="delete-account-description" class="mt-2 text-sm leading-6 text-slate-500">
                {{ __('Your access and personal profile details will be removed. Shared rental history will be retained.') }}
                @if (auth()->user()->google_id)
                    {{ __('Confirm with your Lendly password or your linked Google account.') }}
                @else
                    {{ __('Enter your password to confirm account deletion.') }}
                @endif
            </p>

            <div class="mt-6">
                <x-input-label required for="delete_account_password" value="{{ __('Password') }}" />

                <x-password-input
                    wire:model="password"
                    id="delete_account_password"
                    name="password"
                    type="password"
                    class="mt-2 block min-h-11 w-full"
                    autocomplete="current-password"
                    required
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                <x-input-error :messages="$errors->get('account')" class="mt-2" />
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5">
                <x-secondary-button class="min-h-11" x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="min-h-11" wire:loading.attr="disabled" wire:target="deleteUser">
                    {{ __('Delete Account') }}
                </x-danger-button>
                @if (auth()->user()->google_id)
                    <x-danger-button form="confirm-google-account-deletion" class="min-h-11">
                        {{ __('Delete with Google') }}
                    </x-danger-button>
                @endif
            </div>
        </form>
    </x-modal>
</section>
