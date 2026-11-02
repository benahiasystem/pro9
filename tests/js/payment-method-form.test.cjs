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

test('catalogue displays condition and credit fields from classification, even with stale flags', () => {
    const index = compiler.parseComponent(fs.readFileSync('modules/Sale/Resources/assets/js/views/payment_method_types/index.vue', 'utf8'))
    const compiled = compiler.compile(index.template.content)
    assert.deepEqual(compiled.errors, [])
    const find = (node, predicate) => {
        if (predicate(node)) return node
        for (const child of node.children || []) {
            const match = find(child, predicate)
            if (match) return match
        }
    }
    const condition = find(compiled.ast, node => node.type === 2 && node.text.includes("'Contado'") && node.text.includes('collection_kind'))
    assert.ok(condition)
    const expression = condition.expression.replace(/^_s\((.*)\)$/, '$1')
    for (const [kind, staleFlag, label] of [['account',true,'Contado'],['cash',true,'Contado'],['credit',false,'Crédito'],['unclassified',true,'Sin clasificar'],['unknown',true,'Sin clasificar']]) {
        assert.equal(vm.runInNewContext('(' + expression + ')', {row:{collection_kind:kind,is_credit:staleFlag}}), label)
    }
    const form = compiler.compile(parsed.template.content)
    const field = find(form.ast, node => node.tag === 'div' && node.attrsMap['v-show'] === "form.collection_kind === 'credit'")
    assert.ok(field, 'los días de crédito siguen la naturaleza vigente')
    assert.deepEqual(form.errors, [])
})

test('HKA choices follow nature and currency and incompatible changes require a new selection', () => {
    const options = [
        {code:'08', name:'Efectivo', kinds:['cash'], currencies:['VES']},
        {code:'09', name:'Divisas', kinds:['cash'], currencies:['USD']},
        {code:'03', name:'Transferencia', kinds:['account'], currencies:[]},
        {code:'99', name:'Otros', kinds:['cash','account','credit','unclassified'], currencies:[]},
    ];
    const ctx = {hkaPaymentMethods: options, form:{collection_kind:'cash',currency_type_id:'VES',hka_code:'08'}};
    Object.defineProperty(ctx, 'compatibleHkaMethods', {get: () => component.computed.compatibleHkaMethods.call(ctx)});
    Object.defineProperty(ctx, 'incompatibleHka', {get: () => component.computed.incompatibleHka.call(ctx)});
    assert.deepEqual(Array.from(ctx.compatibleHkaMethods, x => x.code), ['08','99']);
    assert.equal(ctx.incompatibleHka, false);
    ctx.form.currency_type_id = 'USD';
    assert.equal(ctx.incompatibleHka, true);
    component.methods.clearIncompatibleHka.call(ctx);
    assert.equal(ctx.form.hka_code, null);
    assert.deepEqual(Array.from(ctx.compatibleHkaMethods, x => x.code), ['09','99']);
    ctx.form.collection_kind = 'account';
    assert.deepEqual(Array.from(ctx.compatibleHkaMethods, x => x.code), ['03','99']);
    ctx.form.collection_kind = 'credit';
    assert.deepEqual(Array.from(ctx.compatibleHkaMethods, x => x.code), ['99']);
    assert.match(parsed.template.content, /Medio fiscal HKA/);
    assert.match(parsed.template.content, /incompatibleHka/);
    const index = fs.readFileSync('modules/Sale/Resources/assets/js/views/payment_method_types/index.vue', 'utf8');
    assert.match(index, /row\.hka_name/);
    assert.match(index, /!row\.hka_compatible/);
});

// ######## INICIO TRAZABILIDAD CONFIGURACION DEL MEDIO ########
test('catalogue displays every configured currency and the descriptive IGTF', () => {
    const index = compiler.parseComponent(fs.readFileSync('modules/Sale/Resources/assets/js/views/payment_method_types/index.vue', 'utf8'))
    assert.deepEqual(compiler.compile(index.template.content).errors, [])
    assert.match(index.template.content, /<th>Moneda<\/th>/)
    assert.match(index.template.content, /row\.currency_type_id/)
    assert.match(index.template.content, /<th>IGTF asociado<\/th>/)
    const output = {}
    vm.runInNewContext(babel.transformSync(index.script.content, {
        configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs'],
    }).code, {exports: output, require: () => ({})})
    const label = output.default.methods.taxLabel
    assert.equal(label({iva_rate_type_id: null}), 'Sin impuesto')
    assert.equal(label({iva_rate_type_id: 'IGTF', iva_rate_percentage: '3.000000000000'}), 'IGTF - 3.00%')
    assert.equal(label({iva_rate_type_id: 'IGTF', iva_rate_percentage: '3.125'}), 'IGTF - 3.125%')
    assert.equal(label({iva_rate_type_id: 'IGTF', iva_rate_percentage: null}), 'IGTF (porcentaje no disponible)')
})
// ######## FIN TRAZABILIDAD CONFIGURACION DEL MEDIO ########
