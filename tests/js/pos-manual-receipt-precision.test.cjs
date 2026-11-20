const {test} = require('node:test')
const assert = require('node:assert/strict')
const path = require('node:path')
const Vue = require('vue')
const {methods} = require('./support/pos-invoice-runtime.cjs')
const load = require('./helpers/load-module.cjs')
const summary = load(path.resolve('resources/js/mixins/payment-settlement-summary.js')).default

for (const panel of ['payment', 'fast_payment', 'fast_payment_garage']) {
    test(panel + ' validates manual invoice receipts before projecting the amount', async () => {
        const {enterAmount} = methods('partials/' + panel, ['enterAmount'])
        let recalculations = 0
        const instance = new Vue({
            computed: summary.computed,
            data: () => ({
                useLocalCalculations: true,
                form: {document_type_id: '01', payment_condition_id: '01', payments: [{payment: '0.01'}]},
                enter_amount: '0.0049999999999',
            }),
            methods: {inputAmount() { recalculations++ }, enterAmount},
        })
        try {
            assert.equal(instance.singleAmountInvalid, true)
            await instance.enterAmount()
            assert.equal(instance.form.payments[0].payment, '0.01')
            assert.equal(recalculations, 0)

            instance.form.payment_condition_id = '02'
            assert.equal(instance.singleAmountInvalid, false)
            instance.form.payment_condition_id = '01'

            instance.enter_amount = '0.004999999999'
            assert.equal(instance.singleAmountInvalid, false)
            await instance.enterAmount()
            assert.equal(instance.form.payments[0].tendered_amount, '0.004999999999')
            assert.equal(recalculations, 1)

            instance.enter_amount = '1000000000000000000'
            assert.equal(instance.singleAmountInvalid, true)
            instance.form.payments = []
            assert.equal(instance.singleAmountInvalid, true)
            await instance.enterAmount()
            assert.equal(instance.form.payments.length, 0)
            assert.equal(recalculations, 1)

            instance.form.document_type_id = '80'
            instance.form.payments = [{payment: '0.01'}]
            instance.enter_amount = '0.0049999999999'
            assert.equal(instance.singleAmountInvalid, false)
            await instance.enterAmount()
            assert.equal(instance.form.payments[0].payment, '0.005')
        } finally {
            instance.$destroy()
        }
    })
}
