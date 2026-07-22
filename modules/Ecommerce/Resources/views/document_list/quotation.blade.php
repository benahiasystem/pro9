@extends('ecommerce::layouts.layout_account')
@section('account_content')
<style>
.table-loader {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.6);
  backdrop-filter: blur(3px);
  z-index: 10;
  display: none;
}
.table.table-cart tr th{
    text-align: left;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--subtitle-color);
    padding: 14px 26px !important;
    background: #fafbfc;
    border-bottom: 1px solid var(--line);
    border-radius: 0 !important;
}
.quote-state {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    background: #fff4eb;
    color: var(--primary-color, #ff7a00);
}
.quote-state.is-voided {
    background: #f3f5f7;
    color: #667085;
}
.quote-state.is-expired {
    background: #fef3c7;
    color: #92400e;
}
.quote-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.quote-actions .btn {
    font-size: 12px;
}
</style>

<div id="app">
    <div class="panel-head">
        <span class="panel-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><path d="M9 9l1 0" /><path d="M9 13l6 0" /><path d="M9 17l6 0" /></svg>
        </span>
        <div>
            <h2 class="m-0">Mis cotizaciones</h2>
            <p class="m-0">Consulta el estado y descarga el PDF de tus cotizaciones.</p>
        </div>
    </div>

    <div class="cart-table-container position-relative">
        <div class="dropdown dropdown-table d-flex justify-content-between align-items-center filters mb-3">
            <div class="d-flex align-items-end flex-wrap">
                <div class="d-flex flex-column">
                    <span>Fecha de inicio</span>
                    <el-date-picker
                        v-model="filters.date_of_start"
                        type="date"
                        placeholder="Seleccionar fecha"
                        size="small"
                        style="width: 180px;"
                        format="dd/MM/yyyy"
                        value-format="yyyy-MM-dd"
                        clearable>
                    </el-date-picker>
                </div>
                <div class="d-flex flex-column ml-2">
                    <span>Fecha de fin</span>
                    <el-date-picker
                        v-model="filters.date_of_end"
                        type="date"
                        placeholder="Seleccionar fecha"
                        size="small"
                        style="width: 180px;"
                        format="dd/MM/yyyy"
                        value-format="yyyy-MM-dd"
                        clearable>
                    </el-date-picker>
                </div>
                <button class="btn-filter-search ml-2 p-0" @click="page = 1; getRecords()" type="button" aria-label="Buscar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg>
                </button>
            </div>
        </div>

        <table class="table table-cart rounded-0">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Total</th>
                    <th>Fecha</th>
                    <th>Vigencia</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!loading && records.length === 0">
                    <td colspan="6" class="text-center text-muted py-5">
                        Aún no tienes cotizaciones. Solicítalas desde el carrito de compras.
                    </td>
                </tr>
                <tr
                    v-for="row in records"
                    :key="row.id"
                    class="product-row"
                    style="cursor:pointer"
                    @click="openDetail(row)"
                >
                    <td class="text-left">
                        <strong>@{{ row.code || row.number_full }}</strong>
                    </td>
                    <td class="text-success">S/ @{{ formatMoney(row.total) }}</td>
                    <td>@{{ formatDateOnly(row.date_of_issue) }}</td>
                    <td>@{{ formatDateOnly(row.date_of_due) }}</td>
                    <td>
                        <span
                            class="quote-state"
                            :class="{
                                'is-voided': row.state_type_id === '11',
                                'is-expired': row.is_expired
                            }"
                        >
                            @{{ row.state_type_description }}
                        </span>
                    </td>
                    <td @click.stop>
                        <div class="quote-actions">
                            <a
                                v-if="row.print_url"
                                :href="row.print_url"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-sm btn-outline-secondary"
                            >
                                Ver PDF
                            </a>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <nav aria-label="Paginación cotizaciones" v-if="last_page > 1">
            <ul class="pagination">
                <li class="page-item" :class="{ disabled: pagL }">
                    <a class="page-link" href="#" tabindex="-1" @click.prevent="changePage(page - 1)">&laquo;</a>
                </li>
                <li class="page-item active" aria-current="page">
                    <a class="page-link" href="#">@{{ page }}</a>
                </li>
                <li class="page-item" :class="{ disabled: pagR }">
                    <a class="page-link" href="#" @click.prevent="changePage(page + 1)">&raquo;</a>
                </li>
            </ul>
        </nav>

        <div
            id="tableLoader"
            class="d-flex justify-content-center align-items-center"
            :class="{ 'table-loader': loading }"
            :style="{ display: loading ? 'flex' : 'none' }"
        >
            <div class="loader" role="status"></div>
        </div>
    </div>

    <quotation-detail
        :visible.sync="showQuotationModal"
        :record="selectedQuotation"
        :loading="detailLoading">
    </quotation-detail>
