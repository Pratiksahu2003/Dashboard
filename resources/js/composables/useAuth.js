import { getActivePinia } from 'pinia';
import { router, usePage } from '@inertiajs/vue3';
import { useAuthStore } from '@/stores/auth';
import {
    AUTH_DEVICE_TOKEN_KEY,
    AUTH_IDENTIFIER_KEY,
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

let redirectLock = false;

const normalizePath = (value) => {
    const s = String(value || '');
    const path = s.split('?')[0].split('#')[0];
    if (!path) return '/';
    return path.endsWith('/') && path.length > 1 ? path.slice(0, -1) : path;
};

const getPathFromUrl = (url) => {
    if (typeof window === 'undefined') return normalizePath(url);
    try {
        return normalizePath(new URL(url, window.location.origin).pathname);
    } catch {
        return normalizePath(url);
    }
};

const safeVisitNamedRoute = (name, options = {}) => {
    if (typeof window === 'undefined') return false;
    if (redirectLock || router.processing) return false;
    let url;
    try {
        url = route(name);
    } catch {
        return false;
    }
    const targetPath = getPathFromUrl(url);
    const currentPath = normalizePath(window.location.pathname);
    if (targetPath === currentPath) return false;
    redirectLock = true;
    router.visit(url, {
        replace: true,
        preserveState: false,
        preserveScroll: false,
        onFinish: () => {
            redirectLock = false;
        },
        ...options,
    });
    window.setTimeout(() => {
        redirectLock = false;
    }, 1500);
    return true;
};

/** @param {Record<string, unknown>|null|undefined} user */
export const isEmailVerified = user => {
    // Email verification is disabled
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

export const useAuth = () => {
    /** Returns the current authenticated user from Inertia shared props. */
    const getUser = () => usePage().props.auth.user;

    /** True when a session-authenticated user exists in Inertia shared props. */
    const isAuthenticated = () => !!usePage().props.auth.user;

    /** No longer needed — returns null unconditionally. */
    const getToken = () => null;

    /**
     * User + verified email + registration fee satisfied (per AuthApi.md login rules).
     */
    const canAccessDashboard = () => {
        const u = usePage().props.auth.user;
        return !!u && isEmailVerified(u) && isRegistrationFeeSatisfied(u);
    };

    const clearSession = () => {
        // Purge legacy credential keys from existing browser sessions
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        localStorage.removeItem('auth_session_ts');
        // Clear non-credential UI state keys
        localStorage.removeItem(PAYMENT_DETAILS_KEY);
        localStorage.removeItem(AUTH_IDENTIFIER_KEY);
        localStorage.removeItem(REGISTRATION_CHARGES_KEY);
        localStorage.removeItem(POST_VERIFY_LOGIN_NOTICE_KEY);
        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.removeItem(EMAIL_VERIFY_LOGIN_FLOW_KEY);
            sessionStorage.removeItem('post_verify_notice');
        }
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
        return enforceBestRoute();
    };

    const getDeviceToken = () => localStorage.getItem(AUTH_DEVICE_TOKEN_KEY);

    /**
     * Centralized logic to determine where a user should be based on auth state.
     * Returns a route name or null if no redirect needed.
     */
    const getBestAuthRoute = () => {
        const user = usePage().props.auth.user;

        if (!user) return 'login';
        if (!isEmailVerified(user)) return 'auth.otp.verify';
        if (!isRegistrationFeeSatisfied(user)) return 'auth.payment.required';
        return 'dashboard';
    };

    /**
     * Redirects to the best auth route if the user is not where they should be.
     * Used as a global guard in Layouts.
     */
    const enforceBestRoute = () => {
        if (redirectLock || router.processing) return false;

        const best = getBestAuthRoute();
        let current = null;
        try {
            current = typeof route !== 'undefined' ? route().current() : null;
        } catch {
            current = null;
        }
        const page = usePage();
        const component = page?.component || '';

        // If getBestAuthRoute returns null, it means we are in a transition state
        if (best === null) return true;

        // If already on the best route (or a child of it), do nothing
        if (current === best) return true;

        // If best is dashboard, we allow sub-routes
        const isOnAuthPage = ['login', 'register', 'password.request', 'password.reset', 'auth.otp.verify', 'auth.payment.required'].includes(current) || component.startsWith('Auth/');

        if (best === 'dashboard') {
            if (isOnAuthPage) {
                safeVisitNamedRoute('dashboard');
                return false;
            }
            return true;
        }

        // If best is login, we redirect protected pages to login
        if (best === 'login') {
            const isProtected = !isOnAuthPage && !['Home'].includes(component);
            if (isProtected) {
                clearSession();
                safeVisitNamedRoute('login');
                return false;
            }
            return true;
        }

        // If best is an auth gate (OTP or Payment)
        if (['auth.otp.verify', 'auth.payment.required'].includes(best)) {
            if (current !== best) {
                if (best === 'auth.payment.required') {
                    const u = usePage().props.auth.user;
                    if (u) ensureRegistrationPaymentDetails(u, getRegistrationChargesContext);
                }
                safeVisitNamedRoute(best);
                return false;
            }
            return true;
        }

        return true;
    };

    return {
        getToken,
        getUser,
        getDeviceToken,
        isAuthenticated,
        canAccessDashboard,
        clearSession,
        requireAuth,
        setRegistrationChargesContext,
        getRegistrationChargesContext,
        getBestAuthRoute,
        enforceBestRoute,
    };
};
