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
 * GET /teachers/options
 * Returns the options/subjects/cities object directly.
 */
export async function getOptions() {
    try {
        const body = await api.get('/teachers/options');
        return body?.data ?? {};
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
    try {
        const body = await api.get(`/teachers/${id}`);
        return body?.data ?? body;
    } catch (e) {
        throw normaliseError(e);
    }
}
