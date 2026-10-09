const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const babel = require('@babel/core');
const compiler = require('vue-template-compiler');
const exportsObject = {};
vm.runInNewContext(babel.transformSync(fs.readFileSync('resources/js/helpers/document-number.js', 'utf8'), {
    configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs'],
}).code, {exports: exportsObject});

test('display matches eight-digit backend format and preserves empty, provisional and long values', () => {
    for (const [input, expected] of [[25, '00000025'], [0, '00000000'], ['00000025', '00000025'],
        [12345678, '12345678'], ['123456789', '123456789'], [null, ''], ['', ''], ['#', '#'], ['25A', '25A']]) {
        assert.equal(exportsObject.displayDocumentNumber(input), expected);
        assert.equal(exportsObject.documentNumberFull('', input), expected);
        assert.equal(exportsObject.documentNumberFull('FF-01', input), `FF-01-${expected}`);
    }
});

test('changed Vue templates compile and import the shared display helper', () => {
    const files = [
        'resources/js/views/tenant/documents/note.vue',
        'resources/js/views/tenant/sale_notes/ModalGenerateCPE.vue',
        'resources/js/views/tenant/dispatches/generate-document.vue',
        'resources/js/views/tenant/purchases/index.vue',
        'resources/js/views/tenant/purchases/partials/detail-drawer.vue',
        'modules/Report/Resources/assets/js/views/sales_consolidated/index.vue',
        'modules/Report/Resources/assets/js/views/documents/index.vue',
        'modules/Order/Resources/assets/js/views/order_notes/partials/detail-drawer.vue',
    ];
    for (const file of files) {
        const parsed = compiler.parseComponent(fs.readFileSync(file, 'utf8'));
        const result = compiler.compile(parsed.template.content);
        assert.deepEqual(result.errors, [], file);
        babel.transformSync(parsed.script.content, {configFile: false, babelrc: false,
            plugins: ['@babel/plugin-transform-modules-commonjs']});
        assert.match(parsed.script.content, /import \{ documentNumberFull \}/, file);
    }
});
