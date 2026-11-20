<template>
    <el-dialog :title="titleDialog"
               width="40%"
               :visible="showDialog"
               @open="create"
               :close-on-click-modal="false"
               :close-on-press-escape="false"
               append-to-body
               :show-close="false">

        <div class="form-body">
            <div class="col-md-12 text-end">
                <h5>Cant. Pedida: {{ quantity }}</h5>
                <h5 v-bind:class="{ 'text-danger': seriesSelectionInvalid }">Por Atender: {{ toAttend }}</h5>
            </div>
            <div class="row">
                <div class="col-md-6 col-lg-6 col-xl-6 ">
                    <el-input  ref="searchInput" placeholder="Buscar serie ..."
                              v-model="search.input"
                              style="width: 100%;"
                              prefix-icon="el-icon-search"
                              @input="getRecords(true)">
                    </el-input>
                </div>
                <div class="col-md-12" v-loading="loading">
                    <div class="table-responsive mt-3">
                        <table class="table">
                            <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th class="text-center">Seleccionar</th>
                                <th>Cod. Lote</th>
                                <th>Serie</th>
                                <th>Fecha</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(row, index) in records" :key="index">
                                <td class="text-center">
                                    {{ customIndex(index) }}
                                </td>
                                <td class="text-center">
                                    <el-checkbox v-model="row.has_sale"
                                                 @change="changeHasSale(row, index)"></el-checkbox>
                                </td>
                                <td>
                                    {{ row.lot_code }}
                                </td>
                                <td>
                                    {{ row.series }}
                                </td>
                                <td>
                                    {{ row.date }}
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <div>
                            <el-pagination
                                @current-change="getRecords"
                                layout="total, prev, pager, next"
                                :total="pagination.total"
                                :current-page.sync="pagination.current_page"
                                :page-size="pagination.per_page"
                            >
                            </el-pagination>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions text-end pt-2">
            <el-button @click.prevent="close">Cerrar</el-button>
            <el-button type="primary"
                       :disabled="seriesSelectionInvalid"
                       @click="submit">Guardar
            </el-button>
        </div>
    </el-dialog>
</template>

<script>
import {serialIdKey} from "../../../../helpers/invoice-lots";
import queryString from "query-string";
import { exactAmount } from "../../../../helpers/exchange-rate-math";

export default {
    props: [
        'showDialog',
        'lotsAll',
        'lots',
        'stock',
        'itemId',
        'documentItemId',
        'quantity',
        'saleNoteItemId',
        'warehouseId',
        'inputSearch',
        'documentId',
        'exactQuantities',
        'exactQuantity',
    ],
    data() {
        return {
            titleDialog: 'Series',
            resource: 'documents',
            search_series_by_barcode: false,
            loading: false,
            errors: {},
            form: {},
            lotsSelected: [],
            records: [],
            pagination: {},
            search: {
                input: null,
                item_id: null
            },
        }
    },
    computed: {
        exactSeriesBalance() {
            if (!this.exactQuantities || this.exactQuantity == null) return null;
            return exactAmount(this.exactQuantity).minus(this.lotsSelected.length);
        },
        toAttend() {
            if (!this.exactQuantities) return this.quantity - this.lotsSelected.length;
            if (!this.exactSeriesBalance) return '—';
            const decimal = this.exactSeriesBalance.decimal();
            if (decimal !== '0' || this.exactSeriesBalance.compare(0) === 0) return decimal;
            const {n, d} = this.exactSeriesBalance.fraction();
            return `${n}/${d}`;
        },
        seriesSelectionInvalid() {
            if (!this.exactQuantities) return this.toAttend < 0;
            return !this.exactSeriesBalance || this.exactSeriesBalance.compare(0) < 0;
        },
    },
    async mounted() {
        await this.getRecords()
    },
    // async created() {
    //     this.$eventHub.$on('reloadLotsDataTable', () => {
    //         this.getRecords()
    //     })
    // },
    methods: {
        initForm() {
            this.search = {
                input: null,
                item_id: null,
                document_id: null,
                document_item_id: this.documentItemId,
                sale_note_item_id: this.saleNoteItemId,
                warehouse_id: this.warehouseId,
            }
        },
        async create() {
            this.initForm()
            this.lotsSelected = this.lots;
            await this.getRecords();
        },
        async getRecords() {
            this.search.item_id = this.itemId
            // se puede activar la busqueda pero faltan acciones
            // this.search.input = this.inputSearch
            this.search.document_id = (this.documentId)?this.documentId:null
            this.records = [];
            this.loading = true
            await this.$http.post(`/${this.resource}/item_lots?${this.getQueryParameters()}`, this.search)
                .then((response) => {
                    this.records = response.data.data
                    this.pagination = response.data.meta;
                    this.pagination.per_page = parseInt(
                        response.data.meta.per_page
                    );
                })
                .catch(error => {
                })
                .then(() => {
                    this.loading = false
                })
            await this.recordToSelected()
            await this.checkedLot();
        },
        recordToSelected() {

            if(this.search.input){
                const isAlreadySelected = this.lotsSelected.some(lot => lot.series === this.search.input);
                if (!isAlreadySelected) {
                    const lot = this.records.find(record => record.series === this.search.input);
                    if (lot && serialIdKey(lot.id) !== null) {
                        lot.has_sale = true
                        this.lotsSelected.push(lot)
                        this.$nextTick(() => {
                            this.$refs.searchInput.select();
                        });
                    }
                }
            }
        },
        getQueryParameters() {
            return queryString.stringify({
                page: this.pagination.current_page
            });
        },
        changeSearchSeriesBarcode() {
            this.cleanInput()
        },
        cleanInput() {
            this.search.input = null
        },
        // async searchSeriesBarcode() {
        //     await this.getRecords(true)
        //     await this.checkedSeries()
        // },
        // async checkedSeries() {
        //     if (this.search_series_by_barcode) {
        //         if (this.records.length == 1) {
        //             let lot = await _.find(this.lots, {id: this.records[0].id})
        //             if (!lot) {
        //                 this.records[0].has_sale = true
        //                 this.addLot(this.records[0])
        //             }
        //         }
        //         this.cleanInput();
        //     }
        // },
        changeHasSale(row, index) {
            const key = serialIdKey(row.id);
            if (key === null) {
                row.has_sale = false;
                return;
            }
            const lotIndex = this.lotsSelected.findIndex(lot => serialIdKey(lot.id) === key);
            if (lotIndex > -1) {
                this.lotsSelected.splice(lotIndex, 1)
                row.has_sale = false;
            } else {
                this.lotsSelected.push(row);
                row.has_sale = true;
            }
        },
        customIndex(index) {
            return (
                this.pagination.per_page * (this.pagination.current_page - 1) +
                index +
                1
            );
        },
        checkedLot() {
            const selected = new Set(this.lotsSelected.map(lot => serialIdKey(lot.id)));
            selected.delete(null);
            for (const lot of this.records) {
                if (selected.has(serialIdKey(lot.id))) lot.has_sale = true;
            }
        },
        submit() {
            if (this.seriesSelectionInvalid) {
                return this.$message.error('La cantidad de series seleccionadas no es la correcta');
            }
            this.$emit('addRowSelectLot', this.lotsSelected);
            this.close();
        },
        close() {
            this.$emit('update:showDialog', false)
        },
    }
}
</script>
