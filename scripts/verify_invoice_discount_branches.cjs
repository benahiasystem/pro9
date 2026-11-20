const assert = require('node:assert/strict')
const { createHash } = require('node:crypto')
const { readFileSync } = require('node:fs')
const { dirname, resolve } = require('node:path')
const parser = require('@babel/parser')
const traverse = require('@babel/traverse').default
const { matchesPattern } = require('@babel/types')
const { parseComponent } = require('vue-template-compiler')

const root = resolve(__dirname, '..')
const files = ['payment.vue', 'fast_payment.vue', 'fast_payment_garage.vue'].map(
    file => `resources/js/views/tenant/pos/partials/${file}`,
)
const operations = new Map([
    ['parseFloat(this.discount_amount)', 'parseFloat'],
    ['parseFloat(this.discount_amount) > 0', 'native_>'],
    ['this.discount_amount >= this.form.total', 'native_>='],
])
const fiscalHelper = resolve(root, 'resources/js/helpers/exact-commercial')

function invoiceReturn(method, file) {
    const guard = method.node.body.body[0]
    assert.equal(guard?.type, 'IfStatement')
    const { test, consequent } = guard
    assert.equal(test.type, 'BinaryExpression')
    assert.equal(test.operator, '===')
    assert.ok(matchesPattern(test.left, 'this.form.document_type_id'))
    assert.equal(test.right.type, 'StringLiteral')
    assert.equal(test.right.value, '01')
    assert.equal(consequent.type, 'ReturnStatement')
    const call = consequent.argument
    assert.equal(call?.type, 'CallExpression')
    assert.equal(call.callee.type, 'Identifier')
    assert.equal(call.arguments.length, 1)
    assert.equal(call.arguments[0].type, 'ThisExpression')
    const binding = method.scope.getBinding(call.callee.name)
    assert.ok(binding?.constant && binding.path.isImportSpecifier())
    assert.equal(binding.path.node.imported.name, 'applyInvoiceCheckoutDiscount')
    assert.equal(resolve(dirname(resolve(root, file)), binding.path.parent.source.value), fiscalHelper)
    return guard.end
}

function verifyFile(file, audit) {
    const bytes = readFileSync(resolve(root, file))
    const sha256 = createHash('sha256').update(bytes).digest('hex')
    assert.equal(sha256, audit.source_hashes[file], `${file}: la fuente auditada cambió`)
    const source = bytes.toString()
    const script = parseComponent(source).script
    assert.ok(script)
    const lineOffset = source.slice(0, script.start).split('\n').length - 1
    const ast = parser.parse(script.content, { sourceType: 'module' })
    const verified = []
    let matches = 0
    traverse(ast, {
        ObjectMethod(method) {
            if (!method.get('key').isIdentifier({ name: 'inputDiscountAmount' })) return
            const owner = method.parentPath.parentPath
            assert.ok(owner.isObjectProperty() && owner.get('key').isIdentifier({ name: 'methods' }))
            assert.ok(owner.parentPath.parentPath.isExportDefaultDeclaration())
            matches++
            const guardEnd = invoiceReturn(method, file)
            method.traverse({
                'CallExpression|BinaryExpression'({ node }) {
                    const expression = script.content.slice(node.start, node.end).trim().replace(/\s+/g, ' ')
                    const operation = operations.get(expression)
                    if (!operation) return
                    assert.ok(node.start >= guardEnd, `${file}: la operación precede al retorno de factura`)
                    const rows = audit.javascript_review.filter(row => row.file === file && row.symbol === 'inputDiscountAmount'
                        && row.line === node.loc.start.line + lineOffset && row.operation === operation && row.expression === expression)
                    assert.equal(rows.length, 1, `${file}: operación sin identificación única`)
                    verified.push({ id: rows[0].id, file, symbol: rows[0].symbol, operation, expression, sha256 })
                },
            })
        },
    })
    assert.equal(matches, 1, file)
    assert.equal(verified.length, operations.size, file)
    assert.equal(new Set(verified.map(row => row.operation)).size, operations.size, file)
    return verified
}

const auditFile = process.argv[2]
assert.ok(auditFile, 'Indique una salida JSON del auditor JavaScript')
const audit = JSON.parse(readFileSync(auditFile, 'utf8'))
assert.equal(audit.scope?.name, 'billing')
assert.deepEqual(audit.parse_errors, [])
assert.deepEqual(audit.coverage_missing, [])
process.stdout.write(JSON.stringify(files.flatMap(file => verifyFile(file, audit)), null, 2) + '\n')
