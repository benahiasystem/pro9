const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/sale_notes/partials/option_documents.vue', 'utf8'));
const context = {module: {exports: {}}, DocumentOptions: {}, ListRestrictItems: {}, FiscalProfileSummary: {}, fnRestrictSaleItemsCpe: {}, newFiscalOperationKey: () => 'stable-test-key'};
const script = component.script.content.replace(/import[\s\S]*?from\s*['"][^'"]+['"];?/g, '').replace('export default', 'module.exports =');
vm.runInNewContext(script, context);
const componentOptions = context.module.exports;
test('sale note conversion template compiles', () => assert.deepEqual(compiler.compile(component.template.content).errors, []));
test('new document starts without copied receipts and has operation key', () => {
    const ctx = {};
    componentOptions.methods.initDocument.call(ctx);
    assert.equal(ctx.document.operation_key, 'stable-test-key');
    assert.equal(ctx.document.payments.length, 0);
});
test('changing payment condition never copies old receipts', () => {
    const ctx = {document: {payment_condition_id: '01', payments: [{payment: 132}]}, form: {sale_note: {payments: [{id: 8, payment: 100}]}}};
    componentOptions.methods.changePaymentCondition.call(ctx);
    assert.equal(ctx.document.payments.length, 0);
});
test('missing fiscal profile stops submission', async () => {
    let message;
    await componentOptions.methods.submit.call({fiscalProfile: null, $message: {error: value => {message = value;}}});
    assert.match(message, /perfil fiscal/);
});
