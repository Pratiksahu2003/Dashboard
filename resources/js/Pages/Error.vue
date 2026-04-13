<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: Number, default: 404 },
});

const errorConfig = computed(() => {
    const configs = {
        404: {
            code: '404',
            title: 'Page Not Found',
            message: "Oops! The page you're looking for doesn't exist or has been moved.",
        },
        403: {
            code: '403',
            title: 'Access Forbidden',
            message: "You don't have permission to access this page.",
        },
        500: {
            code: '500',
            title: 'Server Error',
            message: 'Something went wrong on our end. Please try again later.',
        },
        503: {
            code: '503',
            title: 'Service Unavailable',
            message: 'We are down for maintenance. Please check back soon.',
        },
        419: {
            code: '419',
            title: 'Session Expired',
            message: 'Your session has expired. Please log in again to continue.',
        },
        429: {
            code: '429',
            title: 'Too Many Requests',
            message: 'You have made too many requests. Please slow down and try again.',
        },
    };
    return configs[props.status] ?? {
        code: String(props.status),
        title: 'Unexpected Error',
        message: 'An unexpected error occurred. Please try again.',
    };
});
</script>

<template>
    <Head :title="`${errorConfig.code} — ${errorConfig.title}`" />

    <div class="error-page-root">
        <!-- Decorative dots background -->
        <div class="error-dots-bg" aria-hidden="true"></div>

        <!-- Orange border frame -->
        <div class="error-frame">
            <div class="error-inner">

                <!-- SVG Illustration -->
                <div class="error-illustration" aria-hidden="true">
                    <svg viewBox="0 0 520 280" fill="none" xmlns="http://www.w3.org/2000/svg" class="error-svg">
                        <!-- Soft cloud/blob shapes -->
                        <ellipse cx="260" cy="200" rx="200" ry="60" fill="#EEF2FF" opacity="0.7"/>
                        <ellipse cx="180" cy="190" rx="120" ry="45" fill="#E0E7FF" opacity="0.5"/>

                        <!-- Hanging strings -->
                        <line x1="130" y1="0" x2="130" y2="80" stroke="#CBD5E1" stroke-width="2"/>
                        <line x1="260" y1="0" x2="260" y2="60" stroke="#CBD5E1" stroke-width="2"/>
                        <line x1="390" y1="0" x2="390" y2="80" stroke="#CBD5E1" stroke-width="2"/>

                        <!-- "4" left — 3D purple/indigo block -->
                        <g>
                            <!-- Front face -->
                            <path d="M80 80 L80 180 L100 180 L100 150 L155 150 L155 180 L175 180 L175 150 L175 80 L155 80 L155 130 L100 130 L100 80 Z" fill="#6366F1"/>
                            <!-- Top face -->
                            <path d="M80 80 L95 65 L175 65 L175 80 Z" fill="#818CF8"/>
                            <!-- Right face -->
                            <path d="M175 65 L190 50 L190 165 L175 180 Z" fill="#4F46E5"/>
                            <!-- Vertical bar top face -->
                            <path d="M155 65 L170 50 L190 50 L175 65 Z" fill="#818CF8"/>
                        </g>

                        <!-- "0" center — orange/coral 3D ring -->
                        <g>
                            <!-- Outer ellipse body -->
                            <path d="M210 75 Q210 55 260 55 Q310 55 310 75 L310 175 Q310 195 260 195 Q210 195 210 175 Z" fill="#F97316"/>
                            <!-- Inner cutout -->
                            <path d="M230 95 Q230 80 260 80 Q290 80 290 95 L290 155 Q290 170 260 170 Q230 170 230 155 Z" fill="white"/>
                            <!-- Top 3D face -->
                            <path d="M210 75 Q210 55 260 55 Q310 55 310 75 Q310 95 260 95 Q210 95 210 75 Z" fill="#FB923C"/>
                            <!-- Inner top -->
                            <path d="M230 95 Q230 80 260 80 Q290 80 290 95 Q290 110 260 110 Q230 110 230 95 Z" fill="#FED7AA"/>
                            <!-- Hanging pin -->
                            <rect x="255" y="195" width="10" height="25" rx="3" fill="#F97316"/>
                        </g>

                        <!-- "4" right — blue/slate 3D block -->
                        <g>
                            <!-- Front face -->
                            <path d="M345 80 L345 180 L365 180 L365 150 L420 150 L420 180 L440 180 L440 150 L440 80 L420 80 L420 130 L365 130 L365 80 Z" fill="#64748B"/>
                            <!-- Top face -->
                            <path d="M345 80 L360 65 L440 65 L440 80 Z" fill="#94A3B8"/>
                            <!-- Right face -->
                            <path d="M440 65 L455 50 L455 165 L440 180 Z" fill="#475569"/>
                            <!-- Vertical bar top face -->
                            <path d="M420 65 L435 50 L455 50 L440 65 Z" fill="#94A3B8"/>
                            <!-- Pencil/tool detail -->
                            <rect x="415" y="155" width="8" height="30" rx="2" fill="#F59E0B"/>
                            <polygon points="415,185 423,185 419,198" fill="#FCD34D"/>
                        </g>

                        <!-- Person on left "4" — sitting, reading -->
                        <g transform="translate(75, 45)">
                            <!-- Body -->
                            <ellipse cx="18" cy="28" rx="9" ry="11" fill="#1E293B"/>
                            <!-- Head -->
                            <circle cx="18" cy="14" r="8" fill="#FBBF24"/>
                            <!-- Legs dangling -->
                            <line x1="13" y1="38" x2="10" y2="55" stroke="#1E293B" stroke-width="4" stroke-linecap="round"/>
                            <line x1="23" y1="38" x2="26" y2="55" stroke="#1E293B" stroke-width="4" stroke-linecap="round"/>
                            <!-- Book/tablet -->
                            <rect x="5" y="22" width="12" height="9" rx="1" fill="#3B82F6"/>
                        </g>

                        <!-- Person on "0" — standing, arms up (celebrating) -->
                        <g transform="translate(240, 30)">
                            <!-- Body -->
                            <ellipse cx="20" cy="30" rx="9" ry="12" fill="#F97316"/>
                            <!-- Head -->
                            <circle cx="20" cy="15" r="8" fill="#FBBF24"/>
                            <!-- Arms up -->
                            <line x1="11" y1="26" x2="2" y2="14" stroke="#F97316" stroke-width="4" stroke-linecap="round"/>
                            <line x1="29" y1="26" x2="38" y2="14" stroke="#F97316" stroke-width="4" stroke-linecap="round"/>
                            <!-- Legs -->
                            <line x1="15" y1="41" x2="12" y2="56" stroke="#F97316" stroke-width="4" stroke-linecap="round"/>
                            <line x1="25" y1="41" x2="28" y2="56" stroke="#F97316" stroke-width="4" stroke-linecap="round"/>
                        </g>

                        <!-- Person on right "4" — sitting on top -->
                        <g transform="translate(375, 30)">
                            <!-- Body -->
                            <ellipse cx="18" cy="28" rx="9" ry="11" fill="#475569"/>
                            <!-- Head -->
                            <circle cx="18" cy="14" r="8" fill="#FBBF24"/>
                            <!-- Legs dangling -->
                            <line x1="13" y1="38" x2="9" y2="55" stroke="#475569" stroke-width="4" stroke-linecap="round"/>
                            <line x1="23" y1="38" x2="27" y2="55" stroke="#475569" stroke-width="4" stroke-linecap="round"/>
                            <!-- Tie -->
                            <polygon points="18,22 15,35 18,32 21,35" fill="#F97316"/>
                        </g>

                        <!-- Decorative dots -->
                        <circle cx="200" cy="210" r="6" fill="#F97316" opacity="0.7"/>
                        <circle cx="320" cy="220" r="4" fill="#F97316" opacity="0.5"/>
                        <circle cx="160" cy="60" r="5" fill="#6366F1" opacity="0.5"/>
                        <circle cx="370" cy="55" r="4" fill="#64748B" opacity="0.4"/>
                        <circle cx="460" cy="120" r="5" fill="#F97316" opacity="0.3"/>
                        <circle cx="60" cy="150" r="4" fill="#6366F1" opacity="0.3"/>
                    </svg>
                </div>

                <!-- Error text -->
                <p class="error-label">OOPS! {{ errorConfig.title.toUpperCase() }}</p>
                <p class="error-message">{{ errorConfig.message }}</p>

                <!-- Action buttons -->
                <div class="error-actions">
                    <a
                        href="https://www.suganta.com"
                        class="error-btn error-btn--primary"
                    >
                        Back to Home
                    </a>
                    <a
                        :href="route('login')"
                        class="error-btn error-btn--secondary"
                    >
                        Back to Login
                    </a>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
