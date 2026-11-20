const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const babel = require('@babel/core');
module.exports = function loadModule(filename) {
    // Pure helper/mixin tests do not mount their imported child components.
    if (filename.endsWith('.vue')) return {default: {}};
    const exports = {};
    const code = babel.transformSync(fs.readFileSync(filename, 'utf8'), {
        configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs'],
    }).code;
    vm.runInNewContext(code, {exports, window: {crypto: require('node:crypto').webcrypto}, require: name => name.startsWith('.')
        ? module.exports(path.resolve(path.dirname(filename), /\.(?:js|vue)$/.test(name) ? name : name + '.js'))
        : require(name)});
    return exports;
};
