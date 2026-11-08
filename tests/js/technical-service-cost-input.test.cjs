const {test}=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs')
const vm=require('node:vm')
const compiler=require('vue-template-compiler')
const parser=require('@babel/parser')
const generate=require('@babel/generator').default
const load=require('./helpers/load-module.cjs')
const source=compiler.parseComponent(fs.readFileSync('modules/Sale/Resources/assets/js/views/technical-services/form.vue','utf8'))
const tree=parser.parse(source.script.content,{sourceType:'module'})
const component=tree.program.body.find(node=>node.type==='ExportDefaultDeclaration').declaration
const methods=component.properties.find(node=>node.key.name==='methods').value
const globals={...load('resources/js/helpers/exchange-rate-math.js'),...load('resources/js/helpers/payment-classification.js'),
    ...load('resources/js/helpers/technical-service-totals.js'),...load('resources/js/helpers/functions.js')}
const get=name=>vm.runInNewContext(`({${generate(methods.properties.find(node=>node.key?.name===name)).code}})[${JSON.stringify(name)}]`,globals)

test('service cost remains text and its total adds decimals exactly',()=>{
    const ctx={form:{cost:'9007199254740993.01',total:'0.02'}}
    get('updateCost').call(ctx)
    assert.equal(ctx.total,'9007199254740993.03')
    assert.equal(ctx.form.cost,'9007199254740993.01')
    get('updateCost').call(ctx,'100.25')
    assert.equal(ctx.total,'100.27')
    assert.match(source.template.content,/<el-input\s+v-model="form\.cost"/)
    assert.deepEqual(compiler.compile(source.template.content).errors,[])
})

test('service rejects a greater prepayment and invalid values without posting',()=>{
    const errors=[]
    const ctx={form:{cost:'9007199254740993.01',prepayment:'9007199254740993.02'},
        ensureFormArraysExist(){},$message:{error:message=>errors.push(message)},
        calculateTotal(){throw new Error('must not submit')}}
    get('submit').call(ctx)
    assert.match(errors.pop(),/mayor al costo/)
    ctx.form.prepayment='-1';get('submit').call(ctx)
    assert.match(errors.pop(),/válidos y no negativos/)
    ctx.form.prepayment='0';ctx.form.cost='1.0000000000001';get('submit').call(ctx)
    assert.match(errors.pop(),/válidos y no negativos/)
})

test('service displays server errors for items and retains the draft',async()=>{
    const errors=[],form={cost:'1',prepayment:'0',items:[{id:4}]}
    const ctx={form,resource:'technical-services',ensureFormArraysExist(){},calculateTotal(){},
        $message:{error:message=>errors.push(message)},
        $http:{post:async()=>{throw {response:{status:422,data:{message:'Datos inválidos',errors:{items:['La partida no pertenece al servicio.']}}}}}}}
    get('submit').call(ctx)
    await new Promise(resolve=>setImmediate(resolve))
    assert.equal(errors[0],'La partida no pertenece al servicio.')
    assert.equal(ctx.errors.items[0],errors[0])
    assert.equal(ctx.form,form)
    assert.equal(ctx.form.items[0].id,4)
    assert.equal(ctx.loading_submit,false)
})

test('service recalculates exact items and retains saved header adjustments',()=>{
    const line=globals.calculateRowItem({item:{id:1,unit_price:'116',currency_type_id:'VES'},quantity:'1',unit_price:'116',
        affectation_igv_type_id:'10',discounts:[],charges:[]},'VES','40','0.16')
    const discount={discount_type_id:'02',amount:'10'},charge={charge_type_id:'50',amount:'5'}
    const ctx={form:{cost:'116',items:[line],discounts:[discount],charges:[charge],total_prepayment:'0'},
        config:{active_allowance_charge:false},percentage_igv:'0.16',enabled_discount_global:false,prepayment_deduction:false,
        setTotalDefaultPayment(){},setPendingAmount(){},calculateFee(){}}
    ctx.updateCost=get('updateCost').bind(ctx)
    get('calculateTotal').call(ctx)
    assert.equal(ctx.form.total,'109.4')
    assert.equal(ctx.form.total_igv,'30.4')
    assert.equal(ctx.form.total_taxed,'190')
    assert.equal(ctx.total,'225.4')
    assert.equal(ctx.form.discounts[0],discount)
    assert.equal(ctx.form.charges[0],charge)
})

test('service total keeps half cents until the display boundary',()=>{
    const line=globals.calculateRowItem({item:{id:1,unit_price:'0.005',currency_type_id:'VES'},quantity:'1',unit_price:'0.005',
        affectation_igv_type_id:'20',discounts:[],charges:[]},'VES','40','0.16')
    const ctx={form:{cost:'0.10',items:[line],discounts:[],charges:[],total_prepayment:'0'},
        config:{active_allowance_charge:false},percentage_igv:'0.16',enabled_discount_global:false,prepayment_deduction:false,
        setTotalDefaultPayment(){},setPendingAmount(){},calculateFee(){}}
    ctx.updateCost=get('updateCost').bind(ctx)
    get('calculateTotal').call(ctx)
    assert.equal(ctx.form.total,'0.005')
    assert.equal(ctx.total,'0.105')
})

