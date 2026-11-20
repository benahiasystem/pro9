const test = require('node:test')
const assert = require('node:assert/strict')
const { parseExpression } = require('@babel/parser')
const proof = require('../../scripts/exact_guard_proof.cjs')

test('literal integer calculations keep every intermediate in the exact integer range', () => {
    for (const source of ['24 * 60 * 60 * 1000', '(13 - 2) * 7', '-(1 + 2)', '+15',
        '9007199254740991 - 1', '(-5) % 2', '(-6) / 3', '144 / 12']) {
        assert.equal(proof(parseExpression(source)), 'literal_integer_arithmetic', source)
    }
})

test('literal arithmetic rejects inexact and dynamic operands', () => {
    for (const source of ['9007199254740991 + 1', '(9007199254740991 + 1) - 1',
        '0.1 + 0.2', '1.0 + 2', '1e-324 + 0', '9007199254740991.1 + 0', '0x10 + 1', '1 / 3', '1 / 0', '1 % 0', '0 / (-1)', '-0', '0 * (-1)', '(-6) % 3',
        '2 ** 4', 'amount + 1', '(1 + 2) * amount', '"12" + 1', '(24 + 1) === 25', 'parseFloat("1.23")']) {
        assert.equal(proof(parseExpression(source)), null, source)
    }
})

test('proven constants agree with native arithmetic and reject signed zero', () => {
    const operations = { '+': (a,b) => a+b, '-': (a,b) => a-b, '*': (a,b) => a*b }
    for (const a of [-97,-3,-1,0,1,2,13,41]) for (const b of [-41,-2,-1,0,1,3,17]) {
        for (const [operator, native] of Object.entries(operations)) {
            const source = `(${a}) ${operator} (${b})`
            const result = native(a,b)
            assert.equal(proof(parseExpression(source)), Object.is(result,-0) ? null : 'literal_integer_arithmetic', source)
            if (!Object.is(result,-0)) {
                const exact = native(BigInt(a),BigInt(b))
                assert.equal(BigInt(result), exact, source)
            }
        }
    }
})
