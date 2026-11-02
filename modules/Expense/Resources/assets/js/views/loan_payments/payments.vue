<template>
    <el-dialog :title="title"
               :visible="showDialog"
               width="65%"
               @close="close"
               @open="getData">
        <div class="form-body">
            <div class="row">
                <div v-if="records.length > 0"
                     class="col-md-12">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha de pago</th>
                                <th>Método de pago</th>
                                <th>Destino</th>
                                <th>Referencia</th>
<!--                                <th>Archivo</th>-->
                                <th class="text-right">Monto</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(row, index) in records"
                                :key="index">
                                <template v-if="row.id">
                                    <td>PAGO-{{ row.id }}</td>
                                    <td>{{ row.date_of_payment }}</td>
                                    <td>{{ row.expense_method_type_description }}</td>
                                    <td>{{ row.destination_description }}</td>
                                    <td>{{ row.reference }}</td>
<!--                                    <td class="text-center">
                                        <button v-if="row.filename"
                                                class="btn waves-effect waves-light btn-xs btn-primary"
                                                type="button"
                                                @click.prevent="clickDownloadFile(row.filename)">
                                            <i class="fas fa-file-download"></i>
                                        </button>
                                    </td>-->
                                    <td class="text-right">{{ row.payment }}</td>
                                    <td class="series-table-actions text-right">
                                        <button class="btn waves-effect waves-light btn-xs btn-danger"
                                                type="button"
                                                @click.prevent="clickDelete(row.id)">Eliminar
                                        </button>
                                        <!--<el-button type="danger" icon="el-icon-delete" plain @click.prevent="clickDelete(row.id)"></el-button>-->
                                    </td>
                                </template>
                                <template v-else>
                                    <td></td>
                                    <td>
                                        <div :class="{'has-danger': row.errors.date_of_payment}"
                                             class="form-group mb-0">
                                            <el-date-picker v-model="row.date_of_payment"
                                                            :clearable="false"
                                                            format="dd/MM/yyyy"
                                                            type="date"
                                                            value-format="yyyy-MM-dd"></el-date-picker>
                                            <small v-if="row.errors.date_of_payment"
                                                   class="form-control-feedback"
                                                   v-text="row.errors.date_of_payment[0]"></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div :class="{'has-danger': row.errors.payment_method_type_id}"
                                             class="form-group mb-0">
                                            <el-select v-model="row.payment_method_type_id"
                                                       @change="changeExpenseMethodType(index)">
                                                <el-option v-for="option in payment_method_types"
                                                           :key="option.id"
                                                           :label="option.description"
                                                           :value="option.id"></el-option>
                                            </el-select>
                                            <small v-if="!row.payment_method_type_id" class="text-muted">No hay un método bancario activo; seleccione uno cuando esté disponible.</small>
                                            <small v-if="row.errors.payment_method_type_id"
                                                   class="form-control-feedback"
                                                   v-text="row.errors.payment_method_type_id[0]"></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div :class="{'has-danger': row.errors.payment_destination_id}"
                                             class="form-group mb-0">
                                            <el-select v-model="row.payment_destination_id"
                                                       :disabled="classifiedPaymentDestinationDisabled(payment_destinations, row)"
                                                       filterable>
                                                <el-option v-for="option in classifiedPaymentDestinations(payment_destinations, row)"
                                                           :key="option.id"
                                                           :label="option.description"
                                                           :value="option.id"></el-option>
                                            </el-select>
                                            <small v-if="row.errors.payment_destination_id"
                                                   class="form-control-feedback"
                                                   v-text="row.errors.payment_destination_id[0]"></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div :class="{'has-danger': row.errors.reference}"
                                             class="form-group mb-0">
                                            <el-input v-model="row.reference"></el-input>
                                            <small v-if="row.errors.reference"
                                                   class="form-control-feedback"
                                                   v-text="row.errors.reference[0]"></small>
                                        </div>
                                    </td>
                                    <!--
                                    <td>
                                        <div class="form-group mb-0">

                                            <el-upload
                                                :action="`/finances/payment-file/upload`"
                                                :data="{'index': index}"
                                                :file-list="fileList"
                                                :headers="headers"
                                                :limit="1"
                                                :multiple="false"
                                                :on-remove="handleRemove"
                                                :on-success="onSuccess"
                                                :show-file-list="true"
                                            >
                                                <el-button slot="trigger"
                                                           type="primary">Seleccione un archivo
                                                </el-button>
                                            </el-upload>
                                        </div>
                                    </td>
                                    -->
                                    <td>
                                        <div :class="{'has-danger': row.errors.payment}"
                                             class="form-group mb-0">
                                            <el-input v-model="row.payment"></el-input>
                                            <small v-if="row.errors.payment"
                                                   class="form-control-feedback"
                                                   v-text="row.errors.payment[0]"></small>
                                        </div>
                                    </td>
                                    <td class="series-table-actions text-right">
                                        <button class="btn waves-effect waves-light btn-xs btn-info"
                                                type="button"
                                                @click.prevent="clickSubmit(index)">
                                            <i class="fa fa-check"></i>
                                        </button>
                                        <button class="btn waves-effect waves-light btn-xs btn-danger"
                                                type="button"
                                                @click.prevent="clickCancel(index)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </template>
                            </tr>
                            </tbody>
                            <tfoot>
                            <tr>
                                <td class="text-right"
                                    colspan="6">TOTAL PAGADO
                                </td>
                                <td class="text-right">{{ expense.total_paid }}</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td class="text-right"
                                    colspan="6">TOTAL A PAGAR
                                </td>
                                <td class="text-right">{{ expense.total }}</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td class="text-right"
                                    colspan="6">PENDIENTE DE PAGO
                                </td>
                                <td class="text-right">{{ expense.total_difference }}</td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div v-if="showAddButton && (expense.total_difference > 0)"
                     class="col-md-12 text-center pt-2">
                    <el-button icon="el-icon-plus"
                               type="primary"
                               @click="clickAddRow">
                        Nuevo
                    </el-button>
                </div>
            </div>
        </div>
    </el-dialog>

