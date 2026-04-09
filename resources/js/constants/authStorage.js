/**
 * Browser storage keys for auth (localStorage unless noted).
 * Do not rename values — existing sessions rely on these strings.
 */
export const AUTH_TOKEN_KEY = 'auth_token';
export const AUTH_USER_KEY = 'user';
export const AUTH_SESSION_TS_KEY = 'auth_session_ts';
export const AUTH_DEVICE_TOKEN_KEY = 'auth_device_token';
export const PAYMENT_DETAILS_KEY = 'payment_details';
export const AUTH_IDENTIFIER_KEY = 'auth_identifier';
export const REGISTRATION_CHARGES_KEY = 'registration_charges_context';
/** sessionStorage: login → verify-email without Bearer (OTP-only path). */
export const EMAIL_VERIFY_LOGIN_FLOW_KEY = 'email_verify_login_flow';
export const AUTH_REDIRECT_REASON_KEY = 'auth_redirect_reason';
