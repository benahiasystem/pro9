const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const compiler = require('vue-template-compiler')

test('account display distinguishes unavailable amounts from zero and keeps the exonerated column independent', () => {
  const component = compiler.parseComponent(fs.readFileSync('modules/Report/Resources/assets/js/views/state_account/index.vue','utf8'))
  assert.deepEqual(compiler.compile(component.template.content).errors, [])
  assert.match(component.template.content, /row\.total_unaffected\s*==\s*null\s*\?\s*["']—["']/)
  assert.match(component.template.content, /row\.total_free\s*==\s*null\s*\?\s*["']—["']/)
  assert.match(component.template.content, /row\.total_exonerated\s*==\s*0/)
})
