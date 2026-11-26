// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
const imageFields = ['image_url', 'image_url_medium', 'image_url_small'];

export function walkMedia(value, visit, seen = new WeakSet()) {
    if (!value || typeof value !== 'object' || seen.has(value)) return;
    seen.add(value);
    if (value.image_media_id && value.image_urls_expires_at) visit(value);
    for (const key of Object.keys(value)) {
        if (key === '__ob__') continue;
        const child = value[key];
        if (child && typeof child === 'object') walkMedia(child, visit, seen);
    }
}

export class PrivateMediaRenewal {
    constructor(fetchBatch, apply, clock = () => Date.now()) {
        this.fetchBatch = fetchBatch;
        this.apply = apply;
        this.clock = clock;
        this.entries = new Map();
        this.pending = false;
        this.failures = 0;
    }

    register(data) {
        walkMedia(data, row => {
            const id = Number(row.image_media_id);
            const expires = Date.parse(row.image_urls_expires_at);
            if (!Number.isInteger(id) || id <= 0 || !Number.isFinite(expires)) return;
            const entry = this.entries.get(id) || { urls: new Map(imageFields.map(field => [field, new Set()])), expires };
            entry.expires = Math.min(entry.expires, expires);
            for (const field of imageFields) if (typeof row[field] === 'string') entry.urls.get(field).add(row[field]);
            this.entries.delete(id); this.entries.set(id, entry);
            while (this.entries.size > 2000) this.entries.delete(this.entries.keys().next().value);
        });
    }

    invalidate(url) {
        for (const entry of this.entries.values()) if ([...entry.urls.values()].some(urls => urls.has(url))) entry.expires = 0;
    }

    async renew(force = false) {
        if (this.pending || (!force && this.failures >= 3)) return;
        const ids = [...this.entries].filter(([, entry]) => entry.expires - this.clock() < 60000).map(([id]) => id);
        if (!ids.length) return;
        this.pending = true;
        try {
            for (let offset = 0; offset < ids.length; offset += 100) {
                const rows = await this.fetchBatch(ids.slice(offset, offset + 100));
                const changes = new Map();
                const received = new Map(rows.map(row => [Number(row.image_media_id || row.id), row]));
                for (const id of ids.slice(offset, offset + 100)) {
                    const old = this.entries.get(id); const row = received.get(id);
                    if (old) for (const field of imageFields) for (const url of old.urls.get(field)) {
                        changes.set(url, row?.[field] || '/logo/imagen-no-disponible.jpg');
                    }
                    this.entries.delete(id);
                }
                this.register(rows);
                changes.rows = received;
                this.apply(changes);
            }
            this.failures = 0;
        } catch (_) {
            // Capability URLs and provider diagnostics must never reach the console.
            this.failures += 1;
        } finally {
            this.pending = false;
        }
    }
}

export function replaceMediaUrls(value, changes, seen = new WeakSet()) {
    if (!value || typeof value !== 'object' || seen.has(value)) return;
    seen.add(value);
    const row = changes.rows?.get(Number(value.image_media_id));
    if (row) value.image_urls_expires_at = row.image_urls_expires_at;
    for (const key of Object.keys(value)) {
        if (key === '__ob__') continue;
        if (imageFields.includes(key) && changes.has(value[key])) value[key] = changes.get(value[key]);
        else if (value[key] && typeof value[key] === 'object') replaceMediaUrls(value[key], changes, seen);
    }
}

export function installPrivateMedia(Vue, client) {
    if (!client || typeof window === 'undefined') return;
    const renewal = new PrivateMediaRenewal(
        async ids => (await client.get('/media/items/urls', { params: { ids } })).data.data,
        changes => {
            window.dispatchEvent(new CustomEvent('sab-private-media', { detail: changes }));
            for (const element of document.querySelectorAll('img,a[data-image],a[data-fancybox]')) {
                for (const attr of ['src', 'href', 'data-zoom-image']) {
                    const old = element.getAttribute(attr);
                    if (changes.has(old)) element.setAttribute(attr, changes.get(old));
                }
            }
        },
    );
    client.interceptors.response.use(response => { renewal.register(response.data); return response; });
    Vue.mixin({
        mounted() {
            this._privateMediaListener = event => replaceMediaUrls(this.$data, event.detail);
            window.addEventListener('sab-private-media', this._privateMediaListener);
        },
        beforeDestroy() { window.removeEventListener('sab-private-media', this._privateMediaListener); },
    });
    let timer;
    const start = () => { window.clearInterval(timer); timer = window.setInterval(() => { if (!document.hidden) renewal.renew(); }, 30000); };
    start();
    document.addEventListener('error', event => {
        if (event.target?.tagName === 'IMG') { renewal.invalidate(event.target.src); renewal.renew(); }
    }, true);
    window.addEventListener('pageshow', start);
    window.addEventListener('focus', () => renewal.renew(true));
    document.addEventListener('visibilitychange', () => { if (!document.hidden) renewal.renew(true); });
    window.addEventListener('pagehide', () => window.clearInterval(timer));
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
