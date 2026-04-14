<?php

namespace App\Http\Controllers\Institutes;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(string $slug, int $id): Response
    {
        return Inertia::render('Institutes/PublicProfile', [
            'id' => $id,
            'slug' => $slug,
        ]);
    }
}
