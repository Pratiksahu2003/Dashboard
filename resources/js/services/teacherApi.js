import api from '@/api';

/**
 * Normalises a rejection from the api interceptor into a proper Error.
 * The interceptor rejects with plain objects { success, code, message, ... }
 * rather than Error instances, so we normalise here.
 */
function normaliseError(e) {
    if (e instanceof Error) return e;
    const err = new Error(e?.message || 'Request failed');
    err.status = e?.code || null;
    return err;
}

/**
 * Public teacher profile API uses User ID. Listing cards may expose `id`, `user_id`, or `user.id`.
 */
export function resolveTeacherUserId(teacher) {
    if (!teacher || typeof teacher !== 'object') return null;
    const raw = teacher.user_id ?? teacher.user?.id ?? teacher.id;
    const n = Number(raw);
    return Number.isFinite(n) && n > 0 ? n : null;
}

/** URL segment from a display name (lowercase, hyphenated, ASCII). */
export function slugifyTeacherName(name) {
    const s = String(name ?? '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
    return s || 'teacher';
}

/**
 * SEO slug for `/teachers/{slug}/{id}`. Strips a trailing `-{id}` from API `slug` when present.
 */
export function teacherSeoSlug(teacher) {
    if (!teacher || typeof teacher !== 'object') return 'teacher';
    const raw = teacher.slug;
    if (typeof raw === 'string' && raw.trim()) {
        const trimmed = raw.replace(/-\d+$/, '').replace(/^-|-$/g, '');
        if (trimmed) return trimmed;
    }
    return slugifyTeacherName(
        teacher.name ?? teacher.user?.name ?? teacher.display_name ?? teacher.profile?.display_name,
    );
}

/** Canonical Inertia path `/teachers/{slug}/{id}` (public API uses numeric user id). */
export function teacherProfilePath(teacher) {
    const id = resolveTeacherUserId(teacher);
    if (!id) return null;
    const slug = teacherSeoSlug(teacher);
    return `/teachers/${slug}/${id}`;
}

/** Option maps from GET /api/v1/options use string ids → labels; some stacks may return [{ id, label }]. */
function normaliseOptionList(val) {
    if (val == null) return [];
    if (Array.isArray(val)) {
        return val
            .map((item) => {
                if (!item || typeof item !== 'object') return null;
                const id = item.id;
                const label = item.label ?? item.name;
                if (id == null || label == null) return null;
                return { id: Number(id), label: String(label) };
            })
            .filter(Boolean)
            .sort((a, b) => a.id - b.id);
    }
    if (typeof val === 'object') {
        return Object.entries(val)
            .map(([id, label]) => ({ id: Number(id), label: String(label) }))
            .filter((o) => !Number.isNaN(o.id))
            .sort((a, b) => a.id - b.id);
    }
    return [];
}

/**
 * Teacher filter dropdowns: public Options API + Subjects list (subjects are not in /options).
 * @see docs/ProfileAndOptionsApi.md — GET /api/v1/options
 * @see docs/SubjectApi.md — GET /api/v1/subjects
 */
export async function getOptions() {
    const key = [
        'teaching_mode',
        'availability_status',
        'hourly_rate_range',
        'teaching_experience_years',
    ].join(',');

    try {
        const [optionsRes, subjectsRes] = await Promise.all([
            api.get('/options', { params: { key } }),
            api.get('/subjects'),
        ]);

        const raw = optionsRes?.data ?? {};
        const subjects = Array.isArray(subjectsRes?.data) ? subjectsRes.data : [];

        return {
            subjects,
            options: {
                teaching_mode: normaliseOptionList(raw.teaching_mode),
                availability_status: normaliseOptionList(raw.availability_status),
                hourly_rate_range: normaliseOptionList(raw.hourly_rate_range),
                teaching_experience_years: normaliseOptionList(raw.teaching_experience_years),
            },
        };
    } catch (e) {
        throw normaliseError(e);
    }
}

/**
 * GET /teachers
 * Returns { teachers, pagination }.
 */
export async function listTeachers(params = {}) {
    try {
        const body = await api.get('/teachers', { params });
        return {
            teachers: body?.data?.teachers ?? [],
            pagination: body?.data?.pagination ?? { current_page: 1, per_page: 12, total: 0, last_page: 1 },
        };
    } catch (e) {
        throw normaliseError(e);
    }
}

/**
 * GET /teachers/{id}
 * Returns the full teacher profile object directly.
 */
export async function getTeacher(id) {
    const numericId = Number(id);
    if (!Number.isFinite(numericId) || numericId <= 0) {
        const err = new Error('Invalid teacher id');
        err.status = 404;
        throw err;
    }
    try {
        const body = await api.get(`/teachers/${numericId}`);
        return body?.data ?? body;
    } catch (e) {
        throw normaliseError(e);
    }
}
