const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path')
const Vue=require('vue/dist/vue.common.js'),compiler=require('vue-template-compiler'),babel=require('@babel/core')
const runtime=require('./support/exact-runtime.cjs'),math=runtime('exchange-rate-math'),creation=runtime('document-creation')
function component(file) {
    const parsed=compiler.parseComponent(fs.readFileSync(file,'utf8')),exports={}
    vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
        {exports,_:require('lodash'),require:name=>runtime(name)||(name.endsWith('/helpers/functions')?require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/functions.js'))
            :name==='moment'?require('moment'):name.includes('vuex')?{mapState:()=>({}),mapActions:()=>({})}:{})})
    assert.deepEqual(compiler.compile(parsed.template.content).errors,[])
    return exports.default
}
const input=component('resources/js/views/tenant/documents/partials/exact_decimal_input.vue')
const invoice=component('resources/js/views/tenant/documents/invoice_generate.vue')
test('the web invoice displays the total of a positive sale below twelve decimal places', () => {
    const file = 'resources/js/views/tenant/documents/invoice_generate.vue'
    const template = compiler.parseComponent(fs.readFileSync(file, 'utf8')).template.content
    const row = Array.from(template.matchAll(/<tr\b[^>]*>[\s\S]*?<\/tr>/g), match => match[0])
        .find(candidate => candidate.includes('Total de la venta'))
    assert.ok(row)
    const texts = node => [node.text, ...(node.children || []).map(texts)].filter(Boolean).join(' ')
    const view = new Vue({
        ...compiler.compileToFunctions(`<table><tbody>${row}</tbody></table>`),
        computed: {hasPositiveInvoiceTotal: invoice.computed.hasPositiveInvoiceTotal},
        data: () => ({
            form: {total: '0', calculation_snapshot: math.exactSnapshot({total: math.exactAmount('0.0000000000001')})},
            documentCurrencySymbol: 'Bs.',
            fiscalPaymentsApplicable: true,
            isUpdateDocument: false,
        }),
    })
    try {
        assert.match(texts(view._render()), /Total de la venta/)
        view.form.calculation_snapshot = math.exactSnapshot({total: math.exactAmount('0')})
        assert.doesNotMatch(texts(view._render()), /Total de la venta/)
        view.form.calculation_snapshot = math.exactSnapshot({total: math.exactAmount('-0.0000000000001')})
        assert.doesNotMatch(texts(view._render()), /Total de la venta/)
        view.form.calculation_snapshot = null
        view.form.total = '1.21'
        assert.match(texts(view._render()), /Total de la venta/)
    } finally {
        view.$destroy()
    }
})

test('the web invoice displays an applied advance whose transport decimal is zero', () => {
    const template = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue', 'utf8')).template.content
    const row = Array.from(template.matchAll(/<tr\b[^>]*>[\s\S]*?<\/tr>/g), match => match[0])
        .find(candidate => candidate.includes('ANTICIPOS:'))
    assert.ok(row)
    const residual = {n: '1', d: '3000000000000'}
    const advances = [{total: '0', calculation_snapshot: math.exactSnapshot({total: residual})}]
    const totals = runtime('exact-document').documentTotals([], [], [], '0', advances)
    assert.deepEqual({...totals.calculation_snapshot.values.total_prepayment}, residual)
    const view = new Vue({
        ...compiler.compileToFunctions(`<table><tbody>${row}</tbody></table>`),
        computed: {hasPositiveInvoicePrepayment: invoice.computed.hasPositiveInvoicePrepayment},
        data: () => ({
            form: {total_prepayment: '0', calculation_snapshot: totals.calculation_snapshot},
            documentCurrencySymbol: 'Bs.',
        }),
    })
    const text = node => [node.text, ...(node.children || []).map(text)].filter(Boolean).join(' ')

    try {
        assert.match(text(view._render()), /ANTICIPOS:/)
        view.form.calculation_snapshot = math.exactSnapshot({total_prepayment: '0'})
        assert.doesNotMatch(text(view._render()), /ANTICIPOS:/)
        view.form.calculation_snapshot = null
        view.form.total_prepayment = '1.21'
        assert.match(text(view._render()).replace(/\s+/g, ' '), /ANTICIPOS: Bs\. 1\.21/)
    } finally {
        view.$destroy()
    }
})

