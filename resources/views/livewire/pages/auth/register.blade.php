<?php

use App\Models\User;
use App\Rules\GmailAddress;
use App\Support\AuthEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request. Every account can both rent
     * and list items — there's no role to choose anymore.
     */
    public function register(): void
    {
        $throttleKey = 'registration:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many signup attempts. Please try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        RateLimiter::hit($throttleKey, 60);
        $this->email = trim($this->email);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', new GmailAddress],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['email'] = AuthEmail::normalize($validated['email']);
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        Auth::login($user);
        Session::regenerate();

        try {
            event(new Registered($user));
        } catch (Throwable $exception) {
            report($exception);
            Session::flash('status', 'verification-mail-failed');
        }

        $this->redirect(route('verification.notice', absolute: false), navigate: true);
    }
}; ?>

<div x-data="{ showPassword: false, showConfirmation: false }">
    <div class="mb-7">
        <span class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.15em] text-blue-700 ring-1 ring-blue-100">
            <span class="h-1.5 w-1.5 rounded-full bg-blue-600"></span>
            Join the community
        </span>
        <h1 class="mt-4 text-3xl font-extrabold tracking-[-0.04em] text-[#071a3d] sm:text-4xl">Create your free account.</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">One account lets you rent what you need and earn from the useful things you already own.</p>
        <p class="mt-2 text-sm leading-6 text-slate-500">A National ID and selfie review is required before you can rent or list items.</p>
    </div>

    <x-google-sign-in :href="route('auth.google.redirect', ['prompt' => 'consent'])" />

    <form wire:submit="register" class="space-y-5">
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Full name <x-required-mark /></label>
                <div class="group relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 transition group-focus-within:text-blue-600">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="6.5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M4.5 16c.45-3 2.28-4.5 5.5-4.5s5.05 1.5 5.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    </span>
                    <input wire:model="name" id="name" type="text" name="name" required autofocus autocomplete="name" aria-describedby="registration-name-help" placeholder="Juan Dela Cruz" class="block w-full rounded-2xl border-slate-200 bg-slate-50/80 py-3.5 pl-12 pr-4 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100" />
                </div>
                <p id="registration-name-help" class="mt-2 text-xs leading-5 text-slate-500">Use your full name as it appears on your National ID, including your middle name and suffix if any.</p>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label for="phone" class="mb-2 block text-sm font-semibold text-slate-700">Phone <span class="font-normal text-slate-400">(optional)</span></label>
                <div class="group relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 transition group-focus-within:text-blue-600">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M6.3 3.5H4.9c-.77 0-1.4.63-1.4 1.4 0 6.4 5.2 11.6 11.6 11.6.77 0 1.4-.63 1.4-1.4v-1.4l-3.1-1.05-.72 1.45c-2.95-1.26-5.52-3.83-6.78-6.78l1.45-.72L6.3 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                    </span>
                    <input wire:model="phone" id="phone" type="tel" name="phone" autocomplete="tel" placeholder="09XX XXX XXXX" class="block w-full rounded-2xl border-slate-200 bg-slate-50/80 py-3.5 pl-12 pr-4 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100" />
                </div>
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
        </div>

        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Gmail address <x-required-mark /></label>
            <div class="group relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 transition group-focus-within:text-blue-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 5.75A1.75 1.75 0 0 1 4.75 4h10.5A1.75 1.75 0 0 1 17 5.75v8.5A1.75 1.75 0 0 1 15.25 16H4.75A1.75 1.75 0 0 1 3 14.25v-8.5Z" stroke="currentColor" stroke-width="1.5"/><path d="m4 6 5.06 3.65a1.6 1.6 0 0 0 1.88 0L16 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </span>
                <input wire:model="email" id="email" type="email" name="email" required autocomplete="username" placeholder="you@gmail.com" class="block w-full rounded-2xl border-slate-200 bg-slate-50/80 py-3.5 pl-12 pr-4 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100" />
            </div>
            <p class="mt-1 text-xs text-slate-500">Use your own @gmail.com address. You will need to verify it before renting or listing items.</p>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div x-data="passwordStrength($wire, 'password')">
                <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Password <x-required-mark /></label>
                <div class="group relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 transition group-focus-within:text-blue-600">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="4" y="8" width="12" height="9" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M6.75 8V6.25a3.25 3.25 0 0 1 6.5 0V8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    </span>
                    <input wire:model="password" id="password" type="password" x-bind:type="showPassword ? 'text' : 'password'" x-on:copy.prevent x-on:cut.prevent x-on:paste.prevent x-on:drop.prevent aria-describedby="password_requirements" name="password" required autocomplete="new-password" placeholder="At least 8 characters" class="block w-full rounded-2xl border-slate-200 bg-slate-50/80 py-3.5 pl-12 pr-11 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100" />
                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400 transition hover:text-blue-600" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2.5 10s2.75-4.5 7.5-4.5 7.5 4.5 7.5 4.5-2.75 4.5-7.5 4.5S2.5 10 2.5 10Z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="10" r="2" stroke="currentColor" stroke-width="1.5"/></svg>
                    </button>
                </div>
                <x-password-strength id="password_requirements" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-700">Confirm password <x-required-mark /></label>
                <div class="group relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 transition group-focus-within:text-blue-600">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="4" y="8" width="12" height="9" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M6.75 8V6.25a3.25 3.25 0 0 1 6.5 0V8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    </span>
                    <input wire:model="password_confirmation" id="password_confirmation" type="password" x-bind:type="showConfirmation ? 'text' : 'password'" x-on:copy.prevent x-on:cut.prevent x-on:paste.prevent x-on:drop.prevent name="password_confirmation" required autocomplete="new-password" placeholder="Repeat your password" class="block w-full rounded-2xl border-slate-200 bg-slate-50/80 py-3.5 pl-12 pr-11 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100" />
                    <button type="button" @click="showConfirmation = !showConfirmation" class="absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400 transition hover:text-blue-600" :aria-label="showConfirmation ? 'Hide password' : 'Show password'">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2.5 10s2.75-4.5 7.5-4.5 7.5 4.5 7.5 4.5-2.75 4.5-7.5 4.5S2.5 10 2.5 10Z" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="10" r="2" stroke="currentColor" stroke-width="1.5"/></svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <p class="text-xs leading-5 text-slate-400">By creating an account, you agree to use Lendly responsibly and keep every rental transaction safe and respectful.</p>

        <button type="submit" wire:loading.attr="disabled" class="group inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-[#075cf5] px-5 py-3.5 text-sm font-bold text-white shadow-[0_12px_28px_rgba(7,92,245,0.28)] transition duration-300 hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-[0_16px_34px_rgba(7,92,245,0.34)] focus:outline-none focus:ring-4 focus:ring-blue-200 disabled:cursor-wait disabled:opacity-70">
            <span wire:loading.remove wire:target="register">Create my free account</span>
            <span wire:loading wire:target="register">Creating your account…</span>
            <svg wire:loading.remove wire:target="register" class="h-4 w-4 transition-transform group-hover:translate-x-1" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4.5 10h11m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </form>

    <div class="mt-6 border-t border-slate-100 pt-5 text-center">
        <p class="text-sm text-slate-500">Already have an account? <a href="{{ route('login') }}" wire:navigate class="font-bold text-blue-600 transition hover:text-blue-800">Log in instead</a></p>
    </div>
</div>
