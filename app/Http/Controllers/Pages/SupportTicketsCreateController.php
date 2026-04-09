<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class SupportTicketsCreateController
{
    public function __invoke(): Response
    {
        return Inertia::render('SupportTickets');
    }
}

