const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const babel = require('@babel/core');
const compiler = require('vue-template-compiler');
const lodash = require('lodash');
const moment = require('moment');

const source = fs.readFileSync('resources/js/views/tenant/pos/partials/fast_payment_garage.vue', 'utf8');
const parsed = compiler.parseComponent(source);
const exported = {};
vm.runInNewContext(babel.transformSync(parsed.script.content, {
    configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs'],
}).code, {exports: exported, require: name => require('./support/exact-runtime.cjs')(name) || ({}), _: lodash, moment});
const component = exported.default;

function context() {
    const fiscal=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/mixins/fiscal-payment-settings.js')).default
    const warnings = [];
    const requests = [];
    const ctx = {
        form: {
            document_type_id: '01', series_id: 1, customer_id: 10,
            date_of_issue: moment().format('YYYY-MM-DD'),
            currency_type_id:'VES',exchange_rate_sale:'1',payment_condition_id:'02',total:'1',total_payable:'1',payments:[],
            items: [{item_id:1,quantity:'1',unit_type_id:'UND',unit_price:'1',affectation_igv_type_id:'20',total_value:'1',total_igv:'0',total:'1'}],
        },
        all_series: [
            {id: 1, document_type_id: '01', number: 'FF01'},
            {id: 2, document_type_id: '01', number: ''},
            {id: 3, document_type_id: '01', number: 'R4MIRAMONTES'},
            {id: 4, document_type_id: '01', number: 'F234-HHFDGGGG'},
            {id: 5, document_type_id: '80', number: 'NV01'},
        ],
        all_customers: [{id: 10}, {id: 11}],
        configuration: {}, businessTurns: {active: false}, rowsItems: 1,
        userSelectedDocType: true, form_cash_document: {},
        $message: {warning: message => warnings.push(message), error: message => assert.fail(message)},
        $http: {post: async (url, form) => {
            requests.push({url, form: JSON.parse(JSON.stringify(form))});
            return {data: {success: true, data: {id: 42}}};
        }},
        $eventHub: {$emit() {}},
        ensureFiscalPaymentsReady: async () => true,
    };
    for(const name of ['prepareCalculationSubmission','handleCalculationChanged'])ctx[name]=fiscal.methods[name].bind(ctx)
    for (const [name, method] of Object.entries(component.methods)) ctx[name] = method.bind(ctx);
    for (const name of ['setLocalStorageIndex', 'setFormPosLocalStorage', 'asignPlateNumberToItems',
        'autoSendPdfMail', 'saveCashDocument', 'cleanLocalStoragePayment', 'initDataComponent']) {
        ctx[name] = () => {};
    }
    return {ctx, warnings, requests};
}

for (const id of [2, 3, 4]) {
    test(`customer changes and repeated filtering preserve selected invoice series ${id} through payment`, async () => {
        const {ctx, warnings, requests} = context();
        ctx.filterSeries();
        ctx.form.series_id = String(id);
        ctx.form.customer_id = 11;
        ctx.changeCustomer();
        ctx.filterSeries();
        assert.equal(ctx.form.series_id, id);
        await ctx.clickPayment();
        assert.deepEqual(warnings, []);
        assert.equal(requests.length, 1);
        assert.equal(requests[0].url, '/documents');
        assert.equal(requests[0].form.series_id, id);
        assert.equal(requests[0].form.customer_id, 11);
    });
}

test('Garage submits the chosen bigint series without coercing adjacent IDs', async () => {
    const {ctx, requests} = context();
    ctx.all_series = [
        {id: '9007199254740992', document_type_id: '01', number: 'F092'},
        {id: '9007199254740993', document_type_id: '01', number: 'F093'},
    ];
    ctx.form.series_id = '9007199254740993';
    ctx.filterSeries();
    await ctx.clickPayment();
    assert.equal(requests[0].form.series_id, '9007199254740993');
});

test('switching document type replaces an incompatible series and preserves a compatible selection', () => {
    const {ctx} = context();
    ctx.form.series_id = 4;
    ctx.form.document_type_id = '80';
    ctx.filterSeries();
    assert.equal(ctx.form.series_id, 5);
    ctx.filterSeries();
    assert.equal(ctx.form.series_id, 5);
    ctx.form.document_type_id = '01';
    ctx.filterSeries();
    assert.equal(ctx.form.series_id, 1);
});

test('the sole authorized option without a series is selected and submitted automatically', async () => {
    const {ctx, warnings, requests} = context();
    ctx.all_series = [{id: 6, document_type_id: '80', number: ''}];
    ctx.form.document_type_id = '80';
    ctx.filterSeries();

    const events = [];
    component.watch.series.call({$emit: (...args) => events.push(args)}, ctx.series);
    assert.deepEqual(events, [['series-filtered', 1]]);
    assert.equal(ctx.form.series_id, 6);

    await ctx.clickPayment();
    assert.deepEqual(warnings, []);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/sale-notes');
    assert.equal(requests[0].form.series_id, 6);
});

test('refreshing available series discards a removed selection and blocks payment when none remain', async () => {
    const {ctx, requests, warnings} = context();
    ctx.form.series_id = 4;
    ctx.all_series = ctx.all_series.filter(series => series.id !== 4);
    ctx.filterSeries();
    assert.equal(ctx.form.series_id, 1);
    ctx.all_series = [];
    ctx.filterSeries();
    assert.equal(ctx.form.series_id, null);
    await ctx.clickPayment();
    assert.equal(requests.length, 0);
    assert.equal(warnings.length, 2);
});

test('payment panel template compiles without generating assets', () => {
    assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
});
