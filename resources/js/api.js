import axios from 'axios';

const AUTH_TOKEN_KEY = 'auth_token';
const AUTH_REDIRECT_REASON_KEY = 'auth_redirect_reason';
const ALLOWED_API_ORIGIN = 'https://www.suganta.in';
const API_TIMEOUT_MS = Number(import.meta.env.VITE_API_TIMEOUT_MS) > 0
    ? Number(import.meta.env.VITE_API_TIMEOUT_MS)
    : 20000;

const getStorage = () => {
    if (typeof window === 'undefined') return null;
    return window.localStorage;
};

const redirectToLoginIfNeeded = () => {
    if (typeof window === 'undefined') return;
    const path = window.location.pathname || '';
    const isAuthPage = path.startsWith('/login') || path.startsWith('/register') || path.startsWith('/password');
    if (!isAuthPage) {
        window.location.assign('/login');
    }
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
            storage?.removeItem(AUTH_TOKEN_KEY);
            storage?.removeItem('user');
            storage?.setItem(AUTH_REDIRECT_REASON_KEY, 'Your session expired. Please sign in again.');
            redirectToLoginIfNeeded();
        }

        if (response?.status === 403) {
            const storage = getStorage();
            storage?.removeItem(AUTH_TOKEN_KEY);
            storage?.removeItem('user');
            storage?.setItem(AUTH_REDIRECT_REASON_KEY, 'Access denied. Please sign in again.');
            redirectToLoginIfNeeded();
        }

        if (response?.status === 429) {
            return Promise.reject({
                success: false,
                code: 429,
                message: 'Too many requests. Please wait a moment and try again.',
                errors: null,
                data: null,
            });
        }

        const normalized = {
            success: false,
            code: response?.status || 500,
            message: sanitizeString(response?.data?.message || error?.message || 'Request failed'),
            errors: response?.data?.errors || null,
            data: response?.data?.data || null,
        };

        return Promise.reject(normalized);
    },
);

export { sanitizeString };
export default api;
