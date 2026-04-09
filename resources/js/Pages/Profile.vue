<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { QuillEditor } from '@vueup/vue-quill';
import { useAuth } from '@/composables/useAuth';
import { useAlerts } from '@/composables/useAlerts';
import api from '@/api';

const { requireAuth, getUser } = useAuth();
const {
    success: alertSuccess,
    error: alertError,
    confirmDanger,
} = useAlerts();
const authUser = ref(null);

const profileLoading = ref(false);
const profileOptionsLoading = ref(false);
const profileCompletionLoading = ref(false);
const locationStatus = ref('');
const isLocating = ref(false);
const profileImageUrl = ref('');
const avatarPreviewUrl = ref('');
const activeProfileTab = ref('basic');
const profileCompletion = ref(null);
const sectionSaving = ref({
    basic: false,
    location: false,
    social: false,
    teaching: false,
    student: false,
    institute: false,
    avatar: false,
    password: false,
    delete_account: false,
});
const optionMap = ref({});
const subjects = ref([]);
const subjectsLoading = ref(false);
const subjectSearch = ref('');
const profileForm = ref({
    basic: {
        first_name: '',
        last_name: '',
        display_name: '',
        email: '',
        bio: '',
        date_of_birth: '',
        gender_id: null,
        nationality: '',
        phone_primary: '',
        phone_secondary: '',
        whatsapp: '',
        website: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
    },
    location: {
        address_line_1: '',
        address_line_2: '',
        area: '',
        city: '',
        state: '',
        pincode: '',
        country_id: null,
        latitude: '',
        longitude: '',
    },
    social: {
        facebook_url: '',
        twitter_url: '',
        instagram_url: '',
        linkedin_url: '',
        youtube_url: '',
        tiktok_url: '',
        telegram_username: '',
        discord_username: '',
        github_url: '',
        portfolio_url: '',
        blog_url: '',
    },
    teaching: {
        highest_qualification_id: null,
        field_of_study_id: null,
        teaching_experience_years_id: null,
        highest_qualification: '',
        institution_name: '',
        field_of_study: '',
        graduation_year: '',
        teaching_experience_years: '',
        hourly_rate_id: null,
        monthly_rate_id: null,
        travel_radius_km_id: null,
        teaching_mode_id: null,
        availability_status_id: null,
        teaching_philosophy: '',
        subjects_taught_ids: [],
    },
    student: {
        current_class_id: null,
        current_school: '',
        board_id: null,
        stream_id: null,
        parent_name: '',
        parent_phone: '',
        parent_email: '',
        budget_min: '',
        budget_max: '',
        learning_challenges: '',
    },
    institute: {
        institute_name: '',
        institute_type_id: null,
        institute_category_id: null,
        affiliation_number: '',
        registration_number: '',
        establishment_year_id: null,
        principal_name: '',
        principal_phone: '',
        principal_email: '',
        total_students_id: null,
        total_teachers_id: null,
        total_branches: '',
        institute_description: '',
    },
    avatar: {
        file: null,
    },
    password: {
        current_password: '',
        password: '',
        password_confirmation: '',
    },
    delete_account: {
        password: '',
        confirmation: '',
        reason: '',
    },
});

const profileTabs = [
    { key: 'basic', label: 'Basic' },
    { key: 'location', label: 'Location' },
    { key: 'social', label: 'Social' },
    { key: 'teaching', label: 'Teaching' },
    { key: 'student', label: 'Student' },
    { key: 'institute', label: 'Institute' },
    { key: 'avatar', label: 'Avatar' },
    { key: 'password', label: 'Password' },
    { key: 'delete_account', label: 'Delete Account' },
];
const normalizedRole = computed(() => String(authUser.value?.role || '').trim().toLowerCase());
const visibleProfileTabs = computed(() => {
    const commonTabs = ['basic', 'location', 'social', 'avatar', 'password', 'delete_account'];
    if (normalizedRole.value === 'student') return [...commonTabs, 'student'];
    if (normalizedRole.value === 'teacher') return [...commonTabs, 'teaching'];
    if (['institute', 'institutes', 'university', 'universities'].includes(normalizedRole.value)) {
        return [...commonTabs, 'institute'];
    }
    return profileTabs.map(tab => tab.key);
});
const filteredProfileTabs = computed(() => profileTabs.filter(tab => visibleProfileTabs.value.includes(tab.key)));
const quillToolbar = [
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link'],
    ['clean'],
];

const activeProfileFields = computed(() => profileForm.value[activeProfileTab.value] || {});
const completionPercent = computed(() => Number(profileCompletion.value?.percentage ?? 0));
const completionStatus = computed(() => profileCompletion.value?.status || 'Not Started');
const filteredSubjects = computed(() => subjects.value);

const showSuccess = message => alertSuccess(message);
const showError = message => alertError(message);

