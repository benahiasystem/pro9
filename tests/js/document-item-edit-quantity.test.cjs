const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs'), vm = require('node:vm')
const compiler = require('vue-template-compiler'), parser = require('@babel/parser')
const generate = require('@babel/generator').default
const {exactAmount,exactInput,exactValue} = require('./support/exact-runtime.cjs')('exchange-rate-math')
const {calculateRowItem}=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/functions.js'))

test('the invoice item minimum is checked without coercing decimal text', () => {
    const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/item.vue', 'utf8'))
    const tree = parser.parse(component.script.content, {sourceType: 'module'})
    const props = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties
    const methods = props.find(node => node.key?.name === 'methods').value.properties
    const method = generate(methods.find(node => node.key?.name === 'validateTotalItem')).code
    const {validateTotalItem} = vm.runInNewContext(`({${method}})`, {exactAmount})
    const state = {
        isFromInvoice: true,
        form: {item: {calculate_quantity: true}},
        $set(object, key, value) { object[key] = value },
    }
    for (const amount of ['0.0099999999999999999', '0.009999999999', 'importe inválido']) {
        state.total_item = amount
        assert.ok(validateTotalItem.call(state).total_item, amount)
    }
    for (const amount of ['0.01', '0.010000000001']) {
        state.total_item = amount
        assert.equal(validateTotalItem.call(state).total_item, undefined, amount)
    }
    state.form.item.calculate_quantity = false
    state.total_item = '0.001'
    assert.equal(validateTotalItem.call(state).total_item, undefined)
})

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
    assert.equal(state.form.quantity,'1')
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
    const functions=vm.runInNewContext(`({${['restoreEditedQuantity','calculateTotal','currentExactQuantity','normalizeDecimal','clickAddItem','itemToDocumentConversion','currentPriceInputAmount'].map(pick).join(',')}})`,{
        exactAmount,exactValue,calculateRowItem,resolveSelectableAffectationType:()=>true,_:require('lodash')})
    let emitted
    const row={id:42,indexi:0,quantity:'0.333333333333',source_sale_note_items:[{sale_note_item_id:'9007199254740993'}],
        calculation_snapshot:{version:1,values:{quantity:{n:'1',d:'3'}},inputs:{}}}
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
    assert.equal(emitted.source_sale_note_items[0].sale_note_item_id,'9007199254740993')
    assert.notEqual(emitted.source_sale_note_items[0],row.source_sale_note_items[0])
    assert.equal(emitted.calculation_snapshot.values.quantity.n,'1')
    assert.equal(emitted.calculation_snapshot.values.quantity.d,'3')
    assert.equal(emitted.discounts[0].amount_exact.n,'1')
    assert.equal(emitted.discounts[0].amount_exact.d,'10')
    assert.equal(emitted.total,'0.9')
})

