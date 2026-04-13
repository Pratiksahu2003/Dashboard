<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: Number, default: 404 },
});

const errorConfig = computed(() => {
    const map = {
        404: { title: 'Page Not Found',       message: "The page you're looking for doesn't exist or has been moved." },
        403: { title: 'Access Forbidden',      message: "You don't have permission to access this page." },
        500: { title: 'Server Error',          message: 'Something went wrong on our end. Please try again later.' },
        503: { title: 'Service Unavailable',   message: 'We are down for maintenance. Please check back soon.' },
        419: { title: 'Session Expired',       message: 'Your session has expired. Please log in again to continue.' },
        429: { title: 'Too Many Requests',     message: 'You have made too many requests. Please slow down and try again.' },
    };
    return map[props.status] ?? { title: 'Unexpected Error', message: 'An unexpected error occurred. Please try again.' };
});
</script>

<template>
    <Head :title="`${status} — ${errorConfig.title}`" />

    <div class="error-root">
        <!-- Dot grid background -->
        <div class="error-dotgrid" aria-hidden="true"></div>

        <!-- Card -->
        <div class="error-card">

            <!-- Logo -->
            <div class="error-logo">
                <img src="/logo/Su250.png" alt="SuGanta" />
            </div>

            <!-- Illustration -->
            <div class="error-illo" aria-hidden="true">
                <svg viewBox="0 0 560 260" fill="none" xmlns="http://www.w3.org/2000/svg">

                    <!-- Ground shadow blob -->
                    <ellipse cx="280" cy="232" rx="210" ry="22" fill="#F1F5F9" />
                    <ellipse cx="280" cy="232" rx="140" ry="14" fill="#E2E8F0" />

                    <!-- Hanging strings -->
                    <line x1="140" y1="0"  x2="140" y2="68"  stroke="#CBD5E1" stroke-width="1.5" stroke-dasharray="4 3"/>
                    <line x1="280" y1="0"  x2="280" y2="52"  stroke="#CBD5E1" stroke-width="1.5" stroke-dasharray="4 3"/>
                    <line x1="420" y1="0"  x2="420" y2="68"  stroke="#CBD5E1" stroke-width="1.5" stroke-dasharray="4 3"/>

                    <!-- ═══ LEFT "4" — brand indigo/blue flat 3D block ═══ -->
                    <g>
                        <!-- right side face -->
                        <path d="M185 68 L200 54 L200 188 L185 202 Z" fill="#3730A3"/>
                        <!-- top face -->
                        <path d="M95 68 L110 54 L200 54 L185 68 Z" fill="#818CF8"/>
                        <!-- front face -->
                        <path d="M95 68 L95 202 L185 202 L185 68 Z" fill="#6366F1"/>
                        <!-- horizontal bar cutout (front) -->
                        <path d="M95 138 L95 162 L155 162 L155 138 Z" fill="#4F46E5"/>
                        <!-- vertical right bar -->
                        <path d="M155 68 L155 202 L185 202 L185 68 Z" fill="#4F46E5"/>
                        <!-- top of vertical bar -->
                        <path d="M155 68 L170 54 L200 54 L185 68 Z" fill="#A5B4FC"/>
                    </g>

                    <!-- ═══ CENTER "0" — brand orange flat 3D cylinder ═══ -->
                    <g>
                        <!-- outer body -->
                        <rect x="222" y="68" width="116" height="160" rx="58" fill="#F97316"/>
                        <!-- top ellipse -->
                        <ellipse cx="280" cy="68" rx="58" ry="18" fill="#FB923C"/>
                        <!-- inner hole body -->
                        <rect x="248" y="90" width="64" height="116" rx="32" fill="white"/>
                        <!-- inner hole top ellipse -->
                        <ellipse cx="280" cy="90" rx="32" ry="10" fill="#FED7AA"/>
                        <!-- hanging pin -->
                        <rect x="274" y="228" width="12" height="20" rx="4" fill="#EA580C"/>
                        <ellipse cx="280" cy="228" rx="6" ry="4" fill="#F97316"/>
                    </g>

                    <!-- ═══ RIGHT "4" — slate flat 3D block ═══ -->
                    <g>
                        <!-- right side face -->
                        <path d="M465 68 L480 54 L480 188 L465 202 Z" fill="#334155"/>
                        <!-- top face -->
                        <path d="M375 68 L390 54 L480 54 L465 68 Z" fill="#94A3B8"/>
                        <!-- front face -->
                        <path d="M375 68 L375 202 L465 202 L465 68 Z" fill="#64748B"/>
                        <!-- horizontal bar cutout (front) -->
                        <path d="M375 138 L375 162 L435 162 L435 138 Z" fill="#475569"/>
                        <!-- vertical right bar -->
                        <path d="M435 68 L435 202 L465 202 L465 68 Z" fill="#475569"/>
                        <!-- top of vertical bar -->
                        <path d="M435 68 L450 54 L480 54 L465 68 Z" fill="#CBD5E1"/>
                        <!-- pencil detail -->
                        <rect x="430" y="178" width="9" height="28" rx="2" fill="#F59E0B"/>
                        <polygon points="430,206 439,206 434.5,220" fill="#FCD34D"/>
                    </g>

                    <!-- ═══ Person on left "4" — sitting, reading ═══ -->
                    <g transform="translate(108, 28)">
                        <!-- head -->
                        <circle cx="16" cy="12" r="9" fill="#FBBF24"/>
                        <!-- hair -->
                        <path d="M7 10 Q16 2 25 10" fill="#1E293B"/>
                        <!-- body -->
                        <rect x="8" y="20" width="16" height="18" rx="5" fill="#1E293B"/>
                        <!-- left arm holding book -->
                        <line x1="8"  y1="26" x2="-2" y2="32" stroke="#1E293B" stroke-width="4" stroke-linecap="round"/>
                        <!-- right arm -->
                        <line x1="24" y1="26" x2="30" y2="22" stroke="#1E293B" stroke-width="4" stroke-linecap="round"/>
                        <!-- book -->
                        <rect x="-8" y="26" width="12" height="9" rx="1.5" fill="#6366F1"/>
                        <line x1="-2" y1="26" x2="-2" y2="35" stroke="white" stroke-width="1"/>
                        <!-- legs dangling -->
                        <line x1="12" y1="38" x2="8"  y2="56" stroke="#1E293B" stroke-width="4" stroke-linecap="round"/>
                        <line x1="20" y1="38" x2="24" y2="56" stroke="#1E293B" stroke-width="4" stroke-linecap="round"/>
                        <!-- shoes -->
                        <ellipse cx="8"  cy="57" rx="5" ry="3" fill="#0F172A"/>
                        <ellipse cx="24" cy="57" rx="5" ry="3" fill="#0F172A"/>
                    </g>

                    <!-- ═══ Person on "0" — standing, arms raised (celebrating) ═══ -->
                    <g transform="translate(258, 14)">
                        <!-- head -->
                        <circle cx="22" cy="12" r="9" fill="#FBBF24"/>
                        <!-- hair -->
                        <path d="M13 9 Q22 1 31 9" fill="#92400E"/>
                        <!-- body -->
                        <rect x="14" y="20" width="16" height="18" rx="5" fill="#F97316"/>
                        <!-- arms up -->
                        <line x1="14" y1="24" x2="2"  y2="10" stroke="#F97316" stroke-width="4" stroke-linecap="round"/>
                        <line x1="30" y1="24" x2="42" y2="10" stroke="#F97316" stroke-width="4" stroke-linecap="round"/>
                        <!-- hands -->
                        <circle cx="2"  cy="9"  r="3.5" fill="#FBBF24"/>
                        <circle cx="42" cy="9"  r="3.5" fill="#FBBF24"/>
                        <!-- legs -->
                        <line x1="18" y1="38" x2="14" y2="54" stroke="#F97316" stroke-width="4" stroke-linecap="round"/>
                        <line x1="26" y1="38" x2="30" y2="54" stroke="#F97316" stroke-width="4" stroke-linecap="round"/>
                        <!-- shoes -->
                        <ellipse cx="14" cy="55" rx="5" ry="3" fill="#0F172A"/>
                        <ellipse cx="30" cy="55" rx="5" ry="3" fill="#0F172A"/>
                    </g>

                    <!-- ═══ Person on right "4" — sitting, tie ═══ -->
                    <g transform="translate(390, 28)">
                        <!-- head -->
                        <circle cx="16" cy="12" r="9" fill="#FBBF24"/>
                        <!-- hair -->
                        <path d="M7 10 Q16 2 25 10" fill="#1E293B"/>
                        <!-- body -->
                        <rect x="8" y="20" width="16" height="18" rx="5" fill="#475569"/>
                        <!-- tie -->
                        <polygon points="16,22 13,34 16,31 19,34" fill="#F97316"/>
                        <!-- arms -->
                        <line x1="8"  y1="26" x2="0"  y2="22" stroke="#475569" stroke-width="4" stroke-linecap="round"/>
                        <line x1="24" y1="26" x2="32" y2="22" stroke="#475569" stroke-width="4" stroke-linecap="round"/>
                        <!-- legs dangling -->
                        <line x1="12" y1="38" x2="8"  y2="56" stroke="#475569" stroke-width="4" stroke-linecap="round"/>
                        <line x1="20" y1="38" x2="24" y2="56" stroke="#475569" stroke-width="4" stroke-linecap="round"/>
                        <!-- shoes -->
                        <ellipse cx="8"  cy="57" rx="5" ry="3" fill="#0F172A"/>
                        <ellipse cx="24" cy="57" rx="5" ry="3" fill="#0F172A"/>
                    </g>

                    <!-- Floating accent dots -->
                    <circle cx="72"  cy="110" r="6"   fill="#6366F1" opacity="0.35"/>
                    <circle cx="52"  cy="160" r="4"   fill="#F97316" opacity="0.4"/>
                    <circle cx="500" cy="100" r="5"   fill="#64748B" opacity="0.3"/>
                    <circle cx="520" cy="155" r="7"   fill="#F97316" opacity="0.25"/>
                    <circle cx="210" cy="220" r="7"   fill="#F97316" opacity="0.55"/>
                    <circle cx="350" cy="226" r="5"   fill="#F97316" opacity="0.4"/>
                    <circle cx="165" cy="48"  r="4.5" fill="#818CF8" opacity="0.5"/>
                    <circle cx="400" cy="42"  r="4"   fill="#94A3B8" opacity="0.45"/>
                </svg>
            </div>

            <!-- Error code badge -->
            <div class="error-code-badge">
                <span class="error-code-dot"></span>
                Error {{ status }}
            </div>

            <!-- Title & message -->
            <h1 class="error-title">Oops! {{ errorConfig.title }}</h1>
            <p class="error-message">{{ errorConfig.message }}</p>

            <!-- Buttons -->
            <div class="error-actions">
                <a href="https://www.suganta.com" class="error-btn error-btn--primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Back to Home
                </a>
                <a :href="route('login')" class="error-btn error-btn--outline">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Back to Login
                </a>
            </div>

        </div>
    </div>
