const path = require('node:path')
const fs = require('node:fs')
const load = require('./load-module.cjs')
const {localPaymentQuote} = load(path.resolve('resources/js/helpers/local-payment-quote.js'))
const methods = [{id:'10',currency_type_id:'USD',collection_kind:'cash',is_active:true},
    {id:'04',currency_type_id:'VES',collection_kind:'cash',is_active:true},
    {id:'01',currency_type_id:'VES',collection_kind:'account',is_active:true}]
const context = {enabled:true,rate:'3',dates:{'2026-09-10':{issuer_spe:true}}}
const fixtures = JSON.parse(fs.readFileSync(path.resolve('tests/fixtures/local-payment-quotes.json')))
const results = fixtures.map(fixture => {
    const request = {currency_type_id:fixture.currency, exchange_rate_sale:fixture.rate,principal_due:fixture.due,
        payments:fixture.payments.map(row => ({date_of_payment:fixture.date || '2026-09-10',exchange_rate:fixture.rate,...row}))}
    try { return {name:fixture.name,quote:localPaymentQuote(request,{...context,enabled:fixture.enabled ?? true,rate:fixture.igtf_rate || '3',dates:{[fixture.date || '2026-09-10']:{issuer_spe:fixture.issuer_spe ?? true}}},methods,fixture.kind || '01')} }
    catch(error) { return {name:fixture.name,error:error.message} }
})
if (require.main === module) process.stdout.write(JSON.stringify(results))
module.exports = {results,localPaymentQuote,methods,context,fixtures}
