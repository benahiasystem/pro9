const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), vm = require('node:vm');
const compiler = require('vue-template-compiler'), babel = require('@babel/core'), Vue = require('vue');
const source = fs.readFileSync('resources/js/views/tenant/configurations/form.vue', 'utf8');
const parsed = compiler.parseComponent(source);
const exportsObject = {};
vm.runInNewContext(babel.transformSync(parsed.script.content, {
 configFile:false,babelrc:false,plugins:['@babel/plugin-transform-modules-commonjs'],
}).code, {exports:exportsObject,headers_token:{},require:name=>name==='vuex'?{mapActions:()=>({}),mapState:()=>({})}:{}});
const component = exportsObject.default;
const block = source.split('<!-- ######## INICIO ENVÍO AUTOMÁTICO HKA ######## -->')[1]
 .split('<!-- ######## FIN ENVÍO AUTOMÁTICO HKA ######## -->')[0].trim();
const render = compiler.compileToFunctions(block);
function context(typeUser='admin',mode='digital') {
 const calls=[];
 const app=new Vue({
  data:()=>({form:{id:1,company:{fiscal_emission_mode:mode},auto_send_hka:true},typeUser,loading_submit:false,errors:{}}),
  methods:{submit:component.methods.submit},render:render.render,staticRenderFns:render.staticRenderFns,
 });
 app.resource='configurations';app.$http={post:async(url,data)=>{calls.push([url,{...data}]);return{data:{success:true,message:'Guardado',configuration:{auto_send_hka:data.auto_send_hka}}}}};
 app.$message={success(){},error(){}};app.$store={commit(){}};
 return{app,calls};
}
function switchNode(node) {
 if(node.tag==='el-switch')return node;
 for(const child of node.children||[]) {const found=switchNode(child);if(found)return found;}
}
test('advanced settings template compiles and starts with automatic sending enabled',()=>{
 assert.deepEqual(compiler.compile(parsed.template.content).errors,[]);
 const ctx={errors:null,config:{}};component.methods.initForm.call(ctx);
 assert.equal(ctx.form.auto_send_hka,true);
});
test('digital admin switch changes the preference and immediately posts the configuration',async()=>{
 const{app,calls}=context();const node=switchNode(app._render());
 assert.equal(node.data.attrs.disabled,false);assert.equal(node.data.model.value,true);
 node.data.model.callback(false);node.data.on.change(false);
 await new Promise(resolve=>setImmediate(resolve));
 assert.equal(calls.length,1);assert.equal(calls[0][0],'/configurations');assert.equal(calls[0][1].auto_send_hka,false);
 assert.equal(app.loading_submit,false);
});
test('other fiscal modes hide the switch and sellers or saving requests cannot toggle it',()=>{
 for(const mode of ['free_form','fiscal_machine']) {const{app}=context('admin',mode);assert.equal(switchNode(app._render()),undefined);}
 const{app}=context('seller');assert.equal(switchNode(app._render()).data.attrs.disabled,true);
 app.typeUser='admin';app.loading_submit=true;assert.equal(switchNode(app._render()).data.attrs.disabled,true);
});
