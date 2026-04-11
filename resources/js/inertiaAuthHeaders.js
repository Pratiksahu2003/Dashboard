import { router } from '@inertiajs/vue3';
import { AUTH_BEARER_TOKEN_KEY } from '@/constants/authStorage';

/**
 * When the API issues a Sanctum personal access token (alongside cookies), attach it to Inertia visits
 * so the dashboard middleware can forward Authorization to GET /auth/user.
 */
export function registerInertiaAuthHeaders() {
    if (typeof window === 'undefined') return;

    router.on('before', event => {
        const token = localStorage.getItem(AUTH_BEARER_TOKEN_KEY);
        if (!token) return;

        const visit = event.detail?.visit ?? event.visit;
        if (!visit || typeof visit !== 'object') return;

        visit.headers = {
            ...visit.headers,
            Authorization: `Bearer ${token}`,
        };
    });
}
