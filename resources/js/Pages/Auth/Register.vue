<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuInput from '@/Components/SuInput.vue';
import SuButton from '@/Components/SuButton.vue';
import SuRoleCard from '@/Components/SuRoleCard.vue';
import api, { sanitizeString, ensureCsrf } from '@/api';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import { describeFirebaseAuthError, hasFirebaseAuthConfig, signInWithFirebaseProvider } from '@/services/firebaseAuth';
import {
    deviceName,
    extractAuthToken,
    needsOnboarding,
    needsPayment,
    persistAuthToken,
    persistPaymentGate,
} from '@/services/authFlow';
import { socialPost } from '@/services/socialApi';

const step = ref(1);
const loading = ref(false);
const socialLoading = ref('');
const fieldErrors = ref({});
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const { setRegistrationChargesContext, clearSession } = useAuth();
const { error: showError, success: showSuccess } = useAlerts();
const firebaseConfigured = hasFirebaseAuthConfig();
const socialProviders = [
    { id: 'google', label: 'Google' },
    { id: 'facebook', label: 'Facebook' },
];

const roles = [
    { value: 'teacher', label: 'Teacher', desc: 'Manage classes and student kits.' },
    { value: 'institute', label: 'Institute', desc: 'Centralized management & procurement.' },
    { value: 'student', label: 'Student', desc: 'Access learning resources and kits.' },
    { value: 'university', label: 'University', desc: 'Administer departments, programs, or university learning operations.' }
];

const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    role: '',
    password: '',
    password_confirmation: '',
    referral_code: '',
    device_name: 'Web Browser'
});

const passwordStrength = computed(() => {
    const p = form.password;
    if (!p) return { score: 0, label: '' };
    let score = 0;
    if (p.length >= 8) score++;
    if (p.length >= 12) score++;
    if (/[A-Z]/.test(p)) score++;
    if (/[a-z]/.test(p)) score++;
    if (/\d/.test(p)) score++;
    if (/[^A-Za-z0-9]/.test(p)) score++;
    if (score <= 2) return { score, label: 'Weak', color: 'bg-red-500' };
    if (score <= 4) return { score, label: 'Medium', color: 'bg-yellow-500' };
    return { score, label: 'Strong', color: 'bg-green-500' };
});

const nextStep = () => {
    if (step.value === 1 && !form.role) return;
    step.value++;
};

const goToDashboard = token => {
    router.post(route('auth.sync-cache'), {
        token: token || null,
        redirect_to: null,
    }, {
        replace: true,
        preserveState: false,
        preserveScroll: false,
    });
};

const finishSocialAuthFlow = response => {
    const token = extractAuthToken(response);
    if (token) persistAuthToken(token);

    if (needsOnboarding(response)) {
        router.visit(route('auth.onboarding'), { replace: true });
        return;
    }

    if (needsPayment(response)) {
        persistPaymentGate(response);
        router.visit(route('auth.payment.required'), { replace: true });
        return;
    }

    goToDashboard(token);
};

const handleSocialRegister = async provider => {
    if (!firebaseConfigured) {
        showError('Firebase social sign up is not configured for this environment.');
        return;
    }

    socialLoading.value = provider;
    fieldErrors.value = {};
    try {
        const firebase = await signInWithFirebaseProvider(provider);
        const response = await socialPost('/auth/social-login', {
            provider,
            token: firebase.token,
            id_token: firebase.token,
            firebase_token: firebase.token,
            device_name: deviceName(),
        }, { skipAuthRedirect: true });

        if (response?.success === false && !needsPayment(response)) {
            throw response;
        }

        finishSocialAuthFlow(response);
    } catch (err) {
        fieldErrors.value = err?.errors || {};
        showError(describeFirebaseAuthError(err, provider));
    } finally {
        socialLoading.value = '';
    }
};

