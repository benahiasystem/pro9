// ######## INICIO PRUEBAS CALCULOS EXACTOS COTIZACIONES ########
const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const compiler=require('vue-template-compiler'),babel=require('@babel/core')
const loadModule=require('./helpers/load-module.cjs')
const inert=new Proxy({}, {get:()=>()=>({})})
function component(file) {
    const parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'))
    assert.deepEqual(compiler.compile(parsed.template.content).errors,[])
    const exports={}
    vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,{
        exports, _:require('lodash'), require:name=>name.includes('/helpers/') && !name.endsWith('.vue')
            ? loadModule(path.resolve('resources/js/helpers',path.basename(name)+'.js')) : inert,
    })
    return exports.default
}
for (const name of ['form.vue','form_edit.vue']) {
    const file=`resources/js/views/tenant/quotations/${name}`
    test(`${name}: payments use exact sums and reject invalid or excess amounts`,()=>{
        const c=component(file), view={form:{total:'0.30',payments:[{payment:'0.10'},{payment:'0.20'}]}}
        let result=c.methods.validate_payments.call(view)
        assert.equal(result.acum_total.compare('0.30'),0)
        assert.equal(result.exceeds_total,false)
        assert.equal(result.error_by_item,0)
        view.form.payments.push({payment:'0.01'})
        result=c.methods.validate_payments.call(view)
        assert.equal(result.exceeds_total,true)
        view.form.payments=[{payment:'0'},{payment:'0.00'},{payment:'bad'},{payment:'-1'}]
        result=c.methods.validate_payments.call(view)
        assert.equal(view.form.payments.length,2)
        assert.equal(result.error_by_item,2)
        view.form.payments=[{id:9,payment:'0'}]
        c.methods.validate_payments.call(view)
        assert.equal(view.form.payments.length,1)
        view.form.total='1'
        view.form.payments=[1,2,3].map(id=>({id,payment:'0.333333333333',calculation_snapshot:{values:{payment:{n:'1',d:'3'}}}}))
        view.form.payments.push({payment:'0.000000000001'})
        result=c.methods.validate_payments.call(view)
        assert.equal(result.exceeds_total,true)
        view.form.total='0.333333333333'
        view.form.calculation_snapshot={values:{total:{n:'1',d:'3'}}}
        view.form.payments=[{id:1,payment:'0.333333333333',calculation_snapshot:{values:{payment:{n:'1',d:'3'}}}}]
        result=c.methods.validate_payments.call(view)
        assert.equal(result.exceeds_total,false)
    })
    test(`${name}: preparing an existing price preserves large decimal text`,()=>{
        const c=component(file), ctx={form:{currency_type_id:'VES',exchange_rate_sale:'1'},percentage_igv:'0.16'}
        const row={unit_price:'9007199254740993.12',quantity:'1',affectation_igv_type_id:'20',discounts:[],charges:[],
            item:{unit_price:'9007199254740993.12',currency_type_id:'VES',has_igv:true}}
        const prepared=name==='form.vue' ? c.methods.normalizeQuotationItem.call(ctx,row) : c.methods.onPrepareItems.call(ctx,[row])[0]
        assert.equal(prepared.item.unit_price,'9007199254740993.12')
        assert.equal(prepared.input_unit_price_value,'9007199254740993.12')
        assert.equal(row.item.unit_price,'9007199254740993.12')
        ctx.normalizeQuotationItem=c.methods.normalizeQuotationItem?.bind(ctx)
        const reopened=name==='form.vue' ? c.methods.onPrepareItems.call(ctx,[row])[0]
            : loadModule(path.resolve('resources/js/helpers/functions.js')).calculateRowItem(prepared,'VES','1','0.16')
        assert.equal(reopened.total,'9007199254740993.12')
        assert.equal(JSON.parse(JSON.stringify(reopened)).price_input.amount,'9007199254740993.12')
    })
    test(`${name}: submit accepts exact payment and blocks a real excess`,async()=>{
        const c=component(file), posts=[]
        const view={form:{id:1,customer_id:1,total:'0.30',payments:[{payment:'0.10'},{payment:'0.20'}]},
            payment_condition:'01',resource:'quotations',$refs:{},$message:{error:()=>{},success:()=>{}},
            validatePaymentDestination:()=>({error_by_item:0}),resetForm(){},
            $http:{post:async(endpoint,form)=>{posts.push(JSON.parse(JSON.stringify(form)));return {data:{success:false,message:'simulado'}}}}}
        view.validate_payments=c.methods.validate_payments.bind(view)
        await c.methods.submit.call(view)
        assert.equal(posts.length,1)
        assert.deepEqual(posts[0].payments.map(row=>row.payment),['0.10','0.20'])
        view.form.payments.push({payment:'0.01'})
        await c.methods.submit.call(view)
        assert.equal(posts.length,1)
    })
}
test('quotation item editor preserves original price as decimal text',()=>{
    const c=component('resources/js/views/tenant/quotations/partials/item.vue')
    assert.equal(c.methods.resolveEditUnitPrice({unit_price:'9007199254740993.12',item:{has_igv:true}}),'9007199254740993.12')
    assert.equal(c.methods.resolveEditUnitPrice({input_unit_price_value:'0',unit_price:'10',item:{has_igv:true}}),'0')
})
test('quotation item editor retains original currency and fraction unless the displayed price changes',async()=>{
    const c=component('resources/js/views/tenant/quotations/partials/item.vue')
    const {calculateRowItem}=loadModule(path.resolve('resources/js/helpers/functions.js'))
    for (const [sourceCurrency,documentCurrency,rate] of [['USD','VES','100'],['VES','USD','100']]) {
        const input={item:{description:'Producto',unit_price:'0.333333333333',currency_type_id:sourceCurrency,has_igv:true},
            quantity:'3',affectation_igv_type_id:'20',discounts:[],charges:[],
            price_input:{amount:{n:'1',d:'3'},currency_type_id:sourceCurrency,includes_iva:true}}
        const original=calculateRowItem(input,documentCurrency,rate,'0.16')
        original.currency_type_id=documentCurrency
        original.input_unit_price_value=original.unit_price
        async function edit(price) {
            let sent
            const view={recordItem:original,form:{...original,item:{...original.item},unit_price:price,has_igv:true},
                currencyTypeIdActive:documentCurrency,exchangeRateSale:rate,percentageIgv:'0.16',
                affectation_igv_types:[{id:'20'}],item_unit_type:[],validateQuantity(){},validateTotalItem:()=>({}),
                resolveEditUnitPrice:c.methods.resolveEditUnitPrice,initForm(){},close(){},$emit:(event,row)=>{sent=row},
                $message:{error:message=>{throw Error(message)}}}
            await c.methods.clickAddItem.call(view)
            return sent
        }
        const same=await edit(original.unit_price)
        assert.deepEqual(JSON.parse(JSON.stringify(same.calculation_snapshot.values.total)),JSON.parse(JSON.stringify(original.calculation_snapshot.values.total)))
        assert.equal(same.price_input.currency_type_id,sourceCurrency)
        const changed=await edit('50')
        assert.equal(changed.total,'150')
        assert.equal(changed.price_input.currency_type_id,documentCurrency)
    }
})
test('Tensu totals preserve large amounts and fractions and use the settled amount for payment',()=>{
    const c=component('resources/js/views/tenant/documents/invoicetensu.vue')
    const view={form:{items:[{affectation_igv_type_id:'20',total_value:'9007199254740993.12',total_base_igv:'9007199254740993.12',total_igv:'0'}],discounts:[],charges:[],total_prepayment:'0'},form_payment:{}}
    c.methods.calculateTotal.call(view)
    assert.equal(view.form.total,'9007199254740993.12')
    assert.equal(view.form_payment.payment,'9007199254740993.12')
    assert.equal(view.form.calculation_snapshot.values.total.n,'225179981368524828')
    view.form.items=[{affectation_igv_type_id:'20',total_value:'0.333333333333',total_base_igv:'0.333333333333',total_igv:'0',calculation_snapshot:{values:{total_value:{n:'1',d:'3'},total_base_igv:{n:'1',d:'3'}}}}]
    c.methods.calculateTotal.call(view)
    assert.equal(view.form.calculation_snapshot.values.total.n,'1')
    assert.equal(view.form.calculation_snapshot.values.total.d,'3')
    assert.equal(view.form_payment.payment,'0.33')
})
test('Tensu totals real taxed and exempt lines and keeps legacy export rows intact',()=>{
    const c=component('resources/js/views/tenant/documents/invoicetensu.vue')
    const {calculateRowItem}=loadModule(path.resolve('resources/js/helpers/functions.js'))
    const make=(price,type)=>calculateRowItem({item:{unit_price:price,currency_type_id:'VES'},quantity:'1',affectation_igv_type_id:type,discounts:[],charges:[]},'VES','1','0.16')
    const taxed=make('500','10'), exempt=make('100','20')
    const legacyExport={...make('25','20'),affectation_igv_type_id:'40'}
    const view={form:{items:[taxed,exempt,legacyExport],discounts:[],charges:[],total_prepayment:'0'},form_payment:{}}
    c.methods.calculateTotal.call(view)
    assert.equal(view.form.total,'625')
    assert.equal(view.form.total_exonerated,'100')
    assert.equal(view.form.total_exportation,'25')
    assert.equal(view.form_payment.payment,'625')
    assert.equal(legacyExport.affectation_igv_type_id,'40')
    assert.equal(view.form.calculation_snapshot.values.total.n,'625')
})
// ######## FIN PRUEBAS CALCULOS EXACTOS COTIZACIONES ########
