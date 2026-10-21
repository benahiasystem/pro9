const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'),vm=require('node:vm'),babel=require('@babel/core'),compiler=require('vue-template-compiler')
const runtime=require('./support/exact-runtime.cjs')
const {prepareCommercialPayments}=runtime('exact-commercial')
const {exactAmount}=runtime('exchange-rate-math')

test('PHP and JavaScript tender fixtures conserve cash and reject fictitious change',()=>{
    for(const c of JSON.parse(fs.readFileSync('tests/fixtures/exact_tenders.json','utf8'))) {
        if(c.invalid) { assert.throws(()=>prepareCommercialPayments(c.payable,c.rows),undefined,c.name);continue }
        const result=prepareCommercialPayments(c.payable,c.rows)
        assert.deepEqual(result.payments.map(row=>row.payment),c.net,c.name)
        assert.deepEqual(result.payments.map(row=>row.change),c.change,c.name)
        assert.equal(result.inputs[0]?.payment,String(c.rows[0]?.payment))
    }
})

function load(file) {
    const parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'))
    assert.deepEqual(compiler.compile(parsed.template.content).errors,[])
    const exported={}
    vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
        {exports:exported,require:name=>runtime(name)|| (name==='moment'? ()=>({}) : {})})
    return exported.default
}
test('sale note totals format large decimal text without Number conversion',()=>{
    const component=load('resources/js/components/DataTableSaleNote.vue')
    assert.equal(component.methods.formatDecimal.call({decimal_quantity:12},'999999999999999999.123456789012'),'999,999,999,999,999,999.123456789012')
    const ctx={decimal_quantity:'2',totals:{total_pen:'0.005',calculation_snapshot:{version:1,values:{total_pen:{n:'499999999999999999',d:'100000000000000000000'}}}}}
    assert.equal(component.methods.formatDecimal.call(ctx,'0.005','total_pen'),'0.00')
})

test('payment inputs remove consecutive zero rows while retaining exact requested strings',()=>{
    const result=prepareCommercialPayments('500',[{payment:'0'},{payment:'0'},{payment:'500.004',payment_method_type_id:'01'}])
    assert.equal(result.inputs.length,1)
    assert.equal(result.inputs[0].payment,'500.004')
    assert.equal(result.total.compare(exactAmount('500')),0)
})

test('sale note payment dialog uses fractions for residual debt and adjustment visibility',()=>{
    const component=load('resources/js/views/tenant/sale_notes/partials/payments.vue')
    const row={cash_adjustment:'0',calculation_snapshot:{version:1,values:{cash_adjustment:{n:'-1',d:'10000000000000'}}}}
    assert.equal(component.methods.hasCashAdjustment(row),true)
    assert.equal(component.methods.cashAdjustmentText(row),'-1/10000000000000')
    const messages=[]
    const context={records:[{payment:'0.01'}],document:{total_difference:'0',calculation_snapshot:{version:1,values:{total_difference:{n:'1',d:'10000000000000'}}}},
        $message:{error:message=>messages.push(message)},$http:{post:()=>{throw new Error('No debe transmitir un cobro excesivo')}}}
    component.methods.clickSubmit.call(context,0)
    assert.equal(messages.length,1)
    const documents=load('resources/js/views/tenant/documents/partials/payments.vue')
    const tiny={payment_adjustment:'0',calculation_snapshot:{version:1,values:{payment_adjustment:{n:'-1',d:'100000000000000000000'}}}}
    assert.equal(documents.methods.hasPaymentAdjustment(tiny),true)
    assert.equal(documents.methods.paymentAdjustmentText(tiny),'-1/100000000000000000000')
})
