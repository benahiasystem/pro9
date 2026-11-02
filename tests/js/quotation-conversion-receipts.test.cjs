const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default

test('invoice initialization never overwrites recorded or selected destinations', () => {
    const path=require('node:path')
    const helpers=require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/payment-classification.js'))
    for (const name of ['invoice.vue','invoice_generate.vue','invoiceupdate.vue']) {
        const component=compiler.parseComponent(fs.readFileSync(`resources/js/views/tenant/documents/${name}`,'utf8'))
        const parsed=parser.parse(component.script.content,{sourceType:'module'})
        const methods=parsed.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
        const get=method=>vm.runInNewContext(`({${generate(methods.properties.find(node=>node.key?.name===method)).code}})[${JSON.stringify(method)}]`,helpers)
        const change=get('changeDestinationSale'),update=get('updateChangeDestinationSale')
        const methodsCatalog=[{id:'01',collection_kind:'account',currency_type_id:'USD',is_active:true},
            {id:'04',collection_kind:'cash',currency_type_id:'VES',cash_currency_type_id:'VES',is_active:true}]
        const receipt={id:12,quotation_id:5,payment_method_type_id:'01',currency_type_id:'USD',payment_destination_id:2}
        const ctx={config:{destination_sale:true},payment_destinations:[{id:'cash'},{id:2,currency_type_id:'USD'}],
            payment_method_types:methodsCatalog,form:{payment_condition_id:'01',currency_type_id:'VES',payments:[receipt]}}
        ctx.changeDestinationSale=change.bind(ctx)
        change.call(ctx);update.call(ctx)
        assert.equal(receipt.payment_destination_id,2,`${name}: destino histórico`)
        const cash={id:null,payment_method_type_id:'04',currency_type_id:'VES',payment_destination_id:null}
        ctx.form.payments=[cash];update.call(ctx);assert.equal(cash.payment_destination_id,'cash',`${name}: efectivo compatible`)
        cash.payment_destination_id=2;change.call(ctx);assert.equal(cash.payment_destination_id,2,`${name}: destino elegido`)
        ctx.form.payments=[{payment_method_type_id:'01',currency_type_id:'USD',payment_destination_id:null}]
        change.call(ctx);assert.equal(ctx.form.payments[0].payment_destination_id,null,`${name}: banco sin destino propuesto`)
        ctx.form.payments=[{source_quotation_payment_id:12,payment_method_type_id:'04',currency_type_id:'VES',payment_destination_id:null}]
        change.call(ctx);assert.equal(ctx.form.payments[0].payment_destination_id,null,`${name}: origen explícito`)
        ctx.form.payments=[{payment_method_type_id:'04',currency_type_id:'USD',payment_destination_id:null}]
        change.call(ctx);assert.equal(ctx.form.payments[0].payment_destination_id,null,`${name}: efectivo en otra moneda`)
        methodsCatalog[1].is_active=false
        ctx.form.payments=[{payment_method_type_id:'04',currency_type_id:'VES',payment_destination_id:null}]
        change.call(ctx);assert.equal(ctx.form.payments[0].payment_destination_id,null,`${name}: efectivo inactivo`)
        ctx.form.payments=[];change.call(ctx)
        assert.deepEqual(compiler.compile(component.template.content).errors,[])
    }
})

