const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs')
const vm=require('node:vm')
const compiler=require('vue-template-compiler')
const parser=require('@babel/parser')
const generate=require('@babel/generator').default
const {getQuantityPrecisionByUnitType}=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/functions.js'))
const {exactAmount}=require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/helpers/exchange-rate-math.js'))

test('continuous quantities use the Venezuelan IDs, not their HKA equivalents',()=>{
    for (const id of ['KG','GR','MG','TON','LB','LT','ML','GAL','M3','M','CM','MM','KM','PULG','M2','HR','DIA'])
        assert.equal(getQuantityPrecisionByUnitType(id),12,id)
    for (const id of ['UND','CAJ','BOT','SERV','KGM','LTR','TNE'])
        assert.equal(getQuantityPrecisionByUnitType(id),0,id)
    assert.equal(getQuantityPrecisionByUnitType('kg'),12)
})

test('invoice item step respects the local unit',()=>{
    const component=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/partials/item.vue','utf8'))
    const ast=parser.parse(component.script.content,{sourceType:'module'})
    const computed=ast.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='computed').value
    const get=name=>vm.runInNewContext(`({${generate(computed.properties.find(n=>n.key?.name===name)).code}})[${JSON.stringify(name)}]`,{getQuantityPrecisionByUnitType})
    const fractional=get('hasFractionalQuantity'),step=get('quantityStep')
    for (const id of ['KG','LT','M','HR']) assert.equal(step.call({hasFractionalQuantity:fractional.call({form:{item:{unit_type_id:id}}})}),0.01,id)
    assert.equal(step.call({hasFractionalQuantity:fractional.call({form:{item:{unit_type_id:'UND'}}})}),1)
    assert.deepEqual(compiler.compile(component.template.content).errors,[])
})

test('item editor and list present the local unit codes',()=>{
    const editor=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/items/form.vue','utf8'))
    assert.match(editor.template.content,/\['KG', 'LT', 'M', 'GAL'\]\.includes\(form\.unit_type_id\)/)
    assert.deepEqual(compiler.compile(editor.template.content).errors,[])
    const list=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/items/index.vue','utf8'))
    const ast=parser.parse(list.script.content,{sourceType:'module'})
    const methods=ast.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='methods').value
    const fn=vm.runInNewContext(`({${generate(methods.properties.find(n=>n.key?.name==='unitSymbol')).code}}).unitSymbol`)
    assert.equal(fn('KG'),'kg');assert.equal(fn('LT'),'L');assert.equal(fn('M2'),'m²');assert.equal(fn('UND'),'und')
    const stock=vm.runInNewContext(`({${generate(methods.properties.find(n=>n.key?.name==='formatStock')).code}}).formatStock`,{exactAmount})
    assert.equal(stock('123456789012345.123456789012','KG'),'123,456,789,012,345.123456789012')
    assert.equal(stock('1.5','UND'),'1.5')
    assert.equal(stock(null,'KG'),'No disponible')
    assert.deepEqual(compiler.compile(list.template.content).errors,[])
})
