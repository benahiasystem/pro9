const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const t = require('@babel/types')

const source=compiler.parseComponent(fs.readFileSync('modules/Finance/Resources/assets/js/views/transfer_between_accounts/options.vue','utf8'))
assert.deepEqual(compiler.compile(source.template.content).errors, [])
const component=parser.parse(source.script.content,{sourceType:'module'}).program.body.find(x=>x.type==='ExportDefaultDeclaration').declaration
const methods=component.properties.find(x=>x.key?.name==='methods').value
const original=methods.properties.find(x=>x.key?.name==='transferAmount')
const transfer=vm.runInNewContext(`(${generate(t.functionExpression(null,original.params,original.body)).code})`)

test('transfer sends the configured cash currency with the amounts', async ()=>{
    let sent
    const ctx={resource:'finances/balance',config:{currency_type_id:'VES'},transfer_amount:{from:'C:1',to:'B:2',amount_transform:'1'},
        $http:{post:(url,body)=>{sent={url,body};return Promise.resolve({data:{cash:[]}})}},
        $emit:()=>{},clickClose:()=>{},$message:{error:()=>{}}}
    transfer.call(ctx)
    await new Promise(resolve=>setImmediate(resolve))
    assert.equal(sent.body.data.cash_currency_type_id,'VES')
    assert.equal(sent.body.data.from,'C:1')
    assert.equal(ctx.transfer_amount.cash_currency_type_id,undefined)
})
