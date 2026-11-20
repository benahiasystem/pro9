const test=require('node:test'),assert=require('node:assert/strict')
const {context,methods,math,pos,calculateRowItem}=require('./support/pos-invoice-runtime.cjs')
const localQuantity = require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/mixins/pos-local-quantity.js')).default

test('POS rápido checks the exact invoice increment at the stock boundary before updating the cart', async () => {
    const ctx = {...context('fast'), ...methods('fast', ['getStatusStock']), resource: 'pos'}
    const item = {id: 1, item_id: 1, unit_type_id: 'KGM', sale_unit_price: '1',
        currency_type_id: 'VES', has_igv: true, sale_affectation_igv_type_id: '20'}
    const row = calculateRowItem({
        item_id: 1, unit_type_id: 'KGM', quantity: '0.333333333333',
        quantity_input: {amount: '1', unit_price: '3'}, affectation_igv_type_id: '20',
        item: {...item, unit_price: '1'},
    }, 'VES', '12.5', '0.16')
    ctx.form.items = [row]
    ctx.items = [item]
    let stock = '1.333333333333'
    const requests = []
    ctx.$http = {get: async (url, {params}) => {
        const quantity = JSON.parse(params.quantity_exact)
        requests.push({url, quantity})
        return {data: {success: math.exactAmount(quantity).compare(stock) <= 0, message: 'Sin existencias'}}
    }}
    await ctx.clickAddItem(item, 0)
    assert.deepEqual(requests[0], {url: '/pos/validate_stock/1/1.333333333333', quantity: {n: '4', d: '3'}})
    assert.deepEqual(ctx.errors, ['Sin existencias'])
    assert.equal(math.exactValue(ctx.form.items[0], 'quantity').compare({n: '1', d: '3'}), 0)
    stock = {n: '4', d: '3'}
    await ctx.clickAddItem(item, 0)
    assert.deepEqual(requests[1], requests[0])
    assert.equal(math.exactValue(ctx.form.items[0], 'quantity').compare(stock), 0)
})

test('POS and Garage invoice quantity buttons check stock with the same rational quantity they save', () => {
    const cases = [
        {file: 'index', action: 'changeCartQuantity', delta: 1, amount: '4', expected: {n: '7', d: '3'}},
        {file: 'garage', action: 'decrementItem', delta: -1, amount: '4', expected: {n: '1', d: '3'}},
        {file: 'garage', action: 'decrementItem', delta: -1, amount: '3000000000001', divisor: '3000000000000',
            expected: {n: '1', d: '3000000000000'}, submitted: {amount: '0.000000000001', unit_price: {n: '3', d: '1'}}},
        {file: 'index', action: 'changeCartQuantity', delta: 1, amount: '999999999999999998', expected: {n: '1000000000000000001', d: '3'}},
    ]
    for (const {file, action, delta, amount, divisor = '3', expected, submitted} of cases) {
        const state = {...context(file), ...localQuantity.methods, ...methods(file, [action])}
        const input = {amount, unit_price: divisor}
        const quantity = math.exactAmount(amount).dividedBy(divisor).decimal()
        const row = calculateRowItem({
            item_id: 1, unit_type_id: 'KGM', quantity, quantity_input: input, affectation_igv_type_id: '20', discounts: [], charges: [],
            item: {id: 1, currency_type_id: 'VES', unit_price: '3', has_igv: true, aux_quantity: quantity},
        }, 'VES', '12.5', '0.16')
        state.form.items = [row]
        const checked = []
        state.validatePosStockChange = (_, next) => {
            checked.push(math.exactValue(next, 'quantity').fraction())
            return true
        }
        state[action](row, 0, delta)
        const saved = state.form.items[0]
        assert.equal(math.exactValue(saved, 'quantity').compare(expected), 0)
        assert.deepEqual(JSON.parse(JSON.stringify(checked)), [expected])
        assert.deepEqual(JSON.parse(JSON.stringify(saved.quantity_input)),
            submitted ?? {amount: '1', unit_price: {n: expected.d, d: expected.n}})
        state.currentPosStockChecks = [{row: saved, error: 'Reintente'}]
        state.retryPosStock()
        assert.equal(math.exactValue(state.form.items[0], 'quantity').compare(expected), 0)
        assert.deepEqual(JSON.parse(JSON.stringify(checked)), [expected, expected])
    }
})

