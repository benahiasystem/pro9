// Editable replacement for the realtime module shipped with the standalone Mozo app.
// The build script binds the Vue/Pinia stores from the active Mozo build.
import { Centrifuge } from 'centrifuge';
import { ref, defineStore, watch, useUserSession, useMesaSession, useProductSession, MesaService, provideApi } from 'mozo:bridge';

export const connectionState = ref('disconnected');
let client;
let connecting;
let credentials;
let credentialRequest;
let generation = 0;
let initialized = false;

function currentToken() {
    return useUserSession().token || '';
}

async function getCredentials(force = false) {
    const apiToken = currentToken();
    const authGeneration = generation;
    if (!apiToken) throw new Centrifuge.UnauthorizedError();
    if (!force && credentials?.apiToken === apiToken && credentials.expires_at > Date.now() / 1000 + 15) {
        return credentials;
    }
    if (credentialRequest) return credentialRequest;
    const pending = (async () => {
        const response = await fetch('/api/restaurant/realtime/token', {
            headers: { Authorization: `Bearer ${apiToken}`, Accept: 'application/json' },
            cache: 'no-store',
        });
        if (response.status === 401 || response.status === 403) throw new Centrifuge.UnauthorizedError();
        if (!response.ok) throw new Error(`Realtime authentication failed (${response.status})`);
        const data = await response.json();
        if (apiToken !== currentToken() || authGeneration !== generation) throw new Centrifuge.UnauthorizedError();
        credentials = { ...data, apiToken };
        return credentials;
    })();
    credentialRequest = pending;
    try {
        return await pending;
    } finally {
        if (credentialRequest === pending) credentialRequest = null;
    }
}

function disconnect() {
    generation++;
    const previous = client;
    client = null;
    connecting = null;
    credentialRequest = null;
    credentials = null;
    connectionState.value = 'disconnected';
    previous?.disconnect();
}

async function connect() {
    if (!currentToken() || client || connecting) return connecting;
    const connectGeneration = generation;
    const pending = (async () => {
        const apiToken = currentToken();
        const response = await fetch('/config.json', { cache: 'no-store' });
        if (!response.ok) throw new Error('Realtime configuration unavailable');
        const config = await response.json();
        const auth = await getCredentials();
        if (apiToken !== currentToken() || connectGeneration !== generation) return;
        const wsUrl = String(config.wsUrl || '').replace(/\/+$/, '');
        if (!/^wss?:\/\//.test(wsUrl)) throw new Error('Invalid realtime endpoint');
        const endpoint = wsUrl.endsWith('/connection/websocket') ? wsUrl : `${wsUrl}/connection/websocket`;
        const socket = new Centrifuge(endpoint, {
            token: auth.token,
            getToken: async () => (await getCredentials(true)).token,
        });
        client = socket;
        socket.on('connecting', () => { if (client === socket) connectionState.value = 'connecting'; });
        socket.on('disconnected', () => { if (client === socket) connectionState.value = 'disconnected'; });
        socket.on('error', () => {}); // SDK errors must never print credentials.
        // JWT is restricted to exactly the channel chosen by the tenant backend.
        const subscription = socket.newSubscription(auth.channel, {
            token: auth.subscription_token,
            getToken: async () => (await getCredentials(true)).subscription_token,
        });
        subscription.on('subscribed', () => {
            if (client !== socket) return;
            connectionState.value = 'connected';
            resyncAll();
        });
        subscription.on('unsubscribed', () => {
            if (client === socket) connectionState.value = 'disconnected';
        });
        subscription.on('error', () => {});
        subscription.on('publication', ({ data }) => {
            if (client !== socket || !currentToken()) return;
            const { event, payload } = data || {};
            if (event === 'tables-env-updated' && payload) {
                useMesaSession().setEnvironmentsMesas(payload.environments);
                useMesaSession().setDataMesas(payload.tables);
            } else if (event === 'command-items-updated') {
                useComandaSession().applyCommandData(payload);
            } else if (event === 'stock-updated' && payload) {
                useProductSession().updateProductsStock(payload);
            }
        });
        subscription.subscribe();
        socket.connect();
    })();
    connecting = pending;
    try {
        await pending;
    } catch (error) {
        if (connectGeneration !== generation) return;
        connectionState.value = 'disconnected';
        // No credentials or provider responses in the console.
        console.warn('[realtime] No se pudo autenticar la conexión.');
        if (!(error instanceof Centrifuge.UnauthorizedError) && currentToken()) {
            setTimeout(() => connect(), 5000);
        }
    } finally {
        if (connecting === pending) connecting = null;
    }
}

export const useComandaSession = defineStore('comandaSession', () => {
    const productsStatusReceived = ref([]);
    const productsStatusProcessing = ref([]);
    const productsStatusToDeliver = ref([]);
    const productsStatusDelivered = ref([]);
    function applyCommandData(buckets) {
        if (!buckets) return;
        const mesas = useMesaSession().getMesas2();
        const addWaiter = products => (products || []).map(p => ({
            ...p, waiter: mesas.find(m => m.id === p.mesa_id)?.waiter || null,
        }));
        productsStatusReceived.value = addWaiter(buckets.productsStatusReceived);
        productsStatusProcessing.value = addWaiter(buckets.productsStatusProcessing);
        productsStatusToDeliver.value = addWaiter(buckets.productsStatusToDeliver);
        productsStatusDelivered.value = addWaiter(buckets.productsStatusDelivered);
    }
    return { productsStatusReceived, productsStatusProcessing, productsStatusToDeliver, productsStatusDelivered, applyCommandData };
});

export const RestaurantService = {
    async setRestaurantFavorite(id, favorite) {
        return (await provideApi().post('/restaurant/items/restaurant-favorite', { id })).data;
    },
    async getProductsStock() {
        return (await provideApi().get('/restaurant/items/stock')).data;
    },
};

export const CommandStatusService = {
    async getProductsByCommandStatus(mesaId) {
        return (await provideApi().get(`/restaurant/command-status/served/${mesaId}`)).data;
    },
};

async function resyncAll() {
    if (!currentToken()) return;
    await Promise.allSettled([
        MesaService.syncMesasAndEnvironments(),
        provideApi().get('/restaurant/command-status/items/0').then(({ data }) => {
            if (data.success) useComandaSession().applyCommandData(data.data);
        }),
        RestaurantService.getProductsStock().then(data => {
            if (data.success && data.data) useProductSession().updateProductsStock(data.data);
        }),
    ]);
}

export function resyncNow() { return resyncAll(); }

export function initRealtime() {
    if (!initialized) {
        initialized = true;
        watch(currentToken, (token, previous) => {
            if (token === previous) return;
            disconnect();
            if (token) connect();
        });
        window.addEventListener('pagehide', disconnect);
        window.addEventListener('pageshow', () => connect());
    }
    connect();
}

// Preserve the public interface used by the existing standalone app chunks.
export { CommandStatusService as C, RestaurantService as R, connectionState as c, initRealtime as i, resyncNow as r, useComandaSession as u };
