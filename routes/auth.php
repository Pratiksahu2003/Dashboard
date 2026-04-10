<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * Guest-only auth pages.
 * SyncApiUser middleware has already run and stored the resolved user in
 * request()->attributes->get('api_user'). If a user is already authenticated,
 * redirect them to the dashboard instead of showing the auth page.
 */
$redirectIfAuthenticated = function () {
    if (request()->attributes->get('api_user')) {
        return redirect()->route('dashboard');
    }
    return null;
};

Route::get('login', function () use ($redirectIfAuthenticated) {
    if ($redirect = $redirectIfAuthenticated()) return $redirect;

    return Inertia::render('Auth/Login', [
        'canResetPassword' => Route::has('password.request'),
        'status' => request()->query('status', session('status')),
    ]);
})->name('login');

Route::get('register', function () use ($redirectIfAuthenticated) {
    if ($redirect = $redirectIfAuthenticated()) return $redirect;

    return Inertia::render('Auth/Register');
})->name('register');

Route::get('forgot-password', function () use ($redirectIfAuthenticated) {
    if ($redirect = $redirectIfAuthenticated()) return $redirect;

    return Inertia::render('Auth/ForgotPassword');
})->name('password.request');

Route::get('reset-password/{token}', function (string $token) use ($redirectIfAuthenticated) {
    if ($redirect = $redirectIfAuthenticated()) return $redirect;

    return Inertia::render('Auth/ResetPassword', [
        'token' => $token,
        'email' => request()->query('email', ''),
    ]);
})->name('password.reset');

/*
 * Not redirected even when authenticated — these screens must be reachable
 * during the OTP and payment flows regardless of session state.
 */
Route::get('otp-verify', function () {
    return Inertia::render('Auth/VerifyOtp');
})->name('auth.otp.verify');

Route::get('payment-required', function () {
    return Inertia::render('Auth/Payment');
})->name('auth.payment.required');