test('the VES invoice summary displays the exact sign of a settlement adjustment projected as zero', () => {
    const template = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue', 'utf8')).template.content
    const row = Array.from(template.matchAll(/<tr\b[^>]*>[\s\S]*?<\/tr>/g), match => match[0])
        .find(candidate => candidate.includes('Ajuste de liquidación:'))
    assert.ok(row)
    const view = new Vue({
        ...compiler.compileToFunctions(`<table><tbody>${row}</tbody></table>`),
        computed: invoice.computed.invoiceSettlementAdjustmentLabel
            ? {invoiceSettlementAdjustmentLabel: invoice.computed.invoiceSettlementAdjustmentLabel} : {},
        data: () => ({form: {}, documentCurrencySymbol: 'Bs.'}),
    })
    const text = node => [node.text, ...(node.children || []).map(text)].filter(Boolean).join(' ')
    const {documentTotals} = runtime('exact-document')
    try {
        for (const [total, expected] of [
            [{n: '10000000000001', d: '10000000000000'}, '-1/10000000000000'],
            [{n: '9999999999999', d: '10000000000000'}, '1/10000000000000'],
            ['1.002', '-0.002'],
            ['1', null],
        ]) {
            const line = {affectation_igv_type_id: '20', calculation_snapshot: math.exactSnapshot({total_value: total})}
            view.form = {...documentTotals([line]), currency_type_id: 'VES'}
            assert.equal(view.form.total_payable, '1')
            const actual = text(view._render()).replace(/\s+/g, ' ')
            if (expected === null) assert.doesNotMatch(actual, /Ajuste de liquidación/)
            else assert.ok(actual.includes('Ajuste de liquidación: Bs. ' + expected), actual)
        }
    } finally {
        view.$destroy()
    }
})

test('the invoice summary keeps rows for positive tax bases and discounts projected as zero', () => {
    const template = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue', 'utf8')).template.content
    const labels = ['Op. gravada:', 'OP.EXONERADAS:', 'EXPORTACIÓN (IVA 0 %):', 'IVA:', 'SUBTOTAL:', 'DESCUENTOS TOTALES:']
    const rows = Array.from(template.matchAll(/<tr\b[^>]*>[\s\S]*?<\/tr>/g), match => match[0])
        .filter(row => labels.some(label => row.includes(`<td>${label}</td>`)))
    assert.equal(rows.length, labels.length)
    const computed = Object.fromEntries(['totalDiscount', 'totalDiscountExact', 'hasPositiveInvoiceDiscount', 'displaySubtotalBeforeDiscount']
        .filter(name => invoice.computed[name]).map(name => [name, invoice.computed[name]]))
    const view = new Vue({
        ...compiler.compileToFunctions(`<table><tbody>${rows.join('')}</tbody></table>`),
        computed,
        methods: {
            itemDiscountExactAmount: invoice.methods.itemDiscountExactAmount,
            hasPositiveInvoiceAmount: invoice.methods.hasPositiveInvoiceAmount,
        },
        data: () => ({form: {items: [], discounts: []}, documentCurrencySymbol: 'Bs.',
            isGlobalDiscountBase: false, percentage_igv: '0.16'}),
    })
    const text = node => [node.text, ...(node.children || []).map(text)].filter(Boolean).join(' ')
    const {documentTotals} = runtime('exact-document')
    const tiny = math.exactAmount({n: '1', d: '3000000000000'})
    try {
        for (const [affectation, expected] of [
            ['10', ['Op. gravada:', 'IVA:']], ['20', ['OP.EXONERADAS:']], ['EXP', ['EXPORTACIÓN (IVA 0 %):']],
        ]) {
            const line = {affectation_igv_type_id: affectation, calculation_snapshot: math.exactSnapshot({
                total_base_igv: tiny, total_value: tiny, total_igv: affectation === '10' ? tiny.times('0.16') : '0',
            })}
            const header = documentTotals([line])
            for (const field of ['total_taxed', 'total_exonerated', 'total_exportation', 'total_igv']) assert.equal(header[field], '0')
            view.form = {...header, items: [line], discounts: []}
            for (const label of labels) assert.equal(text(view._render()).includes(label), expected.includes(label), label)
        }

        const line = {affectation_igv_type_id: 'EXP', total_value: '1'}
        const exportInvoice = {
            ...invoice.methods,
            isExportInvoice: true,
            is_amount: false,
            total_global_discount: '0.000000000001',
            recordDiscountsGlobal: {discount_type_id: '03'},
            form: {items: [line], discounts: []},
        }
        for (const value of ['1', '1', '2']) {
            line.total_value = value
            Object.assign(exportInvoice.form, documentTotals([line]))
            exportInvoice.discountGlobalItems(exportInvoice.form)
            const [discount] = exportInvoice.form.discounts
            assert.equal(exportInvoice.form.discounts.length, 1)
            assert.equal(math.exactAmount(discount.amount_exact).compare(math.exactAmount(value).dividedBy('100000000000000')), 0)
            view.form = {...documentTotals([line], [discount]), items: [line], discounts: [discount]}
            assert.equal(view.form.total_discount, '0')
            assert.equal(view.totalDiscount, '0')
            assert.match(text(view._render()), /SUBTOTAL:/)
            assert.match(text(view._render()), /DESCUENTOS TOTALES:/)
        }
        view.form = {...documentTotals([line]), items: [line], discounts: []}
        assert.doesNotMatch(text(view._render()), /SUBTOTAL:|DESCUENTOS TOTALES:/)

        view.form = {items: [], discounts: [], total_taxed: '10', total_exonerated: '-1', subtotal: '10'}
        assert.match(text(view._render()), /Op\. gravada:/)
        assert.doesNotMatch(text(view._render()), /OP\.EXONERADAS:/)
    } finally {
        view.$destroy()
    }
})

