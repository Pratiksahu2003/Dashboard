<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class SubscriptionsController
{
    public function __invoke(): Response
    {
        return Inertia::render('Subscriptions');
    }
}

