// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
const fs=require('node:fs'), path=require('node:path'), crypto=require('node:crypto')
const parser=require('@babel/parser')
const {parseComponent}=require('vue-template-compiler')
const auditVueTemplate=require('./exact_vue_audit.cjs')
const guardProof=require('./exact_guard_proof.cjs')
const coverage=require('./exact_source_coverage.cjs')
const {validReview,diagnostics,matchesProof}=require('./exact_audit_registry.cjs')
const root=path.resolve(__dirname,'..'), matches=[], errors=[], seen=new Map()
const registryFile=path.join(root,'config/exact_rounding_registry.json')
const registry=fs.existsSync(registryFile)?JSON.parse(fs.readFileSync(registryFile,'utf8')):{}
const evidenceCache=new Map()
const business=/amount|price|quantity|subtotal|total|cost|stock|balance|igv|iva|igtf|discount|charge|retention|point|commission|profit|interest|payment|payable|fee|rate|precio|cantidad|importe|monto|saldo|costo|descuento|puntos|comision|merma/i
let scanned=0
function finding(file,line,symbol,operation,expression,technical_proof=null) {
    expression=expression.trim().replace(/\s+/g,' ')
    const key=[file,symbol,operation,expression].join('|'), ordinal=seen.get(key)||0
    seen.set(key,ordinal+1)
    const id=crypto.createHash('sha256').update(key+'|'+ordinal).digest('hex').slice(0,24)
    const review=registry[id], valid=validReview(review,root,evidenceCache) && review.source.file===file && review.source.symbol===symbol && review.source.operation===operation && review.source.operation_language==='javascript' && matchesProof(review,technical_proof)
    matches.push({id,file,line,symbol,operation,expression,review:'consumer_or_boundary_review',technical_proof,status:valid?'justified':'pending',classification:valid?review:{category:'pending',justification:null,evidence:[]}})
}
function analyze(source,file,offset,context,expression=false) {
    function visit(node,frames=[]) {
        if(!node || typeof node!=='object')return
        let scope=frames
        if(['FunctionDeclaration','ObjectMethod','ClassMethod'].includes(node.type))scope=[...frames,node.id?.name||node.key?.name||'{anonymous}']
        let op
        const text=source.slice(node.start,node.end)
        if(['CallExpression','OptionalCallExpression','NewExpression'].includes(node.type)) {
            const name=node.callee.type==='Identifier'?node.callee.name:['MemberExpression','OptionalMemberExpression'].includes(node.callee.type)?(node.callee.property.name || node.callee.property.value):''
            if(['round','toFixed','parseFloat','Number','parseInt','floor','ceil','trunc','fround','decimal','final','rateMultiply','rateDivide','toNumber','sum','sumBy','mean','meanBy','min','max','abs','pow','sqrt','sign','reduce','reduceRight'].includes(name))op=name
        } else if(['BinaryExpression','AssignmentExpression','UpdateExpression'].includes(node.type) && !['in','instanceof','='].includes(node.operator))op='native_'+node.operator
        else if(node.type==='UnaryExpression' && ['+','-','~'].includes(node.operator))op='native_unary_'+node.operator
        else if(node.type==='ObjectProperty' && node.key?.name==='type' && node.value?.name==='Number')op='numeric_type'
        if(op)finding(file,node.loc.start.line+offset,scope.join('::')||context,op,text,guardProof(node))
        for(const [key,value]of Object.entries(node))if(!['loc','comments','tokens','errors','extra'].includes(key)) {
            if(Array.isArray(value))value.forEach(child=>visit(child,scope))
            else if(value && typeof value==='object')visit(value,scope)
        }
    }
    try {
        const options={sourceType:'unambiguous',allowReturnOutsideFunction:true,plugins:['typescript','jsx']}
        visit(expression?parser.parseExpression(source,options):parser.parse(source,options))
    } catch(error) {errors.push({file,line:(error.loc?.line||1)+offset,context,message:error.reasonCode||error.message,excerpt:source.split('\n')[(error.loc?.line||1)-1]?.trim().slice(0,170)})}
}
function cleanBlade(source) {
    const mask=part=>part.replace(/[^\n]/g,' ')
    source=source.replace(/\{\{--[\s\S]*?--\}\}/g,mask)
        .replace(/@php[\s\S]*?@endphp/g,mask)
        .replace(/\{\{[\s\S]*?\}\}|\{!![\s\S]*?!!\}|<\?php[\s\S]*?\?>/g,part=>'0'+mask(part.slice(1)))
    const directive=/@(json|js|include|if|elseif|foreach|for|while|isset|unless)\s*\(/g
    let match
    while((match=directive.exec(source))) {
        let end=directive.lastIndex,depth=1,quote=null
        for(;end<source.length && depth;end++) {
            const char=source[end]
            if(quote) { if(char==='\\')end++;else if(char===quote)quote=null }
            else if(char==='"' || char==="'")quote=char
            else if(char==='(')depth++
            else if(char===')')depth--
        }
        const full=source.slice(match.index,end)
        const prefix=['json','js','include'].includes(match[1])?'0':match[1]==='elseif'?'}else if(1){':'if(1){'
        source=source.slice(0,match.index)+prefix+mask(full.slice(prefix.length))+source.slice(end)
        directive.lastIndex=match.index+prefix.length
    }
    return source.replace(/@else\b/g,'}else{').replace(/@(?:endif|endforeach|endfor|endwhile|endisset|endunless)\b/g,'}')
}
const source_policy=JSON.parse(fs.readFileSync(path.join(root,'config/exact_audit_sources.json'),'utf8'))
const expectedSources=coverage.sourceFiles(root,source_policy), scanned_files=[], source_hashes={}
for(const file of expectedSources) {
    const entry={name:path.basename(file)}
    scanned_files.push(file)
        let source=fs.readFileSync(path.join(root,file),'utf8');scanned++
        source_hashes[file]=crypto.createHash('sha256').update(source).digest('hex')
        if(entry.name.endsWith('.blade.php'))source=source.replace(/<!--[\s\S]*?-->/g,part=>part.replace(/[^\n]/g,' '))
            .replace(/@php[\s\S]*?@endphp/g,part=>part.replace(/[^\n]/g,' '))
            .replace(/<\?php[\s\S]*?\?>/g,part=>'0'+part.slice(1).replace(/[^\n]/g,' '))
        if(entry.name.endsWith('.vue')) {
            const component=parseComponent(source)
            for(const block of [component.script].filter(Boolean)) analyze(block.content,file,source.slice(0,block.start).split('\n').length-1,'{script}')
            if(component.template) auditVueTemplate(component.template.content,file,source.slice(0,component.template.start).split('\n').length-1,{analyze,finding})
        } else if(entry.name.endsWith('.blade.php')) {
            for(const match of source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)) {
                if(!match[1].trim() || /type=["'](?:application\/(?:ld\+)?json|text\/x-template)/i.test(match[0].slice(0,match[0].indexOf('>'))))continue
                const offset=source.slice(0,match.index+match[0].indexOf('>')+1).split('\n').length-1
                analyze(entry.name.endsWith('.blade.php')?cleanBlade(match[1]):match[1],file,offset,'{script}')
            }
            const markup=cleanBlade(source.replace(/<script\b[^>]*>[\s\S]*?<\/script>/g,part=>part.replace(/[^\n]/g,' ')))
            auditVueTemplate(markup,file,0,{analyze,finding})
        } else analyze(source,file,0,'{file}')
}
const coverage_missing=coverage.missingSources(expectedSources,scanned_files)
matches.sort((a,b)=>a.file.localeCompare(b.file)||a.line-b.line||a.id.localeCompare(b.id))
const registry_errors=diagnostics(matches,registry,'javascript')
const pending=matches.filter(row=>row.status==='pending').length
process.stdout.write(JSON.stringify({scanned_javascript_templates:scanned,javascript_review:matches,parse_errors:errors,registry_errors,coverage_missing,scanned_files,source_hashes,source_policy,pending},null,2)+'\n')
if(errors.length || (process.argv.includes('--check') && (pending || registry_errors.length || coverage_missing.length)))process.exitCode=1
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
