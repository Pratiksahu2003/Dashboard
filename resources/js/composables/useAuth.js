import { router } from '@inertiajs/vue3';

const AUTH_TOKEN_KEY = 'auth_token';
const AUTH_USER_KEY = 'user';
const AUTH_SESSION_TS_KEY = 'auth_session_ts';
const PAYMENT_DETAILS_KEY = 'payment_details';
const AUTH_IDENTIFIER_KEY = 'auth_identifier';
const REGISTRATION_CHARGES_KEY = 'registration_charges_context';

/** Session flag: verify-email page without Bearer (uses `/auth/login/send-otp` + `/auth/login/verify`). */
export const EMAIL_VERIFY_LOGIN_FLOW_KEY = 'email_verify_login_flow';

const MAX_SESSION_AGE_MS = 7 * 24 * 60 * 60 * 1000;

/** @param {Record<string, unknown>|null|undefined} user */
export const isEmailVerified = user => {
    if (!user || typeof user !== 'object') return false;
    const v = user.email_verified_at;
    if (v == null || v === '' || v === 'null') return false;
    if (typeof v === 'boolean') return v;
    return true;
};

/**
 * Backend uses boolean on login, strings like "pending" on register/verify.
 * @param {Record<string, unknown>|null|undefined} user
 */
export const isRegistrationFeeSatisfied = user => {
    if (!user || typeof user !== 'object') return true;
    if (user.payment_required === false) return true;
    const s = user.registration_fee_status;
    if (s === true) return true;
    if (s === false) return false;
    if (typeof s === 'string') {
        const lower = s.toLowerCase();
        if (lower === 'pending' || lower === 'unpaid') return false;
        if (lower === 'paid' || lower === 'completed') return true;
    }
    return true;
};

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
        localStorage.removeItem(REGISTRATION_CHARGES_KEY);
        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.removeItem(EMAIL_VERIFY_LOGIN_FLOW_KEY);
        }
        sessionStorage.clear();
    };

    const setRegistrationChargesContext = charges => {
        if (charges && typeof charges === 'object') {
            localStorage.setItem(REGISTRATION_CHARGES_KEY, JSON.stringify(charges));
        }
    };

    const getRegistrationChargesContext = () => {
        try {
            return JSON.parse(localStorage.getItem(REGISTRATION_CHARGES_KEY) || 'null');
        } catch {
            return null;
        }
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
        setRegistrationChargesContext,
        getRegistrationChargesContext,
    };
};
