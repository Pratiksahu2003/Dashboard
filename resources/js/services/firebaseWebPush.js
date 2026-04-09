import { initializeApp, getApps } from 'firebase/app';
import { deleteToken, getMessaging, getToken, isSupported, onMessage } from 'firebase/messaging';
import { router } from '@inertiajs/vue3';
import { registerPushToken, unregisterPushToken } from '@/services/pushTokenApi';

let messagingInstance = null;
let lastRegisteredToken = null;
let swRegistration = null;
let focusHandler = null;
let gestureHandler = null;

function firebaseConfigFromEnv() {
    const apiKey = import.meta.env.VITE_FIREBASE_API_KEY;
    if (!apiKey) return null;
    const projectId = import.meta.env.VITE_FIREBASE_PROJECT_ID || 'suganta-tutors';
    return {
        apiKey,
        authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN || `${projectId}.firebaseapp.com`,
        projectId,
        storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET || `${projectId}.appspot.com`,
        messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
        appId: import.meta.env.VITE_FIREBASE_APP_ID,
    };
}

function pathFromPushData(data) {
    const kind = data?.kind;
    if (kind === 'chat_message' && data.conversation_id) {
        return `/chat/${encodeURIComponent(String(data.conversation_id))}`;
    }
    return '/notifications';
}

function emitForegroundPush(payload) {
    try {
        if (typeof window === 'undefined') return;
        window.dispatchEvent(new CustomEvent('app:push-message', { detail: payload }));
    } catch {
        /* ignore */
    }
}

async function syncTokenToBackend(messaging, registration, vapidKey) {
    const token = await getToken(messaging, {
        vapidKey,
        serviceWorkerRegistration: registration,
    });
    if (!token) return;
    if (token === lastRegisteredToken) return;
    await registerPushToken({
        token,
        platform: 'web',
        device_name: typeof navigator !== 'undefined' ? navigator.userAgent?.slice(0, 120) || 'Web' : 'Web',
    });
    lastRegisteredToken = token;
}

function bindPermissionRequestOnUserGesture(run) {
    if (typeof window === 'undefined') return;
    if (gestureHandler) return;

    const trigger = async () => {
        unbindPermissionRequestOnUserGesture();
        await run().catch(() => {});
    };

    gestureHandler = trigger;
    window.addEventListener('click', trigger, { once: true, passive: true });
    window.addEventListener('touchstart', trigger, { once: true, passive: true });
    window.addEventListener('keydown', trigger, { once: true });
}

function unbindPermissionRequestOnUserGesture() {
    if (typeof window === 'undefined' || !gestureHandler) return;
    window.removeEventListener('click', gestureHandler);
    window.removeEventListener('touchstart', gestureHandler);
    window.removeEventListener('keydown', gestureHandler);
    gestureHandler = null;
}

/**
 * Initialize Firebase Messaging, register SW, sync FCM token to API v1, foreground + click routing.
 * No-ops when VITE_FIREBASE_* is missing or Messaging unsupported.
 */
export async function initWebPush() {
    const cfg = firebaseConfigFromEnv();
    if (!cfg) return;

    const ok = await isSupported().catch(() => false);
    if (!ok || typeof window === 'undefined' || !('Notification' in window)) return;

    const app = getApps().length ? getApps()[0] : initializeApp(cfg);
    messagingInstance = getMessaging(app);

    try {
        swRegistration = await navigator.serviceWorker.register('/firebase-messaging-sw.js', {
            scope: '/',
        });
    } catch {
        return;
    }

    const vapidKey = import.meta.env.VITE_FIREBASE_VAPID_KEY;
    if (!vapidKey) return;

    const requestAndSync = async () => {
        let perm = Notification.permission;
        if (perm === 'default') {
            perm = await Notification.requestPermission();
        }
        if (perm !== 'granted') return;
        await syncTokenToBackend(messagingInstance, swRegistration, vapidKey).catch(() => {});
    };

    if (Notification.permission === 'granted') {
        await requestAndSync();
    } else if (Notification.permission === 'default') {
        bindPermissionRequestOnUserGesture(requestAndSync);
    } else {
        return;
    }

    onMessage(messagingInstance, payload => {
        const data = payload.data || {};
        const title = payload.notification?.title || 'SuGanta';
        const body = payload.notification?.body || '';
        emitForegroundPush({ title, body, data });
        try {
            const n = new Notification(title, {
                body,
                data,
                icon: '/logo/Su250.png',
            });
            n.onclick = () => {
                window.focus();
                n.close();
                router.visit(pathFromPushData(data));
            };
        } catch {
            /* ignore */
        }
    });

    focusHandler = () => {
        syncTokenToBackend(messagingInstance, swRegistration, vapidKey).catch(() => {});
    };
    window.addEventListener('focus', focusHandler);
}

export async function teardownWebPush() {
    unbindPermissionRequestOnUserGesture();
    if (typeof window !== 'undefined' && focusHandler) {
        window.removeEventListener('focus', focusHandler);
        focusHandler = null;
    }
    if (!messagingInstance) return;
    try {
        await deleteToken(messagingInstance);
    } catch {
        /* ignore */
    }
    if (lastRegisteredToken) {
        await unregisterPushToken({ token: lastRegisteredToken }).catch(() => {});
        lastRegisteredToken = null;
    }
    messagingInstance = null;
    swRegistration = null;
}
