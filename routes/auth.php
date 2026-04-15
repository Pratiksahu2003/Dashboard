<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * Guest-only auth pages (EnsureGuest resolves /auth/user; redirects if already authenticated).
 */
Route::middleware('guest')->group(function () {
    Route::get('login', function () {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => request()->query('status', session('status')),
            'openOtpVerify' => request()->boolean('otp_verify'),
            'returnTo' => request()->query('redirect'),
            'message' => request()->query('message'),
        ]);
    })->name('login');

    Route::get('register', function () {
        return Inertia::render('Auth/Register');
    })->name('register');

    Route::get('forgot-password', function () {
        return Inertia::render('Auth/ForgotPassword');
    })->name('password.request');

    Route::get('reset-password/{token}', function (string $token) {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => request()->query('email', ''),
        ]);
    })->name('password.reset');
});

/*
 * Not redirected even when authenticated — these screens must be reachable
 * during the OTP and payment flows regardless of session state.
 */
Route::get('otp-verify', function () {
    return redirect()->route('login', ['otp_verify' => 1]);
})->name('auth.otp.verify');

Route::get('payment-required', function () {
    return Inertia::render('Auth/Payment');
})->name('auth.payment.required');
