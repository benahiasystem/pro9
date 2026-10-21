const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), vm = require('node:vm');
const compiler = require('vue-template-compiler'), babel = require('@babel/core');
const Vue = require('vue'), _ = require('lodash');
const runtime = require('./support/exact-runtime.cjs');
const creation = runtime('document-creation');
const {exactAmount, exactValue, exactSum} = runtime('exchange-rate-math');
function load(source) {
    const exports = {};
    vm.runInNewContext(babel.transformSync(source, {configFile:false, babelrc:false, plugins:['@babel/plugin-transform-modules-commonjs']}).code,
        {exports, _, require: name => runtime(name) || (name === 'moment' ? require('moment') : name.includes('vuex') ? {mapActions:()=>({}), mapState:()=>({})} : {})});
    return exports;
}
const componentSource = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue', 'utf8'));
const component = load(componentSource.script.content).default;
const {calculateRowItem} = load(fs.readFileSync('resources/js/helpers/functions.js', 'utf8'));
function line(price, quantity = '1', affectation = '10') {
    return calculateRowItem({item:{id:1, unit_price:price, currency_type_id:'VES', has_igv:true},
        quantity, affectation_igv_type_id:affectation, discounts:[], charges:[], attributes:[]}, 'VES', '1');
}
function invoice() {
    const state = new Vue({data:()=>({form:{items:[], payments:[{payment:'0', reference:'ref', payment_destination_id:'cash', payment_method_type_id:'01'}],
        fee:[], discounts:[], charges:[], total_prepayment:'0', currency_type_id:'VES', payment_condition_id:'01', total:'0'},
        config:{}, enabled_discount_global:false, isExportInvoice:false, total_global_charge:'0', isUpdateDocument:false}),
        computed:{collectionTarget(){return creation.collectionTarget(this.form)}},
        methods:{...Object.fromEntries(['calculateTotal','calculatePayments','setTotalDefaultPayment','chargeGlobal','setPendingAmount','calculateFee','getTotal','clickCancel','clickRemoveItem','markPaymentEdited'].map(k=>[k,component.methods[k]])), setTotalPointsBySale(){}}});
    return state;
}
test('invoice totals and the suggested cash payment follow all lines, quantity edits and removals', async () => {
    const state = invoice();
    for (const [item, expected] of [[line('500','2'),'1000'],[line('250'),'1250'],[line('500'),'1750']]) {
        state.form.items.push(item); state.calculateTotal(); await Vue.nextTick();
        assert.equal(state.form.total, expected);
        assert.equal(state.form.payments[0].payment, expected+'.00');
        assert.equal(exactValue(state.form,'total_taxed').plus(exactValue(state.form,'total_igv')).decimal(),expected);
    }
    state.form.items.splice(0,1,line('500','3')); state.calculateTotal();
    assert.equal(state.form.total,'2250'); assert.equal(state.form.payments[0].payment,'2250.00');
    state.clickRemoveItem(1);
    assert.equal(state.form.total,'2000'); assert.equal(state.form.payments[0].payment,'2000.00');
    state.clickRemoveItem(1); state.clickRemoveItem(0);
    assert.equal(state.form.total,'0'); assert.equal(state.form.payments[0].payment,'0.00'); state.$destroy();
});
test('discounts, charges, exempt lines and final cent settlement use the same collectible total', () => {
    const state = invoice();
    state.form.items=[line('500'),line('250','1','20')]; state.calculateTotal();
    assert.equal(state.form.payments[0].payment,'750.00');
    state.form.discounts=[{discount_type_id:'03', amount:'25.005'}]; state.total_global_charge='10'; state.calculateTotal();
    assert.equal(state.form.total,'734.995'); assert.equal(state.form.total_payable,'735');
    assert.equal(state.form.payments[0].payment,'735.00'); state.$destroy();
});
test('manual amounts including zero remain manual; a new row follows only the outstanding amount', () => {
    const state=invoice(); state.form.items=[line('500')]; state.calculateTotal();
    state.form.payments[0].payment='200'; creation.markPaymentEdited(state.form.payments[0]);
    state.form.items.push(line('250')); state.calculateTotal(); assert.equal(state.form.payments[0].payment,'200');
    state.form.payments.push({payment:'0'}); state.calculatePayments(); assert.equal(state.form.payments[1].payment,'550.00');
    state.form.items.push(line('500')); state.calculateTotal();
    assert.equal(state.form.payments[0].payment,'200'); assert.equal(state.form.payments[1].payment,'1050.00');
    assert.equal(exactSum(state.form.payments.map(p=>p.payment)).decimal(),state.form.total_payable);
    state.form.payments[0].payment='300'; state.markPaymentEdited(state.form.payments[0]);
    assert.equal(state.form.payments[1].payment,'950.00');
    state.form.payments[1].payment='0'; state.markPaymentEdited(state.form.payments[1]);
    state.calculateTotal(); assert.equal(state.form.payments[1].payment,'0'); state.$destroy();
});
test('credit, existing receipts, restored amounts and foreign-currency receipts are preserved', () => {
    for (const payment of [{payment:'100',id:1},{payment:'100'}, {payment:'0',currency_type_id:'USD',original_amount:'3',exchange_rate:'40'}]) {
        const original = JSON.stringify(payment);
        const state=invoice(); state.form.items=[line('500')]; state.form.payments=[payment]; state.calculateTotal();
        assert.equal(JSON.stringify(state.form.payments[0]),original); state.$destroy();
    }
    const state=invoice(); state.form.payment_condition_id='02'; state.form.items=[line('500')]; state.calculateTotal();
    assert.equal(state.form.payments[0].payment,'0'); state.$destroy();
});
test('USD invoices update their suggested payment and explicit edits stop automatic changes even at the same amount', () => {
    const form={total:'10',currency_type_id:'USD',payments:[{payment:'0'}]};
    creation.suggestRemainingPayment(form,form.payments,{refreshSuggested:true});
    form.total='20'; creation.suggestRemainingPayment(form,form.payments,{refreshSuggested:true});
    assert.equal(form.payments[0].payment,'20.00');
    creation.markPaymentEdited(form.payments[0]);
    form.total='30'; creation.suggestRemainingPayment(form,form.payments,{refreshSuggested:true});
    assert.equal(form.payments[0].payment,'20.00');
});
test('suggestion tracking is private, updates matching-currency original amount and respects changed inputs', () => {
    const form={total:'100',currency_type_id:'VES',payments:[{payment:'0',currency_type_id:'VES',original_amount:'0'}]};
    creation.suggestRemainingPayment(form,form.payments,{refreshSuggested:true});
    form.total='150'; creation.suggestRemainingPayment(form,form.payments,{refreshSuggested:true});
    assert.equal(form.payments[0].payment,'150.00'); assert.equal(form.payments[0].original_amount,'150.00');
    assert.deepEqual(Object.keys(form.payments[0]).sort(),['currency_type_id','original_amount','payment']);
    form.payments[0].payment='50'; form.total='200'; creation.suggestRemainingPayment(form,form.payments,{refreshSuggested:true});
    assert.equal(form.payments[0].payment,'50');
    assert.deepEqual(compiler.compile(componentSource.template.content).errors,[]);
    assert.match(componentSource.template.content, /@input="markPaymentEdited\(row\)"/);
});
