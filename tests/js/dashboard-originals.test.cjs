const {test}=require('node:test'),assert=require('node:assert/strict')
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const babel=require('@babel/core'),compiler=require('vue-template-compiler')
const load=require('./helpers/load-module.cjs')
const registryFile=path.resolve('modules/Dashboard/Resources/assets/js/widgets/registry.js')
const {exactAmount}=load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
class ChartCapture {
 constructor(ctx,config){this.config=config;ChartCapture.last=config}
 destroy(){}
}
function component(file,styles=()=>({getPropertyValue:()=>'#123456'})) {
 const parsed=compiler.parseComponent(fs.readFileSync(file,'utf8'))
 assert.deepEqual(compiler.compile(parsed.template.content).errors,[])
 const exports={}
 vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
  {exports,setTimeout,clearTimeout,localStorage:{getItem:()=>null},document:{documentElement:{classList:{contains:()=>false}}},
   getComputedStyle:styles,require:name=>{
   if(name.endsWith('.vue'))return {}
   if(name.endsWith('.css'))return {}
   if(name==='chart.js')return ChartCapture
   if(name.includes('vuex'))return {mapState:()=>({}),mapActions:()=>({})}
   const module=name.startsWith('.')?load(path.resolve(path.dirname(file),name.endsWith('.js')?name:name+'.js')):require(name)
   return name.endsWith('registry')?{...module,themeColors:()=>({success:'green',danger:'red',primary:'blue',muted:'gray'}),themePalette:()=>['blue','red']}:module
  }})
 return exports.default
}
function rowTop(extra={}) {
 const source=component('modules/Dashboard/Resources/assets/js/views/RowTop.vue')
 const ctx=Object.assign(source.data(),extra)
 for(const [name,method] of Object.entries(source.methods||{}))ctx[name]=method.bind(ctx)
 for(const [name,method] of Object.entries(source.computed||{}))Object.defineProperty(ctx,name,{get:()=>method.call(ctx)})
 return ctx
}
function dto(amount) {
 return {document_total_global:'0.00',sale_note_total_global:'0.00',total_cpe:1,
  monthly_sales:0,average_ticket:0,accounts_receivable:0,net_utility:0,
  calculation_snapshot:{version:1,values:Object.fromEntries(['monthly_sales','average_ticket','accounts_receivable','net_utility'].map(key=>[key,exactAmount(amount).fraction()]))},
  trend:{labels:[],monthly_sales:[],average_ticket:[],accounts_receivable:[],net_utility:[],calculation_snapshots:[]}}
}
test('dashboard money formatting preserves large original tokens and fractions',()=>{
 const {formatValue,formatKpi}=load(registryFile)
 assert.equal(formatValue('9007199254740993.005','money'),'Bs. 9.007.199.254.740.993,01')
 assert.equal(formatValue({n:'1',d:'200'},'money'),'Bs. 0,01')
 assert.equal(formatKpi({n:'1',d:'200'},'money'),'Bs. 0.01')
})
test('dashboard missing values are displayed as unknown rather than invented zero',()=>{
 const {formatValue,formatKpi}=load(registryFile)
 for(const value of [null,undefined,''])for(const unit of ['money','count','percent']) {
  assert.equal(formatValue(value,unit),'—');assert.equal(formatKpi(value,unit),'—')
 }
})
test('dashboard stock formatting retains fractional quantities',()=>{
 const {formatValue}=load(registryFile)
 assert.equal(formatValue({n:'1',d:'3'},'count'),'0,333333333333')
})
test('RowTop variation uses original snapshots when rounded trend points are both zero',()=>{
 const previous=dto('0.003').calculation_snapshot,current=dto('0.004').calculation_snapshot
 const ctx=rowTop({trend:{labels:['Anterior','Actual'],monthly_sales:[0,0],average_ticket:[0,0],accounts_receivable:[0,0],net_utility:[0,0],
  calculation_snapshots:[previous,current]}})
 assert.ok(ctx.changes.monthly_sales)
 assert.equal(exactAmount(ctx.changes.monthly_sales.pct).compare(exactAmount('100').dividedBy('3')),0)
 assert.equal(ctx.changes.monthly_sales.up,true)
})
test('RowTop ignores an older response after a newer filter response has arrived',async()=>{
 const requests=[]
 const ctx=rowTop({filters:{period:'date',date_start:'2026-11-10'},$http:{get:()=>new Promise(resolve=>requests.push(resolve))}})
 ctx.onFetchData();ctx.filters={period:'date',date_start:'2026-11-11'};ctx.onFetchData()
 requests[1]({data:dto('0.004')});await Promise.resolve();await Promise.resolve()
 requests[0]({data:dto('0.003')});await Promise.resolve();await Promise.resolve()
 assert.equal(exactAmount(ctx.monthly_sales).compare('0.004'),0)
})
test('RowTop keeps absent original KPI data unknown',async()=>{
 const ctx=rowTop({filters:{},$http:{get:()=>Promise.resolve({data:{monthly_sales:999,trend:{labels:[]}}})}})
 ctx.onFetchData();await Promise.resolve();await Promise.resolve()
 assert.equal(ctx.monthly_sales,null)
 assert.equal(ctx.average_ticket,null)
 assert.equal(ctx.accounts_receivable,null)
 assert.equal(ctx.net_utility,null)
})

