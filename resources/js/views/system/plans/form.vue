<template>
    <el-dialog :title="titleDialog" :visible="showDialog" @close="close" @open="create">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <!-- <div class="col-md-12 text-right">
                    <h5>Cant. Pedida: {{quantity}}</h5>
                    <h5 v-bind:class="{ 'text-danger': (toAttend < 0) }">Por Atender: {{toAttend}}</h5>
                </div> -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group" :class="{'has-danger': errors.name}">
                            <label class="control-label">Nombre</label>
                            <el-input v-model="form.name" :maxlength="11"></el-input>
                            <small class="form-control-feedback" v-if="errors.name" v-text="errors.name[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group" :class="{'has-danger': errors.test_days}">
                            <label class="control-label">Dias de prueba</label>
                            <el-input v-model="form.test_days" :disabled="!form.test_days_enabled"></el-input>
                            <el-checkbox v-model="form.test_days_enabled" @change="setTestDays">Días de prueba</el-checkbox><br>
                            <small class="form-control-feedback d-block" v-if="errors.test_days" v-text="errors.test_days[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group" :class="{'has-danger': errors.pricing}">
                            <label class="control-label">Precio</label>
                            <el-input v-model="form.pricing"></el-input>
                            <small class="form-control-feedback" v-if="errors.pricing" v-text="errors.pricing[0]"></small>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group" :class="{'has-danger': errors.limit_users || errorLUser.limit_users}">
                            <label class="control-label">Límite de usuarios</label>
                            <el-input v-model="limit_users" @input="validateLUsers"  :disabled="users_unlimited"></el-input>
                            <el-checkbox v-model="users_unlimited" @change="setUnlimitUsers">Ilimitado</el-checkbox><br>
                            <small class="form-control-feedback d-block" v-if="errors.limit_users" v-text="errors.limit_users[0]"></small>
                            <small class="form-control-feedback" v-if="errorLUser.limit_users" v-text="errorLUser.limit_users[0]"></small> 
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group" :class="{'has-danger': errors.limit_documents || errorLDocument.limit_documents}">
                            <label class="control-label">Límite de documentos</label>
                            <el-input v-model="limit_documents" @input="validateLDocuments" :disabled="documents_unlimited"></el-input>
                            <el-checkbox v-model="documents_unlimited" @change="setUnlimitDocuments">Ilimitado</el-checkbox><br>

                            <el-checkbox v-model="form.include_sale_notes_limit_documents">Incluir notas de venta</el-checkbox><br>

                            <small class="form-control-feedback d-block" v-if="errors.limit_documents" v-text="errors.limit_documents[0]"></small>
                            <small class="form-control-feedback" v-if="errorLDocument.limit_documents" v-text="errorLDocument.limit_documents[0]"></small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group" :class="{'has-danger': errors.establishments_limit}">
                            <label class="control-label">Límite de sucursales</label>

                            <template v-if="form.establishments_unlimited">
                                <el-input value="∞" disabled></el-input>
                            </template>
                            <template v-else>
                                <el-input v-model="form.establishments_limit" :disabled="form.nrus"></el-input>
                            </template>

                            <el-checkbox v-model="form.establishments_unlimited" :disabled="form.nrus">Ilimitado</el-checkbox><br>

                            <small class="form-control-feedback d-block" v-if="errors.establishments_limit" v-text="errors.establishments_limit[0]"></small>
                        </div>
                    </div>

                    
                    <div class="col-md-6">
                        <div class="form-group" :class="{'has-danger': errors.sales_limit}">
                            <label class="control-label">
                                Límite de ventas mensual
                                <el-tooltip class="item"
                                            :content="form.include_sale_notes_sales_limit ? 'Disponible para CPE y Nota de venta' : 'Disponible para CPE'"
                                            effect="dark"
                                            placement="top">
                                    <i class="fa fa-info-circle"></i>
                                </el-tooltip>
                            </label>

                            <template v-if="form.sales_unlimited">
                                <el-input value="∞" disabled></el-input>
                            </template>
                            <template v-else>
                                <el-input v-model="form.sales_limit" @input="normalizeNrusSalesLimit"></el-input>
                            </template>

                            <el-checkbox v-model="form.sales_unlimited" :disabled="form.nrus">Ilimitado</el-checkbox><br>
                            <el-checkbox v-model="form.include_sale_notes_sales_limit">Incluir notas de venta</el-checkbox><br>


                            <small class="form-control-feedback d-block" v-if="errors.sales_limit" v-text="errors.sales_limit[0]"></small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group" :class="{'has-danger': errors.whatsapp_messages_limit}">
                            <label class="control-label">Límite de mensajes de WhatsApp (Ciclo Facturación)</label>

                            <template v-if="form.whatsapp_messages_unlimited">
                                <el-input value="∞" disabled></el-input>
                            </template>
                            <template v-else>
                                <el-input v-model="form.whatsapp_messages_limit"></el-input>
                            </template>

                            <el-checkbox v-model="form.whatsapp_messages_unlimited">Ilimitado</el-checkbox><br>

                            <small class="form-control-feedback d-block" v-if="errors.whatsapp_messages_limit" v-text="errors.whatsapp_messages_limit[0]"></small>
                        </div>
                    </div>

                    <div class="col-md-6 d-flex align-items-center">
                        <div class="form-group">
                            <el-switch
                                v-model="form.nrus"
                                @change="changeNrus">
                            </el-switch>
                            <span class="ms-2">
                                Régimen NRUS
                                <el-tooltip
                                    class="item"
                                    content="Fija el plan en 1 sucursal y hasta S/ 8000 de ventas al mes. No limita los módulos."
                                    effect="dark"
                                    placement="top">
                                    <i class="fa fa-info-circle"></i>
                                </el-tooltip>
                            </span>
                        </div>
                    </div>

                </div>
                <el-collapse v-model="collapse" class="mt-3">
                    <el-collapse-item name="1" title="Módulos predeterminados sugeridos (Opcional)">
                        <div class="row">
                            <span>Giro de negocio <small>(opcional)</small></span>
                            <div class="col-12">
                                <el-radio-group v-model="business" @change="changeModules">
                                    <el-radio v-if="business === 0" :label="0">Personalizado</el-radio>
                                    <el-radio :label="5">Completo</el-radio>
                                    <el-radio
                                        v-for="turn in visibleBusinessTurns"
                                        :key="turn.id"
                                        :label="turn.id">
                                        {{ turn.name }}
                                        <el-tooltip
                                            v-if="turn.description"
                                            class="item"
                                            :content="turn.description"
                                            effect="dark"
                                            placement="top">
                                            <i class="fa fa-info-circle"></i>
                                        </el-tooltip>
                                    </el-radio>
                                </el-radio-group>
                            </div>
                            <div class="col-md-6">
                                <span>
                                    Habilitar módulos
                                </span>
                                <div class="form-group tree-container-admin">
                                    <el-tree
                                        ref="tree"
                                        :check-strictly="true"
                                        :data="modules"
                                        :props="defaultProps"
                                        accordion
                                        highlight-current
                                        node-key="id"
                                        show-checkbox
                                        @check="FixChildren">
                                        <span class="nrus-tree-node" slot-scope="{ node, data }">
                                            <span>{{ node.label }}</span>
                                            <el-tooltip
                                                v-if="nrusTag(data)"
                                                :content="nrusTag(data).tooltip"
                                                effect="dark"
                                                placement="right">
                                                <el-tag
                                                    :type="nrusTag(data).type"
                                                    size="mini"
                                                    class="ms-2">
                                                    {{ nrusTag(data).text }}
                                                </el-tag>
                                            </el-tooltip>
                                        </span>
                                    </el-tree>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <span>
                                    Habilitar apps
                                </span>
                                <div class="form-group tree-container-admin">
                                    <el-tree
                                        ref="Apptree"
                                        :check-strictly="true"
                                        :data="apps"
                                        :props="defaultAppsProps"
                                        accordion
                                        highlight-current
                                        node-key="id"
                                        show-checkbox
                                        @check="FixAppChildren">
                                        <span class="nrus-tree-node" slot-scope="{ node, data }">
                                            <span>{{ node.label }}</span>
                                            <el-tooltip
                                                v-if="nrusTag(data)"
                                                :content="nrusTag(data).tooltip"
                                                effect="dark"
                                                placement="right">
                                                <el-tag
                                                    :type="nrusTag(data).type"
                                                    size="mini"
                                                    class="ms-2">
                                                    {{ nrusTag(data).text }}
                                                </el-tag>
                                            </el-tooltip>
                                        </span>
                                    </el-tree>
                                </div>
                            </div>
                            <div v-if="form.nrus && nrusBlockedSelected" class="col-12 mt-2">
                                <el-alert
                                    type="warning"
                                    :closable="false"
                                    show-icon
                                    title="Los módulos «No Recomendado» no están probados con NRUS"
                                    description="Puedes activarlos, pero bajo tu responsabilidad: pueden no funcionar con el régimen.">
                                </el-alert>
                            </div>
                        </div>
                    </el-collapse-item>
                </el-collapse>
                <!-- <div class="row">
                    <div class="col-md-12 mt-3">
                        <div class="form-group" :class="{'has-danger': (errors.plan_documents)}">
                            <label class="control-label font-weight-bold mb-0">Habilitar documentos electrónicos</label> 

                            <el-checkbox-group v-model="form.plan_documents"  >
                                <el-checkbox v-for="(city,ind) in plan_documents" class="plan_documents" :label="city.id"  :key="ind">{{city.description}}</el-checkbox>
                            </el-checkbox-group>

                            <small class="form-control-feedback" v-if="errors.plan_documents" v-text="errors.plan_documents[0]"></small> 
                        </div>
                    </div>
                   
                </div> -->
            </div>
            <div class="form-actions text-right pt-2">
                <el-button @click.prevent="close()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
            </div>
        </form>
    </el-dialog>
