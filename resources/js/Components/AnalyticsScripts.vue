<script setup>
import { onMounted } from 'vue';

const GTM_ID = 'GTM-KWN9WTDG';
const CLARITY_ID = 'u17ah1ulb7';
const FACEBOOK_PIXEL_ID = '1427181572239343';

const markLoaded = key => {
    window.__analyticsScriptsLoaded = window.__analyticsScriptsLoaded || {};
    window.__analyticsScriptsLoaded[key] = true;
};

const isLoaded = key => Boolean(window.__analyticsScriptsLoaded?.[key]);

const appendScript = (src, attrs = {}) => {
    if (document.querySelector(`script[src="${src}"]`)) return;
    const script = document.createElement('script');
    script.src = src;
    Object.entries(attrs).forEach(([name, value]) => {
        if (value === true) {
            script.setAttribute(name, '');
            return;
        }
        script.setAttribute(name, String(value));
    });
    (document.head || document.documentElement).appendChild(script);
};

const initGoogleTagManager = () => {
    if (isLoaded('gtm')) return;
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
    appendScript(`https://www.suganta.com/um72/?id=${GTM_ID}`);
    markLoaded('gtm');
};

const trackGoogleAdsConversion = () => {
    if (isLoaded('google-ads-conversion')) return;
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function gtag() {
        window.dataLayer.push(arguments);
    };
    window.gtag('event', 'conversion', {
        send_to: 'AW-714159034/mlv4CIK20p8bELrnxNQC',
        transaction_id: '',
    });
    markLoaded('google-ads-conversion');
};

const initClarity = () => {
    if (isLoaded('clarity')) return;
    window.clarity = window.clarity || function clarity() {
        (window.clarity.q = window.clarity.q || []).push(arguments);
    };
    appendScript(`https://www.clarity.ms/tag/${CLARITY_ID}`, { async: true });
    markLoaded('clarity');
};

const initFacebookPixel = () => {
    if (isLoaded('facebook-pixel')) return;

    if (!window.fbq) {
        const fbq = function fbqProxy() {
            if (fbq.callMethod) {
                fbq.callMethod.apply(fbq, arguments);
            } else {
                fbq.queue.push(arguments);
            }
        };
        fbq.push = fbq;
        fbq.loaded = true;
        fbq.version = '2.0';
        fbq.queue = [];
        window.fbq = fbq;
        window._fbq = fbq;
    }

    window.fbq('init', FACEBOOK_PIXEL_ID);
    window.fbq('track', 'PageView');
    appendScript('https://connect.facebook.net/en_US/fbevents.js', { defer: true });
    markLoaded('facebook-pixel');
};

const addFacebookNoScriptImage = () => {
    if (document.getElementById('facebook-pixel-noscript')) return;
    const noscript = document.createElement('noscript');
    noscript.id = 'facebook-pixel-noscript';
    noscript.innerHTML = `<img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=${FACEBOOK_PIXEL_ID}&ev=PageView&noscript=1">`;
    (document.body || document.documentElement).appendChild(noscript);
};

const exposeCookieHandlers = () => {
    window.acceptAnalyticsCookies = window.acceptAnalyticsCookies || function acceptAnalyticsCookies() {};
    window.rejectAnalyticsCookies = window.rejectAnalyticsCookies || function rejectAnalyticsCookies() {};
};

onMounted(() => {
    initGoogleTagManager();
    trackGoogleAdsConversion();
    initClarity();
    initFacebookPixel();
    addFacebookNoScriptImage();
    exposeCookieHandlers();
});
</script>

<template>
    <span class="hidden" aria-hidden="true"></span>
</template>
