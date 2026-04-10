import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const API_ORIGIN = (import.meta.env.VITE_API_DOMAIN || import.meta.env.VITE_API_ORIGIN || 'https://www.suganta.in').replace(/\/$/, '');

let echoInstance = null;

export function isReverbConfigured() {
    return !!import.meta.env.VITE_REVERB_APP_KEY;
}

export function getEcho() {
    return echoInstance;
}

/**
 * @param {() => string | null} getAccessToken Fresh Sanctum token (e.g. from localStorage).
 */
export function connectEcho(getAccessToken) {
    if (!isReverbConfigured()) return null;
    if (echoInstance) return echoInstance;

    window.Pusher = Pusher;

    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'https';
    const forceTLS = scheme === 'https';
    const host = import.meta.env.VITE_REVERB_HOST || 'ws.suganta.in';
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? (forceTLS ? 443 : 80));
    // Pusher protocol already uses `/app/{key}` automatically.
    // If we set wsPath to `/app`, the final URL can become `/app/app/{key}` (404).
    // So we only apply wsPath when you explicitly set a custom non-default path.
    const wsPathRaw = String(import.meta.env.VITE_REVERB_WS_PATH || '').trim();
    const wsPath = wsPathRaw && wsPathRaw !== '/app' && wsPathRaw !== '/' ? wsPathRaw : undefined;

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        ...(wsPath ? { wsPath } : {}),
        forceTLS,
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        activityTimeout: 30_000,
        pongTimeout: 30_000,
        authorizer: channel => ({
            authorize: (socketId, callback) => {
                const token = getAccessToken?.();
                axios
                    .post(
                        // Use same-origin proxy to avoid CORS problems in dev/local.
                        '/broadcasting/auth',
                        {
                            socket_id: socketId,
                            channel_name: channel.name,
                        },
                        {
                            headers: {
                                Authorization: token ? `Bearer ${token}` : '',
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        },
                    )
                    .then(response => {
                        callback(false, response.data);
                    })
                    .catch(error => {
                        callback(true, error);
                    });
            },
        }),
    });

    return echoInstance;
}

export function disconnectEcho() {
    if (echoInstance) {
        echoInstance.disconnect();
        echoInstance = null;
    }
}

/**
 * Subscribe to private chat channel `chat.conversation.{id}` (§5 Chat API v3).
 * Event names use leading `.` so Laravel `broadcastAs()` names are not namespace-prefixed.
 */
export function subscribeToChatConversation(echo, conversationId, handlers = {}) {
    const channel = echo.private(`chat.conversation.${conversationId}`);

    if (handlers.onSubscribed) {
        channel.subscribed(() => handlers.onSubscribed());
    }
    if (handlers.onSubscriptionError) {
        channel.error(err => handlers.onSubscriptionError(err));
    }

    if (handlers.onMessageSent) {
        channel.listen('.chat.message.sent', e => handlers.onMessageSent(e));
    }
    if (handlers.onMessageRead) {
        channel.listen('.chat.message.read', e => handlers.onMessageRead(e));
    }
    if (handlers.onReadState) {
        channel.listen('.chat.conversation.read_state', e => handlers.onReadState(e));
    }
    if (handlers.onReaction) {
        channel.listen('.chat.message.reaction.updated', e => handlers.onReaction(e));
    }
    if (handlers.onTyping) {
        channel.listen('.chat.user.typing', e => handlers.onTyping(e));
    }

    return () => {
        echo.leave(`chat.conversation.${conversationId}`);
    };
}
