const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const babel = require('@babel/core')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const t = require('@babel/types')

const math = {}
math.moment = require('moment')
vm.runInNewContext(babel.transformSync(fs.readFileSync('resources/js/helpers/exchange-rate-math.js','utf8'), {
    configFile:false, babelrc:false, plugins:['@babel/plugin-transform-modules-commonjs']
}).code, {exports:math})
const source=fs.readFileSync('modules/Finance/Resources/assets/js/components/DataTableMovement.vue','utf8')
const component=compiler.parseComponent(source)
assert.deepEqual(compiler.compile(component.template.content).errors, [])
assert.doesNotMatch(component.template.content, /:default-sort=/)
assert.match(component.template.content, /saldo se acumula por moneda en orden de registro/)
const ast=parser.parse(component.script.content,{sourceType:'module'})
const methods=ast.program.body.find(x=>x.type==='ExportDefaultDeclaration').declaration.properties.find(x=>x.key?.name==='methods').value
const selected=methods.properties.find(x=>x.key?.name==='getSummaries')
const summarize=vm.runInNewContext(`(${generate(t.functionExpression(null,selected.params,selected.body)).code})`, math)
const method = name => {
    const node = methods.properties.find(x => x.key?.name === name)
    return vm.runInNewContext(`(${generate(t.functionExpression(null,node.params,node.body,false,node.async)).code})`, math)
}

test('movement summaries keep VES and USD separate and retain exact fractions',()=>{
    const data=[
        {currency_type_id:'VES',type_movement:'input',calculation_snapshot:{values:{amount:{n:'4000',d:'1'}}}},
        {currency_type_id:'USD',type_movement:'input',calculation_snapshot:{values:{amount:{n:'10',d:'1'}}}},
        {currency_type_id:'USD',type_movement:'output',calculation_snapshot:{values:{amount:{n:'1',d:'3'}}}},
        {currency_type_id:null,type_movement:'input',calculation_snapshot:{values:{amount:{n:'700',d:'1'}}}},
    ]
    const result=summarize({columns:[{property:'currency_type_id'},{property:'input'},{property:'output'},{property:'balance'}],data})
    assert.equal(result[1], 'Bs. 4000.00 / $ 10.00')
    assert.equal(result[2], 'Bs. 0.00 / $ 0.33')
    assert.equal(result[3], 'Bs. 4000.00 / $ 9.67')
})

test('movement pagination stops on failure and does not expose partial results', async () => {
    let calls=0
    const errors=[]
    const context={
        resource:'finances/movements', pagination:{}, records:[], currentTableData:[], itemsPerPage:10,
        getQueryParameters(){return `page=${this.pagination.current_page}`},
        reindex_array_keys:method('reindex_array_keys'), getOtherData:method('getOtherData'),
        getTotals(){}, $message:{error:message=>errors.push(message)},
        $http:{get:async()=>{calls++; if(calls===1) return {data:{data:[{id:1}],meta:{current_page:1,last_page:2}}}; throw new Error('network')}}
    }
    await method('getRecords').call(context,true)
    assert.equal(calls,2)
    assert.equal(context.records.length,0)
    assert.equal(context.currentTableData.length,0)
    assert.equal(context.loading_submit,false)
    assert.equal(errors.length,1)
})

test('movement pagination collects each page before showing results', async () => {
    let calls=0
    const context={
        resource:'finances/movements', pagination:{}, records:[], currentTableData:[], itemsPerPage:10,
        getQueryParameters(){return `page=${this.pagination.current_page}`},
        reindex_array_keys:method('reindex_array_keys'), getOtherData:method('getOtherData'),
        getTotals(){}, $message:{error(){throw new Error('unexpected')}},
        $http:{get:async()=>{calls++; return {data:{data:[{id:calls}],meta:{current_page:calls,last_page:2}}}}}
    }
    await method('getRecords').call(context,true)
    assert.equal(calls,2)
    assert.deepEqual(Array.from(context.currentTableData,x=>x.id),[1,2])
    assert.equal(context.loading_submit,false)
})

test('sorting dates preserves received timestamps and balances across pages', () => {
    const context={
        records:[
            {id:1,date_of_payment:'31-10-2026 12:00 AM',person_name:'Zeta',balance:'10.00'},
            {id:2,date_of_payment:'24-10-2026 12:00 AM',person_name:'Ángel',balance:'20.00'},
            {id:3,date_of_payment:'30-10-2026 01:15 PM',person_name:'Bea',balance:'30.00'},
        ], currentPage:1, itemsPerPage:2
    }
    const sort=method('changeTableSort')
    sort.call(context,{prop:'date_of_payment',order:'ascending'})
    assert.deepEqual(Array.from(context.records,x=>x.id),[2,3,1])
    assert.deepEqual(Array.from(context.records,x=>x.date_of_payment),['24-10-2026 12:00 AM','30-10-2026 01:15 PM','31-10-2026 12:00 AM'])
    assert.deepEqual(Array.from(context.records,x=>x.balance),['20.00','30.00','10.00'])
    assert.deepEqual(Array.from(context.currentTableData,x=>x.id),[2,3])
    sort.call(context,{prop:'person_name',order:'ascending'})
    assert.deepEqual(Array.from(context.records,x=>x.id),[2,3,1])
    sort.call(context,{prop:'person_name',order:null})
    assert.deepEqual(Array.from(context.records,x=>x.id),[1,2,3])
})
