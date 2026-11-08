const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'), vm=require('node:vm'), Vue=require('vue')
const compiler=require('vue-template-compiler'), babel=require('@babel/core')
const path='resources/js/views/tenant/documents/partials/free-form-control.vue'
const source=compiler.parseComponent(fs.readFileSync(path,'utf8'))
assert.deepEqual(compiler.compile(source.template.content).errors,[])
const exportsBox={}
vm.runInNewContext(babel.transformSync(source.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,{exports:exportsBox,require})
const component=exportsBox.default
const create=()=>new Vue(component)
const snapshot=value=>JSON.parse(JSON.stringify(value))

test('listing offers the action only when server authorizes the note',()=>{
    const listing=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/index.vue','utf8'))
    assert.deepEqual(compiler.compile(listing.template.content).errors,[])
    assert.match(listing.template.content,/row\.can_register_free_form_control && row\.document_type_id !== '01'/)
    assert.match(listing.template.content,/freeFormControl\.open\(row\.id\)/)
})
test('note control reloads permission and submits one explicit control',async()=>{
    const vm=create(); const calls=[],events=[]
    vm.$http={get:async url=>{calls.push(url);return {data:{data:{id:3,number_full:'NC-3',can_register:true}}}},
        post:async (url,data)=>{calls.push([url,snapshot(data)])}}
    vm.$on('saved',id=>events.push(id))
    await vm.open(3); vm.form={control_number:'00-00000002',evidence_reference:'Nota física'}; await vm.submit()
    assert.equal(calls[0],'/documents/3/free-form-control')
    assert.deepEqual(calls[1],['/documents/3/free-form-control',{control_number:'00-00000002',evidence_reference:'Nota física'}])
    assert.deepEqual(events,[3]);assert.equal(vm.visible,false)
})
test('late response and double submission cannot act on a different note',async()=>{
    const vm=create();let resolveGet, resolvePost, posts=0, saved=0
    vm.$http={get:url=>url.includes('/3/') ? new Promise(done=>{resolveGet=done}) : Promise.resolve({data:{data:{id:4,can_register:true}}}),
        post:()=>{posts++;return new Promise(done=>{resolvePost=done})}}
    vm.$on('saved',()=>saved++)
    const previous=vm.open(3); await vm.open(4); resolveGet({data:{data:{id:3,can_register:true}}}); await previous
    assert.equal(vm.record.id,4)
    const first=vm.submit(); await vm.submit(); assert.equal(posts,1)
    vm.close(); await vm.open(5); resolvePost({}); await first
    assert.equal(saved,0);assert.equal(vm.documentId,5);assert.equal(vm.visible,true)
})
test('unavailable note cannot submit and server errors are visible',async()=>{
    const vm=create();let posts=0
    vm.$http={get:async()=>({data:{data:{can_register:false}}}),post:async()=>{posts++;throw {response:{data:{errors:{control_number:['Control duplicado']}}}}}}
    await vm.open(4);await vm.submit();assert.equal(posts,0)
    vm.record.can_register=true;await vm.submit();assert.equal(posts,1);assert.match(vm.error,/Control duplicado/)
})
