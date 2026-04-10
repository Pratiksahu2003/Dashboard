import api from '@/api';

const LEAD_API_BASE_URL = (import.meta.env.VITE_LEAD_API_BASE_URL || `${(import.meta.env.VITE_API_DOMAIN || 'https://www.suganta.in').replace(/\/$/, '')}/api/v1`).trim();

const normalizePayload = response => response?.data || response || {};

export const useLeadApi = () => {
    const listLeads = async params => normalizePayload(await api.get(`${LEAD_API_BASE_URL}/leads`, { params: params || {} }));
    const createLead = async body => normalizePayload(await api.post(`${LEAD_API_BASE_URL}/leads`, body));
    const getLead = async leadId => normalizePayload(await api.get(`${LEAD_API_BASE_URL}/leads/${leadId}`));

    return {
        listLeads,
        createLead,
        getLead,
    };
};

