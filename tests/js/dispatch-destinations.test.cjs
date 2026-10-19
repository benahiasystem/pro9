const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const source = fs.readFileSync('resources/js/views/tenant/dispatches/create.vue', 'utf8');
const script = compiler.parseComponent(source).script.content;
const box = { mapActions: () => ({}) };
vm.runInNewContext('methods = {' + script.split('    methods: {')[1].split('    watch: {')[0].replace(/},\s*$/, '') + '}', box);
function context(get) {
    const ctx = {
        form: { customer_id: 7, establishment_id: 1, transfer_reason_type_id: '01', delivery_address_id: 99 },
        resource: 'dispatches', errors: {}, delivery_addresses: [{id:99}], deliveryAddressesRequest: 0,
        $http: {get}, $delete: (o,k) => delete o[k], $message: {error(message) {ctx.lastError=message;}},
        searchRemoteCustomers() {}, showBuyer: false,
    };
    for (const [name, method] of Object.entries(box.methods)) ctx[name] = method.bind(ctx);
    ctx.searchRemoteCustomers = () => {};
    return ctx;
}
test('customer main address with id zero is selectable and stale destination is cleared', async () => {
    const ctx = context(async url => {
        assert.equal(url, '/dispatches/get_delivery_addresses/7');
        return {data:[{id:0,address:'Principal'},{id:8,address:'Adicional'}]};
    });
    await ctx.loadDeliveryAddresses();
    assert.equal(ctx.form.delivery_address_id,0);
    ctx.form.delivery_address_id='8';
    await ctx.loadDeliveryAddresses();
    assert.equal(ctx.form.delivery_address_id,8);
    assert.equal(ctx.loadingDeliveryAddresses,false);
});
test('changing reason removes customer destination and clears empty own destinations', async () => {
    const ctx=context(async url => {
        assert.equal(url,'/dispatches/get_addresses_other_establishments/1');
        return {data:[]};
    });
    ctx.form.transfer_reason_type_id='04';
    await ctx.changeTransferReasonType();
    assert.equal(ctx.form.customer_id,null);
    assert.equal(ctx.form.delivery_address_id,null);
    ctx.form.transfer_reason_type_id='01';
    await ctx.changeTransferReasonType();
    assert.equal(ctx.form.delivery_address_id,null);
    assert.equal(ctx.loadingDeliveryAddresses,false);
});
test('a late response cannot replace destinations for the newly selected customer',async()=>{
    const pending=[];
    const ctx=context(url=>new Promise(resolve=>pending.push({url,resolve})));
    const first=ctx.changeCustomer();ctx.form.customer_id=10;
    const second=ctx.changeCustomer();
    pending[1].resolve({data:[{id:20}]});await second;
    pending[0].resolve({data:[{id:30}]});await first;
    assert.equal(ctx.form.delivery_address_id,20);
    assert.equal(ctx.delivery_addresses[0].id,20);
});
test('failed load removes old destinations and releases loading',async()=>{
    const ctx=context(async()=>{throw new Error('Offline');});
    await ctx.loadDeliveryAddresses();
    assert.equal(ctx.form.delivery_address_id,null);
    assert.equal(ctx.delivery_addresses.length,0);
    assert.equal(ctx.loadingDeliveryAddresses,false);
    assert.match(ctx.lastError,/No se pudieron cargar/);
});
test('delivery order template compiles with loading and missing destination guidance',()=>{
    const result=compiler.compile(compiler.parseComponent(source).template.content);
    assert.deepEqual(result.errors,[]);
    assert.match(result.render,/loadingDeliveryAddresses/);
    assert.match(result.render,/No hay otra sucursal/);
});