test('the invoice quantity buttons send the adjusted rational quantity in the line payload', async () => {
    const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/item.vue', 'utf8'))
    const tree = parser.parse(component.script.content, {sourceType: 'module'})
    const methods = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties
        .find(node => node.key?.name === 'methods').value.properties
    const selected = ['restoreEditedQuantity', 'calculateTotal', 'currentExactQuantity', 'normalizeDecimal', 'clickAddItem',
        'itemToDocumentConversion', 'currentPriceInputAmount', 'clickIncrease', 'clickDecrease', 'adjustInvoiceQuantity',
        'getMinQuantity', 'setMinQuantity', 'validateQuantity', 'validateCurrentStock']
    const code = selected.map(name => generate(methods.find(node => node.key?.name === name)).code).join(',')
    const functions = vm.runInNewContext('({' + code + '})', {
        exactAmount, exactValue, calculateRowItem, resolveSelectableAffectationType: () => true, _: require('lodash')
    })
    const original = exactAmount({n: '1', d: '3'})
    let emitted
    const state = {
        ...functions, isFromInvoice: true, quantityStep: 1,
        recordItem: {id: 42, indexi: 0, quantity: original.decimal(), calculation_snapshot: {values: {quantity: original.fraction()}, inputs: {}}},
        form: {quantity: 0, item: {id: 1, description: 'Servicio de prueba', currency_type_id: 'VES', unit_price: '3', lots: [], calculate_quantity: false},
            unit_price_value: '3', has_igv: true, affectation_igv_type_id: '20', discounts: [], charges: [], attributes: []},
        config: {}, configuration: {}, currencyTypeIdActive: 'VES', exchangeRateSale: '1',
        percentageIgv: '0.16', operation_types: [{id: '0101', exportation: false}], operationTypeId: '0101',
        affectation_igv_types: [{id: '20'}], item_unit_type: {}, isExportation: false, applyValidateStock: () => false,
        validateTotalItem: () => ({}), initForm() {}, close() {},
        $emit: (_, value) => { emitted = value }, $message: {error: message => { throw new Error(message) }}
    }
    state.restoreEditedQuantity()
    state.clickIncrease()
    assert.equal(state.currentExactQuantity().compare({n: '4', d: '3'}), 0)
    assert.equal(state.readonly_total, '4')
    state.clickDecrease()
    assert.equal(state.currentExactQuantity().compare({n: '1', d: '3'}), 0)
    state.clickDecrease()
    assert.equal(state.currentExactQuantity().compare('0.01'), 0)
    assert.equal(state.readonly_total, '0.03')
    state.restoreEditedQuantity()
    state.clickIncrease()
    await state.clickAddItem()
    assert.deepEqual(JSON.parse(JSON.stringify(emitted.quantity_input)), {amount: '1', unit_price: {n: '3', d: '4'}})
    assert.deepEqual(JSON.parse(JSON.stringify(emitted.calculation_snapshot.values.quantity)), {n: '4', d: '3'})
    assert.equal(emitted.total, '4')

    const requests = []
    state.applyValidateStock = () => true
    state.$http = {post: async (url, payload) => {
        assert.equal(url, '/validate-current-item-stock')
        requests.push(JSON.parse(JSON.stringify(payload)))
        return {data: {success: true}}
    }}
    state.recordItem = null
    state.setFocusSelectItem = () => {}
    state.form.quantity = '0'
    state.form.quantity_input = null
    state.form.calculation_snapshot = null
    await state.clickAddItem()
    assert.equal(requests[0].quantity, '0.01')
    assert.deepEqual(requests[0].quantity_exact, {n: '1', d: '100'})
    assert.equal(emitted.quantity, '0.01')

    state.form.item.calculate_quantity = true
    state.form.quantity = '0.001'
    state.form.quantity_input = {amount: '0.001', unit_price: '1'}
    await state.clickAddItem()
    assert.equal(requests[1].quantity, '0.001')
    assert.deepEqual(requests[1].quantity_exact, {n: '1', d: '1000'})
    assert.equal(emitted.quantity, '0.001')
})


test('reopening and editing large invoice quantities never converts decimal text to Number', () => {
    const component=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/item.vue','utf8'))
    const tree=parser.parse(component.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='methods').value.properties
    const pick=name=>generate(methods.find(n=>n.key?.name===name)).code
    const functions=vm.runInNewContext(`({${['restoreEditedQuantity','calculateTotal','currentExactQuantity','onQuantityChange','normalizeDecimal'].map(pick).join(',')}})`,{exactAmount,exactValue})
    const amount='9007199254740993.01'
    const state={...functions,recordItem:{id:7,quantity:amount,calculation_snapshot:{version:1,values:{quantity:exactAmount(amount).fraction()},inputs:{}}},
        form:{item:{calculate_quantity:true},quantity:0,unit_price_value:'1'}}
    state.restoreEditedQuantity()
    assert.equal(state.form.quantity,amount)
    assert.equal(state.currentExactQuantity().decimal(),amount)
    assert.equal(state.readonly_total,amount)
    state.onQuantityChange('9007199254740993,02')
    assert.equal(state.form.quantity,'9007199254740993.02')
    assert.equal(state.currentExactQuantity().decimal(),'9007199254740993.02')
    assert.equal(state.normalizeDecimal('12wrong'),'12wrong')
})

