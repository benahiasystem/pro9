const fs=require('node:fs'),path=require('node:path')
function argument(args) {
    const scopes=args.filter(arg=>arg.startsWith('--scope='))
    if(!scopes.length)return null
    if(scopes.length!==1 || scopes[0]!=='--scope=billing')throw new Error('Alcance de auditoría desconocido o duplicado')
    return 'billing'
}
function policy(root,scope) {
    const all=JSON.parse(fs.readFileSync(path.join(root,'config/exact_audit_scopes.json'),'utf8')),entry=all[scope]
    if(all.version!==1 || scope!=='billing' || !entry || !Array.isArray(entry.patterns) || !entry.patterns.length
        || !Array.isArray(entry.required_files) || !entry.required_files.length || typeof entry.decision!=='string')throw new Error('El inventario de facturación está vacío o es inválido')
    const excluded = entry.excluded_files ?? []
    if (!Array.isArray(excluded)) throw new Error('La lista de fuentes excluidas es inválida')
    for(const file of [...entry.patterns,...entry.required_files,...excluded])if(typeof file!=='string' || !file || file.startsWith('/') || file.includes('..') || file.includes('\0') || file.includes('\\'))throw new Error('Ruta de alcance inválida')
    if (excluded.some(file => file.includes('*'))) throw new Error('Las exclusiones requieren una ruta de archivo exacta')
    for(const file of entry.required_files)if(!fs.existsSync(path.join(root,file)) || !fs.statSync(path.join(root,file)).isFile() || !contains(file,entry))throw new Error('Falta una fuente obligatoria de facturación: '+file)
    const excludedMethods = entry.excluded_methods ?? {}
    if (typeof excludedMethods !== 'object' || (Array.isArray(excludedMethods) && excludedMethods.length)) throw new Error('La lista de métodos excluidos es inválida')
    for (const [file, methods] of Object.entries(excludedMethods)) {
        if (!entry.required_files.includes(file) || !Array.isArray(methods) || !methods.length) {
            throw new Error('Los métodos excluidos requieren una fuente obligatoria y una lista de nombres exactos')
        }
        if (methods.some(method => typeof method !== 'string' || method.match(/^[A-Za-z_]\w*::[A-Za-z_]\w*$/)?.[0] !== method)) {
            throw new Error('Nombre de método excluido inválido')
        }
    }
    return entry
}
function contains(file,entry) {
    if (entry.excluded_files?.includes(file)) return false
    return entry.patterns.some(pattern=>{
        const regex=pattern.replace(/[.+?^${}()|[\]\\]/g,'\\$&').replace(/\*\*/g,'\0').replace(/\*/g,'[^/]*').replace(/\0/g,'.*')
        return new RegExp('^'+regex+'$').test(file)
    })
}
function summary(rows,registryErrors,scannedFiles,entry) {
    const selected=rows.filter(row=>contains(row.file,entry) && !entry.excluded_methods?.[row.file]?.includes(row.symbol))
    const pending=selected.filter(row=>row.status==='pending').length
    const errors=registryErrors.filter(error=>!error.source?.file || contains(error.source.file,entry)),files=scannedFiles.filter(file=>contains(file,entry))
    if(!files.length)throw new Error('No se escanearon fuentes del alcance obligatorio')
    return {name:'billing',decision:entry.decision,scanned_files:files,candidates:selected.length,pending,
        pending_outside_scope:rows.filter(row=>row.status==='pending').length-pending,registry_errors:errors,
        registry_errors_outside_scope:registryErrors.length-errors.length,shared_dependencies:entry.shared_dependencies||''}
}
function reviewsForScope(registry, scope) {
    return Object.fromEntries(Object.entries(registry).filter(([id, review]) => {
        if (review?.scope === undefined) return true
        if (review.scope !== 'billing') throw new Error(`Alcance desconocido de la revisión ${id}`)
        return review.scope === scope
    }))
}
module.exports={argument,policy,contains,summary,reviewsForScope}
