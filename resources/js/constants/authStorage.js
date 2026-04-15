/**
 * Browser storage keys for auth (localStorage unless noted).
 * Auth is Sanctum SPA only: session cookies from the API origin + `/sanctum/csrf-cookie` + `X-XSRF-TOKEN` (see api.js).
 * Do not store API personal access tokens; identity on the dashboard comes from cookies forwarded to `/auth/user`.
 */
export const AUTH_DEVICE_TOKEN_KEY = 'auth_device_token';
export const PAYMENT_DETAILS_KEY = 'payment_details';
export const AUTH_IDENTIFIER_KEY = 'auth_identifier';
export const REGISTRATION_CHARGES_KEY = 'registration_charges_context';
/** sessionStorage: login → verify-email without Bearer (OTP-only path). */
export const EMAIL_VERIFY_LOGIN_FLOW_KEY = 'email_verify_login_flow';
export const AUTH_REDIRECT_REASON_KEY = 'auth_redirect_reason';
export const AUTH_RETURN_TO_KEY = 'auth_return_to';
/** localStorage: message to show on Login after email verification (survives clearSession ordering). */
export const POST_VERIFY_LOGIN_NOTICE_KEY = 'post_verify_login_notice';
