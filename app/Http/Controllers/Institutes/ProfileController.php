<?php

namespace App\Http\Controllers\Institutes;

use App\Http\Controllers\Controller;
use App\Support\PublicProfileInertiaSeo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request, string $slug, int $id): Response
    {
        return Inertia::render('Institutes/PublicProfile', [
            'id' => $id,
            'slug' => $slug,
        ])->withViewData(PublicProfileInertiaSeo::viewData($request, $slug, 'institute'));
    }
}
