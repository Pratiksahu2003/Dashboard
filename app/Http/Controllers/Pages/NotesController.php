<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class NotesController
{
    public function index(): Response
    {
        return Inertia::render('Notes');
    }

    public function show(int $note): Response
    {
        return Inertia::render('NotesDetails', [
            'noteId' => $note,
        ]);
    }
}

