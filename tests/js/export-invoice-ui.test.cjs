const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');
const _ = require('lodash');
const exactRuntime = require('./support/exact-runtime.cjs');
const parsed = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/invoice_generate.vue','utf8'));
const output = {};
vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code,
    {exports:output, _, require:name=>exactRuntime(name)|| (name==='moment'?require('moment'):name.includes('vuex')?{mapState:()=>({}),mapActions:()=>({})}:{})});
const c=output.default;
test('the operation selector receives the catalog loaded by the form in create and edit contexts',async()=>{
    for(const operations of [[{id:'0101',description:'Venta interna'}],
        [{id:'0101',description:'Venta interna'},{id:'0201',description:'Exportación FOB'},{id:'0202',description:'Exportación CIF'},{id:'0203',description:'Exportación EXW'}]]) {
        const ctx={resource:'documents',configuration:{},form:{},isNrus:false,idUser:1,operation_types:[],
            $store:{commit(){}},$http:{get:async url=>{
                assert.equal(url,'/documents/tables');
                return {data:{document_types_invoice:[{id:'01'}],operation_types:operations,establishments:[{id:1}],
                    currency_types:[{id:'VES'}],series:[],customers:[],sellers:[],discount_types:[],charges_types:[],payment_method_types:[]}};
            }},sortOperationTypes:rows=>c.methods.sortOperationTypes(rows)};
        for(const method of ['loadConfiguration','loadPriceOptions','loadFormLayout','initForm','selectDocumentType','changeEstablishment',
            'changeDateOfIssue','changeDocumentType','changeDestinationSale','setDefaultDocumentType','setConfigGlobalDiscountType','startConnectionQzTray','verifySelectedSeller'])ctx[method]=()=>{};
        Object.defineProperty(ctx,'operation_types_filter',{get:()=>c.computed.operation_types_filter.call(ctx)});
        await c.methods.initComponent.call(ctx);
        assert.deepEqual(ctx.operation_types_filter.map(row=>row.id),operations.map(row=>row.id));
        assert.equal(c.computed.showOperationTypeField.call(ctx),true);
    }
});
test('a registered invoice or a populated draft retains its sales category',()=>{
    for (const [editing,items] of [[true,[]],[false,[{}]]]) {
        const messages=[]; const ctx={isUpdateDocument:editing,isExportInvoice:false,form:{operation_type_id:'0101',items},$message:{error:m=>messages.push(m)}};
        c.computed.operation_type_id_view.set.call(ctx,'0201');
        assert.equal(ctx.form.operation_type_id,'0101'); assert.equal(messages.length,1);
    }
    const ctx={isUpdateDocument:true,isExportInvoice:true,form:{operation_type_id:'0201',items:[{}]}};
    c.computed.operation_type_id_view.set.call(ctx,'0202'); assert.equal(ctx.form.operation_type_id,'0202');
});
test('export lookup selects foreign customers and discards an obsolete response',async()=>{
    let resolve; const ctx={resource:'documents',isExportInvoice:true,form:{operation_type_id:'0201'},customers:[],ensurePreloadedCustomerInList(){},
        $http:{get:(url,{params})=>{assert.equal(params.operation_type_id,'0201'); return new Promise(r=>resolve=r)}}};
    const pending=c.methods.filterCustomers.call(ctx); ctx.form.operation_type_id='0101'; resolve({data:{customers:[{id:1}]}}); await pending;
    assert.equal(ctx.customers.length,0);
    ctx.isExportInvoice=false; ctx.all_customers=[{id:1,identity_document_type_id:'ND'},{id:2,identity_document_type_id:'E'}];
    await c.methods.filterCustomers.call(ctx); assert.deepEqual(ctx.customers.map(x=>x.id),[2]);
});
test('export header discount is counted once and never introduces IVA',()=>{
    const ctx={isExportInvoice:true,is_amount:false,total_global_discount:10,recordDiscountsGlobal:{discount_type_id:'03',description:'Discount'},
        configuration:{global_discount_type_id:'02'},form:{discounts:[{discount_type_id:'03',amount:20}],total_discount:0,total_discount_item:0},$message:{error:()=>assert.fail('valid discount')}};
    ctx.deleteDiscountGlobal=()=>c.methods.deleteDiscountGlobal.call(ctx); ctx.setGlobalDiscount=(...args)=>c.methods.setGlobalDiscount.call(ctx,...args);
    for(let i=0;i<2;i++){c.methods.discountGlobalItems.call(ctx,{total_exportation:200}); assert.equal(ctx.form.discounts.length,1); assert.equal(ctx.form.total,'180'); assert.equal(ctx.form.total_discount,'20');}
});
test('an export discount above its base is rejected',()=>{
    const messages=[]; const ctx={isExportInvoice:true,is_amount:true,total_global_discount:201,form:{total:200},deleteDiscountGlobal(){},$message:{error:m=>messages.push(m)}};
    c.methods.discountGlobalItems.call(ctx,{total_exportation:200}); assert.equal(messages.length,1); assert.equal(ctx.form.total,200);
});
test('export recalculation includes header charges once and updates payment totals after adjustments',()=>{
    for (const enabledDiscount of [false,true]) {
        const paymentTotals=[];
        const ctx={isExportInvoice:true,is_restaurant_active:false,enabled_discount_global:enabledDiscount,is_amount:true,
            total_global_discount:10,total_global_charge:5,recordDiscountsGlobal:enabledDiscount?{discount_type_id:'03'}:null,
            configuration:{global_discount_type_id:'03'},config:{active_allowance_charge:true,percentage_allowance_charge:25},customers:[],
            form:{charges:[{charge_type_id:'62',amount:2},{charge_type_id:'50',amount:3}],discounts:[],total_charge:0,
                items:[{affectation_igv_type_id:'EXP',quantity:1,unit_price:100,total_value:100,total:100,total_igv:0,total_charge:0,total_discount:0,discounts:[]}]},
            $message:{error:()=>assert.fail('valid export adjustments')},
            clearGlobalDistributionDiscounts(){},verifyRecalculateTotalTaxed:()=>false,
            setTotalDefaultPayment(){paymentTotals.push(this.form.total)},setPendingAmount(){},calculateFee(){},setTotalPointsBySale(){},
            chargeGlobal:()=>assert.fail('Domestic automatic charges must not run for export')};
        for(const method of ['sumDiscountsNoBaseByItem','discountGlobalItems','deleteDiscountGlobal','setGlobalDiscount','applyExportGlobalCharge'])
            ctx[method]=(...args)=>c.methods[method].apply(ctx,args);
        for(let i=0;i<3;i++)c.methods.calculateTotal.call(ctx);
        const net=enabledDiscount?'95':'105';
        assert.equal(ctx.form.total_exportation,net);assert.equal(ctx.form.total_value,net);assert.equal(ctx.form.total,net);
        assert.equal(ctx.form.total_igv,'0');assert.equal(ctx.form.total_charge,'5');
        assert.deepEqual(paymentTotals,[net,net,net]);
        assert.deepEqual(ctx.form.charges.map(row=>[row.charge_type_id,row.amount]),[['62','2'],['50','3']]);
        ctx.total_global_charge=0.03;c.methods.calculateTotal.call(ctx);
        assert.deepEqual(ctx.form.charges.map(row=>row.amount),['0.012','0.018']);
        assert.equal(ctx.form.total,enabledDiscount?'90.03':'100.03');
        ctx.total_global_charge=0;c.methods.calculateTotal.call(ctx);
        assert.equal(ctx.form.total,enabledDiscount?'90':'100');assert.equal(ctx.form.total_charge,'0');assert.equal(ctx.form.charges.length,0);
    }
});
test('loading an existing export preserves its rate, collections and object-serialized header adjustments',async()=>{
    const ctx={documentId:1,isUpdateDocument:true,isGeneratedFromExternal:false,table:null,currency_types:[{id:'USD'}],customers:[],form:{discounts:[]},
        configuration:{global_discount_type_id:'03'},config:{},enabled_discount_global:true,is_amount:true,
        $set:(obj,key,value)=>obj[key]=value,$message:{error:()=>assert.fail('valid saved export')},
        onPrepareDataEstablishment(){},onPrepareItems:items=>items,onPrepareAdditionalInformation:value=>value,
        reloadDataCustomers(){},updateChangeDestinationSale(){},prepareDataCustomer(){},regenerateItems(){},clickAddInitGuides(){},
        filterSeries(){},onSetSeriesId:()=>1,validateDateOfIssue(){},getPercentageIgv(){},
        searchExchangeRateByDate:()=>assert.fail('An editor must preserve the saved exchange rate'),
        clearGlobalDistributionDiscounts(){},verifyRecalculateTotalTaxed:()=>false,
        setTotalDefaultPayment(){},setPendingAmount(){},calculateFee(){},setTotalPointsBySale(){},
        chargeGlobal:()=>assert.fail('Automatic domestic charges cannot replace saved export charges')};
    Object.defineProperty(ctx,'isExportInvoice',{get:()=>['0201','0202','0203'].includes(ctx.form.operation_type_id)});
    for(const method of ['restoreFiscalOptions','sumDiscountsNoBaseByItem','discountGlobalItems','deleteDiscountGlobal','setGlobalDiscount','applyExportGlobalCharge',
        'prepareDataGlobalDiscount','calculateTotal','changeDateOfIssue'])ctx[method]=(...args)=>c.methods[method].apply(ctx,args);
    const payments=[{id:4,payment:'95.00',currency_type_id:'USD'}];
    await c.methods.onSetFormData.call(ctx,{id:1,document_type_id:'01',currency_type_id:'USD',exchange_rate_sale:'40.12345678',
        date_of_issue:'2026-10-09',invoice:{operation_type_id:'0201',date_of_due:'2026-11-09'},customer_id:9,customer:{id:9},payments,fee:[],
        charges:{0:{charge_type_id:'50',amount:5}},discounts:{0:{discount_type_id:'03',amount:10,factor:0.1,is_amount:true}},
        items:[{affectation_igv_type_id:'EXP',quantity:1,unit_price:100,total_value:100,total:100,total_igv:0,total_charge:0,total_discount:0,discounts:[]}]});
    assert.equal(ctx.form.exchange_rate_sale,'40.12345678');assert.equal(ctx.form.date_of_due,'2026-11-09');
    assert.equal(ctx.total_global_charge,'5');assert.equal(ctx.form.total_exportation,'95');assert.equal(ctx.form.total,'95');
    assert.deepEqual(Array.from(ctx.form.charges,row=>[row.charge_type_id,row.amount]),[['50','5']]);
    assert.deepEqual(JSON.parse(JSON.stringify(ctx.existingPayments)),payments);assert.deepEqual(ctx.form.payments,payments);
    assert.equal(ctx.thirdPartyMode,false);assert.equal(ctx.specialTaxRegimeMode,false);
});
test('all affected forms compile their templates',()=>{
    for(const file of ['documents/invoice_generate.vue','documents/partials/item.vue','persons/form.vue','documents/partials/detail-drawer.vue']){
        const source=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/'+file,'utf8'));
        assert.deepEqual(compiler.compile(source.template.content).errors,[]);
        babel.parseSync(source.script.content,{configFile:false,babelrc:false,sourceType:'module'});
    }
});
