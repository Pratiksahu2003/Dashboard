<script setup>
import { onMounted } from 'vue';

const GTM_IDS = ['GTM-KWN9WTDG', 'GTM-W4Q9SS5'];
const GTAG_ID = 'G-ZB176XD2YZ';
const ADS_ID = 'AW-714159034';
const ADS_CONVERSION_ID = 'AW-714159034/mlv4CIK20p8bELrnxNQC';
const CLARITY_ID = 'u17ah1ulb7';
const FACEBOOK_PIXEL_ID = '1427181572239343';

const appendScript = (src, attrs = {}) => {
    if (document.querySelector(`script[src="${src}"]`)) return;
    const script = document.createElement('script');
    script.src = src;
    Object.entries(attrs).forEach(([name, value]) => {
        if (value === true) {
            script.setAttribute(name, '');
        } else {
            script.setAttribute(name, String(value));
        }
    });
    const mountPoint = document.head || document.body || document.documentElement;
    mountPoint.appendChild(script);
};

const initGoogleTagManager = () => {
    window.dataLayer = window.dataLayer || [];
    const firstScript = document.getElementsByTagName('script')[0];
    const dl = 'dataLayer' !== 'dataLayer' ? '&l=dataLayer' : '';

    GTM_IDS.forEach(id => {
        window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
        const script = document.createElement('script');
        script.async = true;
        script.src = `https://app.suganta.com/um72?id=${id}${dl}`;
        if (firstScript?.parentNode) {
            firstScript.parentNode.insertBefore(script, firstScript);
            return;
        }
        (document.head || document.documentElement).appendChild(script);
    });
};

const initGtag = () => {
    appendScript(`https://www.googletagmanager.com/gtag/js?id=${GTAG_ID}`, { async: true });
    window.dataLayer = window.dataLayer || [];
    window.gtag = function gtag() {
        window.dataLayer.push(arguments);
    };
    window.gtag('js', new Date());
    window.gtag('config', GTAG_ID);
    window.gtag('config', ADS_ID);
    window.gtag('event', 'conversion', {
        send_to: ADS_CONVERSION_ID,
        transaction_id: '',
    });
};

const initClarity = () => {
    if (window.clarity) return;
    window.clarity = function clarity() {
        (window.clarity.q = window.clarity.q || []).push(arguments);
    };
    appendScript(`https://www.clarity.ms/tag/${CLARITY_ID}`, { async: true });
};

const initFacebookPixel = () => {
    if (!window.fbq) {
        const fbq = function fbqProxy() {
            if (fbq.callMethod) {
                fbq.callMethod.apply(fbq, arguments);
            } else {
                fbq.queue.push(arguments);
            }
        };
        window.fbq = fbq;
        if (!window._fbq) window._fbq = fbq;
        fbq.push = fbq;
        fbq.loaded = true;
        fbq.version = '2.0';
        fbq.queue = [];
    }

    window.fbq('init', FACEBOOK_PIXEL_ID);
    window.fbq('track', 'PageView');
    appendScript('https://connect.facebook.net/en_US/fbevents.js', { defer: true });
};

const addFacebookNoscript = () => {
    if (document.getElementById('facebook-pixel-noscript')) return;
    const noscript = document.createElement('noscript');
    noscript.id = 'facebook-pixel-noscript';
    noscript.innerHTML =
        '<img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=1427181572239343&amp;ev=PageView&amp;noscript=1">';
    (document.body || document.documentElement).appendChild(noscript);
};

const exposeCookieHandlers = () => {
    window.acceptAnalyticsCookies = function acceptAnalyticsCookies() {};
    window.rejectAnalyticsCookies = function rejectAnalyticsCookies() {};
};

onMounted(() => {
    initGoogleTagManager();
    initGtag();
    initClarity();
    initFacebookPixel();
    addFacebookNoscript();
    exposeCookieHandlers();
});
</script>

<template>
    <span class="hidden" aria-hidden="true"></span>
</template>
