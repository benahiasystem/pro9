const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const babel = require('@babel/core');

// ######## INICIO MONEDA VENEZUELA HOTEL ########
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/helpers/ensure-exchange-rate-sale.js'), 'utf8');
const code = babel.transformSync(source, {configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']}).code;
const exportsObject = {};
vm.runInNewContext(code, {exports: exportsObject});
const {ensureExchangeRateSale} = exportsObject;

test('hotel sale note obtains a valid exchange rate before saving', async () => {
    const document = {date_of_issue: '2026-09-24', exchange_rate_sale: 0};
    const rate = await ensureExchangeRateSale(document, {
        get: async url => {
            assert.equal(url, '/services/exchange/2026-09-24');
            return {data: {sale: '42.50'}};
        },
    });
    assert.equal(rate, 42.5);
    assert.equal(document.exchange_rate_sale, 42.5);
});

test('converting an existing note keeps its valid recorded exchange rate', async () => {
    const document = {date_of_issue: '2026-09-24', exchange_rate_sale: 40};
    assert.equal(await ensureExchangeRateSale(document, {
        get: () => assert.fail('A valid rate must not be replaced'),
    }), 40);
});

test('an invalid exchange response does not change the note or invoice', async () => {
    const document = {date_of_issue: '2026-09-24', exchange_rate_sale: 0};
    await assert.rejects(ensureExchangeRateSale(document, {
        get: async () => ({data: {sale: 0}}),
    }), /tipo de cambio válido/);
    assert.equal(document.exchange_rate_sale, 0);
});
// ######## FIN MONEDA VENEZUELA HOTEL ########