function renderer(name,props) {
 const source=component('modules/Dashboard/Resources/assets/js/widgets/'+name+'.vue')
 const ctx=Object.assign(source.data?source.data():{},props)
 for(const [key,method]of Object.entries(source.methods||{}))ctx[key]=method.bind(ctx)
 for(const [key,method]of Object.entries(source.computed||{}))Object.defineProperty(ctx,key,{get:()=>method.call(ctx)})
 return ctx
}
function dataset() {
 return {unit:'money',labels:['A','B'],series:[{name:'Ventas',data:[0,0],data_fractions:[exactAmount('0.005').fraction(),exactAmount('0.004').fraction()]}],
  breakdown:{labels:['A','B'],values:[0,0],values_fractions:[exactAmount('0.005').fraction(),exactAmount('0.004').fraction()]},
  totals:{current:0,previous:0,delta:0,calculation_snapshot:{version:1,values:{current:exactAmount('0.004').fraction(),previous:exactAmount('0.003').fraction(),delta:exactAmount('100').dividedBy('3').fraction()}}},meta:{}}
}
test('widget KPI reads original previous/delta and spark direction rather than rounded coordinates',()=>{
 const d=dataset(),ctx=renderer('renderers/KpiRenderer',{dataset:d,period:'date'})
 assert.equal(ctx.valueStr,'Bs. 0.00');assert.equal(ctx.hasDelta,true)
 assert.equal(ctx.deltaStr,'33.3%');assert.equal(ctx.deltaUp,true);assert.equal(ctx.sparkColor,'red')
 assert.equal(ctx.sparkFormatter(999,{dataPointIndex:0}),'Bs. 0,01')
})
test('widget rankings and tables format original categories and use exact relative widths',()=>{
 const d=dataset(),ranking=renderer('renderers/RankingRenderer',{dataset:d,limit:8})
 assert.equal(ranking.rows[0].valueStr,'Bs. 0,01');assert.equal(ranking.rows[0].pct,100);assert.equal(ranking.rows[1].pct,80)
 const table=renderer('renderers/TableRenderer',{dataset:d,limit:10})
 assert.equal(table.rows[0].valueStr,'Bs. 0,01');assert.equal(table.totalStr,'Bs. 0,00')
})
test('widget charts project originals only at drawing and read tooltips by point identity',()=>{
 const d=dataset(),ctx=renderer('renderers/ApexRenderer',{dataset:d,type:'bar',height:240,width:4})
 assert.equal(ctx.chartSeries[0].data[0],0.005)
 assert.equal(ctx.chartOptions.tooltip.y.formatter(999,{seriesIndex:0,dataPointIndex:0}),'Bs. 0,01')
 assert.equal(ctx.chartOptions.tooltip.y.formatter(999,{seriesIndex:0,dataPointIndex:1}),'Bs. 0,00')
})
test('donut total uses the frozen whole rather than the sum of rounded slices',()=>{
 const d=dataset();d.totals.calculation_snapshot.values.current=exactAmount('0.009').fraction()
 const ctx=renderer('renderers/ApexRenderer',{dataset:d,type:'donut',height:240,width:4})
 assert.equal(exactAmount(ctx.breakdownTotal).compare('0.009'),0)
 const labels=ctx.chartOptions.plotOptions.pie.donut.labels
 assert.equal(labels.total.showAlways,true);assert.equal(labels.total.formatter(),'Bs. 0.01')
 assert.equal(labels.value.formatter('999'),'Bs. 0.01')
 assert.equal(ctx.valueTooltip(999,{seriesIndex:0}),'Bs. 0,01')
})
test('radial drawing clamps a graphic while its label retains the original percentage',()=>{
 const d=dataset();d.unit='percent';d.breakdown.labels=['Meta'];d.breakdown.values_fractions=[exactAmount('125').fraction()]
 const ctx=renderer('renderers/ApexRenderer',{dataset:d,type:'radial',height:240,width:4})
 assert.equal(ctx.chartSeries[0],100)
 assert.equal(ctx.chartOptions.plotOptions.radialBar.dataLabels.value.formatter(100),'125%')
})
test('widget visibility uses original nonzero values and rejects incomplete datasets',()=>{
 const d=dataset();d.series[0].data_fractions=[exactAmount('0.0000000000001').fraction(),exactAmount('0').fraction()]
 d.breakdown.values_fractions=[exactAmount('0').fraction(),exactAmount('0').fraction()]
 const ctx=renderer('WidgetCard',{dataset:d,widget:{type:'bar'},filters:{},colSpan:4,rowSpan:3})
 assert.equal(ctx.hasError,false);assert.equal(ctx.hasData,true)
 delete d.series[0].data_fractions
 assert.equal(ctx.hasError,true);assert.equal(ctx.hasData,false)
})
test('original readers reject binary/missing fallbacks without mutating frozen operands',()=>{
 const helper=load(path.resolve('resources/js/helpers/dashboard-originals.js'))
 const row=dto('9007199254740993.005'),before=JSON.stringify(row)
 const token=helper.readOriginal(row,'monthly_sales');assert.equal(Object.isFrozen(token),true)
 assert.equal(helper.originalText(token),'9,007,199,254,740,993.01')
 assert.equal(helper.readOriginal({monthly_sales:999},'monthly_sales'),null)
 assert.equal(helper.formatUnitOriginal(0.1+0.2,'money'),'—')
 assert.equal(helper.formatUnitOriginal(null,'count'),'—')
 assert.equal(helper.sumOriginal([{n:'1',d:'3'},null]),null)
 assert.equal(JSON.stringify(row),before)
})

