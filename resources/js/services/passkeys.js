import api from '@/api';
import { deviceName, unwrapAuthPayload } from '@/services/authFlow';

const base64UrlToArrayBuffer = value => {
    const base64 = String(value || '').replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), '=');
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
};

const arrayBufferToBase64Url = buffer => {
    const bytes = new Uint8Array(buffer || new ArrayBuffer(0));
    let binary = '';
    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
};

const prepareGetOptions = publicKey => ({
    ...publicKey,
    challenge: base64UrlToArrayBuffer(publicKey.challenge),
    allowCredentials: publicKey.allowCredentials?.map(item => ({
        ...item,
        id: base64UrlToArrayBuffer(item.id),
    })),
});

const prepareCreateOptions = publicKey => ({
    ...publicKey,
    challenge: base64UrlToArrayBuffer(publicKey.challenge),
    user: {
        ...publicKey.user,
        id: base64UrlToArrayBuffer(publicKey.user.id),
    },
    excludeCredentials: publicKey.excludeCredentials?.map(item => ({
        ...item,
        id: base64UrlToArrayBuffer(item.id),
    })) || [],
});

export const isPasskeySupported = () =>
    typeof window !== 'undefined'
    && window.isSecureContext
    && window.PublicKeyCredential
    && navigator.credentials;

export const describePasskeyError = error => {
    const code = Number(error?.code || 0);
    const message = String(error?.message || '');

    if (typeof window !== 'undefined' && !window.isSecureContext) {
        return 'Passkeys require HTTPS or localhost. Please open the app on a secure domain and try again.';
    }
    if (error?.name === 'NotAllowedError') {
        return 'Passkey sign in was cancelled or timed out. Please try again.';
    }
    if (error?.name === 'SecurityError') {
        return 'Passkey security check failed. Confirm the WebAuthn RP ID and allowed origin match this domain.';
    }
    if (code === 0 || /network error/i.test(message)) {
        return 'Passkey sign in is not reachable from this app yet. Please confirm the backend passkey routes are deployed and CORS allows this dashboard domain.';
    }
    if (code === 404) {
        return 'Passkey sign in is not enabled on the backend yet. Deploy the passkey API routes first.';
    }
    if (code === 401 || code === 403) {
        return 'Your session is not authorized for passkey access. Please sign in again and retry.';
    }

    return message || 'Passkey sign in failed.';
};

export async function loginWithPasskey(identifier = '') {
    if (!isPasskeySupported()) {
        throw new Error('Passkeys are not supported in this browser.');
    }

    const optionsResponse = await api.post(
        '/auth/passkey/login/options',
        identifier ? { identifier } : {},
        { skipCsrfBootstrap: true, skipAuthRedirect: true },
    );
    const options = unwrapAuthPayload(optionsResponse);
    const credential = await navigator.credentials.get({
        publicKey: prepareGetOptions(options.publicKey),
    });
    const assertion = credential.response;

    return api.post(
        '/auth/passkey/login/verify',
        {
            challenge_id: options.challenge_id,
            device_name: deviceName(),
            credential: {
                id: credential.id,
                rawId: arrayBufferToBase64Url(credential.rawId),
                type: credential.type,
                response: {
                    clientDataJSON: arrayBufferToBase64Url(assertion.clientDataJSON),
                    authenticatorData: arrayBufferToBase64Url(assertion.authenticatorData),
                    signature: arrayBufferToBase64Url(assertion.signature),
                    userHandle: assertion.userHandle ? arrayBufferToBase64Url(assertion.userHandle) : null,
                },
            },
        },
        { skipCsrfBootstrap: true, skipAuthRedirect: true },
    );
}

export async function registerPasskey(label = '') {
    if (!isPasskeySupported()) {
        throw new Error('Passkeys are not supported in this browser.');
    }

    const optionsResponse = await api.post(
        '/auth/passkey/register/options',
        {},
        { skipCsrfBootstrap: true },
    );
    const options = unwrapAuthPayload(optionsResponse);
    const credential = await navigator.credentials.create({
        publicKey: prepareCreateOptions(options.publicKey),
    });
    const attestation = credential.response;

    return api.post(
        '/auth/passkey/register/verify',
        {
            challenge_id: options.challenge_id,
            device_name: label || deviceName(),
            credential: {
                id: credential.id,
                rawId: arrayBufferToBase64Url(credential.rawId),
                type: credential.type,
                response: {
                    clientDataJSON: arrayBufferToBase64Url(attestation.clientDataJSON),
                    attestationObject: arrayBufferToBase64Url(attestation.attestationObject),
                },
            },
        },
        { skipCsrfBootstrap: true },
    );
}
