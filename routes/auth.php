<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->middleware('throttle:20,1')->name('auth.google.redirect');
    Volt::route('register', 'pages.auth.register')
        ->name('register');

    Volt::route('login', 'pages.auth.login')
        ->name('login');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password', 'pages.auth.reset-password')
        ->name('password.reset');

    Route::redirect('reset-password/{token}', '/forgot-password');
});

Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:20,1')->name('auth.google.callback');

Route::middleware('auth')->group(function () {
    Route::post('profile/delete-with-google', [GoogleAuthController::class, 'confirmDeletion'])
        ->middleware('throttle:20,1')->name('profile.delete-with-google');
    Volt::route('auth/google/interface', 'pages.auth.google-interface')
        ->middleware('verified')->name('auth.google.interface');

    Volt::route('verify-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');
});
