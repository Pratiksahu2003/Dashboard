import axios from 'axios';
import {
    AUTH_DEVICE_TOKEN_KEY,
    AUTH_REDIRECT_REASON_KEY,
    AUTH_SESSION_TS_KEY,
    AUTH_TOKEN_KEY,
    AUTH_USER_KEY,
} from '@/constants/authStorage';
const ALLOWED_API_ORIGIN = 'https://www.suganta.in';
const API_TIMEOUT_MS = Number(import.meta.env.VITE_API_TIMEOUT_MS) > 0
    ? Number(import.meta.env.VITE_API_TIMEOUT_MS)
    : 20000;

const getStorage = () => {
    if (typeof window === 'undefined') return null;
    return window.localStorage;
};

/** Do not clear stored token on 403 while user is on verify / OTP / payment screens (prevents dashboard ↔ verify loops). */
const shouldPreserveAuthOn403 = () => {
    if (typeof window === 'undefined') return false;
    const path = window.location.pathname || '';
    return (
        path.startsWith('/otp-verify') ||
        path.startsWith('/payment-required')
    );
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
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.interceptors.request.use(config => {
    const storage = getStorage();
    const token = storage?.getItem(AUTH_TOKEN_KEY);

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    const deviceToken = storage?.getItem(AUTH_DEVICE_TOKEN_KEY);
    if (deviceToken) {
        config.headers['X-Device-Token'] = deviceToken;
    }

    config.headers['X-Client-Fingerprint'] = getDeviceFingerprint();
    config.headers['X-Request-Timestamp'] = Date.now().toString();

    const resolved = new URL(config.url || '', config.baseURL || ALLOWED_API_ORIGIN);
    if (resolved.origin !== ALLOWED_API_ORIGIN) {
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

        if (response?.status === 401) {
            const storage = getStorage();
            const tokenBefore = storage?.getItem(AUTH_TOKEN_KEY);
            
            // Only clear and redirect if we actually had a token that is now invalid
            if (tokenBefore) {
                // Potential place for automatic token refresh if we want to do it inside interceptor
                // For now, we follow the stateless principle: clear and redirect.
                storage?.removeItem(AUTH_TOKEN_KEY);
                storage?.removeItem(AUTH_USER_KEY);
                storage?.removeItem(AUTH_SESSION_TS_KEY);
                storage?.removeItem(AUTH_DEVICE_TOKEN_KEY);
                storage?.setItem(AUTH_REDIRECT_REASON_KEY, 'Your session expired. Please sign in again.');
                
                if (typeof document !== 'undefined') {
                    document.dispatchEvent(new CustomEvent('app:unauthorized'));
                }
            }
        }

        if (response?.status === 403) {
            if (!shouldPreserveAuthOn403()) {
                const storage = getStorage();
                const tokenBefore = storage?.getItem(AUTH_TOKEN_KEY);
                
                if (tokenBefore) {
                    storage?.removeItem(AUTH_TOKEN_KEY);
                    storage?.removeItem(AUTH_USER_KEY);
                    storage?.removeItem(AUTH_SESSION_TS_KEY);
                    storage?.removeItem(AUTH_DEVICE_TOKEN_KEY);
                    const msg = sanitizeString(
                        response?.data?.message || 'Access denied. Please sign in again.',
                    );
                    storage?.setItem(AUTH_REDIRECT_REASON_KEY, msg);
                    
                    if (typeof document !== 'undefined') {
                        document.dispatchEvent(new CustomEvent('app:unauthorized'));
                    }
                }
            }
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
            /** Full JSON body (some endpoints put checkout_url at root, not under data). */
            responsePayload: response?.data && typeof response.data === 'object' ? response.data : null,
        };

        return Promise.reject(normalized);
    },
);

export { sanitizeString };
export default api;
