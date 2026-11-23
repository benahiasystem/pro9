const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const load = require('./helpers/load-module.cjs')
const {calculateRowItem,canMergeRowQuantity,canMergeRowPrice} = load(path.resolve('resources/js/helpers/functions.js'))
const {exactAmount} = load(path.resolve('resources/js/helpers/exchange-rate-math.js'))

for (const [file, invoice] of [['resources/js/views/tenant/sale_notes/form.vue',false],
    ['resources/js/views/tenant/documents/invoice_generate.vue',true]]) {
    const component=compiler.parseComponent(fs.readFileSync(file,'utf8'))
    const ast=parser.parse(component.script.content,{sourceType:'module'})
    const methods=ast.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration.properties.find(node=>node.key?.name==='methods').value
    const get=name=>vm.runInNewContext(`({${generate(methods.properties.find(node=>node.key?.name===name)).code}})[${JSON.stringify(name)}]`,
        {calculateRowItem,canMergeRowQuantity,canMergeRowPrice,exactAmount,_:require('lodash')})
    const build=quantity=>calculateRowItem({item:{id:1,internal_id:'TEST',currency_type_id:'USD',unit_price:'10',has_igv:true},
        quantity,affectation_igv_type_id:'10',discounts:[],charges:[],warehouse_id:1},'VES','12.5','0.16')
    const context=()=>({form:{items:[build('0.1')],currency_type_id:'VES',exchange_rate_sale:'12.5'},
        configuration:{show_unify_amount_items:true},config:{show_unify_amount_items:true},$refs:{},
        percentage_igv:'0.16',isExportInvoice:false,calculateTotal(){}})
    const run=get('addRow'), should=get('shouldUnifyAmountItems'), index=get('getUnifiedItemIndex')

    test(`${file} unifies decimal quantities using the document currency and tax rate`,async()=>{
        const ctx=context();ctx.shouldUnifyAmountItems=should.bind(ctx);ctx.getUnifiedItemIndex=index.bind(ctx)
        await run.call(ctx,build('0.2'))
        assert.equal(ctx.form.items.length,1)
        assert.equal(ctx.form.items[0].quantity,'0.3')
        assert.equal(ctx.form.items[0].currency_type_id,'VES')
        assert.equal(ctx.form.items[0].total,'37.5')
        assert.equal(ctx.form.items[0].percentage_igv,'16')
        assert.deepEqual(compiler.compile(component.template.content).errors,[])
    })

    test(`${file} keeps amount-derived and non-terminating quantities in separate rows`,async()=>{
        const ctx=context();ctx.shouldUnifyAmountItems=should.bind(ctx);ctx.getUnifiedItemIndex=index.bind(ctx)
        const periodic=build('1');periodic.quantity_input={amount:'1',unit_price:'3'}
        const original=calculateRowItem(periodic,'VES','12.5','0.16')
        await run.call(ctx,original)
        assert.equal(ctx.form.items.length,2)
        assert.equal(ctx.form.items[1].calculation_snapshot.values.quantity.n,'1')
        assert.equal(ctx.form.items[1].calculation_snapshot.values.quantity.d,'3')
        const fractional=build('1');fractional.quantity='0.333333333333'
        fractional.calculation_snapshot.values.quantity={n:'1',d:'3'}
        assert.equal(canMergeRowQuantity(fractional),false)
        const first=build('1'), second=build('1')
        second.calculation_snapshot.values.unit_price={n:'125000000000001',d:'1000000000000'}
        assert.equal(first.unit_price,second.unit_price)
        assert.equal(canMergeRowPrice(first,second),false)
        assert.equal(canMergeRowPrice(first,{...first,discounts:[{discount_type_id:'01',amount:'1'}]}),false)
    })

    test(`${file} does not discard a second row with a distinct exact price or adjustment`,async()=>{
        const ctx=context();ctx.shouldUnifyAmountItems=should.bind(ctx);ctx.getUnifiedItemIndex=index.bind(ctx)
        const different=build('0.2')
        different.calculation_snapshot.values.unit_price={n:'125000000000001',d:'1000000000000'}
        await run.call(ctx,different)
        assert.equal(ctx.form.items.length,2)
        const adjusted=build('0.2');adjusted.discounts=[{discount_type_id:'01',amount:'1'}]
        await run.call(ctx,adjusted)
        assert.equal(ctx.form.items.length,3)
        assert.equal(ctx.form.items[2].discounts[0].amount,'1')
    })

    if (invoice) test('invoice barcode accumulation preserves the selected currency and actual quantity',async()=>{
        const ctx=context();ctx.config.show_unify_amount_items=false
        ctx.$refs.item_search_quick_sale={search_item_by_barcode:true}
        ctx.shouldUnifyAmountItems=should.bind(ctx);ctx.getUnifiedItemIndex=index.bind(ctx)
        await run.call(ctx,build('0.2'))
        assert.equal(ctx.form.items.length,1)
        assert.equal(ctx.form.items[0].quantity,'0.3')
        assert.equal(ctx.form.items[0].total,'37.5')
    })

    if (invoice) test('invoice unifies only equivalent presentation quantities', async () => {
        const presented = (presentation) => {
            const line = build('1')
            line.item.presentation = presentation
            return line
        }
        const finitePairs = [
            [{quantity_unit: '1'}, {quantity_unit: '2'}],
            [{id: '7', quantity_unit: '1'}, {id: '7', quantity_unit: '2'}],
            [{id: '7', quantity_unit: '0.333333333333', calculation_snapshot: {values: {quantity_unit: {n: '1', d: '3'}}}},
                {id: '7', quantity_unit: '0.333333333333', quantity_unit_exact: {n: '333333333333', d: '1000000000000'}}],
        ]
        for (const barcode of [false, true]) {
            for (const [first, second] of finitePairs) {
                const ctx = context()
                ctx.config.show_unify_amount_items = !barcode
                if (barcode) ctx.$refs.item_search_quick_sale = {search_item_by_barcode: true}
                ctx.shouldUnifyAmountItems = should.bind(ctx)
                ctx.getUnifiedItemIndex = index.bind(ctx)
                ctx.form.items = [presented(first)]

                await run.call(ctx, presented(second))
                assert.equal(ctx.form.items.length, 2)
                assert.deepEqual(JSON.parse(JSON.stringify(ctx.form.items[0].item.presentation)), first)
                assert.deepEqual(JSON.parse(JSON.stringify(ctx.form.items[1].item.presentation)), second)
                await run.call(ctx, presented(first))
                assert.equal(ctx.form.items.length, 2)
                assert.equal(ctx.form.items[0].quantity, '2')
                assert.equal(ctx.form.items[1].quantity, '1')
            }
        }
    })

    if (invoice) test('invoice preserves separate source rows under automatic and barcode grouping',async()=>{
        for (const barcode of [false,true]) {
            const ctx=context()
            ctx.config.show_unify_amount_items=!barcode
            if (barcode) ctx.$refs.item_search_quick_sale={search_item_by_barcode:true}
            ctx.shouldUnifyAmountItems=should.bind(ctx);ctx.getUnifiedItemIndex=index.bind(ctx)
            const first={...build('1'),source_sale_note_items:[{sale_note_item_id:'9007199254740992'}]}
            const second={...build('1'),source_sale_note_items:[{sale_note_item_id:'9007199254740993'}]}
            ctx.form.items=[first]
            await run.call(ctx,second)
            await run.call(ctx,build('1'))
            assert.equal(ctx.form.items.length,3)
            assert.equal(ctx.form.items[0].source_sale_note_items[0].sale_note_item_id,'9007199254740992')
            assert.equal(ctx.form.items[1].source_sale_note_items[0].sale_note_item_id,'9007199254740993')
            assert.equal(ctx.form.items[2].quantity,'1')
        }
    })

    if (invoice) test('invoice grouping preserves origin and price agreement', async()=>{
        for (const barcode of [false,true]) {
            for (const [firstExtra, secondExtra] of [
                [{source_item_id:'42'}, {}], [{_from_delivery_order:true}, {}],
                [{source_price_mode:'manual'}, {}],
                [{source_price_mode:'manual',price_input:{amount:'10',currency_type_id:'USD',includes_iva:true}},
                 {source_price_mode:'manual',price_input:{amount:'125',currency_type_id:'VES',includes_iva:true}}],
                [{source_price_mode:'manual',price_input:{amount:'10',currency_type_id:'USD',includes_iva:true}},
                 {source_price_mode:'manual',price_input:{amount:'10',currency_type_id:'USD',includes_iva:false}}],
                [{source_price_mode:'manual',price_input:{amount:{n:'1',d:'3'},currency_type_id:'USD',includes_iva:true}},
                 {source_price_mode:'manual',price_input:{amount:'0.333333333333',currency_type_id:'USD',includes_iva:true}}],
            ]) {
                const ctx=context();ctx.config.show_unify_amount_items=!barcode
                if (barcode) ctx.$refs.item_search_quick_sale={search_item_by_barcode:true}
                ctx.shouldUnifyAmountItems=should.bind(ctx);ctx.getUnifiedItemIndex=index.bind(ctx)
                ctx.form.items=[{...build('1'),...firstExtra}]
                await run.call(ctx,{...build('1'),...secondExtra})
                assert.equal(ctx.form.items.length,2,JSON.stringify([firstExtra,secondExtra]))
            }
            for (const [field,value] of [['quotation_id','7'],['order_note_id','8'],['technical_service_id','9'],['sale_note_id','12'],['dispatch_id','13'],
                ['sale_notes_relateds',[{id:'10'}]],['dispatches_relateds',[{id:'11'}]]]) {
                const ctx=context();ctx.config.show_unify_amount_items=!barcode;ctx.form[field]=value
                if (barcode) ctx.$refs.item_search_quick_sale={search_item_by_barcode:true}
                ctx.shouldUnifyAmountItems=should.bind(ctx);ctx.getUnifiedItemIndex=index.bind(ctx)
                await run.call(ctx,build('1'))
                assert.equal(ctx.form.items.length,2,field)
            }
            const ctx=context();ctx.config.show_unify_amount_items=!barcode
            if (barcode) ctx.$refs.item_search_quick_sale={search_item_by_barcode:true}
            ctx.shouldUnifyAmountItems=should.bind(ctx);ctx.getUnifiedItemIndex=index.bind(ctx)
            ctx.form.items=[{...build('1'),source_price_mode:'manual'}]
            await run.call(ctx,{...build('1'),source_price_mode:'manual'})
            assert.equal(ctx.form.items.length,1)
            assert.equal(ctx.form.items[0].quantity,'2')
        }
    })
}
