<?php

namespace App\Http\Controllers\Pages;

use Inertia\Inertia;
use Inertia\Response;

class ChatController
{
    public function __invoke(?int $conversation = null): Response
    {
        return Inertia::render('Chat', [
            'initialConversationId' => $conversation,
        ]);
    }
}

