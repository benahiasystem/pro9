const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'),vm=require('node:vm'),babel=require('@babel/core'),compiler=require('vue-template-compiler')
const runtime=require('./support/exact-runtime.cjs')
const {exactValue}=runtime('exchange-rate-math')
const {documentTotals}=runtime('exact-document')
const {restoreSaleNoteContext}=runtime('sale-note-conversion')

function load(file,globals={}) {
    const parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'))
    assert.deepEqual(compiler.compile(parsed.template.content).errors,[])
    const exported={}
    const inert=new Proxy({}, {get:()=>()=>({})})
    vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
        {exports:exported,require:name=>runtime(name) || inert,_:require('lodash'),...globals})
    return exported.default
}
function rows() {
    return ['500','499'].map(price=>({item_id:1,item:{id:1,has_igv:true,presentation:[]},
        quantity:'0.333333333333',unit_price:price,unit_value:price,affectation_igv_type_id:'20',
        currency_type_id:'VES',total_value:'0',total:'0',total_igv:'0',total_discount:'0',total_charge:'0',
        source_sale_note_items:[{sale_note_id:1,sale_note_item_id:price}],
        calculation_snapshot:{version:1,inputs:{conversion_sources:[{sale_note_id:1}]},values:{
            quantity:{n:'1',d:'3'},unit_price:{n:price,d:'1'},unit_value:{n:price,d:'1'},
            total:{n:price,d:'3'},total_value:{n:price,d:'3'},total_igv:{n:'0',d:'1'},
            total_discount:{n:'0',d:'1'},total_charge:{n:'0',d:'1'},total_base_igv:{n:price,d:'3'},
            total_other_taxes:{n:'0',d:'1'},
        }}}))
}
test('note modal transports the server groups without regrouping products or fetching current catalog prices',async()=>{
    const stored=new Map([['itemsForNotes','old'],['lotsItems','old']]),location={href:''};let requests=0
    const component=load('resources/js/views/tenant/sale_notes/ModalGenerateCPE.vue',{
        localStorage:{setItem:(key,value)=>stored.set(key,value),removeItem:key=>stored.delete(key)},window:{location},
    })
    const expected=rows()
    const ctx={form:{selecteds:[1],client_id:1},group_items_generate_document:true,
        notes:[{id:1,selected:true}],clients:[{id:1}],getObjectForNote:v=>v,onClose:()=>{},
        $http:{post:(path,data)=>{requests++;assert.equal(path,'/sale-notes/items');return Promise.resolve({data:{data:[],grouped_items:expected,
            conversion_context:{currency_type_id:'USD',exchange_rate_sale:'40.12345678',customer_id:1,establishment_id:2}}})}},
        $message:()=>{throw new Error('La selección es válida')}}
    component.methods.onFetchNoteItems.call(ctx)
    await new Promise(setImmediate)
    const transferred=JSON.parse(stored.get('itemsNotGroupForNotes'))
    assert.equal(transferred.length,2)
    assert.equal(exactValue(transferred[0],'quantity').times(3).decimal(),'1')
    assert.equal(exactValue(transferred[1],'unit_price').decimal(),'499')
    assert.equal(stored.has('itemsForNotes'),false)
    assert.equal(stored.has('lotsItems'),false)
    assert.equal(location.href,'/documents/create')
    assert.equal(requests,1)
    const view={form:{currency_type_id:'VES'}}
    restoreSaleNoteContext(view,{getItem:key=>stored.get(key),removeItem:key=>stored.delete(key)})
    assert.equal(view.form.currency_type_id,'USD')
    assert.equal(view.form.exchange_rate_sale,'40.12345678')
    assert.equal(view.form.establishment_id,2)
})

