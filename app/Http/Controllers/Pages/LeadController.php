<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class LeadController
{
    public function __invoke(): Response
    {
        return Inertia::render('Leads');
    }
}

