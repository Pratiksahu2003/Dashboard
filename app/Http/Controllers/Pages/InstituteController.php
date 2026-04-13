<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class InstituteController
{
    public function index(): Response
    {
        return Inertia::render('Institutes');
    }

    public function show(int $id, string $slug): Response
    {
        return Inertia::render('InstituteProfile', [
            'id' => $id,
            'slug' => $slug,
        ]);
    }

    public function showLegacy(int $id): Response
    {
        return Inertia::render('InstituteProfile', [
            'id' => $id,
            'slug' => null,
        ]);
    }
}
