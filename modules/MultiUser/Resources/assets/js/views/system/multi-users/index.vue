<template>
    <div>
        <div class="page-header pe-0">
            <h2><a href="/dashboard">
                <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20" viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-users-group" style="margin-top: -5px;"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 13a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M8 21v-1a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v1" /><path d="M15 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M17 10h2a2 2 0 0 1 2 2v1" /><path d="M5 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M3 13v-1a2 2 0 0 1 2 -2h2" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>{{ title }}</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <button class="btn btn-default btn-sm mt-2 me-2" type="button" @click.prevent="toggleHelp">
                    <i class="fa fa-question-circle"></i> ¿Cómo funciona?
                </button>
                <button class="btn btn-custom btn-sm mt-2 me-2" type="button" @click.prevent="clickCreate()"><i
                    class="fa fa-plus-circle"></i> Vincular empresa
                </button>
            </div>
        </div>

        <div v-if="showHelp" class="card mb-0">
            <div class="card-body mu-help">
                <div class="mu-help-header">
                    <h6 class="mu-help-title">¿Qué hace este módulo?</h6>
                    <button type="button" class="mu-help-close" title="Ocultar ayuda" @click="dismissHelp">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                    </button>
                </div>
                <p class="mu-help-text">
                    Cuando una persona tiene <b>más de una empresa</b>, aquí vinculas sus otras empresas a su
                    <b>misma cuenta</b>: ingresa a todas con el mismo correo y contraseña, sin manejar cuentas separadas.
                </p>
                <div class="mu-help-steps">
                    <div class="mu-help-step">
                        <span class="mu-help-step-number">1</span>
                        <div class="mu-help-step-body">
                            <b>Elige el usuario</b>
                            <span>La cuenta de la persona, en la empresa donde ya la usa.</span>
                        </div>
                    </div>
                    <div class="mu-help-step">
                        <span class="mu-help-step-number">2</span>
                        <div class="mu-help-step-body">
                            <b>Elige su otra empresa</b>
                            <span>La empresa que se vinculará a su cuenta.</span>
                        </div>
                    </div>
                    <div class="mu-help-step">
                        <span class="mu-help-step-number">3</span>
                        <div class="mu-help-step-body">
                            <b>Listo</b>
                            <span>Inicia sesión una sola vez y alterna entre sus empresas cuando lo necesite.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-0 mt-1">
            <div class="card-body">
                <div class="mu-table">
                    <data-table :resource="resource" @records-changed="onRecordsChanged">
                        <tr slot="heading">
                            <th>#</th>
                            <th>Usuario</th>
                            <th>Empresas del usuario</th>
                            <th class="text-center">Perfil</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                        <tr slot-scope="{ index, row }">
                            <td class="text-muted">{{ index }}</td>
                            <td>
                                <div class="d-flex flex-column justify-content-start align-items-start">
                                    <span class="">{{ row.user_name }}</span>
                                    <span class="mu-user-email text-muted">{{ row.user_full_name }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="mu-flow">
                                    <div class="mu-company" :class="{'mu-company--missing': isMissingClient(row.client_origin_full_name)}">
                                        <span class="mu-company-label">Empresa principal</span>
                                        <span class="mu-company-name" :title="row.client_origin_full_name">{{ row.client_origin_full_name }}</span>
                                        <span class="mu-company-host">{{ row.origin_hostname }}</span>
                                    </div>
                                    <span class="mu-flow-link" title="Vinculadas a la misma cuenta">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 15l6 -6" /><path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464" /><path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463" /></svg>
                                    </span>
                                    <div class="mu-company mu-company--destination" :class="{'mu-company--missing': isMissingClient(row.client_destination_full_name)}">
                                        <span class="mu-company-label">Empresa vinculada</span>
                                        <span class="mu-company-name" :title="row.client_destination_full_name">{{ row.client_destination_full_name }}</span>
                                        <span class="mu-company-host">{{ row.destination_hostname }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-primary">{{ row.description_type }}</span>
                            </td>
                            <td class="text-end">
                                <el-tooltip content="Desvincular esta empresa de su cuenta" placement="top">
                                    <el-button type="danger" plain size="mini" class="mu-btn-icon" @click.prevent="clickDelete(row)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                    </el-button>
                                </el-tooltip>
                            </td>
                        </tr>
                    </data-table>
                </div>
            </div>

            <multi-user-form :recordId="recordId"
                          :showDialog.sync="showDialog"></multi-user-form>


        </div>
    </div>
</template>

<script>

import MultiUserForm from './form.vue'
import DataTable from '@components/DataTable.vue'

const HELP_STORAGE_KEY = 'multi_users_help_dismissed'

export default {
    components: {
        MultiUserForm,
        DataTable
    },
    data() {
        return {
            title: null,
            showDialog: false,
            resource: 'multi-users',
            recordId: null,
            total: null,
            showHelp: true,
        }
    },
    created()
    {
        this.title = 'Multi Usuarios'
        this.showHelp = localStorage.getItem(HELP_STORAGE_KEY) !== 'true'
    },
    mounted()
    {
        this.$nextTick(() => {
            const dataTable = this.$root.$refs.DataTable
            if (dataTable) dataTable.isVisible = true
        })
    },
    methods:
    {
        getInitial(value)
        {
            return value ? value.trim().charAt(0).toUpperCase() : '?'
        },
        isMissingClient(name)
        {
            return name === 'Cliente eliminado'
        },
        toggleHelp()
        {
            this.showHelp = !this.showHelp
            localStorage.setItem(HELP_STORAGE_KEY, this.showHelp ? 'false' : 'true')
        },
        dismissHelp()
        {
            this.showHelp = false
            localStorage.setItem(HELP_STORAGE_KEY, 'true')
        },
        onRecordsChanged()
        {
            const dataTable = this.$root.$refs.DataTable
            this.total = (dataTable && dataTable.pagination && dataTable.pagination.total !== undefined)
                ? dataTable.pagination.total
                : null
        },
        clickCreate(recordId = null)
        {
            this.recordId = recordId
            this.showDialog = true
        },
        clickDelete(row) {
            this.$confirm(
                `Se desvinculará la empresa ${row.client_destination_full_name} de la cuenta de ${row.user_full_name}. Su empresa principal no se verá afectada.`,
                'Desvincular empresa',
                {
                    confirmButtonText: 'Desvincular',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }
            ).then(() => {
                this.$http.delete(`/${this.resource}/${row.id}`)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(() => {
                        this.$message.error('Error al intentar eliminar')
                    })
                    .finally(() => {
                        this.$eventHub.$emit('reloadData')
                    })
            }).catch(() => {})
        },
    }
}
</script>
