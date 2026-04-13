<?php

namespace App\Http\Controllers\Pages;

use Illuminate\Http\RedirectResponse;

class LeadCreateController
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('leads', ['create' => '1']);
    }
}
