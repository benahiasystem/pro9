const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const generate = require('@babel/generator').default
const t = require('@babel/types')

const source = compiler.parseComponent(fs.readFileSync('resources/js/components/DataTableDocuments.vue', 'utf8'))
assert.deepEqual(compiler.compile(source.template.content).errors, [])
const component = parser.parse(source.script.content, {sourceType:'module'}).program.body.find(node => node.type === 'ExportDefaultDeclaration').declaration
const methods = component.properties.find(node => node.key?.name === 'methods').value
function method(name) {
    const node = methods.properties.find(item => item.key?.name === name)
    return vm.runInNewContext(`(${generate(t.functionExpression(null,node.params,node.body,false,node.async)).code})`)
}
function makeContext(get) {
    const errors=[]
    const context={resource:'documents',config:{show_totals_on_cpe_list:true,typeUser:'admin'},
        records:[{id:1}], totals:[{total:'old'}], pagination:{}, loading_submit:false,
        getQueryParameters:()=> 'number=100', $http:{get}, $message:{error:e=>errors.push(e)}}
    for (const name of ['getRecords','getRecordsByFilter','refreshRecords']) context[name]=method(name).bind(context)
    return {context,errors}
}
const reply=(id,total) => ({data:{data:[{id}],meta:{per_page:'20'},totals:[{total}]}})

test('search receives rows and separate currency totals together', async () => {
    let finish,requested
    const {context}=makeContext(url=>{requested=url;return new Promise(resolve=>{finish=resolve})})
    const pending=context.getRecordsByFilter()
    assert.equal(context.loading_submit,true)
    assert.equal(context.totals.length,0)
    finish({data:{data:[{id:2}],meta:{per_page:'20'},totals:[{total:'Bs. 232.00'},{total:'$ 232.00'}]}})
    await pending
    assert.equal(requested,'/documents/records?number=100&include_totals=1')
    assert.equal(context.loading_submit,false)
    assert.equal(context.records[0].id,2)
    assert.equal(context.totals[0].total,'Bs. 232.00')
    assert.equal(context.totals[1].total,'$ 232.00')
})

test('failed or incomplete responses clear old rows and totals and report an error', async () => {
    const first=makeContext(()=>Promise.reject({response:{data:{message:'Filtro inválido'}}}))
    await first.context.getRecordsByFilter()
    assert.equal(first.context.records.length,0)
    assert.equal(first.context.totals.length,0)
    assert.deepEqual(first.errors,['Filtro inválido'])
    const second=makeContext(()=>Promise.resolve({data:{data:[{id:2}],meta:{per_page:'20'}}}))
    await second.context.getRecordsByFilter()
    assert.equal(second.context.records.length,0)
    assert.equal(second.context.totals.length,0)
    assert.equal(second.context.loading_submit,false)
    assert.deepEqual(second.errors,['No se pudieron consultar los totales.'])
})

test('old responses and errors cannot replace a newer list and its totals', async () => {
    const pending={}
    let filter='number=100'
    const {context,errors}=makeContext(url=>new Promise((resolve,reject)=>{pending[url]={resolve,reject}}))
    context.getQueryParameters=()=>filter
    const oldSearch=context.getRecordsByFilter()
    filter='number=200'
    const newSearch=context.refreshRecords()
    pending['/documents/records?number=200&include_totals=1'].resolve(reply(200,'Bs. 200.00'))
    await newSearch
    pending['/documents/records?number=100&include_totals=1'].resolve(reply(100,'Bs. 100.00'))
    await oldSearch
    assert.equal(context.records[0].id,200)
    assert.equal(context.totals[0].total,'Bs. 200.00')
    assert.equal(context.loading_submit,false)
    filter='number=300'
    const stale=context.getRecordsByFilter()
    filter='number=400'
    const latest=context.getRecordsByFilter()
    pending['/documents/records?number=400&include_totals=1'].resolve(reply(400,'Bs. 400.00'))
    await latest
    pending['/documents/records?number=300&include_totals=1'].reject({response:{data:{message:'Error anterior'}}})
    await stale
    assert.equal(context.records[0].id,400)
    assert.equal(context.totals[0].total,'Bs. 400.00')
    assert.deepEqual(errors,[])
})

test('reload and pagination retain totals while a search is in progress', () => {
    const created=component.properties.find(node=>node.key?.name==='created')
    const run=vm.runInNewContext(`(${generate(t.functionExpression(null,created.params,created.body)).code})`)
    let listener, rows=0, totals=0
    const context={totals:[],totalsRequested:true,loadConfiguration(){},initForm(){},$eventHub:{$on(event,fn){listener=fn}},
        getRecords(){rows++},getRecordsByFilter(){totals++}}
    context.refreshRecords=method('refreshRecords').bind(context)
    run.call(context);listener();context.refreshRecords()
    assert.equal(totals,2);assert.equal(rows,0)
    context.totalsRequested=false;listener()
    assert.equal(rows,1)
})
