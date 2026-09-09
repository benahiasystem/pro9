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
                            <th>Usuario</th>
                            <th>Empresa principal</th>
                            <th>Empresas vinculadas</th>
                        </tr>
                        <tr slot-scope="{ row }">
                            <td>
                                <div class="mu-user">
                                    <span class="mu-avatar">{{ getInitial(row.user_name) }}</span>
                                    <div class="mu-user-info">
                                        <span class="mu-user-name">{{ row.user_name }}</span>
                                        <span class="mu-user-email">{{ row.user_full_name }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="mu-company" :class="{'mu-company--missing': row.origin_missing}">
                                    <span class="mu-company-name" :title="row.client_origin_full_name">{{ row.origin_name }}</span>
                                    <span class="mu-company-meta">
                                        <span v-if="row.origin_number" class="mu-company-number">{{ row.origin_number }}</span>
                                        <span v-if="row.origin_number" class="mu-meta-dot">·</span>
                                        <span class="mu-company-host">{{ row.origin_hostname }}</span>
                                    </span>
                                    <span v-if="row.description_type" class="mu-role" :class="roleClass(row.type)">{{ row.description_type }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="mu-links">
                                    <div v-for="link in visibleLinks(row)"
                                         :key="link.id"
                                         class="mu-chip"
                                         :class="{'mu-chip--missing': link.missing}">
                                        <div class="mu-chip-body">
                                            <span class="mu-chip-name" :title="link.full_name">{{ link.name }}</span>
                                            <span class="mu-chip-meta">
                                                <span v-if="link.number" class="mu-chip-number">{{ link.number }}</span>
                                                <span v-if="link.number" class="mu-meta-dot">·</span>
                                                <span class="mu-chip-host">{{ link.hostname }}</span>
                                            </span>
                                        </div>
                                        <span v-if="link.description_type" class="mu-role" :class="roleClass(link.type)">{{ link.description_type }}</span>
                                        <el-tooltip content="Desvincular esta empresa de su cuenta" placement="top">
                                            <button type="button" class="mu-chip-remove" @click.prevent="clickDelete(row, link)">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                                            </button>
                                        </el-tooltip>
                                    </div>

                                    <button v-if="hasHiddenLinks(row)"
                                            type="button"
                                            class="mu-links-toggle"
                                            @click.prevent="toggleRow(row)">
                                        {{ isExpanded(row) ? 'Ver menos' : '+' + hiddenLinksCount(row) + ' más' }}
                                    </button>

                                    <button type="button" class="mu-links-add" @click.prevent="clickLink(row)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                                        Vincular empresa
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </data-table>
                </div>
            </div>

            <multi-user-form :recordId="recordId"
                          :composedId="presetComposedId"
                          :showDialog.sync="showDialog"></multi-user-form>


        </div>
    </div>
</template>

<script>

import MultiUserForm from './form.vue'
import DataTable from '@components/DataTable.vue'

const HELP_STORAGE_KEY = 'multi_users_help_dismissed'
const COLLAPSED_LINKS = 3

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
            collapsedLinks: COLLAPSED_LINKS,
            expandedRows: {},
            presetComposedId: null,
            checkingLink: false,
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
        linksOf(row)
        {
            return row.links || []
        },
        roleClass(type)
        {
            return type === 'seller' ? 'mu-role--seller' : 'mu-role--admin'
        },
        visibleLinks(row)
        {
            const links = this.linksOf(row)

            return this.isExpanded(row) ? links : links.slice(0, this.collapsedLinks)
        },
        hiddenLinksCount(row)
        {
            return Math.max(this.linksOf(row).length - this.collapsedLinks, 0)
        },
        hasHiddenLinks(row)
        {
            return this.hiddenLinksCount(row) > 0
        },
        isExpanded(row)
        {
            return !!this.expandedRows[row.id]
        },
        toggleRow(row)
        {
            this.$set(this.expandedRows, row.id, !this.isExpanded(row))
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
            this.expandedRows = {}
        },
        clickCreate(recordId = null)
        {
            this.recordId = recordId
            this.presetComposedId = null
            this.showDialog = true
        },
        clickLink(row)
        {
            this.recordId = null
            this.presetComposedId = row.composed_id
            this.showDialog = true
        },
        async clickDelete(row, link) {
            if (this.checkingLink) return

            this.checkingLink = true

            const check = await this.$http.get(`/${this.resource}/${link.id}/can-delete`)
                .then(response => response.data)
                .catch(() => null)
                .finally(() => {
                    this.checkingLink = false
                })

            if (!check) {
                this.$message.error('No se pudo validar la desvinculación')
                return
            }

            if (!check.success) {
                this.$alert(check.message, 'No se puede desvincular', {
                    confirmButtonText: 'Entendido',
                    type: 'warning'
                }).catch(() => {})
                return
            }

            this.$confirm(
                `Se desvinculará la empresa ${link.full_name} de la cuenta de ${row.user_full_name}. Su empresa principal no se verá afectada.`,
                'Desvincular empresa',
                {
                    confirmButtonText: 'Desvincular',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }
            ).then(() => {
                this.$http.delete(`/${this.resource}/${link.id}`)
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
