<template>
    <div class="users">
        <div class="page-header pe-0">
            <h2><a href="/users">
                <svg  xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-users"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" /><path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /><path d="M21 21v-2a4 4 0 0 0 -3 -3.85" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Usuarios</span></li>
            </ol>
            <div class="right-wrapper pull-right">

                <template v-if="showAccessTokenForDiscount">
                    <el-tooltip class="item" content="Genera un token aleatorio para permitir realizar ventas con un porcentaje de descuento superior al límite configurado - Para vendedores" effect="dark" placement="top-start">
                        <button type="button" class="btn btn-info btn-sm  mt-2 me-2" @click.prevent="clickAccessTokenForDiscount()"><i class="fa fa-check"></i> Generar token</button>
                    </el-tooltip>
                </template>

                <button type="button" class="btn btn-custom btn-sm  mt-2 me-2" v-if="typeUser == 'admin'" @click.prevent="clickCreate()"><i class="fa fa-plus-circle"></i> Nuevo</button>

                <!--<button type="button" class="btn btn-custom btn-sm  mt-2 mr-2" @click.prevent="clickImport()"><i class="fa fa-upload"></i> Importar</button>-->
            </div>
        </div>
        <div class="card tab-content-default row-new">
            <!-- <div class="card-header bg-info">
                <h3 class="my-0">Listado de usuarios</h3>
            </div> -->
            <div class="card-body">
                <div class="col-md-12">
                <div class="scroll-shadow shadow-left" v-show="showLeftShadow"></div>
                <div class="scroll-shadow shadow-right" v-show="showRightShadow"></div>
                <div class="table-responsive" ref="scrollContainer">
                    <table class="table">
                        <thead>
                        <tr>
                            <!-- <th>#</th> -->
                            <th>Email</th>
                            <th>Nombre</th>
                            <th>Perfil</th>
                            <th>Permisos app</th>
                            <th>Api Token</th>
                            <th>Sucursal</th>
                            <th>Teléfono</th>
                            <th>Bot</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="(row, index) in records" :key="index" :class="!row.active ? 'text-danger' : ''">
                            <!-- <td>{{ index + 1 }}</td> -->
                            <td>
                                {{ row.email }}
                                <sup v-if="row.is_multi_user" style="padding: 0px 3px;border-radius: 4px;" class="bg-info text-white">Multi Usuario</sup>
                            </td>
                            <td>{{ row.name }}</td>
                            <td>{{ row.type }}</td>
                            <td>
                                <span v-if="row.id == 1" class="text-muted">Todos</span>
                                <div v-else-if="getVisiblePermissions(row).length" class="permission-chips">
                                    <span class="permission-chip"
                                          v-for="(permission, key) in getPermissionsToShow(row)"
                                          :key="key">{{ permission.description }}</span>
                                    <button type="button"
                                            class="permission-chip permission-chip-more"
                                            v-if="getHiddenPermissionsCount(row) > 0"
                                            @click.prevent="toggleExpanded(row)">+{{ getHiddenPermissionsCount(row) }} más</button>
                                    <button type="button"
                                            class="permission-chip permission-chip-more"
                                            v-else-if="expanded[row.id]"
                                            @click.prevent="toggleExpanded(row)">Ver menos</button>
                                </div>
                                <span v-else class="text-muted">Sin permisos</span>
                            </td>
                            <td>
                                <span>
                                    {{ maskToken(row.api_token) }}
                                </span>
                            
                                <button
                                    class="btn-view-token"
                                    @click.prevent="openTokenModal(row)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-eye">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                        <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
                                    </svg>
                                </button>
                            
                                <button
                                    class="btn-copy-token"
                                    @click.prevent="clickCopy(row)"
                                    :class="{ copied: row.copied }"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-copy"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 9.667a2.667 2.667 0 0 1 2.667 -2.667h8.666a2.667 2.667 0 0 1 2.667 2.667v8.666a2.667 2.667 0 0 1 -2.667 2.667h-8.666a2.667 2.667 0 0 1 -2.667 -2.667l0 -8.666" /><path d="M4.012 16.737a2.005 2.005 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1" /></svg>
                                </button>
                            </td>
                            <td>{{ row.establishment_description }}</td>
                            <td>{{ row.personal_cell_phone || '—' }}</td>
                            <td>
                                <span v-if="row.locked" class="badge badge-danger" style="background:#dc3545;color:#fff;padding:3px 7px;border-radius:4px;font-size:11px;">Suspendido</span>
                                <span v-else-if="row.bot_enabled" class="badge" style="background:#28a745;color:#fff;padding:3px 7px;border-radius:4px;font-size:11px;">Activo</span>
                                <span v-else class="badge" style="background:#6c757d;color:#fff;padding:3px 7px;border-radius:4px;font-size:11px;">Inactivo</span>
                                <button
                                    v-if="!row.locked && typeUser === 'admin'"
                                    type="button"
                                    class="btn btn-link btn-xs p-0 ms-1"
                                    :title="row.bot_enabled ? 'Deshabilitar bot' : 'Habilitar bot'"
                                    @click.prevent="toggleBot(row)">
                                    <i :class="row.bot_enabled ? 'fa fa-toggle-on' : 'fa fa-toggle-off'"></i>
                                </button>
                            </td>
                            <td class="text-end">
                                <el-dropdown
                                    v-if="hasRowActions(row)"
                                    trigger="click"
                                    @command="handleRowCommand"
                                >
                                    <button
                                        class="btn btn-default btn-sm btn-dropdown-toggle"
                                        type="button"
                                    >
                                        <i class="fas fa-ellipsis-v"></i>
                                        <i class="fas fa-ellipsis-h" style="display: none;"></i>
                                    </button>
                                    <el-dropdown-menu slot="dropdown" class="actions-dropdown">
                                        <el-dropdown-item
                                            v-if="canEdit(row)"
                                            :command="{ action: 'edit', row }"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg>
                                            Editar
                                        </el-dropdown-item>

                                        <el-dropdown-item divided v-if="canChangeActive(row) || canDelete(row)"></el-dropdown-item>

                                        <el-dropdown-item
                                            v-if="canChangeActive(row)"
                                            :command="{ action: 'changeActive', row }"
                                            :class="row.active ? 'text-danger option-delete' : ''"
                                        >
                                            <template v-if="row.active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-ban me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M5.7 5.7l12.6 12.6" /></svg>Inhabilitar</template>
                                            <template v-else><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-check me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>Habilitar</template>
                                        </el-dropdown-item>

                                        <el-dropdown-item
                                            v-if="canDelete(row)"
                                            :command="{ action: 'delete', row }"
                                            class="text-danger option-delete"
                                            :divided="!canChangeActive(row) && canEdit(row)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                            Eliminar
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </el-dropdown>
                                <span v-else>—</span>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                </div>                
            </div>
            <users-form :showDialog.sync="showDialog"
                        :typeUser="typeUser"
                        :recordId="recordId"></users-form>

            <authorized-token-discount-form :showDialog.sync="showDialogAuthorizedTokenForDiscount" ></authorized-token-discount-form>
            <el-dialog
                title="Api Token"
                :visible.sync="showTokenDialog"
                width="600px"
                append-to-body
            >
                <div v-if="selectedTokenRow">
                    <p class="mb-1"><strong>Usuario:</strong> {{ selectedTokenRow.name }}</p>
                    <p><strong>Email:</strong> {{ selectedTokenRow.email }}</p>
                
                    <el-input
                        type="textarea"
                        :rows="4"
                        :value="selectedTokenRow.api_token"
                        readonly
                    />
                </div>
            
                <span slot="footer" class="dialog-footer">
                    <el-button @click="showTokenDialog = false">Cerrar</el-button>
                    <el-button
                        type="primary"
                        @click="clickCopy(selectedTokenRow)"
                    >
                        Copiar token
                    </el-button>
                </span>
            </el-dialog>
        </div>
    </div>