test('the delivery-order item editor sends an explicit finite override after a quantity change', () => {
    const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/item.vue', 'utf8'));
    const tree = parser.parse(component.script.content, {sourceType: 'module'});
    const methods = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties
        .find(node => node.key?.name === 'methods').value.properties;
    const selected = ['restoreEditedQuantity', 'calculateTotal', 'currentExactQuantity', 'onQuantityChange', 'normalizeDecimal'];
    const code = methods.filter(node => selected.includes(node.key?.name)).map(node => generate(node).code).join(',');
    const functions = vm.runInNewContext('({' + code + '})', {exactAmount, exactInput, exactValue});
    const decimal = '0.666666666667';
    const state = {
        ...functions,
        recordItem: {
            _from_delivery_order: true, quantity: decimal,
            calculation_snapshot: {version: 1, values: {quantity: {n: '2', d: '3'}}, inputs: {}},
        },
        form: {item: {calculate_quantity: false}, unit_price_value: '1'},
    };
    state.restoreEditedQuantity();
    assert.equal(state.currentExactQuantity().compare({n: '2', d: '3'}), 0);
    state.onQuantityChange('0.75');
    state.onQuantityChange(decimal);
    assert.equal(state.form.quantity_input.amount, decimal);
    assert.equal(state.form.quantity_input.unit_price, '1');
    assert.equal(state.currentExactQuantity().compare(decimal), 0);
    state.onQuantityChange('0.6666666666671');
    assert.match(state.errors.quantity[0], /12 decimales sin redondear/);
    assert.equal(state.form.quantity, decimal);
    assert.equal(state.form.quantity_input.amount, decimal);
});

test('reopening a fixed invoice discount preserves its original fraction and accepts an edited amount', async () => {
    const component=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/item.vue','utf8'))
    const tree=parser.parse(component.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='methods').value.properties
    const pick=name=>generate(methods.find(n=>n.key?.name===name)).code
    const functions=vm.runInNewContext(`({${['restoreEditedQuantity','calculateTotal','currentExactQuantity','normalizeDecimal','prepareDiscountsForEdit','clickAddItem','itemToDocumentConversion','currentPriceInputAmount'].map(pick).join(',')}})`,{
        exactAmount,exactValue,calculateRowItem,resolveSelectableAffectationType:()=>true,_:require('lodash')})
    let emitted
    const original={discount_type_id:'00',is_amount:true,amount:'0.333333333333',amount_without_rounded:'0.333333333333',amount_exact:{n:'1',d:'3'}}
    const state={...functions,recordItem:{id:42,indexi:0,quantity:'1',calculation_snapshot:{version:1,values:{quantity:{n:'1',d:'1'}},inputs:{}}},
        form:{item:{id:1,description:'Servicio ficticio',currency_type_id:'VES',unit_price:'3',lots:[],calculate_quantity:true},quantity:'1',unit_price_value:'3',has_igv:true,
        affectation_igv_type_id:'10',discounts:[],charges:[],attributes:[]},config:{},configuration:{},currencyTypeIdActive:'VES',exchangeRateSale:'1',percentageIgv:'0.16',
        operation_types:[{id:'0101',exportation:false}],operationTypeId:'0101',affectation_igv_types:[{id:'10'}],item_unit_type:{},isExportation:false,
        applyValidateStock:()=>false,validateQuantity(){this.calculateTotal()},validateTotalItem:()=>({}),initForm(){},close(){},$emit:(_,value)=>{emitted=value},$message:{error:message=>{throw new Error(message)}}}
    state.form.discounts=state.prepareDiscountsForEdit([original])
    const before=JSON.stringify(state.form.discounts)
    state.restoreEditedQuantity();await state.clickAddItem()
    assert.equal(emitted.discounts[0].amount_exact.n,'1');assert.equal(emitted.discounts[0].amount_exact.d,'3')
    assert.equal(JSON.stringify(state.form.discounts),before)
    await state.clickAddItem()
    assert.equal(emitted.discounts[0].amount_exact.n,'1');assert.equal(emitted.discounts[0].amount_exact.d,'3')
    state.form.discounts[0].amount='1.16';await state.clickAddItem()
    assert.equal(emitted.discounts[0].amount_exact.n,'1');assert.equal(emitted.discounts[0].amount_exact.d,'1')
    assert.equal(original.amount,'0.333333333333')
    assert.equal('_edit_fixed_gross' in emitted.discounts[0],false)
})

