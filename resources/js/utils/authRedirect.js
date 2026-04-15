export const getLoginUrl = () => {
    try {
        const fallback = route('login');
        if (typeof window === 'undefined') return fallback;
        return route('login', { redirect: window.location.href });
    } catch {
        return '/login';
    }
};