const handleRegister = async () => {
    fieldErrors.value = {};

    if (form.password.length < 8) {
        showError('Password must be at least 8 characters.');
        return;
    }
    if (form.password !== form.password_confirmation) {
        showError('Password and confirm password must match.');
        return;
    }
    if (passwordStrength.value.score <= 2) {
        showError('Password is too weak. Add uppercase, numbers, or symbols.');
        return;
    }

    loading.value = true;

    try {
        await ensureCsrf();
    } catch {
        showError('Unable to establish a secure connection. Please check your network and try again.');
        loading.value = false;
        return;
    }

    try {
        const payload = {
            ...form,
            first_name: sanitizeString(form.first_name.trim()),
            last_name: sanitizeString(form.last_name.trim()),
            email: form.email.trim().toLowerCase(),
            phone: form.phone.trim().replace(/[^\d+\-\s()]/g, ''),
            referral_code: sanitizeString(form.referral_code.trim()),
        };
        const response = await api.post('/auth/register', payload);
        if (response.success) {
            // Clear any existing session to ensure they log in fresh
            clearSession();
            
            // Show success message and redirect to login
             showSuccess('Registration Successful! Please login to access your dashboard.');
             
             setTimeout(() => {
                router.visit(route('login'), { replace: true });
            }, 2000);
        }
    } catch (err) {
        fieldErrors.value = err.errors || {};
        showError(err.message || 'Registration failed.');
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AuthLayout>
        <Head title="Create Account" />

        <template #title>{{ step === 1 ? 'Select a role' : 'Account details' }}</template>
        <template #subtitle>
            {{ step === 1 ? 'How do you plan to use SuGanta?' : 'Provide your information to join the platform.' }}
        </template>

        <div v-if="firebaseConfigured" class="mb-4 space-y-2.5">
            <div class="grid grid-cols-2 gap-2">
                <button
                    v-for="provider in socialProviders"
                    :key="provider.id"
                    type="button"
                    :class="[
                        'flex h-11 items-center justify-center gap-2 rounded-xl px-3 text-[13px] font-black leading-none shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60',
                        provider.id === 'google'
                            ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                            : 'border border-[#1877F2] bg-[#1877F2] text-white hover:bg-[#166FE5]',
                    ]"
                    :disabled="!!socialLoading || loading"
                    @click="handleSocialRegister(provider.id)"
                >
                    <svg v-if="provider.id === 'google'" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.5-.2-2.2H12v4.2h6.5c-.3 1.4-1.1 2.7-2.3 3.5v2.9h3.7c2.2-2 3.6-4.9 3.6-8.4z" />
                        <path fill="#34A853" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.7-2.9c-1 .7-2.4 1.1-4.2 1.1-3.1 0-5.7-2.1-6.6-4.9H1.6v3C3.5 21.3 7.4 24 12 24z" />
                        <path fill="#FBBC05" d="M5.4 14.4c-.2-.7-.4-1.5-.4-2.4s.1-1.6.4-2.4v-3H1.6C.6 8.2 0 10.1 0 12s.6 3.8 1.6 5.4l3.8-3z" />
                        <path fill="#EA4335" d="M12 4.7c1.8 0 3.3.6 4.6 1.8L20 3.1C17.9 1.2 15.2 0 12 0 7.4 0 3.5 2.7 1.6 6.6l3.8 3C6.3 6.8 8.9 4.7 12 4.7z" />
                    </svg>
                    <svg v-else class="h-4 w-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M24 12.1C24 5.4 18.6 0 12 0S0 5.4 0 12.1c0 6 4.4 11 10.1 11.9v-8.4h-3v-3.5h3V9.4c0-3 1.8-4.7 4.5-4.7 1.3 0 2.7.2 2.7.2v3h-1.5c-1.5 0-2 .9-2 1.9v2.3h3.4l-.5 3.5h-2.9V24c5.8-.9 10.2-5.9 10.2-11.9z" />
                    </svg>
                    <span>{{ socialLoading === provider.id ? 'Connecting...' : provider.label }}</span>
                </button>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <span class="h-px flex-1 bg-slate-200"></span>
                <span class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">or</span>
                <span class="h-px flex-1 bg-slate-200"></span>
            </div>
        </div>

        <!-- Step 1: Role Selection -->
        <div v-if="step === 1" class="space-y-4">
            <div class="grid grid-cols-1 gap-3">
                <SuRoleCard
                    v-for="r in roles"
                    :key="r.value"
                    :label="r.label"
                    :desc="r.desc"
                    :selected="form.role === r.value"
                    @click="form.role = r.value"
                />
            </div>
            <SuButton @click="nextStep" :disabled="!form.role" class="w-full mt-6">
                Next: Account Details
            </SuButton>
        </div>

        <!-- Step 2: Personal Details -->
        <form v-else @submit.prevent="handleRegister" class="space-y-4 animate-fade-in">
            <div class="grid grid-cols-2 gap-4">
                <SuInput v-model="form.first_name" label="First name" placeholder="John" :error="fieldErrors.first_name?.[0]" required autofocus />
                <SuInput v-model="form.last_name" label="Last name" placeholder="Doe" :error="fieldErrors.last_name?.[0]" required />
            </div>
            <SuInput v-model="form.email" label="Email Address" type="email" placeholder="john@example.com" :error="fieldErrors.email?.[0]" required />
            <SuInput v-model="form.phone" label="Phone Number (optional)" placeholder="+91 00000 00000" :error="fieldErrors.phone?.[0]" />
            <SuInput v-model="form.referral_code" label="Referral code (optional)" placeholder="REF123" :error="fieldErrors.referral_code?.[0]" />
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5 w-full">
                    <label class="block text-xs font-black text-slate-700 tracking-tight">Password</label>
                    <div class="relative">
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            placeholder="••••••••"
                            required
                            class="block w-full px-4 py-3 bg-white border rounded-2xl outline-none transition-all duration-200 shadow-[0_1px_0_rgba(15,23,42,0.04)] border-slate-200 focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10 hover:border-slate-300 pr-12"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-3 my-auto h-8 px-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors"
                        >
                            {{ showPassword ? 'Hide' : 'Show' }}
                        </button>
                    </div>
                    <p v-if="fieldErrors.password?.[0]" class="text-xs font-medium text-red-600 mt-1">{{ fieldErrors.password[0] }}</p>
                </div>

                <div class="space-y-1.5 w-full">
                    <label class="block text-xs font-black text-slate-700 tracking-tight">Confirm</label>
                    <div class="relative">
                        <input
                            v-model="form.password_confirmation"
                            :type="showPasswordConfirmation ? 'text' : 'password'"
                            placeholder="••••••••"
                            required
                            class="block w-full px-4 py-3 bg-white border rounded-2xl outline-none transition-all duration-200 shadow-[0_1px_0_rgba(15,23,42,0.04)] border-slate-200 focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10 hover:border-slate-300 pr-12"
                        />
                        <button
                            type="button"
                            @click="showPasswordConfirmation = !showPasswordConfirmation"
                            class="absolute inset-y-0 right-3 my-auto h-8 px-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors"
                        >
                            {{ showPasswordConfirmation ? 'Hide' : 'Show' }}
                        </button>
                    </div>
                    <p v-if="fieldErrors.password_confirmation?.[0]" class="text-xs font-medium text-red-600 mt-1">{{ fieldErrors.password_confirmation[0] }}</p>
                </div>
            </div>

            <div v-if="form.password" class="flex items-center gap-2">
                <div class="flex-1 h-1.5 rounded-full bg-slate-200 overflow-hidden">
                    <div
                        :class="[passwordStrength.color, 'h-full rounded-full transition-all duration-300']"
                        :style="{ width: `${Math.min((passwordStrength.score / 6) * 100, 100)}%` }"
                    />
                </div>
                <span class="text-[11px] font-bold text-slate-500">{{ passwordStrength.label }}</span>
            </div>

            <div class="pt-4 flex gap-3">
                <SuButton type="button" variant="secondary" @click="step = 1">Back</SuButton>
                <SuButton :loading="loading" class="flex-1">Create Account</SuButton>
            </div>
        </form>

        <template #footer>
            <div class="space-y-3">
                <p>Already have an account? <Link :href="route('login')" class="text-blue-600 font-bold hover:underline">Sign in</Link></p>
                <p class="text-xs border-t border-slate-100 pt-3 text-gray-500 font-medium">Questions? <Link :href="route('contact')" class="text-blue-600 font-bold hover:underline">Contact Support</Link></p>
            </div>
        </template>
    </AuthLayout>
</template>

<style scoped>
@keyframes fade-in { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: translateX(0); } }
.animate-fade-in { animation: fade-in 0.3s ease-out forwards; }
@keyframes fade-in-right {
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
}
.animate-fade-in-right { animation: fade-in-right 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}
.animate-shake { animation: shake 0.4s ease-in-out; }
</style>