function priceEditor(row, catalogCurrency='VES', purchase='0') {
    const component=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/item.vue','utf8'))
    const tree=parser.parse(component.script.content,{sourceType:'module'})
    const methods=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='methods').value.properties
    const selected=['restoreEditedPrice','restoreEditedQuantity','calculateTotal','currentExactQuantity','normalizeDecimal','initForm','clickAddItem','itemToDocumentConversion',
        'create','setPresentationEditItem','selectedPrice','isSelectedPrice','getSelectedClass','calculateQuantity','prepareDiscountsForEdit','currentPriceInputAmount','markManualPrice','onManualPriceInput','selectManualPrice']
    const functions=vm.runInNewContext(`({${methods.filter(n=>selected.includes(n.key?.name)).map(n=>generate(n).code).join(',')}})`,{
        exactAmount,exactValue,calculateRowItem,resolveSelectableAffectationType:()=>true,_:require('lodash'),
        ...require('./support/exact-runtime.cjs')('invoice-lots')})
    const emitted=[]
    const state={...functions,recordItem:row,catalogItemCurrencyTypeId:catalogCurrency,
        form:{item:{id:1,description:'Servicio ficticio',currency_type_id:catalogCurrency,currency_type_symbol:catalogCurrency==='VES'?'Bs.':'$',purchase_unit_price:purchase,
            unit_price:'1',lots:[]},quantity:'1',unit_price_value:row.input_unit_price_value,has_igv:true,affectation_igv_type_id:'20',discounts:[],charges:[],attributes:[]},
        config:{condition_sale_purchase_price_to_item:true},configuration:{validate_purchase_sale_unit_price:true},currencyTypeIdActive:'VES',exchangeRateSale:'40',percentageIgv:'0.16',
        operation_types:[{id:'0101',exportation:false}],operationTypeId:'0101',affectation_igv_types:[{id:'20'}],item_unit_type:{},isExportation:false,
        applyValidateStock:()=>false,validateQuantity(){this.calculateTotal()},validateTotalItem:()=>({}),close(){},setFocusSelectItem(){},$emit:(_,value)=>emitted.push(value),
        $message:{error:message=>{throw new Error(message)}}}
    state.restoreEditedPrice();state.restoreEditedQuantity()
    return {state,emitted}
}

test('the invoice item editor rejects a serial count rounded to the exact quantity by Number', async () => {
    const quantity = '16384.000000000001';
    const serials = Array.from({length: 16384}, (_, index) => ({id: index + 1, has_sale: true}));
    const row = calculateRowItem({item: {id: 1, unit_price: '1', currency_type_id: 'VES'},
        quantity: '1', affectation_igv_type_id: '20'}, 'VES', '40', '0.16');
    for (const [isFromInvoice, selectedQuantity, expected] of [
        [true, quantity, false], [true, '16384', true], [false, quantity, true],
    ]) {
        const {state, emitted} = priceEditor(row);
        const errors = [];
        state.isFromInvoice = isFromInvoice;
        state.form.item.series_enabled = true;
        state.form.item.lots = serials;
        state.form.quantity = selectedQuantity;
        state.$message.error = message => errors.push(message);
        await state.clickAddItem();
        assert.equal(emitted.length, expected ? 1 : 0);
        assert.equal(errors.length, expected ? 0 : 1);
        if (!expected) assert.match(errors[0], /series seleccionadas/);
    }
});

test('reopening an inline price of one third retains the fraction and a changed price replaces it', async()=>{
    const row=calculateRowItem({item:{id:1,unit_price:'1',currency_type_id:'VES'},quantity:'3',price_input:{amount:{n:'1',d:'3'},currency_type_id:'VES',includes_iva:true},
        affectation_igv_type_id:'20'},'VES','40','0.16')
    const {state,emitted}=priceEditor(row)
    assert.equal(state.form.unit_price_value,'0.333333333333')
    await state.clickAddItem()
    assert.equal(emitted[0].total,'1');assert.equal(emitted[0].price_input.amount.d,'3')
    assert.equal(state.editedOriginalPriceInput,null);assert.equal(state.catalogItemCurrencyTypeId,null)
    state.recordItem=emitted[0];state.form={...emitted[0],unit_price_value:'0.333333333333',has_igv:true};state.restoreEditedPrice();state.restoreEditedQuantity()
    state.form.unit_price_value='0.5';await state.clickAddItem()
    assert.equal(emitted[1].total,'1.5');assert.equal(emitted[1].price_input.amount,'0.5')
})

