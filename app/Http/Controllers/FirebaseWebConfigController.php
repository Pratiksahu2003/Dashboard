<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class FirebaseWebConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json([
                'apiKey' => env('VITE_FIREBASE_API_KEY'),
                'authDomain' => env('VITE_FIREBASE_AUTH_DOMAIN', 'suganta-tutors.firebaseapp.com'),
                'projectId' => env('VITE_FIREBASE_PROJECT_ID', 'suganta-tutors'),
                'storageBucket' => env('VITE_FIREBASE_STORAGE_BUCKET', 'suganta-tutors.appspot.com'),
                'messagingSenderId' => env('VITE_FIREBASE_MESSAGING_SENDER_ID'),
                'appId' => env('VITE_FIREBASE_APP_ID'),
                'measurementId' => env('VITE_FIREBASE_MEASUREMENT_ID'),
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}

