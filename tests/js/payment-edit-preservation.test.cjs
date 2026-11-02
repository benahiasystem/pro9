const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const t = require('@babel/types')
const {paymentConditionPayload, withoutDraftRetentions} = require('./helpers/load-module.cjs')('resources/js/helpers/document-creation.js')

test('payment submission preserves draft installments and receipts while normalizing the sent condition', () => {
    const receipt={id:4,payment:'1'}, fee={date:'2026-12-15',amount:'99'}
    const form={payment_condition_id:'03',payments:[receipt],fee:[fee]}
    const credit=paymentConditionPayload(form)
    assert.equal(credit.payment_condition_id,'02')
    assert.equal(credit.fee[0],fee)
    assert.equal(credit.payments[0],receipt)
    assert.equal(form.payment_condition_id,'03')
    form.payment_condition_id='01'
    const cash=paymentConditionPayload(form)
    assert.equal(cash.fee.length,0)
    assert.equal(cash.payments[0],receipt)
    assert.equal(form.fee[0],fee)
})

for (const path of ['resources/js/views/tenant/documents/invoice.vue','resources/js/views/tenant/documents/invoiceupdate.vue']) {
    test(`${path}: failed submission keeps the draft and sends only installments for credit`, async () => {
        const submit=method(path,'submit',{paymentConditionPayload})
        const posts=[], receipt={id:4,payment:'1'}, fee={date:'2026-12-15',amount:'99'}
        const ctx={form:{id:9,payment_condition_id:'03',payments:[receipt],fee:[fee]},isUpdate:false,is_receivable:false,
            resource:'documents',validate_payments:async()=>({error_by_item:0}),validatePaymentDestination:async()=>({error_by_item:0}),
            deleteInitGuides:async()=>{},asignPlateNumberToItems:async()=>{},ensureFiscalPaymentsReady:async()=>true,
            setDefaultDocumentType(){},$message:{error(){}},$http:{post:(url,data)=>{posts.push(data); return Promise.resolve({data:{success:false,message:'test'}})}}}
        await submit.call(ctx); await new Promise(resolve=>setImmediate(resolve))
        assert.equal(posts[0].payment_condition_id,'02')
        assert.equal(posts[0].fee[0],fee)
        assert.equal(posts[0].payments[0],receipt)
        assert.equal(ctx.form.payment_condition_id,'03')
        ctx.form.payment_condition_id='01'
        await submit.call(ctx); await new Promise(resolve=>setImmediate(resolve))
        assert.equal(posts[1].fee.length,0)
        assert.equal(ctx.form.fee[0],fee)
        assert.equal(ctx.form.payments[0],receipt)
    })
}

test('invoice creation and preview normalize a copy without losing draft installments', async () => {
    const path='resources/js/views/tenant/documents/invoice_generate.vue'
    const submission=method(path,'documentSubmission',{paymentConditionPayload,withoutDraftRetentions})
    const preview=method(path,'preview',{paymentConditionPayload,withoutDraftRetentions,URL:{createObjectURL:()=> 'blob:test'}})
    const posts=[], fee={date:'2026-12-15',amount:'99'}, receipt={id:4,payment:'1'}
    const ctx={isUpdateDocument:false,resource:'documents',form:{payment_condition_id:'03',fee:[fee],payments:[receipt],actions:{format_pdf:'a4'}},
        $http:{post:async(url,data)=>{posts.push(data);return {headers:{'content-type':'application/pdf'},data:{size:1}}}}}
    assert.equal(submission.call(ctx).payment_condition_id,'02')
    await preview.call(ctx,'ticket')
    assert.equal(posts[0].payment_condition_id,'02')
    assert.equal(posts[0].fee[0],fee)
    assert.equal(ctx.form.payment_condition_id,'03')
    assert.equal(ctx.form.actions.format_pdf,'a4')
    ctx.form.payment_condition_id='01'
    assert.equal(submission.call(ctx).fee.length,0)
    await preview.call(ctx,'ticket')
    assert.equal(posts[1].fee.length,0)
    assert.equal(ctx.form.fee[0],fee)
    assert.equal(ctx.form.payments[0],receipt)
})