function control(props={}) {return new Vue({...input,propsData:{value:'0',apply:()=>true,...props}})}
function state() {
    return {...invoice.methods,form:{currency_type_id:'VES',total:'90',items:[],discounts:[],charges:[],payments:[],fee:[]},
        total_global_discount:'10',total_global_charge:'0',invoiceAllowancePercent:'0',is_amount:true,
        config:{active_allowance_charge:true,percentage_allowance_charge:'0'},$refs:{},$message:{error(){},warning(){}},
        calculateTotal() {
            if(math.exactAmount(this.total_global_discount).compare(100)>0)return false
            this.form.total=math.exactAmount(100).minus(this.total_global_discount).plus(this.invoiceAllowancePercent).decimal()
            return true
        }}
}
test('billing decimal input commits exact text once and never emits intermediate numeric edits',()=>{
    for(const [entered,expected] of [['9007199254740993.12','9007199254740993.12'],['0,1','0.1'],['0.2','0.2'],['0001.20','1.2']]) {
        const values=[],view=control({apply:value=>{values.push(value);return value}})
        view.onInput(entered);assert.equal(values.length,0)
        assert.equal(view.commitPending(),true);assert.equal(values[0],expected);assert.equal(typeof values[0],'string')
        assert.equal(view.commitPending(),true);assert.equal(values.length,1);view.$destroy()
    }
})
test('invalid, incomplete and out-of-range invoice drafts never reach the calculation',()=>{
    for(const value of ['','-1','1e2','NaN','1,','1,2.3','0.1234567890123','1000000000000000000']) {
        let applied=0;const view=control({apply:()=>applied++});view.onInput(value)
        assert.equal(view.commitPending(),false,value);assert.equal(applied,0,value);assert.equal(view.dirty,true)
        assert.ok(view.error);view.$destroy()
    }
})
test('line-discount input rounds exactly at commit and leaves an unchanged fraction intact',()=>{
    let calls=0;const original=control({value:'0.333333333333',precision:2,apply:()=>calls++})
    assert.equal(original.draft,'0.33');assert.equal(original.commitPending(),true);assert.equal(calls,0)
    original.onInput('0.33');assert.equal(original.commitPending(),true);assert.equal(calls,0)
    original.onInput('0');assert.equal(original.commitPending(),true);assert.equal(calls,1);original.$destroy()
    const values=[],large=control({precision:2,apply:value=>{values.push(value);return value}})
    large.onInput('9007199254740993.005');assert.equal(large.commitPending(),true)
    assert.equal(values[0],'9007199254740993.01');large.$destroy()
})
test('a changed currency context rejects an old draft until it is edited in the new context',()=>{
    let calls=0;const view=control({contextKey:'VES',apply:()=>calls++});view.onInput('10');view.contextKey='USD'
    assert.equal(view.commitPending(),false);assert.equal(calls,0)
    view.onInput('12');assert.equal(view.commitPending(),true);assert.equal(calls,1);view.$destroy()
})
test('starting another invoice cancels its predecessor draft even with the same currency and mode',async()=>{
    let calls=0;const cancelled=[],view=control({value:'10',contextKey:'VES|true',generation:1,apply:()=>calls++})
    view.$on('cancelled',message=>cancelled.push(message));view.onInput('20')
    view.value='10';view.generation=2;await Vue.nextTick()
    assert.equal(view.dirty,false);assert.equal(view.draft,'10');assert.equal(view.commitPending(),true)
    assert.equal(calls,0);assert.equal(cancelled.length,1);view.$destroy()
})
test('failed invoice adjustment restores original payment and installment objects and suggestion ownership',()=>{
    const view=state(),payment={payment:'0',currency_type_id:'VES'},fee={amount:'0'}
    view.form.total='100';view.form.total_payable='100';view.form.payments=[payment];view.form.fee=[fee]
    creation.suggestRemainingPayment(view.form,view.form.payments,{refreshSuggested:true})
    view.form.payments=[];creation.suggestInstallments(view.form);view.form.payments=[payment]
    assert.equal(payment.payment,'100.00');assert.equal(fee.amount,'100.00')
    const original=view.form,payments=view.form.payments,fees=view.form.fee
    view.calculateTotal=function() {
        this.form.total='150';this.form.total_payable='150'
        creation.suggestRemainingPayment(this.form,this.form.payments,{refreshSuggested:true})
        const receipts=this.form.payments;this.form.payments=[];creation.suggestInstallments(this.form);this.form.payments=receipts
        throw new Error('fallo después de sugerir cobros')
    }
    assert.equal(view.applyInvoiceDecimal('charge','1'),false)
    assert.equal(view.form,original);assert.equal(view.form.payments,payments);assert.equal(view.form.payments[0],payment)
    assert.equal(view.form.fee,fees);assert.equal(view.form.fee[0],fee);assert.equal(payment.payment,'100.00');assert.equal(fee.amount,'100.00')
    view.form.total='200';view.form.total_payable='200'
    creation.suggestRemainingPayment(view.form,view.form.payments,{refreshSuggested:true});assert.equal(payment.payment,'200.00')
    view.form.payments=[];creation.suggestInstallments(view.form);assert.equal(fee.amount,'200.00')
    payment.payment='0';creation.markPaymentEdited(payment);view.form.payments=[payment]
    assert.equal(view.applyInvoiceDecimal('charge','1'),false)
    view.form.total_payable='300';creation.suggestRemainingPayment(view.form,view.form.payments,{refreshSuggested:true})
    assert.equal(payment.payment,'0')
})
test('confirming several invoice drafts is atomic if a later discount is rejected',()=>{
    const view=state();const first=control({value:'0',apply:(value,input)=>view.applyInvoiceDecimal('allowance',value,null,input)})
    const second=control({value:'10',apply:(value,input)=>view.applyInvoiceDecimal('discount',value,null,input)})
    view.$refs={invoiceAllowanceInput:first,invoiceDiscountInput:second}
    first.onInput('1');second.onInput('200');const original=view.form
    assert.equal(first.commitPending(),false);assert.equal(view.form,original);assert.equal(view.form.total,'90')
    assert.equal(view.invoiceAllowancePercent,'0');assert.equal(view.total_global_discount,'10')
    assert.equal(first.dirty,true);assert.equal(first.draft,'1');assert.equal(second.dirty,true);assert.equal(second.draft,'200')
    second.onInput('20');assert.equal(view.commitPendingInvoiceAdjustments(),true)
    assert.equal(view.form.total,'81');assert.equal(view.total_global_discount,'20');assert.equal(view.invoiceAllowancePercent,'1')
    first.$destroy();second.$destroy()
})
test('invalid drafts prevent currency and amount-percentage changes before reinterpretation',()=>{
    const view=state(),field=control({apply:()=>assert.fail('invalid amount applied')})
    view.$refs.invoiceDiscountInput=field;field.onInput('1,')
    view.changeCurrencyType=()=>assert.fail('invalid draft changed currency')
    assert.equal(view.changeInvoiceCurrencyType('USD'),false);assert.equal(view.form.currency_type_id,'VES')
    assert.equal(view.changeInvoiceDiscountMode(false),false);assert.equal(view.is_amount,true);field.$destroy()
})
test('invoice row keys follow the same logical row after reordering and stay out of API JSON',()=>{
    const view=state(),first={item_id:1},second={item_id:1}
    const a=view.invoiceInputKey(first),b=view.invoiceInputKey(second);assert.notEqual(a,b)
    view.form.items=[second,first];assert.equal(view.invoiceInputKey(view.form.items[1]),a)
    const recalculated={...first,total:'1'};assert.equal(view.invoiceInputKey(recalculated),a)
    assert.equal(JSON.stringify(first),'{"item_id":1}')
})
test('a global percentage keeps its value when the document currency changes',()=>{
    const view=state();view.is_amount=false;view.total_global_discount='29';view.form.exchange_rate_sale='40'
    view.currency_types=[{id:'VES'},{id:'USD'}];view.calculateTotal=()=>true
    assert.equal(view.changeInvoiceCurrencyType('USD'),true);assert.equal(view.total_global_discount,'29')
    assert.equal(view.changeInvoiceCurrencyType('VES'),true);assert.equal(view.total_global_discount,'29')
})

