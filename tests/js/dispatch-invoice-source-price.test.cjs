const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs'),vm=require('node:vm'),babel=require('@babel/core')

const code=babel.transformSync(fs.readFileSync('resources/js/helpers/confirm-invoice-source-price.js','utf8'),{
    configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']
}).code
const exportsObject={}
vm.runInNewContext(code,{exports:exportsObject,window:{salesDocumentsVes:true}})
const {confirmInvoiceSourcePrice}=exportsObject

test('delivery order invoices confirm the current rate without inventing an origin total',async()=>{
    for(const source of [{dispatch_id:'9'},{dispatches_relateds:['TT01-1','TT01-2']}]){
        let message='',posted=0
        const vmState={$http:{post:async(url,draft)=>{posted++;assert.equal(url,'/documents/source-price-preview');assert.equal(draft.document_type_id,'01');return {data:{data:{source_total:null,rate:'4',total:'2',applied:'0',balance:'2',confirmation:'signed'}}}}},
            $confirm:async text=>{message=text},$message:{error:()=>{throw new Error('unexpected error')}}}
        const result=await confirmInvoiceSourcePrice(vmState,{document_type_id:'01',...source})
        assert.equal(posted,1);assert.equal(result.source_price_confirmation,'signed')
        assert.match(message,/Tasa: Bs\. 4/);assert.doesNotMatch(message,/Origen: Bs\./)
    }
})
