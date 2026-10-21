const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs'), vm = require('node:vm')
const babel = require('@babel/core'), compiler = require('vue-template-compiler')
const exactRuntime = require('./support/exact-runtime.cjs')

function component(file) {
    const parsed = compiler.parseComponent(fs.readFileSync(file,'utf8'))
    assert.deepEqual(compiler.compile(parsed.template.content).errors,[])
    const output = {}
    vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
        {exports:output, require:name=>exactRuntime(name) || (name.includes('vuex') ? {mapState:()=>({}),mapActions:()=>({})} : {})})
    return output.default
}

test('receivables use global exact summaries and preserve the server arrears and row fractions', async () => {
    const c = component('modules/Finance/Resources/assets/js/views/unpaid/index.vue')
    const row = {id:1,total_to_pay:'0',arrears:'0.123456789012',calculation_snapshot:{version:1,values:{total_to_pay:{n:'1',d:'10000000000000'}}}}
    const summary = {VES_balance:'1.000000000001',USD_balance:'2',VES_total:'500',USD_total:'10',count:501}
    const ctx = {resource:'finances/unpaid',pagination:{},getQueryParameters:()=>'',
        $http:{get:async()=>({data:{data:[row],summary,meta:{per_page:50,total:501},configuration:{apply_arrears:true,arrears_amount:'999'}}})}}
    await c.methods.loadUnpaid.call(ctx)
    assert.equal(c.computed.getCurrentBalance.call(ctx),'1.000000000001')
    assert.equal(c.computed.getTotalRowsUnpaid.call(ctx),501)
    assert.equal(ctx.records[0].arrears,'0.123456789012')
    assert.equal(c.methods.isPositiveBalance.call(ctx,row),true)
})

test('payables and consolidated sales templates expose exact server values without assets', () => {
    const row={settlement_adjustment:'0',calculation_snapshot:{version:1,values:{settlement_adjustment:{n:'-1',d:'10000000000000'}}}}
    for (const file of ['to_pay','unpaid']) {
        const c=component(`modules/Finance/Resources/assets/js/views/${file}/index.vue`)
        assert.equal(c.methods.hasSettlementAdjustment(row),true)
        assert.equal(c.methods.settlementAdjustmentText(row),'-1/10000000000000')
        assert.equal(c.methods.hasSettlementAdjustment({settlement_adjustment:'0'}),false)
    }
    component('modules/Report/Resources/assets/js/views/sales_consolidated/index.vue')
})

test('dashboard debtor groups operate on retained fractions and stock shows all business decimals', () => {
    const debtors = component('modules/Dashboard/Resources/assets/js/views/partials/Debtors.vue')
    const debt = (n,d) => ({total_to_pay:'0.333333333333',status:'vencido',calculation_snapshot:{version:1,values:{total_to_pay:{n,d}}}})
    const rows = [debt('1','3'),debt('2','3')]
    const total = debtors.computed.urgentTotal.call({urgentDebts:rows})
    assert.equal(total.decimal(),'1')
    const groups = debtors.computed.dateGroups.call({visibleDebts:rows})
    assert.equal(groups[0].total.decimal(),'1')
    assert.equal(debtors.filters.dbMoney(total),'1')
    assert.equal(debtors.filters.dbMoney('0.123456789012'),'0,123456789012')
    assert.equal(debtors.filters.dbMoney('999999999999999999.99'),'999.999.999.999.999.999,99')
    const stock = component('modules/Dashboard/Resources/assets/js/views/partials/LowStock.vue')
    assert.equal(stock.methods.stockText({stock:'0.123456789012'}),'0.123456789012')
})
