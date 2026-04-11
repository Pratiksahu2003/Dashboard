<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class BroadcastingAuthProxyController extends Controller
{
    public function __invoke(Request $request): Response|JsonResponse
    {
        $apiOrigin = config('services.suganta.api_origin');

        $socketId = (string) $request->input('socket_id');
        $channelName = (string) $request->input('channel_name');

        // ✅ Try local signing first
        $local = $this->tryLocalSign($request, $apiOrigin, $socketId, $channelName);
        if ($local) {
            return $local->header('Cache-Control', 'no-store, max-age=0');
        }

        // ✅ Fallback → Call API server
        $upstream = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => $request->header('Authorization'), // if token used
            'X-CSRF-TOKEN' => $request->header('X-CSRF-TOKEN'),
        ])->asForm()->post($apiOrigin . '/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => $channelName,
        ]);

        // ✅ Handle failure
        if ($upstream->failed()) {
            Log::error('Broadcast auth proxy failed', [
                'status' => $upstream->status(),
                'body' => $upstream->body(),
            ]);

            return response()->json([
                'message' => 'Broadcast auth failed',
            ], 403);
        }

        // ✅ Return API response
        return response($upstream->body(), $upstream->status())->withHeaders([
            'Content-Type' => $upstream->header('Content-Type', 'application/json'),
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function tryLocalSign(Request $request, string $apiOrigin, string $socketId, string $channelName): Response|JsonResponse|null
    {
        if (!str_starts_with($channelName, 'private-chat.conversation.')) {
            return null;
        }

        $conversationId = (int) substr($channelName, strlen('private-chat.conversation.'));
        if ($conversationId <= 0) {
            return null;
        }

        $key = config('services.suganta.reverb_app_key');
        $secret = config('services.suganta.reverb_app_secret');

        if (!is_string($key) || trim($key) === '' || !is_string($secret) || trim($secret) === '') {
            Log::warning('broadcasting.auth.proxy: missing key/secret');
            return null;
        }

        // ✅ Generate signature
        $signature = hash_hmac('sha256', $socketId . ':' . $channelName, $secret);

        return response()->json([
            'auth' => $key . ':' . $signature,
        ]);
    }
}