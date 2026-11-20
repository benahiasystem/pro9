const {test} = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const Vue = require('vue');
const load = require('./helpers/load-module.cjs');
const invoicePrepayments = load(path.resolve('resources/js/mixins/invoice-prepayments.js')).default;

function invoice(prepayment_deduction = true) {
    const calls = [];
    const errors = [];
    const vm = new Vue({
        mixins:[invoicePrepayments],
        data:() => ({
            resource:'documents', prepayment_deduction, prepayment_documents:[],
            form:{customer_id:'9007199254740993', currency_type_id:'VES', establishment_id:1,
                affectation_type_prepayment:20, prepayments:[{amount:'1/3'}], total_prepayment:'0.333333333333',
                discounts:[{discount_type_id:'05'}, {discount_type_id:'03'}]},
        }),
        beforeCreate() {
            this.$http = {get:(url,options) => new Promise((resolve,reject) => calls.push({url, options, resolve, reject}))};
            this.$message = {error:message => errors.push(message)};
        },
        methods:{changeTotalPrepayment(){}},
    });
    return {vm,calls,errors};
}

test('invoice advance options follow the current buyer, currency and branch despite late responses', async () => {
    const {vm,calls,errors} = invoice();
    const first = vm.getDocumentsPrepayment();
    assert.equal(calls[0].url, '/documents/prepayments/20');
    assert.equal(calls[0].options.params.customer_id, '9007199254740993');
    vm.form.customer_id = '9007199254740994';
    vm.form.establishment_id = 2;
    const targetCurrency = process.env.SAB_TEST_INVOICES_VES_ONLY === '1' ? 'VES' : 'USD';
    vm.form.currency_type_id = targetCurrency;
    await Vue.nextTick();
    assert.deepEqual(vm.form.prepayments, []);
    assert.equal(vm.form.total_prepayment, '0');
    assert.deepEqual(vm.form.discounts.map(row => row.discount_type_id), ['03']);
    assert.deepEqual({...calls[1].options.params}, {
        customer_id:'9007199254740994', establishment_id:'2', currency_type_id:targetCurrency,
    });
    calls[1].resolve({data:[{id:2}]});
    await Promise.resolve();
    calls[0].resolve({data:[{id:1}]});
    await first;
    assert.equal(vm.prepayment_documents[0].id, 2);

    const last = vm.getDocumentsPrepayment();
    vm.prepayment_deduction = false;
    vm.resetInvoicePrepaymentOptions();
    calls[2].reject(new Error('late error'));
    await last;
    assert.deepEqual(vm.prepayment_documents, []);
    assert.deepEqual(errors, []);
    vm.$destroy();
});

test('loading an existing invoice with the selector closed preserves its advance rows', async () => {
    const {vm,calls} = invoice(false);
    const original = vm.form.prepayments;
    vm.form.customer_id = '9007199254740994';
    await Vue.nextTick();
    assert.equal(vm.form.prepayments, original);
    assert.equal(vm.form.total_prepayment, '0.333333333333');
    assert.equal(calls.length, 0);
    vm.$destroy();
});
