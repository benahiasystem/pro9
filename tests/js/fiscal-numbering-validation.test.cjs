const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
const component = compiler.parseComponent(fs.readFileSync(require('node:path').join(__dirname, '../../resources/js/views/tenant/establishments/partials/fiscal-numbering.vue'), 'utf8'));
const exportsObject = {};
vm.runInNewContext(babel.transformSync(component.script.content, {configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']}).code, {exports: exportsObject, require: () => ({})});
const methods = exportsObject.default.methods;

for (const [name, body] of [
    ['nested errors', {errors: {initial_number: ['Número inválido']}}],
    ['global handler JSON', {success: false, message: {initial_number: ['Número inválido']}}],
    ['legacy web AJAX', {initial_number: ['Número inválido']}],
]) {
    test(`numbering form displays ${name} without discarding input`, async () => {
        const ctx = {saving: false, errors: {}, saveError: '', base: '/establishments/1/fiscal-numbering', editor: 'sequences', form: {series_code: 'p', initial_number: '0'}, fieldError: methods.fieldError,
            $http: {post: async (url, payload) => {assert.equal(payload.initial_number, '0'); throw {response: {status: 422, data: body}};}},
            cancel() {throw Error('Must retain invalid form');}, load() {throw Error('Must not reload');}};
        await methods.save.call(ctx);
        assert.equal(ctx.fieldError('initial_number'), 'Número inválido');
        assert.equal(ctx.form.initial_number, '0');
        assert.equal(ctx.saving, false);
        assert.ok(ctx.saveError);
    });
}

test('configuration conflict is shown as a general error', async () => {
    const ctx = {saving: false, base: '/establishments/1/fiscal-numbering', editor: 'profiles', form: {}, fieldError: methods.fieldError,
        $http: {post: async () => {throw {response: {status: 422, data: {success: false, message: {configuration: ['La numeración ya existe']}}}};}}};
    await methods.save.call(ctx);
    assert.equal(ctx.saveError, 'La numeración ya existe');
});
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
