const {test, before} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
let fiscalSale;
before(async () => {
    globalThis.window = {crypto: require('node:crypto').webcrypto};
    const helper = fs.readFileSync(path.join(__dirname, '../../resources/js/helpers/fiscal-operation.js'), 'utf8');
    const helperUrl = `data:text/javascript;base64,${Buffer.from(helper).toString('base64')}`;
    const source = fs.readFileSync(path.join(__dirname, '../../resources/js/mixins/fiscal-sale.js'), 'utf8')
        .replace("'../helpers/fiscal-operation'", JSON.stringify(helperUrl))
        .replace("import FiscalProfileSummary from '../components/FiscalProfileSummary.vue';", 'const FiscalProfileSummary = {};');
    ({fiscalSale} = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`));
});

function context(type = '01', profiles = [{document_type_id: '01', id: 1}]) {
    const ctx = {form: {document_type_id: type}, fiscalProfiles: profiles, errors: [], $set: (object, key, value) => {object[key] = value;}};
    ctx.$message = {error: message => ctx.errors.push(message)};
    Object.defineProperty(ctx, 'fiscalProfile', {get: () => fiscalSale.computed.fiscalProfile.call(ctx)});
    return ctx;
}

test('fiscal retry keeps the same operation key and a new sale receives another', () => {
    const ctx = context();
    assert.equal(fiscalSale.methods.prepareFiscalSale.call(ctx), true);
    const first = ctx.form.operation_key;
    assert.match(first, /^[a-f0-9]{32}$/);
    assert.equal(fiscalSale.methods.prepareFiscalSale.call(ctx), true);
    assert.equal(ctx.form.operation_key, first);
    ctx.form = {document_type_id: '01'};
    fiscalSale.methods.prepareFiscalSale.call(ctx);
    assert.notEqual(ctx.form.operation_key, first);
});

test('restored operation key is retained', () => {
    const ctx = context();
    ctx.form.operation_key = 'restored-operation';
    fiscalSale.methods.prepareFiscalSale.call(ctx);
    assert.equal(ctx.form.operation_key, 'restored-operation');
});

test('missing fiscal profile blocks submission without inventing an operation', () => {
    const ctx = context('01', []);
    assert.equal(fiscalSale.methods.prepareFiscalSale.call(ctx), false);
    assert.equal(ctx.form.operation_key, undefined);
    assert.equal(ctx.errors.length, 1);
});

test('commercial sales notes do not require a fiscal profile', () => {
    const ctx = context('80', []);
    assert.equal(fiscalSale.methods.prepareFiscalSale.call(ctx), true);
    assert.equal(ctx.form.operation_key, undefined);
    assert.equal(ctx.errors.length, 0);
});
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