function method(path, name, scope = {}) {
    const script = compiler.parseComponent(fs.readFileSync(path, 'utf8')).script.content
    const ast = parser.parse(script, {sourceType:'module'})
    const component = ast.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
    const methods = component.properties.find(node => node.key?.name === 'methods').value
    const original = methods.properties.find(node => node.key?.name === name)
    assert.ok(original, `${path}: ${name}`)
    const fn = t.functionExpression(null, original.params, original.body, original.generator, original.async)
    return vm.runInNewContext(`(${generate(fn).code})`, scope)
}

for (const path of ['resources/js/views/tenant/quotations/form_edit.vue',
    'resources/js/views/tenant/sale_notes/form.vue', 'modules/Sale/Resources/assets/js/views/contracts/form.vue']) {
    test(`${path}: default destination preserves existing payments and accepts an empty list`, () => {
        const select = method(path, 'selectDestinationSale')
        const row = {id:12, payment:'50', payment_destination_id:7, date_of_payment:'2026-10-01'}
        const ctx = {config:{destination_sale:true}, configuration:{destination_sale:true}, showPayments:true,
            form:{payments:[]}, payment_destinations:[{id:'cash'},{id:9}],
            classifiedPaymentDestinations:()=>[{id:9}]}
        assert.doesNotThrow(() => select.call(ctx))
        ctx.form.payments=[row]
        select.call(ctx)
        assert.equal(row.payment_destination_id,7)
        ctx.form.payments=[{id:null, payment_destination_id:null}]
        select.call(ctx)
        assert.equal(ctx.form.payments[0].payment_destination_id,9)
    })
}

test('changing a sale-note date does not change the date of recorded receipts', () => {
    const update = method('resources/js/views/tenant/sale_notes/form.vue', 'assignmentDateOfPayment')
    const ctx = {form:{date_of_issue:'2026-10-28', payments:[
        {id:5,date_of_payment:'2026-10-01'}, {id:null,date_of_payment:'2026-10-02'}]}}
    update.call(ctx)
    assert.equal(ctx.form.payments[0].date_of_payment,'2026-10-01')
    assert.equal(ctx.form.payments[1].date_of_payment,'2026-10-28')
})

test('a rejected sale note keeps its draft currency, received amount and rate', async () => {
    const submit=method('resources/js/views/tenant/sale_notes/form.vue', 'submit', {
        exactValue:()=> '1', paymentValidationMessage:()=> 'Cuenta incompatible', paymentConditionPayload,
    })
    const payment={currency_type_id:'VES',original_amount:'400',exchange_rate:'400',payment:'1',payment_destination_id:8}
    const notices=[], posts=[]
    const ctx={config:{currency_type_id:'VES'},currency_types:[{id:'VES'},{id:'USD'}],
        form:{customer_id:1,currency_type_id:'USD',exchange_rate_sale:'400',payments:[payment],payment_condition_id:'03',fee:[{amount:'99'}]},
        $refs:{},enabled_payments:true,resource:'sale-notes',
        validate_payments:async()=>({error_by_item:0,acum_total:{compare:()=>0}}),
        validatePaymentDestination:async()=>({error_by_item:0}),
        $message:{error:message=>notices.push(message)},
        $http:{post:(url,data)=>{posts.push(data); return Promise.reject({response:{status:422,data:{payment_destination_id:['Cuenta incompatible']}}})}}}
    await submit.call(ctx)
    await new Promise(resolve=>setImmediate(resolve))
    assert.equal(ctx.form.currency_type_id,'USD')
    assert.equal(ctx.form.exchange_rate_sale,'400')
    assert.equal(ctx.form.payments[0].original_amount,'400')
    assert.equal(ctx.form.payments[0].exchange_rate,'400')
    assert.equal(ctx.form.payment_condition_id,'03')
    assert.equal(ctx.form.fee[0].amount,'99')
    assert.equal(posts[0].payment_condition_id,'02')
    assert.equal(posts[0].fee[0].amount,'99')
    assert.equal(ctx.loading_submit,false)
    assert.deepEqual(notices,['Cuenta incompatible'])
})

test('resetting a sale note after success selects the configured active currency', () => {
    const reset=method('resources/js/views/tenant/sale_notes/form.vue', 'resetForm')
    const ctx={config:{currency_type_id:'VES'},currency_types:[{id:'VES'},{id:'USD'}],establishments:[],
        form:{currency_type_id:'USD'},initForm(){this.form={}},changeEstablishment(){},changeDateOfIssue(){},
        changeCurrencyType(){},allCustomers(){},setDefaultSerieByDocument(){}}
    reset.call(ctx)
    assert.equal(ctx.form.currency_type_id,'VES')
})
