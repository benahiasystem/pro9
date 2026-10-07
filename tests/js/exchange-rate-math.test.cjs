const {test} = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const {exactAmount, normalizeExchangeRate, rateMultiply, rateDivide} = require('./helpers/load-module.cjs')(
    path.resolve('resources/js/helpers/exchange-rate-math.js'));
test('rates retain all eight decimals including trailing zeroes', () => {
    assert.equal(normalizeExchangeRate('873.86700000'), '873.86700000');
    assert.equal(normalizeExchangeRate('873.86712345'), '873.86712345');
    assert.equal(normalizeExchangeRate('873,86700000'), '873.86700000');
    assert.equal(normalizeExchangeRate('873.867000000'), '873.86700000');
    assert.equal(normalizeExchangeRate('0.00000001'), '0.00000001');
    for (const value of ['0', '-1', '1.123456789', 'Infinity', '10000000000']) assert.throws(() => normalizeExchangeRate(value));
});
test('multiplication and division use complete rate and round only final money', () => {
    assert.equal(rateMultiply('1000', '873.86712345'), '873867.12');
    assert.equal(rateMultiply('1000', '873.86700000'), '873867.00');
    assert.equal(rateDivide('500', '873.86712345', 8), '0.57216937');
    assert.equal(exactAmount('500').dividedBy('873.86712345').times('873.86712345').final(2), '500.00');
    assert.equal(exactAmount('-1.005').final(2), '-1.01');
    assert.equal(exactAmount('1e-8').times('100000000').final(2), '1.00');
    assert.equal(exactAmount('0.01').times('873.86712345').times('4').final(2), '34.95');
});