const optionEntries = key => {
    const values = optionMap.value?.[key];
    if (!values || typeof values !== 'object') return [];
    return Object.entries(values).map(([id, label]) => ({ id: Number(id), label }));
};

const mergeProfileSection = (sectionName, payload) => {
    const current = profileForm.value[sectionName];
    if (!current || !payload || typeof payload !== 'object') return;
    Object.keys(current).forEach(key => {
        if (key === 'subjects_taught_ids') return;
        const nextValue = payload[key];
        if (nextValue === null || typeof nextValue === 'undefined') return;
        current[key] = nextValue;
    });
    if (sectionName === 'teaching') {
        const subjects = Array.isArray(payload.subjects_taught) ? payload.subjects_taught : [];
        current.subjects_taught_ids = subjects
            .map(item => Number(item))
            .filter(item => Number.isInteger(item) && item > 0);
    }
};

const sanitizeRichHtml = html => {
    const raw = String(html || '');
    if (!raw.trim()) return '';

    const parser = new DOMParser();
    const doc = parser.parseFromString(raw, 'text/html');

    const allowedTags = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'UL', 'OL', 'LI', 'A']);
    const allowedAttrs = {
        A: new Set(['href', 'target', 'rel']),
    };

    const cleanNode = node => {
        const children = Array.from(node.childNodes);
        children.forEach(child => {
            if (child.nodeType === Node.ELEMENT_NODE) {
                const tag = child.tagName.toUpperCase();

                if (!allowedTags.has(tag)) {
                    const fragment = document.createDocumentFragment();
                    while (child.firstChild) fragment.appendChild(child.firstChild);
                    child.replaceWith(fragment);
                    return;
                }

                Array.from(child.attributes).forEach(attr => {
                    const attrName = attr.name.toLowerCase();
                    const allowedForTag = allowedAttrs[tag] || new Set();
                    if (!allowedForTag.has(attrName)) {
                        child.removeAttribute(attr.name);
                    }
                });

                if (tag === 'A') {
                    const href = child.getAttribute('href') || '';
                    const safeHref = /^(https?:|mailto:|tel:|#)/i.test(href);
                    if (!safeHref) {
                        child.removeAttribute('href');
                    } else {
                        child.setAttribute('target', '_blank');
                        child.setAttribute('rel', 'noopener noreferrer');
                    }
                }

                cleanNode(child);
            } else if (child.nodeType === Node.COMMENT_NODE) {
                child.remove();
            }
        });
    };

    cleanNode(doc.body);
    return doc.body.innerHTML.trim();
};

let subjectSearchTimer = null;

const fetchSubjects = async (search = '') => {
    subjectsLoading.value = true;
    try {
        const response = await api.get('/subjects', {
            params: search ? { search } : undefined,
        });
        subjects.value = Array.isArray(response?.data) ? response.data : [];
    } catch {
        subjects.value = [];
    } finally {
        subjectsLoading.value = false;
    }
};

const syncTeachingSelectionsFromValues = () => {
    const teaching = profileForm.value.teaching;
    const qualificationOptions = optionMap.value?.highest_qualification || {};
    const studyOptions = optionMap.value?.field_of_study || {};

    if (teaching.highest_qualification && !teaching.highest_qualification_id) {
        const match = Object.entries(qualificationOptions).find(([, label]) => String(label).toLowerCase() === String(teaching.highest_qualification).toLowerCase());
        if (match) teaching.highest_qualification_id = Number(match[0]);
    }

    if (teaching.field_of_study && !teaching.field_of_study_id) {
        const match = Object.entries(studyOptions).find(([, label]) => String(label).toLowerCase() === String(teaching.field_of_study).toLowerCase());
        if (match) teaching.field_of_study_id = Number(match[0]);
    }

    if (teaching.teaching_experience_years !== '' && teaching.teaching_experience_years !== null && !teaching.teaching_experience_years_id) {
        const numeric = Number(teaching.teaching_experience_years);
        teaching.teaching_experience_years_id = Number.isFinite(numeric) ? numeric : null;
    }
};

const fetchProfileAutofill = async () => {
    profileLoading.value = true;
    try {
        const response = await api.get('/profile/form-autofill');
        const data = response?.data || {};
        const formData = data.form_data || {};
        profileImageUrl.value = data.profile_image_url || '';
        ['basic', 'location', 'social', 'teaching', 'student', 'institute'].forEach(section => {
            mergeProfileSection(section, formData?.[section] || {});
        });
        syncTeachingSelectionsFromValues();
    } catch (error) {
        showError(error?.message || 'Unable to load profile form data.');
    } finally {
        profileLoading.value = false;
    }
};

