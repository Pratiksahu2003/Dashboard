import { router } from '@inertiajs/vue3';

const AUTH_TOKEN_KEY = 'auth_token';
const AUTH_USER_KEY = 'user';
const AUTH_SESSION_TS_KEY = 'auth_session_ts';
const PAYMENT_DETAILS_KEY = 'payment_details';
const AUTH_IDENTIFIER_KEY = 'auth_identifier';

const MAX_SESSION_AGE_MS = 7 * 24 * 60 * 60 * 1000;

const isSessionExpired = () => {
    const ts = localStorage.getItem(AUTH_SESSION_TS_KEY);
    if (!ts) return true;
    return Date.now() - Number(ts) > MAX_SESSION_AGE_MS;
};

export const useAuth = () => {
    const getToken = () => {
        const token = localStorage.getItem(AUTH_TOKEN_KEY);
        if (!token) return null;
        if (isSessionExpired()) {
            clearSession();
            return null;
        }
        return token;
    };

    const getUser = () => {
        try {
            if (!getToken()) return null;
            return JSON.parse(localStorage.getItem(AUTH_USER_KEY) || 'null');
        } catch {
            localStorage.removeItem(AUTH_USER_KEY);
            return null;
        }
    };

    const isAuthenticated = () => !!getToken();

    const setSession = ({ token, user }) => {
        if (token && typeof token === 'string' && token.length > 10) {
            localStorage.setItem(AUTH_TOKEN_KEY, token);
            localStorage.setItem(AUTH_SESSION_TS_KEY, Date.now().toString());
        }
        if (user && typeof user === 'object') {
            const safe = { ...user };
            delete safe.password;
            delete safe.password_hash;
            delete safe.remember_token;
            localStorage.setItem(AUTH_USER_KEY, JSON.stringify(safe));
        }
    };

    const clearSession = () => {
        localStorage.removeItem(AUTH_TOKEN_KEY);
        localStorage.removeItem(AUTH_USER_KEY);
        localStorage.removeItem(AUTH_SESSION_TS_KEY);
        localStorage.removeItem(PAYMENT_DETAILS_KEY);
        localStorage.removeItem(AUTH_IDENTIFIER_KEY);
        sessionStorage.clear();
    };

    const requireAuth = () => {
        if (!isAuthenticated()) {
            clearSession();
            router.visit(route('login'));
            return false;
        }
        return true;
    };

    return {
        getToken,
        getUser,
        isAuthenticated,
        setSession,
        clearSession,
        requireAuth,
    };
};
