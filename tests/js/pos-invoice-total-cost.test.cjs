const {test} = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const load = require('./helpers/load-module.cjs');

const mixin = load(path.resolve('resources/js/mixins/pos-local-quantity.js')).default;
const {calculateRowItem} = load(path.resolve('resources/js/helpers/functions.js'));
const {exactValue} = load(path.resolve('resources/js/helpers/exchange-rate-math.js'));

function editContext({
    quantity, cost, exactCost, price, catalogCurrency = 'VES',
    rate = '40', affectation = '20', calculateQuantity = false, type = '01',
}) {
    const currency = 'VES';
    const row = calculateRowItem({
        item: {id: 1, currency_type_id: catalogCurrency, unit_price: price, aux_quantity: quantity,
            purchase_unit_price: cost, has_igv: true, calculate_quantity: calculateQuantity,
            calculation_snapshot: exactCost ? {values: {purchase_unit_price: exactCost}} : undefined},
        item_id: 1, unit_type_id: 'KGM', quantity, affectation_igv_type_id: affectation,
        price_input: {amount: price, currency_type_id: currency, includes_iva: true},
    }, currency, rate, '0.16');
    const edits = [];
    const ctx = {
        ...mixin.data(), ...mixin.methods,
        form: {document_type_id: type, currency_type_id: currency, exchange_rate_sale: rate, items: [row]},
        percentage_igv: '0.16', config: {condition_sale_purchase_price_to_item: true},
        $set(target, key, value) { target[key] = value; },
        calculateTotal() { edits.push('total'); },
        setFormPosLocalStorage() { edits.push('save'); },
        validatePosStockChange() { edits.push('stock'); return true; },
    };
    const edit = amount => {
        ctx.form.items[0].total = amount;
        ctx.updateLocalPosTotal(0);
        return ctx.form.items[0];
    };
    return {ctx, row, edit, edits};
}

test('editing the invoice total enforces cost per unit without replacing an invalid draft', () => {
    const {ctx, row, edit, edits} = editContext({quantity:'100', cost:'0.5', price:'1'});
    const originalInput = row.price_input;
    assert.equal(edit('1'), row);
    assert.equal(row.total, '1');
    assert.equal(row.price_input, originalInput);
    assert.equal(exactValue(row, 'total').compare('100'), 0);
    assert.match(ctx.localPosStockChecks[0].inputError, /costo de compra/);
    assert.deepEqual(edits, []);
    const valid = edit('50');
    assert.equal(ctx.localPosStockChecks[0].inputError, '');
    assert.equal(exactValue(valid, 'unit_price').compare('0.5'), 0);
    assert.deepEqual(edits, ['total', 'save']);

    const small = editContext({quantity:'0.1', cost:'10', price:'12'});
    assert.equal(exactValue(small.edit('1'), 'unit_price').compare('10'), 0);
    assert.equal(small.ctx.localPosStockChecks[0].inputError, '');
});

test('invoice total edits use the exact catalog cost in the document currency', () => {
    for (const data of [
        {quantity:'1', cost:'1.5', price:'80', catalogCurrency:'USD', rejected:'59.999999999999', accepted:'60', affectation:'10'},
        {quantity:'1', cost:'9007199254740992', price:'9007199254740994',
            exactCost:{n:'900719925474099301',d:'100'}, rejected:'9007199254740993', accepted:'9007199254740993.01'},
    ]) {
        const {ctx, row, edit, edits} = editContext(data);
        assert.equal(edit(data.rejected), row);
        assert.match(ctx.localPosStockChecks[0].inputError, /costo de compra/);
        assert.deepEqual(edits, []);
        assert.equal(exactValue(edit(data.accepted), 'unit_price').compare(data.accepted), 0);
        assert.equal(ctx.localPosStockChecks[0].inputError, '');
    }
});

test('invoice total-derived quantity retains its current unit price for the cost check', () => {
    const {ctx, edit, edits} = editContext({quantity:'1', cost:'6', price:'6', calculateQuantity:true});
    const updated = edit('1');
    assert.equal(exactValue(updated, 'quantity').compare({n:'1',d:'6'}), 0);
    assert.equal(exactValue(updated, 'unit_price').compare('6'), 0);
    assert.deepEqual(edits, ['stock', 'total', 'save']);
    assert.equal(ctx.localPosStockChecks[0].inputError, '');

    const expensive = editContext({quantity:'1', cost:'6.01', price:'6', calculateQuantity:true});
    assert.equal(expensive.edit('10'), expensive.row);
    assert.match(expensive.ctx.localPosStockChecks[0].inputError, /costo de compra/);
    assert.deepEqual(expensive.edits, []);
    const note = editContext({quantity:'100', cost:'0.5', price:'1', type:'80'});
    assert.equal(exactValue(note.edit('1'), 'unit_price').compare('0.01'), 0);
});