test('configured service charge uses the exact product total',()=>{
    const line=globals.calculateRowItem({item:{id:1,unit_price:'116',currency_type_id:'VES'},quantity:'1',unit_price:'116',
        affectation_igv_type_id:'10',discounts:[],charges:[]},'VES','40','0.16')
    const ctx={form:{cost:'0',items:[line],discounts:[],charges:[],total_prepayment:'0'},
        config:{active_allowance_charge:true,percentage_allowance_charge:'5'},percentage_igv:'0.16',
        enabled_discount_global:false,prepayment_deduction:false,setTotalDefaultPayment(){},setPendingAmount(){},calculateFee(){}}
    ctx.updateCost=get('updateCost').bind(ctx)
    get('calculateTotal').call(ctx)
    assert.equal(ctx.form.charges[0].amount,'5.8')
    assert.equal(ctx.form.total,'121.8')
    assert.equal(ctx.total,'121.8')
})

test('percentage discount preserves a periodic taxable base',()=>{
    const line=globals.calculateRowItem({item:{id:1,unit_price:'1',currency_type_id:'VES'},quantity:'0.333333333333',unit_price:'1',
        quantity_input:{amount:'1',unit_price:'3'},affectation_igv_type_id:'10',discounts:[],charges:[]},'VES','40','0.16')
    const ctx={form:{cost:'0',items:[line],discounts:[],charges:[],total_prepayment:'0'},
        config:{active_allowance_charge:false},percentage_igv:'0.16',enabled_discount_global:true,total_global_discount:'10',is_amount:false,
        prepayment_deduction:false,setTotalDefaultPayment(){},setPendingAmount(){},calculateFee(){}}
    ctx.updateCost=get('updateCost').bind(ctx)
    get('calculateTotal').call(ctx)
    assert.equal(ctx.form.total,'0.3')
    assert.equal(ctx.form.discounts[0].percentage,'10')
    assert.equal(ctx.form.discounts[0].amount_exact.n,'5')
    assert.equal(ctx.form.discounts[0].amount_exact.d,'174')
})

test('automatic percentage charge keeps a periodic base exactly',()=>{
    const line=globals.calculateRowItem({item:{id:1,unit_price:'1',currency_type_id:'VES'},quantity:'0.333333333333',unit_price:'1',
        quantity_input:{amount:'1',unit_price:'3'},affectation_igv_type_id:'10',discounts:[],charges:[]},'VES','40','0.16')
    const ctx={form:{cost:'0',items:[line],discounts:[],charges:[],total_prepayment:'0'},
        config:{active_allowance_charge:true,percentage_allowance_charge:'5'},percentage_igv:'0.16',
        enabled_discount_global:false,prepayment_deduction:false,setTotalDefaultPayment(){},setPendingAmount(){},calculateFee(){}}
    ctx.updateCost=get('updateCost').bind(ctx)
    get('calculateTotal').call(ctx)
    assert.equal(ctx.form.total,'0.35')
    assert.equal(ctx.form.charges[0].amount_exact.n,'1')
    assert.equal(ctx.form.charges[0].amount_exact.d,'60')
})

test('saved automatic charge recalculates even if the company setting is later disabled',()=>{
    const line=globals.calculateRowItem({item:{id:1,unit_price:'58',currency_type_id:'VES'},quantity:'1',unit_price:'58',
        affectation_igv_type_id:'10',discounts:[],charges:[]},'VES','40','0.16')
    const ctx={form:{cost:'0',items:[line],discounts:[],charges:[{charge_type_id:'50',amount:'5.8',percentage:'5',service_auto_percentage:true}],total_prepayment:'0'},
        config:{active_allowance_charge:false},percentage_igv:'0.16',enabled_discount_global:false,prepayment_deduction:false,
        setTotalDefaultPayment(){},setPendingAmount(){},calculateFee(){}}
    ctx.updateCost=get('updateCost').bind(ctx)
    get('calculateTotal').call(ctx)
    assert.equal(ctx.form.charges[0].amount,'2.9')
    assert.equal(ctx.form.total,'60.9')
    ctx.config={active_allowance_charge:true,percentage_allowance_charge:'10'}
    get('calculateTotal').call(ctx)
    assert.equal(ctx.form.charges[0].amount,'2.9')
    assert.equal(ctx.form.total,'60.9')
})

test('changing service cost recalculates a saved percentage discount',()=>{
    const ctx={form:{cost:'0',items:[],discounts:[{discount_type_id:'02',amount:'0',percentage:'10',is_amount:false}],charges:[],total_prepayment:'0'},
        config:{active_allowance_charge:false},percentage_igv:'0.16',enabled_discount_global:false,prepayment_deduction:false,
        setTotalDefaultPayment(){},setPendingAmount(){},calculateFee(){}}
    ctx.updateCost=get('updateCost').bind(ctx)
    ctx.calculateTotal=get('calculateTotal').bind(ctx)
    get('onCostInput').call(ctx,'116')
    assert.equal(ctx.form.discounts[0].amount,'10')
    assert.equal(ctx.total,'104.4')
    get('onCostInput').call(ctx,'1.')
    assert.equal(ctx.form.cost,'1.')
    assert.equal(ctx.total,'104.4')
})
