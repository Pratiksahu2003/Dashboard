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

    public function show(string $slug, int $id): Response
    {
        return Inertia::render('TeacherProfile', ['id' => $id, 'slug' => $slug]);
    }
}
