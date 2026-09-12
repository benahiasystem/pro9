<template>
    <div>
        <div class="page-header pe-0">
            <h2>
                <a href="/list-reports">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        style="margin-top: -5px;"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-file-analytics"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                        <path
                            d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"
                        />
                        <path d="M9 17l0 -5" />
                        <path d="M12 17l0 -1" />
                        <path d="M15 17l0 -3" />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span> Productos por Agotarse </span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <button
                    class="btn btn-custom btn-sm mt-2 me-2"
                    type="button"
                    @click.prevent="clickDownload('pdf')"
                >
                    <i class="fa fa-file-pdf"></i> Exportar PDF
                </button>
                <button
                    class="btn btn-custom btn-sm mt-2 me-2"
                    type="button"
                    @click.prevent="clickDownload('excel')"
                >
                    <i class="fa fa-file-excel"></i> Exportar Excel
                </button>
            </div>
        </div>
        <div class="card mb-0 pt-2 pt-md-0 tab-content-default row-new">
            <div class="card mb-0">
                <div class="card-body">
                    <!-- Filtros -->
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

                    <div class="row mt-2" v-if="isVisible">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Sucursal</label>
                                <el-select v-model="form.establishment_id" clearable @change="getRecords">
                                    <el-option
                                        v-for="option in establishments"
                                        :key="option.id"
                                        :label="option.name"
                                        :value="option.id"
                                    ></el-option>
                                </el-select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Fecha del</label>
                                <el-date-picker
                                    v-model="form.date_start"
                                    :clearable="false"
                                    format="dd/MM/yyyy"
                                    type="date"
                                    value-format="yyyy-MM-dd"
                                    @change="changeDisabledDates"
                                ></el-date-picker>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Fecha al</label>
                                <el-date-picker
                                    v-model="form.date_end"
                                    :clearable="false"
                                    :picker-options="pickerOptionsDates"
                                    format="dd/MM/yyyy"
                                    type="date"
                                    value-format="yyyy-MM-dd"
                                ></el-date-picker>
                            </div>
                        </div>

                        <div class="col-md-3" style="margin-top: 29px;">
                            <el-button
                                :loading="loading_submit"
                                class="submit"
                                icon="el-icon-search"
                                type="primary"
                                @click.prevent="getRecordsByFilter"
                            >Buscar</el-button>
                        </div>
                    </div>

                    <!-- Tabla -->
                    <div class="table-responsive mt-3">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Cód. Interno</th>
                                    <th>Producto</th>
                                    <th class="text-center">Stock</th>
                                    <th>Estado</th>
                                    <th>Almacén</th>
                                    <th class="text-center">Aprovisionar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in records" :key="index">
                                    <td>{{ customIndex(index) }}</td>
                                    <td>{{ row.internal_id }}</td>
                                    <td>{{ row.product }}</td>
                                    <td class="text-center">{{ row.stock }}</td>
                                    <td>
                                        <span class="badge bg-danger text-white" v-if="row.state_code == '01'">Agotado</span>
                                        <span class="badge bg-warning text-white" v-if="row.state_code == '02'">Pocas unidades</span>
                                    </td>
                                    <td>{{ row.warehouse }}</td>
                                    <td class="text-center">
                                        <button
                                            type="button"
                                            style="min-width: 41px"
                                            class="btn waves-effect waves-light btn-xs btn-primary m-1__2"
                                            @click.prevent="clickProvision()"
                                        >
                                            <i class="fas fa-shopping-cart"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="records.length === 0">
                                    <td colspan="7" class="text-center">No se encontraron registros.</td>
                                </tr>
                            </tbody>
                        </table>
                        <div>
                            <el-pagination
                                :current-page.sync="pagination.current_page"
                                :page-size="pagination.per_page"
                                :total="pagination.total"
                                layout="total, prev, pager, next"
                                @current-change="getRecords"
                            ></el-pagination>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import queryString from 'query-string'

export default {
    data() {
        return {
            isVisible: false,
            loading_submit: false,
            records: [],
            establishments: [],
            pagination: {},
            form: {},
            pickerOptionsDates: {
                disabledDate: (time) => {
                    time = moment(time).format('YYYY-MM-DD')
                    return this.form.date_start > time
                }
            },
        }
    },
    created() {
        this.initForm()
    },
    async mounted() {
        await this.$http.get('/reports/stock-running-out/filter')
            .then(response => {
                this.establishments = response.data.establishments
            })

        await this.getRecords()
    },
    methods: {
        toggleInformation() {
            this.isVisible = !this.isVisible
        },
        initForm() {
            this.form = {
                establishment_id: null,
                date_start: moment().startOf('month').format('YYYY-MM-DD'),
                date_end: moment().endOf('month').format('YYYY-MM-DD'),
            }
        },
        customIndex(index) {
            return (this.pagination.per_page * (this.pagination.current_page - 1)) + index + 1
        },
        async getRecordsByFilter() {
            this.loading_submit = true
            await this.getRecords()
            this.loading_submit = false
        },
        getRecords() {
            return this.$http.get(`/reports/stock-running-out/records?${this.getQueryParameters()}`).then((response) => {
                this.records = response.data.data
                this.pagination = response.data.meta
                this.pagination.per_page = parseInt(response.data.meta.per_page)
                this.loading_submit = false
            })
        },
        getQueryParameters() {
            return queryString.stringify({
                page: this.pagination.current_page,
                ...this.form
            })
        },
        clickDownload(type) {
            let query = queryString.stringify({
                ...this.form
            })
            window.open(`/reports/stock-running-out/${type}?${query}`, '_blank')
        },
        changeDisabledDates() {
            if (this.form.date_end < this.form.date_start) {
                this.form.date_end = this.form.date_start
            }
        },
        clickProvision() {
            window.open('/purchases/create')
        }
    }
}
</script>