test('fixed global discounts preserve their original fraction across currency changes and repeated recalculation',()=>{
    for (const [amount,rate] of [['0.01','40'],['0.01','3'],['0.000000000001','3'],['9007199254740993.01','9999999999.99999999']]) {
        const view=state();view.total_global_discount=amount;view.form.exchange_rate_sale=rate
        view.currency_types=[{id:'VES'},{id:'USD'}];view.calculateTotal=()=>true
        assert.equal(view.changeInvoiceCurrencyType('USD'),true)
        const converted=math.exactAmount(amount).dividedBy(rate)
        assert.deepEqual(view.invoiceGlobalDiscountExact().fraction(),converted.fraction())
        const projection=view.total_global_discount
        assert.equal(view.changeCurrencyType(),true);assert.equal(view.total_global_discount,projection)
        assert.deepEqual(view.invoiceGlobalDiscountExact().fraction(),converted.fraction())
        assert.equal(view.changeInvoiceCurrencyType('VES'),true);assert.equal(view.total_global_discount,amount)
        assert.deepEqual(view.invoiceGlobalDiscountExact().fraction(),math.exactAmount(amount).fraction())
    }
})

test('a converted subprecision discount can be explicitly cleared and failed currency changes restore its source',()=>{
    const view=state();view.total_global_discount='0.000000000001';view.form.exchange_rate_sale='3'
    view.currency_types=[{id:'VES'},{id:'USD'}];view.calculateTotal=()=>true
    assert.equal(view.changeInvoiceCurrencyType('USD'),true);assert.equal(view.total_global_discount,'0')
    const source=view.invoiceGlobalDiscountSource
    view.calculateTotal=()=>false
    assert.equal(view.changeInvoiceCurrencyType('VES'),false);assert.equal(view.form.currency_type_id,'USD')
    assert.equal(view.invoiceGlobalDiscountSource,source);assert.equal(view.invoiceGlobalDiscountExact().compare(0),1)
    view.calculateTotal=()=>true
    const field=control({value:'0',exactSource:source.amount,apply:value=>view.applyInvoiceDecimal('discount',value)})
    field.onInput('0');assert.equal(field.commitPending(),true);assert.equal(view.invoiceGlobalDiscountExact().compare(0),0)
    assert.equal(view.invoiceGlobalDiscountSource,null);field.$destroy()
})