test('an edited price that returns to its displayed value is sent as a manual decimal', async () => {
    const row = calculateRowItem({item:{id:1,unit_price:'0.333333333333',currency_type_id:'VES'}, quantity:'3',
        price_input:{amount:{n:'1',d:'3'},currency_type_id:'VES',includes_iva:true},affectation_igv_type_id:'20'},'VES','40','0.16')
    const untouched=priceEditor(row)
    untouched.state.isFromInvoice=true
    await untouched.state.clickAddItem()
    assert.equal(untouched.emitted[0].price_input.amount.d,'3')
    assert.notEqual(untouched.emitted[0].source_price_mode,'manual')
    const edited=priceEditor(row)
    edited.state.isFromInvoice=true
    edited.state.form.unit_price_value='0.4';edited.state.onManualPriceInput()
    edited.state.form.unit_price_value='0.333333333333';edited.state.onManualPriceInput()
    await edited.state.clickAddItem()
    assert.equal(edited.emitted[0].source_price_mode,'manual')
    assert.equal(edited.emitted[0].price_input.amount,'0.333333333333')
})

test('selecting an invoice price list sends the original price and derives the quantity from the entered amount', async () => {
    const row = calculateRowItem({item: {id:1, unit_price:'1', currency_type_id:'VES'}, quantity:'1',
        affectation_igv_type_id:'20'}, 'VES', '40', '0.16');
    const {state, emitted} = priceEditor(row);
    state.recordItem = null;
    state.isFromInvoice = true;
    state.form.item.calculate_quantity = true;
    state.total_item = '1';
    const presentation = {id:7, unit_type_id:'UND', quantity_unit:'1'};
    const price = {id:9, price:'0.33', calculation_snapshot: {version:1, values: {price:{n:'1',d:'3'}}}};
    state.selectManualPrice(presentation, price);
    assert.equal(state.form.unit_price_value, '0.333333333333');
    assert.equal(state.form.quantity, '3');
    assert.equal(state.readonly_total, '1');
    assert.equal(state.form.quantity_input.unit_price.d, '3');
    await state.clickAddItem();
    assert.equal(emitted[0].total, '1');
    assert.equal(emitted[0].price_input.amount.d, '3');
    assert.equal(emitted[0].source_price_mode, 'manual');
    assert.equal(emitted[0].quantity_input.unit_price.d, '3');

    const legacy = priceEditor(row);
    legacy.state.recordItem = null;
    legacy.state.selectedPrice(presentation, price);
    assert.equal(legacy.state.form.unit_price_value, '0.33');
    await legacy.state.clickAddItem();
    assert.equal(legacy.emitted[0].total, '0.33');
});

test('billing accepts a positive list price below the decimal projection when the quantity preserves its value', async () => {
    const row = calculateRowItem({item: {id:1, unit_price:'1', currency_type_id:'VES'}, quantity:'1',
        affectation_igv_type_id:'20'}, 'VES', '40', '0.16');
    const {state, emitted} = priceEditor(row);
    state.recordItem = null;
    state.isFromInvoice = true;
    state.form.quantity = '3000000000000';
    const price = {id:9, price:'0.00', calculation_snapshot: {version:1, values: {price:{n:'1',d:'3000000000000'}}}};
    state.selectedPrice({id:7, unit_type_id:'UND', quantity_unit:'1'}, price);
    assert.equal(state.form.unit_price_value, '0');
    await state.clickAddItem();
    assert.equal(emitted[0].total, '1');
    assert.equal(emitted[0].calculation_snapshot.values.unit_price.d, '3000000000000');
});

test('inline to modal to inline preserves price currency, original cost and cost validation',async()=>{
    const row=calculateRowItem({item:{id:1,unit_price:'11',currency_type_id:'USD',purchase_unit_price:'10'},quantity:'1',
        price_input:{amount:'440',currency_type_id:'VES',includes_iva:true},affectation_igv_type_id:'20'},'VES','40','0.16')
    const {state,emitted}=priceEditor(row,'USD','10')
    assert.equal(state.form.item.currency_type_symbol,'Bs.')
    state.form.unit_price_value='100';await assert.rejects(()=>state.clickAddItem(),/precio de compra/)
    state.form.unit_price_value='440';await state.clickAddItem()
    assert.equal(emitted[0].total,'440');assert.equal(emitted[0].item.currency_type_id,'USD');assert.equal(emitted[0].item.purchase_unit_price,'10')
    const {recalculateInvoiceRow}=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/invoice-row-edit.js'))
    emitted[0].unit_price='20'
    assert.throws(()=>recalculateInvoiceRow(emitted[0],'unit_price',{currency_type_id:'VES',exchange_rate_sale:'40',percentage_igv:'0.16'}),/precio de compra/)
})

