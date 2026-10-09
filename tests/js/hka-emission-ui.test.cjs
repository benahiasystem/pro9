const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');
const Vue = require('vue');
const loadModule = require('./helpers/load-module.cjs');
const { documentStatus } = loadModule(path.resolve('resources/js/helpers/document-status.js'));
const { mergeDocumentColumns } = loadModule(path.resolve('resources/js/helpers/document-list-columns.js'));

function load(file) {
    const parsed = compiler.parseComponent(fs.readFileSync(file, 'utf8'));
    assert.deepEqual(compiler.compile(parsed.template.content).errors, [], file);
    const exports = {};
    vm.runInNewContext(babel.transformSync(parsed.script.content, {
        configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']
    }).code, {
        exports, Uint8Array,
        window: { open: () => assert.fail('Unexpected dispatch window') },
        require: name => {
            if (name.includes('vuex')) return { mapActions: () => ({}), mapState: () => ({}) };
            if (name.startsWith('@mixins/') || name.startsWith('@helpers/')) {
                return loadModule(path.resolve('resources/js/' + name.slice(1) + '.js'));
            }
            if (name.includes('/helpers/') || name.includes('/mixins/document-fiscal')) {
                return loadModule(path.resolve(path.dirname(file), name + '.js'));
            }
            return name === 'moment' ? require('moment') : {};
        },
    });
    return { component: exports.default, template: parsed.template.content };
}
const list = load('resources/js/views/tenant/documents/index.vue');
const drawer = load('resources/js/views/tenant/documents/partials/detail-drawer.vue');
const options = load('resources/js/views/tenant/documents/partials/options.vue');
const system = load('resources/js/views/system/configuration/visibleColumns.vue');
const result = (extra = {}) => ({ status: 'confirmed', description: 'Confirmado', control_number: '00-00000002', can_query: true, ...extra });
function context(component, post, record = {}) {
    const messages = [], events = [], gets = [];
    const document = {
        id: 13, document_type_id: '01', document_type_description: 'Factura', fiscal_emission_mode: 'digital',
        state_type_id: '01', state_type_description: 'Registrado localmente',
        fiscal_emission: result({ status: 'uncertain', description: 'Por conciliar', can_send: true }),
        ...record,
    };
    const ctx = {
        ...component.data(), recordId: document.id, showDrawer: true, showDialog: true,
        record: document, form: document, initialRow: document, resource: 'documents',
        messages, events, gets,
        $set: (object, key, value) => { object[key] = value; },
        $emit: (...args) => events.push(args),
        $message: { info: m => messages.push(m), error: m => messages.push(m) },
        $http: { post, get: async url => { gets.push(url); return { data: { data: { ...document, items: [{}], fiscal_emission: result() } } }; } },
    };
    for (const [name, method] of Object.entries(component.methods || {})) ctx[name] = method.bind(ctx);
    for (const [name, getter] of Object.entries(component.computed || {})) {
        if (typeof getter === 'function') Object.defineProperty(ctx, name, { get: () => getter.call(ctx) });
    }
    ctx.newEmailRequestId = () => 'test-request';
    return ctx;
}
function deferred() {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}
function ordered(columns) {
    return Object.keys(columns).sort((a, b) => columns[a].order - columns[b].order);
}