</template>

<style>
.plan_documents{ display:block ; margin: 15px 0 ;}
.nrus-tree-node {
    display: inline-flex;
    align-items: center;
}
</style>

<script>

    import {nrusModules} from '../../../mixins/nrus-modules'

    export default {
        mixins: [nrusModules],
        props: ['showDialog', 'recordId','plan_documents'],
        data() {
            return {
                loading_submit: false,
                titleDialog: null,
                resource: 'plans',
                documents_unlimited:null,
                users_unlimited:null,
                limit_users:null,
                limit_documents:null,
                errors: {},
                errorLDocument:{},
                errorLUser:{},
                form: {},
                collapse: 1,
                business: null,
                applyingBusinessModules: false,
                nrus_sales_warned: false,
                modules: [],
                apps: [],
                business_turns: [],
                defaultProps: {
                    children: 'childrens',
                    label: 'description'
                },
                defaultAppsProps: {
                    children: 'childrens',
                    label: 'description'
                },
            }
        },
        computed: {
            visibleBusinessTurns() {
                return this.business_turns.filter(turn => turn.active || turn.id === this.business);
            }
        },
        created() 
        {
            this.initForm()
            this.$http.get(`/${this.resource}/tables`).then(response => {
                this.modules = response.data.modules
                this.apps = response.data.apps
                this.business_turns = response.data.business_turns || []
            })
        },
        methods: {
            notEmpty(value)
            {
                return !_.isEmpty(value)
            },
            initForm() {
                this.limit_users = null
                this.limit_documents = null
                this.documents_unlimited = false
                this.users_unlimited = false
                this.errors = {}
                this.errorLDocument = {}
                this.errorLUser = {}
                this.nrusBlockedSelected = false
                this.nrus_sales_warned = false
                
                this.form = {
                    id: null,
                    name: null,
                    test_days: null,
                    test_days_enabled: false,
                    pricing: null,
                    is_popular: false,
                    limit_users: null,
                    limit_documents: null,
                    plan_documents:[],

                    establishments_limit : 0,
                    establishments_unlimited : true,

                    sales_limit : 0,
                    sales_unlimited : true,
                    include_sale_notes_sales_limit : false,
                    include_sale_notes_limit_documents: false,

                    whatsapp_messages_limit : 0,
                    whatsapp_messages_unlimited : true,

                    nrus: false,

                    module_permissions: null
                }
                this.business = null;
                setTimeout(() => {
                    if(this.$refs.tree) this.$refs.tree.setCheckedKeys([]);
                    if(this.$refs.Apptree) this.$refs.Apptree.setCheckedKeys([]);
                }, 100);
            },
            create() {

                this.titleDialog = (this.recordId)? 'Editar plan':'Nuevo plan'
                if (this.recordId) {
                    this.$http.get(`/${this.resource}/record/${this.recordId}`).then(response => {
                            this.setData(response.data.data)
                        })
                }
            },
            permissionsAreNrus(permissions) {
                if (!permissions) return false;
                if (permissions.nrus !== undefined && permissions.nrus !== null) return !!permissions.nrus;

                return Number(permissions.business) === 6;
            },
            changeNrus() {
                if (this.form.nrus) this.applyNrusLimits(true);
                this.refreshNrusBlocked();
            },
            normalizeNrusSalesLimit() {
                if (!this.form.nrus || this.form.sales_unlimited) return;

                const salesLimit = Number(this.form.sales_limit);

                if (isNaN(salesLimit) || salesLimit <= 8000) {
                    this.nrus_sales_warned = false;
                    return;
                }

                this.form.sales_limit = 8000;

                if (this.nrus_sales_warned) return;

                this.nrus_sales_warned = true;
                this.$message.warning('El límite de ventas mensual para NRUS es S/ 8000.');
            },
            applyNrusLimits(showMessages = false) {
                const messages = [];
                const salesLimit = Number(this.form.sales_limit);
                const establishmentsLimit = Number(this.form.establishments_limit);
                const wasEstablishmentsUnlimited = this.form.establishments_unlimited;

                if (!isNaN(salesLimit) && salesLimit > 8000) {
                    this.form.sales_limit = 8000;
                    messages.push('las ventas mensuales se ajustaron a S/ 8000');
                }

                if (wasEstablishmentsUnlimited || isNaN(establishmentsLimit) || establishmentsLimit !== 1) {
                    this.form.establishments_unlimited = false;
                    this.form.establishments_limit = 1;
                    if (wasEstablishmentsUnlimited || isNaN(establishmentsLimit) || establishmentsLimit > 1) {
                        messages.push('las sucursales se ajustaron a 1');
                    }
                }

                this.form.sales_unlimited = false;

                if (showMessages && messages.length) {
                    this.$message.warning(`Para NRUS, ${messages.join(' y ')}.`);
                }
            },
            validateInputs()
            {
                if(!this.form.establishments_unlimited)
                {
                    if(isNaN(this.form.establishments_limit)) return this.getResponseValidations(false, 'Límite de sucursales no es un número válido.')
                } 

                if(!this.form.sales_unlimited)
                {
                    if(isNaN(this.form.sales_limit)) return this.getResponseValidations(false, 'Límite de ventas no es un número válido.')
                    if(this.form.nrus) this.applyNrusLimits()
                    if(this.form.nrus && Number(this.form.sales_limit) > 8000) return this.getResponseValidations(false, 'El límite de ventas mensual para NRUS no puede ser mayor a 8000.')
                }

                if(!this.form.whatsapp_messages_unlimited)
                {
                    if(isNaN(this.form.whatsapp_messages_limit)) return this.getResponseValidations(false, 'Límite de mensajes de WhatsApp no es un número válido.')
                }

                return this.getResponseValidations()
            },
            submit() {   

                if(this.validateLUsers().limit_users || this.validateLDocuments().limit_documents)
                    return
                    
                const validate_inputs = this.validateInputs()
                if(!validate_inputs.success) return this.$message.error(validate_inputs.message)
                
                const modulesAndLevelsSelecteds = this.$refs.tree.getCheckedNodes();
                const appsAndLevelsSelecteds = this.$refs.Apptree.getCheckedNodes();
                const selModules = [];
                modulesAndLevelsSelecteds.map(m => {
                    if (m.is_parent) {
                        selModules.push(m.id);
                    }
                });
                const selApps = [];
                appsAndLevelsSelecteds.map(m => {
                    if (m.is_parent) {
                        selApps.push(m.id);
                    }
                });
                const selLevels = [];
                modulesAndLevelsSelecteds.filter(l => {
                    if (!l.is_parent) {
                        const idArray = l.id.split('-');
                        selLevels.push(idArray[1]);
                    }
                })
                appsAndLevelsSelecteds.filter(l => {
                    if (!l.is_parent) {
                        const idArray = l.id.split('-');
                        selLevels.push(idArray[1]);
                    }
                })

                this.form.module_permissions = {
                    business: this.business,
                    nrus: !!this.form.nrus,
                    modules: selModules,
                    apps: selApps,
                    levels: selLevels
                };

                this.transform()

                this.loading_submit = true  
                this.$http.post(`${this.resource}`, this.form)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            this.$eventHub.$emit('reloadData')
                            this.close()
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(error => {
                        if (error.response.status === 422) {
                            this.errors = error.response.data 
                        } else {
                            console.log(error.response)
                        }
                    })
                    .then(() => {
                        this.loading_submit = false
                    })
                    
            },
            setData(data){

                this.form = data
                this.form.plan_documents = Object.values(data.plan_documents)
                this.users_unlimited = (data.limit_users == 0) ? true : false
                this.documents_unlimited = (data.limit_documents == 0) ? true : false
                this.form.test_days_enabled = !!(data.test_days_enabled || data.test_days > 0)
                this.form.test_days = this.form.test_days_enabled ? data.test_days : null
                this.limit_users = (this.users_unlimited) ? "∞": data.limit_users
                this.limit_documents = (this.documents_unlimited) ? "∞":  data.limit_documents

                const preSelecteds = [];
                const preAppSelecteds = [];

                // `nrus` no es columna del plan: vive dentro de module_permissions,
                // por eso se agrega al form de forma reactiva.
                this.$set(this.form, 'nrus', this.permissionsAreNrus(this.form.module_permissions))

                if (this.form.module_permissions) {
                    const permissions = this.form.module_permissions;
                    this.business = Number(permissions.business) === 6 ? null : permissions.business;
                    const preSelectedsModules = this.form.module_permissions.modules || [];
                    const preSelectedsApps = this.form.module_permissions.apps || [];
                    const preSelectedsLevels = this.form.module_permissions.levels || [];
                    
                    this.modules.map(m => {
                        if (preSelectedsModules.includes(m.id)) {
                            preSelecteds.push(m.id);
                        }
                        m.childrens.map(c => {
                            const idArray = c.id.split('-');
                            if (preSelectedsLevels.includes(parseInt(idArray[1])) || preSelectedsLevels.includes(idArray[1])) {
                                preSelecteds.push(c.id);
                            }
                        })
                    });

                    this.apps.map(m => {
                        if (preSelectedsApps.includes(m.id)) {
                            preAppSelecteds.push(m.id);
                        }
                        m.childrens.map(c => {
                            const idArray = c.id.split('-');
                            if (preSelectedsLevels.includes(parseInt(idArray[1])) || preSelectedsLevels.includes(idArray[1])) {
                                preAppSelecteds.push(c.id);
                            }
                        })
                    });
                }
                
                this.applyingBusinessModules = true;
                setTimeout(() => {
                    if(this.$refs.tree) this.$refs.tree.setCheckedKeys(preSelecteds);
                    if(this.$refs.Apptree) this.$refs.Apptree.setCheckedKeys(preAppSelecteds);
                    this.applyingBusinessModules = false;
                    this.refreshNrusBlocked();
                }, 500);
            },
            transform(){

                if(this.users_unlimited){
                    this.form.limit_users = 0
                }else{
                    this.form.limit_users = this.limit_users
                }

                if(this.documents_unlimited){
                    this.form.limit_documents = 0
                }else{
                    this.form.limit_documents = this.limit_documents
                }

                if(!this.form.test_days_enabled){
                    this.form.test_days = null
                }

            },
            validateLDocuments(){

                this.errorLDocument = {} 

                if(!this.documents_unlimited){
                    if(this.limit_documents < 1)
                        this.$set(this.errorLDocument, 'limit_documents', ['limite de documentos debe ser mayor a cero']);
                } 

                return this.errorLDocument 
            },            
            
            validateLUsers(){

                this.errorLUser = {}  
                 
                if(!this.users_unlimited){
                    if(this.limit_users < 1)
                        this.$set(this.errorLUser, 'limit_users', ['limite de usuarios debe ser mayor a cero']);
                }

                return this.errorLUser 
            },            
            setUnlimitDocuments(){
                this.limit_documents = (this.documents_unlimited) ? "∞" : null
                this.form.limit_documents = (this.limit_documents == "∞") ? 0 : this.limit_documents
            },
            setUnlimitUsers(){
                this.limit_users = (this.users_unlimited) ? "∞" : null
                this.form.limit_users = (this.limit_users == "∞") ? 0 : this.limit_users

            },
            setTestDays(){
                if(!this.form.test_days_enabled){
                    this.form.test_days = null
                }
            },
            close() {
                this.$emit('update:showDialog', false)
                this.initForm()
            },
            FixChildren(currentObj, treeStatus) {
                this.markCustomBusiness()
                let element = this.$refs.tree
                if (currentObj !== undefined) {
                    let selected = treeStatus.checkedKeys.indexOf(currentObj.id)
                    if (selected !== -1) {
                        this.SelectParent(currentObj, element)
                        this.FixSameValueToChild(currentObj, true, element)
                    } else {
                        if (currentObj.childrens !== undefined && currentObj.childrens.length !== 0) {
                            this.FixSameValueToChild(currentObj, false, element)
                        }
                    }
                }
                this.refreshNrusBlocked()
            },
            FixAppChildren(currentObj, treeStatus) {
                this.markCustomBusiness()
                let element = this.$refs.Apptree
                if (currentObj !== undefined) {
                    let selected = treeStatus.checkedKeys.indexOf(currentObj.id)
                    if (selected !== -1) {
                        this.SelectParent(currentObj, element)
                        this.FixSameValueToChild(currentObj, true, element)
                    } else {
                        if (currentObj.childrens !== undefined && currentObj.childrens.length !== 0) {
                            this.FixSameValueToChild(currentObj, false, element)
                        }
                    }
                }
                this.refreshNrusBlocked()
            },
            markCustomBusiness() {
                if (this.applyingBusinessModules || this.business === 0) return;
                this.business = 0;
            },
            FixSameValueToChild(treeList, isSelected, element) {
                if (treeList !== undefined && element !== undefined) {
                    element.setChecked(treeList.id, isSelected)
                    if (treeList.childrens !== undefined) {
                        for (let i = 0; i < treeList.childrens.length; i++) {
                            this.FixSameValueToChild(treeList.childrens[i], isSelected, element)
                        }
                    }
                }
            },
            SelectParent(currentObj, element) {
                if (currentObj !== undefined) {
                    let currentNode = element.getNode(currentObj)
                    if (currentNode.parent.key !== undefined) {
                        element.setChecked(currentNode.parent, true)
                        this.SelectParent(currentNode.parent, element)
                    }
                }
            },
            changeModules() {
                if (this.business === 0) return;

                this.applyingBusinessModules = true;

                const group = {
                    modules: [],
                    levels: [],
                    apps: [],
                };

                if (this.business === 5) {
                    // Completo: se calcula al vuelo, no es un giro administrable.
                    group.modules = this.getIds(this.modules);
                    group.apps = this.getIds(this.apps);
                } else {
                    const turn = this.business_turns.find(t => t.id === this.business);
                    if (turn) {
                        group.modules = turn.modules || [];
                        group.levels = turn.levels || [];
                        group.apps = [...(turn.apps || []), ...(turn.app_levels || [])];
                    }
                }

                this.$nextTick(() => {
                    if (this.$refs.tree) {
                        this.$refs.tree.setCheckedKeys([...group.modules, ...group.levels]);
                    }
                    if (this.$refs.Apptree) {
                        this.$refs.Apptree.setCheckedKeys(group.apps);
                    }

                    this.applyingBusinessModules = false;
                    this.refreshNrusBlocked();
                });
            },
            getIds(modules) {
                const preSelecteds = [];
                modules.map(m => {
                    preSelecteds.push(m.id);
                    m.childrens.map(c => {
                        preSelecteds.push(c.id);
                    });
                });
                return preSelecteds
            }
        }
    }
</script>
