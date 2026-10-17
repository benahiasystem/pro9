const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), vm = require('node:vm'), {webcrypto} = require('node:crypto');
const compiler = require('vue-template-compiler'), babel = require('@babel/core'), Vue = require('vue');
const exactRuntime = require('./support/exact-runtime.cjs');
const creation = exactRuntime('document-creation');
const clone = value => JSON.parse(JSON.stringify(value));
function load(path, globals = {}) {
    const file = compiler.parseComponent(fs.readFileSync(path, 'utf8')), exports = {};
    assert.deepEqual(compiler.compile(file.template.content).errors, [], path);
    vm.runInNewContext(babel.transformSync(file.script.content, {configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']}).code,
        {exports, require: name => exactRuntime(name) || (name === 'moment' ? require('moment') : name.includes('vuex') ? {mapActions: () => ({}), mapState: () => ({})} : {}), window: {crypto: webcrypto}, FormData, ...globals});
    return {component: exports.default, template: file.template.content};
}
const retention = load('resources/js/views/tenant/documents/partials/retention.vue');
const operations = load('resources/js/views/tenant/documents/partials/note-operations.vue');
const invoice = () => ({document_type_id: '01', total: '116', currency_type_id: 'VES', items: [{quantity: '1'}],
    iva_retention_prevision: {enabled: true, amount: '12'}, islr_retention_prevision: {enabled: true, amount: '3'},
    received_retentions: [{tax_kind: 'IVA', upload_id: 'old-upload'}], payments: [{payment: '20', original_amount: '0.50', currency_type_id: 'USD', exchange_rate: '40'}]});
const entrypoints = [
    'resources/js/views/tenant/documents/invoice_generate.vue',
    ...['payment', 'fast_payment', 'fast_payment_garage', 'options'].map(name => `resources/js/views/tenant/pos/partials/${name}.vue`),
    'resources/js/views/tenant/documents/partials/options.vue', 'resources/js/views/tenant/documents/partials/payments.vue',
    'modules/Hotel/Resources/assets/js/views/rooms/Checkout.vue',
    'modules/Order/Resources/assets/js/views/order_notes/partials/options.vue',
    'modules/Sale/Resources/assets/js/views/technical-services/partials/options.vue',
    ...['Suscription', 'FullSuscription'].map(name => `modules/${name}/Resources/assets/js/payment_receipt/partials/option_documents.vue`),
];
function documentData() {
    return {data: [{id: 1, tax_kind: 'IVA'}, {id: 2, tax_kind: 'ISLR'}], document: {
        id: 4, document_type_id: '01', customer_id: 1, number_full: 'F001-1', control_number: '00-1', total_value: '100', total_igv: '16', total_taxed: '100', currency_type_id: 'VES', exchange_rate_sale: '1',
        iva_retention: {can_create: true, prevision: {amount: '12'}, records: [{id: 1, can_query: true, can_retry: true, can_reverse: true, has_attachment: true}]},
        islr_retention: {can_create: true, records: [{id: 2, can_query: true, can_reverse: false}]}, islr_concepts: [{id: '055', description: 'Concepto'}],
    }};
}
function manager(documentId = 4) {
    const instance = new Vue({...retention.component, propsData: {documentId}});
    instance.$http = {get: async () => ({data: documentData()})};
    return instance;
}

test('creation and confirmation screens expose no retention editor, catalogue or proposal requests', () => {
    for (const path of entrypoints) {
        const source = fs.readFileSync(path, 'utf8'), file = compiler.parseComponent(source), template = file.template.content;
        assert.deepEqual(compiler.compile(template).errors, [], path);
        assert.doesNotThrow(() => babel.transformSync(file.script.content, {configFile: false, babelrc: false}));
        assert.doesNotMatch(template, /<iva-retention-fields|<islr-retention-fields|<received-retention-options|<retention-form\b/, path);
        assert.doesNotMatch(source, /retention\/(?:proposal|concepts|upload-draft)|applyIvaNetCollection|ivaCollectionTarget|showIvaRetention/, path);
    }
    const list = fs.readFileSync('resources/js/views/tenant/documents/index.vue', 'utf8');
    assert.match(list, /clickRetention\(row.id\)/);
    assert.match(list, /<document-retention[\s\S]*?:documentId="recordId"/);
    assert.match(list, /<document-options[\s\S]*?:allow-retention-management="true"/);
});

test('creation sanitization excludes old retentions without changing items, receipts or source data', () => {
    const form = invoice(), original = clone(form), payload = creation.withoutDraftRetentions(form);
    for (const key of ['iva_retention_prevision', 'islr_retention_prevision', 'received_retentions']) assert.equal(Object.hasOwn(payload, key), false);
    assert.equal(payload.payments, form.payments); assert.equal(payload.items, form.items);
    assert.deepEqual(clone(form), original);
    assert.equal(creation.collectionTarget(form), '116.00');
    form.total_payable = '116.01'; assert.equal(creation.collectionTarget(form), '116.01');
});

test('POS and Garage restore sanitized drafts and persist them without resetting real receipts', () => {
    for (const name of ['index', 'fast', 'garage']) {
        const form = invoice(), writes = new Map(), key = name === 'garage' ? 'form_pos_garage' : 'form_pos';
        const {component} = load(`resources/js/views/tenant/pos/${name}.vue`, {localStorage: {getItem: requested => requested === key ? JSON.stringify(form) : null, setItem: (key, value) => writes.set(key, value)}});
        const state = {initDateTimeIssue() {}};
        component.methods.getFormPosLocalStorage.call(state);
        assert.equal(Object.hasOwn(state.form, 'received_retentions'), false);
        assert.equal(Object.hasOwn(state.form, 'iva_retention_prevision'), false);
        assert.deepEqual(clone(state.form.payments), form.payments);
        assert.deepEqual(clone(state.form.items), form.items);
        component.methods.setFormPosLocalStorage.call(state, form);
        const stored = JSON.parse(writes.get(key));
        assert.equal(Object.hasOwn(stored, 'islr_retention_prevision'), false);
        assert.deepEqual(stored.payments, form.payments);
    }
});

test('adding a receipt suggests the exact remaining amount without redistributing previous collections', () => {
    const form = invoice(); form.payments.push({payment: '0'});
    creation.suggestRemainingPayment(form);
    assert.equal(form.payments[0].payment, '20'); assert.equal(form.payments[0].original_amount, '0.50');
    assert.equal(form.payments[1].payment, '96.00');
    form.payments[1].payment = '25'; creation.suggestRemainingPayment(form);
    assert.equal(form.payments[1].payment, '25');
    const fraction = {total: '1', payments: [{payment: '0.333333333333', calculation_snapshot: {values: {payment: {n: '1', d: '3'}}}}, {payment: '0'}]};
    creation.suggestRemainingPayment(fraction); assert.equal(fraction.payments[1].payment, '0.67');
    const foreign = {total: '116', currency_type_id: 'VES', payments: [{payment: '0', currency_type_id: 'USD'}]};
    creation.suggestRemainingPayment(foreign); assert.equal(foreign.payments[0].payment, '0');
    const overpaid = {total: '10', payments: [{payment: '20'}, {payment: '0'}]};
    creation.suggestRemainingPayment(overpaid); assert.equal(overpaid.payments[1].payment, '0.00');
});

test('invoice and POS multiple-payment consumers preserve real receipts when adding another method', () => {
    for (const path of ['resources/js/views/tenant/documents/invoice_generate.vue', 'resources/js/views/tenant/pos/partials/payment.vue', ...['multiple_payment', 'multiple_payment_garage'].map(name => `resources/js/views/tenant/pos/partials/${name}.vue`)]) {
        const {component} = load(path), form = invoice(); form.payments.push({payment: '0'});
        component.methods.calculatePayments.call({form, total: form.total, currencyTypeId: form.currency_type_id, payments: form.payments});
        assert.equal(form.payments[0].payment, '20', path);
        assert.equal(form.payments[1].payment, '96.00', path);
    }
});

test('the invoice add-payment action suggests only the remaining amount rather than the full total', () => {
    const {component} = load('resources/js/views/tenant/documents/invoice_generate.vue', {moment: () => ({format: () => '2026-10-16'})});
    const form = invoice();
    const state = {form, getPaymentDestinationId: () => 'cash'};
    state.calculatePayments = component.methods.calculatePayments.bind(state);
    component.methods.clickAddPayment.call(state);
    assert.equal(form.payments[0].payment, '20');
    assert.equal(form.payments[1].payment, '96.00');
});

test('other creation modules suggest only their outstanding total when another payment is added', () => {
    const paths = entrypoints.filter(path => /modules\/(?:Hotel|Sale|Suscription|FullSuscription)\//.test(path));
    for (const path of paths) {
        const {component} = load(path, {moment: () => ({format: () => '2026-10-16'}), _: {find: () => ({id: 'cash'})}});
        const form = invoice();
        const state = path.includes('/Sale/') ? {form} : {document: form, totalDebt: '116', paymentDestinations: []};
        component.methods.clickAddPayment.call(state);
        assert.equal(form.payments[0].payment, '20', path);
        assert.equal(form.payments[1].payment, '96.00', path);
    }
});

test('the manager requires a saved invoice and obtains both tax histories and action permissions', async () => {
    const missing = manager(null); missing.$http.get = () => assert.fail('No draft requests');
    await missing.load(); assert.equal(missing.canCreate, false); assert.match(missing.error, /factura guardada/); missing.$destroy();
    const instance = manager(); await instance.load();
    assert.equal(instance.records.length, 2);
    assert.equal(instance.records[0].can_query, true); assert.equal(instance.records[1].can_reverse, false);
    assert.equal(instance.form.tax_kind, 'IVA');
    instance.changeKind('ISLR');
    assert.equal(instance.form.tax_kind, 'ISLR'); assert.equal(instance.form.base, '100'); assert.equal(instance.concepts[0].id, '055');
    instance.document.islr_retention.can_create = false; instance.changeKind('ISLR'); assert.equal(instance.canCreate, false);
    instance.$http.post = () => assert.fail('No unauthorized save'); await instance.submit(); instance.$destroy();
});

test('both tax types register against an existing invoice and upload only linked private support', async () => {
    for (const kind of ['IVA', 'ISLR']) {
        const instance = manager(), calls = []; await instance.load(); instance.changeKind(kind);
        instance.$eventHub = {$emit() {}};
        instance.$http.post = async (url, body) => {calls.push({url, body}); return {data: {attachment: 'private/support.pdf'}}};
        await instance.upload({target: {files: [new Blob(['support'], {type: 'application/pdf'})]}});
        assert.equal(calls[0].url, '/documents/retention/upload'); assert.equal(calls[0].body.get('document_id'), '4');
        const uuid = instance.form.operation_key;
        await instance.submit(); assert.equal(calls[1].url, '/documents/retention');
        assert.equal(calls[1].body.document_id, 4); assert.equal(calls[1].body.tax_kind, kind); assert.equal(calls[1].body.operation_key, uuid);
        instance.$destroy();
    }
});

test('closing the manager prevents late proposals and uploads from changing its state', async () => {
    for (const action of ['evaluate', 'upload']) {
        const instance = manager(); await instance.load(); let resolve;
        instance.$http.post = () => new Promise(done => resolve = done);
        const pending = action === 'evaluate' ? instance.evaluate() : instance.upload({target: {files: [new Blob(['support'])]}});
        instance.close(); resolve({data: {attachment: 'late', data: {eligible: true, percentage: 75}}}); await pending;
        assert.equal(instance.proposal, null); assert.equal(instance.form.attachment, null); instance.$destroy();
    }
});

test('note retention corrections require the list context while other adjustments remain available', async () => {
    for (const allowed of [false, true]) {
        const instance = new Vue({...operations.component, propsData: {documentId: 4, allowRetentionManagement: allowed}});
        instance.adjustments = {sources: [{id: 1, kind: 'IVA'}, {id: 2, kind: 'ISLR'}, {id: 3, kind: 'fund'}]};
        assert.deepEqual(Array.from(instance.availableAdjustmentSources, row => row.kind), allowed ? ['IVA', 'ISLR', 'fund'] : ['fund']);
        instance.replacement({id: 1, kind: 'IVA'}); assert.equal(instance.showRetention, allowed);
        if (!allowed) {
            instance.adjustmentForm = {kind: 'ISLR'}; instance.mutate = () => assert.fail('No retention adjustment outside list'); await instance.adjust();
        }
        instance.$destroy();
    }
    const defaultContext = new Vue({...operations.component, propsData: {documentId: 4}});
    assert.equal(defaultContext.allowRetentionManagement, false);
    defaultContext.$destroy();
});
