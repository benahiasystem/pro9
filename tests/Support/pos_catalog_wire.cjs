const assert=require('node:assert/strict')
const {context,methods,math,pos}=require('../js/support/pos-invoice-runtime.cjs')
let input=''
process.stdin.setEncoding('utf8');process.stdin.on('data',chunk=>input+=chunk)
process.stdin.on('end',async()=>{
    try {
        const corpus=JSON.parse(input);let checks=0,invoiceLine=null
        for (const sample of corpus.responses) {
            const response=JSON.parse(sample.body),rows=response[sample.key]
            for (const expected of sample.expected) {
                const source=rows.find(row=>String(row.id)===expected.id)
                assert.ok(source,`${sample.path}: ${expected.id}`)
                assert.equal(typeof source.sale_unit_price,'string')
                assert.equal(math.exactValue(source,'sale_unit_price').compare(expected.price),0)
                assert.equal(String(source.item_id),expected.id)
                if (BigInt(expected.id)>BigInt('9007199254740991')) {
                    assert.equal(typeof source.id,'string');assert.equal(typeof source.item_id,'string')
                }
                for (const file of ['index','garage','fast']) {
                    const item=JSON.parse(JSON.stringify(source)),ctx=context(file);ctx.items=[item]
                    if (math.exactAmount(expected.price).compare('0.1')<0 && file!=='fast') {
                        await ctx.clickAddItem(item,0);assert.equal(ctx.form.items.length,0);assert.equal(ctx.errors.length,1)
                        checks++;continue
                    }
                    for (let n=0;n<3;n++) await ctx.clickAddItem(item,0)
                    assert.equal(ctx.form.items.length,1)
                    let total=math.exactAmount(expected.price).times('3')
                    if (!source.has_igv && source.sale_affectation_igv_type_id==='10') total=total.times('1.16')
                    if (source.currency_type_id==='USD') total=total.times('12.5')
                    assert.equal(math.exactValue(ctx.form.items[0],'total').compare(total),0)
                    assert.equal(ctx.errors.length,0)
                    if (sample.name==='base-list' && expected.id===corpus.primary_id && file==='index') invoiceLine=ctx.form.items[0]
                    checks++
                }
            }
        }
        const base=JSON.parse(corpus.responses.find(sample=>sample.name==='base-list').body).data
        assert.equal(new Set(base.filter(row=>corpus.ids.includes(String(row.id))).map(row=>String(row.id))).size,corpus.ids.length)
        const source=base.find(row=>String(row.id)===corpus.primary_id)
        for (const file of ['index','garage','fast']) {
            const ctx=context(file);Object.assign(ctx,methods(file,['nameSets']))
            ctx.items=base.filter(row=>corpus.ids.includes(String(row.id))).map(row=>({...JSON.parse(JSON.stringify(row)),sets:['Set '+row.id]}))
            for (const item of ctx.items) {
                if (math.exactValue(item,'sale_unit_price').compare('0.1')<0 && file!=='fast') pos.commitPosManualPrice(item,'0.1')
                await ctx.clickAddItem(item,ctx.items.indexOf(item))
                assert.equal(ctx.nameSets(item.item_id),'Set '+item.id)
            }
            assert.equal(ctx.form.items.length,4)
            assert.equal(new Set(ctx.form.items.map(row=>String(row.item_id))).size,4);checks++
        }
        for (const file of ['index','garage','fast']) {
            const item=JSON.parse(JSON.stringify(source)),parent=item.item_unit_types[0],ctx=context(file);ctx.items=[item]
            assert.equal(parent.price_default,2)
            const quote=parent.prices.find(price=>String(price.price_label_id)===corpus.label_id)
            assert.equal(typeof quote.price_label_id,'string')
            assert.equal(String(quote.item_unit_type_id),String(parent.id))
            assert.notEqual(String(quote.id),String(parent.id))
            ctx.setPriceItem(file==='fast'?item.unit_type[0]:quote,0)
            for (let n=0;n<3;n++) await ctx.clickAddItem(item,0)
            assert.equal(String(item.presentation.id),String(parent.id))
            assert.equal(pos.posPresentationQuantity(item).times('3').compare('1'),0)
            if (file === 'fast') assert.equal(ctx.checkedQuantity,'1')
            else assert.equal(ctx.posStockDemand(ctx.form.items).get(String(item.id)).decimal(),'1')
            assert.equal(math.exactValue(ctx.form.items[0],'total').compare(file==='fast'?'2':'1'),0)
            assert.equal(ctx.errors.length,0);checks++
        }
        const barcode=JSON.parse(corpus.barcode.body).items.find(row=>String(row.id)===corpus.primary_id)
        assert.equal(barcode.unit_type.length,1)
        assert.equal(String(barcode.unit_type[0].id),String(source.item_unit_types[0].id));checks++
        const variation=JSON.parse(corpus.variation.body).data[0]
        const groupedVariation=JSON.parse(corpus.grouped_parent.body).data[0].variations.find(row=>String(row.id)===String(variation.id))
        assert.equal(groupedVariation.sale_item.id,variation.id)
        assert.equal(math.exactValue(groupedVariation.sale_item,'sale_unit_price').compare({n:'1',d:'3'}),0)
        for (const file of ['index','garage','fast']) {
            const ctx=context(file);Object.assign(ctx,methods(file,['selectVariationFromModal']))
            ctx.resource='pos';ctx.filterItems=()=>{}
            ctx.$http={async get(url){
                assert.equal(file,'fast','POS y Garage reciben la variación en el catálogo agrupado')
                assert.equal(url,'/pos/item/'+variation.id)
                return {data:JSON.parse(corpus.variation.body)}
            }}
            await ctx.selectVariationFromModal(file==='fast'?{id:variation.id}:groupedVariation)
            assert.equal(ctx.form.items.length,1)
            assert.equal(math.exactValue(ctx.form.items[0],'total').times('3').compare('1'),0);checks++
        }
        assert.ok(invoiceLine)
        process.stdout.write(JSON.stringify({checks,invoice_line:invoiceLine,expected_total:'1'}))
    } catch (error) {process.stderr.write(error.stack+'\n');process.exitCode=1}
})