test('POS invoice seller authorization uses the exact configured discount boundary',()=>{
    const validate=methods('partials/payment',['getDiscountPercentages','validateRestrictSellerDiscount'])
    const ctx={...validate,form:{document_type_id:'01'},configuration:{restrict_seller_discount:true,sellers_discount_limit:7},typeUser:'seller'}
    for (const [factors, permitted] of [
        [['0.07'],true],
        [['0.01','0.06'],true],
        [['0.069999999999'],true],
        [['0.070000000001'],false],
        [['0.07234'],false],
    ]) {
        ctx.form.discounts=factors.map(factor=>({factor}))
        ctx.showDialogDiscountPermission=false
        assert.equal(ctx.validateRestrictSellerDiscount().success,permitted)
        assert.equal(ctx.showDialogDiscountPermission,!permitted)
    }
    assert.equal(String(ctx.totalDiscountPercentage),'7.23')
    ctx.form.token_validated_for_discount=true
    assert.equal(ctx.validateRestrictSellerDiscount().success,true)
    ctx.form.token_validated_for_discount=false
    ctx.typeUser='admin'
    assert.equal(ctx.validateRestrictSellerDiscount().success,true)
})

test('POS sales note retains the existing seller discount behavior',()=>{
    const validate=methods('partials/payment',['getDiscountPercentages','validateRestrictSellerDiscount'])
    const ctx={...validate,form:{document_type_id:'80',discounts:[{factor:0.08}]},
        configuration:{restrict_seller_discount:true,sellers_discount_limit:7},typeUser:'seller'}
    assert.equal(ctx.validateRestrictSellerDiscount().success,false)
    assert.equal(ctx.totalDiscountPercentage,8)
})

test('POS uses the unrounded invoice discount rate calculated at checkout', () => {
    const {checkoutTotals} = require('./helpers/load-module.cjs')(
        require('node:path').resolve('resources/js/helpers/exact-commercial.js')
    )
    const validate = methods('partials/payment', ['getDiscountPercentages', 'validateRestrictSellerDiscount'])
    const row = calculateRowItem({
        item: {id: 1, unit_price: '3', currency_type_id: 'VES'},
        quantity: '1', affectation_igv_type_id: '20',
    }, 'VES', '40', '0.16')
    const ctx = {
        ...validate,
        form: {document_type_id: '01', items: [row]},
        configuration: {restrict_seller_discount: true, sellers_discount_limit: '7', global_discount_type_id: '03'},
        typeUser: 'seller',
        enabled_discount: true,
        is_discount_amount: true,
    }

    for (const [amount, permitted] of [['0.209999999999', true], ['0.21', true], ['0.210000000001', false]]) {
        ctx.discount_amount = amount
        checkoutTotals(ctx)
        ctx.form = JSON.parse(JSON.stringify(ctx.form))
        ctx.showDialogDiscountPermission = false
        assert.equal(ctx.form.discounts[0].factor, '0.07')
        assert.equal(ctx.validateRestrictSellerDiscount().success, permitted, amount)
        assert.equal(ctx.showDialogDiscountPermission, !permitted)
    }
})

