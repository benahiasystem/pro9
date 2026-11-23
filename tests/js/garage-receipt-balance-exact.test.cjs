const {test} = require('node:test')
const assert = require('node:assert/strict')
const {methods} = require('./support/pos-invoice-runtime.cjs')

test('Garage convierte recibo USD antes de habilitar cobro VES', async () => {
    const actual=methods('partials/fast_payment_garage',['enterAmount','inputAmount'])
    const vm={...actual,form:{document_type_id:'01',payment_condition_id:'01',currency_type_id:'VES',exchange_rate_sale:'3',payments:[{currency_type_id:'USD',exchange_rate:'3',payment:'0'}]},useLocalCalculations:true,collectionTarget:'100',singleAmountLocked:false,singleAmountInvalid:false,$set:(o,k,v)=>o[k]=v,$message:{warning(m){throw Error(m)}},$eventHub:{$emit(){}},lStoPayment(){}}
    for(const [entered,applied,balance,blocked] of [['33.33','99.99','-0.01',true],['33.34','100.02','0.02',false],['0','0','-100',true]]) {
        vm.enter_amount=entered; await vm.enterAmount()
        assert.equal(vm.form.payments[0].payment,applied); assert.equal(vm.difference,balance); assert.equal(vm.button_payment,blocked)
    }
    vm.form.payments[0].exchange_rate='4'; vm.enter_amount='25'; await vm.enterAmount()
    assert.equal(vm.form.payments[0].payment,'100'); assert.equal(vm.difference,'0'); assert.equal(vm.button_payment,false)
    vm.form.payments[0].exchange_rate='3'; vm.form.payment_condition_id='02'; vm.enter_amount='33.33'; await vm.enterAmount()
    assert.equal(vm.difference,'-0.01'); assert.equal(vm.button_payment,false)
})
