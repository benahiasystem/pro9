// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
const fs = require('node:fs'), path = require('node:path')
function excluded(file, policy) {
    if (file.split('/').some(part=>policy.excluded_directory_names.includes(part))) return true
    return policy.exclusions.some(entry=>{
        if (typeof entry.reason !== 'string' || !entry.reason.trim()) throw new Error('Exclusión sin justificación')
        return file === entry.path || file.startsWith(entry.path + '/')
    })
}
function sourceFiles(root, policy) {
    const files = []
    function scan(directory) {
        for (const entry of fs.readdirSync(path.join(root, directory), {withFileTypes:true})) {
            const file = path.posix.join(directory, entry.name)
            if (excluded(file, policy)) continue
            if (entry.isDirectory()) scan(file)
            else if (/\.(?:js|vue|ts|tsx)$/.test(file) || file.endsWith('.blade.php')) files.push(file)
        }
    }
    policy.roots.forEach(scan)
    return files.sort()
}
module.exports = {excluded, sourceFiles, missingSources:(expected, scanned)=>{const known=new Set(scanned); return expected.filter(file=>!known.has(file))}}
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