for(const file of ['index','garage','fast']) {
    test(file+': a real POS click calculates IVA from the original net price and currency',async()=>{
        for(const currency of ['VES','USD']) for(const affectation of ['10','20']) {
            const ctx=context(file),item={id:1,item_id:1,unit_type_id:'SERV',sale_unit_price:'0.1',currency_type_id:currency,has_igv:false,sale_affectation_igv_type_id:affectation}
            ctx.items=[item];await ctx.clickAddItem(item,0)
            const row=ctx.form.items[0],expected=math.exactAmount(affectation==='10'?'0.116':'0.1').times(currency==='USD'?'12.5':'1')
            assert.equal(math.exactValue(row,'unit_price').compare(expected),0);assert.equal(math.exactValue(row,'total').compare(expected),0)
            assert.equal(row.price_input.amount,'0.1');assert.equal(row.price_input.currency_type_id,currency);assert.equal(ctx.errors.length,0)
        }
    })
    test(file+': repeated clicks conserve large decimal quantities and one third snapshots',async()=>{
        for(const quantity of ['9007199254740993.000000000001','0.333333333333']) {
            const ctx=context(file),item={id:1,item_id:1,unit_type_id:'SERV',sale_unit_price:'1',currency_type_id:'VES',has_igv:true,sale_affectation_igv_type_id:'20'}
            const initial={item_id:1,unit_type_id:'SERV',item:{...item,unit_price:'1',aux_quantity:quantity},quantity,affectation_igv_type_id:'20',price_input:{amount:'1',currency_type_id:'VES',includes_iva:true}}
            if(quantity.startsWith('0.')) initial.quantity_input={amount:'1',unit_price:'3'}
            const row=calculateRowItem(initial,'VES','12.5','0.16');ctx.form.items=[row];ctx.items=[item]
            const expected=math.exactValue(row,'quantity').plus('1');
            if (file !== 'fast' && initial.quantity_input) ctx.incrementPosLine(row)
            else await ctx.clickAddItem(item,0)
            if (file === 'fast') assert.equal(ctx.checkedQuantity,expected.decimal());
            assert.equal(math.exactValue(ctx.form.items[0],'quantity').compare(expected),0)
            assert.equal(math.exactValue(ctx.form.items[0],'total').compare(expected),0);assert.equal(ctx.form.items[0].price_input.amount,'1')
        }
    })
}
test('POS selection and display preserve raw price precision without overwriting it with cents',async()=>{
    const ctx=context('index'),large='9007199254740993.000000000001'
    const row={sale_unit_price:'1.005',unit_type_id:'SERV',item_unit_types:[{id:7,unit_type_id:'SERV',quantity_unit:'1',prices:[{price_label_id:2,price:large}]}]}
    ctx.items=[row];ctx.selected_option_price='price_label_2';await ctx.ChangeSelectedPrice()
    assert.equal(row.sale_unit_price,large);assert.equal(row.original_sale_unit_price,'1.005')
    assert.equal(ctx.itemSetSaleUnitPrice(row),'9007199254740993.00');assert.equal(row.sale_unit_price,large)
    ctx.selected_option_price=1;await ctx.ChangeSelectedPrice();assert.equal(row.sale_unit_price,'1.005');assert.equal(ctx.itemSetSaleUnitPrice(row),'1.01')
    assert.equal(ctx.money(large),'9007199254740993.00')
    assert.equal(ctx.rowUnitPrice({unit_price:large}),'9007199254740993.00')
    assert.equal(ctx.rowTotal({total:'1.005',calculation_snapshot:{values:{total:{n:'100499999999999',d:'100000000000000'}}}}),'1.00')
})

