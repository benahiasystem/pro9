const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
let clock = 0;
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/views/tenant/dispatches/generate-document.vue'), 'utf8');
const script = compiler.parseComponent(source).script.content;
const code = babel.transformSync(script, {configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']}).code;
const exportsObject = {};
vm.runInNewContext(code, {
    exports: exportsObject,
    require: name => {
        if (name === 'moment') return () => ({format: () => `time-${++clock}`});
        if (name.includes('fiscal-operation')) return {newFiscalOperationKey: () => require('node:crypto').randomUUID()};
        return {};
    },
    _: require('lodash'),
});
const methods = exportsObject.default.methods;

function context(post) {
    const ctx = {
        document: {document_type_id: '01', operation_key: 'invoice-operation', time_of_issue: null},
        form: {dispatch: {id: 5, establishment_id: 1, customer_id: 42}}, items: [{id: 1}],
        fiscalProfile: {id: 1}, loading_submit: false, errors: {}, messages: [],
        onCalculateTotals() {}, validatePaymentDestination: async () => ({error_by_item: 0}),
        $http: {post},
    };
    ctx.assignDocument = () => methods.assignDocument.call(ctx);
    ctx.$message = {error: message => ctx.messages.push(message)};
    return ctx;
}

test('conversion retry preserves issue time, source dispatch and full payload', async () => {
    const sent = [];
    const ctx = context(async (url, document) => {
        assert.equal(url, '/documents');
        sent.push(JSON.stringify(document));
        throw new Error('Response lost');
    });
    await methods.submit.call(ctx);
    const initialTime = ctx.document.time_of_issue;
    clock += 60;
    await methods.submit.call(ctx);
    assert.equal(sent.length, 2);
    assert.equal(sent[0], sent[1]);
    assert.equal(ctx.document.time_of_issue, initialTime);
    assert.equal(ctx.document.dispatch_id, 5);
    assert.equal(ctx.document.operation_key, 'invoice-operation');
    assert.equal(ctx.loading_submit, false);
});

test('missing fiscal profile prevents conversion before assigning or sending', async () => {
    const ctx = context(() => assert.fail('Unexpected request'));
    ctx.fiscalProfile = null;
    ctx.assignDocument = () => assert.fail('Unexpected assignment');
    await methods.submit.call(ctx);
    assert.match(ctx.messages[0], /Configure/);
});

test('new conversion form receives a new operation identity', () => {
    const ctx = {prepareDataRetention() {}, configuration: {currency_types: []}};
    methods.initDocument.call(ctx);
    const first = ctx.document.operation_key;
    methods.initDocument.call(ctx);
    assert.notEqual(first, ctx.document.operation_key);
    assert.equal(ctx.document.time_of_issue, null);
});
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
