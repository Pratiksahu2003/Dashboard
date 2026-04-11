<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
/** Used only if Inertia shared props are missing (e.g. error boundary). Mirrors config/auth_slides.php */
const FALLBACK_SLIDES = [
    { image: '/App/1.png', title: 'Smart learning dashboard', subtitle: 'Track lessons, goals, and your growth in one place.', tag: 'Dashboard' },
    { image: '/App/2.png', title: 'Personalized progress', subtitle: 'See performance insights with a clean visual timeline.', tag: 'Progress' },
    { image: '/App/3.png', title: 'Connect and collaborate', subtitle: 'Learn with peers, teachers, and your community instantly.', tag: 'Community' },
    { image: '/App/4.png', title: 'Assignments made easy', subtitle: 'Complete work faster with guided workflows.', tag: 'Tasks' },
    { image: '/App/5.png', title: 'Anytime mobile access', subtitle: 'Stay consistent with learning on the go.', tag: 'Mobile' },
    { image: '/App/6.png', title: 'Insights that motivate', subtitle: 'Beautiful reports to keep momentum high.', tag: 'Insights' },
    { image: '/App/7.png', title: 'One app for everything', subtitle: 'From classes to outcomes - all in SuGanta.', tag: 'All-in-one' },
];

const page = usePage();
const slideVersion = computed(() => String(page.props.authSlidesVersion ?? '1'));

const appSlides = computed(() => {
    const s = page.props.authSlides;
    return Array.isArray(s) && s.length > 0 ? s : FALLBACK_SLIDES;
});

/**
 * Root-relative URLs for files in Laravel `public/` (e.g. public/App/1.png → /App/1.png).
 * Do not use import.meta.env.BASE_URL — in production it is often `/build/` (Vite output only), which
 * incorrectly produced /build/App/1.png and 404s. Static public assets are never under /build/.
 */
