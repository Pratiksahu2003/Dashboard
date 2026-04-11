/**
 * Browser storage keys for auth (localStorage unless noted).
 * Sanctum SPA: `withCredentials` + `X-XSRF-TOKEN` (api.js). When the API returns `data.token` (e.g. auth_mode "both"),
 * we also persist it so axios + Inertia can send `Authorization: Bearer` for `/auth/user` on the dashboard.
 */
export const AUTH_BEARER_TOKEN_KEY = 'auth_token';
export const AUTH_DEVICE_TOKEN_KEY = 'auth_device_token';
export const PAYMENT_DETAILS_KEY = 'payment_details';
export const AUTH_IDENTIFIER_KEY = 'auth_identifier';
export const REGISTRATION_CHARGES_KEY = 'registration_charges_context';
/** sessionStorage: login → verify-email without Bearer (OTP-only path). */
export const EMAIL_VERIFY_LOGIN_FLOW_KEY = 'email_verify_login_flow';
export const AUTH_REDIRECT_REASON_KEY = 'auth_redirect_reason';
/** localStorage: message to show on Login after email verification (survives clearSession ordering). */
export const POST_VERIFY_LOGIN_NOTICE_KEY = 'post_verify_login_notice';
