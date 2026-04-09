import { defineStore } from 'pinia';
import {
    AUTH_TOKEN_KEY,
    AUTH_USER_KEY,
} from '@/constants/authStorage';

/**
 * Mirrors API session state for reactive UI (source of truth remains localStorage via useAuth).
 * Call syncFromStorage() after setSession/clearSession, or rely on useAuth() which syncs Pinia.
 *
 * This app uses Laravel Inertia for routing (not vue-router). "Guards" live in AppLayout + requireAuth().
 */
export const useAuthStore = defineStore('auth', {
    state: () => ({
        /** Last known token (synced from storage). */
        token: null,
        /** Last known user object (synced from storage). */
        user: null,
        /** Set when POST /auth/login returns requires_otp (transient UI hint). */
        requiresOtp: false,
        /** Payload when API returns success=false + errors.requires_registration_payment (optional copy). */
        lastPaymentGate: null,
    }),

    getters: {
        isAuthenticated: state => !!state.token,
        /** Per AuthApi: email_verified_at present. */
        emailVerified(state) {
            const u = state.user;
            if (!u || typeof u !== 'object') return false;
            const v = u.email_verified_at;
            if (v == null || v === '' || v === 'null') return false;
            if (typeof v === 'boolean') return v;
            return true;
        },
        /** Per verify/register payloads: payment_required === true (non-student roles). */
        paymentRequiredFlag(state) {
            const u = state.user;
            return !!(u && typeof u === 'object' && u.payment_required === true);
        },
    },

    actions: {
        syncFromStorage() {
            if (typeof localStorage === 'undefined') return;
            this.token = localStorage.getItem(AUTH_TOKEN_KEY);
            try {
                this.user = JSON.parse(localStorage.getItem(AUTH_USER_KEY) || 'null');
            } catch {
                this.user = null;
            }
        },

        setRequiresOtp(value) {
            this.requiresOtp = !!value;
        },

        setLastPaymentGate(payload) {
            this.lastPaymentGate = payload && typeof payload === 'object' ? { ...payload } : null;
        },

        clearTransient() {
            this.requiresOtp = false;
            this.lastPaymentGate = null;
        },

        reset() {
            this.token = null;
            this.user = null;
            this.clearTransient();
        },
    },
});
