<template>
    <div>
        <div class="btn-filter-content">
            <el-button
                type="secondary"
                class="btn-show-filter"
                :class="{ shift: isVisible }"
                @click="toggleInformation"
            >
                {{ isVisible ? "Ocultar filtros" : "Mostrar filtros" }}
            </el-button>
        </div>
        <div class="row">
            <div class="col-md-12 col-lg-12 col-xl-12" v-if="isVisible">
                <div class="row mt-2">
                    <div class="col-md-3 form-modern">
                        <label class="control-label">Periodo</label>
                        <el-select v-model="form.period" @change="changePeriod">
                            <el-option key="month" label="Por mes" value="month"></el-option>
                            <el-option key="between_months" label="Entre meses" value="between_months"></el-option>
                            <el-option key="date" label="Por fecha" value="date"></el-option>
                            <el-option key="between_dates" label="Entre fechas" value="between_dates"></el-option>
                        </el-select>
                    </div>
                    <template v-if="form.period === 'month' || form.period === 'between_months'">
                        <div class="col-md-3 form-modern">
                            <label class="control-label">Mes de</label>
                            <el-date-picker v-model="form.month_start"
                                            :clearable="false"
                                            format="MM/yyyy"
                                            type="month"
                                            value-format="yyyy-MM"
                                            @change="changeDisabledMonths"></el-date-picker>
                        </div>
                    </template>
                    <template v-if="form.period === 'between_months'">
                        <div class="col-md-3">
                            <label class="control-label">Mes al</label>
                            <el-date-picker v-model="form.month_end"
                                            :clearable="false"
                                            :picker-options="pickerOptionsMonths"
                                            format="MM/yyyy"
                                            type="month"
                                            value-format="yyyy-MM"></el-date-picker>
                        </div>
                    </template>
                    <template v-if="form.period === 'date' || form.period === 'between_dates'">
                        <div class="col-md-3">
                            <label class="control-label">Fecha del</label>
                            <el-date-picker v-model="form.date_start"
                                            :clearable="false"
                                            format="dd/MM/yyyy"
                                            type="date"
                                            value-format="yyyy-MM-dd"
                                            @change="changeDisabledDates"></el-date-picker>
                        </div>
                    </template>
                    <template v-if="form.period === 'between_dates'">
                        <div class="col-md-3">
                            <label class="control-label">Fecha al</label>
                            <el-date-picker v-model="form.date_end"
                                            :clearable="false"
                                            :picker-options="pickerOptionsDates"
                                            format="dd/MM/yyyy"
                                            type="date"
                                            value-format="yyyy-MM-dd"></el-date-picker>
                        </div>
                    </template>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label">Tipo de usuario</label>
                            <el-select v-model="form.user_type">
                                <el-option key="user_id" label="Registrado por" value="user_id"></el-option>
                                <el-option key="seller_id" label="Vendedor asignado" value="seller_id"></el-option>
                            </el-select>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-3">
                        <div class="form-group">
                            <label class="control-label">
                                {{ form.user_type === 'user_id' ? 'Usuario' : 'Vendedor' }}
                            </label>
                            <el-select v-model="form.user_seller_id"
                                       clearable
                                       filterable
                                       placeholder="Nombre usuario"
                                       popper-class="el-select-customers">
                                <el-option v-for="option in sellers"
                                           :key="option.id"
                                           :label="option.name"
                                           :value="option.id"></el-option>
                            </el-select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label">Sucursal</label>
                            <el-select v-model="form.establishment_id" clearable>
                                <el-option v-for="option in establishments"
                                           :key="option.id"
                                           :label="option.name"
                                           :value="option.id"></el-option>
                            </el-select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-7 col-md-7 col-sm-12" style="margin-top:29px">
                        <el-button :loading="loading_submit"
                                   class="submit"
                                   icon="el-icon-search"
                                   type="primary"
                                   @click.prevent="getRecordsByFilter">Buscar
                        </el-button>
                        <template v-if="records.length>0">
                            <el-button class="submit"
                                       icon="el-icon-tickets"
                                       type="danger"
                                       :disabled="hasUnappliedFilters || loading_submit"
                                       @click.prevent="clickDownload('pdf')">Exportar PDF
                            </el-button>
                            <el-button class="submit"
                                       type="success"
                                       :disabled="hasUnappliedFilters || loading_submit"
                                       @click.prevent="clickDownload('excel')"><i class="fa fa-file-excel"></i> Exportar Excel
                            </el-button>
                            <span v-if="hasUnappliedFilters">Los filtros cambiaron. Pulse Buscar para actualizar el reporte.</span>
                        </template>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Usuario</th>
                                <th class="text-center">Monto de cobro sin comisión</th>
                                <th class="text-center">Monto de cobro elegible</th>
                                <th class="text-center">Monto total de cobro efectuado</th>
                                <th class="text-center">Comisión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in records" :key="row.id">
                                <td>{{ customIndex(index) }}</td>
                                <td>{{ row.user_name }}</td>
                                <td class="text-center"><div v-for="entry in row.currency_rows" :key="entry.currency_type_id">{{ entry.currency_type_id }} {{ entry.total_out_of_range }}</div></td>
                                <td class="text-center"><div v-for="entry in row.currency_rows" :key="entry.currency_type_id">{{ entry.currency_type_id }} {{ entry.total_within_range }}</div></td>
                                <td class="text-center"><div v-for="entry in row.currency_rows" :key="entry.currency_type_id">{{ entry.currency_type_id }} {{ entry.total_completed }}</div></td>
                                <td class="text-center"><span v-if="!row.currency_rows.length">Sin cobros en el período</span><div v-for="entry in row.currency_rows" :key="entry.currency_type_id">{{ entry.commission_reason || (entry.currency_type_id + ' ' + entry.commission) }}</div></td>
                            </tr>
                        </tbody>
                    </table>
                    <div>
                        <el-pagination
                            :current-page.sync="pagination.current_page"
                            :page-size="pagination.per_page"
                            :total="pagination.total"
                            layout="total, prev, pager, next"
                            @current-change="getRecords">
                        </el-pagination>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import moment from 'moment'
