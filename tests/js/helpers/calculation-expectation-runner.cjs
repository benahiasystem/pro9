const fs=require('node:fs'),path=require('node:path'),load=require('./load-module.cjs')
const {form,context,revision}=JSON.parse(fs.readFileSync(0,'utf8'))
const {documentTotals}=load(path.resolve('resources/js/helpers/exact-document.js'))
const {calculateRowItem}=load(path.resolve('resources/js/helpers/functions.js'))
const {exactAmount}=load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const {localPaymentQuote}=load(path.resolve('resources/js/helpers/local-payment-quote.js'))
const {calculationExpectation}=load(path.resolve('resources/js/helpers/calculation-expectation.js'))
form.items=form.items.map(row=>calculateRowItem({...row,price_input:row.price_input || {amount:String(row.unit_price),currency_type_id:form.currency_type_id,includes_iva:true}},
    form.currency_type_id,form.exchange_rate_sale,exactAmount(row.percentage_igv || '16').dividedBy('100').decimal()))
Object.assign(form,documentTotals(form.items,form.discounts,form.charges))
const quote=localPaymentQuote({currency_type_id:form.currency_type_id,exchange_rate_sale:form.exchange_rate_sale,
    principal_due:form.total_payable,commercial_total_exact:form.calculation_snapshot.values.total,payments:form.payments},context,context.methods,form.document_type_id)
process.stdout.write(JSON.stringify(calculationExpectation(form,quote,revision,context.methods,context.destinations)))
