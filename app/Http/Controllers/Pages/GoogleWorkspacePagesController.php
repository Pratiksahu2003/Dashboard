<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class GoogleWorkspacePagesController
{
    public function overview(): Response
    {
        return Inertia::render('GoogleWorkspace');
    }

    public function calendar(): Response
    {
        return Inertia::render('GoogleWorkspaceCalendar');
    }

    public function drive(): Response
    {
        return Inertia::render('GoogleWorkspaceDrive');
    }

    public function youtube(): Response
    {
        return Inertia::render('GoogleWorkspaceYoutube');
    }
}