</template>

<script>

    import UsersForm from './form1.vue'
    import AuthorizedTokenDiscountForm from './partials/authorized_token_discount.vue'
    import {deletable} from '../../../mixins/deletable'

    export default {
        props: ['typeUser', 'configuration'],
        mixins: [deletable],
        components: {UsersForm, AuthorizedTokenDiscountForm},
        data() {
            return {
                showDialog: false,
                showDialogAuthorizedTokenForDiscount: false,
                resource: 'users',
                recordId: null,
                records: [],
                showLeftShadow: false,
                showRightShadow: false,
                showTokenDialog: false,
                selectedTokenRow: null,
                // modulos aun no implementados en la app movil, se ocultan del listado
                hidden_app_modules: ['order-note', 'report-sales', 'configuration', 'dispatches', 'carrier_dispatches'],
                // cantidad de permisos visibles antes de agrupar el resto en "+n más"
                visible_chips: 3,
                expanded: {},
            }
        },
        created() {
            this.$eventHub.$on('reloadData', () => {
                this.getData()
            })
            this.getData()
        },
        computed:
        {
            showAccessTokenForDiscount()
            {
                return this.typeUser === 'admin' && this.configuration.restrict_seller_discount
            },
            isAdminUser()
            {
                return this.typeUser === 'admin'
            }
        },
        mounted() {
            this.$nextTick(() => {
                const el = this.$refs.scrollContainer;
                if (el) {
                    el.addEventListener('scroll', this.checkScrollShadows);
                    this.checkScrollShadows();
                }
            });
        },
        methods: {
            checkScrollShadows() {
                const el = this.$refs.scrollContainer;
                if (!el) return;
                
                const scrollLeft = el.scrollLeft;
                const scrollRight = el.scrollWidth - el.clientWidth - scrollLeft;
                
                this.showLeftShadow = scrollLeft > 1;
                this.showRightShadow = scrollRight > 1;
            },
            isMainUser(user_id)
            {
                return user_id === 1
            },
            canEdit(row)
            {
                return this.isAdminUser && row.active
            },
            canDelete(row)
            {
                return this.isAdminUser && row.id != 1 && !row.is_multi_user
            },
            canChangeActive(row)
            {
                return this.isAdminUser && !this.isMainUser(row.id) && !row.is_multi_user
            },
            hasRowActions(row)
            {
                return this.canEdit(row) || this.canChangeActive(row) || this.canDelete(row)
            },
            handleRowCommand(command)
            {
                if (!command || !command.action) return

                const { action, row } = command

                switch (action) {
                    case 'edit':
                        this.clickCreate(row.id)
                        break
                    case 'changeActive':
                        this.clickActive(row.active, row.id)
                        break
                    case 'delete':
                        this.clickDelete(row.id)
                        break
                    default:
                        break
                }
            },
            clickAccessTokenForDiscount()
            {
                this.showDialogAuthorizedTokenForDiscount = true
            },
            getData() {
                Promise.all([
                    this.$http.get(`/${this.resource}/records`),
                    // los permisos de la app son opcionales, si falla la consulta el listado se muestra igual
                    this.$http.get('/app/permissions/records').catch(() => null),
                ])
                    .then(([users_response, permissions_response]) => {
                        const permissions_by_user = permissions_response ? _.keyBy(permissions_response.data.records, 'id') : {}

                        this.records = users_response.data.data.map(r => ({
                            ...r,
                            copied: false,
                            app_modules: permissions_by_user[r.id] ? permissions_by_user[r.id].app_modules : [],
                        }))

                        this.expanded = {}
                    })
            },
            clickCreate(recordId = null) {
                this.recordId = recordId
                this.showDialog = true
            },
            clickDelete(id) {
                this.destroy(`/${this.resource}/${id}`).then(() =>
                    this.$eventHub.$emit('reloadData')
                )
            },
            clickActive(active, id)
            {
                this.changeActive(`/${this.resource}/change-active`, { active, id })
                    .then(() => this.$eventHub.$emit('reloadData'))
            },
            async toggleBot(row) {
                const newValue = !row.bot_enabled
                try {
                    const { data } = await this.$http.post(`/${this.resource}/${row.id}/toggle-bot`, { bot_enabled: newValue })
                    if (data.success) {
                        row.bot_enabled = data.bot_enabled
                        this.$message({ message: data.message, type: 'success' })
                    } else {
                        this.$message({ message: data.message || 'No se pudo actualizar', type: 'error' })
                    }
                } catch (e) {
                    this.$message({ message: 'Error al actualizar el bot', type: 'error' })
                }
            },

            getVisiblePermissions(row) {
                return _.filter(row.app_modules, permission => {
                    return !this.hidden_app_modules.includes(permission.value)
                })
            },
            getPermissionsToShow(row) {
                const permissions = this.getVisiblePermissions(row)

                return this.expanded[row.id] ? permissions : permissions.slice(0, this.visible_chips)
            },
            getHiddenPermissionsCount(row) {
                return this.getVisiblePermissions(row).length - this.getPermissionsToShow(row).length
            },
            toggleExpanded(row) {
                this.$set(this.expanded, row.id, !this.expanded[row.id])
            },
            maskToken(token) {
                if (!token) return ''
                if (token.length <= 6) return token

                const start = token.substring(0, 4)
                const end = token.substring(token.length - 3)

                return `${start}••••••••${end}`
            },
            clickCopy(row) {
                if (!row || !row.api_token) return

                const token = row.api_token

                const markCopied = () => {
                    row.copied = true
                    setTimeout(() => { row.copied = false }, 3000)
                }

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(token)
                        .then(() => {
                            this.$message.success('Token copiado correctamente')
                            markCopied()
                        })
                        .catch(() => {
                            this.$message.error('No se pudo copiar el token')
                        })
                } else {
                    // Fallback para http
                    const textArea = document.createElement("textarea")
                    textArea.value = token
                    textArea.style.position = "fixed"
                    textArea.style.opacity = "0"
                    document.body.appendChild(textArea)
                    textArea.focus()
                    textArea.select()

                    try {
                        const successful = document.execCommand('copy')
                        if (successful) {
                            this.$message.success('Token copiado correctamente')
                            markCopied()
                        } else {
                            this.$message.error('No se pudo copiar el token')
                        }
                    } catch (err) {
                        this.$message.error('No se pudo copiar el token')
                    }

                    document.body.removeChild(textArea)
                }
            },
            openTokenModal(row) {
                this.selectedTokenRow = row
                this.showTokenDialog = true
            },
            
        }
    }
</script>

<style scoped>
.permissions-table td {
    vertical-align: middle;
}
.permission-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    max-width: 420px;
}
.permission-chip {
    display: inline-block;
    padding: 2px 5px;
    border: 1px solid var(--primary);
    border-radius: 6px;
    background-color: color-mix(in srgb, var(--primary) 10%, #fff);
    color: var(--primary);
    font-size: 11px;
    line-height: 1.4;
    white-space: nowrap;
}
.permission-chip-more {
    border-color: #e9ecef;
    background-color: #f1f3f5;
    color: #6c757d;
    cursor: pointer;
}
.permission-chip-more:hover {
    background-color: #e9ecef;
    color: #495057;
}
</style>