.error-page-root {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8fafc;
    padding: 24px;
    position: relative;
    overflow: hidden;
}

/* Subtle dot pattern background */
.error-dots-bg {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
    background-size: 28px 28px;
    opacity: 0.45;
    pointer-events: none;
}

/* Orange border card frame */
.error-frame {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 780px;
    border: 3px solid #f97316;
    border-radius: 24px;
    background: #ffffff;
    box-shadow: 0 20px 60px -12px rgba(249, 115, 22, 0.15), 0 8px 24px -4px rgba(0, 0, 0, 0.06);
    padding: 4px;
}

.error-inner {
    border-radius: 20px;
    padding: 48px 40px 44px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0;
}

/* SVG illustration */
.error-illustration {
    width: 100%;
    max-width: 520px;
    margin-bottom: 28px;
}

.error-svg {
    width: 100%;
    height: auto;
    display: block;
}

/* "OOPS! PAGE NOT FOUND" label */
.error-label {
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.22em;
    color: #64748b;
    margin: 0 0 10px;
    text-transform: uppercase;
}

.error-message {
    font-size: 14px;
    font-weight: 600;
    color: #94a3b8;
    margin: 0 0 32px;
    max-width: 400px;
    line-height: 1.6;
}

/* Buttons row */
.error-actions {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    justify-content: center;
}

.error-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 32px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    text-decoration: none;
    transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
    cursor: pointer;
}

.error-btn:hover {
    transform: translateY(-2px);
    opacity: 0.92;
}

.error-btn--primary {
    background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
    color: #ffffff;
    box-shadow: 0 6px 20px -4px rgba(249, 115, 22, 0.5);
}

.error-btn--secondary {
    background: transparent;
    color: #f97316;
    border: 2px solid #f97316;
    box-shadow: none;
}

.error-btn--secondary:hover {
    background: #fff7ed;
}

@media (max-width: 600px) {
    .error-inner {
        padding: 36px 24px 32px;
    }

    .error-actions {
        flex-direction: column;
        width: 100%;
    }

    .error-btn {
        width: 100%;
    }
}
</style>