test('actual invoice modal creation restores a presentation without replacing its saved fractional price',async()=>{
    for(const [currency,quantity,price,expected] of [['VES','3',{n:'1',d:'3'},'1'],['USD','3',{n:'1',d:'3'},'40'],
        ['USD','0.333333333333','3','40']]) {
        const input={item:{id:1,description:'Producto ficticio',barcode:'BILLING-PRESENTATION',unit_price:'5',currency_type_id:currency,lots:[],
            presentation:{id:7,unit_type_id:'UND',price_default:1,price1:'5',quantity_unit:'3'}},
            item_id:1,quantity,price_input:{amount:price,currency_type_id:currency,includes_iva:true},
            affectation_igv_type_id:'20',attributes:[],discounts:[],charges:[]}
        if(quantity==='0.333333333333')input.quantity_input={amount:'1',unit_price:'3'}
        const row=calculateRowItem(input,'VES','40','0.16'),{state,emitted}=priceEditor(row,currency)
        const presentation=JSON.stringify(row.item.presentation)
        Object.assign(state,{isFromInvoice:true,isOpenFromInvoice:true,isEditItemNote:false,
            all_affectation_igv_types:[{id:'20',exportation:false}],reloadDataItems:async()=>{},
            async changeItem(){this.form.unit_price_value='5';this.form.item.currency_type_id=currency},
            $refs:{selectSearchNormal:{$el:{getElementsByTagName:()=>[{focus(){}}]}}}})
        await state.create();assert.equal(state.form.item_unit_type_id,7)
        assert.equal(JSON.stringify(state.item_unit_type),presentation)
        await state.clickAddItem();assert.equal(emitted[0].total,expected)
        assert.equal(emitted[0].price_input.currency_type_id,currency)
        assert.equal(JSON.stringify(row.item.presentation),presentation)
        if(typeof price==='object')assert.equal(emitted[0].price_input.amount.d,'3')
        if(input.quantity_input)assert.equal(emitted[0].calculation_snapshot.values.quantity.d,'3')
    }
})

test('presentation hydration keeps the previous catalog-selection behavior outside billing',()=>{
    const {state}=priceEditor({item:{presentation:{id:7}},quantity:'1',price_input:{amount:'1',currency_type_id:'VES',includes_iva:true}})
    let selected=0;state.selectedPrice=()=>selected++;state.getSelectedClass=()=>''
    state.setPresentationEditItem();assert.equal(selected,1)
})

test('invoice line editor restores fixed discounts in the price currency without modifying stored amounts', async () => {
    const scenarios = [
        {documentCurrency:'VES', priceCurrency:'USD', price:'3', affectation:'10',
            display:['0.009666666667','0.003571428571'], edits:['0.058','0.025'], edited:['2','1'], total:'116.68'},
        {documentCurrency:'USD', priceCurrency:'VES', price:'120', affectation:'20',
            display:['13.333333333333','5.714285714286'], edits:['20','10'], edited:['1/2','1/4'], total:'2.25'},
    ];
    for (const scenario of scenarios) {
        const {documentCurrency,priceCurrency,price,affectation} = scenario;
        const row = calculateRowItem({
            item:{id:1,description:'Producto ficticio',barcode:'BILLING-FIXED-DISCOUNTS',unit_price:price,
                currency_type_id:priceCurrency,lots:[],original_affectation_igv_type_id:affectation},
            quantity:'1',price_input:{amount:price,currency_type_id:priceCurrency,includes_iva:true},
            affectation_igv_type_id:affectation,attributes:[],charges:[],
            discounts:[{discount_type_id:'00',is_amount:true,amount_exact:{n:'1',d:'3'}},
                {discount_type_id:'01',is_amount:true,amount_exact:{n:'1',d:'7'}}],
        },documentCurrency,'40','0.16');
        const {state,emitted} = priceEditor(row,priceCurrency);
        const saved = JSON.stringify(row.discounts);
        const fractions = discounts => Array.from(discounts, ({amount_exact: {n,d}}) => ({n,d}));
        Object.assign(state,{
            currencyTypeIdActive:documentCurrency,isFromInvoice:true,isOpenFromInvoice:true,isEditItemNote:false,
            all_affectation_igv_types:[{id:affectation,exportation:false}],reloadDataItems:async()=>{},
            async changeItem(){
                this.form.item.currency_type_id = priceCurrency;
                this.form.affectation_igv_type_id = affectation;
            },
            $refs:{selectSearchNormal:{$el:{getElementsByTagName:()=>[{focus(){}}]}}},
        });
        await state.create();
        const inputs = Array.from(state.form.discounts, discount => discount.amount);
        await state.clickAddItem();
        assert.deepEqual(fractions(emitted[0].discounts),
            [{n:'1',d:'3'},{n:'1',d:'7'}],priceCurrency+' → '+documentCurrency);
        assert.equal(emitted[0].total,row.total);
        assert.deepEqual(inputs,scenario.display);
        assert.equal(JSON.stringify(row.discounts),saved);
        assert.ok(emitted[0].discounts.every(discount => !('_edit_fixed_gross' in discount)));

        state.recordItem = emitted[0];
        state.form.item = {...state.recordItem.item};
        await state.create();
        state.form.discounts.forEach((discount,index) => { discount.amount = scenario.edits[index]; });
        await state.clickAddItem();
        assert.deepEqual(fractions(emitted[1].discounts),
            scenario.edited.map(value => {
                const [n,d='1'] = value.split('/');
                return {n,d};
            }));
        assert.equal(emitted[1].total,scenario.total);
    }
});