test('quotation credit preserves real receipts and never fabricates a credit payment', () => {
    const quote=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/quotations/form.vue','utf8'))
    const parsed=parser.parse(quote.script.content,{sourceType:'module'})
    const methods=parsed.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
    const helper=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/payment-classification.js'))
    const get=name=>vm.runInNewContext(`({${generate(methods.properties.find(node=>node.key?.name===name)).code}})[${JSON.stringify(name)}]`,{...helper,moment:()=>({format:()=> '2026-10-01'})})
    const change=get('changePaymentCondition'),add=get('clickAddPayment')
    const payment={id:null,payment:'25',payment_method_type_id:'01',payment_destination_id:2}
    const ctx={payment_condition:'02',form:{currency_type_id:'VES',payment_method_type_id:'01',payments:[payment]},payment_destinations:[{id:'cash'}],
        payment_method_types:[{id:'01',collection_kind:'account',currency_type_id:'USD',is_active:true}, {id:'04',collection_kind:'cash',currency_type_id:'VES',is_active:true}, {id:'07',collection_kind:'credit',is_active:true}],setTotalDefaultPayment:()=>{}}
    change.call(ctx);assert.equal(ctx.form.payment_method_type_id,'07');assert.equal(ctx.form.payments.length,1);assert.equal(ctx.form.payments[0],payment)
    add.call(ctx);assert.equal(ctx.form.payments[1].payment_method_type_id,'04');assert.equal(ctx.form.payments[1].payment_destination_id,'cash')
    ctx.form.payments=[];change.call(ctx);assert.equal(ctx.form.payments.length,0)
    assert.deepEqual(compiler.compile(quote.template.content).errors,[])
})

test('quotation default amount never replaces an entered or recorded receipt', () => {
    const quote=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/quotations/form.vue','utf8'))
    const parsed=parser.parse(quote.script.content,{sourceType:'module'})
    const methods=parsed.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
    const entry=methods.properties.find(node=>node.key?.name==='setTotalDefaultPayment')
    const exact=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/exchange-rate-math.js'))
    const run=vm.runInNewContext(`({${generate(entry).code}}).setTotalDefaultPayment`,{exactAmount:exact.exactAmount})
    const ctx={payment_condition:'01',form:{total:'100',payments:[{id:null,payment:'25'}]}}
    run.call(ctx);assert.equal(ctx.form.payments[0].payment,'25')
    ctx.form.payments=[{id:1,payment:'0'}];run.call(ctx);assert.equal(ctx.form.payments[0].payment,'0')
    ctx.form.payments=[{payment:'0'}];run.call(ctx);assert.equal(ctx.form.payments[0].payment,'100')
    ctx.payment_condition='02';ctx.form.payments=[{payment:'0'}];run.call(ctx);assert.equal(ctx.form.payments[0].payment,'0')
    assert.deepEqual(compiler.compile(quote.template.content).errors,[])
})

const source = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/quotations/partials/options.vue', 'utf8'))
const tree = parser.parse(source.script.content, {sourceType: 'module'})
const component = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
const methods = component.properties.find(node => node.key.name === 'methods').value
const submit = methods.properties.find(node => node.key?.name === 'submit')
const run = vm.runInNewContext(`({${generate(submit).code}}).submit`)
const change = methods.properties.find(node => node.key?.name === 'changePaymentCondition')
const runChange = vm.runInNewContext(`({${generate(change).code}}).changePaymentCondition`, {_: {isEmpty: value => !value || !Object.keys(value).length}})

test('quotation conversion sends a recorded receipt without changing its original data', async () => {
    const receipt = {id: 12, quotation_id: 5, payment_method_type_id: '13', currency_type_id: 'USD', original_amount: '5', exchange_rate: '10', payment: '50', payment_destination_id: 2, financial_intermediation: 'financial_institution'}
    const requests = []
    const ctx = {form: {quotation: {payments: [receipt]}}, document: {document_type_id: '01', payment_condition_id: '01', payments: [{...receipt}]},
        validateQuantityandSeriesLots: async () => ({success: true}), assignDocument: async () => {},
        validatePaymentDestination: async () => ({error_by_item: 0}), ensureFiscalPaymentsReady: async () => true,
        $message: {error() {}}, $http: {post: async (url, data) => {requests.push({url, data}); return {data: {success: false}}}}}
    await run.call(ctx)
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(requests.length, 1)
    assert.equal(requests[0].url, '/documents')
    assert.deepEqual(requests[0].data.payments[0], receipt)
    assert.deepEqual(compiler.compile(source.template.content).errors, [])
    const paymentRow = source.template.content.split('v-for="(row, index) in document.payments"')[1]
    assert.match(paymentRow, /<igtf-payment-fields/)
    assert.match(paymentRow, /<receipt-currency-fields/)
    assert.match(paymentRow, /Cobro registrado:/)
    assert.match(paymentRow, /Intermediación del cobro original/)
    assert.match(paymentRow, /:disabled="isRecordedReceipt\(row\)"/)
})

