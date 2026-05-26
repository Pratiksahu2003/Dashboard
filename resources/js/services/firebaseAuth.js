import { initializeApp, getApps } from 'firebase/app';
import {
    FacebookAuthProvider,
    GoogleAuthProvider,
    getAuth,
    signInWithPopup,
} from 'firebase/auth';

const firebaseConfig = () => {
    const apiKey = import.meta.env.VITE_FIREBASE_API_KEY;
    if (!apiKey) return null;

    const projectId = import.meta.env.VITE_FIREBASE_PROJECT_ID || 'suganta-tutors';
    return {
        apiKey,
        authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN || `${projectId}.firebaseapp.com`,
        projectId,
        storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET || `${projectId}.appspot.com`,
        messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
        appId: import.meta.env.VITE_FIREBASE_APP_ID,
        measurementId: import.meta.env.VITE_FIREBASE_MEASUREMENT_ID,
    };
};

const providerFactory = provider => {
    const normalized = String(provider || '').toLowerCase();
    if (normalized === 'google') {
        const google = new GoogleAuthProvider();
        google.addScope('email');
        google.addScope('profile');
        google.setCustomParameters({ prompt: 'select_account' });
        return google;
    }
    if (normalized === 'facebook') {
        const facebook = new FacebookAuthProvider();
        facebook.addScope('email');
        facebook.addScope('public_profile');
        return facebook;
    }
    throw new Error('Unsupported social login provider.');
};

export const hasFirebaseAuthConfig = () => !!firebaseConfig()?.apiKey;

const serverErrorMessage = error =>
    error?.customData?._tokenResponse?.error?.message
    || error?.customData?._serverResponse?.error?.message
    || error?.customData?._tokenResponse?.error?.errors?.[0]?.message
    || '';

export const describeFirebaseAuthError = (error, provider = 'social') => {
    const code = String(error?.code || '');
    const detail = String(serverErrorMessage(error) || '').toUpperCase();
    const providerLabel = String(provider || 'social').replace(/^\w/, c => c.toUpperCase());

    if (code === 'auth/popup-closed-by-user' || code === 'auth/cancelled-popup-request') {
        return 'Social sign in was cancelled.';
    }
    if (code === 'auth/popup-blocked') {
        return 'Your browser blocked the sign-in popup. Please allow popups and try again.';
    }
    if (code === 'auth/unauthorized-domain') {
        return 'This domain is not authorized in Firebase Authentication. Add the current domain in Firebase Console > Authentication > Settings > Authorized domains.';
    }
    if (code === 'auth/operation-not-allowed' || detail.includes('OPERATION_NOT_ALLOWED')) {
        return `${providerLabel} sign in is not enabled in Firebase Authentication. Enable the provider in Firebase Console.`;
    }
    if (detail.includes('CONFIGURATION_NOT_FOUND')) {
        return `${providerLabel} sign in is not fully configured in Firebase. Check the provider App ID/secret, OAuth client, and callback URL in Firebase Console.`;
    }
    if (detail.includes('INVALID_IDP_RESPONSE') || detail.includes('INVALID_OAUTH')) {
        return `${providerLabel} returned an invalid OAuth response. Check the provider credentials and redirect/callback URL in Firebase.`;
    }
    if (code === 'auth/account-exists-with-different-credential') {
        return 'An account already exists with this email using another sign-in method. Please sign in with the original method first.';
    }
    if (code === 'auth/network-request-failed') {
        return 'Network error while contacting Firebase. Please check your connection and try again.';
    }
    if (code === 'auth/internal-error' && detail) {
        return `Firebase ${providerLabel} setup error: ${detail.replace(/_/g, ' ').toLowerCase()}.`;
    }

    return error?.message || `${providerLabel} sign in failed.`;
};

export async function signInWithFirebaseProvider(provider) {
    const config = firebaseConfig();
    if (!config) {
        throw new Error('Firebase web auth is not configured. Please set VITE_FIREBASE_* values.');
    }

    const app = getApps().length ? getApps()[0] : initializeApp(config);
    const auth = getAuth(app);
    auth.useDeviceLanguage();

    const authProvider = providerFactory(provider);

    const credential = await signInWithPopup(auth, authProvider);
    const token = await credential.user.getIdToken(true);

    return {
        provider,
        token,
        user: credential.user,
    };
}
