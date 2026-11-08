const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs')
const vm=require('node:vm')
const compiler=require('vue-template-compiler')
const parser=require('@babel/parser')
const generate=require('@babel/generator').default
const t=require('@babel/types')
const queryString=require('query-string').default
const component=compiler.parseComponent(fs.readFileSync('modules/Report/Resources/assets/js/components/DataTableCommissions.vue','utf8'))
assert.deepEqual(compiler.compile(component.template.content).errors,[])
const declaration=parser.parse(component.script.content,{sourceType:'module'}).program.body.find(x=>x.type==='ExportDefaultDeclaration').declaration
const methods=declaration.properties.find(x=>x.key?.name==='methods').value
let opened=[]
function method(name){const n=methods.properties.find(x=>x.key?.name===name);return vm.runInNewContext(`(${generate(t.functionExpression(null,n.params,n.body,false,n.async)).code})`,{queryString,window:{open:url=>opened.push(url)}})}
function context(get){const errors=[];const c={resource:'reports/commissions',form:{user_seller_id:1,period:'date',date_start:'2026-11-07'},appliedForm:null,pagination:{current_page:1},records:[],loading_submit:false,$message:{error:x=>errors.push(x)},$http:{get}};for(const n of ['getRecords','getRecordsByFilter','getQueryParameters','clickDownload'])c[n]=method(n).bind(c);return {c,errors}}
const response=(id)=>({data:{data:[{id}],meta:{current_page:1,per_page:'20',total:1}}})
test('export and pagination use the filters that produced the visible rows',async()=>{
 opened=[];const urls=[];const {c}=context(url=>{urls.push(url);return Promise.resolve(response(1))})
 await c.getRecordsByFilter();c.form.user_seller_id=2;c.clickDownload('excel');await c.getRecords()
 assert.match(opened[0],/user_seller_id=1(?:&|$)/);assert.match(urls[1],/user_seller_id=1(?:&|$)/)
})
test('a failed search clears stale results and releases the button',async()=>{
 const {c,errors}=context(()=>Promise.reject({response:{data:{message:'Error de consulta'}}}));c.records=[{id:5}]
 await c.getRecordsByFilter();assert.equal(c.records.length,0);assert.equal(c.loading_submit,false);assert.deepEqual(errors,['Error de consulta'])
})
test('an earlier response or error cannot replace the current results',async()=>{
 const pending={};const {c,errors}=context(url=>new Promise((resolve,reject)=>{pending[url]={resolve,reject}}))
 const first=c.getRecordsByFilter();c.form.user_seller_id=2;const second=c.getRecordsByFilter();
 const firstUrl=Object.keys(pending).find(x=>x.includes('user_seller_id=1'));const secondUrl=Object.keys(pending).find(x=>x.includes('user_seller_id=2'))
 pending[secondUrl].resolve(response(2));await second;pending[firstUrl].reject({response:{data:{message:'Error anterior'}}});await first
 assert.equal(c.records[0].id,2);assert.deepEqual(errors,[]);assert.equal(c.loading_submit,false)
})
test('a new search starts on page one and preserves its date for exports',async()=>{
 opened=[];const urls=[];const {c}=context(url=>{urls.push(url);return Promise.resolve(response(1))})
 c.pagination.current_page=8;await c.getRecordsByFilter();c.form.date_start='2026-12-25';c.clickDownload('excel')
 assert.match(urls[0],/page=1(?:&|$)/);assert.match(opened[0],/date_start=2026-11-07(?:&|$)/)
})
test('changing a visible filter disables export until the report is searched again',()=>{
 const node=declaration.properties.find(x=>x.key?.name==='computed').value.properties.find(x=>x.key?.name==='hasUnappliedFilters')
 const check=vm.runInNewContext(`(${generate(t.functionExpression(null,node.params,node.body)).code})`)
 assert.equal(check.call({appliedForm:{user_seller_id:1,period:'date'},form:{user_seller_id:1,period:'date'}}),false)
 assert.equal(check.call({appliedForm:{user_seller_id:1,period:'date'},form:{user_seller_id:2,period:'date'}}),true)
 assert.match(component.template.content,/:disabled="hasUnappliedFilters \|\| loading_submit"/)
 const {c,errors}=context(()=>Promise.resolve(response(1)))
 c.appliedForm={user_seller_id:1};c.hasUnappliedFilters=true;opened=[];c.clickDownload('excel')
 assert.equal(opened.length,0);assert.match(errors[0],/Buscar/)
})
