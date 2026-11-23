const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const compiler=require('vue-template-compiler'),parser=require('@babel/parser'),generate=require('@babel/generator').default
const load=require('./helpers/load-module.cjs'),math=load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const {calculateRowItem}=load(path.resolve('resources/js/helpers/functions.js'))
const {documentTotals}=load(path.resolve('resources/js/helpers/exact-document.js'))
const component=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue','utf8'))
const tree=parser.parse(component.script.content,{sourceType:'module'})
const methods=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='methods').value.properties
const inheritedTree=parser.parse(fs.readFileSync('resources/js/mixins/editable-row-items.js','utf8'),{sourceType:'module'})
const inherited=inheritedTree.program.body.find(n=>n.type==='ExportNamedDeclaration'&&n.declaration?.declarations?.[0]?.id.name==='editableRowItems').declaration.declarations[0].init.properties.find(n=>n.key.name==='methods').value.properties
const selected=['documentRowUnitPrice','beginEditableInvoiceRow','markEditableInvoicePrice','finishEditableInvoicePrice','commitEditableInvoiceRow','changeRowQuantity','changeRowUnitPrice','changeRowTotal','recalculateRowTotals']
function context(quantity='2',price='0.1',currency='VES',rate='1') {
    const helper=fs.existsSync('resources/js/helpers/invoice-row-edit.js')?load(path.resolve('resources/js/helpers/invoice-row-edit.js')):{}
    const globals={...math,...helper,_:require('lodash')}
    const base=vm.runInNewContext(`({${inherited.map(n=>generate(n).code).join(',')}})`,globals)
    const own=vm.runInNewContext(`({${methods.filter(n=>selected.includes(n.key?.name)).map(n=>generate(n).code).join(',')}})`,globals)
    const row=calculateRowItem({item:{id:1,unit_price:price,currency_type_id:currency,unit_type_id:'SERV',purchase_unit_price:'0'},quantity,
        discounts:[],charges:[],attributes:[],affectation_igv_type_id:'20'},currency,rate,'0.16')
    const errors=[]
    const state={...base,...own,form:{items:[row],currency_type_id:currency,exchange_rate_sale:rate},percentage_igv:0.16,isExportInvoice:false,
        getFormatUnitPriceRow:value=>math.exactAmount(value).decimal(),valueInputSelect(){},$set:(o,k,v)=>o[k]=v,
        calculateTotal(){this.header=documentTotals(this.form.items)},$message:{error:value=>errors.push(value)}}
    return {state,errors}
}
test('an explicit inline edit back to the displayed price remains a manual decimal',()=>{
    const {state}=context('3','0.333333333333');const row=state.form.items[0]
    row.calculation_snapshot.values.unit_price={n:'1',d:'3'}
    state.beginEditableInvoiceRow(row,'unit_price',{})
    state.finishEditableInvoicePrice(row,'unit_price')
    assert.equal(state.form.items[0].source_price_mode,undefined)
    state.beginEditableInvoiceRow(row,'unit_price',{})
    row.unit_price='0.4';state.markEditableInvoicePrice(row,'unit_price')
    row.unit_price='0.333333333333';state.markEditableInvoicePrice(row,'unit_price')
    assert.equal(state.finishEditableInvoicePrice(row,'unit_price'),true)
    const edited=state.form.items[0]
    assert.equal(edited.source_price_mode,'manual')
    assert.equal(edited.price_input.amount.n,'333333333333')
    assert.equal(edited.price_input.amount.d,'1000000000000')
})
test('actual invoice inline edits renew snapshots and header from exact quantities and prices',()=>{
    const {state,errors}=context();const row=state.form.items[0];row.quantity='3';state.changeRowQuantity(row)
    assert.equal(state.form.items[0].calculation_snapshot.values.quantity.n,'3')
    assert.equal(state.header.total,'0.3')
    state.form.items[0].unit_price='0.2';state.changeRowUnitPrice(state.form.items[0])
    assert.equal(state.header.total,'0.6');assert.equal(state.form.items[0].calculation_snapshot.values.unit_price.n,'1')
    assert.equal(state.form.items[0].calculation_snapshot.values.unit_price.d,'5');assert.deepEqual(errors,[])
})
test('manual invoice total one divided among three units keeps one third through later recalculation',()=>{
    const {state,errors}=context('3','1');state.form.items[0].total='1';state.changeRowTotal(state.form.items[0])
    let row=state.form.items[0];assert.equal(row.calculation_snapshot.values.unit_price.n,'1');assert.equal(row.calculation_snapshot.values.unit_price.d,'3')
    assert.equal(row.price_input.amount.n,'1');assert.equal(row.price_input.amount.d,'3');assert.equal(state.header.total,'1')
    state.recalculateRowTotals(row);row=state.form.items[0]
    assert.equal(state.header.total,'1');assert.equal(row.calculation_snapshot.values.unit_price.d,'3');assert.deepEqual(errors,[])
})
test('invoice inline quantity accepts large decimal text and rejects malformed edits without corrupting snapshots',()=>{
    const {state,errors}=context('1','1');state.form.items[0].quantity='9007199254740993.01';state.changeRowQuantity(state.form.items[0])
    assert.equal(state.header.total,'9007199254740993.01')
    const snapshot=JSON.stringify(state.form.items[0].calculation_snapshot)
    state.form.items[0].quantity='12invalid';state.changeRowQuantity(state.form.items[0])
    assert.equal(JSON.stringify(state.form.items[0].calculation_snapshot),snapshot)
    assert.equal(state.form.items[0].quantity,'9007199254740993.01');assert.equal(errors.length,1)
})
test('editable invoice columns accept decimal text instead of coercing it with a numeric widget',()=>{
    const result=compiler.compile(component.template.content);assert.deepEqual(result.errors,[])
    const nodes=[]
    function visit(n){if(!n || n.type!==1)return;nodes.push(n);for(const child of n.children||[])visit(child);for(const condition of n.ifConditions||[])if(condition.block!==n)visit(condition.block)}
    visit(result.ast)
    for(const field of ['quantity','unit_price','total']) {
        const input=nodes.find(n=>n.attrsMap?.['v-model']==='row.'+field)
        assert.ok(input,field);assert.equal(input.tag,'el-input',field);assert.equal(input.attrsMap.inputmode,'decimal',field)
    }
})