test('sale note conversion displays the original receipt and sends it unchanged', async () => {
    const receipt = {id: 12, quotation_id: 5, payment_method_type_id: '10', currency_type_id: 'USD', original_amount: '5', exchange_rate: '10', payment: '50', payment_destination_id: 2,
        payment_method_snapshot: {description: 'Banco anterior'}, payment_destination_description: 'Cuenta USD histórica'}
    const start=source.template.content.lastIndexOf('<!-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## -->',source.template.content.indexOf('Cobro registrado de la cotización'))
    const end=source.template.content.indexOf('<!-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## -->',start)
    assert.ok(start>=0 && end>start)
    const block=source.template.content.slice(start,end)
    const compiled=compiler.compileToFunctions(`<div>${block}</div>`)
    const Vue=require('vue')
    const render=(invoice, payments)=>new Vue({data:()=>({is_document_type_invoice:invoice,document:{currency_type_id:'VES',payments}}),methods:{isRecordedReceipt:require('./support/exact-runtime.cjs')('payment-classification').isRecordedReceipt},...compiled})._render()
    const text=node=>[node.text || '',...(node.children || []).map(text)].join(' ')
    const note=text(render(false,[receipt,{id:null,payment:'999'}]))
    for (const expected of ['Banco anterior','Cuenta USD histórica','Recibido: 5 USD','Aplicado: 50 VES','Tasa VES por USD: 10']) assert.ok(note.includes(expected),note)
    assert.ok(!note.includes('999'))
    assert.ok(!text(render(true,[receipt])).includes('Cobro registrado de la cotización'))
    const totalLabel=source.template.content.match(/<label class="control-label">\{\{ document\.currency_type_id \}\} \{\{ document\.total \}\}<\/label>/)
    assert.ok(totalLabel,'El diálogo debe mostrar la moneda y el total de la cotización')
    const totalRender=compiler.compileToFunctions(`<div>${totalLabel[0]}</div>`)
    assert.ok(text(new Vue({data:()=>({document:{currency_type_id:'VES',total:'100'}}),...totalRender})._render()).includes('VES 100'))
    const calls=[]
    const ctx={form:{quotation:{payments:[receipt]}},document:{document_type_id:'nv',payment_condition_id:'01',payments:[{...receipt}]},
        validateQuantityandSeriesLots:async()=>({success:true}),assignDocument:async()=>{},validatePaymentDestination:async()=>({error_by_item:0}),
        ensureFiscalPaymentsReady:async()=>true,$message:{error(){}},$http:{post:async(url,data)=>{calls.push({url,data});return {data:{success:false}}}}}
    await run.call(ctx)
    await new Promise(resolve=>setImmediate(resolve))
    assert.equal(calls[0].url,'/sale-notes')
    assert.deepEqual(calls[0].data.payments[0],receipt)
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
})

test('credit installments normalize the form condition while preserving fees and receipts', async () => {
    const receipt = {id: 12, quotation_id: 5, payment_method_type_id: '13', currency_type_id: 'USD', original_amount: '5', exchange_rate: '10', payment: '50', payment_destination_id: 2}
    const fees = [{date: '2026-11-26', amount: '60'}, {date: '2026-12-26', amount: '122'}]
    const calls = []
    const ctx = {form: {quotation: {payments: [receipt]}}, document: {document_type_id: '01', payment_condition_id: '03', payments: [{...receipt}], fee: fees},
        validateQuantityandSeriesLots: async () => ({success: true}), assignDocument: async () => {},
        validatePaymentDestination: async () => ({error_by_item: 0}), ensureFiscalPaymentsReady: async () => true,
        $message: {error() {}}, $http: {post: async (url, data) => {calls.push({url, data}); return {data: {success: false}}}}}
    await run.call(ctx)
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(calls.length, 1)
    assert.equal(calls[0].data.payment_condition_id, '02')
    assert.deepEqual(calls[0].data.fee, fees)
    assert.deepEqual(calls[0].data.payments[0], receipt)
    assert.equal(ctx.document.payment_condition_id, '03')
    ctx.document.payment_condition_id = '01'
    await run.call(ctx)
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(calls[1].data.fee.length, 0)
    assert.equal(ctx.document.fee, fees)
})

