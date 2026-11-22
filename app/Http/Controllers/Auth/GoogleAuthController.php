<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\SuspendedGoogleAccount;
use App\Http\Controllers\Controller;
use App\Livewire\Actions\Logout;
use App\Models\User;
use App\Support\AccountLockout;
use App\Support\AuthEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->forget(['state', 'google_oauth_interface', 'google_oauth_pending_user', 'google_account_deletion']);
        if (! $this->configured()) {
            return $this->failed();
        }

        try {
            return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])
                ->with(['prompt' => $request->query('prompt') === 'consent' ? 'select_account consent' : 'select_account'])->redirect();
        } catch (Throwable $exception) {
            $request->session()->forget(['state', 'google_oauth_interface']);

            return $this->failed('redirect', $exception);
        }
    }

    public function confirmDeletion(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->eligible($user) && $user->google_id, 403);

        $request->session()->forget(['state', 'google_account_deletion']);

        if ($this->configured()) {
            try {
                $redirect = Socialite::driver('google')->scopes(['openid', 'profile', 'email'])
                    ->with(['prompt' => 'select_account'])->redirect();
                $request->session()->put('google_account_deletion', [
                    'user_id' => $user->id,
                    'google_id' => $user->google_id,
                    'expires_at' => now()->addMinutes(5)->timestamp,
                ]);

                return $redirect;
            } catch (Throwable $exception) {
                $request->session()->forget('state');
                Log::warning('Google account deletion redirect failed.', ['exception_type' => $exception::class]);
            }
        }

        return redirect()->route('profile')->withErrors(['account' => 'Google confirmation is unavailable. Please try again.']);
    }

    public function callback(Request $request, Logout $logout): RedirectResponse
    {
        if ($confirmation = $request->session()->pull('google_account_deletion')) {
            return $this->finishDeletion($request, $confirmation, $logout);
        }

        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        $request->session()->forget('google_oauth_pending_user');
        $stage = 'profile';
        try {
            if (! $this->configured() || $request->has('error')) {
                return $this->failed('configuration_or_provider');
            }

            // Stateful Socialite consumes and validates state before exchanging the authorization code.
            $profile = Socialite::driver('google')->user();
            $id = $profile->getId();
            $email = $profile->getEmail();
            $name = $profile->getName();
            $raw = $profile->getRaw();
            if (! is_string($id) || trim($id) === '' || strlen($id) > 255
                || ! is_string($email) || strlen($email) > 255
                || ! filter_var(trim($email), FILTER_VALIDATE_EMAIL)
                || ! is_array($raw) || (($raw['email_verified'] ?? null) !== true && ($raw['verified_email'] ?? null) !== true)
                // Google is authoritative for Gmail and managed Workspace addresses only.
                || (! str_ends_with(strtolower(trim($email)), '@gmail.com')
                    && (! is_string($raw['hd'] ?? null) || trim($raw['hd']) === ''))
                || ($name !== null && (! is_string($name) || ! mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > 255))) {
                return $this->failed('profile_validation');
            }
            $email = AuthEmail::normalize($email);
            AccountLockout::ensureNotLocked($email, 'google');

            $stage = 'account';
            $user = DB::transaction(function () use ($id, $email, $name) {
                $linked = User::withTrashed()->where('google_id', $id)->first();
                // shortcut: legacy Gmail aliases require a scan; add an indexed canonical column at larger account volumes.
                $matches = User::withTrashed()->where(fn ($query) => $query
                    ->whereRaw('LOWER(email) LIKE ?', ['%@gmail.com'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%@googlemail.com'])
                    ->orWhereRaw('LOWER(email) = ?', [$email]))
                    ->get()->filter(fn (User $candidate) => AuthEmail::normalize($candidate->email) === $email);
                $ids = $matches->pluck('id');
                if ($linked) {
                    $ids->push($linked->id);
                }
                $accounts = User::withTrashed()->whereIn('id', $ids->unique()->sort()->all())->orderBy('id')->lockForUpdate()->get();
                $matches = $accounts->filter(fn (User $candidate) => AuthEmail::normalize($candidate->email) === $email);
                if ($matches->count() > 1 || ($linked && ($matches->count() !== 1 || $matches->first()->id !== $linked->id))) {
                    throw $this->rejected();
                }
                $user = $matches->first();
                if ($user) {
                    if ($user->trashed() || $user->isAdmin()
                        || ($user->google_id !== null && $user->google_id !== $id)) {
                        throw $this->rejected();
                    }
                    if ($user->isSuspended()) {
                        throw new SuspendedGoogleAccount;
                    }
                    if (! $user->hasVerifiedEmail()) {
                        // Discard credentials and sessions from an earlier, unconfirmed signup.
                        $user->forceFill([
                            'email_verified_at' => now(), 'password' => Str::random(64), 'remember_token' => Str::random(60),
                        ]);
                        DB::table(config('auth.passwords.users.table'))->where('email', $user->email)->delete();
                        if (config('session.driver') === 'database') {
                            DB::connection(config('session.connection'))->table(config('session.table'))
                                ->where('user_id', $user->id)->delete();
                        }
                    }
                    $user->forceFill(['google_id' => $id])->save();
                } else {
                    $user = new User;
                    $user->forceFill([
                        'name' => trim($name ?? '') ?: 'Google member', 'email' => $email,
                        'email_verified_at' => now(), 'google_id' => $id, 'password' => Str::random(64),
                    ])->save();
                }
                $user = User::withTrashed()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                if ($user->google_id !== $id || AuthEmail::normalize($user->email) !== $email) {
                    throw $this->rejected();
                }
                $this->ensureEligible($user);
                AccountLockout::ensureNotLocked($email, 'google');
                Auth::guard('web')->login($user);
                AccountLockout::ensureNotLocked($email, 'google');
                $user->refresh();
                $this->ensureEligible($user);

                return $user;
            });

            $stage = 'session';
            $request->session()->regenerate();
            $request->session()->forget(['url.intended', 'active_interface']);
            $request->session()->put('google_oauth_pending_user', $user->id);
            AccountLockout::clear($email);

            return redirect()->route('auth.google.interface');
        } catch (Throwable $exception) {
            // Remote exceptions can include tokens and profile data; expose only the generic sign-in error.
            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
            }
            $request->session()->forget('google_oauth_pending_user');

            return $this->failed($stage, $exception);
        } finally {
            $request->session()->forget(['state', 'google_oauth_interface']);
        }
    }

    private function finishDeletion(Request $request, array $confirmation, Logout $logout): RedirectResponse
    {
        try {
            $member = $request->user();
            if (! $member) {
                return $this->failed('deletion_session');
            }
            if ($member->id !== $confirmation['user_id'] || now()->timestamp >= $confirmation['expires_at']) {
                return redirect()->route('profile')->withErrors(['account' => 'Google confirmation expired. Start again from your profile.']);
            }
            if (! $this->configured() || $request->has('error')) {
                return redirect()->route('profile')->withErrors(['account' => 'Account deletion was not confirmed with Google.']);
            }

            $googleId = Socialite::driver('google')->user()->getId();
            DB::transaction(function () use ($member, $confirmation, $googleId): void {
                $member = User::query()->lockForUpdate()->find($member->id);
                if (! $member || ! $this->eligible($member) || ! $member->google_id
                    || $member->google_id !== $confirmation['google_id'] || $member->google_id !== $googleId) {
                    throw ValidationException::withMessages(['account' => 'Confirm with the Google account linked to this Lendly account.']);
                }

                $member->closeAccount();
            });

            $logout();

            return redirect('/');
        } catch (ValidationException $exception) {
            return redirect()->route('profile')->withErrors($exception->errors());
        } catch (Throwable $exception) {
            Log::warning('Google account deletion confirmation failed.', ['exception_type' => $exception::class]);

            return redirect()->route('profile')->withErrors(['account' => 'Google confirmation could not be completed. Please try again.']);
        } finally {
            $request->session()->forget('state');
        }
    }

    private function configured(): bool
    {
        foreach (['client_id', 'client_secret', 'redirect'] as $key) {
            if (! is_string(config('services.google.'.$key)) || trim(config('services.google.'.$key)) === '') {
                return false;
            }
        }

        return true;
    }

    private function eligible(User $user): bool
    {
        return ! $user->trashed() && ! $user->isSuspended() && ! $user->isAdmin() && $user->hasVerifiedEmail();
    }

    private function ensureEligible(User $user): void
    {
        if (! $user->trashed() && ! $user->isAdmin() && $user->isSuspended()) {
            throw new SuspendedGoogleAccount;
        }
        if (! $this->eligible($user)) {
            throw $this->rejected();
        }
    }

    private function rejected(): ValidationException
    {
        return ValidationException::withMessages(['google' => 'Google sign-in could not be completed. Please try again or use your password.']);
    }

    private function failed(string $stage = 'configuration', ?Throwable $exception = null): RedirectResponse
    {
        Log::warning('Google sign-in failed.', array_filter([
            'stage' => $stage,
            'exception_type' => $exception ? $exception::class : null,
            'exception_code' => $exception?->getCode(),
        ], fn ($value) => $value !== null));

        return redirect()->route('login')->withErrors($exception instanceof SuspendedGoogleAccount
            ? ['google' => 'Your account has been suspended. Please contact support for assistance.']
            : $this->rejected()->errors());
    }
}
