// ######## INICIO AUDITORIA CALCULOS EXACTOS PRO9 ########
const fs = require('node:fs'), path = require('node:path'), crypto = require('node:crypto')
const categories = ['storage','presentation','settlement','external_protocol','allocation','discrete_units','technical','unreachable','validation','exact_arithmetic']
function validReview(review, root, contents = new Map()) {
    if (!review || !categories.includes(review.category) || typeof review.justification !== 'string' || !review.justification.trim() || !Array.isArray(review.evidence) || !review.evidence.length) return false
    if (!review.source || !['php','javascript'].includes(review.source.operation_language) || !['file','symbol','operation'].every(field=>typeof review.source[field] === 'string' && review.source[field].trim())) return false
    const canonicalRoot = fs.realpathSync(root) + path.sep
    return review.evidence.every(evidence => {
        if (typeof evidence !== 'string') return false
        const [file, anchor] = evidence.split('#', 2)
        try {
            const canonical = fs.realpathSync(path.resolve(root, file))
            if (!canonical.startsWith(canonicalRoot) || !fs.statSync(canonical).isFile()) return false
            const stat = fs.statSync(canonical), stamp = `${canonical}:${stat.mtimeMs}:${stat.size}`
            if (!contents.has(stamp)) {
                const content = fs.readFileSync(canonical)
                contents.set(stamp, {hash:crypto.createHash('sha256').update(content).digest('hex'), text:content.toString()})
            }
            const content = contents.get(stamp)
            if (review.evidence_sha256?.[file] !== content.hash) return false
            return !anchor || content.text.includes(anchor)
        } catch (_) { return false }
    })
}
function diagnostics(rows, registry, language) {
    const ids = new Set(rows.map(row => row.id)), errors = []
    const current = new Map(rows.map(row => [row.id,row]))
    for (const [id, review] of Object.entries(registry)) {
        if (!review?.source?.file) { errors.push({id, reason:'missing_source'}); continue }
        const belongs = language === 'php' ? review.source.operation_language === 'php' : review.source.operation_language === 'javascript'
        if (belongs && !ids.has(id)) errors.push({id, reason:'orphaned_review', source:review.source})
        else if (belongs && current.get(id)?.status === 'pending') errors.push({id, reason:'invalid_or_stale_review', source:review.source})
    }
    return errors
}
function matchesProof(review, proof) { return !review?.proof || review.proof === proof }
module.exports = {validReview, diagnostics, matchesProof}
// ######## FIN AUDITORIA CALCULOS EXACTOS PRO9 ########