for (const file of ['index','garage','fast']) {
    test(file+': opening and confirming a VES invoice price keeps the selected warehouse fraction', async () => {
        const ctx = context(file);
        const item = {id:1, item_id:1, unit_type_id:'UND', currency_type_id:'VES', has_igv:true,
            sale_affectation_igv_type_id:'20', sale_unit_price:'0.666666666667', edit_sale_unit_price:'0.333333333333',
            calculation_snapshot:{version:1,values:{sale_unit_price:{n:'2',d:'3'}}}};
        ctx.items = [item];

        ctx.clickOpenInputEditUP(0);
        ctx.clickEditUnitPriceItem(0);
        assert.equal(item.edit_unit_price, false);
        assert.equal(pos.posPriceAmount(item).compare({n:'2',d:'3'}), 0);

        ctx.clickOpenInputEditUP(0);
        ctx.updateUnitPriceDraft(item, '4');
        ctx.clickCancelUnitPriceItem(0);
        ctx.clickOpenInputEditUP(0);
        ctx.clickEditUnitPriceItem(0);
        assert.equal(pos.posPriceAmount(item).compare({n:'2',d:'3'}), 0);

        for (let count=0; count<3; count++) await ctx.clickAddItem(item, 0);
        assert.equal(math.exactValue(ctx.form.items[0], 'total').compare('2'), 0);
        assert.equal(ctx.errors.length, 0);

        ctx.form.document_type_id = '80';
        ctx.clickOpenInputEditUP(0);
        assert.equal(item.edit_sale_unit_price, '0.333333333333');
        ctx.updateUnitPriceDraft(item, '3');
        ctx.clickEditUnitPriceItem(0);
        assert.equal(pos.posPriceAmount(item).compare('3'), 0);
        ctx.form.document_type_id = '01';
        ctx.clickOpenInputEditUP(0);
        ctx.clickEditUnitPriceItem(0);
        assert.equal(pos.posPriceAmount(item).compare('3'), 0);
    })
    test(file+': catalog thirds survive selection; typing the same decimal creates a distinct finite price',async()=>{
        const ctx=context(file),item={id:'9007199254740993',item_id:'9007199254740993',unit_type_id:'SERV',sale_unit_price:'0.333333333333',
            currency_type_id:'VES',has_igv:true,sale_affectation_igv_type_id:'20',purchase_unit_price:'0',
            calculation_snapshot:{version:1,values:{sale_unit_price:{n:'1',d:'3'}}}}
        ctx.items=[item]
        for (let n=0;n<3;n++) await ctx.clickAddItem(item,0)
        assert.equal(ctx.form.items.length,1)
        assert.equal(math.exactValue(ctx.form.items[0],'total').compare('1'),0)
        const original=ctx.form.items[0]
        ctx.clickOpenInputEditUP(0);ctx.updateUnitPriceDraft(item,'0.333333333333');ctx.clickEditUnitPriceItem(0)
        for (let n=0;n<3;n++) await ctx.clickAddItem(item,0)
        assert.equal(ctx.form.items.length,2,'a finite manual price must not merge into the catalog fraction')
        const manual=ctx.form.items.find(row=>row!==original)
        assert.equal(math.exactValue(manual,'total').compare('0.999999999999'),0)
        assert.equal(math.exactValue(original,'total').compare('1'),0)
        assert.equal(ctx.errors.length,0)
    })
    test(file+': presentation selection retains parent identity and the selected original price',async()=>{
        for (const option of [1,2,3]) {
            const ctx=context(file),item={id:3,item_id:3,unit_type_id:'UND',sale_unit_price:'5',currency_type_id:'VES',has_igv:true,sale_affectation_igv_type_id:'20'}
            const parent={id:7,item_id:3,unit_type_id:'UND',quantity_unit:'6',price_default:option,
                price1:'0.333333333333',price2:'0.666666666667',price3:'9007199254740993.01',
                calculation_snapshot:{version:1,values:{quantity_unit:{n:'6',d:'1'},price1:{n:'1',d:'3'},price2:{n:'2',d:'3'},price3:{n:'900719925474099301',d:'100'}}}}
            const quote={id:19,item_unit_type_id:7,price:'0.333333333333',unit_type_id:'UND',quantity_unit:'6',calculation_snapshot:{version:1,values:{price:{n:'1',d:'3'}}}}
            item.item_unit_types=[parent];ctx.items=[item]
            item.pos_stock={control:true,components:[{item_id:item.id,description:'Presentación',stock:'18',quantity:'1'}]}
            ctx.setPriceItem(file==='fast'?parent:quote,0)
            assert.equal(item.presentation.id,7)
            assert.equal(pos.posPresentationQuantity(item).compare('6'),0)
            for(let n=0;n<3;n++)await ctx.clickAddItem(item,0)
            if (file === 'fast') assert.equal(ctx.checkedQuantity,'18')
            else assert.equal(ctx.posStockDemand(ctx.form.items).get(String(item.id)).decimal(),'18')
            const expected=file==='fast'?math.exactAmount(parent.calculation_snapshot.values['price'+option]).times('3'):math.exactAmount('1')
            assert.equal(math.exactValue(ctx.form.items[0],'total').compare(expected),0)
            assert.equal(ctx.errors.length,0)
        }
    })
    test(file+': editing a large price compares the original purchase cost without binary arithmetic',()=>{
        const ctx=context(file);ctx.config.condition_sale_purchase_price_to_item=true
        const item={sale_unit_price:'9007199254740993.01',purchase_unit_price:'9007199254740993.01',edit_sale_unit_price:'9007199254740993.00'}
        ctx.items=[item];ctx.clickOpenInputEditUP(0);ctx.updateUnitPriceDraft(item,'9007199254740993.00');ctx.clickEditUnitPriceItem(0)
        assert.equal(item.sale_unit_price,'9007199254740993.01');assert.equal(ctx.errors.length,1)
        ctx.updateUnitPriceDraft(item,'9007199254740993.02');ctx.clickEditUnitPriceItem(0)
        assert.equal(pos.posPriceAmount(item).compare('9007199254740993.02'),0)
    })
}

