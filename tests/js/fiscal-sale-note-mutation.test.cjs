const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = {module: {exports: {}}};
vm.runInNewContext(fs.readFileSync('resources/js/mixins/deletable.js', 'utf8').replace('export const deletable =', 'module.exports ='), context);
const annul = context.module.exports.methods.anular;
test('annulment explicitly posts after confirmation', async () => {
    const calls = [];
    const ctx = {$confirm: async () => {}, $http: {post: async url => {calls.push(url); return {data: {success: true}};}}, $message: {success() {}}};
    assert.equal(await annul.call(ctx, '/sale-notes/anulate/1', 'post'), true);
    assert.deepEqual(calls, ['/sale-notes/anulate/1']);
});
test('converted source reports the validation reason and settles the loading promise', async () => {
    let message;
    const ctx = {$confirm: async () => {}, $http: {post: async () => {throw {response: {data: {errors: {sale_note_id: ['Origen convertido']}}}};}}, $message: {error: value => {message = value;}}};
    assert.equal(await annul.call(ctx, '/sale-notes/anulate/1', 'post'), false);
    assert.equal(message, 'Origen convertido');
});
test('cancel does not send an annulment', async () => {
    const ctx = {$confirm: async () => {throw 'cancel';}, $http: {post: () => {throw Error('Unexpected request');}}};
    assert.equal(await annul.call(ctx, '/sale-notes/anulate/1', 'post'), false);
});
