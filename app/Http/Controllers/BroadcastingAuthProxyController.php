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

        $auth = $request->header('Authorization', '');
        if (!is_string($auth) || trim($auth) === '') {
            Log::warning('broadcasting.auth.proxy: missing Authorization header', [
                'socket_id' => $socketId,
                'channel_name' => $channelName,
                'ip' => $request->ip(),
                'origin' => $request->header('Origin'),
                'referer' => $request->header('Referer'),
            ]);
            return response([
                'message' => 'Unauthenticated.',
            ], 401)->header('Cache-Control', 'no-store, max-age=0');
        }

        if (trim($socketId) === '' || trim($channelName) === '') {
            return response([
                'message' => 'Invalid request.',
            ], 422)->header('Cache-Control', 'no-store, max-age=0');
        }

        $upstream = Http::withHeaders([
            'Authorization' => $auth,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post($apiOrigin . '/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => $channelName,
        ]);

        if ($upstream->status() !== 200) {
            Log::warning('broadcasting.auth.proxy: upstream rejected', [
                'upstream_status' => $upstream->status(),
                'upstream_content_type' => $upstream->header('Content-Type'),
                'socket_id' => $socketId,
                'channel_name' => $channelName,
                'origin' => $request->header('Origin'),
                'token_prefix' => substr(trim((string) $auth), 0, 24),
                'upstream_body' => mb_substr((string) $upstream->body(), 0, 5000),
            ]);

            // If the upstream broadcasting auth is misconfigured (common with cross-origin Sanctum),
            // we can sign the private channel auth locally (Pusher protocol) after verifying access
            // via the chat REST API (which already supports Bearer tokens).
            if (in_array($upstream->status(), [401, 403], true)) {
                $local = $this->tryLocalSign($apiOrigin, $auth, $socketId, $channelName);
                if ($local) {
                    return $local->header('Cache-Control', 'no-store, max-age=0');
                }
            }
        }

        return response($upstream->body(), $upstream->status())->withHeaders([
            'Content-Type' => $upstream->header('Content-Type', 'application/json'),
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function tryLocalSign(string $apiOrigin, string $auth, string $socketId, string $channelName): Response|JsonResponse|null
    {
        if (!str_starts_with($channelName, 'private-chat.conversation.')) {
            return null;
        }

        $conversationId = (int) substr($channelName, strlen('private-chat.conversation.'));
        if ($conversationId <= 0) return null;

        $can = Http::withHeaders([
            'Authorization' => $auth,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get($apiOrigin . '/api/v3/chat/conversations/' . $conversationId);

        $ok = $can->status() === 200 && (bool) data_get($can->json(), 'success', false) === true;
        Log::info('broadcasting.auth.proxy: local-sign check', [
            'conversation_id' => $conversationId,
            'status' => $can->status(),
            'ok' => $ok,
        ]);

        if (!$ok) return null;

        $key = config('services.suganta.reverb_app_key');
        $secret = config('services.suganta.reverb_app_secret');
        if (!is_string($key) || trim($key) === '' || !is_string($secret) || trim($secret) === '') {
            Log::warning('broadcasting.auth.proxy: local-sign missing key/secret');
            return null;
        }

        $signature = hash_hmac('sha256', $socketId . ':' . $channelName, $secret);
        return response()->json([
            'auth' => $key . ':' . $signature,
        ]);
    }
}

