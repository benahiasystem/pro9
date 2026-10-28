const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const babel = require('@babel/core');
const compiler = require('vue-template-compiler');

// ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
const source = fs.readFileSync('modules/Sale/Resources/assets/js/views/payment_method_types/form.vue', 'utf8');
const parsed = compiler.parseComponent(source);
const exported = {};
vm.runInNewContext(babel.transformSync(parsed.script.content, {
    configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs'],
}).code, {exports: exported});
const component = exported.default;

test('duplicate code displays the server error and keeps the form open', async () => {
    for (const data of [{id: ['El código ya existe']}, {message: {id: ['El código ya existe']}}]) {
        let closed = false;
        const ctx = {
            recordId: null, resource: 'payment-method-types', form: {id: '01'}, errors: {},
            $http: {post: (url) => {
                assert.equal(url, '/payment-method-types');
                return Promise.reject({response: {status: 422, data}});
            }},
            $message: {error: () => assert.fail('expected an inline error')},
            close: () => { closed = true; },
        };
        component.methods.submit.call(ctx);
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(ctx.errors.id[0], 'El código ya existe');
        assert.equal(ctx.loading_submit, false);
        assert.equal(closed, false);
    }
});

test('editing sends PUT to the selected method', async () => {
    let saved = false;
    const ctx = {
        recordId: '27', resource: 'payment-method-types', form: {id: '27'},
        $http: {put: (url) => {
            assert.equal(url, '/payment-method-types/27');
            return Promise.resolve({data: {success: true}});
        }},
        $message: {success() {}}, $eventHub: {$emit() {}}, close: () => { saved = true; },
    };
    component.methods.submit.call(ctx);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(saved, true);
    assert.equal(ctx.loading_submit, false);
});

test('payment methods no longer request card details', () => {
    assert.doesNotMatch(source, /Solicitar datos de tarjeta|has_card|card_brand_id/);
});

test('availability checkbox is absent in both creation and editing', () => {
    const ast = compiler.compile(parsed.template.content).ast;
    function find(node, predicate) {
        if (predicate(node)) return node;
        for (const child of node.children || []) {
            const found = find(child, predicate);
            if (found) return found;
        }
    }
    const checkbox = find(ast, node => node.tag === 'el-checkbox' && node.attrsMap['v-model'] === 'form.is_active');
    assert.equal(checkbox, undefined);
    const unclassified = find(ast, node => node.tag === 'el-option' && node.attrsMap.value === 'unclassified');
    assert.equal(unclassified.if, 'recordId !== null');
    const ctx = {};
    component.methods.initForm.call(ctx);
    assert.equal(ctx.form.is_active, true);
});

test('editing preserves the availability loaded from the server', async () => {
    for (const isActive of [true, false]) {
        let submitted;
        const ctx = {
            recordId: '27', resource: 'payment-method-types', showTax: false,
            $http: {
                get: url => Promise.resolve({data: url.endsWith('/tables')
                    ? {currency_types: [], taxes: []}
                    : {id: '27', description: 'Método', collection_kind: 'account', is_active: isActive}}),
                put: (url, form) => { submitted = {...form}; return Promise.resolve({data: {success: true}}); },
            },
            $message: {success() {}, error() {}}, $eventHub: {$emit() {}}, close() {},
        };
        component.methods.create.call(ctx);
        await new Promise(resolve => setImmediate(resolve));
        ctx.form.description = 'Método editado';
        component.methods.submit.call(ctx);
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(submitted.is_active, isActive);
    }
});

test('payment method form template compiles', () => {
    assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
});
// ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