test('native input retains an explicit zero replacement that Element UI does not emit',()=>{
    const calls=[],field=control({value:'0',exactSource:{n:'1',d:'3000000000000'},apply:value=>{calls.push(value);return value}})
    assert.equal(field.commitPending(),true);assert.deepEqual(calls,[])
    field.onNativeInput({target:{tagName:'DIV',value:'0'}});assert.equal(field.dirty,false)
    field.onNativeInput({isComposing:true,target:{tagName:'INPUT',value:'0'}});assert.equal(field.dirty,false)
    field.disabled=true;field.onNativeInput({target:{tagName:'INPUT',value:'0'}});assert.equal(field.dirty,false)
    field.disabled=false;field.onNativeInput({target:{tagName:'INPUT',value:'0'}})
    assert.equal(field.dirty,true);assert.equal(field.commitPending(),true);assert.deepEqual(calls,['0'])
    assert.equal(field.commitPending(),true);assert.deepEqual(calls,['0']);field.$destroy()
})

test('actual export discount calculation consumes the converted fraction and keeps it in the submitted adjustments',()=>{
    const {calculateRowItem}=require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/functions.js'))
    const view=state(),noop=()=>{}
    Object.assign(view,{calculateTotal:invoice.methods.calculateTotal,enabled_discount_global:true,isExportInvoice:true,
        configuration:{global_discount_type_id:'03',exact_discount:false},global_discount_type:{id:'03',description:'Descuento'},
        recordDiscountsGlobal:{discount_type_id:'03'},percentage_igv:'0.16',prepayment_deduction:false,
        setTotalDefaultPayment:noop,setPendingAmount:noop,calculateFee:noop,setTotalPointsBySale:noop,
        currency_types:[{id:'VES'},{id:'USD'}],total_global_discount:'0.01'})
    view.config.active_allowance_charge=false
    view.form={...view.form,currency_type_id:'VES',exchange_rate_sale:'3',total_prepayment:'0',prepayments:[],
        items:[calculateRowItem({item:{id:1,unit_price:'1',currency_type_id:'VES'},quantity:'1',
            affectation_igv_type_id:'EXP',discounts:[],charges:[]},'VES','3','0.16',true)]}
    assert.equal(view.calculateTotal(),true);assert.equal(view.form.total,'0.99')
    assert.equal(view.changeInvoiceCurrencyType('USD'),true)
    assert.deepEqual(JSON.parse(JSON.stringify(view.invoiceGlobalDiscountExact().fraction())),{n:'1',d:'300'})
    assert.deepEqual(JSON.parse(JSON.stringify(view.form.discounts[0].amount_exact)),{n:'1',d:'300'})
    assert.equal(view.form.total,'0.33')
    assert.equal(view.changeInvoiceCurrencyType('VES'),true);assert.equal(view.form.total,'0.99')
    assert.equal(JSON.parse(JSON.stringify(view.form)).discounts[0].amount,'0.01')
})

