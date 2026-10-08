const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');
const source = fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue', 'utf8');
const parsed = compiler.parseComponent(source);
const exportsObject = {};
vm.runInNewContext(babel.transformSync(parsed.script.content, {
    configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']
}).code, {exports: exportsObject, require: name => name.includes('vuex') ? {mapActions: () => ({}), mapState: () => ({})} : {}});
const component = exportsObject.default;
test('editing submits commercial changes without resubmitting collections', () => {
    const form = {id: 17, series: '', number: 5, currency_type_id: 'VES', customer_id: 4,
        items: [{unit_price: 110}], payments: [{id: 10, payment: 100}], received_retentions: [{amount: 5}], guarantee_fund: {amount: 5}};
    const payload = component.methods.documentSubmission.call({isUpdateDocument: true, form});
    assert.equal(payload.id, 17); assert.equal(payload.series, ''); assert.equal(payload.number, 5);
    assert.equal(payload.items[0].unit_price, 110); assert.equal(payload.currency_type_id, 'VES');
    assert.equal(payload.payments.length, 0); assert.equal(payload.received_retentions.length, 0); assert.equal(payload.guarantee_fund, null);
    assert.equal(form.payments[0].payment, 100);
    assert.equal(component.methods.documentSubmission.call({isUpdateDocument: false, form}), form);
});
test('blocked edits stop before validation, HTTP or cash writes', async () => {
    const messages = [];
    await component.methods.submit.call({editBlocked: true, form: {edit_block_reason: 'Factura registrada en HKA'}, $message: {error: m => messages.push(m)}});
    assert.deepEqual(messages, ['Factura registrada en HKA']);
});
test('the editor displays existing collections and preserves currency and series controls', () => {
    assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
    assert.match(parsed.template.content, /v-for="payment in existingPayments"/);
    assert.match(parsed.template.content, /:disabled="isUpdateDocument"/);
    assert.match(source, /if \(!this.isUpdateDocument\) this.saveCashDocument\(\)/);
});
