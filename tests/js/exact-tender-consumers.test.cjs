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
        {exports:exported,require:name=>runtime(name)|| (name==='moment'? ()=>({}) : name.includes('vuex') ? {mapState:()=>({}),mapActions:()=>({})} : {})})
    return exported.default
}
test('sale note totals format large decimal text without Number conversion',()=>{
    const component=load('resources/js/components/DataTableSaleNote.vue')
    assert.equal(component.methods.formatDecimal.call({decimal_quantity:12},'999999999999999999.123456789012'),'999,999,999,999,999,999.123456789012')
    const ctx={decimal_quantity:'2',totals:{total_pen:'0.005',calculation_snapshot:{version:1,values:{total_pen:{n:'499999999999999999',d:'100000000000000000000'}}}}}
    assert.equal(component.methods.formatDecimal.call(ctx,'0.005','total_pen'),'0.00')
})

test('payment inputs remove consecutive zero rows while retaining exact requested strings',()=>{
    const result=prepareCommercialPayments('500',[{payment:'0'},{payment:'0'},{payment:'500.004',payment_method_type_id:'01',collection_kind:'cash'}])
    assert.equal(result.inputs.length,1)
    assert.equal(result.inputs[0].payment,'500.004')
    assert.equal(result.total.compare(exactAmount('500')),0)
})

test('change follows classification even when familiar IDs are reassigned',()=>{
    const rows=[{payment:'100',payment_method_type_id:'01',collection_kind:'account'}, {payment:'20',payment_method_type_id:'04',collection_kind:'cash'}]
    const result=prepareCommercialPayments('110',rows)
    assert.deepEqual(result.payments.map(row=>row.change),['0','10'])
    assert.throws(()=>prepareCommercialPayments('100',[{payment:'110',payment_method_type_id:'01',collection_kind:'account'}]))
    assert.throws(()=>prepareCommercialPayments('100',[{payment:'110',payment_method_type_id:'01'}]))
})

test('a prior cash receipt cannot finance change for a new bank collection',()=>{
    const old={payment:'20',payment_method_type_id:'04',collection_kind:'cash',source_quotation_payment_id:8}
    const bank={payment:'100',payment_method_type_id:'01',collection_kind:'account'}
    assert.throws(()=>prepareCommercialPayments('110',[old,bank]))
    const fresh={payment:'10',payment_method_type_id:'04',collection_kind:'cash'}
    const result=prepareCommercialPayments('120',[old,bank,fresh])
    assert.deepEqual(result.payments.map(row=>row.change),['0','0','10'])
})

test('sale note and invoice forms keep reused receipts and use historical classification',()=>{
    for (const path of ['resources/js/views/tenant/sale_notes/form.vue','resources/js/views/tenant/documents/invoice_generate.vue']) {
        const component=load(path)
        for (const origin of [{id:8},{source_quotation_payment_id:8},{source_technical_service_payment_id:8},{source_sale_note_payment_id:8},
            {payment_method_snapshot:{receipt_origin:{id:8},collection_kind:'cash'}},{receipt_parent_id:8}]) {
            const row={payment:'20',payment_method_type_id:'04',collection_kind:'cash',...origin}
            const ctx={form:{payments:[row]},calculatePayments(){throw Error('El cobro previo fue retirado')}}
            component.methods.clickCancel.call(ctx,0)
            assert.equal(ctx.form.payments[0],row,path)
        }
    }
    const note=load('resources/js/views/tenant/sale_notes/form.vue')
    const old={payment:'20',payment_method_type_id:'04',source_quotation_payment_id:8,payment_method_snapshot:{collection_kind:'cash'}}
    const bank={payment:'100',payment_method_type_id:'01'}
    const ctx={form:{total_payable:'110',payments:[old,bank]},payment_method_types:[{id:'04',collection_kind:'account'},{id:'01',collection_kind:'account'}]}
    assert.equal(note.methods.validate_payments.call(ctx).error_by_item,1)
    assert.equal(ctx.form.payments[0],old)
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
