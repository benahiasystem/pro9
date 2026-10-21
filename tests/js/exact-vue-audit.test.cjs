const {test} = require('node:test')
const assert = require('node:assert/strict')
const parser = require('@babel/parser')
const audit = require('../../scripts/exact_vue_audit.cjs')
const registry = require('../../scripts/exact_audit_registry.cjs')
const proof = require('../../scripts/exact_guard_proof.cjs')
const coverage = require('../../scripts/exact_source_coverage.cjs')
const fs = require('node:fs'), os = require('node:os'), path = require('node:path'), crypto = require('node:crypto')

test('Vue AST covers filters, nested objects, branch expressions, slots and numeric modifiers', () => {
    const expressions = [], findings = []
    const template = `<section>
      <div v-for="row in lines" :class="{price: row.total > 0}" @click="total += row.price">
        {{ ({amount: row.total / 3}).amount | money }}
      </div><div v-if="total > 0">A</div><div v-else-if="balance < 0">B</div>
      <x-list><template #default="slot"><input v-model.trim.number="slot.amount" type="number"></template></x-list>
    </section>`
    audit(template, 'source.vue', 5, {
        analyze(source, file, offset, context, expression) {
            parser[expression ? 'parseExpression' : 'parse'](source)
            expressions.push(source)
        },
        finding(...args) { findings.push(args) }
    })
    assert.ok(expressions.some(value => value.includes('_f("money")') && value.includes('row.total / 3')))
    assert.ok(expressions.some(value => value.includes('price: row.total > 0')))
    assert.ok(expressions.includes('balance < 0'))
    assert.ok(expressions.includes('total += row.price'))
    assert.ok(expressions.includes('lines'))
    assert.ok(findings.some(value => value[3] === 'numeric_control'))
    assert.ok(findings.some(value => value[3] === 'numeric_model'))
})

test('registry rejects changed evidence, missing anchors, symlink escapes and orphan reviews', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'pro9-registry-'))
    try {
        fs.writeFileSync(path.join(root, 'proof.js'), 'function verified_case() {}')
        const hash = crypto.createHash('sha256').update(fs.readFileSync(path.join(root, 'proof.js'))).digest('hex')
        const review = {category:'presentation', justification:'Final output only', evidence:['proof.js#verified_case'], evidence_sha256:{'proof.js':hash}, source:{file:'source.js',symbol:'Price::format',operation:'decimal',operation_language:'javascript'}}
        assert.equal(registry.validReview(review, root), true)
        assert.equal(registry.validReview({...review,source:{file:'source.js'}}, root), false)
        assert.equal(registry.validReview({...review,evidence:['proof.js#missing']}, root), false)
        fs.symlinkSync(__filename, path.join(root, 'escape.js'))
        assert.equal(registry.validReview({...review,evidence:['escape.js']}, root), false)
        assert.equal(registry.diagnostics([], {known:review}, 'javascript')[0].reason, 'orphaned_review')
        assert.deepEqual(registry.diagnostics([{id:'known'}], {known:review}, 'javascript'), [])
        assert.equal(registry.diagnostics([{id:'known',status:'pending'}], {known:review}, 'javascript')[0].reason,'invalid_or_stale_review')
        fs.appendFileSync(path.join(root, 'proof.js'), '// changed')
        assert.equal(registry.validReview(review, root), false)
    } finally { fs.rmSync(root, {recursive:true, force:true}) }
})

test('classification proof never exempts arithmetic, numeric comparisons or coercive equality', () => {
    assert.equal(registry.matchesProof({proof:'strict_type_guard'},null),false)
    assert.equal(registry.matchesProof({proof:'strict_type_guard'},'strict_type_guard'),true)
    assert.equal(registry.matchesProof({},null),true)
    assert.equal(proof(parser.parseExpression('currency === "VES"')), 'nonnumeric_string_equality')
    assert.equal(proof(parser.parseExpression('amount === null')), 'strict_type_guard')
    for (const expression of ['total > 0', 'amount == "0x1000000000000001"', 'amount == "1e-12"', 'quantity / 3', 'price === "0"', 'value > "VES"']) {
        assert.equal(proof(parser.parseExpression(expression)), null, expression)
    }
})

test('nullish guards prove presence only and retain nested monetary candidates', () => {
    for (const expression of ['price == null', 'null != price', 'price != null', 'null == price']) {
        assert.equal(proof(parser.parseExpression(expression)), 'nullish_presence_guard', expression)
    }
    for (const expression of ['price == undefined', 'price == false', 'price == 0', 'price < null', 'price == "null"']) {
        assert.notEqual(proof(parser.parseExpression(expression)), 'nullish_presence_guard', expression)
    }
    const tree=parser.parseExpression('round(total / 3, 2) != null')
    assert.equal(proof(tree), 'nullish_presence_guard')
    assert.equal(proof(tree.left), null)
    assert.equal(proof(tree.left.arguments[0]), null)
    for (const value of [0, 1, 0n, '', false, NaN, {valueOf() { throw Error('must not coerce') }}]) {
        assert.equal(value == null, false)
    }
    assert.equal(undefined == null, true)
    assert.equal(null == null, true)
})

test('loose nonnumeric guards exclude numeric coercions and nested money', () => {
    for (const expression of ['currency == "VES"', 'mode != "draft"']) {
        assert.equal(proof(parser.parseExpression(expression)), 'loose_nonnumeric_string_equality')
    }
    for (const literal of ['0x1000000000000001','1e-12','Infinity','-Infinity','  ','','0b101','0o70','0','.1']) {
        assert.equal(proof(parser.parseExpression('amount == '+JSON.stringify(literal))),null,literal)
    }
    const tree=parser.parseExpression('round(total / 3, 2) == "invalid"')
    assert.equal(proof(tree),'loose_nonnumeric_string_equality')
    assert.equal(proof(tree.left.arguments[0]),null)
    assert.equal(proof(tree.left),null)
})

test('new production files are covered and undocumented exclusions fail', () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'pro9-coverage-'))
    try {
        fs.mkdirSync(path.join(root,'public','build'), {recursive:true})
        fs.writeFileSync(path.join(root,'public','consumer.js'), 'const total = Number(price)')
        fs.writeFileSync(path.join(root,'public','build','generated.js'), 'const total = Number(price)')
        const policy = {roots:['public'],excluded_directory_names:[],exclusions:[{path:'public/build',reason:'Generated from audited sources'}]}
        assert.deepEqual(coverage.sourceFiles(root,policy), ['public/consumer.js'])
        assert.deepEqual(coverage.missingSources(['public/consumer.js'],[]), ['public/consumer.js'])
        assert.throws(()=>coverage.excluded('public/consumer.js',{...policy,exclusions:[{path:'public'}]}))
    } finally { fs.rmSync(root,{recursive:true,force:true}) }
})
