<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class PaymentsController
{
    public function __invoke(): Response
    {
        return Inertia::render('Payments');
    }
}

