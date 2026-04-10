import axios from 'axios';
import {
    AUTH_DEVICE_TOKEN_KEY,
    AUTH_REDIRECT_REASON_KEY,
} from '@/constants/authStorage';

// Ensure all axios requests send credentials cross-origin.
axios.defaults.withCredentials = true;

const ALLOWED_API_ORIGIN = (import.meta.env.VITE_API_DOMAIN || 'https://api.suganta.com').replace(/\/$/, '');
const SANCTUM_URL = (import.meta.env.VITE_SANCTUM_URL || ALLOWED_API_ORIGIN).replace(/\/$/, '');
const ALLOWED_ORIGIN_PARSED = new URL(ALLOWED_API_ORIGIN).origin;
const API_TIMEOUT_MS = Number(import.meta.env.VITE_API_TIMEOUT_MS) > 0
    ? Number(import.meta.env.VITE_API_TIMEOUT_MS)
    : 20000;

const STATE_MUTATING_METHODS = new Set(['post', 'put', 'patch', 'delete']);

/**
 * CSRF bootstrap — fetches /sanctum/csrf-cookie once per page load.
 * Subsequent calls return the cached promise so only one request is ever made.
 * On failure the cache is cleared so the next request retries.
 */
let csrfPromise = null;
const bootstrapCsrf = () => {
    if (!csrfPromise) {
        csrfPromise = axios
            .get(`${SANCTUM_URL}/sanctum/csrf-cookie`, { withCredentials: true })
            .catch(err => {
                csrfPromise = null;
                return Promise.reject(err);
            });
    }
    return csrfPromise;
};

const getStorage = () => {
    if (typeof window === 'undefined') return null;
    return window.localStorage;
};

const shouldPreserveAuthOn403 = () => {
    if (typeof window === 'undefined') return false;
    const path = window.location.pathname || '';
    return path.startsWith('/otp-verify') || path.startsWith('/payment-required');
};

const sanitizeString = (str) => {
    if (typeof str !== 'string') return '';
    return str.replace(/[<>"'`]/g, '');
};

const getDeviceFingerprint = () => {
    const nav = typeof navigator !== 'undefined' ? navigator : {};
    const screen = typeof window !== 'undefined' ? window.screen : {};
    const raw = [
        nav.userAgent || '',
        nav.language || '',
        `${screen.width || 0}x${screen.height || 0}`,
        Intl?.DateTimeFormat()?.resolvedOptions()?.timeZone || '',
    ].join('|');
    let hash = 0;
    for (let i = 0; i < raw.length; i++) {
        hash = ((hash << 5) - hash + raw.charCodeAt(i)) | 0;
    }
    return Math.abs(hash).toString(36);
};

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || `${ALLOWED_API_ORIGIN}/api/v1`,
    timeout: API_TIMEOUT_MS,
    withCredentials: true,
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.interceptors.request.use(async config => {
    // Automatically fetch the CSRF cookie before any state-mutating request.
    // This is a no-op after the first successful fetch (cached promise).
    if (STATE_MUTATING_METHODS.has((config.method || '').toLowerCase())) {
        await bootstrapCsrf();
    }

    const storage = getStorage();
    const deviceToken = storage?.getItem(AUTH_DEVICE_TOKEN_KEY);
    if (deviceToken) {
        config.headers['X-Device-Token'] = deviceToken;
    }

    config.headers['X-Client-Fingerprint'] = getDeviceFingerprint();
    config.headers['X-Request-Timestamp'] = Date.now().toString();

    const resolved = new URL(config.url || '', config.baseURL || ALLOWED_API_ORIGIN);
    if (resolved.origin !== ALLOWED_ORIGIN_PARSED) {
        return Promise.reject(new Error('Blocked: request to untrusted origin'));
    }

    return config;
}, error => Promise.reject(error));

api.interceptors.response.use(
    response => {
        if (response.headers?.['content-type']?.includes('text/html')) {
            return Promise.reject({
                success: false,
                code: 0,
                message: 'Unexpected response format.',
                errors: null,
                data: null,
            });
        }
        return response.data;
    },
    error => {
        const response = error?.response;
        const storage = getStorage();

        if (response?.status === 401) {
            storage?.setItem(AUTH_REDIRECT_REASON_KEY, 'Your session expired. Please sign in again.');
            if (typeof document !== 'undefined') {
                document.dispatchEvent(new CustomEvent('app:unauthorized'));
            }
        }

        if (response?.status === 403) {
            if (!shouldPreserveAuthOn403()) {
                const msg = sanitizeString(
                    response?.data?.message || 'Access denied. Please sign in again.',
                );
                storage?.setItem(AUTH_REDIRECT_REASON_KEY, msg);
                if (typeof document !== 'undefined') {
                    document.dispatchEvent(new CustomEvent('app:unauthorized'));
                }
            }
        }

        if (response?.status === 419) {
            // CSRF token expired — clear the cache so the next request re-fetches it.
            csrfPromise = null;
        }

        if (response?.status === 429) {
            return Promise.reject({
                success: false,
                code: 429,
                message: sanitizeString(response?.data?.message || 'Too many requests. Please wait a moment and try again.'),
                errors: response?.data?.errors || null,
                data: response?.data?.data || null,
            });
        }

        const normalized = {
            success: false,
            code: response?.status || 500,
            message: sanitizeString(response?.data?.message || error?.message || 'Request failed'),
            errors: response?.data?.errors || null,
            data: response?.data?.data || null,
            responsePayload: response?.data && typeof response.data === 'object' ? response.data : null,
        };

        return Promise.reject(normalized);
    },
);

/**
 * Manually pre-warm the CSRF cookie (e.g. on page mount before a form).
 * Safe to call multiple times — returns the cached promise.
 */
export async function ensureCsrf() {
    await bootstrapCsrf();
}

export { sanitizeString };
export default api;
