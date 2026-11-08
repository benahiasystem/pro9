const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs')
const vm=require('node:vm')
const compiler=require('vue-template-compiler')
const parser=require('@babel/parser')
const generate=require('@babel/generator').default
const t=require('@babel/types')
const queryString=require('query-string').default
const source=compiler.parseComponent(fs.readFileSync('modules/Report/Resources/assets/js/components/DataTableReportsDocuments.vue','utf8'))
assert.deepEqual(compiler.compile(source.template.content).errors,[])
const declaration=parser.parse(source.script.content,{sourceType:'module'}).program.body.find(x=>x.type==='ExportDefaultDeclaration').declaration
const methods=declaration.properties.find(x=>x.key?.name==='methods').value
let opened=[]
function method(name){const n=methods.properties.find(x=>x.key?.name===name);return vm.runInNewContext(`(${generate(t.functionExpression(null,n.params,n.body,false,n.async)).code})`,{queryString,window:{open:url=>opened.push(url)}})}
function setup(){const urls=[],posts=[],errors=[];const http=req=>{posts.push(req.data);return Promise.resolve({data:{success:true,message:'Listo'}})};http.get=url=>{urls.push(url);return Promise.resolve({data:{data:[{id:1}],meta:{per_page:'20',current_page:1}}})};const c={resource:'reports/sales',form:{person_id:1,user_id:[5],document_type_id:'01',period:'date',date_start:'2026-11-07'},appliedForm:null,pagination:{current_page:1},records:[],users:[],visibleColumns:{},loading_submit:false,$http:http,$message:{error:e=>errors.push(e)},$notify(){},getTotals(){},initTotals(){}};for(const n of ['getRecords','getRecordsByFilter','getQueryParameters','canExportReport','clickDownload','clickExport','clickEmail'])c[n]=method(n).bind(c);const n=declaration.properties.find(x=>x.key?.name==='computed').value.properties.find(x=>x.key?.name==='hasUnappliedFilters');const check=vm.runInNewContext(`(${generate(t.functionExpression(null,n.params,n.body)).code})`);Object.defineProperty(c,'hasUnappliedFilters',{get:()=>check.call(c)});return {c,urls,posts,errors}}
test('report blocks changed filters and retains the searched filters for pagination and exports',async()=>{
 opened=[];const {c,urls,posts,errors}=setup();await c.getRecordsByFilter();c.form.person_id=2;c.form.user_id.push(6);c.form.document_type_id='07';
 c.clickDownload('excel');await c.clickExport('xlsx');c.clickEmail();await c.getRecords()
 assert.equal(opened.length,0);assert.equal(posts.length,0);assert.equal(c.showEmailOptions,undefined);assert.equal(errors.length,3)
 c.form.person_id=1;c.form.user_id.pop();c.form.document_type_id='01';c.clickDownload('excel');await c.clickExport('xlsx')
 const query=new URL(opened[0],'http://localhost').searchParams
 assert.equal(query.get('person_id'),'1');assert.equal(query.getAll('user_id').length,1);assert.equal(query.get('user_id'),'[5]');assert.equal(query.get('document_type_id'),'"01"')
 assert.equal(posts[0].person_id,1);assert.deepEqual(Array.from(posts[0].user_id),[5]);assert.equal(posts[0].document_type_id,'01');assert.match(urls[1],/person_id=1(?:&|$)/)
})
test('older search errors cannot erase the latest successful report',async()=>{
 const {c,errors}=setup();const pending={};c.$http.get=url=>new Promise((resolve,reject)=>pending[url]={resolve,reject});const a=c.getRecordsByFilter();c.form.person_id=2;const b=c.getRecordsByFilter();
 const ua=Object.keys(pending).find(x=>x.includes('person_id=1')),ub=Object.keys(pending).find(x=>x.includes('person_id=2'))
 pending[ub].resolve({data:{data:[{id:2}],meta:{per_page:'20'}}});await b;pending[ua].reject({response:{data:{message:'Error anterior'}}});await a
 assert.equal(c.records[0].id,2);assert.deepEqual(errors,[]);assert.equal(c.loading_submit,false)
})
test('email dialog takes a detached copy of the current searched filters when opened',async()=>{
 const email=compiler.parseComponent(fs.readFileSync('modules/Report/Resources/assets/js/views/documents/partials/options.vue','utf8'))
 const decl=parser.parse(email.script.content,{sourceType:'module'}).program.body.find(x=>x.type==='ExportDefaultDeclaration').declaration
 const n=decl.properties.find(x=>x.key?.name==='methods').value.properties.find(x=>x.key?.name==='create')
 const create=vm.runInNewContext(`(${generate(t.functionExpression(null,n.params,n.body,false,n.async)).code})`)
 const init=decl.properties.find(x=>x.key?.name==='methods').value.properties.find(x=>x.key?.name==='initForm')
 const initForm=vm.runInNewContext(`(${generate(t.functionExpression(null,init.params,init.body)).code})`)
 const data={person_id:1,user_id:[5]}, columns={total:true};const context={data,columns,initForm};await create.call(context)
 data.user_id.push(6);columns.total=false
 assert.equal(context.form.data.person_id,1);assert.deepEqual(Array.from(context.form.data.user_id),[5]);assert.equal(context.form.columns.total,true)
})
test('purchase reports do not offer the sales-only email or queued exports',async()=>{
 const {c,posts}=setup();c.resource='reports/purchases';await c.getRecordsByFilter();c.clickEmail();await c.clickExport('xlsx')
 assert.equal(c.showEmailOptions,undefined)
 assert.equal(posts.length,0)
 assert.match(source.template.content,/<email-options v-if="resource === 'reports\/sales'"/)
})