test('switching quotation payment conditions preserves edited installments and receipt data', () => {
    const originalFee = {date: '2026-12-01', amount: '75.00'}
    const receipt = {id: 12, payment: '25', currency_type_id: 'USD'}
    const ctx = {form: {quotation: {payments: [receipt], fee: [originalFee]}},
        document: {payment_condition_id: '02', fee: [], payments: [{...receipt}], retention: {}}}
    runChange.call(ctx)
    ctx.document.fee[0].amount = '65.00'
    assert.equal(originalFee.amount, '75.00')
    ctx.document.payment_condition_id = '01'; runChange.call(ctx)
    ctx.document.payment_condition_id = '03'; runChange.call(ctx)
    assert.equal(ctx.document.fee[0].amount, '65.00')
    assert.equal(ctx.document.payments.length, 1)
    assert.equal(ctx.document.payments[0].id, 12)
})

test('sale note conversion exposes credit installments and does not add an invisible cash payment', () => {
    const ctx = {is_document_type_invoice: false, form: {quotation: {payments: []}},
        document: {payment_condition_id: '01', fee: [], payments: [], retention: {}},
        clickAddPayment() { throw new Error('La nota no debe añadir un pago invisible') }}
    runChange.call(ctx)
    assert.equal(ctx.document.payments.length, 0)
    const start = source.template.content.indexOf('<!-- Crédito con cuotas -->')
    const paymentStart = source.template.content.indexOf('<!-- Contado -->', start)
    const before = source.template.content.slice(0, start)
    assert.ok(before.lastIndexOf('<template v-if="is_document_type_invoice">') < before.lastIndexOf('</template>'))
    assert.ok(source.template.content.slice(start, paymentStart).includes('<template v-if="is_document_type_invoice">'))
})

test('removing a draft payment cannot erase the quotation receipt used by the guard', () => {
    const original = [{id: 12, currency_type_id: 'USD'}]
    const ctx = {form: {quotation: {payments: original}}, document: {payment_condition_id: '01', fee: [], payments: [], retention: {}}}
    runChange.call(ctx)
    ctx.document.payments.splice(0, 1)
    assert.equal(original.length, 1)
    assert.equal(original[0].id, 12)
})

test('loading a credit quotation preserves its receipt and suggests only the unpaid balance', async () => {
    const load=require('./helpers/load-module.cjs'), path=require('node:path')
    const helpers={...load(path.resolve('resources/js/helpers/document-creation.js')),
        _:{isEmpty:value=>!value || !Object.keys(value).length},hydrateItemLots:()=>{},
        moment:()=>({add:()=>({format:()=> '2026-11-01'})})}
    const method=name=>vm.runInNewContext(`({${generate(methods.properties.find(node=>node.key?.name===name)).code}}).${name}`,helpers)
    const receipt={id:12,quotation_id:5,payment:'50',currency_type_id:'USD',original_amount:'5',exchange_rate:'10'}
    const quotation={establishment_id:1,date_of_issue:'2026-10-31',currency_type_id:'VES',total:'100',exchange_rate_sale:'10',payment_condition_id:'02',payments:[receipt],items:[]}
    const ctx={resource:'quotations',recordId:5,type:'create',document:{retention:{},fee:[],payments:[]},credit_payment_metod:[{id:'09',number_days:0}],
        $http:{get:async()=>({data:{data:{quotation,identifier:'COT-5'}}})},getPercentageIgv(){},getCustomer(){}}
    for (const name of ['changePaymentCondition','clickAddFeeNew','calculateFee','getTotal']) ctx[name]=method(name).bind(ctx)
    await method('getRecord').call(ctx)
    assert.equal(ctx.document.payment_condition_id,'02')
    assert.equal(ctx.document.payments.length,1)
    assert.deepEqual(JSON.parse(JSON.stringify(ctx.document.payments[0])),receipt)
    assert.equal(ctx.document.fee.length,1)
    assert.equal(ctx.document.fee[0].amount,'50.00')
    assert.notEqual(ctx.document.payments[0],receipt)
    quotation.payment_condition_id=undefined
    await method('getRecord').call(ctx)
    assert.equal(ctx.document.payment_condition_id,'01')
})

