/* eslint-disable no-undef */
/**
 * Firebase Cloud Messaging — service worker (background).
 * Keep `firebaseConfig` identical to VITE_FIREBASE_* in `.env` (Firebase Console → Project settings).
 * Uses compat scripts so this file stays static in /public.
 */
importScripts('https://www.gstatic.com/firebasejs/11.10.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/11.10.0/firebase-messaging-compat.js');

let messaging = null;
let firebaseReady = false;

async function ensureFirebase() {
    if (firebaseReady) return true;
    try {
        const res = await fetch('/firebase/web-config', { cache: 'no-store' });
        const cfg = await res.json();
        if (!cfg?.apiKey || !cfg?.messagingSenderId || !cfg?.appId) return false;
        firebase.initializeApp(cfg);
        messaging = firebase.messaging();
        firebaseReady = true;
        return true;
    } catch {
        return false;
    }
}

self.addEventListener('install', event => {
    event.waitUntil(ensureFirebase());
});

const onBackground = async payload => {
    const ok = await ensureFirebase();
    if (!ok) return;
    const title = payload.notification?.title || 'SuGanta';
    const body = payload.notification?.body || '';
    const data = payload.data || {};
    return self.registration.showNotification(title, {
        body,
        data,
        icon: '/logo/Su250.png',
    });
};

// FCM background messages
self.addEventListener('push', event => {
    try {
        const payload = event?.data ? event.data.json() : null;
        if (payload) {
            event.waitUntil(onBackground(payload));
        }
    } catch {
        /* ignore */
    }
});

// Also register if messaging is ready
ensureFirebase().then(ok => {
    if (ok && messaging) {
        messaging.onBackgroundMessage(onBackground);
    }
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const data = event.notification.data || {};
    const kind = data.kind;
    let path = '/notifications';
    if (kind === 'chat_message' && data.conversation_id) {
        path = `/chat/${String(data.conversation_id)}`;
    } else if (kind === 'system_notification') {
        path = '/notifications';
    }
    const url = new URL(path, self.location.origin).href;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
            if (clientList.length && 'focus' in clientList[0]) {
                return clientList[0].focus();
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        }),
    );
});