</template>

<script>

import {deletable} from '@mixins/deletable'
import {mapActions, mapState} from "vuex";

export default {
    paymentCurrencyPolicy: 'document',
    props: [
        'showDialog',
        'expenseId'
    ],
    mixins: [
        deletable
    ],

    computed: {
        ...mapState([
            'config',
            'establishment',
            'payment_destinations',
            'payment_method_types'
        ])
    },
    data() {
        return {
            title: null,
            resource: 'loan-payments',
            records: [],
            // expense_method_types: [],
            // payment_destinations: [],
            headers: headers_token,
            index_file: null,
            fileList: [],
            showAddButton: true,
            expense: {}
        }
    },
    async created() {
        await this.initForm();
    },
    methods: {
        async refreshLoanCatalog() {
        await this.$http.get(`/${this.resource}/tables`)
            .then(response => {
                this.$store.commit('setPaymentMethodTypes',response.data.payment_method_types)
                // this.expense_method_types = response.data.expense_method_types
                this.$store.commit('setPaymentDestinations',response.data.payment_destinations);

                // this.payment_destinations = response.data.payment_destinations;
            })
        },
        changeExpenseMethodType(index) {
            const row = this.records[index];
            const method = this.payment_method_types.find(item => String(item.id) === String(row.payment_method_type_id) && [true, 1, '1'].includes(item.is_active) && item.collection_kind === 'account');
            const available = method ? this.classifiedPaymentDestinations(this.payment_destinations, row) : [];
            if (available.some(item => String(item.id) === String(row.payment_destination_id))) return;
            const preferred = available.find(item => String(item.id) === String(this.expense?.payment_destination_id));
            row.payment_destination_id = preferred?.id ?? null;
        },
        clickDownloadFile(filename) {
            window.open(
                `/finances/payment-file/download-file/${filename}/expenses`,
                "_blank"
            );
        },
        onSuccess(response, file, fileList) {

            // console.log(response, file, fileList)
            this.fileList = fileList

            if (response.success) {

                this.index_file = response.data.index
                this.records[this.index_file].filename = response.data.filename
                this.records[this.index_file].temp_path = response.data.temp_path

            } else {
                this.cleanFileList()
                this.$message.error(response.message)
            }

            // console.log(this.records)

        },
        cleanFileList() {
            this.fileList = []
        },
        handleRemove(file, fileList) {

            this.records[this.index_file].filename = null
            this.records[this.index_file].temp_path = null
            this.fileList = []
            this.index_file = null

        },
        initForm() {
            this.records = [];
            this.fileList = [];
            this.showAddButton = true;
        },
        async getData() {
            this.initForm();
            await this.refreshLoanCatalog();
            await this.$http.get(`/${this.resource}/expense/${this.expenseId}`)
                .then(response => {
                    this.expense = response.data;
                    this.title = 'Pagos del prestamo: ' + this.expense.number_full;
                });
            await this.$http.get(`/${this.resource}/records/${this.expenseId}`)
                .then(response => {
                    this.records = response.data.data
                });
            this.$eventHub.$emit('reloadDataToPay')

        },
        clickAddRow() {
            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            const method = this.payment_method_types.filter(item => [true, 1, '1'].includes(item.is_active) && item.collection_kind === 'account')
                .sort((a, b) => String(a.id).localeCompare(String(b.id)))[0] || null;
            const destination = this.payment_destinations.find(item => item.id !== 'cash' &&
                String(item.id) === String(this.expense?.payment_destination_id) && item.currency_type_id === this.expense?.currency_type_id);
            const payment_method_type_id = method?.id || null;
            const payment_destination_id = method && destination ? destination.id : null;
            const payment_destination_disabled = false;
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
            this.records.push({
                id: null,
                date_of_payment: moment().format('YYYY-MM-DD'),
                payment_method_type_id: payment_method_type_id,
                payment_destination_id: payment_destination_id,
                payment_destination_disabled: payment_destination_disabled,
                reference: null,
                filename: null,
                temp_path: null,
                payment: 0,
                errors: {},
                loading: false
            });
            this.showAddButton = false;

        },
        clickCancel(index) {
            this.records.splice(index, 1);
            this.showAddButton = true;
            this.fileList = []
        },
        clickSubmit(index) {

            if (this.records[index].payment > parseFloat(this.expense.total_difference)) {
                this.$message.error('El monto ingresado supera al monto pendiente de pago, verifique.');
                return;
            }

            let form = {
                id: this.records[index].id,
                bank_loan_id: this.expenseId,
                date_of_payment: this.records[index].date_of_payment,
                payment_method_type_id: this.records[index].payment_method_type_id,
                payment_destination_id: this.records[index].payment_destination_id,
                reference: this.records[index].reference,
                filename: this.records[index].filename,
                temp_path: this.records[index].temp_path,
                payment: this.records[index].payment,
            }

            this.$http.post(`/${this.resource}`, form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.getData();
                        // this.initexpenseTypes()
                        this.$eventHub.$emit('reloadData')
                        this.showAddButton = true;
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        const data = error.response.data || {};
                        this.records[index].errors = data.errors || (data.message && typeof data.message === 'object' ? data.message : data);
                    } else {
                        this.$message.error(error.response.data.message)
                        console.log(error);
                    }
                })
        },
        close() {
            this.$emit('update:showDialog', false);
        },
        clickDelete(id) {
            this.destroy(`/${this.resource}/${id}`).then(() => {
                    this.getData()
                    this.$eventHub.$emit('reloadData')
                }
            )
        }
    }
}
</script>