test('global price lists replace their own fractional source A → B → A → original',async()=>{
    const ctx=context('index'),item={sale_unit_price:'1.005',unit_type_id:'UND',has_igv:true,currency_type_id:'VES',
        item_unit_types:[{id:7,quantity_unit:'1',unit_type_id:'UND',prices:[
            {price_label_id:'9007199254740992',price:'0.333333333333',calculation_snapshot:{version:1,values:{price:{n:'1',d:'3'}}}},
            {price_label_id:'9007199254740993',price:'0.666666666667',calculation_snapshot:{version:1,values:{price:{n:'2',d:'3'}}}}
        ]}]}
    ctx.items=[item]
    for (const [id,numerator] of [['9007199254740992','1'],['9007199254740993','2'],['9007199254740992','1']]) {
        ctx.selected_option_price='price_label_'+id;await ctx.ChangeSelectedPrice()
        assert.equal(pos.posPriceAmount(item).compare({n:numerator,d:'3'}),0)
        assert.equal(item.presentation.id,7)
    }
    ctx.selected_option_price=1;await ctx.ChangeSelectedPrice()
    assert.equal(item.sale_unit_price,'1.005');assert.equal(item.presentation,null);assert.equal(item.unit_type_id,'UND')
})

test('table price chips send the parent presentation and preserve quantity and price fractions',()=>{
    const api=methods('partials/table',['priceOptions','pickPrice']),emitted=[]
    const parent={id:7,item_id:3,unit_type_id:'UND',quantity_unit:'0.333333333333',calculation_snapshot:{version:1,values:{quantity_unit:{n:'1',d:'3'}}},
        prices:[{id:19,item_unit_type_id:7,price:'0.333333333333',price_label_id:2,calculation_snapshot:{version:1,values:{price:{n:'1',d:'3'}}}}]}
    const item={item_id:3,unit_type_id:'UND',sale_unit_price:'2',item_unit_types:[parent]}
    const ctx={...api,records:[item],$set:(o,k,v)=>o[k]=v,$emit:(...args)=>emitted.push(args)}
    const option=ctx.priceOptions(item)[0];ctx.pickPrice(option,0)
    assert.equal(emitted.length,1);assert.equal(item.presentation.id,7)
    assert.equal(pos.posPriceAmount(item).times('3').compare('1'),0)
    assert.equal(pos.posPresentationQuantity(item).times('3').compare('1'),0)
})

test('the POS table and variation selector print decimal prices from their original catalog values', () => {
    const table = methods('partials/table',['formatPrice','priceOptions'])
    const variations = methods('partials/variations_modal',['formatPrice','selectVariation','close'])
    const halfCent = {sale_unit_price:'1.005',stock:'1'}
    const large = {sale_unit_price:'9007199254740993.01',stock:'1'}
    const recent = {sale_unit_price:'0.335',calculation_snapshot:{version:1,
        values:{sale_unit_price:{n:'3349999999999',d:'10000000000000'}}},stock:'1'}
    const selected = []
    const gallery={...variations,$emit:(event,row)=>{if(event==='select') selected.push(row)}}

    for (const [item, expected] of [[halfCent,'1.01'],[large,'9007199254740993.01'],[recent,'0.33']]) {
        assert.equal(table.formatPrice.call({config:{decimal_quantity:2}},item),expected)
        assert.equal(gallery.formatPrice(item),expected)
        gallery.selectVariation(item)
    }
    assert.deepEqual(selected,[halfCent,large,recent])
    assert.equal(gallery.formatPrice(null),'0.00')
    assert.equal(table.formatPrice.call({config:{decimal_quantity:4}},halfCent),'1.0050')
    assert.equal(table.formatPrice.call({config:{decimal_quantity:12}},large),'9007199254740993.010000000000')
    assert.equal(table.formatPrice.call({config:{}},large),'9007199254740993.01')

    const quote={price:'0.335',calculation_snapshot:{version:1,values:{price:recent.calculation_snapshot.values.sale_unit_price}}}
    const option=table.priceOptions({item_unit_types:[{id:1,prices:[quote]}]})[0]
    assert.equal(option.ref,quote)
    assert.equal(table.formatPrice.call({config:{decimal_quantity:2}},option.ref,'price'),'0.33')
})

