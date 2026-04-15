import { router } from '@inertiajs/vue3';
import { clearClientAuthState } from '@/composables/useAuth';

let redirectLock = false;

const normalizePath = value => {
    const s = String(value || '');
    const path = s.split('?')[0].split('#')[0];
    if (!path) return '/';
    return path.endsWith('/') && path.length > 1 ? path.slice(0, -1) : path;
};

const buildLoginTargetWithReturn = loginUrl => {
    if (typeof window === 'undefined') return loginUrl;
    const current = window.location.href;
    try {
        const url = new URL(loginUrl, window.location.origin);
        url.searchParams.set('redirect', current);
        return url.toString();
    } catch {
        return loginUrl;
    }
};

/**
 * Single global handler: any `app:unauthorized` (401/403 from API) sends the user to login.
 * Retries when Inertia is mid-navigation; falls back to full page load if needed.
 */
export function installUnauthorizedRedirect() {
    if (typeof document === 'undefined') return;

    const handler = () => {
        if (typeof window === 'undefined') return;
        if (redirectLock) return;
        redirectLock = true;

        let target;
        try {
            target = buildLoginTargetWithReturn(route('login'));
        } catch {
            redirectLock = false;
            return;
        }

        const targetPath = normalizePath(new URL(target, window.location.origin).pathname);
        const currentPath = normalizePath(window.location.pathname);
        if (targetPath === currentPath) {
            redirectLock = false;
            return;
        }

        const go = () => {
            clearClientAuthState();
            router.visit(target, { replace: true, preserveState: false, preserveScroll: false });
        };

        const releaseLockSoon = () => {
            window.setTimeout(() => {
                redirectLock = false;
            }, 600);
        };

        if (!router.processing) {
            go();
            releaseLockSoon();
            return;
        }

        let settled = false;
        const onFinish = () => {
            if (settled) return;
            settled = true;
            document.removeEventListener('inertia:finish', onFinish);
            go();
            releaseLockSoon();
        };

        document.addEventListener('inertia:finish', onFinish);
        window.setTimeout(() => {
            if (settled) return;
            settled = true;
            document.removeEventListener('inertia:finish', onFinish);
            clearClientAuthState();
            if (normalizePath(window.location.pathname) !== targetPath) {
                window.location.assign(target);
            }
            releaseLockSoon();
        }, 2500);
    };

    document.addEventListener('app:unauthorized', handler);
}