</template>

<style scoped>
/* ── Root ── */
.error-root {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8fafc;
    padding: 24px;
    position: relative;
    overflow: hidden;
    font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
}

/* ── Dot grid ── */
.error-dotgrid {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(circle, #cbd5e1 1.2px, transparent 1.2px);
    background-size: 26px 26px;
    opacity: 0.4;
    pointer-events: none;
}

/* ── Card ── */
.error-card {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 720px;
    background: #ffffff;
    border: 2.5px solid #f97316;
    border-radius: 28px;
    box-shadow:
        0 0 0 6px rgba(249, 115, 22, 0.07),
        0 24px 64px -12px rgba(249, 115, 22, 0.18),
        0 8px 24px -4px rgba(15, 23, 42, 0.06);
    padding: 44px 40px 40px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0;
}

/* ── Logo ── */
.error-logo {
    margin-bottom: 20px;
}
.error-logo img {
    height: 40px;
    width: auto;
    object-fit: contain;
}

/* ── Illustration ── */
.error-illo {
    width: 100%;
    max-width: 560px;
    margin-bottom: 24px;
}
.error-illo svg {
    width: 100%;
    height: auto;
    display: block;
}

/* ── Error code badge ── */
.error-code-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #fff7ed;
    border: 1.5px solid #fed7aa;
    color: #ea580c;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    padding: 5px 14px;
    border-radius: 50px;
    margin-bottom: 14px;
}
.error-code-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #f97316;
    animation: pulse-dot 1.8s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.5; transform: scale(0.75); }
}

