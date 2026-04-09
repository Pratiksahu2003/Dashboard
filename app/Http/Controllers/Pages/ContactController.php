<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class ContactController
{
    public function __invoke(): Response
    {
        return Inertia::render('Contact');
    }
}