test('reopening a tiny export discount keeps its original percentage and exact amount', () => {
    const {calculateRowItem} = require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/functions.js'))
    const newDraft = () => {
        const view = state()
        const noop = () => {}
        Object.assign(view, {calculateTotal: invoice.methods.calculateTotal, enabled_discount_global: true, isExportInvoice: true,
            configuration: {global_discount_type_id: '03', exact_discount: false}, global_discount_type: {id: '03', description: 'Descuento'},
            percentage_igv: '0.16', setTotalDefaultPayment: noop, setPendingAmount: noop, calculateFee: noop, setTotalPointsBySale: noop,
            is_amount: false, total_global_discount: '0.000000000001', currency_types: [{id: 'VES'}, {id: 'USD'}]})
        view.config.active_allowance_charge = false
        Object.assign(view.form, {exchange_rate_sale: '2', total_prepayment: '0', prepayments: [],
            items: [calculateRowItem({item: {id: 1, unit_price: '100000000000000', currency_type_id: 'VES'}, quantity: '1',
                affectation_igv_type_id: 'EXP', discounts: [], charges: []}, 'VES', '2', '0.16', true)]})
        return view
    }
    const original = newDraft()
    assert.equal(original.calculateTotal(), true)
    const saved = JSON.parse(JSON.stringify(original.form.discounts[0]))
    assert.equal(saved.factor, '0')
    assert.equal(saved.percentage, '0.000000000001')
    assert.equal(saved.amount, '1')
    for (const discount of [saved, {...saved, percentage: null}]) {
        const draft = newDraft()
        draft.total_global_discount = '0'
        assert.equal(draft.restoreInvoiceGlobalDiscount({discounts: [discount]}), true)
        assert.equal(draft.calculateTotal(), true)
        assert.equal(draft.form.total, '99999999999999')
        assert.deepEqual(JSON.parse(JSON.stringify(draft.form.discounts[0].amount_exact)), {n: '1', d: '1'})
        assert.equal(draft.changeInvoiceCurrencyType('USD'), true)
        assert.equal(draft.form.total, '49999999999999.5')
        assert.equal(draft.applyInvoiceDecimal('discount', '0'), '0')
        assert.equal(draft.form.total, '50000000000000')
    }
})

