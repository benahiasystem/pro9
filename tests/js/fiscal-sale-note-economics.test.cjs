const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('resources/js/mixins/fiscal-sale-note-economics.js', 'utf8');
const context = {module: {exports: {}}};
vm.runInNewContext(source.replace('export const fiscalSaleNoteEconomics =', 'module.exports ='), context);
const mixin = context.module.exports;
function instance() {
    const ctx = {...mixin.data(), form: {sale_notes_relateds: [{id: 2}, {id: 1}]}, $set: (object, key, value) => {object[key] = value;}};
    Object.defineProperty(ctx, 'hasSourceSaleNoteEconomics', {get: () => mixin.computed.hasSourceSaleNoteEconomics.call(ctx)});
    return ctx;
}
test('source adjustments and final total survive repeated calculation without another deduction', () => {
    const ctx = instance();
    const snapshot = {source_ids: [1, 2], totals: {total: '300.00', total_discount: '10.00', total_charge: '10.00'}, discounts: [{amount: '10'}], charges: [{amount: '10'}]};
    mixin.methods.loadSaleNoteEconomics.call(ctx, ctx.form.sale_notes_relateds, snapshot);
    for (let i = 0; i < 3; i++) {
        assert.equal(mixin.methods.applySaleNoteEconomics.call(ctx), true);
        assert.equal(ctx.form.total, 300);
        assert.equal(ctx.form.total_discount, 10);
        assert.equal(ctx.form.discounts.length, 1);
    }
    ctx.form.discounts[0].amount = '999';
    assert.equal(snapshot.discounts[0].amount, '10');
});
test('stale snapshot cannot apply to another source selection', () => {
    const ctx = instance();
    mixin.methods.loadSaleNoteEconomics.call(ctx, ctx.form.sale_notes_relateds, {source_ids: [3], totals: {total: '999'}});
    assert.equal(mixin.methods.applySaleNoteEconomics.call(ctx), false);
});
test('resetting to an ordinary invoice stops applying the old snapshot', () => {
    const ctx = instance();
    mixin.methods.loadSaleNoteEconomics.call(ctx, ctx.form.sale_notes_relateds, {source_ids: [1, 2], totals: {total: '300'}});
    ctx.form.sale_notes_relateds = null;
    assert.equal(mixin.methods.applySaleNoteEconomics.call(ctx), false);
});
