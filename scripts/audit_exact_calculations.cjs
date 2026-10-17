const fs = require('node:fs')
const path = require('node:path')
const parser = require('@babel/parser')
const root = path.resolve(__dirname, '..')
const matches = [], errors = []
function visit(node, file, offset) {
    if (!node || typeof node !== 'object') return
    if (node.type === 'CallExpression') {
        const name=node.callee.type === 'Identifier' ? node.callee.name : node.callee.type === 'MemberExpression' ? node.callee.property.name : ''
        if (['round','toFixed','parseFloat','Number'].includes(name)) matches.push({file,line:node.loc.start.line+offset,operation:name,review:'consumer_or_presentation_review'})
    }
    for (const [key,value] of Object.entries(node)) if (!['loc','comments','tokens'].includes(key)) {
        if (Array.isArray(value)) value.forEach(child=>visit(child,file,offset))
        else if (value && typeof value === 'object') visit(value,file,offset)
    }
}
function scan(directory) {
    for (const file of fs.readdirSync(path.join(root,directory),{withFileTypes:true})) {
        const relative=path.join(directory,file.name)
        if (file.isDirectory()) { scan(relative); continue }
        if (!/\.(js|vue)$/.test(file.name)) continue
        let source=fs.readFileSync(path.join(root,relative),'utf8'),offset=0
        if (file.name.endsWith('.vue')) {
            const script=/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/.exec(source)
            if (!script) continue
            offset=source.slice(0,script.index+script[0].indexOf('>')+1).split('\n').length-1
            source=script[1]
        }
        try { visit(parser.parse(source,{sourceType:'unambiguous'}),relative,offset) }
        catch(error) { errors.push({file:relative,message:error.message}) }
    }
}
scan('resources/js')
process.stdout.write(JSON.stringify({javascript_review:matches,parse_errors:errors},null,2)+'\n')
if (errors.length) process.exitCode=1
