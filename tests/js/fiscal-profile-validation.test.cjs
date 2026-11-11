const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const compiler = require('vue-template-compiler');
const Vue = require('vue');
const parsed = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/fiscal_profiles/editor.vue', 'utf8'));
const component = new Function(parsed.script.content.replace('export default', 'return'))();

function editor(profile = {}) {
    const state = Object.assign(component.data(), {kind:'company', entityId:1, loaded:true, canEdit:true});
    state.profile = Object.assign({rif:'J-12345678-9', nature:'legal', personal_firm:false, public_kind:null,
        commercial_activity:true, iva_status:'ordinary', spe:false, effective_from:'2026-11-07',
        effective_until:null, revoked_at:null, notification_reference:null, notification_date:null,
        iva_agent_basis:'none', islr_agent:false, islr_basis:null, iva_retention_rate:75,
        iva_100_basis:null, iva_exclusion:null, evidence_reference:null}, profile);
    for (const [key, method] of Object.entries(component.methods)) state[key] = method.bind(state);
    state.$nextTick = callback => callback();
    state.$el = {querySelector: () => null};
    return state;
}

test('saving reports all incorrect fields without posting or changing fiscal facts', async () => {
    const state = editor({rif:'J12345678-9', iva_agent_basis:'SNAT/2025/000054', islr_agent:true});
    const before = JSON.stringify(state.profile);
    state.$http = {post: () => assert.fail('invalid profile must not be posted')};
    await state.save();
    assert.deepEqual(Object.keys(state.fieldErrors).sort(), ['rif','iva_agent_basis','islr_basis'].sort());
    assert.match(state.fieldErrors.rif, /J-12345678-9/);
    assert.match(state.fieldErrors.iva_agent_basis, /no está marcada la condición SPE/);
    assert.match(state.fieldErrors.islr_basis, /desmarque/);
    assert.match(state.error, /3 campos/);
    assert.deepEqual(state.openPanels, ['fiscal']);
    assert.equal(JSON.stringify(state.profile), before);
});

test('conditional SPE, ISLR and 100 percent evidence errors are attributed separately', () => {
    const state = editor({spe:true, islr_agent:true, iva_retention_rate:100});
    assert.equal(state.validateProfile(), false);
    for (const key of ['notification_reference','notification_date','islr_basis','iva_100_basis','evidence_reference']) assert.ok(state.fieldErrors[key], key);
    state.profile.notification_date = '2026-12-01';
    state.validateProfile();
    assert.match(state.fieldErrors.notification_date, /posterior/);
});

test('dates, whitespace and text lengths receive understandable field errors', () => {
    const state = editor({effective_until:'2026-11-06', revoked_at:'2026-02-30', spe:true,
        notification_reference:'   ', notification_date:'2026-10-01', iva_exclusion:'x'.repeat(256)});
    assert.equal(state.validateProfile(), false);
    assert.match(state.fieldErrors.notification_reference, /referencia/);
    assert.match(state.fieldErrors.effective_until, /igual o posterior/);
    assert.match(state.fieldErrors.revoked_at, /fecha válida/);
    assert.match(state.fieldErrors.iva_exclusion, /255/);
});

test('server error response formats retain field mapping and update after correction', async () => {
    for (const body of [
        {rif:['El RIF no coincide con el registrado en la empresa.']},
        {message:{rif:['El RIF no coincide con el registrado en la empresa.']}},
        {errors:{rif:['El RIF no coincide con el registrado en la empresa.']}, message:'Invalid data'},
    ]) {
        const state = editor();
        state.$http = {post:async () => {throw {response:{status:422, data:body}}}};
        await state.save();
        assert.match(state.fieldErrors.rif, /no coincide/);
        state.profile.islr_agent = true;
        state.onFieldChange('islr_agent');
        assert.match(state.fieldErrors.rif, /no coincide/);
        assert.ok(state.fieldErrors.islr_basis);
        state.profile.rif = 'J-87654321-0';
        state.onFieldChange('rif');
        assert.equal(state.fieldErrors.rif, undefined);
        assert.ok(state.fieldErrors.islr_basis);
    }
});

test('correct profiles submit while general failures remain visible', async () => {
    const state = editor();
    let calls = 0;
    state.$http = {post:async () => {calls++; throw Error('network')}};
    await state.save();
    assert.equal(calls, 1);
    assert.deepEqual(state.fieldErrors, {});
    assert.match(state.error, /conexión/);
    assert.equal(state.saving, false);
});

test('rendered conditional controls include the 100 percent error and highlighted field', () => {
    const compiled = compiler.compile(parsed.template.content);
    assert.deepEqual(compiled.errors, []);
    const render = compiler.compileToFunctions(parsed.template.content);
    const state = editor({iva_retention_rate:100});
    state.validateProfile();
    const view = new Vue({data: () => state, render:render.render, staticRenderFns:render.staticRenderFns});
    const nodes = [];
    function visit(node) { if (!node) return; nodes.push(node); (node.children || []).forEach(visit); }
    visit(view._render());
    const error = nodes.find(node => node.data?.attrs?.id === 'fiscal-error-iva_100_basis');
    assert.ok(error, 'the error must be rendered under the conditional field');
    assert.match(error.children.map(node => node.text || '').join(''), /Seleccione la causal/);
    const group = nodes.find(node => node.data?.attrs?.['data-fiscal-field'] === 'iva_100_basis');
    assert.equal(group.data.class['fiscal-invalid'], state.fieldErrors.iva_100_basis);
});