</div>
@include('ecommerce::document_list.quotation_detail')
@endsection

@push('scripts')
<script type="text/javascript">
    Vue.use(ELEMENT, { locale: ELEMENT.lang.es });
    Vue.component('quotation-detail', {
        props: ['visible', 'record', 'loading'],
        template: '#quotation-detail-template',
        watch: {
            visible(val) {
                if (val) {
                    $('#quotationDetailModal').modal('show');
                } else {
                    $('#quotationDetailModal').modal('hide');
                }
            }
        },
        mounted() {
            $('#quotationDetailModal').on('hidden.bs.modal', () => {
                this.$emit('update:visible', false);
            });
        },
        methods: {
            close() {
                this.$emit('update:visible', false);
            }
        }
    });
    new Vue({
        el: '#app',
        data: {
            records: [],
            page: 1,
            loading: false,
            detailLoading: false,
            showQuotationModal: false,
            selectedQuotation: null,
            filters: {
                date_of_start: null,
                date_of_end: null,
            },
            last_page: 1,
        },
        computed: {
            pagL() { return this.page <= 1; },
            pagR() { return this.page >= this.last_page; },
        },
        created() {
            this.getRecords();
        },
        methods: {
            formatDateOnly(date) {
                if (!date) return '—';
                const parsed = moment(date);
                return parsed.isValid() ? parsed.format('DD/MM/YYYY') : '—';
            },
            formatMoney(amount) {
                return Number(amount || 0).toLocaleString('es-PE', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            },
            changePage(page) {
                if (page < 1 || page > this.last_page) return;
                this.page = page;
                this.getRecords();
            },
            openDetail(row) {
                if (!row || !row.id) return;
                this.detailLoading = true;
                axios.get(`{{ url('ecommerce/quotations') }}/${row.id}`)
                    .then(response => {
                        const payload = response.data || {};
                        if (payload.success === false) {
                            throw new Error(payload.message || 'No se pudo cargar el detalle');
                        }
                        this.selectedQuotation = payload.data || payload;
                        this.showQuotationModal = true;
                    })
                    .catch(error => {
                        console.error(error);
                        this.selectedQuotation = null;
                        this.showQuotationModal = false;
                        if (typeof swal === 'function') {
                            swal('Error', 'No se pudo cargar el detalle de la cotización.', 'error');
                        }
                    })
                    .finally(() => {
                        this.detailLoading = false;
                    });
            },
            getRecords() {
                this.loading = true;
                const params = Object.assign({ page: this.page }, this.filters);
                axios.get('{{ route("tenant_ecommerce_quotations") }}', { params })
                    .then(response => {
                        const payload = response.data || {};
                        this.records = payload.data || [];
                        this.last_page = (payload.meta && payload.meta.last_page)
                            || (payload.links && payload.links.last_page)
                            || 1;
                        this.page = (payload.meta && payload.meta.current_page) || this.page;
                    })
                    .catch(error => {
                        console.error(error);
                        this.records = [];
                        if (typeof swal === 'function') {
                            swal('Error', 'No se pudieron cargar las cotizaciones.', 'error');
                        }
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },
        },
    });
</script>
@endpush
