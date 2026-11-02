const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs')
const path=require('node:path')
const vm=require('node:vm')
const babel=require('@babel/core')
const compiler=require('vue-template-compiler')
const load=require('./helpers/load-module.cjs')

for(const [moduleName,filename] of [
    ['Suscription','modules/Suscription/Resources/assets/js/payment_receipt/partials/payments.vue'],
    ['FullSuscription','modules/FullSuscription/Resources/assets/js/payment_receipt/partials/payments.vue'],
    ['Nota de venta','resources/js/views/tenant/sale_notes/partials/payments.vue'],
]) {
    const source=compiler.parseComponent(fs.readFileSync(filename,'utf8'))
    const exported={}
    const code=babel.transformSync(source.script.content,{configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs']}).code
    vm.runInNewContext(code,{exports:exported,require:name=>{
        if(name.endsWith('.vue')) return {}
        if(name.includes('mixins/deletable')) return {deletable:{}}
        if(name.startsWith('@helpers/')) return load(path.resolve('resources/js/helpers',name.slice('@helpers/'.length)+'.js'))
        if(name.startsWith('.')) return load(path.resolve(path.dirname(filename),name+'.js'))
        return require(name)
    }})
    const submit=exported.default.methods.clickSubmit
    const fixture=()=>{
        const requests=[],errors=[]
        const row={id:null,date_of_payment:'2026-10-01',payment_method_type_id:'13',payment_destination_id:2,
            currency_type_id:'USD',original_amount:'2.50',exchange_rate:'40',payment:'100',errors:{},loading:false}
        let complete
        const ctx={records:[row],documentId:9,resource:'sale_note_payments',document:{currency_type_id:'VES',total_difference:'100'},
            $message:{error:message=>errors.push(message),success(){}},$eventHub:{$emit(){}},
            $http:{post:(url,data)=>{requests.push({url,data});return new Promise(resolve=>{complete=resolve})}}}
        return {ctx,row,requests,errors,finish:()=>complete({data:{success:false,message:'No guardado'}})}
    }
    test(`${moduleName} posts the original USD receipt and prevents a double click`,async()=>{
        const {ctx,row,requests,errors,finish}=fixture()
        submit.call(ctx,0);submit.call(ctx,0)
        assert.equal(requests.length,1)
        assert.equal(requests[0].data.currency_type_id,'USD')
        assert.equal(requests[0].data.original_amount,'2.50')
        assert.equal(requests[0].data.exchange_rate,'40')
        assert.equal(requests[0].data.payment,'100.00')
        assert.equal(requests[0].data.paid,true)
        finish();await new Promise(resolve=>setImmediate(resolve))
        assert.equal(row.loading,false)
        assert.deepEqual(errors,['No guardado'])
        assert.deepEqual(compiler.compile(source.template.content).errors,[])
    })
    test(`${moduleName} validates the applied amount derived from received money`,()=>{
        const {ctx,row,requests,errors}=fixture()
        row.original_amount='3';row.payment='1'
        submit.call(ctx,0)
        assert.equal(requests.length,0)
        assert.match(errors[0],/supera al monto pendiente/)
    })
    test(`${moduleName} retains and displays wrapped currency validation errors`,async()=>{
        const {ctx,row,errors}=fixture()
        ctx.$http.post=()=>Promise.reject({response:{status:422,data:{errors:{currency_type_id:['Moneda no disponible']}}}})
        submit.call(ctx,0)
        await new Promise(resolve=>setImmediate(resolve))
        assert.equal(row.errors.currency_type_id[0],'Moneda no disponible')
        assert.deepEqual(errors,['Moneda no disponible'])
        assert.equal(row.loading,false)
    })
}
