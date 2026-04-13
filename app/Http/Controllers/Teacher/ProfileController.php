<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(string $slug, int $id): Response
    {
        return Inertia::render('Teachers/PublicProfile', [
            'id'   => $id,
            'slug' => $slug,
        ]);
    }
}