test('fiscal results share the same label and color in the list and detail', () => {
    for (const [status, description, color] of [
        ['confirmed', 'Confirmado', 'success'], ['rejected', 'Rechazado', 'danger'],
        ['pending', 'Enviando', 'warning'], ['uncertain', 'Por conciliar', 'warning'],
        ['prepared', 'Preparado', 'secondary'], ['not_requested', 'Sin solicitar', 'secondary'],
        ['cancelled', 'Cancelado', 'dark'],
    ]) {
        const record = { state_type_id: '01', state_type_description: 'Registrado', fiscal_emission: result({ status, description }) };
        const display = list.component.methods.documentStatus(record);
        assert.equal(display.label, description);
        assert.match(display.badgeClass, new RegExp('bg-' + color));
        assert.equal(drawer.component.computed.stateLabel.call({ record }), display.label);
        assert.equal(drawer.component.computed.stateBadgeClass.call({ record }), display.badgeClass);
    }
});
test('commercial rejection and annulment retain priority; non-HKA documents use local state', () => {
    for (const [id, label] of [['09', 'Rechazado'], ['11', 'Anulado'], ['13', 'Por anular']]) {
        const display = documentStatus({ state_type_id: id, state_type_description: label, fiscal_emission: result() });
        assert.equal(display.label, label);
        assert.equal(display.rejected, id === '09');
    }
    assert.equal(documentStatus({ state_type_description: 'Registrado', fiscal_emission: { status: null, description: 'No aplica' } }).label, 'Registrado');
    assert.equal(documentStatus().controlNumber, 'Sin asignar');
    assert.equal(documentStatus({ fiscal_emission: result() }).controlNumber, '00-00000002');
});
test('the actual status popup shows the reason only on rejection and handles missing information', () => {
    const cell = list.template.match(/<td v-if="col.visible && col.key === 'state_type'"[\s\S]*?<\/td>/)[0];
    const code = compiler.compile('<table><tbody><tr>' + cell + '</tr></tbody></table>');
    const render = new Function(code.render);
    function text(node) {
        return (node.text || '') + (node.children || []).map(text).join('');
    }
    for (const [status, diagnostic, control] of [
        ['confirmed', 'Diagnóstico anterior', '00-00000002'],
        ['rejected', '<b>Rango no asignado</b>', '00-00000002'],
        ['rejected', null, null],
    ]) {
        const instance = new Vue({
            data: () => ({ col: { key: 'state_type', visible: true }, row: { state_type_id: '01', fiscal_emission: result({
                status, description: status === 'rejected' ? 'Rechazado' : 'Confirmado', diagnostic, control_number: control,
            }) } }),
            methods: { documentStatus }, render,
        });
        const rendered = text(instance._render());
        assert.ok(rendered.includes('N° de control: ' + (control || 'Sin asignar')));
        assert.equal(rendered.includes('Estado:'), status !== 'rejected');
        assert.equal(rendered.includes('Motivo de rechazo:'), status === 'rejected');
        if (status === 'rejected') {
            assert.ok(rendered.includes(diagnostic || 'Motivo de rechazo no disponible'));
            assert.doesNotMatch(code.render, /domProps/); // Diagnostic stays a text node, never HTML.
        } else {
            assert.doesNotMatch(rendered, /Diagnóstico anterior/);
        }
    }
});
test('local rejection does not reuse an unrelated fiscal diagnostic', () => {
    const display = documentStatus({ state_type_id: '09', fiscal_emission: result({ diagnostic: 'Consulta anterior' }) });
    assert.equal(display.rejected, true);
    assert.equal(display.rejectionReason, 'Motivo de rechazo no disponible');
});
test('list retains mobile PDF and edit permissions without a download column or blocked-edit notice', () => {
    assert.doesNotMatch(list.template, /col.key === 'downloads'|Edición bloqueada|row.edit_block_reason/);
    assert.match(list.template, /class="mobile-item-only"[\s\S]*clickDownload\(row.download_pdf\)[\s\S]*Descargar PDF/);
    assert.match(list.template, /userPermissionEditCpe && row.can_edit/);
    assert.match(list.template, /userId == row.user_id && row.can_edit/);
    const merged = mergeDocumentColumns({ ...list.component.data().columns, downloads: { title: 'Descargas PDF' } }, {});
    assert.equal(merged.downloads, undefined);
});
test('type is visible after customer; HKA actions and the old column leave the table', () => {
    const columns = list.component.data().columns;
    assert.equal(columns.document_type.visible, true);
    const order = ordered(columns);
    assert.equal(order[order.indexOf('customer') + 1], 'document_type');
    assert.equal(columns.hka_status, undefined);
    assert.equal(columns.downloads, undefined);
    assert.doesNotMatch(list.template, /Estado HKA|Consultar HKA|Enviar HKA|hka_status/);
    assert.match(list.template, /row.document_type_description/);
    assert.match(drawer.template, /can_query[\s\S]*runFiscalAction\('query'\)[\s\S]*Consultar comprobante/);
    assert.ok(drawer.template.indexOf("runFiscalAction('query')") < drawer.template.indexOf('clickDownload(record.download_pdf)'));
    assert.match(drawer.template, /icon-tabler-search/);
    assert.match(options.template, /can_send[\s\S]*runFiscalAction\('send'\).*Enviar HKA/);
});
test('old preferences keep visibility, custom fields and relative order while inserting type after customer', () => {
    const defaults = list.component.data().columns;
    const saved = {
        number: { visible: false, order: 0 }, customer: { visible: true, order: 20 },
        state_type: { visible: false, order: 21 }, downloads: { visible: true, order: 29 }, hka_status: { visible: true, order: 20.5 },
        personalized: { visible: true, order: 22, fields: { reference: false } },
    };
    const columns = mergeDocumentColumns(defaults, saved);
    assert.equal(columns.hka_status, undefined);
    assert.equal(columns.downloads, undefined);
    assert.equal(columns.number.visible, false);
    assert.equal(columns.state_type.visible, false);
    assert.equal(columns.personalized.fields.reference, false);
    const order = ordered(columns);
    assert.equal(order[order.indexOf('customer') + 1], 'document_type');
    assert.ok(order.indexOf('number') < order.indexOf('customer'));
    assert.ok(order.indexOf('customer') < order.indexOf('state_type'));
    const reordered = mergeDocumentColumns(defaults, { ...saved, document_type: { visible: false, order: -1 } });
    assert.equal(reordered.document_type.visible, false);
    assert.equal(ordered(reordered)[0], 'document_type');
});
test('Total stays visible despite old preferences, saves selected and cannot be removed by the superadmin', async () => {
    const ctx = context(list.component);
    assert.equal(ctx.columns.total.visible, true);
    ctx.applyColumnPreferences({ total: { visible: false, order: 26 }, number: { visible: false, order: 5 } });
    assert.equal(ctx.columns.total.visible, true);
    assert.equal(ctx.columns.number.visible, false);
    ctx.$http.post = async (url, payload) => {
        assert.equal(payload.columns.total.visible, true);
        return { data: { columns: payload.columns } };
    };
    await ctx.getColumnsToShow(1);
    assert.match(list.template, /:disabled="col.key === 'total'"/);
    const admin = context(system.component);
    admin.savedConfigs = { document_index: { columns: { total: { visible: false, order: 26 } } } };
    admin.openDialog('document_index');
    assert.equal(admin.isActive('total'), true);
    admin.toggleColumn('total');
    admin.deactivateColumn('total');
    assert.equal(admin.isActive('total'), true);
    admin.resetToDefault();
    assert.equal(admin.isActive('total'), true);
    admin.openDialog('sale_notes_index');
    admin.toggleColumn('total');
    assert.equal(admin.isActive('total'), false);
});
test('saving and reloading type visibility uses the existing report configuration', async () => {
    const ctx = context(list.component);
    ctx.$http.post = async (url, payload) => {
        assert.equal(url, '/validate_columns');
        assert.equal(payload.report, 'document_index');
        assert.equal(payload.updated, true);
        assert.equal(payload.columns.document_type.visible, false);
        assert.equal(payload.columns.hka_status, undefined);
        assert.equal(payload.columns.downloads, undefined);
        return { data: { columns: payload.columns } };
    };
    ctx.columns.document_type.visible = false;
    await ctx.getColumnsToShow(1);
    ctx.applyColumnPreferences({ document_type: { visible: false, order: 4.5 } });
    assert.equal(ctx.columns.document_type.visible, false);
});
test('superadmin exposes type and inserts it after a reordered customer in saved configurations', () => {
    const ctx = context(system.component);
    ctx.savedConfigs = { document_index: { columns: {
        customer: { visible: false, order: 40 }, number: { visible: true, order: 41 },
        hka_status: { visible: true, order: 40.5 }, downloads: { visible: true, order: 29 },
    } } };
    ctx.openDialog('document_index');
    const keys = ctx.allColumns.map(col => col.key);
    assert.equal(keys[keys.indexOf('customer') + 1], 'document_type');
    assert.equal(ctx.allColumns.find(col => col.key === 'document_type').visible, true);
    assert.equal(ctx.allColumns.find(col => col.key === 'customer').visible, false);
    assert.ok(!keys.includes('hka_status'));
    assert.ok(!keys.includes('downloads'));
    ctx.toggleColumn('document_type');
    assert.equal(ctx.isActive('document_type'), false);
});
for (const [name, component, action] of [['detail', drawer.component, 'query'], ['options', options.component, 'send']]) {
    test(name + ' action prevents double clicks, refreshes the record and publishes the fiscal update', async () => {
        const pending = deferred();
        let calls = 0;
        const ctx = context(component, url => {
            calls++;
            assert.equal(url, '/documents/13/' + action + '-hka');
            return pending.promise;
        });
        const first = ctx.runFiscalAction(action);
        await ctx.runFiscalAction(action);
        assert.equal(calls, 1);
        pending.resolve({ data: { fiscal_emission: result() } });
        await first;
        assert.equal(ctx.fiscalRecord.fiscal_emission.control_number, '00-00000002');
        assert.deepEqual(ctx.gets, ['/documents/record/13']);
        assert.equal(ctx.events[0][0], 'fiscal-updated');
        assert.equal(ctx.events[0][1].id, 13);
        assert.equal(ctx.fiscalActionBusy, false);
    });
    test(name + ' refuses unavailable actions, loading, hidden windows and mismatched document IDs', async () => {
        let calls = 0;
        const ctx = context(component, async () => { calls++; });
        ctx.fiscalRecord.fiscal_emission[action === 'query' ? 'can_query' : 'can_send'] = false;
        await ctx.runFiscalAction(action);
        ctx.fiscalRecord.fiscal_emission[action === 'query' ? 'can_query' : 'can_send'] = true;
        ctx.loading = true; await ctx.runFiscalAction(action);
        ctx.loading = false; ctx.showDialog = ctx.showDrawer = false; await ctx.runFiscalAction(action);
        ctx.showDialog = ctx.showDrawer = true; ctx.recordId = 99; await ctx.runFiscalAction(action);
        assert.equal(calls, 0);
    });
    test(name + ' failures preserve the saved document and allow another explicit attempt', async () => {
        const ctx = context(component, async () => { throw Error('network'); });
        const previous = ctx.fiscalRecord.fiscal_emission;
        await ctx.runFiscalAction(action);
        assert.equal(ctx.fiscalRecord.fiscal_emission, previous);
        assert.equal(ctx.fiscalActionBusy, false);
        assert.match(ctx.messages[0], /venta está guardada/);
        assert.deepEqual(ctx.events, []);
    });
    test(name + ' ignores a response after closing and reopening the same document', async () => {
        const pending = deferred();
        const ctx = context(component, () => pending.promise);
        const first = ctx.runFiscalAction(action);
        component.watch[name === 'detail' ? 'showDrawer' : 'showDialog'].call(ctx, false);
        const previous = ctx.fiscalRecord.fiscal_emission;
        pending.resolve({ data: { fiscal_emission: result() } });
        await first;
        assert.equal(ctx.fiscalRecord.fiscal_emission, previous);
        assert.equal(ctx.messages.length, 0);
        assert.equal(ctx.events.length, 0);
        assert.equal(ctx.gets.length, 0);
    });
    test(name + ' ignores a late error after switching documents', async () => {
        const pending = deferred();
        const ctx = context(component, () => pending.promise);
        const first = ctx.runFiscalAction(action);
        ctx.recordId = 14;
        pending.reject(Error('late error'));
        await first;
        assert.equal(ctx.messages.length, 0);
        assert.equal(ctx.events.length, 0);
    });
}
test('record refresh discards an obsolete detail response after an awaited item fallback', async () => {
    const ctx = context(drawer.component);
    const fallback = deferred();
    ctx.$http.get = async () => ({ data: { data: { id: 13, items: [] } } });
    ctx.fetchDocumentItemsFallback = () => fallback.promise;
    const first = ctx.loadRecord();
    await Promise.resolve();
    ctx.recordId = 14;
    ctx.record = { id: 14 };
    fallback.resolve([{ description: 'Old item' }]);
    await first;
    assert.equal(ctx.record.id, 14);
});
test('options refresh cannot restore a previous window session or reopen dispatch generation', async () => {
    const ctx = context(options.component);
    const pending = deferred();
    ctx.$http.get = () => pending.promise;
    const first = ctx.getRecord();
    ctx.resetFiscalAction();
    ctx.form = { id: 13, number: 'Current session' };
    pending.resolve({ data: { data: { id: 13, number: 'Old session' } } });
    await first;
    assert.equal(ctx.form.number, 'Current session');
    ctx.$http.get = async () => ({ data: { data: { id: 13, number: 'Fresh', fiscal_emission: result() } } });
    ctx.generatDispatch = true;
    await ctx.refreshFiscalDocument();
    assert.equal(ctx.form.number, 'Fresh');
});
test('fiscal events refresh the list without applying another document result to the detail snapshot', () => {
    const ctx = context(list.component);
    const events = [];
    ctx.detailInitialRow = { id: 13 };
    ctx.$eventHub = { $emit: event => events.push(event) };
    ctx.onFiscalUpdated({ id: 13, fiscal_emission: result() });
    assert.equal(ctx.detailInitialRow.fiscal_emission.status, 'confirmed');
    ctx.onFiscalUpdated({ id: 14, fiscal_emission: result({ status: 'rejected' }) });
    assert.equal(ctx.detailInitialRow.fiscal_emission.status, 'confirmed');
    assert.deepEqual(events, ['reloadData', 'reloadData']);
});
