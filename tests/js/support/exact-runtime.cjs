const fs = require('node:fs')
const vm = require('node:vm')
const babel = require('@babel/core')
const cache = new Map()
module.exports = function exactRuntime(name) {
    const file = ['exact-commercial','exact-document','exchange-rate-math','document-creation'].find(file=>name.includes(file))
    if (!file) return null
    if (!cache.has(file)) {
        const exports = {}
        const source = fs.readFileSync(`resources/js/helpers/${file}.js`, 'utf8')
        const code = babel.transformSync(source, {configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']}).code
        vm.runInNewContext(code, {exports, require: dependency => module.exports(dependency)})
        cache.set(file, exports)
    }
    return cache.get(file)
}
