import { AUTH_TOKEN_KEY, PAYMENT_DETAILS_KEY } from '@/constants/authStorage';

export const deviceName = () => {
    if (typeof navigator === 'undefined') return 'Web Browser';
    return (navigator.userAgent || 'Web Browser').slice(0, 120);
};

export const unwrapAuthPayload = response => {
    if (!response || typeof response !== 'object') return {};
    const data = response.data && typeof response.data === 'object' ? response.data : null;

    if (data && (data.token || data.user || data.needs_onboarding !== undefined || data.needs_payment !== undefined)) {
        return data;
    }

    return response;
};

export const extractAuthToken = response => {
    const payload = unwrapAuthPayload(response);
    return payload?.token || payload?.access_token || response?.token || response?.data?.token || '';
};

export const persistAuthToken = token => {
    if (typeof token === 'string' && token.trim()) {
        localStorage.setItem(AUTH_TOKEN_KEY, token.trim());
    }
};

export const needsOnboarding = response => {
    const payload = unwrapAuthPayload(response);
    const user = payload?.user && typeof payload.user === 'object' ? payload.user : {};

    return payload?.needs_onboarding === true
        || payload?.next_step === 'onboarding'
        || user?.is_profile_complete === false
        || user?.is_phone_verified === false;
};

export const needsPayment = response => {
    const payload = unwrapAuthPayload(response);
    const errors = response?.errors && typeof response.errors === 'object' ? response.errors : {};
    const user = payload?.user && typeof payload.user === 'object' ? payload.user : {};

    return payload?.needs_payment === true
        || payload?.requires_registration_payment === true
        || payload?.payment_required === true
        || errors?.requires_registration_payment === true
        || user?.is_fee_paid === false;
};

export const persistPaymentGate = (response, fallbackMessage = 'Registration payment is required to continue.') => {
    const payload = unwrapAuthPayload(response);
    const errors = response?.errors && typeof response.errors === 'object' ? response.errors : {};
    const user = payload?.user && typeof payload.user === 'object' ? payload.user : {};
    const gate = {
        ...errors,
        ...payload,
        requires_registration_payment: true,
        role: payload?.role || user?.role || errors?.role,
        discounted_price: payload?.amount ?? errors?.discounted_price,
        currency: payload?.currency || errors?.currency || 'INR',
        message: response?.message || payload?.message || errors?.message || fallbackMessage,
    };
    delete gate.token;
    delete gate.access_token;

    localStorage.setItem(
        PAYMENT_DETAILS_KEY,
        JSON.stringify({
            success: false,
            message: gate.message,
            errors: gate,
            code: response?.code || 200,
        }),
    );
};
