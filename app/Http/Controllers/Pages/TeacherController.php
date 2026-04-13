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

    public function show(int $id): Response
    {
        return Inertia::render('TeacherProfile', ['id' => $id]);
    }
}
