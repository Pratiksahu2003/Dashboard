import { defineStore } from 'pinia';
import { usePage } from '@inertiajs/vue3';

/**
 * Auth store — transient UI state only.
 * Authentication identity is derived from Inertia shared props (auth.user).
 * No token or user object is stored here; the server is the source of truth.
 */
export const useAuthStore = defineStore('auth', {
    state: () => ({
        /** Set when POST /auth/login returns requires_otp (transient UI hint). */
        requiresOtp: false,
        /** Payload when API returns success=false + errors.requires_registration_payment (optional copy). */
        lastPaymentGate: null,
    }),

    getters: {
        isAuthenticated: () => usePage().props?.auth?.user != null,
    },

    actions: {
        /** No-op — kept for call-site compatibility during migration. */
        syncFromStorage() {},

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
            this.clearTransient();
        },
    },
});
