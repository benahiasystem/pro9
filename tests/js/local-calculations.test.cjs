const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const babel=require('@babel/core'),compiler=require('vue-template-compiler'),Vue=require('vue')
function runtime() {
    const cache=new Map()
    function load(file) {
        file=path.resolve(file)
        if(cache.has(file))return cache.get(file)
        const exports={};cache.set(file,exports)
        let source=fs.readFileSync(file,'utf8');if(file.endsWith('.vue'))source=compiler.parseComponent(source).script.content
        vm.runInNewContext(babel.transformSync(source,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
            {exports,crypto:require('node:crypto').webcrypto,require(name){if(name==='vue')return Vue;if(name.startsWith('.'))return load(path.resolve(path.dirname(file),/\.(js|vue)$/.test(name)?name:name+'.js'));return {}}})
        return exports
    }
    return {load}
}
const context={enabled:true,rate:'3',methods:[{id:'10',currency_type_id:'USD',collection_kind:'cash',is_active:true}],destinations:[],dates:{'2026-09-10':{issuer_spe:true}}}
const draft=()=>({currency_type_id:'USD',exchange_rate_sale:'100',date_of_issue:'2026-09-10',total:'60',total_payable:'60',payment_condition_id:'01',items:[],fee:[],payments:[{date_of_payment:'2026-09-10',payment_method_type_id:'10',currency_type_id:'USD',exchange_rate:'100',payment:'70',original_amount:'70',tendered_amount:'70'}]})
const flush=async()=>{await Promise.resolve();await Vue.nextTick();await Promise.resolve();await Vue.nextTick()}
test('amounts recalculate locally without HTTP per key and preserve entered money',async()=>{
    const {load}=runtime(),mixin=load('resources/js/mixins/fiscal-payment-settings.js').default,calls=[]
    const view=new Vue({localCalculations:true,mixins:[mixin],data:()=>({form:draft(),fiscalQuoteInline:true}),beforeCreate(){this.$http={post:async(url)=>{calls.push(url);assert.equal(url,'/documents/calculation-context');return {data:{data:context}}}}}})
    await view.refreshLocalContext();await flush()
    assert.equal(view.igtfQuote.payments[0].change,'8.20')
    const count=calls.length
    view.form.payments[0].tendered_amount='80';await flush()
    assert.equal(view.igtfQuote.payments[0].change,'18.20')
    assert.equal(view.form.payments[0].tendered_amount,'80')
    assert.equal(calls.length,count)
    view.form.total='30';view.form.total_payable='30';await flush()
    assert.equal(view.igtfQuote.payments[0].change,'49.10')
    assert.equal(calls.length,count)
    view.$destroy()
})
test('a pending context cannot allow duplicate confirmation or a replaced draft',async()=>{
    const {load}=runtime(),mixin=load('resources/js/mixins/fiscal-payment-settings.js').default
    let resolve
    const view=new Vue({localCalculations:true,mixins:[mixin],data:()=>({form:{...draft(),customer_id:55},fiscalQuoteInline:true}),beforeCreate(){this.$http={post:()=>new Promise(r=>resolve=r)};this.$message={error(){}}}})
    const first=view.ensureFiscalPaymentsReady(view.form.payments)
    assert.equal(await view.ensureFiscalPaymentsReady(view.form.payments),false)
    view.form={...draft(),customer_id:55,total:'80'}
    resolve({data:{data:context}})
    assert.equal(await first,false)
    view.$destroy()
})
test('zero installment weights use an equal allocation and currency changes remain exact',()=>{
    const {load}=runtime(),{calculationExpectation}=load('resources/js/helpers/calculation-expectation.js')
    const form={...draft(),payment_condition_id:'02',payments:[],fee:[{date:'2026-10-10',amount:'0',allocation_weight:'0',payment_method_type_id:'05'},{date:'2026-11-10',amount:'0',allocation_weight:'0',payment_method_type_id:'05'}]}
    const result=calculationExpectation(form,{payments:[],balance:'60',total_with_tax:'60'},'r1')
    assert.deepEqual(Array.from(result.fee,row=>row.amount),['30.00','30.00'])
})
test('newer fiscal contexts win even when a different component started an older request',async()=>{
    const {load}=runtime(),{loadCalculationContext}=load('resources/js/helpers/calculation-context.js')
    const resolvers=[],http={post:()=>new Promise(resolve=>resolvers.push(resolve))},input={customer_id:33,dates:['2026-09-10']}
    const old=loadCalculationContext(http,input),current=loadCalculationContext(http,input,true)
    resolvers[1]({data:{data:{...context,rate:'5'}}})
    assert.equal((await current).rate,'5')
    resolvers[0]({data:{data:{...context,rate:'3'}}})
    assert.equal((await old).rate,'5')
})
test('server corrections need another click and internal tax conversion preserves the review',async()=>{
    const {load}=runtime(),mixin=load('resources/js/mixins/fiscal-payment-settings.js').default
    const view=new Vue({localCalculations:true,mixins:[mixin],data:()=>({form:draft(),fiscalQuoteInline:true}),beforeCreate(){this.$http={post:async()=>({data:{data:context}})};this.$message={error(){}}}})
    await view.refreshLocalContext();await flush()
    const first=view.prepareCalculationSubmission(view.form)
    const summary={...first.expected_calculation,total_with_tax:'63.00',balance:'0.00',payments:[{...first.expected_calculation.payments[0],tax_amount:'3.00',change:'7.00'}]}
    view.handleCalculationChanged({response:{status:409,data:{code:'calculation_changed',calculation:summary}}},view.form)
    await flush()
    assert.equal(view.form.payments[0].tendered_amount,'70')
    assert.equal(view.igtfQuote.payments[0].change,'7.00')
    view.$set(view.form.payments[0],'igtf_status','subject');view.form.payments[0].original_amount='66.67';view.form.payments[0].payment='66.67';await flush()
    assert.ok(view.localCalculationReview)
    const confirmed=view.prepareCalculationSubmission(view.form)
    assert.equal(confirmed.expected_calculation.total_with_tax,'63.00')
    assert.notEqual(confirmed.expected_calculation.revision,summary.revision)
    view.form.payments[0].tendered_amount='80';await flush()
    assert.equal(view.localCalculationReview,null)
    assert.equal(view.form.payments[0].tendered_amount,'80')
    view.$destroy()
})
test('a method or manual rate edit updates taxes locally without requesting a new context',async()=>{
    const {load}=runtime(),mixin=load('resources/js/mixins/fiscal-payment-settings.js').default,calls=[]
    const current={...context,methods:[...context.methods,{id:'04',currency_type_id:'VES',collection_kind:'cash',is_active:true}]}
    const view=new Vue({localCalculations:true,mixins:[mixin],data:()=>({form:draft(),fiscalQuoteInline:true}),beforeCreate(){this.$http={post:async url=>{calls.push(url);return {data:{data:current}}}}}})
    await view.refreshLocalContext();await flush();const count=calls.length
    Object.assign(view.form.payments[0],{payment_method_type_id:'04',currency_type_id:'VES',tendered_amount:'7000',payment:'70',original_amount:'7000'})
    await flush();assert.equal(view.igtfQuote.payments[0].tax_amount,'0.00');assert.equal(view.igtfQuote.payments[0].change,'1000.00')
    view.form.payments[0].exchange_rate='50';await flush();assert.equal(view.igtfQuote.payments[0].change,'4000.00')
    assert.equal(calls.length,count);view.$destroy()
})
test('credit excludes retained cash from the local result and payload and restores it for cash sales',async()=>{
    const {load}=runtime(),mixin=load('resources/js/mixins/fiscal-payment-settings.js').default
    const view=new Vue({localCalculations:true,mixins:[mixin],data:()=>({form:draft(),fiscalQuoteInline:true}),beforeCreate(){this.$http={post:async()=>({data:{data:context}})}}})
    await view.refreshLocalContext();await flush()
    view.form.payment_condition_id='03';view.form.fee=[{date:'2026-12-10',payment_method_type_id:'05',amount:'30',allocation_weight:'1'},{date:'2027-01-10',payment_method_type_id:'05',amount:'30',allocation_weight:'1'}]
    await flush()
    assert.equal(await view.ensureFiscalPaymentsReady(view.form.payments),true)
    assert.equal(view.igtfQuote.payments.length,0)
    const credit=view.prepareCalculationSubmission(view.form)
    assert.equal(credit.payments.length,0);assert.equal(credit.payment_condition_id,'02');assert.equal(credit.expected_calculation.fee[1].date,'2027-01-10')
    assert.equal(view.form.payments[0].tendered_amount,'70')
    view.form.payment_condition_id='01';await flush()
    assert.equal(view.igtfQuote.payments[0].change,'8.20')
    const cash=view.prepareCalculationSubmission(view.form)
    assert.equal(cash.payments[0].tendered_amount,'70');assert.equal(cash.fee.length,0);view.$destroy()
})
test('sale notes reject a subcent balance after an exact historical receipt',()=>{
    const {load}=runtime(),{localPaymentQuote}=load('resources/js/helpers/local-payment-quote.js')
    const methods=[{id:'04',currency_type_id:'VES',collection_kind:'cash',is_active:true}]
    const request={currency_type_id:'VES',exchange_rate_sale:'100',principal_due:'250',payments:[
        {id:1,currency_type_id:'VES',payment_method_type_id:'04',original_amount:'0.33',payment:'0.333333333333',exchange_rate:'100',calculation_snapshot:{values:{payment:{n:'1',d:'3'}}}},
        {currency_type_id:'VES',payment_method_type_id:'04',original_amount:'249.66',payment:'249.66',exchange_rate:'100'}]}
    assert.throws(()=>localPaymentQuote(request,context,methods,'80'),/saldo después de los abonos/)
})
test('the exact invoice header and converted IGTF settle to the same cents as persistence',()=>{
    const {load}=runtime(),{localPaymentQuote}=load('resources/js/helpers/local-payment-quote.js')
    const quote=localPaymentQuote({currency_type_id:'VES',exchange_rate_sale:'0.4',principal_due:'0.40',commercial_total_exact:'0.404',payments:[
        {date_of_payment:'2026-09-10',payment_method_type_id:'10',currency_type_id:'USD',original_amount:'1',payment:'0.40',exchange_rate:'0.4'}]},context,context.methods)
    assert.equal(quote.total_with_tax,'0.42');assert.equal(quote.balance,'0.01')
    assert.deepEqual({...quote.total_with_tax_exact},{n:'52',d:'125'})
})
test('editing an existing invoice keeps the legacy settings object initialized',()=>{
    const {load}=runtime(),mixin=load('resources/js/mixins/fiscal-payment-settings.js').default
    const globalSettings=load('resources/js/helpers/igtf-settings.js').igtfSettings
    const view=new Vue({localCalculations:true,mixins:[mixin],props:{documentId:Number},propsData:{documentId:1},
        data:()=>({form:{document_type_id:'80',payments:[]}}),computed:{isUpdateDocument(){return !!this.documentId}}})
    assert.equal(view.useLocalCalculations,false);assert.equal(view.igtfSettings,globalSettings);view.$destroy()
})
test('closing a fractional balance preserves its exact applied payment in the expected summary',()=>{
    const {load}=runtime(),{localPaymentQuote}=load('resources/js/helpers/local-payment-quote.js'),{calculationExpectation}=load('resources/js/helpers/calculation-expectation.js')
    const methods=[...context.methods,{id:'04',currency_type_id:'VES',collection_kind:'cash',is_active:true}]
    const payments=[{id:1,date_of_payment:'2026-09-10',currency_type_id:'VES',exchange_rate:'1',original_amount:'0.67',payment:'0.666666666667',payment_method_type_id:'04',calculation_snapshot:{values:{payment:{n:'2',d:'3'}}}},
        {date_of_payment:'2026-09-10',currency_type_id:'USD',exchange_rate:'1',original_amount:'0.34',payment:'0.34',payment_method_type_id:'10'}]
    const quote=localPaymentQuote({currency_type_id:'VES',exchange_rate_sale:'1',principal_due:'1',payments},context,methods)
    assert.deepEqual({...quote.payments[1].payment_exact},{n:'1',d:'3'})
    const expected=calculationExpectation({...draft(),currency_type_id:'VES',exchange_rate_sale:'1',total:'1',total_payable:'1',payments},quote,'fractional',methods)
    assert.deepEqual({...expected.payments[1].payment_exact},{n:'1',d:'3'})
})
function stockView({unitPrice='100', documentType='01', calculateQuantity=false} = {}) {
    const {load}=runtime(),mixin=load('resources/js/mixins/pos-local-quantity.js').default
    const {calculateRowItem}=load('resources/js/helpers/functions.js'),{documentTotals}=load('resources/js/helpers/exact-document.js')
    const pending=[],messages=[]
    const row=calculateRowItem({item:{id:1,currency_type_id:'VES',unit_price:unitPrice,has_igv:true,aux_quantity:'1',calculate_quantity:calculateQuantity},
        quantity:'1',affectation_igv_type_id:'10',unit_type_id:'UND'},'VES','100','0.16')
    const view=new Vue({mixins:[mixin],data:()=>({form:{document_type_id:documentType,currency_type_id:'VES',exchange_rate_sale:'100',items:[row]},percentage_igv:'0.16'}),
        methods:{calculateTotal(){Object.assign(this.form,documentTotals(this.form.items))},setFormPosLocalStorage(){},getStatusStock(itemId, quantity){return new Promise((resolve,reject)=>pending.push({resolve,reject,itemId,quantity}))}},
        beforeCreate(){this.$message={error:message=>messages.push(message)}}})
    view.calculateTotal();return {view,pending,messages}
}
test('quantities and IVA render immediately while stock is slow, and stale responses cannot undo edits',async()=>{
    const {view,pending,messages}=stockView()
    let row=view.form.items[0];row.item.aux_quantity='2';view.updateLocalPosQuantity(row,0)
    assert.equal(view.form.total,'200');assert.equal(view.posStockBlocked,true)
    row=view.form.items[0];row.item.aux_quantity='3';view.updateLocalPosQuantity(row,0);await flush()
    assert.equal(view.form.total,'300');assert.equal(pending.length,2)
    pending[1].resolve({success:true});await flush();assert.equal(view.posStockBlocked,false)
    pending[0].resolve({success:false,message:'Respuesta antigua'});await flush()
    assert.equal(view.form.total,'300');assert.deepEqual(messages,[]);view.$destroy()
})
test('stock errors block generation, permit retry and cannot alter deleted rows',async()=>{
    const {view,pending}=stockView()
    view.form.items[0].item.aux_quantity='2';view.updateLocalPosQuantity(view.form.items[0],0);await flush()
    pending[0].reject(new Error('network'));await flush();assert.equal(view.posStockBlocked,true);assert.equal(view.posStockRetryable,true)
    view.retryPosStock();await flush();assert.equal(view.posStockPending,true)
    view.form.items=[];pending[1].resolve({success:false,message:'Ya no existe'});await flush()
    assert.equal(view.posStockBlocked,false);assert.equal(view.posStockError,'');view.$destroy()
})
test('price edits preserve exact fractions, do not wait for stock and keep invalid inputs blocked',async()=>{
    const {view,pending}=stockView()
    view.form.items[0].item.aux_quantity='3';view.updateLocalPosQuantity(view.form.items[0],0);await flush()
    view.form.items[0].total='1';view.updateLocalPosTotal(0)
    assert.equal(view.form.total,'1');assert.deepEqual({...view.form.items[0].price_input.amount},{n:'1',d:'3'})
    assert.equal(view.form.items[0].item.unit_price,'0.333333333333');assert.equal(view.form.items[0].item.sale_unit_price,'0.333333333333')
    assert.equal(pending.length,1)
    view.form.items[0].total='abc';view.updateLocalPosTotal(0)
    pending[0].resolve({success:true});await flush()
    assert.equal(view.posStockBlocked,true);assert.equal(view.posStockRetryable,false)
    assert.equal(view.form.items[0].total,'abc');view.$destroy()
})
test('invoice quantity derived from the entered total keeps valid operands instead of limiting its numerator',async()=>{
    const amount='123456789.123456789012'
    const {view,pending}=stockView({unitPrice:'0.987654321019',calculateQuantity:true})
    view.form.items[0].total=amount
    view.updateLocalPosTotal(0)
    await flush()
    assert.equal(view.posStockError,'')
    const row=view.form.items[0]
    assert.equal(row.quantity,'124999998.983532811407')
    assert.deepEqual({...row.calculation_snapshot.values.quantity},{n:'123456789123456789012',d:'987654321019'})
    assert.equal(row.quantity_input.amount,amount)
    assert.deepEqual({...row.quantity_input.unit_price},{n:'987654321019',d:'1000000000000'})
    assert.equal(view.form.total,amount)
    assert.equal(pending.length,1)
    pending[0].resolve({success:true})
    await flush()
    assert.equal(view.posStockBlocked,false)
    view.$destroy()
})
test('quick checkout validates the exact physical demand for an edited invoice line', async () => {
    const {view, pending} = stockView({unitPrice:'3', calculateQuantity:true})
    const row = view.form.items[0]
    view.$set(row.item, 'presentation', {id:7, quantity_unit:'2'})
    const otherPrice = {...row, item:{...row.item, presentation:null}}
    const otherProduct = {...otherPrice, item_id:2, item:{...otherPrice.item, id:2}}
    view.form.items.push(otherPrice, otherProduct)
    row.total = '1'
    view.updateLocalPosTotal(0)
    await flush()

    assert.equal(view.form.items[0].total, '1')
    assert.deepEqual({...view.form.items[0].calculation_snapshot.values.quantity}, {n:'1', d:'3'})
    assert.equal(pending[0].itemId, 1)
    assert.deepEqual({...pending[0].quantity}, {n:'5', d:'3'})
    pending[0].resolve({success:false, message:'Sin existencias'})
    await flush()
    assert.equal(view.posStockBlocked, true)

    view.retryPosStock()
    await flush()
    assert.deepEqual({...pending[1].quantity}, {n:'5', d:'3'})
    const edited = view.form.items[0]
    edited.item.aux_quantity = '2'
    view.updateLocalPosQuantity(edited, 0)
    await flush()
    assert.equal(view.form.items[0].total, '6')
    assert.equal(view.form.items[0].quantity_input, null)
    assert.deepEqual({...pending[2].quantity}, {n:'5', d:'1'})
    pending[1].resolve({success:false, message:'Respuesta anterior'})
    pending[2].resolve({success:true})
    await flush()
    assert.equal(view.posStockBlocked, false)
    view.$destroy()
})

test('quick checkout keeps existing stock validation of sale notes', async () => {
    const {view, pending} = stockView({documentType:'80'})
    const row = view.form.items[0]
    view.$set(row.item, 'presentation', {id:7, quantity_unit:'2'})
    row.item.aux_quantity = '3'
    view.updateLocalPosQuantity(row, 0)
    await flush()
    assert.equal(pending[0].quantity, '3')
    pending[0].resolve({success:true})
    await flush()
    view.$destroy()
})

test('quick checkout ignores an older stock result after another invoice line of the same product changes', async () => {
    const {view, pending} = stockView({unitPrice:'3'})
    const first = view.form.items[0]
    const second = {...first, item:{...first.item}}
    const unrelated = {...first, item_id:2, item:{...first.item, id:2}}
    view.$set(first.item, 'presentation', {id:7, quantity_unit:'2'})
    view.form.items.push(second, unrelated)

    unrelated.item.aux_quantity = '2'
    view.updateLocalPosQuantity(unrelated, 2)
    first.item.aux_quantity = '2'
    view.updateLocalPosQuantity(first, 0)
    await flush()
    assert.deepEqual({...pending[1].quantity}, {n:'5', d:'1'})

    second.item.aux_quantity = '0.4'
    view.updateLocalPosQuantity(second, 1)
    await flush()
    assert.deepEqual({...pending[2].quantity}, {n:'22', d:'5'})
    pending[2].resolve({success:true})
    pending[1].resolve({success:false, message:'Respuesta de una demanda anterior'})
    await flush()
    assert.equal(view.posStockPending, true)
    assert.equal(view.posStockError, '')
    pending[0].resolve({success:true})
    await flush()
    assert.equal(view.posStockBlocked, false)
    view.$destroy()
})

test('switching invoice and sale note preserves the latest total delivered and invalidates stale tendered amounts',async()=>{
    const {load}=runtime(),mixin=load('resources/js/mixins/fiscal-payment-settings.js').default
    const current={...context,methods:[...context.methods,{id:'04',currency_type_id:'VES',collection_kind:'cash',is_active:true}]}
    const bolivarCash={date_of_payment:'2026-09-10',payment_method_type_id:'04',currency_type_id:'VES',exchange_rate:'100',payment:'1000',original_amount:'1000',tendered_amount:'1000'}
    const {payments:[dollarCash],...document}=draft()
    const form={...document,document_type_id:'01',currency_type_id:'VES',total:'6000',total_payable:'6000',
        payments:[bolivarCash,{...dollarCash,payment:'7000'}]}
    const view=new Vue({localCalculations:true,mixins:[mixin],data:()=>({form,fiscalQuoteInline:true}),beforeCreate(){this.$http={post:async()=>({data:{data:current}})}}})
    await view.refreshLocalContext();await flush()
    assert.equal(view.igtfQuote.payments[1].change,'18.50')
    assert.equal(view.igtfQuote.payments[1].exchange_rate,'100.00000000')
    assert.equal(view.igtfQuote.principal_applied,'6000.00')
    assert.equal(view.igtfQuote.total_with_tax,'6150.00')
    view.form.document_type_id='80';await flush()
    assert.equal(view.form.payments[1].tendered_amount,undefined)
    assert.equal(view.form.payments[1].original_amount,'70')
    assert.equal(view.igtfQuote.payments[0].change,'1000.00')
    assert.equal(view.igtfQuote.payments[1].change,'10.00')
    Object.assign(view.form.payments[1],{original_amount:'80',payment:'8000'})
    view.form.document_type_id='01';await flush()
    assert.equal(view.form.payments[1].tendered_amount,'80')
    assert.equal(view.igtfQuote.payments[1].change,'28.50')
    assert.equal(view.form.currency_type_id,'VES')
    assert.equal(view.form.total,'6000')
    view.$destroy()
})
test('rebuilding a row for currency conversion preserves pending and failed availability checks',async()=>{
    const {view,pending}=stockView()
    view.form.items[0].item.aux_quantity='2';view.updateLocalPosQuantity(view.form.items[0],0);await flush()
    view.form.items=view.form.items.map(row=>({...row,item:{...row.item}}));await flush()
    assert.equal(view.posStockBlocked,true)
    pending[0].resolve({success:false,message:'Sin existencias'});await flush()
    view.form.items=view.form.items.map(row=>({...row,item:{...row.item}}));await flush()
    assert.equal(view.posStockBlocked,true);assert.equal(view.posStockError,'Sin existencias');view.$destroy()
})