for (const grouped of [true,false]) {
    test(`a full browser storage leaves no partial currency or rows and does not open an invoice (grouped=${grouped})`,async()=>{
        const stored=new Map([['noteConversionContext','old'],['itemsForNotes','old'],['lotsItems','old']]),location={href:''}
        let shown=null
        const component=load('resources/js/views/tenant/sale_notes/ModalGenerateCPE.vue',{
            localStorage:{removeItem:key=>stored.delete(key),setItem(key,value) {
                if (key==='notes') { const error=new Error('quota');error.name='QuotaExceededError';throw error }
                stored.set(key,value)
            }},window:{location},
        })
        const ctx={form:{selecteds:[1],client_id:1},group_items_generate_document:grouped,
            notes:[{id:1,selected:true}],clients:[{id:1}],getObjectForNote:v=>v,
            onClose:()=>{throw Error('Una transferencia incompleta no cierra el modal')},
            $http:{post:()=>Promise.resolve({data:{data:rows(),grouped_items:rows(),conversion_context:{
                currency_type_id:'USD',exchange_rate_sale:'40.12345678',customer_id:1,establishment_id:2,
            }}})},$message:{error:value=>{shown=value}},axiosError:()=>{throw Error('No es un error HTTP')}}
        ctx.processNotGroupItems=component.methods.processNotGroupItems.bind(ctx)
        component.methods.onFetchNoteItems.call(ctx)
        await new Promise(setImmediate)
        assert.match(shown,/selección es demasiado grande/)
        assert.equal(stored.size,0)
        assert.equal(location.href,'')
        assert.equal(ctx.loading,false)
    })
}

for (const file of ['invoice_generate.vue','invoice.vue','invoiceupdate.vue']) {
    test(`${file} retains distinct prices, quantities and fractions from grouped note lines`,()=>{
        const component=load(`resources/js/views/tenant/documents/${file}`)
        const ctx={table:false,form:{currency_type_id:'VES'},currency_types:[{id:'VES',symbol:'Bs.'}],
            onPrepareAdditionalInformation:v=>v}
        ctx.onPrepareIndividualItem=component.methods.onPrepareIndividualItem.bind(ctx)
        const result=component.methods.onPrepareItems.call(ctx,rows())
        assert.equal(result.length,2)
        assert.equal(exactValue(result[0],'unit_price').decimal(),'500')
        assert.equal(exactValue(result[1],'unit_price').decimal(),'499')
        assert.equal(exactValue(result[0],'quantity').times(3).decimal(),'1')
        assert.equal(result[0].source_sale_note_items[0].sale_note_item_id,'500')
        assert.equal(exactValue(documentTotals(result),'total').decimal(),'333')
    })
}

test('missing or malformed historical conversion context is not replaced with zero or a current rate',()=>{
    const view={form:{exchange_rate_sale:'1.00000000'}}
    for (const context of [{currency_type_id:'USD',customer_id:1,establishment_id:1},
        {currency_type_id:'PEN',exchange_rate_sale:'1',customer_id:1,establishment_id:1},
        {currency_type_id:'USD',exchange_rate_sale:40.1,customer_id:1,establishment_id:1}]) {
        assert.throws(()=>restoreSaleNoteContext(view,{getItem:()=>JSON.stringify(context),removeItem:()=>{throw Error('No debe consumir una entrada inválida')}}))
        assert.equal(view.form.exchange_rate_sale,'1.00000000')
    }
})

test('the grouped conversion shows a source validation error instead of silently opening another invoice',async()=>{
    const component=load('resources/js/views/tenant/sale_notes/ModalGenerateCPE.vue')
    const error={response:{status:422,data:{errors:{notes_id:['Las notas deben conservar la moneda y tasa.']}}}}
    let shown=null
    const ctx={form:{selecteds:[1,2]},group_items_generate_document:true,
        $http:{post:()=>Promise.reject(error)},axiosError:value=>{shown=value},$message:()=>{}}
    component.methods.onFetchNoteItems.call(ctx)
    await new Promise(setImmediate)
    assert.equal(shown,error)
    assert.equal(ctx.loading,false)
})