test('reopening mixed domestic lines restores only the global fraction, including when its current preference is disabled',()=>{
    const {calculateRowItem}=require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/functions.js'))
    for (const enabled of [true,false]) for (const grossInput of [true,false]) {
        const view=state(),noop=()=>{}
        Object.assign(view,{calculateTotal:invoice.methods.calculateTotal,enabled_discount_global:true,isExportInvoice:false,
            configuration:{global_discount_type_id:'02',exact_discount:grossInput},global_discount_type:{id:'02',description:'Descuento'},
            recordDiscountsGlobal:null,percentage_igv:'0.16',prepayment_deduction:false,
            setTotalDefaultPayment:noop,setPendingAmount:noop,calculateFee:noop,setTotalPointsBySale:noop})
        view.config.active_allowance_charge=false;view.form.exchange_rate_sale='1';view.form.total_prepayment='0';view.form.prepayments=[]
        view.form.items=[['116','10'],['100','20']].map(([price,affectation],index)=>calculateRowItem({
            item:{id:index+1,unit_price:price,currency_type_id:'VES'},quantity:'1',affectation_igv_type_id:affectation,
            discounts:index===0?[{discount_type_id:'00',amount:'0.1',amount_exact:{n:'1',d:'10'},is_amount:true}]:[],charges:[]},'VES','1','0.16'))
        view.setInvoiceGlobalDiscount(math.exactAmount('0.01').dividedBy(3));assert.equal(view.calculateTotal(),true)
        const values=['total','total_igv','total_discount'].map(key=>math.exactValue(view.form,key).fraction())
        const previousShares=view.form.items.map(row=>row.discounts.filter(d=>d.from_global_distribution).map(d=>d.amount_exact))
        view.form.items=JSON.parse(JSON.stringify(view.form.items));view.form.items.forEach(row=>delete row._original_before_global_discount)
        view.total_global_discount='0';view.invoiceGlobalDiscountSource=null;view.enabled_discount_global=enabled
        assert.equal(view.restoreInvoiceGlobalDiscount({discounts:[]}),true)
        assert.equal(view.enabled_discount_global,enabled);assert.equal(view.is_amount,true)
        assert.equal(view.calculateTotal(),true)
        assert.deepEqual(['total','total_igv','total_discount'].map(key=>math.exactValue(view.form,key).fraction()),values)
        assert.deepEqual(JSON.parse(JSON.stringify(view.form.items.map(row=>row.discounts.filter(d=>d.from_global_distribution).map(d=>d.amount_exact)))),
            JSON.parse(JSON.stringify(previousShares)))
        const own=view.form.items[0].discounts.filter(d=>!d.from_global_distribution)
        assert.equal(own.length,1);assert.equal(math.exactAmount(own[0].amount_exact).compare('0.1'),0)
        view.currency_types=[{id:'VES'},{id:'USD'}];view.form.exchange_rate_sale='3'
        view.setInvoiceGlobalCharge(math.exactAmount('0.01').dividedBy(7));assert.equal(view.calculateTotal(),true)
        const withCharge=['total','total_igv','total_discount','total_charge'].map(key=>math.exactValue(view.form,key))
        assert.equal(view.changeInvoiceCurrencyType('USD'),true)
        for (const [index,key] of ['total','total_igv','total_discount','total_charge'].entries()) {
            assert.equal(math.exactValue(view.form,key).compare(withCharge[index].dividedBy(3)),0,key)
        }
        assert.equal(view.changeInvoiceCurrencyType('VES'),true)
        for (const [index,key] of ['total','total_igv','total_discount','total_charge'].entries()) {
            assert.equal(math.exactValue(view.form,key).compare(withCharge[index]),0,key)
        }
        assert.equal(view.enabled_discount_global,enabled)
    }
})

test('clearing the global discount preserves the individual fraction in a VES invoice', () => {
    const {calculateRowItem}=require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/functions.js'))
    for (const exporting of [false,true]) {
        const view=state(), noop=()=>{}, discountType=exporting?'03':'02'
        Object.assign(view,{calculateTotal:invoice.methods.calculateTotal,enabled_discount_global:true,isExportInvoice:exporting,
            configuration:{global_discount_type_id:discountType,exact_discount:false},global_discount_type:{id:discountType},
            recordDiscountsGlobal:exporting?{discount_type_id:discountType}:null,percentage_igv:'0.16',prepayment_deduction:false,
            setTotalDefaultPayment:noop,setPendingAmount:noop,calculateFee:noop,setTotalPointsBySale:noop})
        view.config.active_allowance_charge=false
        const amount='0.000000000001'
        view.form={...view.form,currency_type_id:'VES',exchange_rate_sale:'1',total_prepayment:'0',prepayments:[],
            items:[calculateRowItem({item:{id:1,unit_price:'100',currency_type_id:'VES'},quantity:'1',
                affectation_igv_type_id:exporting?'EXP':'20',discounts:[{discount_type_id:'01',amount,is_amount:true}],charges:[]},'VES','1','0.16',exporting)]}
        view.setInvoiceGlobalDiscount(math.exactAmount('10'))
        assert.equal(view.calculateTotal(),true)
        view.setInvoiceGlobalDiscount(math.exactAmount('0'))
        assert.equal(view.calculateTotal(),true)
        assert.equal(math.exactValue(view.form,'total_discount').compare(amount),0)
        assert.equal(math.exactValue(view.form,'total').compare('99.999999999999'),0)
    }
})

