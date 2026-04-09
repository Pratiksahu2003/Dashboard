<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class NotificationsController
{
    public function __invoke(): Response
    {
        return Inertia::render('Notifications');
    }
}