import queryString from 'query-string'

export default {
    props: {
        resource: String,
    },
    data() {
        return {
            isVisible: false,
            loading_submit: false,
            records: [],
            appliedForm: null,
            recordsRequestId: 0,
            pagination: {},
            form: {},
            sellers: [],
            establishments: [],
            pickerOptionsDates: {
                disabledDate: (time) => {
                    time = moment(time).format('YYYY-MM-DD')
                    return this.form.date_start > time
                }
            },
            pickerOptionsMonths: {
                disabledDate: (time) => {
                    time = moment(time).format('YYYY-MM')
                    return this.form.month_start > time
                }
            },
        }
    },
    computed: {
        hasUnappliedFilters() {
            return !!this.appliedForm && JSON.stringify(this.form) !== JSON.stringify(this.appliedForm)
        },
    },
    created() {
        this.initForm()
        this.$eventHub.$on('reloadData', () => {
            this.getRecords()
        })
    },
    async mounted() {
        await this.$http.get(`/${this.resource}/filter`)
            .then(response => {
                this.establishments = response.data.establishments;
                this.sellers = response.data.sellers || [];
            });
        await this.getRecords()
    },
    methods: {
        toggleInformation(){
            this.isVisible = !this.isVisible;
        },
        clickDownload(type) {
            if (!this.appliedForm || this.hasUnappliedFilters || this.loading_submit) {
                return this.$message.error('Pulse Buscar para actualizar el reporte antes de exportar.')
            }
            let query = queryString.stringify({
                ...this.appliedForm
            });
            window.open(`/${this.resource}/${type}/?${query}`, '_blank');
        },
        initForm() {
            this.form = {
                user_type: 'user_id',
                establishment_id: null,
                period: 'month',
                date_start: moment().format('YYYY-MM-DD'),
                date_end: moment().format('YYYY-MM-DD'),
                month_start: moment().format('YYYY-MM'),
                month_end: moment().format('YYYY-MM'),
                user_seller_id: null,
            }
        },
        customIndex(index) {
            return (this.pagination.per_page * (this.pagination.current_page - 1)) + index + 1
        },
        async getRecordsByFilter() {
            return this.getRecords({...this.form}, 1)
        },
        getRecords(filters, page) {
            const selected = filters && typeof filters === 'object' ? filters : (this.appliedForm || this.form)
            const snapshot = {...selected}
            const pageNumber = typeof filters === 'number' ? filters : (page || this.pagination.current_page || 1)
            const requestId = this.recordsRequestId = (this.recordsRequestId || 0) + 1
            this.loading_submit = true
            return this.$http.get(`/${this.resource}/records?${this.getQueryParameters(snapshot, pageNumber)}`).then((response) => {
                if (requestId !== this.recordsRequestId) return false
                this.records = response.data.data
                this.pagination = response.data.meta
                this.pagination.per_page = parseInt(response.data.meta.per_page)
                this.appliedForm = snapshot
                return true
            }).catch(error => {
                if (requestId !== this.recordsRequestId) return false
                this.records = []
                this.pagination = {}
                this.appliedForm = null
                const data = error.response && error.response.data
                const errors = data && (data.errors || (typeof data.message === 'object' && data.message))
                this.$message.error(errors ? Object.values(errors).flat().join(' ') : (data && data.message) || 'No se pudo consultar el reporte.')
                return false
            }).finally(() => {
                if (requestId === this.recordsRequestId) this.loading_submit = false
            })
        },
        getQueryParameters(filters = this.appliedForm || this.form, page = this.pagination.current_page) {
            return queryString.stringify({
                page,
                ...filters
            })
        },
        changeDisabledDates() {
            if (this.form.date_end < this.form.date_start) {
                this.form.date_end = this.form.date_start
            }
        },
        changeDisabledMonths() {
            if (this.form.month_end < this.form.month_start) {
                this.form.month_end = this.form.month_start
            }
        },
        changePeriod() {
            if (this.form.period === 'month') {
                this.form.month_start = moment().format('YYYY-MM');
                this.form.month_end = moment().format('YYYY-MM');
            }
            if (this.form.period === 'between_months') {
                this.form.month_start = moment().startOf('year').format('YYYY-MM');
                this.form.month_end = moment().endOf('year').format('YYYY-MM');
            }
            if (this.form.period === 'date') {
                this.form.date_start = moment().format('YYYY-MM-DD');
                this.form.date_end = moment().format('YYYY-MM-DD');
            }
            if (this.form.period === 'between_dates') {
                this.form.date_start = moment().startOf('month').format('YYYY-MM-DD');
                this.form.date_end = moment().endOf('month').format('YYYY-MM-DD');
            }
        },
    }
}
</script>