test('invoice inline editing preserves the original row when its snapshot version is unsupported', () => {
    const {state, errors} = context('1', '2')
    const row = state.form.items[0]
    row.calculation_snapshot.version = 2
    const original = JSON.parse(JSON.stringify(row))
    state.beginEditableInvoiceRow(row, 'quantity', {})
    row.quantity = '3'
    assert.equal(state.changeRowQuantity(row), false)
    assert.deepEqual(JSON.parse(JSON.stringify(state.form.items[0])), original)
    assert.equal(state.header, undefined)
    assert.match(errors[0], /versión de cálculo compatible/)
})

test('invoice displays a documentary price once and compares costs in their original currency',()=>{
    const {state,errors}=context('1','10','USD','40')
    state.form.currency_type_id='VES';state.form.items[0]=calculateRowItem(state.form.items[0],'VES','40','0.16')
    let row=state.form.items[0];row.item.purchase_unit_price='10'
    assert.equal(state.documentRowUnitPrice(row),'400')
    state.beginEditableInvoiceRow(row,'unit_price',{});row.unit_price='20'
    assert.equal(state.changeRowUnitPrice(row),false)
    assert.equal(state.form.items[0].unit_price,'400');assert.equal(errors.length,1)
    row=state.form.items[0];row.unit_price='440';assert.equal(state.changeRowUnitPrice(row),true)
    assert.equal(state.header.total,'440');assert.equal(state.form.items[0].item.currency_type_id,'USD')
})

test('invoice restores a legacy row and the form if document recalculation fails',()=>{
    const {state,errors}=context('1','2');let row=state.form.items[0];delete row.calculation_snapshot
    state.beginEditableInvoiceRow(row,'quantity',{});row.quantity='invalid';assert.equal(state.changeRowQuantity(row),false)
    assert.equal(state.form.items[0].quantity,'1')
    row=state.form.items[0];state.form.total='2';state.form.discounts=[]
    state.calculateTotal=function(){this.form.total='broken';this.form.discounts.push({amount:'999'});throw new Error('total inválido')}
    state.beginEditableInvoiceRow(row,'quantity',{});row.quantity='3';assert.equal(state.changeRowQuantity(row),false)
    assert.equal(state.form.items[0].quantity,'1');assert.equal(state.form.total,'2');assert.equal(state.form.discounts.length,0)
    assert.equal(errors.length,2)
})
