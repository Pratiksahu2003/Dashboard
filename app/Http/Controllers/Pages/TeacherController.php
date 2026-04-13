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

    /** SEO-friendly path `/teachers/{slug}/{id}` (slug is cosmetic; profile is loaded by id). */
    public function show(string $slug, int $id): Response
    {
        return Inertia::render('TeacherProfile', [
            'id' => $id,
            'slug' => $slug,
        ]);
    }

    /** Old `/teachers/{id}` links: SPA will replace the URL with the canonical slugged path after load. */
    public function showLegacy(int $id): Response
    {
        return Inertia::render('TeacherProfile', [
            'id' => $id,
            'slug' => null,
        ]);
    }
}
