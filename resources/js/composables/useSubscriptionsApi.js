import api from '@/api';

const readData = payload => payload?.data ?? {};

export const SUBSCRIPTION_TYPES = [
    { value: 1, label: 'Portfolio' },
    { value: 2, label: 'Note Download' },
    { value: 3, label: 'AI Advisor' },
    { value: 6, label: 'Market Listing' },
];

export const useSubscriptionsApi = () => {
    const listPlans = async (sType = 1) => {
        const payload = await api.get('/subscriptions/plans', {
            params: { s_type: sType },
        });
        const data = readData(payload);
        return data?.plans || [];
    };

    const getCurrentSubscription = async (sType = 1) => {
        const payload = await api.get('/subscriptions/current', {
            params: { s_type: sType },
        });
        const data = readData(payload);
        return data?.subscription || null;
    };

    const listMySubscriptions = async ({ sType = 1, status = '', perPage = 10, page = 1 } = {}) => {
        const payload = await api.get('/subscriptions/my-subscriptions', {
            params: {
                s_type: sType,
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

    const purchaseSubscription = async subscriptionPlanId => {
        const payload = await api.post('/subscriptions/purchase', {
            subscription_plan_id: subscriptionPlanId,
        });
        const data = readData(payload);
        return {
            checkoutUrl: data?.checkout_url || '',
            payment: data?.payment || null,
            subscriptionPlan: data?.subscription_plan || null,
        };
    };

    const renewSubscription = async subscriptionId => {
        const payload = await api.post(`/subscriptions/${subscriptionId}/renew`);
        const data = readData(payload);
        return {
            checkoutUrl: data?.checkout_url || '',
            payment: data?.payment || null,
            subscription: data?.subscription || null,
        };
    };

    const cancelSubscription = async subscriptionId => {
        const payload = await api.patch(`/subscriptions/${subscriptionId}/cancel`);
        const data = readData(payload);
        return data?.subscription || null;
    };

    return {
        listPlans,
        getCurrentSubscription,
        listMySubscriptions,
        purchaseSubscription,
        renewSubscription,
        cancelSubscription,
    };
};
