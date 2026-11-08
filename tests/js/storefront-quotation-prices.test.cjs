const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs')
const path=require('node:path')
const compiler=require('vue-template-compiler')
const parser=require('@babel/parser')
const generate=require('@babel/generator').default
const vm=require('node:vm')
const load=require('./helpers/load-module.cjs')
const {storefrontPreview}=load(path.resolve('resources/js/helpers/storefront-quotation-prices.js'))
const {exactInput}=load(path.resolve('resources/js/helpers/exchange-rate-math.js'))

test('la vista previa conserva fracciones, descuento y ajustes de cabecera',()=>{
    const row={quantity:'0.333333333333',unit_price:'3',discount_percentage:'10',
        original_quantity:'1',original_unit_price:'1',original_discount_percentage:'0',
        affectation_igv_type_id:'20',discounts:[],charges:[]}
    const quote={current_percentage_igv:'16',discounts:[],charges:[],total_prepayment:'0'}
    const preview=storefrontPreview([row],quote)
    assert.equal(preview.totals.calculation_snapshot.values.total.n,'8999999999991')
    assert.equal(preview.totals.calculation_snapshot.values.total.d,'10000000000000')
    assert.equal(preview.totals.total,'0.899999999999')
    assert.throws(()=>storefrontPreview([{...row,quantity:'0.0000000000001'}],quote))
    assert.throws(()=>storefrontPreview([{...row,discount_percentage:'101'}],quote))
    const taxed=storefrontPreview([{...row,quantity:'2',unit_price:'116',discount_percentage:'0',affectation_igv_type_id:'10'}],quote)
    assert.equal(taxed.totals.total_taxed,'200')
    assert.equal(taxed.totals.total_igv,'32')
    assert.equal(taxed.totals.total,'232')
    const historical={...row,quantity:'0.333333333333',unit_price:'3',
        original_quantity:'0.333333333333',original_unit_price:'3',original_discount_percentage:'0',
        calculation_snapshot:{values:{quantity:{n:'1',d:'3'},unit_price:{n:'3',d:'1'}}}}
    const preserved=storefrontPreview([historical],quote)
    assert.equal(preserved.totals.calculation_snapshot.values.total.n,'9')
    assert.equal(preserved.totals.calculation_snapshot.values.total.d,'10')
})

test('el diálogo conserva cadenas al enviar y admite entradas precisas',async()=>{
    const component=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/quotations/partials/define_prices.vue','utf8'))
    assert.deepEqual(compiler.compile(component.template.content).errors,[])
    assert.doesNotMatch(component.template.content,/<el-input-number/)
    const tree=parser.parse(component.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key.name==='methods').value
    const submit=methods.properties.find(n=>n.key.name==='submit')
    const run=vm.runInNewContext(`({${generate(submit).code}}).submit`,{exactInput})
    let sent
    const ctx={canSave:true,recordId:1,items:[{id:2,quantity:'0.333333333333',unit_price:'9007199254740993.01',discount_percentage:'0.125'}],
        $http:{post:async (url,payload)=>{sent=payload;return {data:{success:false}}}},
        $message:{error:()=>{},warning:()=>{}}}
    await run.call(ctx)
    assert.equal(sent.items[0].quantity,'0.333333333333')
    assert.equal(sent.items[0].unit_price,'9007199254740993.01')
    assert.equal(sent.items[0].discount_percentage,'0.125')
})
