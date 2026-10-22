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
    const template = parsed.template ? parsed.template.content : '';
    if (template) assert.deepEqual(compiler.compile(template).errors, [], file);
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
    return { component: exports.default, template };
}
const list = load('resources/js/views/tenant/documents/index.vue');
const drawer = load('resources/js/views/tenant/documents/partials/detail-drawer.vue');
const options = load('resources/js/views/tenant/documents/partials/options.vue');
const noteAnnulmentAction = load('resources/js/views/tenant/documents/partials/note-annulment-action.vue');
const documentSendAction = load('resources/js/views/tenant/documents/partials/document-send-action.vue');
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
// ######## INICIO CONFLICTO NUMERACION NC HKA ########
test('numbering conflict is shown as an error in list, detail and PDF options without claiming rejection', () => {
    const diagnostic='HKA ya tiene registrada la nota de crédito 00000002 en otra operación, con control 00-00000712 y estado Anulada.';
    const record={id:9,number:'00000002',document_type_id:'07',fiscal_emission_mode:'digital',state_type_id:'01',
        fiscal_emission:result({status:'uncertain',description:'Conflicto de numeración',numbering_conflict:true,can_send:false,control_number:null,diagnostic})};
    const status=documentStatus(record);
    assert.equal(status.label,'Conflicto de numeración');assert.match(status.badgeClass,/danger/);
    assert.equal(status.rejected,false);assert.equal(status.numberingConflict,true);
    const notice=options.component.computed.fiscalEmissionNotice.call({form:record});
    assert.equal(notice.type,'error');assert.equal(notice.description,diagnostic);
    assert.doesNotMatch(notice.title,/rechazada|aceptada/);
    const start=drawer.template.indexOf('<el-alert v-if="record.fiscal_emission');
    const alert=drawer.template.slice(start,drawer.template.indexOf('/>',start)+2);
    const detailInstance=new Vue({data:()=>({record}),render:new Function(compiler.compile(alert).render)});
    const rendered=detailInstance._render();
    assert.equal(rendered.data.attrs.type,'error');assert.equal(rendered.data.attrs.title,diagnostic);
    const cell=list.template.match(/<td v-if="col.visible && col.key === 'state_type'"[\s\S]*?<\/td>/)[0];
    const compiled=compiler.compile('<table><tbody><tr>'+cell+'</tr></tbody></table>');
    const instance=new Vue({data:()=>({row:record,col:{key:'state_type',visible:true}}),methods:{documentStatus},render:new Function(compiled.render)});
    const text=node=>(node.text||'')+(node.children||[]).map(text).join('');
    assert.ok(text(instance._render()).includes(diagnostic));
    assert.doesNotMatch(compiled.render,/domProps/);
    assert.equal(documentStatus({...record,state_type_id:'11',state_type_description:'Anulado'}).numberingConflict,false);
});
// ######## FIN CONFLICTO NUMERACION NC HKA ########
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
    assert.doesNotMatch(options.template, /runFiscalAction\('send'\)|Enviar HKA/);
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
test('delivery orders keep one column and ignore retired guides preferences', () => {
    const defaults = list.component.data().columns;
    const columns = mergeDocumentColumns(defaults, {
        document_type: { visible: true, order: 4.5 },
        dispatch: { visible: true, order: 7 },
        guides: { visible: true, order: 17 },
    });
    assert.equal(columns.guides, undefined);
    assert.equal(columns.dispatch.visible, true);
    assert.equal(columns.dispatch.order, 7);
    assert.equal(Object.values(columns).filter(column => /[ÓO]rden(?:es)? de entrega/.test(column.title)).length, 1);
    assert.doesNotMatch(list.template, /col.key === 'guides'|row.guides/);
    assert.equal((list.template.match(/<th[^>]+col.key === 'dispatch'/g) || []).length, 1);
    assert.equal((list.template.match(/<td[^>]+col.key === 'dispatch'/g) || []).length, 1);
    const hidden = mergeDocumentColumns(defaults, {
        document_type: { visible: true, order: 4.5 },
        dispatch: { visible: false, order: 3 }, guides: { visible: true },
    });
    assert.equal(hidden.dispatch.visible, false);
    assert.equal(hidden.dispatch.order, 3);
    assert.equal(hidden.guides, undefined);
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
// ######## INICIO ACCESO DIRECTO DEVOLUCIONES NC ########
test('the actual refunds action opens its own dialog directly and preserves the selected note', () => {
    const action = list.template.match(/<el-dropdown-item v-if="row.btn_note_operations"[\s\S]*?<\/el-dropdown-item>/)[0];
    const dialog = list.template.match(/<note-operations\b[\s\S]*?<\/note-operations>/)[0];
    const instance = new Vue({
        data: () => ({...list.component.data(),row:{id:13,btn_note_operations:true}}),
        methods: list.component.methods,
        render: new Function(compiler.compile(action).render),
    });
    instance._render().data.nativeOn.click();
    assert.equal(instance.showDialogNoteOperations,true);
    assert.equal(instance.showDialogOptions,false);
    assert.equal(instance.recordId,null);
    const renderDialog = new Function(compiler.compile(dialog).render);
    let node = renderDialog.call(instance);
    assert.equal(node.data.attrs.documentId,13);
    assert.equal(node.data.attrs.showDialog,true);
    assert.equal(node.data.attrs['allow-retention-management'],true);
    node.data.on['update:showDialog'](false);
    assert.equal(instance.showDialogNoteOperations,false);
    instance.row.id=14;
    instance._render().data.nativeOn.click();
    node=renderDialog.call(instance);
    assert.equal(node.data.attrs.documentId,14);
    assert.equal(node.data.attrs.showDialog,true);
    assert.equal(instance.showDialogOptions,false);
    instance.row.btn_note_operations=false;
    assert.equal(instance._render().isComment,true);
});
test('PDF options no longer contain refunds or annul buttons', () => {
    assert.doesNotMatch(options.template,/Devoluciones y ajustes|<note-operations\b|showNoteOperations/);
    assert.equal(options.component.components.NoteOperations,undefined);
    assert.doesNotMatch(options.template,/>\s*Anular\s*<|@click="requestNoteAnnulment"/);
    assert.match(options.template,/clickDownload\('a4'\)|clickPrint\('a4'\)/);
});
// ######## FIN ACCESO DIRECTO DEVOLUCIONES NC ########
// ######## INICIO ANULACION DIRECTA DESDE LISTADO ########
test('the actual list annul action invokes annulment directly without opening PDF options', () => {
    const tag = list.template.match(/<el-dropdown-item v-if="row.fiscal_emission && row.fiscal_emission.annulment[^>]*>Anular<\/el-dropdown-item>/)[0];
    let id;
    const instance = new Vue({
        data: () => ({...list.component.data(),row:{id:13,fiscal_emission:{annulment:{available:true}}}}),
        methods: list.component.methods,
        render: new Function(compiler.compile(tag).render),
    });
    instance.$refs.noteAnnulmentAction={open:recordId=>{id=recordId;}};
    instance._render().data.nativeOn.click();
    assert.equal(id,13);
    assert.equal(instance.showDialogOptions,false);
    assert.equal(instance.recordId,null);
});

function directAnnulment(canRequest=true,post) {
    const ctx=context(noteAnnulmentAction.component,post);
    ctx.alerts=[];
    ctx.$alert=async message=>{ctx.alerts.push(message);};
    ctx.$prompt=async()=>({value:'Registro incorrecto'});
    ctx.$http.get=async url=>{
        ctx.gets.push(url);
        return {data:{data:{id:13,document_type_id:'07',fiscal_emission:result({annulment:{available:true,can_request:canRequest,
            diagnostic:canRequest ? '' : 'Para anular esta nota de crédito, primero regularice las devoluciones de dinero asociadas.'}})}}};
    };
    return ctx;
}
test('direct annulment requests a reason, posts once and refreshes the fiscal result',async()=>{
    const posts=[];
    const ctx=directAnnulment(true,async(url,body)=>{posts.push({url,body});return {data:{fiscal_emission:result({status:'cancelled',annulment:{status:'completed',diagnostic:'Nota anulada.'}})}};});
    await ctx.open(13);
    assert.equal(posts.length,1);
    assert.equal(posts[0].url,'/documents/13/annul');
    assert.equal(posts[0].body.reason,'Registro incorrecto');
    assert.ok(posts[0].body.operation_key);
    assert.equal(ctx.events[0][0],'fiscal-updated');
    assert.deepEqual(ctx.gets,['/documents/record/13','/documents/record/13']);
    assert.equal(ctx.active,false);
});
test('direct annulment displays the blocking reason and sends nothing for active refunds',async()=>{
    const ctx=directAnnulment(false,()=>assert.fail('Blocked annulment must not send'));
    ctx.$prompt=()=>assert.fail('Blocked annulment must not ask to confirm');
    await ctx.open(13);
    assert.match(ctx.alerts[0],/Para anular esta nota de crédito/);
    assert.equal(ctx.active,false);
});
test('cancelling direct annulment does not send and duplicate clicks show one prompt',async()=>{
    const pending=deferred();let prompts=0;
    const ctx=directAnnulment(true,()=>assert.fail('Cancellation must not send'));
    ctx.$prompt=()=>{prompts++;return pending.promise;};
    const first=ctx.open(13);
    await Promise.resolve();
    await ctx.open(13);
    assert.equal(prompts,1);
    pending.reject('cancel');
    await first;
    assert.equal(ctx.active,false);
});
test('direct annulment retains its request UUID after a lost response',async()=>{
    const bodies=[];
    const ctx=directAnnulment(true,async(url,body)=>{bodies.push(body);throw new Error('Lost response');});
    let prompts=0;ctx.$prompt=async()=>{prompts++;return {value:'Registro incorrecto'};};
    await ctx.open(13);
    await ctx.open(13);
    assert.equal(prompts,1);
    assert.equal(bodies.length,2);
    assert.equal(bodies[0].operation_key,bodies[1].operation_key);
    assert.equal(ctx.fiscalActionBusy,false);
});
// ######## FIN ANULACION DIRECTA DESDE LISTADO ########
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

test('actual options render fiscal notices without a send action for invoices and notes', () => {
    const start=options.template.indexOf('<div v-if="fiscalEmissionNotice"');
    const end=options.template.indexOf('<div v-else-if="form.response_message"',start);
    const compiled=compiler.compile('<div>'+options.template.slice(start,end)+'</div>');
    assert.deepEqual(compiled.errors,[]);
    for(const type of ['01','07','08']) for(const canSend of [true,false]) {
        const form={id:4,document_type_id:type,number:'00000004',fiscal_emission_mode:'digital',fiscal_emission:{status:'not_requested',show_send:true,can_send:canSend,diagnostic:'Pendiente'}};
        const instance=new Vue({data:()=>({form,loading:false,fiscalActionBusy:false}),computed:{fiscalEmissionNotice:options.component.computed.fiscalEmissionNotice},methods:{runFiscalAction(){}},render:new Function(compiled.render)});
        const tree=instance._render();const nodes=[];
        (function walk(node){nodes.push(node);for(const child of node.children||[])walk(child);})(tree);
        assert.equal(nodes.some(node=>node.tag==='el-button'),false);
        assert.match(instance.fiscalEmissionNotice.title,{'01':/Factura/,'07':/Nota de crédito/,'08':/Nota de débito/}[type]);
    }
});
// ######## INICIO ENVIO HKA DESDE ACCIONES DEL LISTADO ########
test('send document is the first list action when applicable and respects availability and busy state', () => {
    const opening='<el-dropdown-menu slot="dropdown">';
    const marker=list.template.indexOf('<!-- ######## INICIO ENVIO HKA DESDE ACCIONES DEL LISTADO ######## -->');
    const start=list.template.lastIndexOf(opening,marker)+opening.length;
    const end=list.template.indexOf('<el-dropdown-item divided',start);
    const compiled=compiler.compile('<div>'+list.template.slice(start,end)+'</div>');
    assert.deepEqual(compiled.errors,[]);
    const text=node=>(node.text||'')+(node.children||[]).map(text).join('');
    for(const documentType of ['01','07','08'])for(const [showSend,canSend] of [[true,true],[true,false],[false,false]]){
        let selected;
        const instance=new Vue({data:()=>({row:{id:13,document_type_id:documentType,fiscal_emission:{show_send:showSend,can_send:canSend}},sendingDocumentId:null}),
            methods:{clickSendDocument:id=>{selected=id;},clickDetail(){}},render:new Function(compiled.render)});
        let items=instance._render().children.filter(node=>node.tag);
        assert.match(text(items[0]),showSend?/Enviar comprobante/:/Ver detalle/);
        if(showSend){
            assert.equal(items[0].data.attrs.disabled,!canSend);
            if(canSend){items[0].data.nativeOn.click();assert.equal(selected,13);}
            instance.sendingDocumentId=13;
            items=instance._render().children.filter(node=>node.tag);
            assert.equal(items[0].data.attrs.disabled,true);
        }
    }
});
test('clicking send from the list bypasses PDF options and prevents overlapping sends',async()=>{
    const pending=deferred();const ids=[];
    const ctx=context(list.component);
    ctx.$refs={documentSendAction:{open:id=>{ids.push(id);return pending.promise;}}};
    ctx.showDialogOptions=false;
    const first=ctx.clickSendDocument(13);await ctx.clickSendDocument(14);
    assert.deepEqual(ids,[13]);assert.equal(ctx.showDialogOptions,false);assert.equal(ctx.sendingDocumentId,13);
    pending.resolve();await first;assert.equal(ctx.sendingDocumentId,null);
});
function directSend(canSend,post) {
    const ctx=context(documentSendAction.component,post);
    ctx.$message.warning=m=>ctx.messages.push(m);
    ctx.$http.get=async url=>{ctx.gets.push(url);return {data:{data:{id:13,fiscal_emission:result({status:'prepared',can_send:canSend,diagnostic:'Envío bloqueado.'})}}};};
    return ctx;
}
test('direct send reloads current permissions, posts to HKA and refreshes the list through its event',async()=>{
    const posts=[];
    const ctx=directSend(true,async(url,body)=>{posts.push({url,body});return {data:{fiscal_emission:result()}};});
    await ctx.open(13);
    assert.equal(posts.length,1);assert.equal(posts[0].url,'/documents/13/send-hka');
    assert.deepEqual(ctx.gets,['/documents/record/13','/documents/record/13']);
    assert.equal(ctx.events[0][0],'fiscal-updated');assert.equal(ctx.active,false);
});
test('direct send refuses a document whose refreshed capability no longer allows sending',async()=>{
    const ctx=directSend(false,()=>assert.fail('Blocked document must not send'));
    await ctx.open(13);
    assert.deepEqual(ctx.messages,['Envío bloqueado.']);assert.equal(ctx.active,false);
});
test('credit invoices display their contract block and never post when capability is disabled',async()=>{
    const ctx=directSend(false,()=>assert.fail('Unverified credit contract must not send'));
    const diagnostic='La emisión de facturas a crédito sin abono espera la validación de su contrato en HKA DEMO.';
    ctx.$http.get=async()=>({data:{data:{id:13,document_type_id:'01',payment_condition_id:'02',balance:'500.00',
        fiscal_emission:result({status:'not_requested',show_send:true,can_send:false,diagnostic})}}});
    await ctx.open(13);
    assert.deepEqual(ctx.messages,[diagnostic]);assert.equal(ctx.events.length,0);
});
test('confirmed credit invoices retain their unpaid balance after the fiscal refresh',async()=>{
    const ctx=directSend(true,async()=>({data:{fiscal_emission:result()}}));
    ctx.$http.get=async()=>({data:{data:{id:13,document_type_id:'01',payment_condition_id:'02',balance:'500.00',
        fiscal_emission:result({can_send:true})}}});
    await ctx.open(13);
    assert.equal(ctx.record.balance,'500.00');assert.equal(ctx.record.payment_condition_id,'02');
    assert.equal(ctx.events[0][0],'fiscal-updated');
});
test('direct send ignores duplicate clicks and a response after destroying the action',async()=>{
    const pending=deferred();let sends=0;
    const ctx=directSend(true,()=>{sends++;return pending.promise;});
    const first=ctx.open(13);await Promise.resolve();await ctx.open(13);
    assert.equal(sends,1);
    documentSendAction.component.beforeDestroy.call(ctx);
    pending.resolve({data:{fiscal_emission:result()}});await first;
    assert.equal(ctx.events.length,0);assert.equal(ctx.active,false);
});
test('direct send releases the action after a network failure',async()=>{
    const ctx=directSend(true,async()=>{throw new Error('network');});
    await ctx.open(13);
    assert.equal(ctx.active,false);assert.equal(ctx.fiscalActionBusy,false);assert.equal(ctx.messages.length,1);
});
// ######## FIN ENVIO HKA DESDE ACCIONES DEL LISTADO ########
test('annulment requests preserve their UUID and reason after a lost response', async () => {
    const ctx=context(options.component,async()=>{throw new Error('lost');},{document_type_id:'07',fiscal_emission:result({annulment:{available:true,can_request:true}})});
    ctx.annulmentDocumentId=13;ctx.annulmentRequest={operation_key:'stable-note-annulment',reason:'Registro incorrecto'};
    const posted=[];ctx.$http.post=async(url,body)=>{posted.push({url,body});throw new Error('lost');};
    await ctx.requestNoteAnnulment();await ctx.requestNoteAnnulment();
    assert.equal(posted.length,2);assert.equal(posted[0].url,'/documents/13/annul');assert.deepEqual(posted[0].body,posted[1].body);
});
test('annulment reconciliation updates the note and discards a late result for another note', async () => {
    const pending=deferred();const ctx=context(options.component,()=>pending.promise,{document_type_id:'08',fiscal_emission:result({annulment:{can_query:true}})});
    const previous=ctx.form.fiscal_emission;const request=ctx.runFiscalAction('query-annulment');ctx.recordId=14;ctx.resetFiscalAction();
    pending.resolve({data:{fiscal_emission:result({status:'cancelled',annulment:{status:'completed'}})}});await request;
    assert.equal(ctx.events.length,0);assert.equal(ctx.form.fiscal_emission,previous);
});
test('double click while the annulment confirmation is open shows one prompt and sends once',async()=>{
    const prompt=deferred();let prompts=0,posts=0;
    const ctx=context(options.component,async()=>{posts++;return {data:{fiscal_emission:result({status:'cancelled',annulment:{status:'completed'}})}};},{document_type_id:'07',fiscal_emission:result({annulment:{can_request:true}})});
    ctx.$prompt=()=>{prompts++;return prompt.promise;};
    const first=ctx.requestNoteAnnulment();await ctx.requestNoteAnnulment();assert.equal(prompts,1);
    prompt.resolve({value:'Documento incorrecto'});await first;assert.equal(posts,1);assert.equal(ctx.annulmentPromptBusy,false);
});
