const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');
const cp = require('node:child_process');
const path = require('node:path');
const load = require('./helpers/load-module.cjs');

test('invoice import automatically sends a blank series only when it is the sole option', () => {
    const source = fs.readFileSync('resources/js/views/tenant/documents/partials/ImportExcel.vue', 'utf8');
    const {script} = compiler.parseComponent(source);
    const seriesHelper = load(path.resolve('resources/js/helpers/document-series.js'));
    const exported = {};
    vm.runInNewContext(babel.transformSync(script.content, {configFile:false, babelrc:false,
        plugins:['@babel/plugin-transform-modules-commonjs']}).code, {
        exports: exported,
        require: () => seriesHelper,
        _: require('lodash'),
    });
    const component = exported.default;
    const ctx = {form: {document_type_id: '01'}, series: []};
    Object.defineProperty(ctx, 'showSeriesSelector', {get: component.computed.showSeriesSelector.bind(ctx)});
    const blank = {document_type_id: '01', number: ''};
    const named = {document_type_id: '01', number: 'FF01'};
    const otherType = {document_type_id: '80', number: 'NV01'};

    for (const [options, selected, visible] of [
        [[blank], '', false],
        [[blank, named], null, true],
        [[named], null, true],
        [[], null, false],
    ]) {
        ctx.all_series = [...options, otherType];
        component.methods.changeDocumentType.call(ctx);
        assert.equal(ctx.form.series, selected);
        assert.equal(ctx.showSeriesSelector, visible);
    }
    ctx.series = [named];
    component.methods.initForm.call(ctx);
    assert.equal(ctx.showSeriesSelector, false);
});

test('blank series option displays no ID or filter marker in Element UI', () => {
    const parsed = compiler.parseComponent(fs.readFileSync('node_modules/element-ui/packages/select/src/option.vue', 'utf8'));
    const exported = {};
    vm.runInNewContext(babel.transformSync(parsed.script.content, {configFile:false, babelrc:false,
        plugins:['@babel/plugin-transform-modules-commonjs']}).code, {exports:exported, require:()=>({})});
    for (const value of [17, '__without_series__', '']) {
        const label = exported.default.computed.currentLabel.call({label:'\u00a0', value, isObject:false});
        assert.equal(label.trim(), '');
        assert.ok(label, 'A truthy visual blank prevents the select from falling back to its placeholder');
    }
    assert.equal(exported.default.computed.currentLabel.call({label:'FF01', value:17, isObject:false}), 'FF01');
});

test('all series option sources compile and keep blank options selectable', () => {
    const files = cp.execFileSync('rg',['--files','resources','modules','-g','*.vue'],{encoding:'utf8'}).trim().split('\n');
    let count = 0;
    for (const file of files) {
        const source = fs.readFileSync(file,'utf8');
        assert.doesNotMatch(source, /Sin serie(?:['"]|\s*<)/i, file);
        if (!source.includes("|| '\\u00a0'")) continue;
        count++;
        const parsed = compiler.parseComponent(source);
        assert.deepEqual(compiler.compile(parsed.template.content).errors,[],file);
        babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']});
        assert.match(source, /:value=/,file);
    }
    assert.ok(count > 30);
    const filter = fs.readFileSync('resources/js/components/DataTableDocuments.vue','utf8');
    assert.ok(filter.includes("option.number || '__without_series__'"));
    assert.ok(filter.includes('clearable'));
    assert.ok(!fs.readFileSync('resources/views/system/public-search/index.blade.php','utf8').includes('placeholder="Sin serie"'));
});
