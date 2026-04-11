import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { MotionPlugin } from '@vueuse/motion';
import { useAuthStore } from './stores/auth';
import { registerInertiaAuthHeaders } from './inertiaAuthHeaders';

registerInertiaAuthHeaders();

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const defaultDescription = 'SuGanta dashboard for notes, subscriptions, marketplace, payments, leads, and AI-powered workflows.';

const pageDescriptionMap = [
    { match: /^\/login/i, description: 'Sign in securely to access your SuGanta dashboard and account workflows.' },
    { match: /^\/register/i, description: 'Create your SuGanta account to manage notes, subscriptions, and marketplace access.' },
    { match: /^\/forgot-password/i, description: 'Reset your SuGanta account password securely and continue your workflow.' },
    { match: /^\/reset-password/i, description: 'Set a new secure password for your SuGanta account.' },
    { match: /^\/verify-otp/i, description: 'Verify your OTP to complete secure authentication on SuGanta.' },
    { match: /^\/payment-required/i, description: 'Complete registration fee payment to unlock your SuGanta dashboard access.' },
    { match: /^\/dashboard/i, description: 'View your overview dashboard with leads, payments, notifications, and recent activity.' },
    { match: /^\/notes/i, description: 'Browse notes, purchase securely, and download content with subscription-aware access control.' },
    { match: /^\/subscriptions/i, description: 'Manage subscription plans, active access, renewal, and cancellation workflows.' },
    { match: /^\/payments/i, description: 'Track payment history, statuses, and invoice access in one place.' },
    { match: /^\/marketplace/i, description: 'Discover marketplace listings, purchase soft copies, contact sellers, and manage your listings.' },
    { match: /^\/portfolio/i, description: 'Create and manage your portfolio with media, categories, tags, and publication controls.' },
    { match: /^\/profile/i, description: 'Manage your personal profile and account settings on SuGanta.' },
    { match: /^\/chat/i, description: 'Chat in real-time with users, sellers, and support conversations.' },
    { match: /^\/notifications/i, description: 'Review and manage your latest account and system notifications.' },
];

const ensureMetaTag = (name, content) => {
    if (typeof document === 'undefined') return;
    let tag = document.head.querySelector(`meta[name="${name}"]`);
    if (!tag) {
        tag = document.createElement('meta');
        tag.setAttribute('name', name);
        document.head.appendChild(tag);
    }
    tag.setAttribute('content', content);
};

const ensurePropertyMetaTag = (property, content) => {
    if (typeof document === 'undefined') return;
    let tag = document.head.querySelector(`meta[property="${property}"]`);
    if (!tag) {
        tag = document.createElement('meta');
        tag.setAttribute('property', property);
        document.head.appendChild(tag);
    }
    tag.setAttribute('content', content);
};

const getDescriptionForPath = (pathname) => {
    const normalized = String(pathname || '/').toLowerCase();
    const match = pageDescriptionMap.find(item => item.match.test(normalized));
    return match?.description || defaultDescription;
};

const getRobotsForPath = (pathname) => {
    // Keep all routes indexable.
    void pathname;
    return 'index, follow';
};

const applyPageMeta = () => {
    if (typeof window === 'undefined') return;
    const pathname = window.location.pathname;
    const description = getDescriptionForPath(pathname);
    const robots = getRobotsForPath(pathname);
    ensureMetaTag('description', description);
    ensureMetaTag('robots', robots);
    ensurePropertyMetaTag('og:title', document.title || appName);
    ensurePropertyMetaTag('og:description', description);
    ensurePropertyMetaTag('og:type', 'website');
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: name =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue', { eager: false }),
        ),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();
        const vueApp = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .use(MotionPlugin)
            .mount(el);

        useAuthStore().syncFromStorage();

        applyPageMeta();
        document.addEventListener('inertia:navigate', () => {
            // Wait for Inertia to update title first.
            setTimeout(applyPageMeta, 0);
        });

        return vueApp;
    },
    progress: {
        color: '#4B5563',
        delay: 120,
        includeCSS: false,
    },
});
