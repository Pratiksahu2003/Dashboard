<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import SuInput from '@/Components/SuInput.vue';
import SuButton from '@/Components/SuButton.vue';
import SuRoleCard from '@/Components/SuRoleCard.vue';
import api, { sanitizeString } from '@/api';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';

const step = ref(1);
const loading = ref(false);
const fieldErrors = ref({});
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const { setSession, setRegistrationChargesContext } = useAuth();
const { error: showError } = useAlerts();

const roles = [
    { value: 'teacher', label: 'Teacher', desc: 'Manage classes and student kits.' },
    { value: 'institute', label: 'Institute', desc: 'Centralized management & procurement.' },
    { value: 'student', label: 'Student', desc: 'Access learning resources and kits.' },
    { value: 'ngo', label: 'University / NGO', desc: 'Administer departments, programs, or NGO learning operations.' }
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
        const payload = {
            ...form,
            first_name: sanitizeString(form.first_name.trim()),
            last_name: sanitizeString(form.last_name.trim()),
            email: form.email.trim().toLowerCase(),
            phone: form.phone.trim().replace(/[^\d+\-\s()]/g, ''),
            referral_code: sanitizeString(form.referral_code.trim()),
        };
        const response = await api.post('/auth/register', payload);
        if (response.success && response.data?.token) {
            setSession({ token: response.data.token, user: response.data.user });
            if (response.data.registration_charges && typeof response.data.registration_charges === 'object') {
                setRegistrationChargesContext(response.data.registration_charges);
            }
            const next = response.data.next_step;
            if (next === 'email_verification' || !response.data.user?.email_verified_at) {
                router.visit(route('auth.verify.email'), { replace: true });
            } else {
                router.visit(route('dashboard'), { replace: true });
            }
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
                            class="block w-full px-4 py-3 bg-white/80 border rounded-2xl outline-none transition-all duration-200 shadow-[0_1px_0_rgba(15,23,42,0.04)] border-slate-200 focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10 hover:border-slate-300 pr-12"
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
                            class="block w-full px-4 py-3 bg-white/80 border rounded-2xl outline-none transition-all duration-200 shadow-[0_1px_0_rgba(15,23,42,0.04)] border-slate-200 focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10 hover:border-slate-300 pr-12"
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
