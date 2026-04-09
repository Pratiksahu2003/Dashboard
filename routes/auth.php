<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('login', function () {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => request()->query('status', session('status')),
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
 * Not under `guest`: these screens must load when the user already has a Sanctum/API session
 * or Laravel web auth. `guest` redirects authenticated users to /dashboard, which caused a
 * loop with AppLayout redirecting unverified users back to /verify-email.
 */
Route::get('otp-verify', function () {
    return Inertia::render('Auth/VerifyOtp');
})->name('auth.otp.verify');

Route::get('payment-required', function () {
    return Inertia::render('Auth/RegistrationFee');
})->name('auth.payment.required');

Route::get('verify-email', function () {
    return Inertia::render('Auth/VerifyEmail');
})->name('auth.verify.email');
