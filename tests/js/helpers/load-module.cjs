const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const babel = require('@babel/core');
module.exports = function loadModule(filename) {
    const exports = {};
    const code = babel.transformSync(fs.readFileSync(filename, 'utf8'), {
        configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs'],
    }).code;
    vm.runInNewContext(code, {exports, require: name => name.startsWith('.')
        ? module.exports(path.resolve(path.dirname(filename), name.endsWith('.js') ? name : name + '.js'))
        : require(name)});
    return exports;
};
