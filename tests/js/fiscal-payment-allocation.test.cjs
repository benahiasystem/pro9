const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/payments.vue', 'utf8'));
const opened = [];
const context = {module: {exports: {}}, deletable: {}, DialogLinkPayment: {}, DocumentOptions: {}, window: {open: (...args) => opened.push(args)}};
vm.runInNewContext(component.script.content.replace(/^\s*import .*$/gm, '').replace('export default', 'module.exports ='), context);

test('payment template compiles with immutable source allocations', () => assert.deepEqual(compiler.compile(component.template.content).errors, []));
for (const type of [undefined, 'sale_notes']) {
    test(`voucher download retains ${type || 'documents'} storage location`, () => {
        context.module.exports.methods.clickDownloadFile('receipt.pdf', type);
        assert.deepEqual(opened.pop(), [`/finances/payment-file/download-file/receipt.pdf/${type || 'documents'}`, '_blank']);
    });
}
