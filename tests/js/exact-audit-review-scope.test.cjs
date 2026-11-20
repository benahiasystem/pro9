const { test } = require('node:test')
const assert = require('node:assert/strict')
const { reviewsForScope } = require('../../scripts/exact_audit_scope.cjs')

const reviews = {
    common: { category: 'technical' },
    invoiceBranch: { category: 'unreachable', scope: 'billing' },
}

test('a review for billing leaves the corresponding global candidate open', () => {
    assert.deepEqual(reviewsForScope(reviews, null), { common: reviews.common })
    assert.deepEqual(reviewsForScope(reviews, 'billing'), reviews)
})

test('unsupported review scopes are rejected', () => {
    assert.throws(() => reviewsForScope({ invalid: { scope: 'typo' } }, 'billing'), /Alcance desconocido/)
})
