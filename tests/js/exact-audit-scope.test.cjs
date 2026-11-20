const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path')
const scope=require('../../scripts/exact_audit_scope.cjs'),{spawnSync}=require('node:child_process')
test('billing scope retains global findings and diagnostics with PHP JavaScript parity',()=>{
    const rows=[{id:'billing',file:'billing/Invoice.php',symbol:'Invoice::save',status:'pending'},
        {id:'method',file:'billing/Invoice.php',symbol:'Invoice::catalog',status:'pending'},
        {id:'outside',file:'purchase/Purchase.php',status:'pending'},
        {id:'valid',file:'billing/nested/Pdf.php',status:'justified'}]
    const errors=[{reason:'stale',source:{file:'billing/Invoice.php',symbol:'Invoice::catalog'}},{reason:'orphaned_review',source:{file:'purchase/Removed.php'}},{reason:'missing_source'}]
    rows.push({id:'quotation_pdf',file:'billing/QuotationPreview.php',status:'pending'})
    errors.push({reason:'stale',source:{file:'billing/QuotationPreview.php'}})
    const files=[...new Set(rows.map(r=>r.file))]
    const policy={patterns:['billing/**'],excluded_files:['billing/QuotationPreview.php'],
        excluded_methods:{'billing/Invoice.php':['Invoice::catalog']},decision:'Facturación explícita'}
    const before=JSON.stringify(rows)
    const result=scope.summary(rows,errors,files,policy)
    assert.equal(result.pending,1);assert.equal(result.pending_outside_scope,3);assert.equal(result.registry_errors.length,2)
    assert.equal(result.registry_errors_outside_scope,2);assert.equal(JSON.stringify(rows),before)
    assert.equal(result.scanned_files.includes('billing/QuotationPreview.php'),false)
    assert.equal(result.scanned_files.includes('billing/Invoice.php'),true)
    const php=spawnSync('php',['-r',`require 'scripts/exact_audit_scope.php'; $p=json_decode(stream_get_contents(STDIN),true); echo json_encode(exactAuditScopeSummary(...$p));`],
        {cwd:path.resolve(__dirname,'../..'),input:JSON.stringify([rows,errors,files,policy]),encoding:'utf8'})
    assert.equal(php.status,0,php.stderr);assert.deepEqual(JSON.parse(php.stdout),result)
    assert.equal(scope.contains('billingSibling/Invoice.php',policy),false)
    assert.equal(scope.contains('billing/deep/Pdf.php',{patterns:['billing/*.php']}),false)
    assert.throws(()=>scope.summary([],[],['outside.php'],policy),/No se escanearon/)
})
test('scope defaults remain global and missing required sources fail closed',()=>{
    assert.equal(scope.argument(['--check']),null);assert.equal(scope.argument(['--check','--scope=billing']),'billing')
    assert.throws(()=>scope.argument(['--scope=other']));assert.throws(()=>scope.argument(['--scope=billing','--scope=billing']))
    const root=fs.mkdtempSync(path.join(os.tmpdir(),'sab-audit-scope-'));fs.mkdirSync(path.join(root,'config'))
    try {
        fs.writeFileSync(path.join(root,'Invoice.php'),'<?php')
        for(const required of ['Missing.php','Invoice.php']) {
            fs.writeFileSync(path.join(root,'config/exact_audit_scopes.json'),JSON.stringify({version:1,billing:{patterns:['different/**'],required_files:[required],decision:'Facturación'}}))
            assert.throws(()=>scope.policy(root,'billing'),/fuente obligatoria/)
        }
        fs.writeFileSync(path.join(root,'config/exact_audit_scopes.json'),JSON.stringify({version:1,billing:{patterns:['*.php'],required_files:['Invoice.php'],excluded_files:['Invoice.php'],decision:'Facturación'}}))
        assert.throws(()=>scope.policy(root,'billing'),/fuente obligatoria/)
    } finally {fs.rmSync(root,{recursive:true,force:true})}
})
