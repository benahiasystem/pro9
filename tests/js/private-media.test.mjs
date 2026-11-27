// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
import test from 'node:test';
import assert from 'node:assert/strict';
import { PrivateMediaRenewal, replaceMediaUrls } from '../../resources/js/tenant/private-media.mjs';

test('renews in batches, applies each variant, and deduplicates concurrent requests', async () => {
    const batches = []; const changes = []; let finish;
    const wait = new Promise(resolve => { finish = resolve; });
    const renewal = new PrivateMediaRenewal(async ids => {
        batches.push(ids); await wait;
        return ids.map(id => ({ id, image_media_id: id, image_url: `new-${id}`, image_url_medium: `medium-${id}`, image_urls_expires_at: new Date(1000000).toISOString() }));
    }, map => changes.push(map), () => 1000);
    const rows = Array.from({ length: 105 }, (_, i) => ({ image_media_id: i + 1, image_url: `old-${i + 1}`, image_url_medium: `old-medium-${i + 1}`, image_urls_expires_at: new Date(2000).toISOString() }));
    renewal.register(rows);
    const first = renewal.renew(); await renewal.renew(); finish(); await first;
    assert.deepEqual(batches.map(batch => batch.length), [100, 5]);
    assert.equal(changes[0].get('old-1'), 'new-1');
    assert.equal(changes[0].get('old-medium-1'), 'medium-1');
    await renewal.renew(); assert.equal(batches.length, 2);
});

test('replaces nested reactive fields safely with cycles', () => {
    const state = { item: { image_url: 'old', image_url_small: 'small' }, text: 'old' }; state.self = state;
    replaceMediaUrls(state, new Map([['old', 'new']]));
    assert.equal(state.item.image_url, 'new'); assert.equal(state.text, 'old');
});

test('bounds automatic retries and allows recovery after suspension', async () => {
    let calls = 0;
    const renewal = new PrivateMediaRenewal(async () => { calls++; throw Error('private backend'); }, () => {}, () => 1000);
    renewal.register({ image_media_id: 1, image_url: 'private', image_urls_expires_at: new Date(1000).toISOString() });
    for (let i = 0; i < 5; i++) await renewal.renew();
    assert.equal(calls, 3); await renewal.renew(true); assert.equal(calls, 4);
});
test('renews every signed alias of the same product and replaces revoked products', async () => {
    const updates = [];
    const renewal = new PrivateMediaRenewal(async ids => ids.includes(1) ? [{ id: 1, image_media_id: 1, image_url: 'fresh', image_urls_expires_at: new Date(1000000).toISOString() }] : [], map => updates.push(map), () => 1000);
    for (const url of ['alias-a', 'alias-b']) renewal.register({ image_media_id: 1, image_url: url, image_urls_expires_at: new Date(2000).toISOString() });
    renewal.register({ image_media_id: 2, image_url: 'revoked', image_urls_expires_at: new Date(2000).toISOString() });
    await renewal.renew();
    assert.equal(updates[0].get('alias-a'), 'fresh'); assert.equal(updates[0].get('alias-b'), 'fresh');
    assert.equal(updates[0].get('revoked'), '/logo/imagen-no-disponible.jpg');
    const row = { image_media_id: 1, image_url: 'alias-a', image_urls_expires_at: 'old' };
    replaceMediaUrls(row, updates[0]); assert.equal(row.image_urls_expires_at, new Date(1000000).toISOString());
});
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

test('renewal preserves the field when small and original previously shared a URL', () => {
    const updates = new Map([['shared', 'new-original']]);
    updates.rows = new Map([[1, {image_url: 'new-original', image_url_small: 'new-small'}]]);
    const row = {image_media_id: 1, image_url: 'shared', image_url_small: 'shared'};
    replaceMediaUrls(row, updates);
    assert.equal(row.image_url, 'new-original');
    assert.equal(row.image_url_small, 'new-small');
});