test('recorded quotation receipts cannot be removed while draft payments can', () => {
    const entry=methods.properties.find(node=>node.key?.name==='clickCancel')
    const cancel=vm.runInNewContext(`({${generate(entry).code}}).clickCancel`,{isRecordedReceipt:require('./support/exact-runtime.cjs')('payment-classification').isRecordedReceipt})
    const original={id:12,payment:'50'},draft={id:null,payment:'25'}
    const ctx={document:{payments:[original,draft]}}
    cancel.call(ctx,0)
    assert.deepEqual(ctx.document.payments,[original,draft])
    cancel.call(ctx,1)
    assert.deepEqual(ctx.document.payments,[original])
    const button=source.template.content.match(/<button\s+v-if="!isRecordedReceipt\(row\)"[\s\S]*?@click\.prevent="clickCancel\(index\)"/)
    assert.ok(button,'La acción de eliminar sólo debe mostrarse para pagos nuevos')
})

test('invoice creation keeps quotation receipts read-only and permits removing drafts', () => {
    const invoice=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue','utf8'))
    const parsed=parser.parse(invoice.script.content,{sourceType:'module'})
    const methods=parsed.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
    const cancel=vm.runInNewContext(`({${generate(methods.properties.find(node=>node.key?.name==='clickCancel')).code}}).clickCancel`,{isRecordedReceipt:require('./support/exact-runtime.cjs')('payment-classification').isRecordedReceipt})
    let recalculations=0
    const original={id:12,payment:'50'},draft={id:null,payment:'25'}
    const ctx={form:{payments:[original,draft]},calculatePayments:()=>recalculations++}
    cancel.call(ctx,0);assert.deepEqual(ctx.form.payments,[original,draft]);assert.equal(recalculations,0)
    cancel.call(ctx,1);assert.deepEqual(ctx.form.payments,[original]);assert.equal(recalculations,1)
    const block=invoice.template.content.slice(invoice.template.content.indexOf('Cobro registrado:'),invoice.template.content.indexOf('@click.prevent="\n                                                                        clickCancel'))
    assert.match(block,/row\.original_amount/)
    assert.match(block,/row\.exchange_rate/)
    assert.match(block,/row\.financial_intermediation/)
    assert.match(block,/row\.payment_destination_description/)
    assert.equal((block.match(/:disabled="isRecordedReceipt\(row\)"/g) || []).length,2)
    assert.match(block,/v-if="!isRecordedReceipt\(row\)"/)
    assert.deepEqual(compiler.compile(invoice.template.content).errors,[])
})

test('a quotation without prior receipts submits the new received currency and rate', async () => {
    const calls = []
    const payment = {id: null, payment_method_type_id: '13', currency_type_id: 'USD', original_amount: '2.50', exchange_rate: '40', payment: '100.00', payment_destination_id: 2}
    const ctx = {form: {quotation: {payments: []}}, document: {document_type_id: '01', payment_condition_id: '01', payments: [payment]},
        validateQuantityandSeriesLots: async () => ({success: true}), assignDocument: async () => {},
        validatePaymentDestination: async () => ({error_by_item: 0}), ensureFiscalPaymentsReady: async rows => {assert.equal(rows[0], payment); return true},
        $message: {error() {}}, $http: {post: async (url, data) => {calls.push({url, data}); return {data: {success: false}}}}}
    await run.call(ctx)
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(calls.length, 1)
    assert.equal(calls[0].url, '/documents')
    assert.equal(calls[0].data.payments[0].currency_type_id, 'USD')
    assert.equal(calls[0].data.payments[0].original_amount, '2.50')
    assert.equal(calls[0].data.payments[0].exchange_rate, '40')
})

