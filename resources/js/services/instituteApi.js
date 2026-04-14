import api from '@/api';

function normaliseError(e) {
    if (e instanceof Error) return e;
    const err = new Error(e?.message || 'Request failed');
    err.status = e?.code || null;
    return err;
}

/** Public institute listing uses User ID as `id`. */
export function resolveInstituteUserId(institute) {
    if (!institute || typeof institute !== 'object') return null;
    const raw = institute.user_id ?? institute.user?.id ?? institute.id;
    const n = Number(raw);
    return Number.isFinite(n) && n > 0 ? n : null;
}

export function slugifyInstituteName(name) {
    const s = String(name ?? '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
    return s || 'institute';
}

export function instituteSeoSlug(institute) {
    if (!institute || typeof institute !== 'object') return 'institute';
    const raw = institute.slug;
    if (typeof raw === 'string' && raw.trim()) {
        const trimmed = raw.replace(/-\d+$/, '').replace(/^-|-$/g, '');
        if (trimmed) return trimmed;
    }
    return slugifyInstituteName(
        institute.name ?? institute.user?.name ?? institute.profile?.name,
    );
}

/** Inertia path `/institute/{id}/{slug}` (auth app directory — see routes/web.php). */
export function instituteProfilePath(institute) {
    const id = resolveInstituteUserId(institute);
    if (!id) return null;
    const slug = instituteSeoSlug(institute);
    return `/institute/${id}/${slug}`;
}

/** Public marketing URL `/institutes/{slug}-{id}` (no auth — institutes.show). */
export function publicInstituteProfilePath(institute) {
    const id = resolveInstituteUserId(institute);
    if (!id) return null;
    const slug = instituteSeoSlug(institute);
    return `/institutes/${slug}-${id}`;
}

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
    return [];
}

/**
 * GET /institutes/options — @see docs/PublicInstituteApi.md
 */
export async function getInstituteOptions() {
    try {
        const body = await api.get('/institutes/options');
        const data = body?.data ?? {};
        const rawOpts = data.options ?? {};
        return {
            options: {
                institute_type: normaliseOptionList(rawOpts.institute_type),
                institute_category: normaliseOptionList(rawOpts.institute_category),
                establishment_year_range: normaliseOptionList(rawOpts.establishment_year_range),
                total_students_range: normaliseOptionList(rawOpts.total_students_range),
                total_teachers_range: normaliseOptionList(rawOpts.total_teachers_range),
            },
            subjects: Array.isArray(data.subjects) ? data.subjects : [],
            cities: Array.isArray(data.cities) ? data.cities : [],
        };
    } catch (e) {
        throw normaliseError(e);
    }
}

/**
 * GET /institutes
 * @param {Record<string, unknown>} params — see PublicInstituteApi.md (order_by, page, per_page, filters…)
 */
export async function listInstitutes(params = {}) {
    try {
        const body = await api.get('/institutes', { params });
        return {
            institutes: body?.data?.institutes ?? [],
            pagination: body?.data?.pagination ?? {
                current_page: 1,
                per_page: 12,
                total: 0,
                last_page: 1,
            },
        };
    } catch (e) {
        throw normaliseError(e);
    }
}

/**
 * GET /institutes/{id}
 */
export async function getInstitute(id) {
    const numericId = Number(id);
    if (!Number.isFinite(numericId) || numericId <= 0) {
        const err = new Error('Invalid institute id');
        err.status = 404;
        throw err;
    }
    try {
        const body = await api.get(`/institutes/${numericId}`);
        return body?.data ?? body;
    } catch (e) {
        throw normaliseError(e);
    }
}
