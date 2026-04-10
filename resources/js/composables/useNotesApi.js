import api from '@/api';

const NOTES_API_BASE = `${(import.meta.env.VITE_API_DOMAIN || 'https://api.suganta.com').replace(/\/$/, '')}/api/v2/notes`;

const readData = payload => payload?.data ?? {};

export const useNotesApi = () => {
    const listNotes = async ({
        categoryId = '',
        noteTypeId = '',
        search = '',
        isPaid = '',
        perPage = 12,
        page = 1,
    } = {}) => {
        const payload = await api.get(NOTES_API_BASE, {
            params: {
                category_id: categoryId || undefined,
                note_type_id: noteTypeId || undefined,
                search: search || undefined,
                is_paid: isPaid === '' ? undefined : isPaid,
                per_page: perPage,
                page,
            },
        });

        const data = readData(payload);
        return {
            rows: data?.data || [],
            meta: data?.meta || null,
        };
    };

    const listCategories = async () => {
        const payload = await api.get(`${NOTES_API_BASE}/categories`);
        const data = readData(payload);
        return data?.categories || [];
    };

    const listTypes = async () => {
        const payload = await api.get(`${NOTES_API_BASE}/types`);
        const data = readData(payload);
        return data?.types || [];
    };

    const purchaseNote = async noteId => {
        const payload = await api.post(`${NOTES_API_BASE}/purchase`, { note_id: noteId });
        const data = readData(payload);
        return {
            paymentRequired: data?.payment_required ?? true,
            status: data?.status || '',
            note: data?.note || null,
            payment: data?.payment || null,
            checkoutUrl: data?.checkout_url || '',
            paymentSessionId: data?.payment_session_id || '',
        };
    };

    const checkAccess = async noteId => {
        const payload = await api.get(`${NOTES_API_BASE}/${encodeURIComponent(noteId)}/check-access`);
        return readData(payload);
    };

    const getPaymentStatus = async orderId => {
        const payload = await api.get('/payments/status', {
            params: { order_id: orderId },
        });
        return readData(payload);
    };

    const listMyPurchases = async ({ status = '', perPage = 10, page = 1 } = {}) => {
        const payload = await api.get(`${NOTES_API_BASE}/my-purchases`, {
            params: {
                status: status || undefined,
                per_page: perPage,
                page,
            },
        });
        const data = readData(payload);
        return {
            rows: data?.data || [],
            meta: data?.meta || null,
        };
    };

    return {
        listNotes,
        listCategories,
        listTypes,
        purchaseNote,
        checkAccess,
        getPaymentStatus,
        listMyPurchases,
    };
};
