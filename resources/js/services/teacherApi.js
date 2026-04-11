import api from '@/api';

/**
 * Checks the response body for success: false and throws if so.
 * Since the api interceptor already unwraps response.data, we receive the body directly.
 */
function checkSuccess(body) {
    if (body && body.success === false) {
        throw new Error(body.message);
    }
    return body;
}

/**
 * GET /teachers/options
 * Returns the options/subjects/cities object directly.
 */
export async function getOptions() {
    const body = await api.get('/teachers/options');
    checkSuccess(body);
    return body;
}

/**
 * GET /teachers
 * Returns { teachers, pagination }.
 */
export async function listTeachers(params = {}) {
    const body = await api.get('/teachers', { params });
    checkSuccess(body);
    return {
        teachers: body.data,
        pagination: body.pagination,
    };
}

/**
 * GET /teachers/{id}
 * Returns the full teacher profile object directly.
 */
export async function getTeacher(id) {
    try {
        const body = await api.get(`/teachers/${id}`);
        checkSuccess(body);
        return body;
    } catch (e) {
        // If it's an axios error with a response, attach the status
        if (e.response?.status) {
            const err = new Error(e.message);
            err.status = e.response.status;
            throw err;
        }
        throw e;
    }
}
