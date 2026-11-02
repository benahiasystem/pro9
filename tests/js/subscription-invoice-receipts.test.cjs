const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const babel = require('@babel/core')
const compiler = require('vue-template-compiler')
const load = require('./helpers/load-module.cjs')

for (const moduleName of ['Suscription', 'FullSuscription']) {
    const filename = `modules/${moduleName}/Resources/assets/js/payment_receipt/partials/option_documents.vue`
    const parsed = compiler.parseComponent(fs.readFileSync(filename, 'utf8'))
    const exported = {}
    const code = babel.transformSync(parsed.script.content, {configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code
    vm.runInNewContext(code, {exports:exported, moment: () => ({format: () => '2026-10-01'}), $:Object.assign(()=>[],{each(){}}), require: name => {
        if (name.endsWith('.vue') || name === '@mixins/fiscal-payment-settings') return {}
        if (name.startsWith('@helpers/')) return load(path.resolve('resources/js/helpers', name.slice('@helpers/'.length)+'.js'))
        return require(name)
    }})
    const component = exported.default
    test(`${moduleName} initializes a credit note with its installments and previous receipt`, () => {
        const receipt = {id: 4, payment: '25', currency_type_id: 'USD'}
        const fee = {id: 22, sale_note_id: 9, date: '2026-11-01T04:00:00.000000Z', amount: '75.00', currency_type_id: 'VES', payment_method_type_id: '06'}
        const source = {id: 9, currency_type_id: 'VES', exchange_rate_sale: '40', total: '100',
            payment_condition_id: '02', payments: [receipt], fee: [fee], items: []}
        const ctx = {...component.data(), form: {id: 9, sale_note: source, recorded_payments:[{
            id:4,payment:'25',currency_type_id:'USD',payment_destination_description:'Cuenta USD histórica',payment_destination_id:2
        }]}, document: {}}
        for (const [name, method] of Object.entries(component.methods)) ctx[name] = method.bind(ctx)
        ctx.assignDocument()
        assert.equal(ctx.document.payment_condition_id, '02')
        assert.equal(ctx.document.payments[0].payment, '25')
        assert.equal(ctx.document.payments[0].payment_destination_description, 'Cuenta USD histórica')
        assert.equal(ctx.document.payments[0].payment_destination_id, 2)
        assert.equal(ctx.document.fee.length, 1)
        assert.equal(ctx.document.fee[0].amount, '75.00')
        assert.equal(ctx.document.fee[0].date, '2026-11-01')
        assert.equal(ctx.document.fee[0].id, null)
        assert.equal(ctx.document.fee[0].payment_method_type_id, '06')
        assert.equal(ctx.document.fee[0].currency_type_id, 'VES')
        assert.notEqual(ctx.document.fee[0], fee)
        source.fee = []
        ctx.assignDocument()
        assert.equal(ctx.document.fee.length, 1)
        assert.equal(ctx.document.fee[0].amount, '75.00')
        source.payment_condition_id = '01'
        ctx.assignDocument()
        assert.equal(ctx.document.fee.length, 0)
        assert.equal(ctx.document.payments[0].payment, '25')
    })
    test(`${moduleName} conversion cannot remove a registered or reused receipt`, () => {
        for (const origin of [{id:4},{source_sale_note_payment_id:4},{source_quotation_payment_id:4},
            {source_technical_service_payment_id:4},{payment_method_snapshot:{receipt_origin:{id:4}}}]) {
            const receipt={payment:'25',...origin},ctx={document:{payments:[receipt]}}
            component.methods.clickCancel.call(ctx,0)
            assert.equal(ctx.document.payments[0],receipt)
        }
        const ctx={document:{payments:[{id:null,payment:'10'}]}}
        component.methods.clickCancel.call(ctx,0)
        assert.equal(ctx.document.payments.length,0)
        ctx.document.payments=[{source_sale_note_payment_id:4,payment_destination_id:null}]
        assert.equal(component.methods.validatePaymentDestination.call(ctx).error_by_item,0)
        ctx.document.payments.push({id:null,payment_destination_id:null})
        assert.equal(component.methods.validatePaymentDestination.call(ctx).error_by_item,1)
        assert.match(parsed.template.content,/v-if="!isRecordedReceipt\(row\)"[\s\S]*?clickCancel\(index\)/)
    })
    test(`${moduleName} conversion retains the source rate and receipt details`, async () => {
        const requests=[],errors=[]
        const receipt={id:4,sale_note_id:9,payment_method_type_id:'13',currency_type_id:'USD',original_amount:'2.50',exchange_rate:'40',payment:'100',payment_destination_id:2}
        const ctx={...component.data(), form:{id:9}, document:{document_type_id:'01',date_of_issue:'2026-10-01',currency_type_id:'VES',exchange_rate_sale:'40',payments:[receipt]},
            ensureFiscalPaymentsReady:async()=>true,$message:{error:m=>errors.push(m)},$eventHub:{$emit(){}},$emit(){},
            $http:{post:async(url,data)=>{requests.push({url,data});return {data:{success:true,data:{id:7}}}},get:async url=>{requests.push({url});return {data:{}}}}}
        for(const [name,method] of Object.entries(component.methods)) ctx[name]=method.bind(ctx)
        ctx.resetDocument=()=>{}
        await ctx.submit()
        assert.deepEqual(errors,[])
        const post=requests.find(r=>r.data)
        assert.equal(post.url,'/documents')
        assert.equal(post.data.exchange_rate_sale,'40.00000000')
        assert.deepEqual(post.data.payments[0],receipt)
        assert.ok(!requests.some(r=>r.url.startsWith('/services/exchange/')))
        assert.deepEqual(compiler.compile(parsed.template.content).errors,[])
        assert.match(parsed.template.content,/Cobro registrado:/)
        assert.match(parsed.template.content,/<igtf-payment-fields/)
    })
    test(`${moduleName} keeps receipts and suggests only the unpaid credit balance`, () => {
        const receipt = {id: 4, payment: '25', currency_type_id: 'USD'}
        const ctx = {...component.data(), form: {sale_note: {fee: []}},
            document: {currency_type_id: 'VES', total: '100', payments: [receipt], fee: [], payment_condition_id: '02'}}
        for (const [name, method] of Object.entries(component.methods)) ctx[name] = method.bind(ctx)
        ctx.changePaymentCondition()
        assert.equal(ctx.document.payments[0], receipt)
        assert.equal(ctx.document.fee[0].amount, '75.00')
        ctx.document.payments.push({payment: '15'})
        component.watch['document.payments'].handler.call(ctx)
        assert.equal(ctx.document.fee[0].amount, '60.00')
        ctx.document.fee[0].amount = '33.00'
        ctx.document.payments[1].payment = '20'
        component.watch['document.payments'].handler.call(ctx)
        assert.equal(ctx.document.fee[0].amount, '33.00')
        ctx.document.payment_condition_id = '01'; ctx.changePaymentCondition()
        ctx.document.payment_condition_id = '02'; ctx.changePaymentCondition()
        assert.equal(ctx.document.payments.length, 2)
        assert.equal(ctx.document.fee[0].amount, '33.00')
        ctx.document.payments = []; ctx.document.fee = []
        ctx.changePaymentCondition()
        assert.equal(ctx.document.payments.length, 0)
        assert.equal(ctx.document.fee[0].amount, '100.00')
        assert.doesNotMatch(parsed.template.content, /v-show="document.payment_condition_id != '02'"/)
    })
    test(`${moduleName} sends receipts for credit and omits installments for cash`, async () => {
        const posts = []
        const ctx = {...component.data(), form: {id: 9}, document: {
            document_type_id: '01', date_of_issue: '2026-10-01', currency_type_id: 'VES', exchange_rate_sale: '40',
            payment_condition_id: '02', fee: [{amount: '75'}], payments: [{id: 4, payment: '25'}]},
            ensureFiscalPaymentsReady: async () => true, $message: {error: message => { throw Error(message) }},
            $eventHub: {$emit() {}}, $emit() {}, $http: {post: async (url, data) => {
                posts.push(data); return {data: {success: true, data: {id: 7}}}
            }, get: async () => ({data: {}})}}
        for (const [name, method] of Object.entries(component.methods)) ctx[name] = method.bind(ctx)
        ctx.resetDocument = () => {}
        await ctx.submit()
        assert.equal(posts[0].payment_condition_id, '02')
        assert.equal(posts[0].payments[0].payment, '25')
        assert.equal(posts[0].fee[0].amount, '75')
        ctx.document.payment_condition_id = '01'
        await ctx.submit()
        assert.equal(posts[1].payments[0].payment, '25')
        assert.equal(posts[1].fee.length, 0)
        assert.equal(ctx.document.fee[0].amount, '75')
    })
    test(`${moduleName} blocks conversion when no valid exchange rate is available`, async () => {
        const errors=[],requests=[]
        const ctx={...component.data(),document:{date_of_issue:'2026-10-01',currency_type_id:'USD',exchange_rate_sale:0,payments:[]},
            ensureFiscalPaymentsReady:async()=>true,$message:{error:m=>errors.push(m)},
            $http:{get:async url=>{requests.push(url);return {data:{sale:0}}},post:async()=>{throw Error('must not post')}}}
        for(const [name,method] of Object.entries(component.methods)) ctx[name]=method.bind(ctx)
        await ctx.submit()
        assert.equal(errors.length,1)
        assert.match(errors[0],/Tasa fuera de rango/)
        assert.deepEqual(requests,['/services/exchange/2026-10-01'])
    })
    test(`${moduleName} shows a validation error unrelated to payment and keeps the draft`, async () => {
        const errors=[]
        const receipt={id:4,payment:'25'},fee={amount:'75'}
        const ctx={...component.data(),form:{id:9},document:{document_type_id:'01',date_of_issue:'2026-10-01',
            currency_type_id:'VES',exchange_rate_sale:'40',payment_condition_id:'02',fee:[fee],payments:[receipt]},
            ensureFiscalPaymentsReady:async()=>true,$message:{error:m=>errors.push(m)},
            $http:{post:async()=>{throw {response:{status:422,data:{errors:{series_id:['Seleccione una serie.']}}}}}}}
        for(const [name,method] of Object.entries(component.methods)) ctx[name]=method.bind(ctx)
        await ctx.submit()
        assert.deepEqual(errors,['Seleccione una serie.'])
        assert.equal(ctx.document.payments[0],receipt)
        assert.equal(ctx.document.fee[0],fee)
    })
}