test('nonproportional stored global shares block recalculation without changing the loaded lines',()=>{
    const {calculateRowItem}=require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/functions.js'))
    const view=state();Object.assign(view,{configuration:{exact_discount:false},percentage_igv:'0.16',isExportInvoice:false})
    view.form.exchange_rate_sale='1'
    view.form.items=['0.1','0.2'].map((amount,index)=>calculateRowItem({item:{id:index+1,unit_price:'1',currency_type_id:'VES'},
        quantity:'1',affectation_igv_type_id:'20',discounts:[{discount_type_id:'01',amount,is_amount:true,from_global_distribution:true}],charges:[]},'VES','1','0.16'))
    const before=JSON.stringify(view.form)
    assert.equal(view.restoreInvoiceGlobalDiscount({discounts:[]}),false)
    assert.equal(JSON.stringify(view.form),before);assert.ok(view.invoiceDiscountRestoreError)
    assert.equal(invoice.methods.calculateTotal.call(view),false);assert.equal(JSON.stringify(view.form),before)
})

test('actual domestic and export calculations reject excess discount without removing the previous one',()=>{
    const {calculateRowItem}=require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/functions.js'))
    for(const exporting of [false,true]) {
        const view=state(),noop=()=>{}
        Object.assign(view,{calculateTotal:invoice.methods.calculateTotal,enabled_discount_global:true,isExportInvoice:exporting,
            configuration:{global_discount_type_id:exporting?'03':'02',exact_discount:false},global_discount_type:{id:exporting?'03':'02',description:'Descuento'},
            recordDiscountsGlobal:exporting?{discount_type_id:'03'}:null,percentage_igv:'0.16',prepayment_deduction:false,
            setTotalDefaultPayment:noop,setPendingAmount:noop,calculateFee:noop,setTotalPointsBySale:noop})
        view.config.active_allowance_charge=false
        view.form={...view.form,currency_type_id:'VES',exchange_rate_sale:'1',total_prepayment:'0',prepayments:[],
            items:[calculateRowItem({item:{id:1,unit_price:'100',currency_type_id:'VES'},quantity:'1',
                affectation_igv_type_id:exporting?'EXP':'20',discounts:[],charges:[]},'VES','1','0.16',exporting)]}
        assert.equal(view.calculateTotal(),true);assert.equal(view.form.total,'90')
        const original=view.form,row=view.form.items[0],before=JSON.stringify(view.form)
        const field=control({value:'10',apply:(value,input)=>view.applyInvoiceDecimal('discount',value,null,input)})
        view.$refs.invoiceDiscountInput=field;field.onInput('200');assert.equal(field.commitPending(),false)
        assert.equal(view.form,original);assert.equal(view.form.items[0],row);assert.equal(JSON.stringify(view.form),before)
        assert.equal(view.total_global_discount,'10');assert.equal(field.dirty,true);field.$destroy()
    }
})

test('removing an invoice line with an excessive existing discount blocks submit and preview until corrected',async()=>{
    const {calculateRowItem}=require('./helpers/load-module.cjs')(path.resolve('resources/js/helpers/functions.js'))
    const view=state(),noop=()=>{},errors=[]
    Object.assign(view,{calculateTotal:invoice.methods.calculateTotal,enabled_discount_global:true,isExportInvoice:false,
        configuration:{global_discount_type_id:'02',exact_discount:false},global_discount_type:{id:'02',description:'Descuento'},
        recordDiscountsGlobal:null,percentage_igv:'0.16',prepayment_deduction:false,
        setTotalDefaultPayment:noop,setPendingAmount:noop,calculateFee:noop,setTotalPointsBySale:noop,
        $message:{error:message=>errors.push(message)},$http:{post:()=>assert.fail('invalid draft was submitted')}})
    view.config.active_allowance_charge=false;view.total_global_discount='80'
    view.form={...view.form,exchange_rate_sale:'1',total_prepayment:'0',prepayments:[],items:[1,2].map(id=>calculateRowItem({
        item:{id,unit_price:'50',currency_type_id:'VES'},quantity:'1',affectation_igv_type_id:'20',discounts:[],charges:[]},'VES','1','0.16'))}
    assert.equal(view.calculateTotal(),true);assert.equal(view.form.total,'20')
    view.clickRemoveItem(0);assert.equal(view.form.items.length,1);assert.ok(view.invoiceCalculationError)
    await view.submit();assert.equal(await view.validatePreview(),false);assert.ok(errors.length>=3)
    assert.equal(view.applyInvoiceDecimal('discount','10'),'10');assert.equal(view.form.total,'40')
    assert.equal(view.invoiceCalculationError,null)
})
