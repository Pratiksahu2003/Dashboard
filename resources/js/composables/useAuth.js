import { getActivePinia } from 'pinia';
import { router } from '@inertiajs/vue3';
import { useAuthStore } from '@/stores/auth';
import {
    AUTH_DEVICE_TOKEN_KEY,
    AUTH_IDENTIFIER_KEY,
    AUTH_SESSION_TS_KEY,
    AUTH_TOKEN_KEY,
    AUTH_USER_KEY,
    EMAIL_VERIFY_LOGIN_FLOW_KEY,
    PAYMENT_DETAILS_KEY,
    POST_VERIFY_LOGIN_NOTICE_KEY,
    REGISTRATION_CHARGES_KEY,
} from '@/constants/authStorage';

/** Re-export for pages that import keys from composable (stable public API). */
export {
    AUTH_DEVICE_TOKEN_KEY,
    EMAIL_VERIFY_LOGIN_FLOW_KEY,
    POST_VERIFY_LOGIN_NOTICE_KEY,
} from '@/constants/authStorage';

/** Shown on login after successful `/auth/verification/verify` (API revokes the old token — user must sign in again). */
export const POST_EMAIL_VERIFY_RELOGIN_MESSAGE =
    'Email verified successfully. For your security, your previous session was ended. Please sign in again with your email and password.';

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
        if (lower === 'paid' || lower === 'completed') return true;
        if (lower === 'pending' || lower === 'unpaid') return false;
    }
    /** API may send `registration_fee_status: null` with `payment_required: true` (e.g. after email verify). */
    if (user.payment_required === true) return false;
    return true;
};

/**
 * Ensures `payment_details` exists for RegistrationFee when fee is unpaid (uses register-time `registration_charges_context`).
 * @param {Record<string, unknown>} user
 * @param {() => unknown} getCharges
 */
export const ensureRegistrationPaymentDetails = (user, getCharges) => {
    if (!user || typeof user !== 'object') return;
    if (isRegistrationFeeSatisfied(user)) return;
    if (user.payment_required === false) return;
    try {
        const raw = localStorage.getItem(PAYMENT_DETAILS_KEY);
        const p = raw ? JSON.parse(raw) : {};
        const info = p?.errors && typeof p.errors === 'object' ? p.errors : p;
        if (info?.requires_registration_payment) return;
    } catch {
        /* ignore */
    }
    const charges = getCharges?.();
    if (!charges || typeof charges !== 'object') return;
    localStorage.setItem(
        PAYMENT_DETAILS_KEY,
        JSON.stringify({
            success: false,
            message: 'Registration fee payment is required to complete login.',
            errors: {
                requires_registration_payment: true,
                actual_price: charges.actual_price,
                discounted_price: charges.discounted_price,
                description: charges.description,
                currency: charges.currency,
                role: user.role,
            },
            code: 200,
        }),
    );
};

const syncPiniaFromStorage = () => {
    try {
        if (getActivePinia()) {
            useAuthStore().syncFromStorage();
        }
    } catch {
        /* Pinia not ready (e.g. tests) */
    }
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

    /** True when a Sanctum/API token exists (may still need email verification or payment). */
    const isAuthenticated = () => !!getToken();

    /**
     * Token + user + verified email + registration fee satisfied (per AuthApi.md login rules).
     */
    const canAccessDashboard = () => {
        const u = getUser();
        return !!getToken() && !!u && isEmailVerified(u) && isRegistrationFeeSatisfied(u);
    };

    const setSession = ({ token, user, deviceToken } = {}) => {
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
        if (deviceToken && typeof deviceToken === 'string' && deviceToken.length > 0) {
            localStorage.setItem(AUTH_DEVICE_TOKEN_KEY, deviceToken);
        }
        syncPiniaFromStorage();
    };

    const clearSession = () => {
        localStorage.removeItem(AUTH_TOKEN_KEY);
        localStorage.removeItem(AUTH_USER_KEY);
        localStorage.removeItem(AUTH_SESSION_TS_KEY);
        localStorage.removeItem(AUTH_DEVICE_TOKEN_KEY);
        localStorage.removeItem(PAYMENT_DETAILS_KEY);
        localStorage.removeItem(AUTH_IDENTIFIER_KEY);
        localStorage.removeItem(REGISTRATION_CHARGES_KEY);
        localStorage.removeItem(POST_VERIFY_LOGIN_NOTICE_KEY);
        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.removeItem(EMAIL_VERIFY_LOGIN_FLOW_KEY);
            sessionStorage.removeItem('post_verify_notice');
        }
        syncPiniaFromStorage();
        try {
            if (getActivePinia()) {
                useAuthStore().clearTransient();
            }
        } catch {
            /* ignore */
        }
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
        if (!getToken()) {
            clearSession();
            router.replace(route('login'));
            return false;
        }
        const u = getUser();
        if (!u) {
            clearSession();
            router.replace(route('login'));
            return false;
        }
        if (!isEmailVerified(u)) {
            router.replace(route('auth.verify.email'));
            return false;
        }
        if (!isRegistrationFeeSatisfied(u)) {
            ensureRegistrationPaymentDetails(u, getRegistrationChargesContext);
            router.replace(route('auth.payment.required'));
            return false;
        }
        return true;
    };

    const getDeviceToken = () => localStorage.getItem(AUTH_DEVICE_TOKEN_KEY);

    return {
        getToken,
        getUser,
        getDeviceToken,
        isAuthenticated,
        canAccessDashboard,
        setSession,
        clearSession,
        requireAuth,
        setRegistrationChargesContext,
        getRegistrationChargesContext,
    };
};
