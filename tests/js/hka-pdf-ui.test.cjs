const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), vm = require('node:vm');
const compiler = require('vue-template-compiler'), babel = require('@babel/core');
const actions = [], downloads = [], revoked = [];
const scope = {
 window: {open: url => actions.push(url)},
 document: {body: {appendChild() {}}, createElement: () => ({click() {downloads.push(this.download)}, remove() {}})},
 URL: {createObjectURL: () => 'blob:pdf', revokeObjectURL: url => revoked.push(url)},
 setTimeout: callback => callback(),
};
function evaluate(source, requireFn = () => ({})) {
 const exports = {};
 vm.runInNewContext(babel.transformSync(source, {configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']}).code, {...scope, exports, require: requireFn});
 return exports;
}
const pdf = evaluate(fs.readFileSync('resources/js/mixins/document-pdf.js', 'utf8')).documentPdf;
const email = require('./helpers/load-module.cjs')(require('node:path').resolve('resources/js/mixins/document-email.js'));
function load(file) {
 const parsed = compiler.parseComponent(fs.readFileSync(file, 'utf8'));
 assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
 return evaluate(parsed.script.content, name => name === '@mixins/document-pdf' ? {documentPdf: pdf}
  : name === '@mixins/document-email' ? email : name.includes('vuex') ? {mapActions:()=>({}),mapState:()=>({})} : {}).default;
}
const web = load('resources/js/views/tenant/documents/partials/options.vue');
const pos = load('resources/js/views/tenant/pos/partials/options.vue');
const finance = load('modules/Finance/Resources/assets/js/views/unpaid/partials/options.vue');
function context(component, get = async () => ({headers: {'content-type':'application/pdf'}, data: {size: 400}})) {
 actions.length = downloads.length = revoked.length = 0;
 const messages = [], requests = [], ctx = {...component.data(), resource: 'documents', type: 'document', showDialog: true,
  config:{show_ticket_80:true},
  form: {id:16, external_id:'external-16', print_a4:'/local/a4', print_a5:'/local/a5', print_ticket:'/local/ticket',
   fiscal_emission_mode:'digital', document_type_id:'01', pdf_downloads: Object.fromEntries(['a4','a5','ticket'].map(format => [format, {provider:format === 'ticket' ? 'local' : 'hka', available:true, url:`/documents/external-16/download-pdf/${format}`}]))},
  $http: {get: async (...args) => {requests.push(args);return get(...args)}},
  $message: {error:m=>messages.push(m),warning:m=>messages.push(m)}, messages, requests};
 for (const [name, fn] of Object.entries(component.methods)) ctx[name] = fn.bind(ctx);
 return ctx;
}
test('web and listing shared dialog downloads both formats using the dedicated routes', async () => {
 const ctx = context(web);
 await ctx.clickPrint('a4'); await ctx.clickPrint('a5'); await ctx.clickDownload('a5');
 assert.deepEqual(ctx.requests.map(r=>r[0]), ['/documents/external-16/download-pdf/a4','/documents/external-16/download-pdf/a5','/documents/external-16/download-pdf/a5']);
 assert.equal(ctx.requests[0][1].responseType, 'blob');
 assert.deepEqual(downloads, ['factura-16-a4.pdf','factura-16-a5.pdf','factura-16-a5.pdf']); assert.equal(actions.length,0);
 assert.equal(revoked.length,3);
});
test('POS/Garage format buttons and keyboard tabs use the same service', async () => {
 const ctx = context(pos);
 await ctx.downloadDocumentPdf('a5',ctx.form.print_a5);
 ctx.activeName='quarter'; await ctx.someMethod(); ctx.activeName='fifth'; await ctx.someMethod();
 assert.deepEqual(ctx.requests.map(r=>r[0].split('/').pop()), ['a5','a4','a5']); assert.equal(actions.length,0);
});
test('downloads retain the fiscal filename for quoted and plain disposition headers', async () => {
 for(const disposition of ['attachment; filename=J123456789-01-FF01-16.pdf','attachment; filename="J123456789-01-FF01-16.pdf"']) {
  const ctx=context(web,async()=>({headers:{'content-type':'application/pdf','content-disposition':disposition},data:{size:100}}));
  await ctx.clickPrint('a4');assert.deepEqual(downloads,['J123456789-01-FF01-16.pdf']);
 }
});
test('finance A4 action uses HKA while sale notes retain their local print URL', async () => {
 const ctx=context(finance);await ctx.clickPrint('a4');assert.equal(ctx.requests.length,1);
 ctx.type='sale_note';ctx.clickPrint('a5');assert.deepEqual(actions,['/local/a5']);assert.equal(ctx.requests.length,1);
});
test('pending invoices block buttons and direct methods without downloading local copies', async () => {
 for(const component of [web,pos,finance]) {
  const ctx=context(component);ctx.form.pdf_downloads.a4.available=false;ctx.form.pdf_downloads.a4.message='Confirme la factura';
  assert.equal(ctx.pdfDownloadDisabled('a4'),true);await ctx.downloadDocumentPdf('a4','/local/a4');
  assert.equal(ctx.requests.length,0);assert.equal(actions.length,0);assert.equal(ctx.messages[0],'Confirme la factura');
 }
});
test('double clicks share the busy state and release it when download finishes', async () => {
 let resolve;const ctx=context(web,()=>new Promise(r=>resolve=r));
 const first=ctx.clickPrint('a4');await ctx.clickPrint('a5');assert.equal(ctx.requests.length,1);assert.equal(ctx.pdfDownloadDisabled('a5'),true);
 resolve({headers:{'content-type':'application/pdf'},data:{size:100}});await first;assert.equal(ctx.pdfDownloadBusy,false);
});
test('blob validation and network errors retain the sale and allow manual retry', async () => {
 const ctx=context(web,async()=>{throw{response:{data:{text:async()=>JSON.stringify({pdf:['HKA rechazó la descarga']})}}}});
 await ctx.clickPrint('a5');assert.equal(ctx.messages[0],'HKA rechazó la descarga');assert.equal(ctx.form.id,16);assert.equal(ctx.pdfDownloadBusy,false);assert.equal(downloads.length,0);assert.equal(actions.length,0);
 ctx.$http.get=async()=>{throw Error('offline')};await ctx.clickPrint('a4');assert.match(ctx.messages[1],/venta sigue guardada/);
 ctx.$http.get=async()=>({headers:{'content-type':'text/html'},data:{size:100}});await ctx.clickPrint('a4');assert.equal(downloads.length,0);
});
test('local formats and tickets preserve existing links; a missing remote contract fails closed', async () => {
 const ctx=context(web);ctx.form.fiscal_emission_mode='free_form';ctx.form.pdf_downloads.a4.provider=ctx.form.pdf_downloads.a5.provider='local';
 await ctx.clickPrint('a4');await ctx.clickPrint('ticket');assert.deepEqual(actions,['/print/document/external-16/a4','/print/document/external-16/ticket']);assert.equal(ctx.requests.length,0);
 ctx.form.fiscal_emission_mode='digital';ctx.form.pdf_downloads={};await ctx.clickPrint('a5');assert.equal(actions.length,2);assert.equal(ctx.requests.length,0);
});
test('80MM web/list/finance buttons and POS/Garage shortcuts download a local ticket from the authenticated route', async () => {
 for (const component of [web,finance]) {
  const ctx=context(component);await ctx.clickPrint('ticket');
  assert.deepEqual(ctx.requests.map(r=>r[0]),['/documents/external-16/download-pdf/ticket']);
  assert.deepEqual(downloads,['factura-16-80mm.pdf']);assert.equal(actions.length,0);
 }
 const ctx=context(pos);await ctx.downloadDocumentPdf('ticket',ctx.form.print_ticket);
 ctx.activeName='first';await ctx.someMethod();
 assert.deepEqual(ctx.requests.map(r=>r[0]),['/documents/external-16/download-pdf/ticket','/documents/external-16/download-pdf/ticket']);
 assert.deepEqual(downloads,['factura-16-80mm.pdf','factura-16-80mm.pdf']);assert.equal(actions.length,0);
});
test('an unavailable HKA QR blocks only 80MM and releases the busy state after failures', async () => {
 const ctx=context(web);ctx.form.pdf_downloads.ticket.available=false;ctx.form.pdf_downloads.ticket.message='Consulte el estado HKA';
 assert.equal(ctx.pdfDownloadDisabled('ticket'),true);await ctx.clickPrint('ticket');
 assert.equal(ctx.requests.length,0);assert.equal(actions.length,0);assert.equal(ctx.messages[0],'Consulte el estado HKA');
 assert.equal(ctx.pdfDownloadDisabled('a4'),false);assert.equal(ctx.pdfDownloadDisabled('a5'),false);
 assert.equal(pdf.computed.pdfDownloadMessage.call(ctx),'Consulte el estado HKA');
 ctx.config.show_ticket_80=false;assert.equal(pdf.computed.pdfDownloadMessage.call(ctx),'');
 ctx.form.pdf_downloads.ticket.available=true;ctx.$http.get=async()=>{throw Error('offline')};
 await ctx.clickPrint('ticket');assert.equal(ctx.pdfDownloadBusy,false);assert.equal(downloads.length,0);assert.match(ctx.messages[1],/ticket 80MM/);
});
test('80MM visibility still follows configuration and preview/print URLs remain unchanged', async () => {
 for(const component of [web,finance]) {
  const ctx=context(component);ctx.config.show_ticket_80=false;assert.equal(component.computed.ShowTicket80.call(ctx),false);
  ctx.config.show_ticket_80=true;assert.equal(component.computed.ShowTicket80.call(ctx),true);
 }
 for(const file of ['resources/js/views/tenant/documents/partials/options.vue','modules/Finance/Resources/assets/js/views/unpaid/partials/options.vue']) {
  const template=fs.readFileSync(file,'utf8');assert.match(template,/v-if="ShowTicket80"/);assert.match(template,/:disabled="pdfDownloadDisabled\('ticket'\)"/);
 }
 const source=fs.readFileSync('resources/js/views/tenant/pos/partials/options.vue','utf8');
 assert.match(source,/v-if="config !== null && config.show_ticket_80"/);
 assert.match(source,/:src="form.print_ticket"/);assert.match(source,/@click="downloadDocumentPdf\('ticket', form.print_ticket\)"/);
});
test('POS preview URLs and automatic printing remain local and distinct from download buttons', async () => {
 const source=fs.readFileSync('resources/js/views/tenant/pos/partials/options.vue','utf8');
 assert.match(source,/:src="form.print_a4"/);assert.match(source,/:src="form.print_a5"/);
 assert.match(source,/@click="downloadDocumentPdf\('a4', form.print_a4\)"/);assert.match(source,/@click="downloadDocumentPdf\('a5', form.print_a5\)"/);
 const ctx=context(pos);ctx.isPrint=true;ctx.form.print_ticket='/local/ticket';ctx.configuration={};ctx.printDocument=async url=>actions.push(url);
 await ctx.autoPrint();assert.deepEqual(actions,['/local/ticket']);assert.equal(ctx.requests.length,0);
});
