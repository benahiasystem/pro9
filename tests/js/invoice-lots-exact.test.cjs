const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path')
const compiler=require('vue-template-compiler'),parser=require('@babel/parser'),generate=require('@babel/generator').default
const load=require('./helpers/load-module.cjs'),math=load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const lots=load(path.resolve('resources/js/helpers/invoice-lots.js'))
function component(file,names,section='methods') {
    const c=compiler.parseComponent(fs.readFileSync(file,'utf8')),ast=parser.parse(c.script.content,{sourceType:'module'})
    const entries=ast.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name===section).value.properties
    const methods=vm.runInNewContext(`({${entries.filter(n=>names.includes(n.key?.name)).map(n=>generate(n).code).join(',')}})`,{...math,...lots,_:require('lodash')})
    return {methods,template:c.template.content}
}
test('invoice lot assignments retain twelve decimals, large quantities and exact sums',()=>{
    const row={quantity:'0.3',item:{lots_enabled:true,description:'Servicio ficticio'},IdLoteSelected:[{id:1,compromise_quantity:'0.1'},{id:2,compromise_quantity:'0.2'}]}
    assert.equal(lots.validateItemsLots([row]).valid,true)
    row.quantity='0.300000000001';assert.equal(lots.validateItemsLots([row]).valid,false)
    row.quantity='1.00001';row.IdLoteSelected=[{id:1,compromise_quantity:'1'}];assert.equal(lots.validateItemsLots([row]).valid,false)
    row.quantity='9007199254740993.000000000001';row.IdLoteSelected[0].compromise_quantity=row.quantity
    assert.equal(lots.validateItemsLots([row]).valid,true)
    assert.equal(lots.normalizeInvoiceLots(row.IdLoteSelected)[0].compromise_quantity,row.quantity)
    row.IdLoteSelected[0].compromise_quantity='12bad';assert.equal(lots.validateItemsLots([row]).valid,false)
})
test('invoice submission preserves assigned quantity text and rejects invalid quantities explicitly',()=>{
    const {methods}=component('resources/js/views/tenant/documents/invoice_generate.vue',['ensureItemsLotsForSubmit'])
    const quantity='9007199254740993.000000000001',errors=[]
    const row={quantity,item:{lots_enabled:true},IdLoteSelected:[{id:1,code:'LOT-1',compromise_quantity:quantity}]}
    const state={...methods,form:{items:[row]},$set:(o,k,v)=>o[k]=v,$message:{error:m=>errors.push(m)}}
    assert.equal(state.ensureItemsLotsForSubmit(),true);assert.equal(row.IdLoteSelected[0].compromise_quantity,quantity)
    assert.equal(row.item.IdLoteSelected[0].compromise_quantity,quantity)
    row.IdLoteSelected[0].compromise_quantity='bad';assert.equal(state.ensureItemsLotsForSubmit(),false);assert.equal(errors.length,1)
})
test('all serial-controlled invoice rows are validated, independently of their order',async()=>{
    const {methods}=component('resources/js/views/tenant/documents/invoice_generate.vue',['validatePreview','commitPendingInvoiceAdjustments'])
    const valid={quantity:'1',item:{series_enabled:true,lots:[{id:1}]}},invalid={quantity:'2',item:{series_enabled:true,lots:[{id:2}]}}
    for(const items of [[valid,invalid],[invalid,valid]]) {
        const errors=[];assert.equal(lots.invoiceSeriesValid(items),false)
        assert.equal(await methods.validatePreview.call({...methods,form:{items},$message:{error:m=>errors.push(m)}}),false)
        assert.equal(errors.length,1)
    }
    assert.equal(lots.invoiceSeriesValid([valid,valid]),true)
})
test('the real invoice lot dialog computes remainder and submits exact decimal text',async()=>{
    const {methods,template}=component('resources/js/views/tenant/documents/partials/lots_group.vue',['setTotalQuantityToCompromise','submit'])
    const {methods:computed}=component('resources/js/views/tenant/documents/partials/lots_group.vue',['toAttend','quantityCompleted'],'computed')
    const events=[],warnings=[]
    const state={...methods,exactQuantities:true,quantity:'0.3',lots_group_:[{id:1,quantity:'1',compromise_quantity:'0.1'},{id:2,quantity:'1',compromise_quantity:'0.2'}],
        $message:{warning:m=>warnings.push(m),success(){}},$emit:(name,value)=>events.push([name,value])}
    state.sourceLots=state.lots_group_
    assert.equal(computed.toAttend.call(state),'0');await state.submit();assert.equal(events[0][1][0].compromise_quantity,'0.1')
    state.quantity='0.300000000001';events.length=0;await state.submit();assert.equal(events.length,0);assert.equal(warnings.length,1)
    const row={quantity:'9007199254740993.000000000001'};state.setTotalQuantityToCompromise(row);assert.equal(row.compromise_quantity,row.quantity)
    assert.deepEqual(compiler.compile(template).errors,[])
    assert.ok(template.includes('v-if="exactQuantities"'));assert.ok(template.includes('inputmode="decimal"'))
})

test('filtering invoice lots keeps allocations made under a different search',async()=>{
    const file='resources/js/views/tenant/documents/partials/lots_group.vue'
    const {methods}=component(file,['filter','submit'])
    const {methods:computed}=component(file,['toAttend','sourceLots'],'computed')
    const Vue=require('vue'),events=[], warnings=[]
    const lotsGroupAll=[
        {id:1,code:'LOTE-ALFA',quantity:'1',compromise_quantity:'0'},
        {id:2,code:'LOTE-BETA',quantity:'1',compromise_quantity:'0'},
    ]
    const state=new Vue({
        data:()=>({exactQuantities:true,quantity:'0.3',search:'ALFA',lots_group_:[],lotsGroupAll}),
        computed,methods,
        created(){this.$message={warning:message=>warnings.push(message)}},
    })
    state.$on('addRowLotGroup',lots=>events.push(lots))
    state.filter();assert.deepEqual(state.lots_group_.map(row=>row.id),[1])
    state.lots_group_[0].compromise_quantity='0.1'
    state.search='BETA'
    state.filter();assert.deepEqual(state.lots_group_.map(row=>row.id),[2])
    state.lots_group_[0].compromise_quantity='0.2'
    assert.equal(state.toAttend,'0')
    await state.submit()
    assert.equal(warnings.length,0)
    assert.deepEqual(Array.from(events[0],row=>[row.id,row.compromise_quantity]),[[1,'0.1'],[2,'0.2']])
    state.$destroy()
})

test('Vue reacts to incomplete lot inputs and recovers after a valid decimal is entered',async()=>{
    const Vue=require('vue'),errors=[]
    const {methods:computed}=component('resources/js/views/tenant/documents/partials/lots_group.vue',['toAttend','quantityCompleted','sourceLots'],'computed')
    const state=new Vue({data:()=>({exactQuantities:true,quantity:'0.3',lotsGroup:[{id:1,quantity:'1',compromise_quantity:'0.1'}]}),computed})
    assert.equal(state.toAttend,'0.2')
    for(const value of ['','-','invalid','-1']) {state.lotsGroup[0].compromise_quantity=value;await Vue.nextTick();assert.equal(state.toAttend,'—')}
    state.lotsGroup[0].compromise_quantity='0,3';await Vue.nextTick();assert.equal(state.toAttend,'0')
    state.$destroy()
})
