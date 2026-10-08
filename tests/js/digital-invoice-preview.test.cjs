const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const babel = require('@babel/core');
const compiler = require('vue-template-compiler');
const revoked = [];
let generated = 0;
global.window = {URL: {revokeObjectURL: url => revoked.push(url)}};
function load(file) {
    const source = fs.readFileSync(file, 'utf8');
    const parsed = compiler.parseComponent(source);
    assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
    const exports = {};
    const code = babel.transformSync(parsed.script.content, {plugins: ['@babel/plugin-transform-modules-commonjs']}).code;
    vm.runInNewContext(code, {exports, require: () => ({mapState: () => ({})}), window,
        URL: {createObjectURL: () => `blob:${++generated}`, revokeObjectURL: url => revoked.push(url)}});
    return exports.default;
}
const draft = load('resources/js/views/tenant/documents/partials/preview.vue');
function context(component, extra = {}) {
    const ctx = {...component.data(), ...extra};
    for (const [name, fn] of Object.entries(component.methods)) ctx[name] = fn.bind(ctx);
    return ctx;
}
test('draft digital preview forces 80MM with a different default and disabled ticket setting', async () => {
    const requests = [];
    const ctx = context(draft, {showDialog:true, digitalInvoice:true, config:{show_ticket_80:false},
        format:'a5', preview: async format => {requests.push(format);return 'blob:draft';}});
    await ctx.openPreview();
    assert.equal(ctx.format, 'ticket');assert.deepEqual(requests,['ticket']);assert.equal(ctx.URL,'blob:draft');
    assert.equal(ctx.loading,false);assert.equal(ctx.error,'');
});
test('closing revokes the PDF and reopening requests fresh draft content', async () => {
    let count = 0;
    const ctx = context(draft,{showDialog:true,digitalInvoice:true,preview:async()=>`blob:draft-${++count}`,$emit:()=>{}});
    await ctx.openPreview();ctx.clickClose();assert.equal(ctx.URL,null);
    await ctx.openPreview();assert.equal(ctx.URL,'blob:draft-2');assert.ok(revoked.includes('blob:draft-1'));
});
test('a response arriving after close cannot restore the old draft', async () => {
    let resolve;
    const ctx = context(draft,{showDialog:true,digitalInvoice:true,preview:()=>new Promise(r=>resolve=r),$emit:()=>{}});
    const pending = ctx.openPreview();ctx.clickClose();resolve('blob:late');await pending;
    assert.equal(ctx.URL,null);assert.ok(revoked.includes('blob:late'));
});
test('draft failures show an error and release loading', async () => {
    const ctx = context(draft,{showDialog:true,digitalInvoice:true,preview:async()=>{throw Error('Error del PDF');}});
    await ctx.openPreview();assert.equal(ctx.URL,null);assert.equal(ctx.error,'Error del PDF');assert.equal(ctx.loading,false);
});
