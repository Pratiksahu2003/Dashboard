import axios from 'axios';
import { useAuth } from '@/composables/useAuth';

const LEAD_API_BASE_URL = (import.meta.env.VITE_LEAD_API_BASE_URL || `${(import.meta.env.VITE_API_DOMAIN || 'https://www.suganta.in').replace(/\/$/, '')}/api/v1`).trim();

const normalizePayload = response => response?.data || response || {};

export const useLeadApi = () => {
    const { getToken } = useAuth();

    const api = axios.create({
        baseURL: LEAD_API_BASE_URL,
        timeout: 20000,
        headers: {
            Accept: 'application/json',
        },
    });

    api.interceptors.request.use(config => {
        const token = getToken();
        if (token) config.headers.Authorization = `Bearer ${token}`;
        return config;
    });

    const listLeads = async params => normalizePayload(await api.get('/leads', { params: params || {} }));
    const createLead = async body => normalizePayload(await api.post('/leads', body));
    const getLead = async leadId => normalizePayload(await api.get(`/leads/${leadId}`));

    return {
        listLeads,
        createLead,
        getLead,
    };
};

