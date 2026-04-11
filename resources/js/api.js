import axios from 'axios';
import {
    AUTH_BEARER_TOKEN_KEY,
    AUTH_DEVICE_TOKEN_KEY,
    AUTH_REDIRECT_REASON_KEY,
} from '@/constants/authStorage';

axios.defaults.withCredentials = true;

const ALLOWED_API_ORIGIN = (import.meta.env.VITE_API_DOMAIN || 'https://api.suganta.com').replace(/\/$/, '');
const SANCTUM_URL         = (import.meta.env.VITE_SANCTUM_URL || ALLOWED_API_ORIGIN).replace(/\/$/, '');
const ALLOWED_ORIGIN      = new URL(ALLOWED_API_ORIGIN).origin;
const API_TIMEOUT_MS      = Number(import.meta.env.VITE_API_TIMEOUT_MS) > 0
    ? Number(import.meta.env.VITE_API_TIMEOUT_MS)
    : 15000;

const MUTATING = new Set(['post', 'put', 'patch', 'delete']);

// ─── Device fingerprint — computed once at module load, reused on every request
const _fingerprint = (() => {
    try {
        const nav    = typeof navigator !== 'undefined' ? navigator : {};
        const scr    = typeof window    !== 'undefined' ? window.screen : {};
        const raw    = [nav.userAgent || '', nav.language || '',
                        `${scr.width || 0}x${scr.height || 0}`,
                        Intl?.DateTimeFormat()?.resolvedOptions()?.timeZone || ''].join('|');
        let h = 0;
        for (let i = 0; i < raw.length; i++) h = ((h << 5) - h + raw.charCodeAt(i)) | 0;
        return Math.abs(h).toString(36);
    } catch { return 'unknown'; }
})();

// ─── CSRF bootstrap — one request per page load, cached promise ──────────────
let _csrf = null;
const bootstrapCsrf = () => {
    if (!_csrf) {
        _csrf = axios
            .get(`${SANCTUM_URL}/sanctum/csrf-cookie`, { withCredentials: true })
            .catch(err => { _csrf = null; return Promise.reject(err); });
    }
    return _csrf;
};

// ─── Helpers ─────────────────────────────────────────────────────────────────
const getStorage  = () => (typeof window !== 'undefined' ? window.localStorage : null);
const preserveOn403 = () => {
    if (typeof window === 'undefined') return false;
    const p = window.location.pathname || '';
    return p.startsWith('/otp-verify') || p.startsWith('/payment-required');
};

export const sanitizeString = (str) =>
    typeof str === 'string' ? str.replace(/[<>"'`]/g, '') : '';

/** Sanctum: XSRF-TOKEN must be readable on `document.cookie` (shared parent domain with API). */
const xsrfTokenFromCookie = () => {
    if (typeof document === 'undefined') return null;
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    if (!m) return null;
    try {
        return decodeURIComponent(m[1]);
    } catch {
        return m[1];
    }
};

// ─── Axios instance ───────────────────────────────────────────────────────────
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

// ─── Request interceptor ─────────────────────────────────────────────────────
api.interceptors.request.use(async config => {
    const method = (config.method || '').toLowerCase();

    // Auto-fetch CSRF cookie before any state-mutating request.
    if (MUTATING.has(method)) await bootstrapCsrf();

    // Origin allowlist — block requests to untrusted domains.
    const resolved = new URL(config.url || '', config.baseURL || ALLOWED_API_ORIGIN);
    if (resolved.origin !== ALLOWED_ORIGIN) {
        return Promise.reject(new Error('Blocked: request to untrusted origin'));
    }

    config.headers['X-Client-Fingerprint'] = _fingerprint;
    config.headers['X-Request-Timestamp']  = Date.now().toString();

    const deviceToken = getStorage()?.getItem(AUTH_DEVICE_TOKEN_KEY);
    if (deviceToken) config.headers['X-Device-Token'] = deviceToken;

    const xsrf = xsrfTokenFromCookie();
    if (xsrf) config.headers['X-XSRF-TOKEN'] = xsrf;

    const bearer = getStorage()?.getItem(AUTH_BEARER_TOKEN_KEY);
    if (bearer) config.headers.Authorization = `Bearer ${bearer}`;

    return config;
}, err => Promise.reject(err));

// ─── Response interceptor ────────────────────────────────────────────────────
api.interceptors.response.use(
    response => {
        if (response.headers?.['content-type']?.includes('text/html')) {
            return Promise.reject({ success: false, code: 0, message: 'Unexpected response format.', errors: null, data: null });
        }
        return response.data;
    },
    async error => {
        const res     = error?.response;
        const storage = getStorage();
        const config  = error?.config;

        // ── 419 CSRF expired: refresh token and auto-retry once ──────────────
        if (res?.status === 419 && config && !config._csrfRetried) {
            _csrf = null; // bust cached promise
            config._csrfRetried = true;
            try {
                await bootstrapCsrf();
                return api(config); // retry original request
            } catch {
                // CSRF refresh failed — fall through to error handling
            }
        }

        if (res?.status === 401) {
            storage?.setItem(AUTH_REDIRECT_REASON_KEY, 'Your session expired. Please sign in again.');
            document?.dispatchEvent(new CustomEvent('app:unauthorized'));
        }

        if (res?.status === 403 && !preserveOn403()) {
            storage?.setItem(AUTH_REDIRECT_REASON_KEY, sanitizeString(res?.data?.message || 'Access denied. Please sign in again.'));
            document?.dispatchEvent(new CustomEvent('app:unauthorized'));
        }

        if (res?.status === 429) {
            return Promise.reject({
                success: false, code: 429,
                message: sanitizeString(res?.data?.message || 'Too many requests. Please wait a moment and try again.'),
                errors: res?.data?.errors || null,
                data:   res?.data?.data   || null,
            });
        }

        return Promise.reject({
            success: false,
            code:    res?.status || 500,
            message: sanitizeString(res?.data?.message || error?.message || 'Request failed'),
            errors:  res?.data?.errors || null,
            data:    res?.data?.data   || null,
            responsePayload: res?.data && typeof res.data === 'object' ? res.data : null,
        });
    },
);

/** Pre-warm CSRF cookie. Safe to call multiple times — returns cached promise. */
export async function ensureCsrf() {
    await bootstrapCsrf();
}

export default api;