test('a stale widget batch cannot overwrite new filters or clear the new loading state',async()=>{
 const Vue=require('vue'),previous=Vue.prototype.$http,requests=[]
 Vue.prototype.$http={post:(_url,body)=>new Promise(resolve=>requests.push({body,resolve}))}
 try {
  const {widgetStore,dataKey}=load(path.resolve('modules/Dashboard/Resources/assets/js/widgets/store.js'))
  const widget={source:'ventas.ventas_totales',options:{}};widgetStore.state.layout=[widget]
  const a=widgetStore.setFilters({date_start:'2026-11-10'}),b=widgetStore.setFilters({date_start:'2026-11-11'})
  requests[0].resolve({data:{data:{[dataKey(widget)]:dto('0.003')}}});await a
  assert.equal(widgetStore.isLoading(widget),true);assert.equal(widgetStore.dataset(widget),null)
  requests[1].resolve({data:{data:{[dataKey(widget)]:dto('0.004')}}});await b
  assert.equal(exactAmount(widgetStore.dataset(widget).calculation_snapshot.values.monthly_sales).compare('0.004'),0)
  assert.equal(widgetStore.isLoading(widget),false)
  assert.equal(requests[0].body.filters.date_start,'2026-11-10')
 } finally {Vue.prototype.$http=previous}
})
test('a later refresh of the same widget also wins when responses arrive in reverse order',async()=>{
 const Vue=require('vue'),previous=Vue.prototype.$http,requests=[]
 Vue.prototype.$http={post:()=>new Promise(resolve=>requests.push(resolve))}
 try {
  const {widgetStore,dataKey}=load(path.resolve('modules/Dashboard/Resources/assets/js/widgets/store.js'))
  const widget={source:'ventas.ventas_totales',options:{}};widgetStore.state.layout=[widget]
  const a=widgetStore.refresh(),b=widgetStore.refresh()
  requests[1]({data:{data:{[dataKey(widget)]:dto('0.004')}}});await b
  requests[0]({data:{data:{[dataKey(widget)]:dto('0.003')}}});await a
  assert.equal(exactAmount(widgetStore.dataset(widget).calculation_snapshot.values.monthly_sales).compare('0.004'),0)
 } finally {Vue.prototype.$http=previous}
})
test('a filter change during widget initialization survives the delayed catalog response',async()=>{
 const Vue=require('vue'),previous=Vue.prototype.$http,requests=[];let catalog
 Vue.prototype.$http={get:url=>url.endsWith('catalog')?new Promise(resolve=>{catalog=resolve}):Promise.resolve({data:{layout:[]}}),
  post:(_url,body)=>{requests.push(body);return Promise.resolve({data:{data:{}}})}}
 try {
  const {widgetStore}=load(path.resolve('modules/Dashboard/Resources/assets/js/widgets/store.js'))
  const pending=widgetStore.init({date_start:'2026-11-10'})
  await widgetStore.setFilters({date_start:'2026-11-11'})
  catalog({data:{modules:[],sources:[{key:'ventas.ventas_totales',label:'Ventas',focus:'serie'}]}})
  await pending;assert.equal(widgetStore.state.ready,true)
  assert.equal(requests.at(-1).filters.date_start,'2026-11-11')
 } finally {Vue.prototype.$http=previous}
})
test('the widget grid forwards filter changes while its catalog is still loading',async()=>{
 const source=component('modules/Dashboard/Resources/assets/js/widgets/WidgetGrid.vue'),filters={period:'date'};let received
 await source.watch.filters.handler.call({state:{ready:false},store:{setFilters:async value=>{received=value}}},filters)
 assert.equal(received,filters)
})

