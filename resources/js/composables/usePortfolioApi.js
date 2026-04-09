import api from '@/api';

const readPayload = (response) => response?.data || response || {};

export const usePortfolioApi = () => {
    const getPortfolioOptions = async () => readPayload(await api.get('/portfolios/options'));
    const getPortfolio = async () => readPayload(await api.get('/portfolios'));
    const createPortfolio = async (formData) => readPayload(await api.post('/portfolios', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }));
    const updatePortfolio = async (body) => {
        const isFormData = typeof FormData !== 'undefined' && body instanceof FormData;
        return readPayload(await api.post('/portfolios/update', body, {
            headers: isFormData ? { 'Content-Type': 'multipart/form-data' } : undefined,
        }));
    };

    return {
        getPortfolioOptions,
        getPortfolio,
        createPortfolio,
        updatePortfolio,
    };
};

export const toCommaSeparatedString = (values) => {
    if (!Array.isArray(values)) return '';
    return values
        .map(item => String(item || '').trim())
        .filter(Boolean)
        .join(', ');
};

export const splitCommaSeparatedString = (value) => {
    return String(value || '')
        .split(',')
        .map(item => item.trim())
        .filter(Boolean);
};