const publicAssetUrl = path => {
    if (!path || typeof path !== 'string') return '';
    if (/^https?:\/\//i.test(path)) return path;
    const normalized = path.startsWith('/') ? path : `/${path}`;
    const sep = normalized.includes('?') ? '&' : '?';
    return `${normalized}${sep}v=${encodeURIComponent(slideVersion.value)}`;
};

const onSlideImageError = e => {
    const el = e?.target;
    if (!el || el.dataset.slideFallback) return;
    el.dataset.slideFallback = '1';
    el.src = publicAssetUrl('/App/1.png');
};

const currentSlide = ref(0);
const showDemoModal = ref(false);
const demoPlaying = ref(false);
/** Avoid loading the mobile YouTube iframe until idle — prevents request storms if the layout remounts often. */
const mobileDemoEmbedReady = ref(false);
let intervalId = null;
let mobileEmbedTimer = null;

const activeSlide = computed(() => {
    const slides = appSlides.value;
    return slides[currentSlide.value] ?? slides[0];
});

const slideCount = () => appSlides.value.length;

const nextSlide = () => {
    const n = slideCount();
    if (n < 1) return;
    currentSlide.value = (currentSlide.value + 1) % n;
};

const prevSlide = () => {
    const n = slideCount();
    if (n < 1) return;
    currentSlide.value = (currentSlide.value - 1 + n) % n;
};

const goToSlide = index => {
    const n = slideCount();
    if (n < 1 || index < 0 || index >= n) return;
    currentSlide.value = index;
};

const startAutoPlay = () => {
    if (intervalId) clearInterval(intervalId);
    intervalId = setInterval(nextSlide, 4200);
};

const openDemoModal = () => {
    showDemoModal.value = true;
    demoPlaying.value = false;
};

const closeDemoModal = () => {
    showDemoModal.value = false;
    demoPlaying.value = false;
};

const playDemoVideo = () => {
    demoPlaying.value = true;
};

const onInertiaFinish = () => {
    // If user is authenticated and lands on an auth page via client-side navigation,
    // redirect them to dashboard. Server handles this for full page loads.
    const u = usePage().props.auth?.user;
    if (u && !router.processing) {
        try {
            const current = typeof route !== 'undefined' ? route().current() : null;
            const authRoutes = ['login', 'register', 'password.request', 'password.reset'];
            if (authRoutes.includes(current)) {
                router.visit(route('dashboard'), { replace: true });
            }
        } catch { /* ignore */ }
    }
};

const initAuthRedirect = () => {
    // Server-side (routes/auth.php) redirects authenticated users to dashboard on full page loads.
};

onMounted(() => {
    initAuthRedirect();

    document.addEventListener('inertia:finish', onInertiaFinish);

    startAutoPlay();
    mobileEmbedTimer = window.setTimeout(() => {
        mobileDemoEmbedReady.value = true;
    }, 400);
});
onBeforeUnmount(() => {
    document.removeEventListener('inertia:finish', onInertiaFinish);
    if (intervalId) clearInterval(intervalId);
    if (mobileEmbedTimer) clearTimeout(mobileEmbedTimer);
});
</script>

<template>
    <div class="min-h-screen bg-white flex flex-col md:flex-row overflow-hidden font-sans">
        <!-- Left: Form Section (40%) -->
        <div
            v-motion
            :initial="{ opacity: 0, x: -12 }"
            :enter="{ opacity: 1, x: 0, transition: { type: 'spring', stiffness: 420, damping: 40, mass: 0.8 } }"
            class="w-full md:w-[40%] flex flex-col p-8 md:p-12 lg:p-16 relative z-10"
        >
            <div class="flex items-center justify-between mb-12">
                <Link href="/" class="flex items-center group">
                    <img src="/logo/Su250.png" alt="SuGanta Logo" class="h-12 w-auto object-contain transition-transform group-hover:scale-105" />
                </Link>
                <Link href="/" class="text-sm font-bold text-gray-500 hover:text-gray-900 flex items-center transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Go back
                </Link>
            </div>

            <div class="flex-1 flex flex-col justify-center max-w-[400px] mx-auto w-full">
                <div v-motion :initial="{ opacity: 0, y: 12 }" :enter="{ opacity: 1, y: 0, transition: { delay: 0.05, type: 'spring', stiffness: 500, damping: 40 } }" class="mb-8">
                    <h1 class="text-3xl font-black text-gray-900 tracking-tight mb-3">
                        <slot name="title">Sign in</slot>
                    </h1>
                    <p class="text-gray-500 font-medium">
                        <slot name="subtitle">Welcome back! Please enter your details.</slot>
                    </p>
                </div>

                <slot />

                <div class="mt-12 text-center md:text-left">
                    <p class="text-sm font-medium text-gray-500">
                        <slot name="footer">
                            Need an account? <Link :href="route('register')" class="text-blue-600 font-bold hover:underline">Sign up here</Link>
                        </slot>
                    </p>
                </div>
            </div>
        </div>

        <!-- Mobile: Demo + Download (shown at the end) -->
        <div class="md:hidden px-5 pb-6">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-black text-slate-900">Watch Demo</div>
                        <div class="text-xs font-medium text-slate-500 mt-0.5">See SuGanta in action</div>
                    </div>
                    <a
                        href="https://www.suganta.com/app"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-[11px] font-black text-white hover:bg-slate-700 transition-colors"
                    >
                        Download App
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                </div>
                <div class="relative w-full bg-black" style="padding-top: 56.25%;">
                    <iframe
                        v-if="mobileDemoEmbedReady"
                        class="absolute inset-0 w-full h-full"
                        src="https://www.youtube.com/embed/0CT5hjq9tEE"
                        title="SuGanta Demo Video"
                        frameborder="0"
                        loading="lazy"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen
                    ></iframe>
                </div>
            </div>
        </div>

        <!-- Right: New white modern app showcase -->
        <div
            v-motion
            :initial="{ opacity: 0, x: 16 }"
            :enter="{ opacity: 1, x: 0, transition: { delay: 0.08, type: 'spring', stiffness: 360, damping: 44, mass: 0.9 } }"
            class="hidden md:block w-[60%] p-5 lg:p-6 relative"
        >
            <div class="h-full w-full rounded-[34px] overflow-hidden relative border border-slate-200 bg-gradient-to-br from-white to-slate-50 shadow-[0_20px_80px_rgba(15,23,42,0.10)]">
                <div class="absolute inset-0 white-grid opacity-70"></div>
                <div class="absolute -top-28 -right-28 w-[420px] h-[420px] rounded-full bg-blue-100/70 blur-3xl"></div>
                <div class="absolute -bottom-24 -left-24 w-[380px] h-[420px] rounded-full bg-indigo-100/65 blur-3xl"></div>
                <div class="absolute inset-0 white-vignette"></div>

                <div class="absolute top-6 right-6 z-20">
                    <a
                        href="https://www.suganta.com/app"
                        target="_blank"
                        rel="noopener noreferrer"
                        v-motion
                        :initial="{ opacity: 0, y: -8 }"
                        :enter="{ opacity: 1, y: 0, transition: { delay: 0.2 } }"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black tracking-tight text-slate-800 hover:text-blue-700 hover:border-blue-200 transition-colors"
                    >
                        Download Mobile App
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                </div>

                <div class="absolute inset-0 z-10 p-6 lg:p-8 flex flex-col">
                    <div
                        :key="`slide-${currentSlide}`"
                        v-motion
                        :initial="{ opacity: 0, y: 14, scale: 0.99 }"
                        :enter="{ opacity: 1, y: 0, scale: 1, transition: { type: 'spring', stiffness: 320, damping: 34 } }"
                        class="flex flex-col h-full"
                    >
                    <div class="max-w-4xl">
                        <div class="inline-flex items-center gap-3 px-4 py-2.5 rounded-2xl border border-slate-200 bg-white shadow-sm mb-5">
                            <img src="/logo/Su250.png" class="h-8 w-auto" />
                            <span class="text-xs font-black uppercase tracking-[0.22em] text-slate-500">{{ activeSlide.tag }}</span>
                        </div>

                        <h2 class="text-[36px] lg:text-[42px] leading-[1.06] font-black tracking-tight text-slate-900 max-w-4xl">
                            {{ activeSlide.title }}
                        </h2>
                        <p class="mt-3 text-slate-600 text-[18px] lg:text-[22px] leading-tight max-w-4xl">
                            {{ activeSlide.subtitle }}
                        </p>

                        <div class="mt-6 flex items-center gap-2 text-sm font-bold text-slate-500">
                            <span class="inline-flex w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            Built for speed, focus, and consistency
                        </div>
                    </div>

                    <div class="mt-5 lg:mt-6 relative flex-1 min-h-0">
                        <div class="absolute -inset-3 rounded-[30px] bg-gradient-to-r from-blue-200/45 to-indigo-200/45 blur-xl"></div>
                        <div class="relative h-full min-h-[300px] lg:min-h-[360px] rounded-[28px] border border-slate-200 bg-white p-3 lg:p-4 shadow-[0_20px_60px_rgba(15,23,42,0.14)] flex flex-col">
                            <div class="rounded-[20px] bg-slate-100 border border-slate-200 overflow-hidden flex-1 min-h-[260px] lg:min-h-[300px] flex items-center justify-center relative aspect-[4/3] max-h-[min(52vh,560px)]">
                                <!-- Stacked images: browser caches all slides; only visibility toggles (no reload per slide). -->
                                <img
                                    v-for="(slide, i) in appSlides"
                                    :key="slide.image"
                                    :src="publicAssetUrl(slide.image)"
                                    :alt="slide.title"
                                    :loading="i === 0 ? 'eager' : 'lazy'"
                                    :fetchpriority="i === 0 ? 'high' : 'low'"
                                    decoding="async"
                                    width="1200"
                                    height="900"
                                    class="absolute inset-0 w-full h-full object-contain object-center transition-opacity duration-300"
                                    :class="i === currentSlide ? 'opacity-100 z-[1]' : 'opacity-0 z-0 pointer-events-none'"
                                    @error="onSlideImageError"
                                />
                            </div>
                            <div class="mt-3 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-base font-black text-slate-900 tracking-tight">{{ activeSlide.title }}</div>
                                    <div class="text-sm font-medium text-slate-500 mt-1">{{ activeSlide.subtitle }}</div>
                                </div>
                                <div class="hidden lg:flex shrink-0 items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 text-xs font-black">
                                    Live Preview
                                </div>
                            </div>

                            <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Watch Demo</div>
                                    <div class="text-sm font-semibold text-slate-800 mt-0.5">See full SuGanta walkthrough</div>
                                </div>
                                <button
                                    type="button"
                                    @click="openDemoModal"
                                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-700 transition-colors"
                                >
                                    Play Video
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

                <div
                    v-motion
                    :initial="{ opacity: 0, y: 10 }"
                    :enter="{ opacity: 1, y: 0, transition: { delay: 0.28 } }"
                    class="absolute bottom-4 left-8 right-8 z-[30] flex items-center justify-between pointer-events-auto"
                >
                    <div class="flex items-center gap-2">
                        <button
                            v-for="(s, i) in appSlides"
                            :key="s.image || s.title"
                            type="button"
                            @click="goToSlide(i)"
                            :class="[
                                'rounded-full transition-all',
                                i === currentSlide ? 'w-8 h-2 bg-slate-900' : 'w-2 h-2 bg-slate-300 hover:bg-slate-500'
                            ]"
                        />
                    </div>
                    <div class="flex gap-3">
                        <button type="button" @click="prevSlide" class="p-3 bg-white border border-slate-200 rounded-full text-slate-700 hover:bg-slate-100 transition-all shadow-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button type="button" @click="nextSlide" class="p-3 bg-slate-900 border border-slate-900 rounded-full text-white hover:bg-slate-700 transition-all shadow-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        v-if="showDemoModal"
        class="fixed inset-0 z-[100] bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="closeDemoModal"
    >
        <div class="w-full max-w-5xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200">
                <div class="text-sm font-black text-slate-900">SuGanta Demo Video</div>
                <button
                    type="button"
                    @click="closeDemoModal"
                    class="h-8 w-8 rounded-lg border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition"
                >
                    ✕
                </button>
            </div>
            <div class="relative w-full bg-black" style="padding-top: 56.25%;">
                <button
                    v-if="!demoPlaying"
                    type="button"
                    @click="playDemoVideo"
                    class="absolute inset-0 w-full h-full group"
                    aria-label="Play demo video"
                >
                    <img
                        :src="publicAssetUrl('/Launch.png')"
                        alt="SuGanta app launch preview"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 w-full h-full object-cover"
                    />
                    <div class="absolute inset-0 bg-black/30 group-hover:bg-black/40 transition-colors"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/95 px-5 py-3 text-sm font-black text-slate-900 shadow-lg group-hover:scale-105 transition-transform">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                            Play Demo
                        </span>
                    </div>
                </button>
                <iframe
                    v-else
                    class="absolute inset-0 w-full h-full"
                    src="https://www.youtube.com/embed/0CT5hjq9tEE?autoplay=1"
                    title="SuGanta Demo Video"
                    frameborder="0"
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                ></iframe>
            </div>
        </div>
    </div>
</template>

<style scoped>
.white-grid {
    background-image: linear-gradient(to right, rgba(148, 163, 184, 0.08) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
    background-size: 28px 28px;
}

.white-vignette {
    background: radial-gradient(1000px 700px at 55% 40%, rgba(255,255,255,0.08) 0%, rgba(15,23,42,0.03) 48%, rgba(15,23,42,0.08) 100%);
}
</style>
