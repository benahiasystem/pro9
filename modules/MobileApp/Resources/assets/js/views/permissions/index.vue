<template>
    <div>
        <div class="card">
            <div class="card-body" v-loading="loadingUsers">
                <h4>Gestionar permisos</h4>
                <div class="col-md-12">
                <div class="table-responsive">
                    <table class="table permissions-table align-middle">
                        <thead>
                        <tr>
                            <!-- <th>#</th> -->
                            <th>Email</th>
                            <th>Nombre</th>
                            <th>Perfil</th>
                            <th>Permisos asignados</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="(row, index) in records" :key="index">
                            <!-- <td>{{ index + 1 }}</td> -->
                            <td>{{ row.email }}</td>
                            <td class="fw-bold">{{ row.name }}</td>
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
                                <span v-else class="text-muted">Sin permisos asignados</span>
                            </td>
                            <td class="text-center">
                                <template v-if="row.id == 1">—</template>
                                <span v-else-if="row.locked || !row.active" class="text-muted">
                                    {{ row.locked ? 'Suspendido' : 'Inhabilitado' }}
                                </span>
                                <template v-else>
                                    <button type="button" class="btn btn-sm waves-effect waves-light btn-primary" @click.prevent="clickShowPermissions(row.id)">
                                        <i class="fas fa-user-lock"></i>
                                    </button>
                                </template>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                </div>
            </div>
            <permission-form :showDialog.sync="showDialog"
                        :typeUser="typeUser"
                        :recordId="recordId"></permission-form>
        </div>
    </div>
</template>

<script>

    import PermissionForm from './partials/form.vue'

    export default {
        props: ['typeUser'],
        components: {PermissionForm},
        data() {
            return {
                showDialog: false,
                resource: 'users',
                recordId: null,
                records: [],
                loadingUsers: false,
                hidden_app_modules: ['order-note', 'report-sales', 'configuration'],
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
        methods: {
            getData() {
                this.loadingUsers = true;
                Promise.all([
                    this.$http.get(`/${this.resource}/records`),
                    this.$http.get('/app/permissions/records'),
                ])
                    .then(([users_response, permissions_response]) => {
                        const permissions_by_user = _.keyBy(permissions_response.data.records, 'id')

                        this.records = _.map(users_response.data.data, row => {
                            const permissions = permissions_by_user[row.id]

                            return Object.assign({}, row, {
                                app_modules: permissions ? permissions.app_modules : [],
                            })
                        })

                        this.expanded = {}
                    })
                    .finally(() => {
                        this.loadingUsers = false;
                    })

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
            clickShowPermissions(recordId) {

                if(recordId != 1)
                {
                    this.recordId = recordId
                    this.showDialog = true
                }
                else
                {
                    this.$message.warning('El usuario principal tiene todos los permisos asignados, no puede modificarlos.')
                }
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
