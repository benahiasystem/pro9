<template>
    <el-dialog :title="titleDialog" :visible="showDialog" @close="close" @open="create" width="900px">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body" v-loading="loading">
                <el-alert
                    v-if="form.locked"
                    type="warning"
                    :closable="false"
                    show-icon
                    class="mb-3"
                    title="Giro de negocio de solo lectura"
                    description="NRUS tiene módulos fijos porque depende de las restricciones del régimen (1 sucursal y hasta Bs. 8000 de ventas al mes). Sí puedes activarlo o desactivarlo desde el listado.">
                </el-alert>

                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group" :class="{'has-danger': errors.name}">
                            <label class="control-label">Nombre</label>
                            <el-input v-model="form.name" :disabled="form.locked" :maxlength="100"></el-input>
                            <small class="form-control-feedback" v-if="errors.name" v-text="errors.name[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="form-group" :class="{'has-danger': errors.description}">
                            <label class="control-label">Descripción <small>(opcional)</small></label>
                            <el-input v-model="form.description" :disabled="form.locked" :maxlength="255"></el-input>
                            <small class="form-control-feedback" v-if="errors.description" v-text="errors.description[0]"></small>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <span>Habilitar módulos</span>
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
                            </el-tree>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <span>Habilitar apps</span>
                        <div class="form-group tree-container-admin">
                            <el-tree
                                ref="Apptree"
                                :check-strictly="true"
                                :data="apps"
                                :props="defaultProps"
                                accordion
                                highlight-current
                                node-key="id"
                                show-checkbox
                                @check="FixAppChildren">
                            </el-tree>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <el-checkbox v-model="form.active" :disabled="form.locked">
                            Activo (visible al crear o editar una empresa)
                        </el-checkbox>
                    </div>
                </div>
            </div>
            <div class="form-actions text-end pt-2 mt-3">
                <el-button @click.prevent="close()">{{ form.locked ? 'Cerrar' : 'Cancelar' }}</el-button>
                <el-button v-if="!form.locked" type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
            </div>
        </form>
    </el-dialog>
</template>

<script>
    export default {
        props: ['showDialog', 'recordId'],
        data() {
            return {
                resource: 'business-turns',
                loading: false,
                loading_submit: false,
                titleDialog: null,
                errors: {},
                form: {},
                modules: [],
                apps: [],
                defaultProps: {
                    children: 'childrens',
                    label: 'description',
                    disabled: 'disabled'
                },
            }
        },
        created() {
            this.initForm()
        },
        methods: {
            initForm() {
                this.errors = {}
                this.form = {
                    id: null,
                    name: null,
                    description: null,
                    modules: [],
                    levels: [],
                    apps: [],
                    app_levels: [],
                    active: true,
                    locked: false,
                }
            },
            async create() {
                this.initForm()
                this.titleDialog = this.recordId ? 'Editar giro de negocio' : 'Nuevo giro de negocio'
                this.loading = true

                await this.$http.get(`/${this.resource}/tables`)
                    .then(response => {
                        this.modules = response.data.modules
                        this.apps = response.data.apps
                    })

                if (this.recordId) {
                    await this.$http.get(`/${this.resource}/record/${this.recordId}`)
                        .then(response => {
                            const record = response.data
                            this.form = {
                                id: record.id,
                                name: record.name,
                                description: record.description,
                                modules: record.modules || [],
                                levels: record.levels || [],
                                apps: record.apps || [],
                                app_levels: record.app_levels || [],
                                active: !!record.active,
                                locked: !!record.locked,
                            }
                            this.titleDialog = record.locked
                                ? `Giro de negocio: ${record.name}`
                                : `Editar giro de negocio: ${record.name}`
                        })

                    if (this.form.locked) {
                        this.disableTree(this.modules)
                        this.disableTree(this.apps)
                    }
                }

                this.loading = false

                // Un unico volcado a los arboles: para un registro nuevo el form
                // trae arreglos vacios, con lo que quedan limpios.
                this.$nextTick(() => {
                    if (this.$refs.tree) {
                        this.$refs.tree.setCheckedKeys([...this.form.modules, ...this.form.levels])
                    }
                    if (this.$refs.Apptree) {
                        this.$refs.Apptree.setCheckedKeys([...this.form.apps, ...this.form.app_levels])
                    }
                })
            },
            /**
             * Marca los nodos como no seleccionables (giros de solo lectura).
             */
            disableTree(nodes) {
                nodes.forEach(node => {
                    this.$set(node, 'disabled', true)
                    if (node.childrens) this.disableTree(node.childrens)
                })
            },
            /**
             * Separa las claves marcadas del arbol: los ids numericos son modulos
             * y las claves "modulo-nivel" son niveles.
             */
            splitCheckedKeys(tree) {
                const keys = tree ? tree.getCheckedKeys() : []
                const ids = []
                const levels = []

                keys.forEach(key => {
                    if (typeof key === 'string' && key.includes('-')) {
                        levels.push(key)
                    } else {
                        ids.push(parseInt(key))
                    }
                })

                return {ids, levels}
            },
            FixChildren(currentObj, treeStatus) {
                this.syncTree(currentObj, treeStatus, this.$refs.tree)
            },
            FixAppChildren(currentObj, treeStatus) {
                this.syncTree(currentObj, treeStatus, this.$refs.Apptree)
            },
            syncTree(currentObj, treeStatus, element) {
                if (currentObj === undefined) return

                const selected = treeStatus.checkedKeys.indexOf(currentObj.id) // -1 is unchecked
                if (selected !== -1) {
                    this.SelectParent(currentObj, element)
                    this.FixSameValueToChild(currentObj, true, element)
                } else if (currentObj.childrens !== undefined && currentObj.childrens.length !== 0) {
                    this.FixSameValueToChild(currentObj, false, element)
                }
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
                    const currentNode = element.getNode(currentObj)
                    if (currentNode.parent.key !== undefined) {
                        element.setChecked(currentNode.parent, true)
                        this.SelectParent(currentNode.parent, element)
                    }
                }
            },
            submit() {
                if (this.form.locked) return

                const modulesTree = this.splitCheckedKeys(this.$refs.tree)
                const appsTree = this.splitCheckedKeys(this.$refs.Apptree)

                if (!modulesTree.ids.length && !appsTree.ids.length) {
                    return this.$message.error('Debe seleccionar al menos un módulo o una app')
                }

                this.form.modules = modulesTree.ids
                this.form.levels = modulesTree.levels
                this.form.apps = appsTree.ids
                this.form.app_levels = appsTree.levels

                this.loading_submit = true
                this.$http.post(`/${this.resource}`, this.form)
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
                        if (error.response && error.response.status === 422) {
                            this.errors = error.response.data.errors || error.response.data
                        } else {
                            this.$message.error('Error al guardar el giro de negocio')
                        }
                    })
                    .then(() => {
                        this.loading_submit = false
                    })
            },
            close() {
                this.$emit('update:showDialog', false)
                this.$emit('close')
                this.initForm()
            },
        }
    }
</script>
