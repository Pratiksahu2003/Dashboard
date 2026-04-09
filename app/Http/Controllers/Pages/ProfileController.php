<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class ProfileController
{
    public function __invoke(): Response
    {
        return Inertia::render('Profile');
    }
}

