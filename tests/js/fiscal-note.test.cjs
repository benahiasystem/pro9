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
test('partial return uses the remaining amount after prior price credit',()=>{
    assert.equal(helper.noteTotal({document_type_id:'07',note_credit_or_debit_type_id:'07',items:[{affected_document_item_id:1,quantity:1}]},
        {items:[{id:1,remaining_quantity:2,remaining_total:'208.80'}]}),'104.4');
});
test('monetary adjustment sends the delta once and full credit uses the server limit',()=>{
    assert.equal(helper.noteTotal({document_type_id:'08',note_credit_or_debit_type_id:'02',items:[{adjustment_amount:'11.60'},{adjustment_amount:'0.03'}]}, {items:[]}),'11.63');
    assert.equal(helper.noteTotal({document_type_id:'07',note_credit_or_debit_type_id:'01',items:[]},{items:[],credit_available:232}),232);
});