const fetchProfileOptions = async () => {
    profileOptionsLoading.value = true;
    try {
        const keys = [
            'gender',
            'country',
            'current_class',
            'board',
            'stream',
            'institute_type',
            'institute_category',
            'establishment_year_range',
            'total_students_range',
            'total_teachers_range',
            'hourly_rate_range',
            'monthly_rate_range',
            'travel_radius_km',
            'teaching_mode',
            'availability_status',
            'highest_qualification',
            'field_of_study',
            'teaching_experience_years',
        ].join(',');
        const response = await api.get('/options', { params: { key: keys } });
        optionMap.value = response?.data || {};
        syncTeachingSelectionsFromValues();
    } catch {
        optionMap.value = {};
    } finally {
        profileOptionsLoading.value = false;
    }
};

const fetchProfileCompletion = async () => {
    profileCompletionLoading.value = true;
    try {
        const response = await api.get('/profile/completion');
        profileCompletion.value = response?.data || null;
    } catch {
        profileCompletion.value = null;
    } finally {
        profileCompletionLoading.value = false;
    }
};

const getCurrentLocation = () => {
    if (!navigator.geolocation) {
        showError('Geolocation is not supported by your browser.');
        return;
    }

    isLocating.value = true;
    locationStatus.value = 'Requesting your current location...';

    navigator.geolocation.getCurrentPosition(async position => {
        try {
            const lat = Number(position.coords.latitude.toFixed(6));
            const lon = Number(position.coords.longitude.toFixed(6));
            const locationForm = profileForm.value.location;

            locationForm.latitude = lat;
            locationForm.longitude = lon;

            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}`);
            const data = await response.json();
            const address = data?.address || {};

            locationForm.address_line_1 = data?.display_name || locationForm.address_line_1;
            locationForm.area = address.suburb || address.neighbourhood || address.hamlet || locationForm.area;
            locationForm.city = address.city || address.town || address.village || address.county || locationForm.city;
            locationForm.state = address.state || locationForm.state;
            locationForm.pincode = address.postcode || locationForm.pincode;

            const countryName = String(address.country || '').trim().toLowerCase();
            if (countryName) {
                const countryMatch = optionEntries('country').find(item => String(item.label || '').trim().toLowerCase() === countryName);
                if (countryMatch) locationForm.country_id = countryMatch.id;
            }

            locationStatus.value = 'Location fetched and form auto-filled successfully.';
            showSuccess('Location fetched and form auto-filled successfully.');
        } catch {
            locationStatus.value = 'Coordinates fetched. Some address fields could not be auto-filled.';
            showError('Coordinates fetched. Some address fields could not be auto-filled.');
        } finally {
            isLocating.value = false;
        }
    }, error => {
        isLocating.value = false;
        if (error?.code === 1) {
            showError('Location permission denied. Please allow location access.');
        } else if (error?.code === 2) {
            showError('Location unavailable. Try again in an open area.');
        } else if (error?.code === 3) {
            showError('Location request timed out. Please try again.');
        } else {
            showError('Unable to fetch current location.');
        }
        locationStatus.value = '';
    }, {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 0,
    });
};

const onAvatarFileChange = event => {
    const file = event?.target?.files?.[0] || null;
    profileForm.value.avatar.file = file;
    if (avatarPreviewUrl.value) URL.revokeObjectURL(avatarPreviewUrl.value);
    avatarPreviewUrl.value = file ? URL.createObjectURL(file) : '';
};

const uploadAvatar = async () => {
    const file = profileForm.value.avatar.file;
    if (!file) {
        showError('Please select an image file first.');
        return;
    }

    sectionSaving.value.avatar = true;
    try {
        const formData = new FormData();
        formData.append('avatar', file);
        const response = await api.post('/profile/avatar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        profileImageUrl.value = response?.data?.profile_image_url || profileImageUrl.value;
        profileForm.value.avatar.file = null;
        if (avatarPreviewUrl.value) {
            URL.revokeObjectURL(avatarPreviewUrl.value);
            avatarPreviewUrl.value = '';
        }
        showSuccess('Profile picture updated successfully.');
    } catch (error) {
        showError(error?.message || 'Unable to update profile picture.');
    } finally {
        sectionSaving.value.avatar = false;
    }
};

const changePassword = async () => {
    sectionSaving.value.password = true;
    try {
        const payload = { ...profileForm.value.password };
        await api.put('/profile/password', payload);
        showSuccess('Password changed successfully.');
        profileForm.value.password.current_password = '';
        profileForm.value.password.password = '';
        profileForm.value.password.password_confirmation = '';
    } catch (error) {
        showError(error?.message || 'Unable to change password.');
    } finally {
        sectionSaving.value.password = false;
    }
};

const deleteAccount = async () => {
    const confirmed = await confirmDanger({
        title: 'Delete account permanently?',
        text: 'This action cannot be undone.',
        confirmText: 'Yes, delete',
        cancelText: 'Cancel',
    });
    if (!confirmed) return;

    sectionSaving.value.delete_account = true;
    try {
        const payload = { ...profileForm.value.delete_account };
        await api.delete('/profile', { data: payload });
        showSuccess('Your account has been permanently deleted.');
    } catch (error) {
        showError(error?.message || 'Unable to delete account.');
    } finally {
        sectionSaving.value.delete_account = false;
    }
};

const saveProfileSection = async section => {
    sectionSaving.value[section] = true;
    try {
        const payload = { ...(profileForm.value[section] || {}) };
        if (section === 'basic') {
            payload.bio = sanitizeRichHtml(payload.bio);
        }
        if (section === 'teaching') {
            payload.highest_qualification = optionMap.value?.highest_qualification?.[payload.highest_qualification_id] || payload.highest_qualification || null;
            payload.field_of_study = optionMap.value?.field_of_study?.[payload.field_of_study_id] || payload.field_of_study || null;
            payload.teaching_experience_years = payload.teaching_experience_years_id ?? payload.teaching_experience_years ?? null;
            payload.teaching_philosophy = sanitizeRichHtml(payload.teaching_philosophy);
            payload.subjects_taught = Array.isArray(payload.subjects_taught_ids) ? payload.subjects_taught_ids : [];
            delete payload.highest_qualification_id;
            delete payload.field_of_study_id;
            delete payload.teaching_experience_years_id;
            delete payload.subjects_taught_ids;
        }
        if (section === 'student') {
            payload.learning_challenges = sanitizeRichHtml(payload.learning_challenges);
        }
        if (section === 'institute') {
            payload.institute_description = sanitizeRichHtml(payload.institute_description);
        }
        const endpointMap = {
            basic: '/profile',
            location: '/profile/location',
            social: '/profile/social',
            teaching: '/profile/teaching',
            student: '/profile/student',
            institute: '/profile/institute',
        };
        await api.put(endpointMap[section], payload);
        showSuccess(`${section.charAt(0).toUpperCase()}${section.slice(1)} section saved successfully.`);
        await fetchProfileCompletion();
    } catch (error) {
        showError(error?.message || `Unable to save ${section} section.`);
    } finally {
        sectionSaving.value[section] = false;
    }
};

watch(visibleProfileTabs, nextTabs => {
    if (!nextTabs.includes(activeProfileTab.value)) {
        activeProfileTab.value = nextTabs[0] || 'basic';
    }
});

watch(subjectSearch, value => {
    if (subjectSearchTimer) clearTimeout(subjectSearchTimer);
    subjectSearchTimer = setTimeout(() => {
        fetchSubjects(String(value || '').trim());
    }, 300);
});

onMounted(() => {
    if (!requireAuth()) return;
    authUser.value = getUser();
    fetchProfileAutofill();
    fetchProfileOptions();
    fetchSubjects();
    fetchProfileCompletion();
});
</script>

<template>
    <Head title="Profile" />
    <AppLayout>
        <template #breadcrumb>Profile</template>

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between mb-4">
                <div>
                    <h1 class="text-xl font-black text-slate-900">Profile Management</h1>
                    <p class="text-xs font-semibold text-slate-500">Manage all profile sections from one structured page.</p>
                </div>
                <div class="w-full lg:w-80 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl overflow-hidden bg-slate-200 flex items-center justify-center text-xs font-black text-slate-700">
                            <img v-if="profileImageUrl" :src="profileImageUrl" alt="Profile" class="h-full w-full object-cover" />
                            <span v-else>U</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-black text-slate-800 truncate">Completion: {{ completionPercent }}%</div>
                            <div class="text-[11px] font-semibold text-slate-500">{{ profileCompletionLoading ? 'Checking...' : completionStatus }}</div>
                        </div>
                    </div>
                    <div class="mt-3 h-2 rounded-full bg-slate-200 overflow-hidden">
                        <div class="h-full bg-blue-600 transition-all duration-500" :style="{ width: `${Math.min(100, Math.max(0, completionPercent))}%` }"></div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mb-4">
                <button
                    v-for="tab in filteredProfileTabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-lg border px-3 py-1.5 text-xs font-black transition"
                    :class="activeProfileTab === tab.key ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-50'"
                    @click="activeProfileTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div v-if="profileLoading" class="space-y-3">
                <div v-for="i in 3" :key="`profile-loading-${i}`" class="h-14 rounded-xl bg-slate-100 animate-pulse"></div>
            </div>

            <div v-else class="space-y-4">
                <div v-if="activeProfileTab === 'basic'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="field-wrap"><span class="field-title">First Name *</span><input v-model="activeProfileFields.first_name" type="text" placeholder="Enter first name" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Last Name</span><input v-model="activeProfileFields.last_name" type="text" placeholder="Enter last name" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Display Name</span><input v-model="activeProfileFields.display_name" type="text" placeholder="Enter display name" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Email *</span><input v-model="activeProfileFields.email" type="email" placeholder="Enter email" class="field-input field-readonly" readonly /></label>
                    <label class="field-wrap"><span class="field-title">Date of Birth</span><input v-model="activeProfileFields.date_of_birth" type="date" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Gender</span><select v-model.number="activeProfileFields.gender_id" class="field-input bg-white"><option :value="null">Select gender</option><option v-for="item in optionEntries('gender')" :key="`gender-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Nationality</span><input v-model="activeProfileFields.nationality" type="text" placeholder="Enter nationality" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Primary Phone</span><input v-model="activeProfileFields.phone_primary" type="text" placeholder="Enter primary phone" class="field-input field-readonly" readonly /></label>
                    <label class="field-wrap"><span class="field-title">Secondary Phone</span><input v-model="activeProfileFields.phone_secondary" type="text" placeholder="Enter secondary phone" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">WhatsApp</span><input v-model="activeProfileFields.whatsapp" type="text" placeholder="Enter WhatsApp number" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Website</span><input v-model="activeProfileFields.website" type="url" placeholder="Enter website URL" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Emergency Contact Name</span><input v-model="activeProfileFields.emergency_contact_name" type="text" placeholder="Enter emergency contact name" class="field-input" /></label>
                    <label class="field-wrap md:col-span-2"><span class="field-title">Emergency Contact Phone</span><input v-model="activeProfileFields.emergency_contact_phone" type="text" placeholder="Enter emergency contact phone" class="field-input" /></label>
                    <label class="field-wrap md:col-span-2">
                        <span class="field-title">Bio</span>
                        <QuillEditor
                            v-model:content="activeProfileFields.bio"
                            content-type="html"
                            theme="snow"
                            :toolbar="quillToolbar"
                            placeholder="Write bio"
                            class="quill-editor"
                        />
                    </label>
                </div>

                <div v-if="activeProfileTab === 'location'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-blue-600 bg-blue-600 px-4 py-2 text-xs font-black text-white hover:bg-blue-700 transition disabled:opacity-70"
                            :disabled="isLocating"
                            @click="getCurrentLocation"
                        >
                            {{ isLocating ? 'Fetching Location...' : 'Get Current Location' }}
                        </button>
                        <span v-if="locationStatus" class="text-xs font-semibold text-emerald-700">{{ locationStatus }}</span>
                    </div>
                    <label class="field-wrap md:col-span-2"><span class="field-title">Address Line 1</span><input v-model="activeProfileFields.address_line_1" type="text" placeholder="Enter address line 1" class="field-input" /></label>
                    <label class="field-wrap md:col-span-2"><span class="field-title">Address Line 2</span><input v-model="activeProfileFields.address_line_2" type="text" placeholder="Enter address line 2" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Area</span><input v-model="activeProfileFields.area" type="text" placeholder="Enter area" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">City</span><input v-model="activeProfileFields.city" type="text" placeholder="Enter city" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">State</span><input v-model="activeProfileFields.state" type="text" placeholder="Enter state" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">PIN / ZIP</span><input v-model="activeProfileFields.pincode" type="text" placeholder="Enter PIN / ZIP" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Country</span><select v-model.number="activeProfileFields.country_id" class="field-input bg-white"><option :value="null">Select country</option><option v-for="item in optionEntries('country')" :key="`country-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Latitude</span><input v-model="activeProfileFields.latitude" type="number" step="0.000001" placeholder="Enter latitude" class="field-input" /></label>
                    <label class="field-wrap md:col-span-2"><span class="field-title">Longitude</span><input v-model="activeProfileFields.longitude" type="number" step="0.000001" placeholder="Enter longitude" class="field-input" /></label>
                </div>

                <div v-if="activeProfileTab === 'social'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="field-wrap"><span class="field-title">Facebook URL</span><input v-model="activeProfileFields.facebook_url" type="url" placeholder="Enter Facebook URL" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Twitter URL</span><input v-model="activeProfileFields.twitter_url" type="url" placeholder="Enter Twitter URL" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Instagram URL</span><input v-model="activeProfileFields.instagram_url" type="url" placeholder="Enter Instagram URL" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">LinkedIn URL</span><input v-model="activeProfileFields.linkedin_url" type="url" placeholder="Enter LinkedIn URL" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">YouTube URL</span><input v-model="activeProfileFields.youtube_url" type="url" placeholder="Enter YouTube URL" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">TikTok URL</span><input v-model="activeProfileFields.tiktok_url" type="url" placeholder="Enter TikTok URL" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Telegram Username</span><input v-model="activeProfileFields.telegram_username" type="text" placeholder="Enter Telegram username" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Discord Username</span><input v-model="activeProfileFields.discord_username" type="text" placeholder="Enter Discord username" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">GitHub URL</span><input v-model="activeProfileFields.github_url" type="url" placeholder="Enter GitHub URL" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Portfolio URL</span><input v-model="activeProfileFields.portfolio_url" type="url" placeholder="Enter portfolio URL" class="field-input" /></label>
                    <label class="field-wrap md:col-span-2"><span class="field-title">Blog URL</span><input v-model="activeProfileFields.blog_url" type="url" placeholder="Enter blog URL" class="field-input" /></label>
                </div>

                <div v-if="activeProfileTab === 'teaching'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="field-wrap">
                        <span class="field-title">Highest Qualification (ID)</span>
                        <select v-model.number="activeProfileFields.highest_qualification_id" class="field-input bg-white">
                            <option :value="null">Select qualification</option>
                            <option v-for="item in optionEntries('highest_qualification')" :key="`highest-qualification-${item.id}`" :value="item.id">{{ item.id }} - {{ item.label }}</option>
                        </select>
                    </label>
                    <label class="field-wrap"><span class="field-title">Institution Name</span><input v-model="activeProfileFields.institution_name" type="text" placeholder="Enter institution name" class="field-input" /></label>
                    <label class="field-wrap">
                        <span class="field-title">Field of Study (ID)</span>
                        <select v-model.number="activeProfileFields.field_of_study_id" class="field-input bg-white">
                            <option :value="null">Select field of study</option>
                            <option v-for="item in optionEntries('field_of_study')" :key="`field-of-study-${item.id}`" :value="item.id">{{ item.id }} - {{ item.label }}</option>
                        </select>
                    </label>
                    <label class="field-wrap"><span class="field-title">Graduation Year</span><input v-model="activeProfileFields.graduation_year" type="number" placeholder="Enter graduation year" class="field-input" /></label>
                    <label class="field-wrap">
                        <span class="field-title">Teaching Experience (ID)</span>
                        <select v-model.number="activeProfileFields.teaching_experience_years_id" class="field-input bg-white">
                            <option :value="null">Select experience</option>
                            <option v-for="item in optionEntries('teaching_experience_years')" :key="`experience-years-${item.id}`" :value="item.id">{{ item.id }} - {{ item.label }}</option>
                        </select>
                    </label>
                    <label class="field-wrap"><span class="field-title">Hourly Rate</span><select v-model.number="activeProfileFields.hourly_rate_id" class="field-input bg-white"><option :value="null">Select hourly rate</option><option v-for="item in optionEntries('hourly_rate_range')" :key="`hourly-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Monthly Rate</span><select v-model.number="activeProfileFields.monthly_rate_id" class="field-input bg-white"><option :value="null">Select monthly rate</option><option v-for="item in optionEntries('monthly_rate_range')" :key="`monthly-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Travel Radius</span><select v-model.number="activeProfileFields.travel_radius_km_id" class="field-input bg-white"><option :value="null">Select travel radius</option><option v-for="item in optionEntries('travel_radius_km')" :key="`travel-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Teaching Mode</span><select v-model.number="activeProfileFields.teaching_mode_id" class="field-input bg-white"><option :value="null">Select teaching mode</option><option v-for="item in optionEntries('teaching_mode')" :key="`mode-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Availability Status</span><select v-model.number="activeProfileFields.availability_status_id" class="field-input bg-white"><option :value="null">Select availability status</option><option v-for="item in optionEntries('availability_status')" :key="`availability-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <div class="field-wrap md:col-span-2">
                        <span class="field-title">Subjects Taught</span>
                        <input v-model="subjectSearch" type="text" placeholder="Search subjects" class="field-input" />
                        <p class="text-[11px] font-semibold text-slate-500">
                            Type to search subjects from API.
                        </p>
                        <div class="subject-box">
                            <div v-if="subjectsLoading" class="text-xs font-semibold text-slate-500">Loading subjects...</div>
                            <div v-else-if="filteredSubjects.length === 0" class="text-xs font-semibold text-slate-500">No subjects found.</div>
                            <label v-for="item in filteredSubjects" :key="`subject-${item.id}`" class="subject-item">
                                <input v-model="activeProfileFields.subjects_taught_ids" :value="item.id" type="checkbox" class="h-4 w-4" />
                                <span class="text-sm font-semibold text-slate-700">{{ item.name }}</span>
                            </label>
                        </div>
                    </div>
                    <label class="field-wrap md:col-span-2">
                        <span class="field-title">Teaching Philosophy</span>
                        <QuillEditor
                            v-model:content="activeProfileFields.teaching_philosophy"
                            content-type="html"
                            theme="snow"
                            :toolbar="quillToolbar"
                            placeholder="Write teaching philosophy"
                            class="quill-editor"
                        />
                    </label>
                </div>

                <div v-if="activeProfileTab === 'student'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="field-wrap"><span class="field-title">Current Class</span><select v-model.number="activeProfileFields.current_class_id" class="field-input bg-white"><option :value="null">Select current class</option><option v-for="item in optionEntries('current_class')" :key="`class-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Current School</span><input v-model="activeProfileFields.current_school" type="text" placeholder="Enter current school" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Board</span><select v-model.number="activeProfileFields.board_id" class="field-input bg-white"><option :value="null">Select board</option><option v-for="item in optionEntries('board')" :key="`board-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Stream</span><select v-model.number="activeProfileFields.stream_id" class="field-input bg-white"><option :value="null">Select stream</option><option v-for="item in optionEntries('stream')" :key="`stream-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Parent Name</span><input v-model="activeProfileFields.parent_name" type="text" placeholder="Enter parent name" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Parent Phone</span><input v-model="activeProfileFields.parent_phone" type="text" placeholder="Enter parent phone" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Parent Email</span><input v-model="activeProfileFields.parent_email" type="email" placeholder="Enter parent email" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Budget Min</span><input v-model="activeProfileFields.budget_min" type="number" placeholder="Enter minimum budget" class="field-input" /></label>
                    <label class="field-wrap md:col-span-2"><span class="field-title">Budget Max</span><input v-model="activeProfileFields.budget_max" type="number" placeholder="Enter maximum budget" class="field-input" /></label>
                    <label class="field-wrap md:col-span-2">
                        <span class="field-title">Learning Challenges</span>
                        <QuillEditor
                            v-model:content="activeProfileFields.learning_challenges"
                            content-type="html"
                            theme="snow"
                            :toolbar="quillToolbar"
                            placeholder="Write learning challenges"
                            class="quill-editor"
                        />
                    </label>
                </div>

                <div v-if="activeProfileTab === 'institute'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="field-wrap"><span class="field-title">Institute Name *</span><input v-model="activeProfileFields.institute_name" type="text" placeholder="Enter institute name" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Institute Type *</span><select v-model.number="activeProfileFields.institute_type_id" class="field-input bg-white"><option :value="null">Select institute type</option><option v-for="item in optionEntries('institute_type')" :key="`institute-type-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Institute Category</span><select v-model.number="activeProfileFields.institute_category_id" class="field-input bg-white"><option :value="null">Select institute category</option><option v-for="item in optionEntries('institute_category')" :key="`institute-category-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Affiliation Number</span><input v-model="activeProfileFields.affiliation_number" type="text" placeholder="Enter affiliation number" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Registration Number</span><input v-model="activeProfileFields.registration_number" type="text" placeholder="Enter registration number" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Establishment Year Range</span><select v-model.number="activeProfileFields.establishment_year_id" class="field-input bg-white"><option :value="null">Select establishment year range</option><option v-for="item in optionEntries('establishment_year_range')" :key="`establish-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Principal Name</span><input v-model="activeProfileFields.principal_name" type="text" placeholder="Enter principal name" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Principal Phone</span><input v-model="activeProfileFields.principal_phone" type="text" placeholder="Enter principal phone" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Principal Email</span><input v-model="activeProfileFields.principal_email" type="email" placeholder="Enter principal email" class="field-input" /></label>
                    <label class="field-wrap"><span class="field-title">Total Students Range</span><select v-model.number="activeProfileFields.total_students_id" class="field-input bg-white"><option :value="null">Select total students range</option><option v-for="item in optionEntries('total_students_range')" :key="`students-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Total Teachers Range</span><select v-model.number="activeProfileFields.total_teachers_id" class="field-input bg-white"><option :value="null">Select total teachers range</option><option v-for="item in optionEntries('total_teachers_range')" :key="`teachers-${item.id}`" :value="item.id">{{ item.label }}</option></select></label>
                    <label class="field-wrap"><span class="field-title">Total Branches</span><input v-model="activeProfileFields.total_branches" type="number" min="1" placeholder="Enter total branches" class="field-input" /></label>
                    <label class="field-wrap md:col-span-2">
                        <span class="field-title">Institute Description</span>
                        <QuillEditor
                            v-model:content="activeProfileFields.institute_description"
                            content-type="html"
                            theme="snow"
                            :toolbar="quillToolbar"
                            placeholder="Write institute description"
                            class="quill-editor"
                        />
                    </label>
                </div>

                <div v-if="activeProfileTab === 'avatar'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="avatar-preview-card">
                            <p class="text-xs font-black text-slate-700 uppercase tracking-[0.05em]">Current Uploaded Image</p>
                            <div class="avatar-preview-frame">
                                <img v-if="profileImageUrl" :src="profileImageUrl" alt="Current avatar" class="avatar-preview-img" />
                                <span v-else class="text-xs font-semibold text-slate-500">No uploaded image</span>
                            </div>
                        </div>
                        <div class="avatar-preview-card">
                            <p class="text-xs font-black text-slate-700 uppercase tracking-[0.05em]">Selected Image Preview</p>
                            <div class="avatar-preview-frame">
                                <img v-if="avatarPreviewUrl" :src="avatarPreviewUrl" alt="Selected avatar preview" class="avatar-preview-img" />
                                <span v-else class="text-xs font-semibold text-slate-500">No file selected</span>
                            </div>
                        </div>
                    </div>
                    <label class="field-wrap md:col-span-2">
                        <span class="field-title">Upload Profile Picture</span>
                        <input type="file" accept="image/*" class="field-input" @change="onAvatarFileChange" />
                        <p class="text-[11px] font-semibold text-slate-500">Allowed: jpeg, png, jpg, gif (max 2MB).</p>
                    </label>
                    <div class="md:col-span-2 flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-blue-600 bg-blue-600 px-4 py-2 text-xs font-black text-white hover:bg-blue-700 transition disabled:opacity-70"
                            :disabled="sectionSaving.avatar"
                            @click="uploadAvatar"
                        >
                            {{ sectionSaving.avatar ? 'Uploading...' : 'Upload Avatar' }}
                        </button>
                    </div>
                </div>

                <div v-if="activeProfileTab === 'password'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="field-wrap md:col-span-2"><span class="field-title">Current Password *</span><input v-model="profileForm.password.current_password" type="password" class="field-input" placeholder="Enter current password" /></label>
                    <label class="field-wrap"><span class="field-title">New Password *</span><input v-model="profileForm.password.password" type="password" class="field-input" placeholder="Enter new password" /></label>
                    <label class="field-wrap"><span class="field-title">Confirm New Password *</span><input v-model="profileForm.password.password_confirmation" type="password" class="field-input" placeholder="Confirm new password" /></label>
                    <div class="md:col-span-2 flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-blue-600 bg-blue-600 px-4 py-2 text-xs font-black text-white hover:bg-blue-700 transition disabled:opacity-70"
                            :disabled="sectionSaving.password"
                            @click="changePassword"
                        >
                            {{ sectionSaving.password ? 'Updating...' : 'Update Password' }}
                        </button>
                    </div>
                </div>

                <div v-if="activeProfileTab === 'delete_account'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
                        <p class="text-sm font-black text-rose-800">Permanent Action</p>
                        <p class="mt-1 text-xs font-semibold text-rose-700">
                            This will permanently delete your account and revoke all tokens. This action cannot be undone.
                        </p>
                    </div>
                    <label class="field-wrap md:col-span-2"><span class="field-title">Current Password *</span><input v-model="profileForm.delete_account.password" type="password" class="field-input" placeholder="Enter current password" /></label>
                    <label class="field-wrap md:col-span-2"><span class="field-title">Type DELETE to Confirm *</span><input v-model="profileForm.delete_account.confirmation" type="text" class="field-input" placeholder="DELETE" /></label>
                    <label class="field-wrap md:col-span-2"><span class="field-title">Reason (Optional)</span><input v-model="profileForm.delete_account.reason" type="text" class="field-input" placeholder="Tell us why you are leaving" /></label>
                    <div class="md:col-span-2 flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-rose-700 bg-rose-700 px-4 py-2 text-xs font-black text-white hover:bg-rose-800 transition disabled:opacity-70"
                            :disabled="sectionSaving.delete_account"
                            @click="deleteAccount"
                        >
                            {{ sectionSaving.delete_account ? 'Deleting...' : 'Delete Account Permanently' }}
                        </button>
                    </div>
                </div>

                <div v-if="!['avatar', 'password', 'delete_account'].includes(activeProfileTab)" class="flex flex-wrap items-center gap-2 pt-1">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800 transition disabled:opacity-70"
                        :disabled="sectionSaving[activeProfileTab] || profileOptionsLoading"
                        @click="saveProfileSection(activeProfileTab)"
                    >
                        {{ sectionSaving[activeProfileTab] ? 'Saving...' : `Save ${activeProfileTab}` }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-black text-slate-700 hover:bg-slate-50 transition"
                        :disabled="profileLoading"
                        @click="fetchProfileAutofill"
                    >
                        Reload Data
                    </button>
                </div>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.field-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.field-title {
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: rgb(71 85 105);
}

.field-input {
    border-radius: 0.5rem;
    border: 1px solid rgb(203 213 225);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: rgb(51 65 85);
}

.field-input:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    border-color: rgb(59 130 246);
}

.field-readonly {
    background: rgb(241 245 249);
    color: rgb(100 116 139);
    cursor: not-allowed;
}

.subject-box {
    border: 1px solid rgb(226 232 240);
    border-radius: 0.5rem;
    padding: 0.6rem;
    max-height: 220px;
    overflow: auto;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    background: rgb(248 250 252);
}

.subject-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

.avatar-preview-card {
    border: 1px solid rgb(226 232 240);
    border-radius: 0.75rem;
    background: rgb(248 250 252);
    padding: 0.75rem;
}

.avatar-preview-frame {
    margin-top: 0.5rem;
    min-height: 170px;
    border: 1px dashed rgb(203 213 225);
    border-radius: 0.65rem;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.avatar-preview-img {
    width: 100%;
    height: 170px;
    object-fit: cover;
}

.quill-editor :deep(.ql-container) {
    min-height: 150px;
    font-size: 0.875rem;
    background: white;
}

.quill-editor :deep(.ql-toolbar) {
    border-top-left-radius: 0.5rem;
    border-top-right-radius: 0.5rem;
    background: white;
}

.quill-editor :deep(.ql-container) {
    border-bottom-left-radius: 0.5rem;
    border-bottom-right-radius: 0.5rem;
}
</style>