/* ── Title ── */
.error-title {
    font-size: 22px;
    font-weight: 900;
    color: #0f172a;
    letter-spacing: -0.02em;
    margin: 0 0 10px;
    line-height: 1.2;
}

/* ── Message ── */
.error-message {
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    margin: 0 0 32px;
    max-width: 420px;
    line-height: 1.65;
}

/* ── Buttons ── */
.error-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: center;
}

.error-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-decoration: none;
    transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    cursor: pointer;
    white-space: nowrap;
}
.error-btn:hover {
    transform: translateY(-2px);
}

.error-btn--primary {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    color: #ffffff;
    border: none;
    box-shadow: 0 6px 22px -4px rgba(249, 115, 22, 0.55);
}
.error-btn--primary:hover {
    box-shadow: 0 10px 28px -4px rgba(249, 115, 22, 0.65);
}

.error-btn--outline {
    background: transparent;
    color: #f97316;
    border: 2px solid #f97316;
}
.error-btn--outline:hover {
    background: #fff7ed;
}

/* ── Responsive ── */
@media (max-width: 560px) {
    .error-card {
        padding: 32px 20px 28px;
        border-radius: 20px;
    }
    .error-title {
        font-size: 18px;
    }
    .error-actions {
        flex-direction: column;
        width: 100%;
    }
    .error-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
