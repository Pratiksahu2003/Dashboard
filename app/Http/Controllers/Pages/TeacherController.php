<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class TeacherController
{
    public function index(): Response
    {
        return Inertia::render('Teachers');
    }

    /** SEO-friendly path `/teachers/{id}/{slug}` (slug is cosmetic; profile is loaded by id). */
    public function show(int $id, string $slug): Response
    {
        return Inertia::render('TeacherProfile', [
            'id' => $id,
            'slug' => $slug,
        ]);
    }

    /** Old `/teachers/{id}` only: SPA replaces URL with canonical `/teachers/{id}/{slug}` after load. */
    public function showLegacy(int $id): Response
    {
        return Inertia::render('TeacherProfile', [
            'id' => $id,
            'slug' => null,
        ]);
    }
}