function classic(name,props={}) {
 const file='modules/Dashboard/Resources/assets/js/views/partials/'+name+'.vue',source=component(file)
 const ctx=Object.assign(source.data?source.data.call(props):{},props)
 for(const [key,method]of Object.entries(source.methods||{}))ctx[key]=method.bind(ctx)
 for(const [key,method]of Object.entries(source.computed||{}))Object.defineProperty(ctx,key,{get:()=>method.call(ctx)})
 return {source,ctx,file}
}
function classicDto(value) {
 const fields=['goal','sales','percent','remaining','projected','total','current_total','previous_total','income_total','egress_total','net_total']
 const fraction=exactAmount(value).fraction()
 return {labels:['A'],previous_labels:['B'],income:[0],egress:[0],current:[0],previous:[0],values:[0],total:1,count:1,
  calculation_snapshot:{version:1,values:Object.fromEntries(fields.map(key=>[key,fraction]))},
  income_fractions:[fraction],egress_fractions:[fraction],current_fractions:[fraction],previous_fractions:[fraction],values_fractions:[fraction],
  items:[{product:'Artículo',stock:'999',calculation_snapshot:{version:1,values:{stock:fraction,total_to_pay:fraction}}}]}
}
for(const [name,read] of [
 ['MonthGoal',ctx=>ctx.goal],['CashFlowChart',ctx=>ctx.income[0]],['WeeklySalesChart',ctx=>ctx.current[0]],
 ['PaymentMethods',ctx=>ctx.values[0]],['Debtors',ctx=>ctx.total],['LowStock',ctx=>ctx.items[0].calculation_snapshot.values.stock],
]) {
 test(name+' keeps originals and ignores stale responses without ending the active loader',async()=>{
  const requests=[],$http={get:()=>new Promise(resolve=>requests.push(resolve))}
  const {ctx}=classic(name,{filters:{period:'date'},$http})
  const a=ctx.fetchData(),b=ctx.fetchData()
  requests[0]({data:classicDto('0.003')});await a
  assert.equal(ctx.loading,true)
  requests[1]({data:classicDto('0.004')});await b
  assert.equal(exactAmount(read(ctx)).compare('0.004'),0);assert.equal(ctx.loading,false)
 })
 test(name+' keeps amounts unknown when the request fails',async()=>{
  const {ctx}=classic(name,{filters:{},$http:{get:()=>Promise.reject(new Error('offline'))}})
  await ctx.fetchData();assert.equal(ctx.loading,false);assert.equal(ctx.fetchFailed,true)
  if(name==='MonthGoal')assert.equal(ctx.goal,null)
  if(name==='Debtors'||name==='PaymentMethods'||name==='LowStock')assert.equal(ctx.total,null)
 })
}
test('weekly variation uses independent original totals and keeps nullable comparison days',()=>{
 const {ctx}=classic('WeeklySalesChart',{currentTotal:exactAmount('0.01').fraction(),previousTotal:exactAmount('0.003').fraction(),
  current:[exactAmount('0.005').fraction(),exactAmount('0.005').fraction()],previous:[exactAmount('0.003').fraction(),null]})
 assert.equal(exactAmount(ctx.variation.value).compare(exactAmount('700').dividedBy('3')),0)
 assert.equal(ctx.series[1].data[1],null)
 assert.equal(ctx.chartOptions.tooltip.y.formatter(999,{seriesIndex:0,dataPointIndex:0}),'Bs. 0,01')
})
test('month goal clamps drawing while retaining its raw percentage and large money text',()=>{
 const {source,ctx}=classic('MonthGoal',{percent:exactAmount('125.25').fraction()})
 assert.equal(ctx.chartPercent,100);assert.equal(ctx.chartOptions.plotOptions.radialBar.dataLabels.value.formatter(100),'125.3%')
 assert.equal(source.filters.mgMoney(exactAmount('9007199254740993.005').fraction()),'9,007,199,254,740,993.01')
})
test('classic payment tooltips ignore numeric slice values and use original slice identities',()=>{
 const {ctx}=classic('PaymentMethods',{values:[exactAmount('0.005').fraction()],total:exactAmount('0.005').fraction()})
 assert.equal(ctx.series[0],0.005)
 assert.equal(ctx.chartOptions.tooltip.y.formatter(999,{seriesIndex:0}),'Bs. 0,01')
 assert.equal(ctx.chartOptions.plotOptions.pie.donut.labels.total.formatter(),'Bs. 0.01')
})
test('compiled product template formats header-allocated totals and movement snapshots',()=>{
 const Vue=require('vue'),{source,file}=classic('TopProducts',{value:false})
 const parsed=compiler.parseComponent(fs.readFileSync(file,'utf8')),compiled=compiler.compile(parsed.template.content)
 source.render=new Function(compiled.render);source.staticRenderFns=compiled.staticRenderFns.map(code=>new Function(code))
 const item={description:'Producto',total:'999.00',move_quantity:'999',calculation_snapshot:{version:1,values:{total:exactAmount('0.005').fraction(),move_quantity:exactAmount('1').dividedBy('3').fraction()}}}
 const before=JSON.stringify(item),instance=new Vue({...source,propsData:{items:[item],value:false}}),node=instance._render(),texts=[]
 function walk(vnode){if(vnode.text)texts.push(vnode.text);for(const child of vnode.children||[])walk(child)}walk(node)
 assert.ok(texts.join(' ').includes('0.01'));assert.ok(texts.join(' ').includes('0,333333333333'))
 assert.ok(!texts.join(' ').includes('999'));assert.equal(JSON.stringify(item),before);instance.$destroy()
})
test('debtor totals and original USD balances do not fall back to rounded public fields',()=>{
 const {source,ctx}=classic('Debtors',{loading:false,items:[{customer:'A',debts:[{due_days:0,total_to_pay:'999',calculation_snapshot:{version:1,values:{total_to_pay:exactAmount('1').dividedBy('3').fraction()}},original_balance:'999',original_balance_exact:exactAmount('1').dividedBy('7').fraction()}]}]})
 assert.equal(exactAmount(ctx.urgentTotal).compare(exactAmount('1').dividedBy('3')),0)
 assert.equal(source.filters.dbMoney(ctx.originalBalance(ctx.items[0].debts[0])),'0,142857142857')
 assert.equal(ctx.debtTotal({total_to_pay:'999'}),null)
})
test('legacy stock decimal strings retain their exact textual contract while binary fallbacks are rejected',()=>{
 const {ctx}=classic('LowStock')
 assert.equal(ctx.stockText({stock:'0.123456789012'}),'0.123456789012')
 assert.equal(ctx.stockText({stock:0.1+0.2}),'—')
 assert.equal(ctx.stockText({stock:'999',calculation_snapshot:{version:1,values:{stock:exactAmount('1').dividedBy('3').fraction()}}}),'0.333333333333')
})
test('comparison chart retains negative credit sales, gaps and safe original tooltips',()=>{
 const {ctx}=classic('new_dashboard/MonthlyComparisonChart',{categories:['<img src=x onerror=alert(1)>','B'],offset:0,maxVisible:12,bodyWidth:400,emptyBarRatio:0.04,
  sales:[999,999],purchases:[999,999],salesExact:['999','999'],purchasesExact:['999','999'],
  salesFractions:[exactAmount('-1').fraction(),null],purchasesFractions:[exactAmount('0.005').fraction(),exactAmount('0').fraction()]})
 assert.equal(ctx.series[0].data[0].y,-1);assert.equal(ctx.series[0].data[1].y,null);assert.ok(ctx.chartOptions.yaxis.min<0)
 const text=ctx.chartOptions.tooltip.custom({seriesIndex:0,dataPointIndex:0,w:{config:{series:[{name:'Ventas'}]},globals:{labels:ctx.categories}}})
 assert.ok(text.includes('Bs. -1.00'));assert.ok(text.includes('&lt;img'));assert.ok(!text.includes('<img'))
})
test('growth chart tooltips never borrow a numeric point when the original is absent',()=>{
 const {ctx}=classic('new_dashboard/SalesGrowthChart',{categories:['A'],currentLabel:'2026',previousLabel:'2025',current:[999],previous:[999],currentExact:['999'],previousExact:['999'],
  currentFractions:[exactAmount('0.005').fraction()],previousFractions:[]})
 assert.equal(ctx.series[0].data[0],0.005)
 assert.equal(ctx.chartOptions.tooltip.y.formatter(999,{seriesIndex:0,dataPointIndex:0}),'Bs. 0.01')
 assert.equal(ctx.chartOptions.tooltip.y.formatter(999,{seriesIndex:1,dataPointIndex:0}),'—')
})
test('new dashboard KPI preserves its original large amounts and ignores stale responses',async()=>{
 const requests=[],{ctx}=classic('new_dashboard/NewDashboard',{$http:{get:()=>new Promise(resolve=>requests.push(resolve))}})
 const value=amount=>({data:{data:{month_sales:'999',month_purchases:'999',accumulated_sales:'999',accumulated_year:'2026',
  calculation_snapshot:{version:1,values:{month_sales:exactAmount(amount).fraction(),month_purchases:exactAmount('1').dividedBy('3').fraction(),accumulated_sales:exactAmount(amount).fraction(),month_sales_variation:exactAmount('100').dividedBy('3').fraction(),month_purchases_variation:exactAmount('0').fraction()}}}}})
 const a=ctx.loadKpi(),b=ctx.loadKpi();requests[1](value('9007199254740993.005'));await b;requests[0](value('1'));await a
 assert.equal(ctx.kpi.monthSales,'9,007,199,254,740,993.01');assert.equal(ctx.formatPercent(ctx.kpi.monthSalesVariation),'+33.3%')
 assert.equal(ctx.kpi.operatingMargin,'—');assert.equal(ctx.loadingKpi,false)
})

