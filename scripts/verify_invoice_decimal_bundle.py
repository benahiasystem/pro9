#!/usr/bin/env python3
"""Exercise the real compiled billing decimal control and Element UI on loopback only.

This browser fixture has no application database or commercial HTTP endpoints.
The invoice form's business calculations have separate integration tests.
"""
import argparse
import gzip
import hashlib
import html
import http.server
import json
import mimetypes
import pathlib
import re
import shutil
import subprocess
import tempfile
import threading
import urllib.parse

ROOT = pathlib.Path(__file__).resolve().parent.parent


def source_manifest():
    return json.loads(subprocess.check_output([
        'php', '-r', "require 'scripts/postgresql_source_manifest.php';echo json_encode(postgresqlSourceManifest(getcwd()));"
    ], cwd=ROOT, text=True))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=pathlib.Path, required=True)
    parser.add_argument('--ves-invoices-only', action='store_true', help='Comprueba facturas en VES y conserva los cobros y precios en USD.')
    args = parser.parse_args()
    args.output = args.output.resolve()
    screenshot = args.output.with_suffix('.png')
    if args.output.exists() or screenshot.exists():
        parser.error('La evidencia previa es inmutable; seleccione otro nombre de ejecución.')
    browser = next((shutil.which(name) for name in ['google-chrome', 'chromium', 'chromium-browser'] if shutil.which(name)), None)
    if not browser:
        parser.error('Se requiere Chrome/Chromium; esta prueba no se omite.')
    before = source_manifest()
    public = ROOT / 'public'
    manifest_path = public / 'build/manifest.json'
    manifest = json.loads(manifest_path.read_text())
    sources = json.loads((public / 'build/frontend-sources.json').read_text())
    required = ['resources/js/views/tenant/documents/partials/exact_decimal_input.vue',
                'resources/js/views/tenant/documents/invoice_generate.vue', 'resources/js/helpers/invoice-edit-state.js']
    required += ['resources/js/helpers/exact-prepayments.js']
    required += ['resources/js/views/tenant/documents/partials/item.vue']
    required += ['resources/js/views/tenant/documents/partials/lots.vue']
    required += ['resources/js/helpers/invoice-lots.js']
    required += ['resources/js/views/tenant/documents/partials/lots_group.vue']
    required += ['resources/js/helpers/fiscal-note.js', 'resources/js/views/tenant/documents/note.vue']
    required += ['resources/js/views/tenant/pos/partials/payment.vue']
    required += ['resources/js/mixins/payment-settlement-summary.js', 'resources/js/views/tenant/pos/partials/fast_payment_garage.vue']
    required += ['resources/js/mixins/pos-local-quantity.js', 'resources/js/views/tenant/pos/garage.vue']
    required += ['resources/js/mixins/pos-cart-additions.js', 'resources/js/views/tenant/pos/index.vue']
    required += ['resources/js/views/tenant/pos/fast.vue', 'resources/js/views/tenant/pos/partials/fast_payment.vue']
    required += ['resources/js/views/tenant/sale_notes/partials/option_documents.vue']
    required += ['resources/js/components/secondary/ListRestrictItems.vue', 'resources/js/helpers/exact-document.js']
    required += ['resources/js/views/tenant/quotations/partials/options.vue',
                 'resources/js/views/tenant/quotations/partials/series_form.vue']
    required += ['resources/js/views/tenant/documents/invoicetensu.vue']
    required += ['resources/js/views/tenant/documents/partials/detail-drawer.vue']
    required += ['resources/js/helpers/sale-note-conversion.js']
    required += [f'modules/{name}/Resources/assets/js/payment_receipt/ModalGenerateCPE.vue'
                 for name in ['Suscription', 'FullSuscription']]
    stale = [name for name, digest in sources.get('files', {}).items()
             if not (ROOT / name).is_file() or hashlib.sha256((ROOT / name).read_bytes()).hexdigest() != digest]
    if sources.get('version') != 1 or not sources.get('files') or stale or any(name not in sources['files'] for name in required):
        parser.error('El bundle no incluye las fuentes actuales de facturación: ' + ', '.join(stale))
    entry = manifest['resources/js/app.js']
    stylesheets, visited = [], set()

    def collect_css(key):
        if key in visited:
            return
        visited.add(key)
        for imported in manifest[key].get('imports', []):
            collect_css(imported)
        for name in manifest[key].get('css', []):
            if name not in stylesheets:
                stylesheets.append(name)

    collect_css('resources/js/app.js')
    css = ''.join('<link rel="stylesheet" href="/build/' + name + '">' for name in stylesheets)
    page = r'''<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="csrf-token" content="temporary-billing-control-fixture">
CSS<style>body{padding:24px;background:#f4f6fa}section{max-width:720px;margin:16px 0}#sab-invoice-result{white-space:pre-wrap;font-size:11px}</style>
<script>
window.invoiceErrors=[];
window.addEventListener('error',e=>{if(e.message)window.invoiceErrors.push(e.message)});
window.addEventListener('unhandledrejection',e=>window.invoiceErrors.push(String(e.reason)));
const originalError=console.error;console.error=function(...args){window.invoiceErrors.push(args.map(String).join(' '));originalError.apply(console,args)};
localStorage.clear();localStorage.setItem('config',JSON.stringify({user:{type:'admin'},company:{name:'Prueba temporal'},establishment:{id:1}}));
localStorage.setItem('userType','admin');localStorage.setItem('customers','[]');
</script></head><body><div id="main-wrapper"></div><h2>Comprobación temporal de entradas decimales</h2>
<div id="invoice-control-root"></div><div id="invoice-rows-root"></div><pre id="sab-invoice-result">pending</pre>
<script src="/porto-light/vendor/jquery/jquery.js"></script><script type="module" src="/build/ENTRY"></script>
<script>
document.addEventListener('DOMContentLoaded',async()=>{
 const vesInvoicesOnly=__VES_INVOICES_ONLY__;
 const checks=[],assert=(ok,label)=>{if(!ok)throw new Error(label);checks.push(label)};
 const wait=()=>new Promise(resolve=>setTimeout(resolve,100));let wrapper,rows;
 try{
  for(let attempt=0;attempt<180 && !document.getElementById('main-wrapper').__vue__;attempt++)await wait();
  const root=document.getElementById('main-wrapper').__vue__;assert(!!root,'actual application Vue root mounted');
  const Vue=root.constructor,definition=Vue.options.components['tenant-documents-invoice-generate'];
  const Invoice=definition?.options||definition,Input=Invoice?.components?.ExactDecimalInput;
  assert(!!Input && Invoice.render.toString().includes('invoiceInputKey'),'actual compiled invoice registers the decimal control and stable row keys');
  for (const [module,name] of [
    ['Suscription','tenant-index-payment-receipt'],
    ['FullSuscription','tenant-full-suscription-index-payment-receipt'],
  ]) {
    const page=Vue.options.components[name], pageOptions=page?.options||page;
    const modal=pageOptions?.components?.ModalGenerateCPE, modalOptions=modal?.options||modal;
    const rows=['500','499'].map((unit_price,index)=>({item_id:11,unit_price,
      calculation_snapshot:{version:1,values:{quantity:{n:'1',d:'3'}}},
      source_sale_note_items:[{sale_note_id:index+1,sale_note_item_id:index+5}]}));
    const payments=[{currency_type_id:'USD',exchange_rate:'40.12345678'},{currency_type_id:'VES',payment:'10'}];
    for (const grouped of [true,false]) {
      const skipNavigation=new Error('terminate fixture before navigation');
      let posted, reported;
      const vm={group_items_generate_document:grouped,form:{selecteds:[1,2],client_id:8},clients:[{id:8}],
        notes:[{id:1,series:'NV01',number:21,selected:true},{id:2,series:'NV01',number:22,selected:true}],
        onClose(){throw skipNavigation},$message:{error:message=>{reported=message}},
        $http:{post:async(path,payload)=>{
          posted={path,payload};
          return {data:{grouped_items:rows,data:grouped?[{item_id:11,quantity:'0.666666666666'}]:rows,payments,
            conversion_context:{currency_type_id:'VES',exchange_rate_sale:'40.12345678',customer_id:8,establishment_id:9}}};
        }}
      };
      await modalOptions.methods.onFetchNoteItems.call(vm);
      const storageKeys=['itemsNotGroupForNotes','noteConversionContext','noteConversionPayments','notes','client'];
      const [saved,context,receipts,notes]=storageKeys.map(key=>JSON.parse(localStorage.getItem(key)));
      storageKeys.forEach(key=>localStorage.removeItem(key));
      assert(posted?.path==='/sale-notes/items' && posted.payload.select_all===!grouped
        && JSON.stringify(saved)===JSON.stringify(rows) && JSON.stringify(receipts)===JSON.stringify(payments)
        && context.currency_type_id==='VES' && context.exchange_rate_sale==='40.12345678'
        && notes.map(note=>note.id).join(',')==='1,2' && !vm.loading && reported===skipNavigation.message,
        'compiled '+module+' transfers VES invoice rows and mixed payments (grouped='+grouped+')');
    }
  }
  const editorOptions=Invoice.components.DocumentFormItem?.options||Invoice.components.DocumentFormItem;
  const lineEditor=editorOptions?.methods;
  assert(!!lineEditor?.itemToDocumentConversion,'compiled invoice uses the line editor currency conversion');
  const identityState={
    preloadedCustomerId:7,preloadedCustomer:{id:7},customers:[{id:'7'}],all_customers:[{id:'7'}],
    filteredSellers:[{id:9},{id:42}],idUser:42,form:{currency_type_id:'VES',customer_id:7,seller_id:'9'},
    shouldProtectPreloadedCustomer:Invoice.methods.shouldProtectPreloadedCustomer,
  };
  Invoice.methods.ensurePreloadedCustomerInList.call(identityState);
  Invoice.methods.verifySelectedSeller.call(identityState);
  assert(identityState.form.customer_id==='7' && identityState.customers.length===1 && identityState.all_customers.length===1,
    'compiled invoice selects the matching customer option across ID representations');
  assert(identityState.form.seller_id===9,'compiled invoice preserves the selected seller across ID representations');
  const variationIdentity={resource:'documents',items:[{id:7},{id:'9007199254740992'},{id:'9007199254740993'}],form:{},$refs:{},
    $http:{get:async()=>({data:{items:[{id:'7',sale_unit_price:'12.004'}]}})},changeItem(){}};
  await lineEditor.selectVariation.call(variationIdentity,{id:7});
  assert(variationIdentity.items.length===3 && variationIdentity.form.item_id==='7' && variationIdentity.items[0].sale_unit_price==='12.004',
    'compiled invoice refreshes an equivalent variation option without duplicating it');
  assert(lineEditor.isSelectedPrice.call({item_unit_type:{id:7}},{id:'7'})
    && !lineEditor.isSelectedPrice.call({item_unit_type:{id:'9007199254740992'}},{id:'9007199254740993'}),
    'compiled invoice distinguishes adjacent bigint presentations');
  const serialDef=editorOptions.components.SelectLotsForm;
  const serialPicker=serialDef?.options||serialDef;
  assert(editorOptions.render.toString().includes('serialPickerExactQuantity')
    && serialPicker.render.toString().includes('seriesSelectionInvalid'),
    'compiled invoice connects the exact quantity to the serial selector controls');
  const serialCheck=new Vue({data:()=>({exactQuantities:true,
    exactQuantity:{n:'32767999999999999',d:'1000000000000'},quantity:'32767.999999999999',
    lotsSelected:new Array(32768),search:{input:'00012'},records:[{series:'000123',has_sale:false}]}),
    computed:serialPicker.computed,methods:{recordToSelected:serialPicker.methods.recordToSelected}});
  serialCheck.recordToSelected();
  assert(serialCheck.lotsSelected.length===32768 && !serialCheck.records[0].has_sale,
    'compiled invoice serial selector ignores an incomplete search without selecting a row');
  assert(serialCheck.toAttend==='-0.000000000001' && serialCheck.seriesSelectionInvalid,
    'compiled invoice serial selector detects an excess hidden by binary rounding');
  serialCheck.exactQuantity={n:'32768',d:'1'};
  assert(serialCheck.toAttend==='0' && !serialCheck.seriesSelectionInvalid,
    'compiled invoice serial selector allows the exact number of selected series');
  serialCheck.$destroy();
  const lotDefinition=Invoice.components.LotsGroup;
  const lotOptions=lotDefinition?.options||lotDefinition;
  const invoiceLots=new Vue({
    data:()=>({exactQuantities:true,quantity:'0.3',search:'ALFA',lots_group_:[],lotsGroup:[
      {id:1,code:'ALFA',quantity:'1',compromise_quantity:'0'},
      {id:2,code:'BETA',quantity:'1',compromise_quantity:'0'},
    ]}),
    computed:{sourceLots:lotOptions.computed.sourceLots,toAttend:lotOptions.computed.toAttend},
    methods:{filter:lotOptions.methods.filter,submit:lotOptions.methods.submit},
  });
  const lotEvents=[];invoiceLots.$on('addRowLotGroup',rows=>lotEvents.push(rows));
  invoiceLots.filter();invoiceLots.lots_group_[0].compromise_quantity='0.1';
  invoiceLots.search='BETA';invoiceLots.filter();invoiceLots.lots_group_[0].compromise_quantity='0.2';
  assert(invoiceLots.toAttend==='0','compiled invoice lot search preserves the remainder across filters');
  await invoiceLots.submit();
  assert(lotEvents[0]?.map(value=>value.compromise_quantity).join(',')==='0.1,0.2',
    'compiled invoice lot dialog submits assignments from both searches');
  invoiceLots.$destroy();
  const redVariation={value:'Rojo',color:'#cc2222'};
  const variationOption={variations:[
    {stock:'0.1',variation_attributes:[redVariation]},
    {stock:'0.2',variation_attributes:[redVariation]},
    {stock:'9007199254740993.02'},
  ]};
  assert(lineEditor.variationsTotalStock(variationOption)==='9007199254740993.32','compiled invoice adds variation stocks without binary rounding');
  const variationsGroup=lineEditor.variationGroups(variationOption)[0];
  assert(lineEditor.formatVariationStock(variationsGroup.stock)==='0.3','compiled invoice renders the exact variation group subtotal');
  assert(lineEditor.variationStockClass({stock:'9007199254740993.01',stock_min:'9007199254740993'})==='text-success',
    'compiled invoice compares variation stock to the minimum without losing precision');
  assert(editorOptions.render.toString().includes('forgetRestoredDiscount'),
    'compiled invoice connects fixed discount inputs to native edit handling');
  const quantityEditor={
    ...lineEditor,isFromInvoice:true,quantityStep:1,
    recordItem:{quantity:'0.333333333333',calculation_snapshot:{values:{quantity:{n:'1',d:'3'}},inputs:{}}},
    form:{quantity:'0',unit_price_value:'3',item:{calculate_quantity:false,currency_type_id:'VES'}},
  };
  quantityEditor.restoreEditedQuantity();
  quantityEditor.clickIncrease();
  const adjusted=quantityEditor.form.quantity_input;
  assert(quantityEditor.readonly_total==='4' && adjusted.amount==='1' &&
    adjusted.unit_price.n==='3' && adjusted.unit_price.d==='4',
    'compiled invoice quantity button preserves four thirds and its exact total');
  quantityEditor.clickDecrease();
  const restoredQuantity=quantityEditor.currentExactQuantity().fraction();
  assert(restoredQuantity.n==='1' && restoredQuantity.d==='3','compiled invoice quantity decrease recovers one third');
  let invoiceStock;
  quantityEditor.$http={post:async (url,payload)=>{invoiceStock={url,payload};return {data:{success:true}}}};
  quantityEditor.form.quantity='0';
  quantityEditor.form.quantity_input=null;
  quantityEditor.form.calculation_snapshot=null;
  quantityEditor.validateQuantity();
  await quantityEditor.validateCurrentStock();
  assert(invoiceStock.url==='/validate-current-item-stock' && invoiceStock.payload.quantity==='0.01' &&
    invoiceStock.payload.quantity_exact.n==='1' && invoiceStock.payload.quantity_exact.d==='100',
    'compiled invoice checks the exact minimum used in the line');
  const selectedSerials=Array.from({length:16384},(_,i)=>({id:i+1,has_sale:true}));
  for(const [quantity,matching] of [['16384.000000000001',false],['16384',true]]){
    const errors=[],submitted=[];
    const serialEditor={...lineEditor,isFromInvoice:true,config:{},configuration:{},recordItem:{indexi:0},
      currencyTypeIdActive:'VES',exchangeRateSale:'1',percentageIgv:'0.16',operationTypeId:'0101',
      operation_types:[{id:'0101',exportation:false}],affectation_igv_types:[{id:'20',active:true,exportation:false}],
      item_unit_type:{},form:{quantity,unit_price_value:'1',has_igv:true,affectation_igv_type_id:'20',discounts:[],charges:[],attributes:[],
        item:{id:1,description:'Artículo con seriales',currency_type_id:'VES',series_enabled:true,lots:selectedSerials}},
      applyValidateStock:()=>false,validateQuantity:()=>true,validateTotalItem:()=>({}),initForm(){},close(){},
      $message:{error:message=>errors.push(message)},$emit:(_,row)=>submitted.push(row)};
    await serialEditor.clickAddItem();
    assert(matching ? submitted.length===1 && errors.length===0 : submitted.length===0 && errors[0]?.includes('series seleccionadas'),
      'compiled invoice item serial count '+(matching?'accepts the integer':'rejects the fractional mismatch during item editing'));
  }
  const exportEditor={
    ...Invoice.methods,isExportInvoice:true,is_amount:false,total_global_discount:'0',
    recordDiscountsGlobal:{discount_type_id:'03',description:'Descuento'},
    form:{currency_type_id:'VES',items:[{affectation_igv_type_id:'EXP',total_value:'100000000000000'}],discounts:[]},
  };
  exportEditor.prepareDataGlobalDiscount({discounts:[{factor:'0',is_amount:false,amount_exact:{n:'1',d:'1'}}]});
  exportEditor.discountGlobalItems(exportEditor.form);
  assert(exportEditor.form.total==='99999999999999' && exportEditor.form.discounts[0].percentage==='0.000000000001',
    'compiled export invoice reopens a tiny percentage discount without changing its amount');
  for (const [currency,priceCurrency,price,affectation,savedAmount,savedDenominator,shown,changed,changedAmount,changedTotal] of [
    ['VES','USD','3','10','1','1','0.029','0.058','2','117.68'],
    ['USD','VES','120','20','1','1','40','20','0.5','2.5'],
    ['VES','USD','3','10','0.000000000001','1000000000000','0','0','0','120'],
    ['USD','USD','3','20','0.333333333333','3','0.333333333333','0.333333333333','0.333333333333','2.666666666667'],
  ]) {
    if(vesInvoicesOnly && currency!=='VES') continue;
    const saved={discount_type_id:'00',is_amount:true,amount:savedAmount,amount_exact:{n:'1',d:savedDenominator}};
    const submitted=[];
    const editor=new Vue({
      data:()=>({currencyTypeIdActive:currency,exchangeRateSale:'40',percentageIgv:'0.16',
        isFromInvoice:true,isExportation:false,config:{},configuration:{},recordItem:{indexi:0},
        operation_types:[{id:'0101',exportation:false}],operationTypeId:'0101',affectation_igv_types:[{id:affectation,active:true,exportation:false}],
        item_unit_type:{},form:{quantity:'1',unit_price_value:price,has_igv:true,affectation_igv_type_id:affectation,
          discounts:[],charges:[],attributes:[],item:{id:1,description:'Prueba temporal',currency_type_id:priceCurrency,lots:[]}}}),
      beforeCreate(){this.$message={error:message=>{throw new Error(message)}}},
      methods:{prepareDiscountsForEdit:lineEditor.prepareDiscountsForEdit,clickAddItem:lineEditor.clickAddItem,
        itemToDocumentConversion:lineEditor.itemToDocumentConversion,currentPriceInputAmount:lineEditor.currentPriceInputAmount,
        applyValidateStock:()=>false,validateQuantity:()=>true,validateTotalItem:()=>({}),currentExactQuantity:()=> '1',
        initForm(){},close(){}},
      render(h){return h('el-input',{props:{value:this.form.discounts[0]?.amount||''},
        on:{input:value=>{this.form.discounts[0].amount=value}},
        nativeOn:{input:event=>lineEditor.forgetRestoredDiscount.call(this,this.form.discounts[0],event)}})},
    }).$mount();
    document.body.appendChild(editor.$el);
    editor.form.discounts=editor.prepareDiscountsForEdit([saved]);
    editor.$on('add',row=>submitted.push(row));await Vue.nextTick();
    const discountInput=editor.$el.querySelector('input');
    assert(discountInput.value===shown,'compiled invoice restores the fixed discount in '+priceCurrency);
    await editor.clickAddItem();
    assert(submitted[0].discounts[0].amount_exact.n==='1'&&submitted[0].discounts[0].amount_exact.d===savedDenominator
      &&saved.amount===savedAmount,'compiled invoice retains the unedited discount in '+currency);
    discountInput.value=changed;discountInput.dispatchEvent(new Event('input',{bubbles:true}));await Vue.nextTick();
    await editor.clickAddItem();
    assert(submitted[1].discounts[0].amount===changedAmount&&submitted[1].total===changedTotal,
      'compiled invoice converts the edited discount once into '+currency+' ('+changed+')');
    if(changed===shown) {
      assert(submitted[1].discounts[0].amount_exact.n===(changed==='0'?'0':'333333333333')&&
        submitted[1].discounts[0].amount_exact.d===(changed==='0'?'1':'1000000000000'),
        'compiled invoice saves the typed decimal even when it matches the display');
    }
    editor.$destroy();editor.$el.remove();
  }
  const fraction={n:'1',d:'3'},projection='0.333333333333';
  const advance={id:7,description:'FF01-7',document_type_id:'02',amount:projection,total:projection,
    calculation_snapshot:{version:1,values:{amount:fraction,total:fraction}}};
  const prepayment=new Vue({
    data:()=>({form:{affectation_type_prepayment:'20',prepayments:[{document_id:7}],discounts:[],
      items:[{affectation_igv_type_id:'20',total_base_igv:'1'}]},percentage_igv:'0.16'}),
    methods:{inputAmountPrepayment:Invoice.methods.inputAmountPrepayment,
      changeDocumentPrepayment:Invoice.methods.changeDocumentPrepayment,getPrepayment:()=>advance,
      changeTotalPrepayment:Invoice.methods.changeTotalPrepayment,
      discountGlobalPrepayment:Invoice.methods.discountGlobalPrepayment,calculateTotal(){}},
    beforeCreate(){this.$message={error:message=>{throw new Error(message)}}},
    render(h){return h('el-input',{props:{value:this.form.prepayments[0].amount},
      on:{input:value=>this.inputAmountPrepayment(0,value)}})},
  }).$mount();
  document.body.appendChild(prepayment.$el);
  await prepayment.changeDocumentPrepayment(0);await Vue.nextTick();
  assert(prepayment.form.prepayments[0].calculation_snapshot.values.total.d==='3',
    'compiled invoice selection retains the original advance fraction');
  const advanceInput=prepayment.$el.querySelector('input');
  advanceInput.value=projection+'0';
  advanceInput.dispatchEvent(new Event('input',{bubbles:true}));await Vue.nextTick();
  const entered=prepayment.form.prepayments[0];
  assert(entered.total===projection && entered.calculation_snapshot.values.total.n==='333333333333' &&
    entered.calculation_snapshot.values.total.d==='1000000000000',
    'compiled invoice uses the typed decimal when Element UI emits an equivalent spelling');
  assert(prepayment.form.discounts[0].amount_exact.d==='1000000000000',
    'compiled invoice computes its prepayment discount from the typed decimal');
  prepayment.$destroy();prepayment.$el.remove();
  const residualAdvance={form:{total_prepayment:'0',calculation_snapshot:{values:{total_prepayment:{n:'1',d:'3000000000000'}}}}};
  assert(Invoice.render.toString().includes('hasPositiveInvoicePrepayment') &&
    Invoice.computed.hasPositiveInvoicePrepayment.call(residualAdvance),
    'compiled invoice shows the advance row for an exact positive amount projected as zero');
  residualAdvance.form.calculation_snapshot.values.total_prepayment.n='0';
  assert(!Invoice.computed.hasPositiveInvoicePrepayment.call(residualAdvance),
    'compiled invoice hides the advance row when the original amount is zero');
  const residualBase={form:{total_taxed:'0',calculation_snapshot:{values:{total_taxed:{n:'1',d:'3000000000000'}}}}};
  assert(Invoice.render.toString().includes('hasPositiveInvoiceAmount') &&
    Invoice.methods.hasPositiveInvoiceAmount.call(residualBase,'total_taxed'),
    'compiled invoice shows a positive tax base whose decimal projection is zero');
  const tensuDefinition=Vue.options.components['tenant-documents-invoicetensu'];
  const Tensu=tensuDefinition?.options||tensuDefinition;
  assert(Tensu.render.toString().includes('hasPositiveInvoiceAmount') &&
    Tensu.methods.hasPositiveInvoiceAmount.call(residualBase,'total_taxed') &&
    !Tensu.methods.hasPositiveInvoiceAmount.call(residualBase,'total_exonerated'),
    'compiled Tensu shows the exact positive tax base and hides a missing total');
  const residualDiscount={form:{items:[],discounts:[{discount_type_id:'03',amount:'0',amount_exact:{n:'1',d:'100000000000000'}}]}};
  residualDiscount.totalDiscountExact=Invoice.computed.totalDiscountExact.call(residualDiscount);
  assert(Invoice.render.toString().includes('hasPositiveInvoiceDiscount') &&
    Invoice.computed.hasPositiveInvoiceDiscount.call(residualDiscount) &&
    Invoice.computed.totalDiscount.call(residualDiscount)==='0',
    'compiled invoice shows a positive discount while preserving its projected display');
  const scopedAdvances=Invoice.mixins.find(mixin=>mixin.methods?.getDocumentsPrepayment);
  assert(!!scopedAdvances,'compiled invoice mounts the scoped advance selector');
  const lookups=[];
  const advanceSelector=new Vue({mixins:[scopedAdvances],
    data:()=>({prepayment_deduction:true,resource:'documents',prepayment_documents:[],
      form:{customer_id:'9007199254740993',establishment_id:'1',currency_type_id:'VES',
        affectation_type_prepayment:20,prepayments:[{total:'1'}],total_prepayment:'1',discounts:[]}}),
    beforeCreate(){this.$http={get:(url,options)=>new Promise(resolve=>lookups.push({url,options,resolve}))}},
    methods:{changeTotalPrepayment(){}},
  });
  const previousLookup=advanceSelector.getDocumentsPrepayment();
  advanceSelector.form.customer_id='9007199254740994';
  if(!vesInvoicesOnly) advanceSelector.form.currency_type_id='USD';
  await Vue.nextTick();
  assert(lookups[1].options.params.customer_id==='9007199254740994' &&
    lookups[1].options.params.currency_type_id===(vesInvoicesOnly ? 'VES' : 'USD') && advanceSelector.form.prepayments.length===0,
    'compiled invoice refreshes the scoped options and clears the superseded draft advance');
  lookups[1].resolve({data:[{id:2}]});await Vue.nextTick();
  lookups[0].resolve({data:[{id:1}]});await previousLookup;
  assert(advanceSelector.prepayment_documents[0].id===2,'compiled invoice ignores a late response for the previous buyer');
  advanceSelector.$destroy();
  const saleNotes=Vue.options.components['tenant-sale-notes-index'];
  const quotations=Vue.options.components['tenant-quotations-index'];
  const quotationDefinition=(quotations?.options||quotations)?.components?.QuotationOptions;
  const quotationOptions=quotationDefinition?.options||quotationDefinition;
  const quotationMethods=quotationOptions?.methods;
  assert(typeof quotationMethods?.changeRetention==='function','compiled quotation registers invoice retention calculation');
  const quotation={
    ...quotationMethods,config:{igv_retention_percentage:'75'},
    document:{total:'9007199254740993.01',currency_type_id:'VES',payment_condition_id:'02',has_retention:true,
      payments:[{payment:'0.25'}],fee:[{amount:'0'},{amount:'0'}]},
  };
  quotation.changeRetention();
  assert(quotation.document.retention.amount==='6755399441055744.76' &&
    quotation.document.total_pending_payment==='2251799813685248.25','compiled quotation keeps retention and balance as exact decimals');
  assert(quotation.document.fee.every(fee=>fee.amount==='1125899906842624.00'),'compiled quotation distributes the exact retained balance after receipts');
  const lotRow={item_id:13,quantity:'1.000000000001',item:{lots_enabled:true,description:'Lote de prueba'},
    IdLoteSelected:[{id:'17',compromise_quantity:'1'}]};
  const conversionLots={document:{document_type_id:'01'},form:{quotation:{items:[lotRow]}}};
  assert(!(await quotationMethods.validateQuantityandSeriesLots.call(conversionLots)).success,
    'compiled quotation invoice rejects a lot difference at the twelfth decimal');
  conversionLots.document.document_type_id='nv';
  assert((await quotationMethods.validateQuantityandSeriesLots.call(conversionLots)).success,
    'compiled quotation sale note uses its original lot validation');
  const seriesDefinition=quotationOptions.components.SeriesForm;
  const seriesOptions=seriesDefinition?.options||seriesDefinition;
  const seriesEditor={...seriesOptions.methods,exactQuantities:true,
    async getLotsGroup(){this.lots_group=[{id:17,compromise_quantity:'0'}]}};
  await seriesEditor.openDialogLotsGroup(0,lotRow);
  assert(seriesEditor.rowNeedsLotAssignment(lotRow) && seriesEditor.lots_group_quantity===lotRow.quantity &&
    seriesEditor.lots_group[0].compromise_quantity==='1' && seriesEditor.showDialogLotsGroup,
    'compiled quotation invoice opens lot correction with the full quantity and the previous assignment');
  const serialized={quantity:'16384.000000000001',item:{series_enabled:true,
    lots:Array.from({length:16384},(_,index)=>({id:index+1,has_sale:true}))}};
  conversionLots.document.document_type_id='01';
  conversionLots.form.quotation.items=[serialized];
  assert(!(await quotationMethods.validateQuantityandSeriesLots.call(conversionLots)).success,
    'compiled quotation invoice rejects a serial quantity rounded to the selected count by Number');
  const conversion=(saleNotes?.options||saleNotes)?.components?.SaleNoteGenerate;
  const conversionMethods=(conversion?.options||conversion)?.methods;
  const convertFees=conversionMethods?.getFeesData;
  assert(typeof convertFees==='function','compiled sale note conversion registers its installment mapping');
  const installments=convertFees.call({document:{currency_type_id:'VES'}},{fee:[
    {date:'2026-12-01',amount:'100000000000000.01'},
    {date:'2027-01-01',amount:'100000000000000.02'},
  ]});
  assert(installments[0].amount==='100000000000000.01' && installments[1].amount==='100000000000000.02',
    'compiled sale note conversion preserves each scheduled installment to the cent');
  const datedDraft={document:{payment_condition_id:'01',date_of_issue:'2026-10-01',
    fee:[{date:'2026-10-01',amount:'75.01'}]}};
  assert(conversionMethods.validatePaymentDate.call(datedDraft).error_by_item===0 && datedDraft.document.fee.length===1,
    'compiled cash conversion keeps unused dated installments without blocking');
  datedDraft.document.payment_condition_id='02';
  assert(conversionMethods.validatePaymentDate.call(datedDraft).error_by_item===1,
    'compiled credit conversion rejects an installment on the issue date');
  const restriction=(conversion?.options||conversion)?.components?.ListRestrictItems;
  const restrictMethods=(restriction?.options||restriction)?.methods;
  assert(typeof restrictMethods?.removeInvoiceItem==='function','compiled conversion registers the exact restricted-item editor');
  const originalRows=[{total_value:'1',total_base_igv:'1',affectation_igv_type_id:'20'},
    {total_value:'4',total_base_igv:'4',affectation_igv_type_id:'20',item:{restrict_sale_cpe:true}}];
  const restrictedDraft=new Vue({methods:restrictMethods,data:()=>({
    form:{document_type_id:'01',items:originalRows,discounts:[{discount_type_id:'03',is_amount:true,
      amount:'0.335',amount_exact:{n:'3349999999999',d:'10000000000000'}}]},
  })});
  restrictedDraft.clickRemoveItem(1);
  assert(restrictedDraft.form.discounts[0].amount_exact?.n==='3349999999999' && restrictedDraft.form.total_payable==='0.67',
    'compiled restricted-item removal preserves the source discount and settles at sixty-seven cents');
  assert(originalRows.length===2 && restrictedDraft.form.items.length===1,
    'compiled restricted-item removal keeps the source note rows intact');
  restrictedDraft.$destroy();
  const mixedDiscountDraft=new Vue({methods:restrictMethods,data:()=>({
    form:{document_type_id:'01',charges:{},items:[{affectation_igv_type_id:'10',total_value:'70',total_base_igv:'90',
      total_igv:'14.4',discounts:{0:{from_global_distribution:true,discount_type_id:'00',amount:'10'},
        1:{from_global_distribution:true,discount_type_id:'01',amount:'20'}}},originalRows[1]],
      discounts:{0:{discount_type_id:'02',amount:'20'},1:{discount_type_id:'03',amount:'20'}}},
  })});
  mixedDiscountDraft.clickRemoveItem(1);
  assert(mixedDiscountDraft.form.total_taxed==='80' && mixedDiscountDraft.form.total_igv==='12.8' &&
    mixedDiscountDraft.form.total_payable==='72.8','compiled restricted-item removal applies only taxable distributions to the IVA base');
  mixedDiscountDraft.$destroy();
  const posDefinition=Vue.options.components['tenant-pos-index'];
  const posComponents=(posDefinition?.options||posDefinition)?.components;
  const cartMixin=(posDefinition?.options||posDefinition)?.mixins.find(mixin=>mixin.methods?.addPosItem);
  const cartState={...cartMixin.data(),...cartMixin.methods,form:{document_type_id:'01',currency_type_id:'VES',
    exchange_rate_sale:'1',items:[]},form_item:{},percentage_igv:'0.16',affectation_igv_types:[{id:'20'}],localPosStockChecks:[],
    $set:(target,key,value)=>{target[key]=value},$notify(){},$message:{error(message){throw new Error(message)}},
    cleanInput(){},initFocus(){},calculateTotal(){},setFormPosLocalStorage(){}};
  const units=['9007199254740992','9007199254740993'].map((id,index)=>({id,quantity_unit:'2',unit_type_id:'UND',
    description:'Caja',prices:[{id:7+index,price_label_id:2}]}));
  const saleSelection=id=>({id:1,item_id:1,unit_type_id:'UND',currency_type_id:'VES',sale_unit_price:'10',has_igv:true,
    sale_affectation_igv_type_id:'20',item_unit_types:units,presentation:{id:String(id),price_label_id:2,
    quantity_unit:'2',unit_type_id:'UND',description:'Caja'}});
  cartState.addPosItem(saleSelection(7));
  const restored=JSON.parse(JSON.stringify(cartState.form.items[0]));
  for(const presentation of [restored.presentation,restored.item.presentation]){
    delete presentation.item_unit_type_id;presentation.id='7';
  }
  cartState.form.items=[restored];
  cartState.addPosItem(saleSelection(8));cartState.addPosItem(saleSelection(7));
  assert(cartState.form.items.length===2 && cartState.form.items[0].item.presentation.id===units[1].id
    && cartState.form.items[1].item.presentation.id===units[0].id && cartState.form.items[1].quantity==='2',
    'compiled POS preserves separate bigint presentations on restored price rows');
  const table=posComponents?.TableItems;
  const variationModal=posComponents?.VariationsModal;
  const tableOptions=table?.options||table;
  const variationOptions=variationModal?.options||variationModal;
  const displayedPrice=tableOptions?.methods?.formatPrice;
  assert(typeof displayedPrice==='function' && typeof variationOptions?.methods?.formatPrice==='function',
    'compiled POS registers the product table and variation prices');
  const multiplierUnit={id:1,unit_type_id:'UND',quantity_unit:'1',prices:[{price:'10',quantity_unit:'1'}]};
  const multiplier={...multiplierUnit,calculation_snapshot:{version:1,values:{quantity_unit:{n:'3000000000001',d:'3000000000000'}}}};
  const multiplierLabel=tableOptions.methods.priceOptions({item_unit_types:[multiplier]})[0].quantity_label;
  assert(tableOptions.render.toString().includes('quantity_label') && multiplierLabel==='3000000000001/3000000000000'
    && tableOptions.methods.priceOptions({item_unit_types:[multiplierUnit]})[0].quantity_label===null,
    'compiled POS price selector distinguishes a presentation just above one physical unit');
  const largePrice='9007199254740993.01';
  assert(displayedPrice.call({config:{decimal_quantity:2}},{sale_unit_price:largePrice})===largePrice,
    'compiled POS preserves both cents and large integers in the product table');
  const originalPrice={n:'3349999999999',d:'10000000000000'};
  assert(displayedPrice.call({config:{decimal_quantity:2}},
    {price:'0.335',calculation_snapshot:{version:1,values:{price:originalPrice}}},'price')==='0.33',
    'compiled POS displays a price list choice from its original catalog fraction');
  const variant={id:'1',description:'Variación temporal',stock:'1',sale_unit_price:'0.335',
    variation_attributes:[{variable:'Color',value:'Rojo'}],calculation_snapshot:{version:1,values:{sale_unit_price:originalPrice}}};
  const chosenVariants=[];
  const variationHost=new Vue({
    render(h){return h(variationModal,{ref:'selector',props:{showDialog:true,
      parent:{description:'Producto temporal',currency_type_symbol:'Bs.',variations:[variant]}},
      on:{select:value=>chosenVariants.push(value)}})},
  }).$mount();
  document.body.appendChild(variationHost.$el);
  const selector=variationHost.$refs.selector;
  selector.resetSelection();await Vue.nextTick();
  assert(document.querySelector('.variation-gallery__price')?.textContent.includes('0.33'),
    'compiled POS variation gallery uses the original price fraction');
  selector.view='list';await Vue.nextTick();
  const variantRow=document.querySelector('.variation-row');
  assert(variantRow?.textContent.includes('0.33'),'compiled POS variation list uses the same price');
  variantRow.click();await Vue.nextTick();
  assert(chosenVariants[0]===variant && chosenVariants[0].sale_unit_price==='0.335',
    'selecting the variation preserves the original price data');
  variationHost.$destroy();variationHost.$el.remove();
  const posPayment=posComponents?.PaymentForm;
  const posMethods=(posPayment?.options||posPayment)?.methods;
  const settlement=(posPayment?.options||posPayment).mixins.find(mixin=>mixin.computed?.settlementPaymentBlocked);
  const restoredReceipt={id:1,payment:'0.335',calculation_snapshot:{version:1,values:{payment:{n:'3349999999999',d:'10000000000000'}}}};
  const settlementView=new Vue({mixins:[settlement],methods:{inputAmount(){}},data:()=>({
    useLocalCalculations:true,igtfQuote:null,igtfQuoteLoading:false,igtfQuoteError:null,button_payment:false,
    form:{document_type_id:'01',payment_condition_id:'01',payments:[restoredReceipt]},
  })});
  assert(settlementView.paymentAppliedTotal==='0.33','compiled invoice checkout restores the exact principal of a recorded receipt');
  assert(settlementView.settlementPaymentBlocked,'compiled cash checkout waits for a valid local quote');
  settlementView.igtfQuote={balance:'0.00'};
  assert(!settlementView.settlementPaymentBlocked,'compiled cash checkout accepts a valid quote with no collectible balance');
  settlementView.igtfQuote=null;settlementView.form.payment_condition_id='02';
  assert(!settlementView.settlementPaymentBlocked,'compiled credit checkout permits an open balance');
  settlementView.$destroy();
  const canPay=(posDefinition?.options||posDefinition)?.computed?.canPay;
  const currentDate=new Date();
  const dateOfIssue=[currentDate.getFullYear(),String(currentDate.getMonth()+1).padStart(2,'0'),
    String(currentDate.getDate()).padStart(2,'0')].join('-');
  const tinyInvoice={document_type_id:'01',total:'0',customer_id:1,series_id:1,items:[],payments:[],
    payment_condition_id:'02',date_of_issue:dateOfIssue,
    calculation_snapshot:{version:1,values:{total:{n:'1',d:'10000000000000'}}}};
  let checkedTinyInvoice=false;
  assert(canPay.call({form:tinyInvoice}),'compiled POS enables checkout for a positive exact invoice total represented as zero');
  const tinyTaxed={...tinyInvoice,total_taxed:'0',total_igv:'0',
    calculation_snapshot:{version:1,values:{total_taxed:{n:'1',d:'3000000000000'},total_igv:{n:'1',d:'18750000000000'}}}};
  const cartMethods=(posDefinition?.options||posDefinition)?.methods;
  const cartIdentity={form:{items:[{item_id:7,quantity:'2'},
    {item_id:'9007199254740992',quantity:'4'},{item_id:'9007199254740993',quantity:'3'}]}};
  assert(cartMethods.cartQtyLabel.call({...cartMethods,...cartIdentity},{item_id:'7'})==='2'
    && cartMethods.cartQtyLabel.call({...cartMethods,...cartIdentity},{item_id:'9007199254740993'})==='3'
    && cartMethods.cartQtyLabel.call({...cartMethods,...cartIdentity},{item_id:9007199254740992})==='0',
    'compiled POS matching preserves the selected cart row across transport types and adjacent bigint keys');
  const smallCart={...cartMethods,form:{document_type_id:'01',currency_type_id:'VES',items:[{
    item_id:7,quantity:'0',calculation_snapshot:{version:1,values:{quantity:{n:'1',d:'3000000000000'}}}
  }]}};
  assert(smallCart.cartHasQuantity({item_id:'7'}) && smallCart.cartQtyLabel({item_id:'7'})==='<0.000000000001'
    && !smallCart.cartHasQuantity({item_id:'8'}),
    'compiled POS invoice keeps card controls and a truthful label for positive quantities below display precision');

  const cartAmount=(form,field)=>cartMethods.cartSummaryValue.call({form},field);
  const formatCart=(form,field)=>cartMethods.cartSummaryMoney.call({...cartMethods,form},field);
  assert(cartAmount(tinyTaxed,'total_taxed').compare(0)>0 && cartAmount(tinyTaxed,'total_igv').compare(0)>0,
    'compiled POS summary recognizes positive exact invoice bases and tax projected as zero');
  const roundingInvoice={...tinyInvoice,total:'0.005',
    calculation_snapshot:{version:1,values:{total:{n:'99999999999',d:'20000000000000'}}}};
  assert(formatCart(roundingInvoice,'total')==='0.00' && formatCart({...roundingInvoice,document_type_id:'80',total:'1.005'},'total')==='1.00',
    'compiled POS summary rounds an invoice from its fraction and a sales note from its scalar');
  await posMethods.clickPayment.call({form:tinyInvoice,refreshScaleTotals:()=>true,asignPlateNumberToItems:async()=>{},
    validateRestrictSaleItemsCpe:()=>({success:true}),validateRestrictSellerDiscount:()=>({success:true}),
    ensureFiscalPaymentsReady:async()=>{checkedTinyInvoice=true;return false;},
    $message:{error:message=>{throw Error(message);},warning:message=>{throw Error(message);}}});
  assert(checkedTinyInvoice,'compiled POS validates receipts using the positive exact invoice total');
  const zeroPayableCashInvoice={...tinyInvoice,currency_type_id:'VES',payment_condition_id:'01'};
  let submittedCashCondition=null;
  await posMethods.clickPayment.call({
    form:zeroPayableCashInvoice,refreshScaleTotals:()=>true,asignPlateNumberToItems:async()=>{},
    validateRestrictSaleItemsCpe:()=>({success:true}),validateRestrictSellerDiscount:()=>({success:true}),
    ensureFiscalPaymentsReady:async()=>true,settlementPaymentBlocked:false,prepareCalculationSubmission:payload=>payload,
    $message:{error(){},warning:message=>{throw Error(message);}},
    $http:{post:async (_url,payload)=>{submittedCashCondition=payload.payment_condition_id;return {data:{success:false}};}},
  });
  assert(submittedCashCondition==='01' && zeroPayableCashInvoice.payment_condition_id==='01',
    'compiled POS keeps a zero-payable VES invoice as cash when no receipt is needed');
  assert(!!posMethods?.validateRestrictSellerDiscount,'compiled POS registers the seller discount validation');
  const seller=new Vue({
    data:()=>({form:{document_type_id:'01',discounts:[{factor:'0.07'}]},typeUser:'seller',
      configuration:{restrict_seller_discount:true,sellers_discount_limit:7},showDialogDiscountPermission:false,totalDiscountPercentage:0}),
    methods:{validateRestrictSellerDiscount:posMethods.validateRestrictSellerDiscount},
  });
  assert(seller.validateRestrictSellerDiscount().success && !seller.showDialogDiscountPermission,'compiled POS accepts the exact invoice discount limit of 7 percent');
  seller.form.discounts=[{factor:'0.070000000001'}];
  assert(!seller.validateRestrictSellerDiscount().success && seller.showDialogDiscountPermission,'compiled POS requires authorization above the exact invoice discount limit');
  seller.$destroy();
  const checkoutDraft={document_type_id:'01',currency_type_id:'VES',
    items:[{affectation_igv_type_id:'20',total_base_igv:'120',total_value:'120'}],discounts:[],charges:[]};
  const mountCheckout=form=>new Vue({props:{form:Object},propsData:{form},
    data(){return {...(posPayment?.options||posPayment).data.call(this),configuration:{global_discount_type_id:'03'}}},
    methods:{inputDiscountAmount:posMethods.inputDiscountAmount,changeEnabledDiscount:posMethods.changeEnabledDiscount,
      reCalculateTotal:posMethods.reCalculateTotal,calculatePayments(){}},
  });
  const checkout=mountCheckout(checkoutDraft);
  checkout.enabled_discount=true;checkout.discount_amount='10';checkout.is_discount_amount=false;
  assert(checkout.inputDiscountAmount() && checkoutDraft.total==='108','compiled POS applies the invoice percentage discount');
  checkout.$destroy();
  const checkoutReopened=mountCheckout(checkoutDraft);
  assert(checkoutReopened.enabled_discount && !checkoutReopened.is_discount_amount && checkoutReopened.discount_amount==='10',
    'compiled POS restores the invoice discount mode and input when the checkout is recreated');
  checkoutReopened.enabled_discount=false;checkoutReopened.changeEnabledDiscount();
  assert(checkoutDraft.total==='120' && !checkoutDraft.discounts.length,'compiled POS removes the invoice discount when switched off');
  checkoutReopened.$destroy();
  const garageDefinition=Vue.options.components['tenant-pos-garage'];
  const garageMethods=(garageDefinition?.options||garageDefinition)?.methods;
  assert(!!garageMethods?.updateLocalPosTotal,'compiled Garage registers its invoice amount editor');
  const gross='123456789.123456789012', unitPrice='0.987654321019';
  const garage=new Vue({
    data:()=>({localPosStockChecks:[],percentage_igv:'0.16',
      form:{document_type_id:'01',currency_type_id:'VES',exchange_rate_sale:'1',items:[
        {quantity:'1',total:gross,unit_price:unitPrice,unit_type_id:'UND',affectation_igv_type_id:'20',
          item:{id:1,aux_quantity:'1',unit_price:unitPrice,currency_type_id:'VES',has_igv:true,calculate_quantity:true}}
      ]}}),
    methods:{updateLocalPosTotal:garageMethods.updateLocalPosTotal,updateLocalPosQuantity:garageMethods.updateLocalPosQuantity,
      posStockEntry:garageMethods.posStockEntry,validatePosStockChange:()=>true,calculateTotal(){},setFormPosLocalStorage(){}},
  });
  garage.updateLocalPosTotal(0);
  const garageLine=garage.form.items[0];
  assert(garageLine.quantity==='124999998.983532811407' && !garage.localPosStockChecks[0].inputError,
    'compiled Garage accepts a valid invoice quantity with a large fraction numerator');
  assert(garageLine.total===gross && garageLine.quantity_input.amount===gross &&
    garageLine.quantity_input.unit_price.n==='987654321019' && garageLine.quantity_input.unit_price.d==='1000000000000',
    'compiled Garage preserves the entered amount and original unit price for submission');
  garage.$destroy();
  const costDraft=new Vue({
    data:()=>({localPosStockChecks:[],percentage_igv:'0.16',config:{condition_sale_purchase_price_to_item:true},
      form:{document_type_id:'01',currency_type_id:'VES',exchange_rate_sale:'40',items:[
        {quantity:'100',total:'1',unit_price:'1',unit_type_id:'UND',affectation_igv_type_id:'20',
          item:{id:1,aux_quantity:'100',unit_price:'1',currency_type_id:'USD',has_igv:true,purchase_unit_price:'0.0125'}}
      ]}}),
    methods:{updateLocalPosTotal:garageMethods.updateLocalPosTotal,posStockEntry:garageMethods.posStockEntry,
      calculateTotal(){},setFormPosLocalStorage(){}},
  });
  costDraft.updateLocalPosTotal(0);
  assert(costDraft.localPosStockChecks[0].inputError.includes('costo de compra') &&
    costDraft.form.items[0].unit_price==='1' && costDraft.form.items[0].total==='1',
    'compiled Garage rejects an invoice unit price below the converted cost and keeps the draft');
  costDraft.form.items[0].total='50';costDraft.updateLocalPosTotal(0);
  assert(!costDraft.localPosStockChecks[0].inputError && costDraft.form.items[0].unit_price==='0.5',
    'compiled Garage accepts an invoice unit price equal to the converted cost');
  costDraft.$destroy();
  for(const [name,component] of [['POS',posDefinition],['Garage',garageDefinition],['POS rápido',Vue.options.components['tenant-pos-fast']]]) {
    const options=component?.options||component,methods=options?.methods;
    assert(options?.render.toString().includes('updateUnitPriceDraft'),name+' renders the invoice price input handler');
    const refreshed=[{id:17,sale_unit_price:'0.666666666667',
      calculation_snapshot:{version:1,values:{sale_unit_price:{n:'2',d:'3'}}}}];
    const reload={form:{document_type_id:'01',currency_type_id:'VES'},resource:'pos',
      fixItems(){},filterItems(){},$http:{get:async(url,config)=>{
        assert(url==='/pos/table/items' && config.params.document_type_id==='01',name+' requests an invoice catalog reload');
        return {data:refreshed};
      }}};
    await methods.reloadDataItems.call(reload);
    assert(reload.all_items===refreshed,name+' keeps the exact warehouse price on catalog reload');
    const row={sale_unit_price:'0.666666666667',edit_sale_unit_price:'0.333333333333',purchase_unit_price:'0',
      calculation_snapshot:{values:{sale_unit_price:{n:'2',d:'3'}}}};
    const state={...methods,config:{},items:[row],form:{document_type_id:'01',currency_type_id:'VES'},
      $set(target,key,value){target[key]=value}};
    state.clickOpenInputEditUP(0);state.clickEditUnitPriceItem(0);
    assert(row.sale_unit_price==='0.666666666667' && !row.selected_price_amount && !row.edit_unit_price,
      name+' keeps the warehouse price when confirming without input');
    state.clickOpenInputEditUP(0);state.updateUnitPriceDraft(row,'0.666666666667');state.clickEditUnitPriceItem(0);
    assert(row.selected_price_amount.n==='666666666667' && row.selected_price_amount.d==='1000000000000',
      name+' records an explicitly entered price as a finite decimal');
  }
  const remainingSerials = [{id:'1'},{id:'2'},{id:'3'}];
  await lineEditor.regularizeLots.call({resource:'documents',form:{document_item_id:'9',item:{lots:remainingSerials}},
    $http:{get:async()=>({data:[]})}});
  assert(remainingSerials.length===0,'compiled credit note discards consecutive unavailable serials');
  const returnSerials=[{id:'9007199254740992'},{id:'9007199254740993'},{id:17}];
  await lineEditor.regularizeLots.call({resource:'documents',form:{document_item_id:'20',currency_type_id:'VES',item:{lots:returnSerials}},
    $http:{get:async()=>({data:[{id:'9007199254740993'},{id:'17'}, {id:Number('9007199254740993')}]})}});
  assert(returnSerials.map(row=>String(row.id)).join(',')==='9007199254740993,17',
    'compiled serial regularization keeps the exact bigint and ignores unsafe JSON numbers');
  const small={id:'17',has_sale:false}, nextSerial={id:'9007199254740993',has_sale:false};
  const picks={lotsSelected:[{id:17},{id:'9007199254740992'}],records:[small,nextSerial]};
  serialPicker.methods.checkedLot.call(picks);
  assert(small.has_sale && !nextSerial.has_sale,'compiled invoice serial picker distinguishes adjacent bigint IDs');
  serialPicker.methods.changeHasSale.call(picks,small);
  serialPicker.methods.changeHasSale.call(picks,nextSerial);
  assert(picks.lotsSelected.map(row=>row.id).join(',')==='9007199254740992,9007199254740993',
    'compiled invoice serial picker handles an equivalent small ID and preserves both bigint IDs');

  for(const [name,definition,action,delta,n,d,initial,nInitial,dInitial,amount,priceN,priceD] of [
    ['POS',posDefinition,'changeCartQuantity',1,'7','3','1.333333333333','4','3','1','3','7'],
    ['Garage',garageDefinition,'decrementItem',-1,'1','3','1.333333333333','4','3','1','3','1'],
    ['Garage subprecision',garageDefinition,'decrementItem',-1,'1','3000000000000','1','3000000000001','3000000000000','0.000000000001','3','1'],
  ]) {
    const methods=(definition?.options||definition).methods;
    const row={item_id:1,quantity:initial,unit_type_id:'KGM',affectation_igv_type_id:'20',discounts:[],charges:[],
      calculation_snapshot:{values:{quantity:{n:nInitial,d:dInitial},unit_price:{n:'3',d:'1'}},inputs:{}},
      item:{id:1,aux_quantity:initial,unit_price:'3',currency_type_id:'VES',has_igv:true}};
    let checked;
    const editor={
      ...methods,localPosStockChecks:[],percentage_igv:'0.16',
      form:{document_type_id:'01',currency_type_id:'VES',exchange_rate_sale:'1',items:[row]},
      $set(target,key,value){target[key]=value},calculateTotal(){},setFormPosLocalStorage(){},
      validatePosStockChange(_,next){checked=next.calculation_snapshot.values.quantity;return true},
    };
    editor[action](row,0,delta);
    const saved=editor.form.items[0];
    assert(checked?.n===n && checked?.d===d && saved.quantity_input?.amount===amount &&
      saved.quantity_input?.unit_price.n===priceN && saved.quantity_input?.unit_price.d===priceD,
      'compiled '+name+' quantity button checks and saves the adjusted rational quantity');
  }
  const garagePayment=(garageDefinition?.options||garageDefinition).components.FastPayment;
  const garageCheckout=garagePayment.options||garagePayment;
  const garageCustomer={all_customers:[{id:7},{id:'9007199254740992'}],form:{customer_id:7},
    customerById:garageCheckout.methods.customerById,inputDiscountAmount(){}};
  garageCheckout.watch.customer.handler.call(garageCustomer,{id:'7'});
  const keptCustomer=garageCustomer.form.customer_id===7 && garageCustomer.all_customers.length===2;
  garageCheckout.watch.customer.handler.call(garageCustomer,{id:'9007199254740993'});
  const selectedAdjacent=garageCustomer.form.customer_id==='9007199254740993' && garageCustomer.all_customers.length===3;
  garageCheckout.watch.customer.handler.call(garageCustomer,null);
  assert(keptCustomer && selectedAdjacent && garageCustomer.form.customer_id===null && garageCustomer.all_customers.length===3,
    'compiled Garage matches, selects and clears the customer while preserving bigint identity');
  for(const [name,checkoutDefinition] of [['POS',posDefinition],['Garage',garagePayment]]){
    const clickPayment=(checkoutDefinition?.options||checkoutDefinition)?.methods?.clickPayment;
    for(const row of [
      {quantity:'9007199254740993.1',unit_type_id:'UND'},
      {quantity:'1',unit_type_id:'SERV',calculation_snapshot:{values:{quantity:{n:'3000000000001',d:'3000000000000'}}}},
    ]){
      const messages=[];
      const checkout={form:{document_type_id:'01',customer_id:1,exchange_rate_sale:'1',total:'1',items:[row]},
        rowsItems:1,businessTurns:{},isPaymentCurrencyBlocked:()=>false,$message:{error:message=>messages.push(message)},
        sleep(){throw new Error('Invalid POS quantity opened payment')},
        asignPlateNumberToItems(){throw new Error('Invalid Garage quantity reached submission')}};
      await clickPayment.call(checkout);
      assert(messages.length===1 && messages[0].includes('decimales'), 'compiled '+name+' rejects a non-integer invoice quantity');
    }
  }
  const fastDefinition=Vue.options.components['tenant-pos-fast'];
  const fastOptions=fastDefinition?.options||fastDefinition;
  const fastMethods=fastOptions?.methods;
  assert(!!fastMethods?.getStatusStock && !!fastMethods?.updateLocalPosTotal,'compiled quick checkout connects invoice editing to stock validation');
  assert(/["']rows-items["']\s*:\s*(?:[\w$]+\.)?form\.items\.length/.test(fastOptions.render.toString()),
    'compiled quick checkout passes the live cart line count');
  const fastPayment=fastOptions.components.FastPayment;
  const garageRateLabel=(garagePayment.options||garagePayment).computed.ivaPercentageLabel;
  const rateLabel=(percentageIgv)=>garageRateLabel.call({form:{document_type_id:'01',currency_type_id:'VES'},percentageIgv});
  assert(rateLabel('0')==='0' && rateLabel('0.1625')==='16.25',
    'compiled Garage preserves zero and fractional IVA rates on a VES invoice');
  for(const [label,panel] of [['POS rápido',fastPayment],['Garage',garagePayment]]){
    const options=panel.options||panel;
    const state={all_series:[{id:1,document_type_id:'01'},{id:2,document_type_id:'01'}],
      form:{document_type_id:'01',currency_type_id:'VES',series_id:'2'}};
    options.methods.filterSeries.call(state,false);
    assert(state.form.series_id===2,'compiled '+label+' keeps the invoice series through an ID representation change');
  }
  for(const [name,panel] of [['POS',posPayment],['POS rápido',fastPayment],['Garage',garagePayment]]){
    const options=panel.options||panel;
    const checkoutMix=options.mixins.find(mixin=>mixin.methods?.checkoutValue);
    const checkoutSummary=new Vue({mixins:[checkoutMix],methods:{money:options.methods.money},data:()=>({
      form:{document_type_id:'01',total_taxed:'0.005',total_discount:'0',calculation_snapshot:{version:1,values:{
        total_taxed:{n:'99999999999',d:'20000000000000'},total_discount:{n:'1',d:'30000000000000'},
      }}},
    })});
    const formatted=()=>checkoutSummary.money(checkoutSummary.checkoutValue(checkoutSummary.form,'total_taxed'));
    const exactSummary=formatted()==='0.00' && checkoutSummary.checkoutHasDiscount();
    checkoutSummary.form.document_type_id='80';
    assert(options.render.toString().includes('checkoutValue') && exactSummary && formatted()==='0.01' && !checkoutSummary.checkoutHasDiscount(),
      'compiled '+name+' checkout presents the invoice fraction and preserves sales note amounts');
    checkoutSummary.$destroy();
    const form=new Vue({mixins:[settlement],methods:{enterAmount:options.methods.enterAmount,inputAmount(){}},data:()=>({
      useLocalCalculations:true,enter_amount:'0.0049999999999',
      form:{document_type_id:'01',payment_condition_id:'01',payments:[{payment:'0.01'}]},
    })});
    await form.enterAmount();
    assert(form.singleAmountInvalid && form.form.payments[0].payment==='0.01',
      'compiled '+name+' rejects a manual receipt requiring a thirteenth decimal');
    form.form.payment_condition_id='02';
    assert(!form.singleAmountInvalid,'compiled '+name+' ignores the hidden manual amount on credit');
    form.form.payment_condition_id='01';form.enter_amount='0.004999999999';
    await form.enterAmount();
    assert(!form.singleAmountInvalid && form.form.payments[0].tendered_amount==='0.004999999999',
      'compiled '+name+' preserves all twelve allowed decimal places');
    form.$destroy();
  }
  const emptyWarnings=[];
  await (fastPayment.options||fastPayment).methods.clickPayment.call({
    form:{document_type_id:'01',items:[]},rowsItems:0,businessTurns:{active:false},
    $message:{warning:message=>emptyWarnings.push(message),error:message=>emptyWarnings.push(message)},
    ensureFiscalPaymentsReady(){throw new Error('Empty quick checkout reached payment validation')},
    $http:{post(){throw new Error('Empty quick checkout reached submission')}},
  });
  assert(emptyWarnings.join('')==='Debe agregar productos','compiled quick checkout blocks an empty cart before submission');
  for (const [item, valid] of [
    [{unit_type_id:'UND',quantity:'1',calculation_snapshot:{values:{quantity:{n:'3000000000001',d:'3000000000000'}}}},false],
    [{unit_type_id:'KG',quantity:'1.5'},true],
  ]) {
    let paymentChecked=false;
    const messages=[];
    await (fastPayment.options||fastPayment).methods.clickPayment.call({
      form:{document_type_id:'01',currency_type_id:'VES',items:[item],payments:[],series_id:1,date_of_issue:dateOfIssue},
      rowsItems:1,businessTurns:{active:false},asignPlateNumberToItems:async()=>{},
      $message:{warning:message=>messages.push(message),error:message=>messages.push(message)},
      ensureFiscalPaymentsReady:async()=>{paymentChecked=true;return false;},
      $http:{post(){throw new Error('Quantity check reached submission')}},
    });
    assert(paymentChecked===valid && (valid ? messages.length===0 : messages.some(message=>message.includes('decimales'))),
      valid ? 'compiled quick checkout accepts fractional KG in a VES invoice'
        : 'compiled quick checkout rejects an UND fraction even when projected as an integer');
  }
  const stockRequests=[];
  const makeLine=(presentation)=>({item_id:1,quantity:'1',total:'3',unit_price:'3',unit_type_id:'UND',affectation_igv_type_id:'20',
    item:{id:1,aux_quantity:'1',unit_price:'3',currency_type_id:'VES',has_igv:true,calculate_quantity:true,presentation}});
  const fast=new Vue({
    data:()=>({localPosStockChecks:[],percentage_igv:'0.16',resource:'pos',
      form:{document_type_id:'01',currency_type_id:'VES',exchange_rate_sale:'1',items:[makeLine({id:7,quantity_unit:'2'}),makeLine(null)]}}),
    methods:{updateLocalPosTotal:fastMethods.updateLocalPosTotal,updateLocalPosQuantity:fastMethods.updateLocalPosQuantity,
      posStockEntry:fastMethods.posStockEntry,getStatusStock:fastMethods.getStatusStock,calculateTotal(){},setFormPosLocalStorage(){}},
    computed:{currentPosStockChecks:fastOptions.computed.currentPosStockChecks,posStockPending:fastOptions.computed.posStockPending,
      posStockError:fastOptions.computed.posStockError,posStockBlocked:fastOptions.computed.posStockBlocked},
    beforeCreate(){this.$http={get:async(url,options)=>{stockRequests.push([url,JSON.parse(options.params.quantity_exact)]);
      return {data:{success:false,message:'Sin existencias'}}}};this.$message={error(){}}},
  });
  fast.form.items[0].total='1';fast.updateLocalPosTotal(0);await wait();
  const fastDemand=stockRequests[0];
  assert(fastDemand?.[0]==='/pos/validate_stock/1/1.666666666667' && fastDemand[1].n==='5' && fastDemand[1].d==='3',
    'compiled quick checkout transports all exact physical units for the edited invoice product');
  assert(fast.posStockBlocked && fast.form.items[0].total==='1','compiled quick checkout blocks insufficient stock and retains the exact invoice total');
  fast.$destroy();
  const quickItem={id:1,item_id:1,unit_type_id:'KGM',sale_unit_price:'1',currency_type_id:'VES',has_igv:true,sale_affectation_igv_type_id:'20'};
  const quickLine={item_id:1,unit_type_id:'KGM',quantity:'0.333333333333',quantity_input:{amount:'1',unit_price:'3'},
    calculation_snapshot:{version:1,values:{quantity:{n:'1',d:'3'}}},item:{...quickItem,unit_price:'1'}};
  const incrementRequests=[];
  const quickState={resource:'pos',form:{document_type_id:'01',currency_type_id:'VES',exchange_rate_sale:'1',items:[quickLine]},
    getStatusStock:fastMethods.getStatusStock,$message:{error(){}},$http:{get:async(url,{params})=>{
      incrementRequests.push({url,quantity:JSON.parse(params.quantity_exact)});
      return {data:{success:false,message:'Sin existencias'}};
    }}};
  await fastMethods.clickAddItem.call(quickState,quickItem,0);
  const incrementDemand=incrementRequests[0];
  assert(incrementDemand?.url==='/pos/validate_stock/1/1.333333333333' && incrementDemand.quantity.n==='4' && incrementDemand.quantity.d==='3'
    && quickState.form.items[0].quantity==='0.333333333333',
    'compiled quick checkout sends the original fraction before applying an increment');
  const indexDefinition=Vue.options.components['tenant-documents-index'];
  const indexOptions=indexDefinition?.options||indexDefinition;
  const retentionDefinition=indexOptions?.components?.DocumentRetention;
  const retentionOptions=retentionDefinition?.options||retentionDefinition;
  const retentionContext={sequence:0,proposalSequence:0,documentId:1,canCreate:true,document:{},
    form:{base:'0.01',tax_kind:'IVA',currency_type_id:'VES'},
    $http:{post:async()=>({data:{data:{eligible:true,percentage:75}}})}};
  await retentionOptions.methods.evaluate.call(retentionContext);
  assert(retentionContext.form.amount==='0.01','compiled IVA retention proposal rounds to the cents required for registration');
  const detailDefinition=indexOptions?.components?.DocumentDetailDrawer;
  const detailOptions=detailDefinition?.options||detailDefinition;
  const openBalance={balance:'0',has_open_balance:true};
  assert(indexOptions.methods.hasOpenBalance(openBalance),'compiled invoice listing recognizes a pending balance represented as zero');
  const balanceView=new Vue({data:()=>({record:openBalance}),computed:{
    balanceAmount:detailOptions.computed.balanceAmount,balanceIsPaid:detailOptions.computed.balanceIsPaid,
    balanceStatusLabel:detailOptions.computed.balanceStatusLabel,
  },methods:{formatMoney:value=>value}});
  assert(balanceView.balanceStatusLabel==='0 · Pendiente','compiled invoice drawer keeps the exact positive balance pending');
  balanceView.record={balance:'0',has_open_balance:false};
  assert(balanceView.balanceStatusLabel==='0 · Pagado','compiled invoice drawer marks the cleared balance paid');
  balanceView.$destroy();
  const detail=new Vue({extends:detailDefinition,propsData:{showDrawer:true},
    data:()=>({activeTab:'totals',record:{id:1,document_type_id:'01',currency_type_id:'VES',total:'100',
      total_payable:'100',settlement_adjustment:'0',calculation_snapshot:{version:1,values:{
        settlement_adjustment:{n:'1',d:'10000000000000'}
      }}}})}).$mount();
  document.body.appendChild(detail.$el);await Vue.nextTick();
  const settlementRows=()=>Array.from(detail.$el.querySelectorAll('.detail-drawer__totals-row'))
    .filter(row=>/Ajuste de liquidación|Importe cobrable/.test(row.textContent));
  assert(settlementRows().length===2 && settlementRows()[0].textContent.includes('Bs. 1/10000000000000')
    && settlementRows()[1].textContent.includes('Bs. 100,00'),
    'compiled invoice drawer displays both settlement rows when the positive adjustment projects to zero');
  detail.record.calculation_snapshot.values.settlement_adjustment={n:'-1',d:'10000000000000'};await Vue.nextTick();
  assert(settlementRows().length===2 && settlementRows()[0].textContent.includes('Bs. -1/10000000000000'),
    'compiled invoice drawer preserves a negative settlement adjustment below twelve places');
  detail.record.calculation_snapshot.values.settlement_adjustment={n:'0',d:'1'};await Vue.nextTick();
  assert(settlementRows().length===0,'compiled invoice drawer hides settlement rows for exact zero');
  detail.$destroy();detail.$el.remove();
  const invoiceAdjustment=new Vue({computed:{invoiceSettlementAdjustmentLabel:Invoice.computed.invoiceSettlementAdjustmentLabel},
    data:()=>({form:{currency_type_id:'VES',settlement_adjustment:'0',
      calculation_snapshot:{version:1,values:{settlement_adjustment:{n:'-1',d:'10000000000000'}}}}})});
  assert(Invoice.render.toString().includes('invoiceSettlementAdjustmentLabel') && invoiceAdjustment.invoiceSettlementAdjustmentLabel==='-1/10000000000000',
    'compiled invoice summary reveals the negative adjustment when its decimal is zero');
  invoiceAdjustment.form.calculation_snapshot.values.settlement_adjustment={n:'1',d:'10000000000000'};
  assert(invoiceAdjustment.invoiceSettlementAdjustmentLabel==='1/10000000000000',
    'compiled invoice summary preserves the positive adjustment below twelve places');
  invoiceAdjustment.form.calculation_snapshot.values.settlement_adjustment={n:'0',d:'1'};
  assert(invoiceAdjustment.invoiceSettlementAdjustmentLabel===null,'compiled invoice summary hides an exactly zero adjustment');
  invoiceAdjustment.$destroy();
  const paymentDefinition=indexOptions?.components?.DocumentPayments;
  const linkDefinition=(paymentDefinition?.options||paymentDefinition)?.components?.DialogLinkPayment;
  assert(!!linkDefinition,'the compiled invoice payments view registers its payment link dialog');
  window.headers_token={};
  const LinkDialog=Vue.extend({extends:linkDefinition,methods:{getConfiguration(){},create(){}}});
  const link=new Vue({data:()=>({currencyTypeId:vesInvoicesOnly ? 'VES' : 'USD',payment:vesInvoicesOnly ? '1.20' : '0.29',exchangeRateSale:'1.5'}),
    render(h){return h(LinkDialog,{props:{showDialog:true,currencyTypeId:this.currencyTypeId,payment:this.payment,exchangeRateSale:this.exchangeRateSale}})}
  }).$mount();
  document.body.appendChild(link.$el);await Vue.nextTick();
  if(!vesInvoicesOnly) assert(link.$el.textContent.includes('Total a pagar: Bs. 0.44'),'compiled invoice link rounds USD 0.29 at 1.5 to VES 0.44');
  link.currencyTypeId='VES';link.payment='1.20';await Vue.nextTick();
  assert(link.$el.textContent.includes('Total a pagar: Bs. 1.20'),'compiled invoice link preserves its bolivar total');
  link.$destroy();link.$el.remove();
  wrapper=new Vue({data:()=>({value:'0',exactSource:null,precision:12,disabled:false,contextKey:'VES',generation:0,commits:[],cancelled:[]}),
   render(h){return h('section',[h(Input,{ref:'amount',props:{value:this.value,exactSource:this.exactSource,precision:this.precision,disabled:this.disabled,contextKey:this.contextKey,generation:this.generation,
    apply:value=>{this.commits.push({value,type:typeof value});this.value=value;this.exactSource=null;return value}},on:{cancelled:message=>this.cancelled.push(message)}})])}}).$mount('#invoice-control-root');
  const tick=()=>Vue.nextTick(),control=()=>wrapper.$refs.amount;
  const edit=async(value,commit=true)=>{const element=wrapper.$el.querySelector('input.el-input__inner');assert(!!element,'real Element UI text input mounted');
   element.focus();element.value=value;element.dispatchEvent(new Event('input',{bubbles:true}));await tick();
   if(commit){element.dispatchEvent(new Event('change',{bubbles:true}));element.blur();await tick()}};
  const reset=async(value='0',precision=12)=>{wrapper.value=value;wrapper.exactSource=null;wrapper.precision=precision;wrapper.disabled=false;wrapper.contextKey='VES';wrapper.generation++;await tick();wrapper.commits=[];wrapper.cancelled=[];};
  await edit('9007199254740993.12',false);assert(wrapper.commits.length===0 && wrapper.value==='0','typing never changes the committed invoice amount');
  assert(wrapper.$el.querySelector('input').value==='9007199254740993.12','native input retains the entire large decimal text');
  assert(control().commitPending(),'large value commits');await tick();
  assert(wrapper.value==='9007199254740993.12' && wrapper.commits[0].type==='string','large value reaches the invoice callback as exact text');
  control().commitPending();assert(wrapper.commits.length===1,'repeated blur or submit is idempotent');
  await reset();await edit('0,1');assert(wrapper.value==='0.1','decimal comma commits without Number conversion');
  for(const value of ['1,','','-1','0.1234567890123','1000000000000000000']){
   await reset();await edit(value);assert(wrapper.value==='0' && !wrapper.commits.length && control().dirty,'invalid draft never reaches the invoice calculation: '+value);
   assert(!!wrapper.$el.querySelector('[role="alert"]'),'invalid decimal has a visible error');
  }
  await reset('0',2);await edit('9007199254740993.005');assert(wrapper.value==='9007199254740993.01','two-decimal discount rounds HALF_UP using exact arithmetic');
  await reset('0.333333333333',2);control().commitPending();await tick();assert(wrapper.value==='0.333333333333' && !wrapper.commits.length,'an untouched periodic discount is not rewritten from its display');
  await reset();await edit('12',false);wrapper.contextKey=vesInvoicesOnly ? 'VES:next' : 'USD';await tick();assert(!control().commitPending() && !wrapper.commits.length,'a pending amount cannot silently change currency context');
  await edit('13');assert(wrapper.value==='13','editing in the new currency context recovers normally');
  await reset();await edit('20',false);wrapper.generation++;await tick();assert(!control().dirty && !wrapper.commits.length && wrapper.cancelled.length===1,'new document cancels the previous draft explicitly');
  await edit('30',false);wrapper.disabled=true;await tick();assert(!control().dirty && !wrapper.commits.length && wrapper.cancelled.length===2,'disabling a field cancels pending text explicitly');
  rows=new Vue({data:()=>({items:[{id:1,amount:'0'},{id:2,amount:'0'}],commits:[]}),methods:{invoiceInputKey:Invoice.methods.invoiceInputKey},
   render(h){return h('section',this.items.map(row=>h('div',{key:this.invoiceInputKey(row),attrs:{'data-line-id':row.id}},[
    h('label','Partida '+row.id),h(Input,{ref:'row'+row.id,props:{value:row.amount,apply:value=>{row.amount=value;this.commits.push({id:row.id,value});return value}}})])))}}).$mount('#invoice-rows-root');
  const second=rows.$refs.row2,element=second.$el.querySelector('input');element.value='5';element.dispatchEvent(new Event('input',{bubbles:true}));await tick();
  rows.items.reverse();await tick();assert(rows.$refs.row2===second && second.draft==='5','pending line discount follows its own component after row reorder');
  assert(second.commitPending(),'reordered line commits');await tick();assert(rows.items.find(row=>row.id===2).amount==='5' && rows.items.find(row=>row.id===1).amount==='0','reordering never applies a draft to another product');
  assert(!JSON.stringify(rows.items).includes('invoice.input.identity'),'private row identity is absent from API JSON');
  await reset();wrapper.exactSource={n:'1',d:'3000000000000'};await tick();
  assert(control().commitPending() && !wrapper.commits.length,'untouched subprecision source is preserved');
  await edit('0');assert(wrapper.commits.length===1 && wrapper.exactSource===null,'explicit zero clears a positive subprecision source');
  if(!vesInvoicesOnly) {
    const invoice={...Invoice.methods,form:{currency_type_id:'VES',exchange_rate_sale:'3',items:[],charges:[]},
     total_global_discount:'0.01',total_global_charge:'0.01',is_amount:true,config:{active_allowance_charge:false},currency_types:[{id:'VES'},{id:'USD'}],
     $refs:{},$message:{error:message=>{throw new Error(message)}},calculateTotal:()=>true};
    assert(invoice.changeInvoiceCurrencyType('USD'),'compiled invoice changes currency');
    assert(invoice.invoiceGlobalDiscountExact().fraction().n==='1' && invoice.invoiceGlobalDiscountExact().fraction().d==='300','compiled discount retains its conversion fraction');
    assert(invoice.invoiceGlobalChargeExact().fraction().n==='1' && invoice.invoiceGlobalChargeExact().fraction().d==='300','compiled charge retains its conversion fraction');
    assert(invoice.changeInvoiceCurrencyType('VES') && invoice.total_global_discount==='0.01' && invoice.total_global_charge==='0.01','compiled currency round trip preserves both original amounts');
  }
  await reset();await edit('9007199254740993.12');await wait();
  const noteComponent=Vue.options.components['tenant-documents-note'];
  const note=noteComponent?.options||noteComponent;
  assert(typeof note?.computed?.series==='function','compiled fiscal note registers its series selector');
  const branch='9007199254740993';
  const series=(id,establishment_id)=>({id,establishment_id,document_type_id:'07'});
  const draft={form:{document_type_id:'07'},document:{establishment_id:branch},tables:{
   series:[series('9007199254741002','9007199254740992'),series('9007199254741003',branch),series('9007199254741004',Number(branch))]
  },credit:true,changeDate(){}};
  Object.defineProperty(draft,'series',{get:()=>note.computed.series.call(draft)});
  assert(draft.series.length===1 && draft.series[0].id==='9007199254741003','compiled note distinguishes adjacent bigint branch identifiers');
  note.methods.changeType.call(draft);
  assert(draft.form.series_id==='9007199254741003','compiled note keeps the selected bigint series identifier for submission');
  const mountDraft=async(type,reason,source)=>{
   const posted=[];
   const stub={render(h){return h('span')}};
   const container=document.createElement('div');document.body.appendChild(container);
   const vm=new Vue({render:note.render,staticRenderFns:note.staticRenderFns,computed:note.computed,methods:note.methods,
    components:{DocumentFormItem:stub,DocumentOptions:stub},
    data:()=>({document:{id:1,series:'FF01',number:1,establishment_id:1,currency_type_id:'VES'},
     context:{items:[source],notes:[],can_create:true},form:{document_type_id:type,note_credit_or_debit_type_id:reason,series_id:4,items:[{affected_document_item_id:1,quantity:'1',adjustment_amount:'0.01',serial_ids:[],lot_returns:null}]},
     tables:{series:[{id:4,establishment_id:1,number:'NOTA',document_type_id:type}],note_credit_types:[],note_debit_types:[]},
     configuration:{},authUser:{type:'admin'},percentage_igv:16,errors:{},loading:false,busy:false,showItem:false,showOptions:false,newId:null}),
    beforeCreate(){this.$http={async post(path,payload){posted.push({path,payload:JSON.parse(JSON.stringify(payload))});throw {response:{status:422,data:{errors:{}}}}}}}
   }).$mount(container);
   await Vue.nextTick();
   const form=vm.$el.querySelector('form'), rowInput=index=>form.querySelectorAll('tbody input.el-input__inner')[index];
   const enter=async(index,value)=>{const input=rowInput(index);input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));await Vue.nextTick();assert(input.value===value,'fiscal note input preserves '+value+' as text');};
   const submit=async()=>{form.querySelector('button[type=submit]').click();await Vue.nextTick();assert(posted.length===1,'actual note button submits once');assert(posted[0].path==='/documents','note uses its real post route');return posted[0].payload.items[0];};
   return {form,enter,submit,vm};
  };
  const sourceItem={id:1,description:'Partida temporal',unit_type_id:'UND',remaining_quantity:'1',remaining_total:'10',serials:[],lots:[{id:19,quantity:'1'}]};
  const returning=await mountDraft('07','07',sourceItem);
  await returning.enter(1,'0.000000000001');await returning.enter(2,'0.000000000001');
  assert(returning.form.checkValidity(),'credit form admits one trillionth of an item and lot');
  const returnPayload=await returning.submit();
  assert(returnPayload.quantity==='0.000000000001' && returnPayload.lot_returns[0].compromise_quantity==='0.000000000001','credit posts exact quantity and lot as text');
  returning.vm.$destroy();
  const adjustment=await mountDraft('08','02',{...sourceItem,lots:[]});
  await adjustment.enter(1,'0.001');assert(adjustment.form.checkValidity(),'debit form admits a subcent adjustment');
  const adjustmentPayload=await adjustment.submit();assert(adjustmentPayload.adjustment_amount==='0.001','debit posts exact adjustment text');
  await adjustment.enter(1,'9007199254740993.000000000001');assert(adjustment.form.checkValidity(),'debit form admits an exact decimal beyond safe integers');
  adjustment.vm.$destroy();
  const mountService=async(total='0.333333333333',fraction={n:'1',d:'3'})=>{
   const service=await mountDraft('08','01',sourceItem);
   service.vm.form.items=[];
   service.vm.addService({item:{id:2,description:'Servicio temporal',unit_type_id:'SERV'},item_id:2,
    total,calculation_snapshot:{values:{total:fraction}}});
   await Vue.nextTick();
   return service;
  };
  const originalService=await mountService();
  const servicePayload=await originalService.submit();
  assert(servicePayload.quantity===1 && servicePayload.adjustment_amount_exact.n==='1' && servicePayload.adjustment_amount_exact.d==='3',
   'debit sends the original service fraction as a single fiscal unit');
  originalService.vm.$destroy();
  const editedService=await mountService();
  await editedService.enter(0,'0.5');await editedService.enter(0,'0.333333333333');
  const editedPayload=await editedService.submit();
  assert(editedPayload.adjustment_amount==='0.333333333333' && editedPayload.adjustment_amount_exact===null,
   'editing back to the same visible decimal discards the old service fraction');
  editedService.vm.$destroy();
  const tinyService=await mountService('0',{n:'1',d:'3000000000000'});
  assert(tinyService.form.checkValidity(),'debit form admits a positive service below twelve decimal places');
  const tinyPayload=await tinyService.submit();
  assert(tinyPayload.adjustment_amount==='0' && tinyPayload.adjustment_amount_exact.d==='3000000000000',
   'the native debit form submits the positive service fraction despite its zero projection');
  tinyService.vm.$destroy();
  assert(!window.invoiceErrors.length,'no browser or Vue rendering errors');
  document.getElementById('sab-invoice-result').textContent=JSON.stringify({ok:true,checks,errors:window.invoiceErrors});
 }catch(error){document.getElementById('sab-invoice-result').textContent=JSON.stringify({ok:false,checks,error:String(error),errors:window.invoiceErrors});}
});
</script></body></html>'''.replace('CSS', css).replace('ENTRY', entry['file']).replace('__VES_INVOICES_ONLY__', json.dumps(args.ves_invoices_only))
    unexpected = []

    class Handler(http.server.BaseHTTPRequestHandler):
        def send_bytes(self, content, content_type='application/json; charset=utf-8', code=200):
            self.send_response(code)
            self.send_header('Content-Type', content_type)
            self.send_header('Content-Length', str(len(content)))
            self.send_header('Content-Security-Policy', "connect-src 'self';")
            self.end_headers()
            try:
                self.wfile.write(content)
            except BrokenPipeError:
                pass

        def do_GET(self):
            path = urllib.parse.urlsplit(self.path).path
            if path == '/':
                self.send_bytes(page.encode(), 'text/html; charset=utf-8')
                return
            if path == '/favicon.ico':
                self.send_bytes(b'', code=204)
                return
            relative = path.lstrip('/')
            target = (public / relative).resolve()
            if (relative.startswith('build/assets/') or relative == 'porto-light/vendor/jquery/jquery.js') and target.is_relative_to(public) and target.is_file():
                self.send_bytes(target.read_bytes(), mimetypes.guess_type(target)[0] or 'application/octet-stream')
                return
            unexpected.append({'method': 'GET', 'path': path})
            self.send_bytes(b'{"error":"unexpected_fixture_request"}', code=404)

        def reject_write(self):
            unexpected.append({'method': self.command, 'path': urllib.parse.urlsplit(self.path).path})
            self.send_bytes(b'{"error":"no_commercial_endpoints"}', code=405)

        do_POST = reject_write
        do_PUT = reject_write
        do_DELETE = reject_write

        def log_message(self, *unused):
            pass

    args.output.parent.mkdir(parents=True, exist_ok=True)
    server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), Handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    try:
        with tempfile.TemporaryDirectory(prefix='sab-invoice-browser-') as profile:
            process = subprocess.run([browser, '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage',
                '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
                '--user-data-dir=' + profile, '--virtual-time-budget=24000', '--window-size=1200,1600',
                '--screenshot=' + str(screenshot), '--dump-dom', 'http://127.0.0.1:' + str(server.server_port) + '/'],
                capture_output=True, text=True, timeout=55)
        match = re.search(r'<pre id="sab-invoice-result">(.*?)</pre>', process.stdout, re.S)
        result = json.loads(html.unescape(match[1])) if match and match[1] != 'pending' else {
            'ok': False, 'error': 'El navegador no terminó las comprobaciones.'}
        result.update(exit_code=process.returncode, unexpected_requests=unexpected, source_sha256=before['sha256'],
                      source_unchanged=before['sha256'] == source_manifest()['sha256'],
                      manifest_sha256=hashlib.sha256(manifest_path.read_bytes()).hexdigest(), entry=entry['file'],
                      screenshot=str(screenshot.relative_to(ROOT)),
                      browser=subprocess.check_output([browser, '--version'], text=True).strip(),
                      databases='none; isolated loopback component fixture',
                      invoice_currency_scope='VES' if args.ves_invoices_only else 'VES and USD',
                      excluded_usd_invoice_scenarios=[
                          'fixed line discount (VES catalog price)', 'fixed line discount (USD catalog price)',
                          'payment link on a USD invoice', 'document currency round trip',
                      ] if args.ves_invoices_only else [],
                      scope='Compiled invoice and fiscal note decimal controls in Chrome, using loopback and synthetic browser data.')
        result['ok'] = result['ok'] and process.returncode == 0 and not unexpected and result['source_unchanged']
        if not result['ok']:
            args.output.with_suffix('.chrome.log.gz').write_bytes(gzip.compress(process.stderr.encode(), mtime=0))
            args.output.with_suffix('.html.gz').write_bytes(gzip.compress(process.stdout.encode(), mtime=0))
        args.output.write_text(json.dumps(result, indent=2, ensure_ascii=False) + '\n')
        print(json.dumps(result, ensure_ascii=False))
        return 0 if result['ok'] else 1
    finally:
        server.shutdown()
        server.server_close()
        thread.join()


if __name__ == '__main__':
    raise SystemExit(main())
