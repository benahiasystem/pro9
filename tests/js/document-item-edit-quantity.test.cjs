const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs'), vm = require('node:vm')
const compiler = require('vue-template-compiler'), parser = require('@babel/parser')
const generate = require('@babel/generator').default
const {exactAmount,exactValue} = require('./support/exact-runtime.cjs')('exchange-rate-math')
const {calculateRowItem}=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/functions.js'))

test('reopening a total-based line preserves its quantity and restores the entered total', () => {
    const component=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/item.vue','utf8'))
    const tree=parser.parse(component.script.content,{sourceType:'module'})
    const props=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties
    const methods=props.find(node=>node.key?.name==='methods').value.properties
    const pick=name=>generate(methods.find(node=>node.key?.name===name)).code
    const functions=vm.runInNewContext(`({${['restoreEditedQuantity','calculateQuantity','calculateTotal','currentExactQuantity','onQuantityChange','normalizeDecimal'].map(pick).join(',')}})`,{exactAmount,exactValue})
    const row={quantity:'1',calculation_snapshot:{version:1,values:{quantity:{n:'1',d:'1'}},inputs:{}}}
    const state={...functions,recordItem:row,form:{item:{calculate_quantity:true},unit_price_value:'100',quantity:0},total_item:null}
    state.restoreEditedQuantity()
    assert.equal(state.form.quantity,1)
    assert.equal(state.total_item,'100')
    assert.equal(state.readonly_total,'100')
    state.total_item='50';state.calculateQuantity()
    assert.equal(state.form.quantity,'0.5')
    assert.equal(state.readonly_total,'50')
    const fraction={amount:'1',unit_price:'3'}
    state.recordItem={quantity:'0.333333333333',calculation_snapshot:{version:1,values:{quantity:{n:'1',d:'3'}},inputs:{quantity_input:fraction}}}
    state.form.unit_price_value='100'; state.restoreEditedQuantity()
    assert.equal(state.total_item,'33.333333333333')
    assert.equal(state.readonly_total,'33.333333333333')
    assert.deepEqual(state.form.quantity_input,fraction)
    state.form.quantity=0.5; state.onQuantityChange(0.5)
    assert.equal(state.form.quantity_input,null)
    assert.equal(state.readonly_total,'50')
    state.recordItem={quantity:'0.333333333333',calculation_snapshot:{version:1,values:{quantity:{n:'1',d:'3'}},inputs:{}}}
    state.restoreEditedQuantity()
    assert.equal(state.currentExactQuantity().fraction().n,'1')
    assert.equal(state.currentExactQuantity().fraction().d,'3')
    assert.deepEqual(compiler.compile(component.template.content).errors,[])
})

test('the actual item submission retains one third and its exact percentage discount', async () => {
    const component=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/item.vue','utf8'))
    const tree=parser.parse(component.script.content,{sourceType:'module'})
    const props=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties
    const methods=props.find(node=>node.key?.name==='methods').value.properties
    const pick=name=>generate(methods.find(node=>node.key?.name===name)).code
    const functions=vm.runInNewContext(`({${['restoreEditedQuantity','calculateTotal','currentExactQuantity','normalizeDecimal','clickAddItem'].map(pick).join(',')}})`,{
        exactAmount,exactValue,calculateRowItem,resolveSelectableAffectationType:()=>true,_:require('lodash')})
    let emitted
    const row={id:42,indexi:0,quantity:'0.333333333333',calculation_snapshot:{version:1,values:{quantity:{n:'1',d:'3'}},inputs:{}}}
    const state={...functions,recordItem:row,form:{item:{id:1,description:'Servicio de prueba',currency_type_id:'VES',unit_price:'3',lots:[],calculate_quantity:true},
        quantity:0,unit_price_value:'3',has_igv:true,affectation_igv_type_id:'20',discounts:[{discount_type_id:'02',percentage:'10'}],charges:[],attributes:[]},
        config:{},configuration:{},currencyTypeIdActive:'VES',exchangeRateSale:'1',percentageIgv:'0.16',operation_types:[{id:'0101',exportation:false}],
        operationTypeId:'0101',affectation_igv_types:[{id:'20'}],item_unit_type:{},isExportation:false,
        applyValidateStock:()=>false,validateQuantity(){this.calculateTotal()},validateTotalItem:()=>({}),
        initForm(){},close(){},$emit:(_,value)=>{emitted=value},$message:{error:message=>{throw new Error(message)}}}
    state.restoreEditedQuantity()
    await state.clickAddItem()
    assert.ok(emitted)
    assert.equal(emitted.record_id,42)
    assert.equal(emitted.calculation_snapshot.values.quantity.n,'1')
    assert.equal(emitted.calculation_snapshot.values.quantity.d,'3')
    assert.equal(emitted.discounts[0].amount_exact.n,'1')
    assert.equal(emitted.discounts[0].amount_exact.d,'10')
    assert.equal(emitted.total,'0.9')
})