function mainContext(extra={}) {
 const file='modules/Dashboard/Resources/assets/js/views/index.vue',source=component(file)
 const ctx=Object.assign(source.data.call({}),extra)
 for(const [key,method]of Object.entries(source.methods||{}))ctx[key]=method.bind(ctx)
 for(const [key,method]of Object.entries(source.computed||{}))Object.defineProperty(ctx,key,{get:()=>method.call(ctx)})
 return {ctx,source,file}
}
function collectionCard(value='0.008') {
 return {totals:{total:'999',total_payment:'999',total_to_pay:'999',calculation_snapshot:{version:1,values:{total:exactAmount(value).fraction(),total_payment:exactAmount('0.004').fraction(),total_to_pay:exactAmount('0.004').fraction()}}},
  graph:{labels:['Pagado','Pendiente'],datasets:[{label:'Cobros',data:[999,999],calculation_snapshot:{version:1,values:{paid:exactAmount('0.004').fraction(),pending:exactAmount('0.004').fraction()}}}]}}
}
test('classic container reads collection totals and tooltips from snapshots',()=>{
 const {ctx}=mainContext({sale_note:collectionCard(),document:collectionCard()})
 assert.equal(exactAmount(ctx.saleNoteTotal).compare('0.008'),0);assert.equal(exactAmount(ctx.documentPaid).compare('0.004'),0)
 assert.equal(ctx.saleNoteValues[0],0.004)
 assert.equal(ctx.saleNoteChartOptions.tooltip.y.formatter(999,{seriesIndex:0}),'Bs. 0.00')
 assert.equal(ctx.documentChartOptions.plotOptions.pie.donut.labels.total.formatter(),'Bs. 0.01')
})
test('classic main card loaders ignore stale replies for each independent request group',async()=>{
 const requests=[],{ctx}=mainContext({$http:{post:(_url,body)=>new Promise(resolve=>requests.push({body,resolve}))}})
 ctx.form={period:'date',date_start:'2026-11-10'}
 const a=ctx.loadData();ctx.form.date_start='2026-11-11';const b=ctx.loadData()
 const response=amount=>({data:{data:{document:collectionCard(amount),sale_note:collectionCard(amount),balance:{totals:{},graph:[]},general:{totals:{},graph:[]},items:[]}}})
 requests[0].resolve(response('0.003'));await a;assert.equal(ctx.loaders.document,true)
 requests[1].resolve(response('0.004'));await b;assert.equal(ctx.loaders.document,false)
 assert.equal(exactAmount(ctx.documentTotal).compare('0.004'),0)
 assert.equal(requests[0].body.date_start,'2026-11-10')
})
test('classic balance rows bind the whole and paid totals separately',()=>{
 const {file}=mainContext(),template=compiler.parseComponent(fs.readFileSync(file,'utf8')).template.content
 const compiled=compiler.compile(template)
 assert.equal((compiled.render.match(/balance\.totals\.all_totals\)/g)||[]).length,1)
 assert.equal((compiled.render.match(/balance\.totals\.all_totals_payment\)/g)||[]).length,1)
})
function chartContext(file,props) {
 const source=component(file),ctx={...props}
 for(const [key,method]of Object.entries(source.methods||{}))ctx[key]=method.bind(ctx)
 Object.assign(ctx,source.data.call(ctx))
 ctx.$refs={canvas:{getContext:()=>({canvas:{height:220},createLinearGradient:()=>({addColorStop(){}})})}}
 return {source,ctx}
}
test('Chart.js dashboard doughnut uses its declared whole rather than adding whole and paid',()=>{
 const source=collectionCard().graph;source.datasets[0].calculation_snapshot.values={whole:exactAmount('9').fraction(),paid:exactAmount('3').fraction()}
 const {ctx}=chartContext('resources/js/components/graph/src/Graph.vue',{allData:source,exactTotal:exactAmount('9').fraction(),totalLabel:'Balance'})
 assert.equal(ctx.getTotal(),'Bs. 9.00')
 assert.equal(ctx.options.plugins.doughnutlabel.labels[1].text,'Balance')
 ctx.createChart()
 assert.equal(ChartCapture.last.data.datasets[0].data[0],9)
 assert.equal(ChartCapture.last.options.tooltips.callbacks.label({datasetIndex:0,index:1},ChartCapture.last.data),' Pendiente: Bs. 3.00')
})
test('Chart.js purchase line tooltips retain original values rather than library numeric labels',()=>{
 const fraction=exactAmount('9007199254740993.005').fraction(),graph={labels:['A'],datasets:[{label:'Compras',data:[999],data_fractions:[fraction]}]}
 const {ctx}=chartContext('resources/js/components/graph/src/GraphLine.vue',{allData:graph})
 ctx.createChart()
 const label=ChartCapture.last.options.tooltips.callbacks.label({datasetIndex:0,index:0,yLabel:999},ChartCapture.last.data)
 assert.equal(label,' Compras: Bs. 9,007,199,254,740,993.01')
 assert.equal(graph.datasets[0].data[0],999)
})
test('Chart.js purchase lines retain usable colors without an optional installed theme',()=>{
 const source=component('resources/js/components/graph/src/GraphLine.vue',()=>({getPropertyValue:()=>''}))
 assert.equal(source.methods.getCSSVariable('--info'),'#00cfe8')
 assert.equal(source.methods.getCSSVariable('--danger'),'#fe006c')
 assert.equal(source.methods.getCSSVariable('--primary-color'),'#2447e8')
 const themed=component('resources/js/components/graph/src/GraphLine.vue',()=>({getPropertyValue:name=>name==='--primary'?'#abcdef':''}))
 assert.equal(themed.methods.getCSSVariable('--primary-color'),'#abcdef')
})
