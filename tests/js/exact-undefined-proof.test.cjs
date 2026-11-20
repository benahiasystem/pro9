const {test}=require('node:test'),assert=require('node:assert/strict'),parser=require('@babel/parser'),traverse=require('@babel/traverse').default
const proof=require('../../scripts/exact_guard_proof.cjs')
function expressions(source,template=false) {
 const ast=parser.parse(source,{sourceType:'unambiguous',plugins:['typescript','jsx']}),set=proof.globalUndefinedIdentifiers(ast,template),found=[]
 traverse(ast,{BinaryExpression(path) {found.push([source.slice(path.node.start,path.node.end),proof(path.node,new Set(),set)])}})
 return found
}
test('scope-resolved global undefined proves strict presence only',()=>{
 for(const source of ['price === undefined','undefined !== price','function f(){return amount === undefined}','function f(){return undefined !== quantity}']) assert.equal(expressions(source)[0][1],'strict_undefined_guard',source)
 for(const source of ['price == undefined','price != undefined','price > undefined','price === globalThis.undefined','price === object.undefined','price === object?.undefined']) assert.notEqual(expressions(source)[0][1],'strict_undefined_guard',source)
})
test('shadowing, hoisting and lexical bindings never establish undefined',()=>{
 for(const source of ['function f(undefined){return price === undefined}', 'function f(){return price === undefined;var undefined=2}', 'let undefined=2; price === undefined', 'const {undefined}=object; price === undefined', 'const {x:undefined}=object; price === undefined', 'try{}catch(undefined){price === undefined}', 'import undefined from "x"; price === undefined', 'function undefined(){}; price === undefined','class undefined{}; price === undefined','((...undefined)=>price === undefined)()','function f(){const undefined=2;return ()=>price === undefined}']) assert.notEqual(expressions(source)[0][1],'strict_undefined_guard',source)
 const scope=expressions('function a(undefined){return price === undefined} function b(){return price === undefined}')
 assert.equal(scope[0][1],null);assert.equal(scope[1][1],'strict_undefined_guard')
})
test('dynamic scopes and Vue render contexts are conservative',()=>{
 for(const source of ['with(context){price === undefined}', 'function f(){eval("var undefined=2");return price === undefined}', 'eval("var undefined=2");price === undefined']) assert.notEqual(expressions(source)[0][1],'strict_undefined_guard',source)
 assert.equal(expressions('price === undefined',true)[0][1],null)
})
test('void zero is invariant, without approving its nested money or other operands',()=>{
 for(const source of ['price === void 0', 'void 0 !== price']) assert.equal(expressions(source,true)[0][1],'strict_undefined_guard',source)
 for(const source of ['price == void 0','price === void amount','price === void (amount / 3)','price > void 0']) assert.notEqual(expressions(source)[0][1],'strict_undefined_guard',source)
 const rows=expressions('round(total / 3,2) === undefined');assert.equal(rows[0][1],'strict_undefined_guard');assert.equal(rows[1][1],null)
})
test('strict presence guard does not coerce valid zero, text, rationals or objects',()=>{
 const throwIfCoerced={valueOf(){throw Error('unexpected numeric conversion')}}
 for(const value of [null,false,true,0,0n,'','0.000000000001','1/3',{},[],throwIfCoerced]) assert.equal(value === undefined,false)
 assert.equal(undefined === void 0,true)
})
