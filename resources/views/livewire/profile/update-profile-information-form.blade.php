<?php

use App\Rules\GmailAddress;
use App\Support\AuthEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';

    public $avatar = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->phone = Auth::user()->phone ?? '';
        $this->address = Auth::user()->address ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();
        $this->email = trim($this->email);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', ...($this->email === $user->email ? [] : [new GmailAddress($user->id)])],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->email !== $user->email) {
            $validated['email'] = AuthEmail::normalize($validated['email']);

            if ($validated['email'] === AuthEmail::normalize($user->email)) {
                $validated['email'] = $user->email;
            }
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function updateAvatar(): void
    {
        $this->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $this->avatar->store('avatars', 'public');

        $user->update(['avatar_path' => $path]);

        $this->reset('avatar');

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $this->resetErrorBag('verification');
        Session::forget('status');
        $throttleKey = 'verification:'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            throw ValidationException::withMessages([
                'verification' => 'Please wait '.RateLimiter::availableIn($throttleKey).' seconds before requesting another verification email.',
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('verification', 'We could not send your verification email. Please try again shortly.');

            return;
        }

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<div class="account-profile-grid">
    <aside class="account-profile-card overflow-hidden rounded-3xl border border-blue-200 bg-white" aria-labelledby="account-profile-name">
        <div class="account-profile-hero relative isolate overflow-hidden border-b border-blue-100 p-6 sm:p-7">
            <div class="account-profile-dots pointer-events-none absolute right-5 top-5 h-20 w-20" aria-hidden="true"></div>
            <div class="relative z-10">
                <div class="relative mb-1 h-36" aria-hidden="true">
                    <span class="account-profile-orbit absolute left-10 top-0 h-36 w-36 rounded-full border border-blue-200/70"></span>
                    <span class="animate-float-slow absolute right-2 top-1 grid h-12 w-12 place-items-center rounded-2xl border border-white/90 bg-white/80 text-blue-600 shadow-[0_12px_28px_rgba(37,99,235,0.12)] backdrop-blur-sm"><x-icon name="map-pin" class="h-6 w-6" /></span>
                    <span class="animate-float-reverse absolute bottom-1 right-0 grid h-11 w-11 place-items-center rounded-2xl border border-white/90 bg-white/85 text-indigo-500 shadow-[0_12px_28px_rgba(37,99,235,0.12)] backdrop-blur-sm"><x-icon name="archive" class="h-5 w-5" /></span>
                    <span class="absolute left-0 top-2 text-sky-400"><x-icon name="sparkles" class="h-4 w-4" /></span>
                </div>
                <div class="account-profile-avatar absolute left-0 top-7 h-24 w-24 overflow-hidden rounded-3xl border-4 border-white bg-gradient-to-br from-blue-100 to-indigo-100 shadow-[0_14px_32px_rgba(37,99,235,0.16)]">
                    @if ($avatar && $avatar->isPreviewable())
                        <img src="{{ $avatar->temporaryUrl() }}" alt="Preview of your new profile photo" class="h-full w-full object-cover" />
                    @elseif (auth()->user()->avatar_path)
                        <img src="{{ auth()->user()->avatarUrl() }}" alt="Your profile photo" class="h-full w-full object-cover" />
                    @else
                        <span class="grid h-full w-full place-items-center text-4xl font-bold text-blue-700" aria-hidden="true">{{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                    @endif
                    <x-input-error :messages="$errors->get('verification')" class="mt-2" />
                </div>

                <p class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wider text-blue-600">{{ auth()->user()->isAdmin() ? 'Administrator' : 'Community member' }}</p>
                <h2 id="account-profile-name" class="break-words text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ auth()->user()->name }}</h2>
                <p class="mt-2 break-all text-sm text-slate-500">{{ auth()->user()->email }}</p>
                <p class="mt-3 flex items-center gap-2 text-xs text-slate-500"><x-icon name="user-circle" class="h-4 w-4 shrink-0" aria-hidden="true" /> Member since {{ auth()->user()->created_at->format('M Y') }}</p>
                <span class="mt-4 inline-flex rounded-full border px-3 py-1.5 text-xs font-semibold {{ auth()->user()->hasVerifiedEmail() ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">{{ auth()->user()->hasVerifiedEmail() ? 'Email verified' : 'Email not verified' }}</span>
                @unless (auth()->user()->isAdmin())
                    <div class="mt-3">
                        @if (auth()->user()->hasVerifiedIdentity())
                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">{{ auth()->user()->identity_verification_environment === 'manual' ? 'ID reviewed' : 'Identity verified' }}</span>
                        @elseif (auth()->user()->hasTestedIdentity())
                            <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800">Test identity check completed</span>
                        @else
                            <p class="text-xs font-medium text-slate-600">Identity not verified</p>
                        @endif
                        <a href="{{ route('identity.verify') }}" wire:navigate class="mt-2 inline-flex min-h-10 items-center rounded-lg text-sm font-semibold text-blue-700 underline underline-offset-4 focus:outline-none focus:ring-4 focus:ring-blue-100">{{ auth()->user()->hasVerifiedIdentity() ? 'View identity verification' : 'Verify your identity' }}</a>
                        <p class="mt-1 text-xs leading-5 text-slate-500">One identity check covers renting and listing items.</p>
                    </div>
                @endunless
            </div>
        </div>

        <div class="space-y-5 p-6 sm:p-7">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Profile photo</h3>
                <p id="avatar-help" class="mt-1 text-xs leading-5 text-slate-500">Choose an image up to 2 MB.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <label class="relative inline-flex min-h-10 cursor-pointer items-center justify-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:border-blue-300 hover:bg-blue-100 focus-within:ring-4 focus-within:ring-blue-100">
                        <x-icon name="camera" class="h-4 w-4" aria-hidden="true" />
                        Change photo
                        <input type="file" wire:model="avatar" accept="image/*" aria-describedby="avatar-help" class="sr-only" />
                    </label>
                    @if ($avatar && $avatar->isPreviewable())
                        <button type="button" wire:click="updateAvatar" wire:loading.attr="disabled" wire:target="avatar, updateAvatar" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:opacity-60">Save photo</button>
                    @elseif (auth()->user()->avatar_path)
                        <button type="button" wire:click="removeAvatar" wire:confirm="Remove your profile photo?" wire:loading.attr="disabled" wire:target="avatar, removeAvatar" class="inline-flex min-h-10 items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:opacity-60">Remove</button>
                    @endif
                </div>
                <p wire:loading wire:target="avatar" class="mt-2 text-xs text-blue-600" role="status">Uploading photo…</p>
                <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
            </div>

            @unless (auth()->user()->isAdmin())
                <div class="border-t border-slate-100 pt-5">
                    <a href="{{ route('users.show', auth()->user()) }}" wire:navigate class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">View public profile <x-icon name="chevron-down" class="h-4 w-4 -rotate-90" aria-hidden="true" /></a>
                    <p class="mt-2 text-xs leading-5 text-slate-500">See your profile and reviews as other members see them.</p>
                </div>
            @endunless
        </div>
    </aside>

    <div class="account-profile-settings space-y-6">
        <section id="profile-information" class="account-profile-section overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="profile-information-title">
            <header class="flex items-start gap-3 border-b border-slate-100 p-6 sm:p-7">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600"><x-icon name="user-circle" class="h-5 w-5" aria-hidden="true" /></span>
                <div class="min-w-0">
                    <h2 id="profile-information-title" class="text-lg font-bold text-slate-900 sm:text-xl">{{ __('Personal details') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">{{ __('Keep your account and contact information up to date.') }}</p>
                </div>
            </header>

            <form wire:submit="updateProfileInformation" class="space-y-6 p-6 sm:p-7">
                <fieldset>
                    <legend class="mb-4 text-sm font-bold text-slate-800">Account information</legend>
                    <div class="account-profile-fields">
                        <div>
                            <x-input-label required for="name" :value="__('Name')" />
                            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-2 block min-h-11 w-full bg-slate-50/50" required autocomplete="name" />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>
                        <div>
                            <x-input-label required for="email" :value="__('Email')" />
                            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-2 block min-h-11 w-full bg-slate-50/50" required autocomplete="username" />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>
                    </div>

                    @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-sm font-medium text-amber-800">{{ __('Your email address is unverified.') }}</p>
                            <button type="button" wire:click="sendVerification" wire:loading.attr="disabled" wire:target="sendVerification" class="mt-2 inline-flex min-h-10 items-center rounded-lg text-sm font-semibold text-blue-700 underline underline-offset-4 transition hover:text-blue-900 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:opacity-60">{{ __('Re-send verification email') }}</button>
                            @if (session('status') === 'verification-link-sent')
                                <p class="mt-2 text-sm font-medium text-green-700" role="status">{{ __('A new verification link has been sent to your email address.') }}</p>
                            @endif
                        </div>
                    @endif
                </fieldset>

                <fieldset class="border-t border-slate-100 pt-5">
                    <legend class="float-left mb-4 w-full text-sm font-bold text-slate-800">Contact details <span class="ml-1 text-xs font-normal text-slate-400">Optional</span></legend>
                    <div class="account-profile-fields clear-both">
                        <div>
                            <x-input-label for="phone" :value="__('Phone number')" />
                            <x-text-input wire:model="phone" id="phone" name="phone" type="tel" class="mt-2 block min-h-11 w-full bg-slate-50/50" autocomplete="tel" placeholder="Add a phone number" />
                            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                        </div>
                        <div>
                            <x-input-label for="address" :value="__('Address')" />
                            <x-text-input wire:model="address" id="address" name="address" type="text" class="mt-2 block min-h-11 w-full bg-slate-50/50" autocomplete="street-address" placeholder="Add your address" />
                            <x-input-error class="mt-2" :messages="$errors->get('address')" />
                        </div>
                    </div>
                </fieldset>

                <div class="flex flex-wrap items-center justify-end gap-4 border-t border-slate-100 pt-5">
                    <x-action-message on="profile-updated" class="text-emerald-700" role="status">{{ __('Changes saved.') }}</x-action-message>
                    <x-primary-button class="min-h-11" wire:loading.attr="disabled" wire:target="updateProfileInformation">{{ __('Save changes') }}</x-primary-button>
                </div>
            </form>
        </section>

        <livewire:profile.update-password-form />
        <livewire:profile.delete-user-form />
    </div>
</div>
