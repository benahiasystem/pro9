const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');
const parsed = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/options.vue', 'utf8'));
const exportsObject = {};
vm.runInNewContext(babel.transformSync(parsed.script.content, {
    configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']
}).code, { exports: exportsObject, require: name => name === '@mixins/document-email' ? require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/mixins/document-email.js')) : name.includes('vuex') ? { mapActions: () => ({}), mapState: () => ({}) } : {}, Uint8Array });
const component = exportsObject.default;
function context(post, delivery = { provider: 'hka', status: null, can_send: true }) {
    const messages = [];
    return { form: { id: 13, customer_email: 'test@example.test', email_delivery: delivery }, resource: 'documents',
        emailBusy: false, emailRequestId: 'fixed-request-uuid', newEmailRequestId: () => 'next-request-uuid', errors: {},
        $http: { post }, $set: (object, key, value) => { object[key] = value; }, messages,
        $message: { success: message => messages.push(message), warning: message => messages.push(message), error: message => messages.push(message), info: message => messages.push(message) } };
}
test('both invoice creation and listing use the same email dialog and valid template', () => {
    for (const file of ['invoice.vue', 'index.vue']) assert.match(fs.readFileSync(`resources/js/views/tenant/documents/${file}`, 'utf8'), /partials\/options.vue/);
    assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
    assert.match(parsed.template.content, /!form.email_delivery.can_send/);
});
test('send uses one request UUID and prevents parallel clicks and blocked invoices', async () => {
    let resolve, calls = 0;
    const ctx = context((url, payload) => {
        calls++; assert.equal(url, '/documents/email'); assert.equal(payload.request_id, 'fixed-request-uuid');
        return new Promise(r => { resolve = r; });
    });
    const first = component.methods.clickSendEmail.call(ctx);
    await component.methods.clickSendEmail.call(ctx);
    assert.equal(calls, 1);
    resolve({ data: { success: true, message: 'Solicitud aceptada', email_delivery: { provider: 'hka', status: 'accepted', can_send: true } } });
    await first;
    assert.equal(ctx.emailRequestId, 'next-request-uuid');
    assert.equal(component.computed.emailSendLabel.call(ctx), 'Enviar de nuevo');
    ctx.form.email_delivery.can_send = false;
    await component.methods.clickSendEmail.call(ctx);
    assert.equal(calls, 1);
});
test('network failure retains the UUID and the saved invoice', async () => {
    const ctx = context(async () => { throw Error('network'); });
    await component.methods.clickSendEmail.call(ctx);
    assert.equal(ctx.emailRequestId, 'fixed-request-uuid'); assert.equal(ctx.form.id, 13);
    assert.equal(ctx.emailBusy, false); assert.match(ctx.messages[0], /Factura guardada/);
});
test('query updates delivery independently from fiscal state', async () => {
    const ctx = context(async url => {
        assert.equal(url, '/documents/13/query-hka-email');
        return { data: { message: 'Rastreo actualizado', email_delivery: { status: 'accepted', can_send: true } } };
    });
    ctx.form.fiscal_emission = { status: 'confirmed', control_number: '00-00000002' };
    await component.methods.queryHkaEmail.call(ctx);
    assert.equal(ctx.form.email_delivery.status, 'accepted');
    assert.equal(ctx.form.fiscal_emission.control_number, '00-00000002');
});
