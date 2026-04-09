<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class NotesController
{
    public function __invoke(): Response
    {
        return Inertia::render('Notes');
    }
}

