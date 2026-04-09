<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class SupportTicketDetailsController
{
    public function __invoke(int $supportTicket): Response
    {
        return Inertia::render('SupportTicketDetails', [
            'supportTicketId' => $supportTicket,
        ]);
    }
}

