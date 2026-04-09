<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class PortfolioController
{
    public function __invoke(): Response
    {
        return Inertia::render('Portfolio', [
            'pageMode' => 'default',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Portfolio', [
            'pageMode' => 'create',
        ]);
    }
}
