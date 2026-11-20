const {test,before}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
let helper;
before(async()=>{
    const exact=fs.readFileSync('resources/js/helpers/exchange-rate-math.js','utf8');
    const url='data:text/javascript;base64,'+Buffer.from(exact).toString('base64');
    const source=fs.readFileSync('resources/js/helpers/fiscal-note.js','utf8').replace('./exchange-rate-math',url);
    helper=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64'));
});
test('series accept empty and arbitrary codes within the original branch',()=>{
    const rows=[{id:1,document_type_id:'07',establishment_id:1,number:''},{id:2,document_type_id:'07',establishment_id:1,number:'NC-A'},
        {id:3,document_type_id:'07',establishment_id:2,number:''},{id:4,document_type_id:'08',establishment_id:1,number:'D'}];
    assert.deepEqual(helper.noteSeries(rows,'07',1).map(r=>r.id),[1,2]);
});
test('series distinguish adjacent PostgreSQL branch IDs above the safe integer limit',()=>{
    const branch='9007199254740993', previous='9007199254740992', maximum='9223372036854775807';
    const series=(id,establishment_id,options={})=>({id,establishment_id,document_type_id:'07',...options});
    const rows=[series(1,previous),series(2,branch),series(3,branch,{contingency:true}),
        series(4,branch,{document_type_id:'08'}),series(5,Number(branch)),series(6,null),
        series(7,maximum),series(8,'9223372036854775808'),series(9,12),series(10,'12')];
    assert.deepEqual(helper.noteSeries(rows,'07',branch).map(r=>r.id),[2]);
    assert.deepEqual(helper.noteSeries(rows,'08',branch).map(r=>r.id),[4]);
    assert.deepEqual(helper.noteSeries(rows,'07',maximum).map(r=>r.id),[7]);
    assert.deepEqual(helper.noteSeries(rows,'07','9223372036854775808'),[]);
    assert.deepEqual(helper.noteSeries(rows,'07',Number(branch)),[]);
    assert.deepEqual(helper.noteSeries(rows,'07',null),[]);
    assert.deepEqual(helper.noteSeries(rows,'07','12').map(r=>r.id),[9,10]);
    assert.deepEqual(helper.noteSeries(rows,'07',12).map(r=>r.id),[9,10]);
});
test('partial return uses the remaining amount after prior price credit',()=>{
    assert.equal(helper.noteTotal({document_type_id:'07',note_credit_or_debit_type_id:'07',items:[{affected_document_item_id:1,quantity:1}]},
        {items:[{id:1,remaining_quantity:2,remaining_total:'208.80'}]}),'104.4');
});
test('monetary adjustment sends the delta once and full credit uses the server limit',()=>{
    assert.equal(helper.noteTotal({document_type_id:'08',note_credit_or_debit_type_id:'02',items:[{adjustment_amount:'11.60'},{adjustment_amount:'0.03'}]}, {items:[]}),'11.63');
    assert.equal(helper.noteTotal({document_type_id:'07',note_credit_or_debit_type_id:'01',items:[]},{items:[],credit_available:232}),232);
});
test('debit service preview adds the original fractions and respects edited amounts', () => {
    const form={document_type_id:'08',note_credit_or_debit_type_id:'01',
        items:Array.from({length:3},()=>({adjustment_amount:'0.333333333333',adjustment_amount_exact:{n:'1',d:'3'}}))};
    const context={items:[]};
    assert.equal(helper.noteTotalLabel(form,context),'1');
    form.items[0].adjustment_amount='0.5';
    assert.equal(helper.noteTotalLabel(form,context),'1.166666666667');
    form.items=[{adjustment_amount:'0',adjustment_amount_exact:{n:'1',d:'3000000000000'}}];
    assert.equal(helper.noteTotalLabel(form,context),'<0.000000000001');
    assert.equal(helper.noteCanGenerate(form,context),true);
});
test('a positive amount below twelve decimals remains visible and can be credited',()=>{
    const context={items:[],credit_available:'0',calculation_snapshot:{values:{credit_available:{n:'1',d:'30000000000000'}}}};
    const form={document_type_id:'07',note_credit_or_debit_type_id:'01',items:[]};
    assert.equal(helper.noteAmountLabel(context,'credit_available'),'<0.000000000001');
    assert.equal(helper.noteCanGenerate(form,context),true);
    assert.equal(helper.noteCanGenerate(form,{items:[],credit_available:'0'}),false);
    const item={remaining_quantity:'0',calculation_snapshot:{values:{remaining_quantity:{n:'1',d:'3000000000000'}}}};
    assert.equal(helper.noteAmountLabel(item,'remaining_quantity'),'<0.000000000001');
    assert.equal(helper.noteCanReturnPartially(item),false);
    assert.equal(helper.noteCanReturnPartially({remaining_quantity:'0.000000000001'}),true);
    assert.equal(helper.noteCanGenerate(form,{items:[item],credit_available:'0'}),false);
    assert.equal(helper.noteTotalLabel(form,context),'<0.000000000001');
    assert.equal(helper.noteAmountLabel({quantity:'0',calculation_snapshot:{values:{quantity:{n:'1',d:'3000000000000'}}}},'quantity'),'<0.000000000001');
});
test('the note component presents and permits an exact residual without using its rounded zero',()=>{
    const vm=require('node:vm'), babel=require('@babel/core'), compiler=require('vue-template-compiler');
    const parsed=compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/note.vue','utf8'));
    assert.deepEqual(compiler.compile(parsed.template.content).errors,[]);
    const module={exports:{}};
    vm.runInNewContext(babel.transformSync(parsed.script.content,{configFile:false,babelrc:false,
        plugins:['@babel/plugin-transform-modules-commonjs']}).code,{module,exports:module.exports,
        require:path=>path.includes('fiscal-note') ? helper : {}});
    const component=module.exports.default;
    const state={form:{document_type_id:'07',note_credit_or_debit_type_id:'01',items:[]},
        context:{credit_available:'0',items:[],calculation_snapshot:{values:{credit_available:{n:'1',d:'30000000000000'}}}}};
    assert.equal(component.computed.canGenerate.call(state),true);
    assert.equal(component.computed.total.call(state),'<0.000000000001');
    assert.match(parsed.template.content,/:disabled="[^"]*!canGenerate/);
});