test('invoice variation stock totals and colors use exact decimal amounts', () => {
    const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/item.vue', 'utf8'))
    const tree = parser.parse(component.script.content, {sourceType: 'module'})
    const methods = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration')
        .declaration.properties.find(node => node.key?.name === 'methods').value.properties
    const names = ['variationsTotalStock', 'variationGroups', 'formatVariationStock', 'variationStockClass']
    const code = names.map(name => generate(methods.find(node => node.key?.name === name)).code)
    const selector = vm.runInNewContext('({' + code.join(',') + '})', {exactAmount})
    const color = {value: 'Rojo', color: '#cc2222'}
    const option = {variations: [
        {stock: '0.1', variation_attributes: [color]},
        {stock: '0.2', variation_attributes: [color]},
        {stock: '9007199254740993.02', variation_attributes: []},
    ]}

    const groups = selector.variationGroups(option)
    assert.equal(groups[0].stock.decimal?.() ?? String(groups[0].stock), '0.3')
    assert.equal(selector.formatVariationStock(groups[0].stock), '0.3')
    assert.equal(String(selector.variationsTotalStock(option)), '9007199254740993.32')
    assert.equal(selector.formatVariationStock('9007199254740993.125'), '9007199254740993.13')
    assert.equal(selector.variationStockClass({stock: '9007199254740993.01', stock_min: '9007199254740993'}), 'text-success')
    assert.equal(selector.variationStockClass({stock: '0.1', stock_min: '0.1'}), 'text-warning')
    assert.equal(selector.variationStockClass({stock: '0'}), 'text-danger')
    assert.equal(String(selector.variationsTotalStock({})), '0')
    assert.deepEqual(compiler.compile(component.template.content).errors, [])
})

test('regularizing a credit note in VES removes every unavailable serial and leaves the original on HTTP failure', async () => {
    const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/item.vue', 'utf8'));
    const tree = parser.parse(component.script.content, {sourceType: 'module'});
    const props = tree.program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration.properties;
    const methods = props.find(node => node.key?.name === 'methods').value.properties;
    const method = generate(methods.find(node => node.key?.name === 'regularizeLots')).code;
    const {serialIdKey}=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/invoice-lots.js'));
    const {regularizeLots} = vm.runInNewContext(`({${method}})`, {serialIdKey});
    const selected = [{id:'9007199254740992'}, {id:'9007199254740993'}, {id:17}];

    for (const [available, expected] of [
        [[], []],
        [[{id:'9007199254740993'}, {id:'17'}], [selected[1], selected[2]]],
        [[{id:Number('9007199254740993')}, {id:'17'}], [selected[2]]],
        [null, selected],
    ]) {
        const lots = selected.slice();
        const state = {
            resource:'documents', form:{document_item_id:'81', item:{lots}},
            $http:{get:async url => {
                assert.equal(url, '/documents/regularize-lots/81');
                if (available === null) throw new Error('Consulta no disponible');
                return {data:available};
            }},
        };
        await regularizeLots.call(state);
        assert.equal(state.form.item.lots, lots);
        assert.deepEqual(lots, expected);
    }
})
