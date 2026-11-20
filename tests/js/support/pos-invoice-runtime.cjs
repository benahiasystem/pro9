const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const compiler=require('vue-template-compiler'),parser=require('@babel/parser'),generate=require('@babel/generator').default
const load=require('../helpers/load-module.cjs'),math=load(path.resolve('resources/js/helpers/exchange-rate-math.js'))
const pos=load(path.resolve('resources/js/helpers/pos-invoice-line.js'))
const {calculateRowItem}=load(path.resolve('resources/js/helpers/functions.js'))
const {ensurePosRate}=load(path.resolve('resources/js/helpers/pos-exchange-rate.js'))
const cart=load(path.resolve('resources/js/mixins/pos-cart-additions.js')).default
function methods(file,names) {
    const c=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/pos/'+file+'.vue','utf8'))
    const tree=parser.parse(c.script.content,{sourceType:'module'})
    const entries=tree.program.body.find(n=>n.type==='ExportDefaultDeclaration').declaration.properties.find(n=>n.key?.name==='methods').value.properties
    const result=vm.runInNewContext(`({${entries.filter(n=>names.includes(n.key?.name)).map(n=>generate(n).code).join(',')}})`,{...math,...pos,ensurePosRate,calculateRowItem,_:require('lodash')})
    assert.deepEqual(compiler.compile(c.template.content).errors,[])
    return result
}
function context(file) {
    const errors=[]
    const localStock = file === 'fast' ? {} : {...cart.data(), ...cart.methods, localPosStockChecks: []}
    return {...localStock, ...methods(file,['setPriceItem','clickAddItem','clickOpenInputEditUP','updateUnitPriceDraft','clickEditUnitPriceItem','clickCancelUnitPriceItem','money','rowUnitPrice','rowTotal','selectedItemPrice','itemSetSaleUnitPrice','ChangeSelectedPrice','cartQty','cartQtyLabel']),
        form:{document_type_id:'01',currency_type_id:'VES',exchange_rate_sale:'12.5',items:[]},form_item:{},items:[],percentage_igv:'0.16',
        affectation_igv_types:[{id:'10'},{id:'20'}],configuration:{},config:{},$set:(o,k,v)=>o[k]=v,
        $message:{error:m=>errors.push(m),success:()=>{}},$notify:()=>({close(){}}),errors,
        async getStatusStock(id,quantity){assert.equal(file,'fast','POS y Garage deben comprobar el stock local');this.checkedQuantity=math.exactAmount(quantity).decimal();this.checkedQuantityExact=quantity;return {success:true}},
        cleanInput(){},initFocus(){},initElectronicScaleData(){},setScaleQuantityIfNotExistItem(){},calculateTotal(){},setFormPosLocalStorage(){},setDefaultDataPriceSelected(){}}
}
module.exports={methods,context,math,pos,calculateRowItem}