test('minimum price uses the original fraction at the rounded boundary',()=>{
    assert.equal(pos.isPosPriceAllowed({sale_unit_price:'0.1',calculation_snapshot:{version:1,values:{sale_unit_price:{n:'999999999999',d:'10000000000000'}}}}),false)
    assert.equal(pos.isPosPriceAllowed({sale_unit_price:'0.1',calculation_snapshot:{version:1,values:{sale_unit_price:{n:'1000000000001',d:'10000000000000'}}}}),true)
})

test('the POS invoice price menu distinguishes a presentation just above one physical unit', () => {
    const {priceOptions} = methods('partials/table', ['priceOptions'])
    const fraction = {n: '3000000000001', d: '3000000000000'}
    const unit = {id: 7, unit_type_id: 'UND', quantity_unit: '1', prices: [{price: '1', quantity_unit: '1'}]}
    const original = {...unit, calculation_snapshot: {version: 1, values: {quantity_unit: fraction}}}
    const option = priceOptions({item_unit_types: [original]})[0]
    assert.equal(option.quantity_label, '3000000000001/3000000000000')
    assert.equal(pos.posPresentationQuantity({presentation: option.presentation}).compare(fraction), 0)
    assert.equal(priceOptions({item_unit_types: [unit]})[0].quantity_label, null)
    assert.equal(priceOptions({item_unit_types: [{...unit, quantity_unit: '2.5'}]})[0].quantity_label, '2.5')
})

test('stock checks include other prices and exact presentation factors of the same product',()=>{
    const item={item_id:'9007199254740993',presentation:{id:7,quantity_unit:'0.333333333333',calculation_snapshot:{version:1,values:{quantity_unit:{n:'1',d:'3'}}}}}
    const row={item_id:item.item_id,item,quantity:'3'}
    assert.equal(math.exactAmount(pos.posRequiredStock([row],item,'3',row)).compare('1'),0)
    assert.equal(math.exactAmount(pos.posRequiredStock([row],{...item,presentation:{id:8,quantity_unit:'6'}},'2')).compare('13'),0)
    assert.equal(math.exactAmount(pos.posRequiredStock([row],{item_id:'9007199254740992'},'2')).compare('2'),0)
})

test('a manually edited POS price takes precedence over a prior list and compares cost exactly',()=>{
    const ctx=context('index');ctx.config.condition_sale_purchase_price_to_item=true
    ctx.selected_option_price='price_label_2';ctx.items=[{sale_unit_price:'20',original_sale_unit_price:'20',purchase_unit_price:'20',edit_sale_unit_price:'100',
        item_unit_types:[{prices:[{price_label_id:2,price:'30'}]}]}]
    ctx.clickOpenInputEditUP(0);ctx.updateUnitPriceDraft(ctx.items[0],'100');ctx.clickEditUnitPriceItem(0);assert.equal(ctx.items[0].sale_unit_price,'100');assert.equal(ctx.itemSetSaleUnitPrice(ctx.items[0]),'100.00')
    ctx.clickOpenInputEditUP(0);ctx.updateUnitPriceDraft(ctx.items[0],'19.999999999999');ctx.clickEditUnitPriceItem(0)
    assert.equal(ctx.items[0].sale_unit_price,'100');assert.equal(ctx.errors.length,1)
})
