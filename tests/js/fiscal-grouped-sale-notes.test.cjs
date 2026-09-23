const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
for (const name of ['invoice', 'invoice_generate']) {
    const source = fs.readFileSync(`resources/js/views/tenant/documents/${name}.vue`, 'utf8');
    const method = source.slice(source.indexOf('        getTotal() {'), source.indexOf('        setDescriptionOfItem', source.indexOf('        getTotal() {')));
    const context = { result: null, _: {isEmpty: () => true}, console: {log() {}} };
    vm.runInNewContext('result = ({' + method + '})', context);
    test(`${name}: grouped payment suggestion subtracts original receipts`, () => {
        assert.equal(context.result.getTotal.call({form: {total: 464, retention: {}, sale_notes_relateds: [{source_paid: '100.00'}, {source_paid: '100.00'}]}}), 264);
    });
    test(`${name}: ordinary invoice keeps its amount`, () => assert.equal(context.result.getTotal.call({form: {total: 232, retention: {}}}), 232));
    test(`${name}: fully paid sources never suggest a negative receipt`, () => assert.equal(context.result.getTotal.call({form: {total: 100, retention: {}, sale_notes_relateds: [{source_paid: 100}]}}), 0));
}
test('group selection template compiles', () => {
    const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/sale_notes/ModalGenerateCPE.vue', 'utf8'));
    assert.deepEqual(compiler.compile(component.template.content).errors, []);
});
for (const name of ['invoice', 'invoice_generate']) {
    const source = fs.readFileSync(`resources/js/views/tenant/documents/${name}.vue`, 'utf8');
    test(`${name}: source currency is established before preparing stored rows`, () => {
        const start = source.indexOf("        const sourceNotes = JSON.parse");
        const end = source.indexOf('        const itemsFromDispatches', start);
        const context = {form: {currency_type_id: 'VES', exchange_rate_sale: 1}, currency_types: [{id: 'USD', symbol: '$'}],
            loadSaleNoteEconomics() {}, localStorage: {getItem: key => key === 'notes' ? JSON.stringify([{currency_type_id: 'USD', exchange_rate_sale: '36.50'}]) : null, removeItem() {}},
            _: {find: (rows, filter) => rows.find(row => row.id === filter.id)}};
        vm.runInNewContext(source.slice(start, end), context);
        assert.equal(context.form.currency_type_id, 'USD');
        assert.equal(context.form.exchange_rate_sale, 36.5);
        assert.equal(context.currency_type.symbol, '$');
        assert.ok(end < source.indexOf('await this.processItemsForNotesNotGroup()'));
    });
}
test('group selection transports complete stored rows instead of catalog prices', async () => {
    const component = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/sale_notes/ModalGenerateCPE.vue', 'utf8'));
    const writes = {};
    let request;
    const rows = [{item_id: 1, unit_price: '116.00', total: '232.00'}, {item_id: 1, unit_price: '174.00', total: '348.00', discounts: [{amount: '5.00'}]}];
    const context = {module: {exports: {}}, localStorage: {setItem: (key, value) => {writes[key] = JSON.parse(value);}, removeItem() {}}, window: {location: {}}, console};
    vm.runInNewContext(component.script.content.replace('export default', 'module.exports ='), context);
    const methods = context.module.exports.methods;
    const ctx = {form: {selecteds: [1, 2], client_id: 1}, group_items_generate_document: true,
        notes: [{id: 1, selected: true}, {id: 2, selected: true}], clients: [{id: 1}], getObjectForNote: methods.getObjectForNote,
        $http: {post: (url, body) => {request = body; return Promise.resolve({data: {data: rows, economics: {source_ids: [1, 2], totals: {total: '580.00'}, discounts: [], charges: []}}});}}, onClose() {}};
    methods.onFetchNoteItems.call(ctx);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(request.group_items, true);
    assert.deepEqual(writes.itemsNotGroupForNotes, rows);
    assert.equal(writes.itemsForNotes, undefined);
});
