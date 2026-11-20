const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');
const babel = require('@babel/core');
const compiler = require('vue-template-compiler');
const load = require('./helpers/load-module.cjs');

const filename = 'resources/js/views/tenant/documents/partials/lots.vue';
const parsed = compiler.parseComponent(fs.readFileSync(filename, 'utf8'));
const code = babel.transformSync(parsed.script.content, {configFile:false, babelrc:false, plugins:['@babel/plugin-transform-modules-commonjs']}).code;
const exportsObject = {};
vm.runInNewContext(code, {
    _: require('lodash'), exports:exportsObject,
    require: name => name.endsWith('exchange-rate-math')
        ? load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
        : name.endsWith('invoice-lots')
            ? load(path.resolve('resources/js/helpers/invoice-lots.js'))
            : {default:{stringify: () => ''}, __esModule:true},
});
const component = exportsObject.default;

function context(data = {}) {
    const ctx = {...component.data(), ...data};
    for (const [key, value] of Object.entries(component.methods)) ctx[key] = value.bind(ctx);
    for (const [key, value] of Object.entries(component.computed)) Object.defineProperty(ctx, key, {get:()=>value.call(ctx)});
    return ctx;
}

test('invoice serial lookup keeps searching after an unmatched or incomplete series', async () => {
    const selected = [];
    const match = {series:'000124', id:'9007199254740993', has_sale:false};
    const other = {series:'000123', id:'9007199254740992', has_sale:false};
    let result = [];
    let selections = 0;
    const ctx = context({
        search:{input:'00012'}, lotsSelected:selected,
        $http:{post:async()=>({data:{data:result,meta:{per_page:'20'}}})},
        $nextTick:callback=>callback(),
        $refs:{searchInput:{select(){selections++;}}},
    });
    await ctx.getRecords();
    assert.deepEqual(selected, []);
    assert.equal(ctx.pagination.per_page, 20);
    result = [other, match];
    await ctx.getRecords();
    assert.deepEqual(selected, []);
    ctx.search.input = '000124';
    await ctx.getRecords();
    await ctx.getRecords();
    assert.deepEqual(selected, [match]);
    assert.equal(other.has_sale, false);
    assert.equal(selections, 1);
});

test('invoice serial picker rejects an excess that a rounded quantity conceals', () => {
    const emitted = [], errors = [];
    const ctx = context({
        exactQuantities:true, quantity:'32767.999999999999',
        exactQuantity:{n:'32767999999999999',d:'1000000000000'},
        lotsSelected:new Array(32768),
        $emit:(event)=>emitted.push(event),
        $message:{error:message=>errors.push(message)},
    });
    assert.equal(ctx.toAttend, '-0.000000000001');
    ctx.submit();
    assert.deepEqual(emitted, []);
    assert.equal(errors.length, 1);
    ctx.quantity = '32768';
    ctx.exactQuantity = {n:'32768',d:'1'};
    ctx.submit();
    assert.deepEqual(emitted, ['addRowSelectLot', 'update:showDialog']);
    ctx.exactQuantity = null;
    ctx.submit();
    assert.equal(errors.length, 2);
    ctx.exactQuantity = {n:'327679999999999999',d:'10000000000000'};
    assert.equal(ctx.toAttend, '-1/10000000000000');
    assert.equal(ctx.seriesSelectionInvalid, true);
    ctx.exactQuantities = false;
    assert.equal(ctx.toAttend, 0);
    assert.equal(ctx.seriesSelectionInvalid, false);
});

test('selecting invoice serials keeps equivalent small IDs and separates adjacent bigint IDs', () => {
    const selected = [{id:17}, {id:'9007199254740992'}];
    const small = {id:'17', has_sale:false};
    const next = {id:'9007199254740993', has_sale:false};
    const unsafe = {id:Number('9007199254740993'), has_sale:false};
    const ctx = context({lotsSelected:selected, records:[small,next,unsafe]});
    ctx.checkedLot();
    assert.equal(small.has_sale,true);
    assert.equal(next.has_sale,false);
    assert.equal(unsafe.has_sale,false);
    ctx.changeHasSale(small);
    assert.deepEqual(selected,[{id:'9007199254740992'}]);
    ctx.changeHasSale(next);
    ctx.changeHasSale(unsafe);
    assert.deepEqual(selected.map(row=>row.id),['9007199254740992','9007199254740993']);
    ctx.changeHasSale(next);
    assert.deepEqual(selected,[{id:'9007199254740992'}]);
});