test('credit conversion calculates the installment from the balance after real receipts',()=>{
    const component=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue','utf8'))
    const tree=parser.parse(component.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
    const entry=methods.properties.find(node=>node.key?.name==='preparePaymentsFee')
    const exact=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/exchange-rate-math.js'))
    const prepare=vm.runInNewContext(`({${generate(entry).code}}).preparePaymentsFee`,exact)
    const {suggestInstallments}=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/document-creation.js'))
    const ctx={isCreditPaymentCondition:true,form:{payment_condition_id:'02',total:'232',payments:[{payment:'50',currency_type_id:'USD',original_amount:'5'}],fee:[]},clickAddFeeNew(){this.form.fee.push({amount:'0'});suggestInstallments(this.form)},changePaymentMethodType(){}}
    prepare.call(ctx,{document_payment_method_type:{id:'07'}})
    assert.equal(ctx.form.fee[0].amount,'182.00')
    assert.equal(ctx.form.fee[0].payment_method_type_id,'07')
    assert.equal(ctx.form.payments[0].original_amount,'5')
    assert.deepEqual(compiler.compile(component.template.content).errors,[])
})

test('quotation conversion proposes only the remaining receipt and never fabricates credit amounts',()=>{
    const source=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/quotations/partials/options.vue','utf8'))
    const tree=parser.parse(source.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
    const entry=methods.properties.find(node=>node.key?.name==='clickAddPayment')
    const load=require('./helpers/load-module.cjs'), path=require('node:path')
    const helper={...load(path.resolve('resources/js/helpers/payment-classification.js')),...load(path.resolve('resources/js/helpers/document-creation.js')),moment:()=>({format:()=> '2026-10-01'})}
    const add=vm.runInNewContext(`({${generate(entry).code}}).clickAddPayment`,helper)
    const original={id:12,payment:'25',currency_type_id:'VES'}
    const ctx={document:{payment_condition_id:'01',currency_type_id:'VES',total:'100',payments:[original]},payment_method_types:[{id:'04',collection_kind:'cash',currency_type_id:'VES',is_active:true}]}
    add.call(ctx);assert.equal(original.payment,'25');assert.equal(ctx.document.payments[1].payment,'75.00')
    ctx.document.payment_condition_id='02';add.call(ctx);assert.equal(ctx.document.payments[2].payment,'0')
    assert.equal(original.payment,'25')
})

test('quotation credit installments account for retention and applied receipts without overwriting edited fees',()=>{
    const source=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/quotations/partials/options.vue','utf8'))
    const tree=parser.parse(source.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key.name==='methods').value
    const load=require('./helpers/load-module.cjs'),path=require('node:path')
    const context={...load(path.resolve('resources/js/helpers/document-creation.js')),_:{isEmpty:value=>!value || !Object.keys(value).length}}
    const method=name=>vm.runInNewContext(`({${generate(methods.properties.find(node=>node.key?.name===name)).code}}).${name}`,context)
    const receipt={payment:'25',currency_type_id:'USD',original_amount:'2.5'},first={amount:'0'},second={amount:'0'}
    const ctx={document:{total:'100',total_pending_payment:'80',retention:{amount:'20'},payments:[receipt],fee:[first,second]}}
    ctx.getTotal=method('getTotal').bind(ctx);const calculate=method('calculateFee')
    calculate.call(ctx);assert.equal(first.amount,'27.50');assert.equal(second.amount,'27.50')
    first.amount='20';receipt.payment='30';calculate.call(ctx)
    assert.equal(first.amount,'20');assert.equal(second.amount,'30.00')
    ctx.document.total_pending_payment='0';calculate.call(ctx);assert.equal(second.amount,'0.00')
})
