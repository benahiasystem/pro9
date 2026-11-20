const { test } = require('node:test')
const assert = require('node:assert/strict')
const path = require('node:path')
const parser = require('@babel/parser')
const exactComparisonNodes = require('../../scripts/exact_compare_proof.cjs')

const sourceFile = path.join(__dirname, '../../resources/js/views/tenant/documents/invoice.vue')
const exactModule = path.join(__dirname, '../../resources/js/helpers/exchange-rate-math.js')
const factoryImport = "import { exactAmount } from '../../../helpers/exchange-rate-math'\n"

function proofs(code) {
    const graph = parser.parse(code, { sourceType: 'unambiguous', plugins: ['typescript'] })
    return Array.from(exactComparisonNodes(graph, sourceFile, exactModule), node => code.slice(node.start, node.end))
}

test('recognizes exact comparison outcomes through static named imports', () => {
    const expression = "exactAmount(price).compare(limit) >= 0"
    assert.deepEqual(proofs(factoryImport + expression), [expression])
    assert.deepEqual(proofs("import { exactValue as read } from '../../../helpers/exchange-rate-math.js'\n-1 === read(row, 'total').compare(limit)"),
        ["-1 === read(row, 'total').compare(limit)"])
    const nested = 'exactAmount(price / exchangeRate).compare(limit) > 0'
    assert.deepEqual(proofs(factoryImport + nested), [nested])
})

test('rejects shadowed factories and unrelated operands', () => {
    for (const expression of [
        'function f(exactAmount) { return exactAmount(price).compare(limit) > 0 }',
        'function f() { return exactAmount(price).compare(limit) > 0; const exactAmount = fake }',
        '{ const exactAmount = fake; exactAmount(price).compare(limit) > 0 }',
        'price.compare(limit) > 0', 'exactAmount(price) > 0', 'exactAmount(price).compare(limit) >= 0.0000000000000001',
        'exactAmount(price).compare(limit) === 1.0000000000000001', 'exactAmount(price).compare(limit) === -0',
        'exactAmount(price).compare(limit) == 0', "exactAmount(price)['compare'](limit) > 0",
        'exactAmount(price).compare(...args) > 0', 'exactAmount(price)?.compare(limit) > 0',
        'eval(code); exactAmount(price).compare(limit) > 0',
    ]) assert.deepEqual(proofs(factoryImport + expression), [], expression)
    for (const otherImport of [
        "import { exactAmount } from './exchange-rate-math'\n", "import { other as exactAmount } from '../../../helpers/exchange-rate-math'\n",
        "import type { exactAmount } from '../../../helpers/exchange-rate-math'\n", '',
    ]) assert.deepEqual(proofs(otherImport + 'exactAmount(price).compare(limit) > 0'), [], otherImport)
    assert.deepEqual(proofs('with (scope) { exactAmount(price).compare(limit) > 0 }'), [])
})

test('recognizes the comparison sign on a local exact amount with read-only uses', () => {
    const expression = "amount.compare('0.01') < 0"
    const definitions = "const amount = exactAmount(input).dividedBy(rate); amount.fraction();"
    assert.deepEqual(proofs(factoryImport + definitions + expression), [expression])
    assert.deepEqual(proofs(factoryImport + "const amount = exactAmount(input); amount.times(rate).compare(0) > 0"),
        ["amount.times(rate).compare(0) > 0"])
})

test('leaves escaped, reassigned and unresolved receivers for manual review', () => {
    for (const code of [
        'const amount = exactAmount(input); amount.compare = custom; amount.compare(0) > 0',
        'const amount = exactAmount(input); Object.assign(amount, replacement); amount.compare(0) > 0',
        'const amount = exactAmount(input); const alias = amount; alias.compare = custom; amount.compare(0) > 0',
        'let amount = exactAmount(input); amount = replacement; amount.compare(0) > 0',
        'const amount = exactAmount(input); amount.plus(...args).compare(0) > 0',
        'const amount = exactAmount(input); amount.decimal().compare(0) > 0',
        'const amount = exactAmount(input); amount.n = Number(input); amount.compare(0) > 0',
        'const amount = exactAmount(input); function f(amount) { return amount.compare(0) > 0 }',
        'export const amount = exactAmount(input); amount.compare(0) > 0',
        'const amount = exactAmount(input); export { amount }; amount.compare(0) > 0',
    ]) assert.deepEqual(proofs(factoryImport + code), [], code)
})
