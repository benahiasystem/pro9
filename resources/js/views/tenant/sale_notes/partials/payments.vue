<template>
    <el-dialog :title="title" :visible="showDialog" @close="close" @open="getData" width="65%" :close-on-click-modal="false" :close-on-press-escape="false">
        <div class="form-body">
            <div class="row">
                <div class="col-md-12" v-if="records.length > 0">
                    <div class="table-responsive table-sm">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha de pago</th>
                                <th>Método de pago</th>
                                <th>Destino</th>
                                <th>Referencia</th>
                                <th>Archivo</th>
                                <th class="text-right">Monto</th>
                                <template v-if="external">
                                    <th>Imprimir</th>
                                </template>
                                
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(row, index) in records" :key="index">
                                <template v-if="row.id">
                                    <td>PAGO-{{ row.id }}</td>
                                    <td>{{ row.date_of_payment }}</td>
                                    <td>{{ row.payment_method_type_description }}</td>
                                    <td>{{ row.destination_description }}</td>
                                    <td>{{ row.reference }}</td>
                                    <td class="text-center">
                                        <button  type="button" v-if="row.filename" class="btn waves-effect waves-light btn-xs btn-primary" @click.prevent="clickDownloadFile(row.filename)">
                                            <i class="fas fa-file-download"></i>
                                        </button>
                                    </td>
                                    <!-- ######## INICIO CALCULOS EXACTOS PRO9 ######## -->
                                    <td class="text-right">
                                        {{ row.payment }}
                                        <small v-if="row.currency_type_id && row.tendered_original_amount !== null && row.tendered_original_amount !== undefined" class="d-block">{{ row.tendered_original_amount }} {{ row.currency_type_id }} recibido · tasa {{ row.exchange_rate }}</small>
                                        <small v-else-if="row.currency_type_id" class="d-block">{{ row.original_amount }} {{ row.currency_type_id }} neto · tasa {{ row.exchange_rate }}</small>
                                        <small v-if="hasCashAdjustment(row)" class="d-block text-muted">Ajuste de liquidación: {{ cashAdjustmentText(row) }}</small>
                                        <small v-if="hasChange(row) && row.received_change !== null && row.received_change !== undefined" class="d-block text-muted">Vuelto: {{ row.received_change }} {{ row.currency_type_id }}</small>
                                        <small v-else-if="hasChange(row)" class="d-block text-muted">Vuelto aplicado: {{ row.change }} {{ document.currency_type_id }}</small>
                                    </td>
                                    <!-- ######## FIN CALCULOS EXACTOS PRO9 ######## -->
                                <template v-if="external">
                                    <td class="series-table-actions text-center">
                                        <button type="button" class="btn waves-effect waves-light btn-xs btn-primary" @click.prevent="clickOptions()"><i class="fas fa-file-upload"></i></button>
                                    </td>
                                </template>

                                    <td class="series-table-actions text-right">
                                        <button type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickDelete(row.id)"><i class="fas fa-trash"></i></button>
                                        <!--<el-button type="danger" icon="el-icon-delete" plain @click.prevent="clickDelete(row.id)"></el-button>-->
                                    </td>
                                </template>
                                <template v-else>
                                    <td></td>
                                    <td>
                                        <div class="form-group mb-0" :class="{'has-danger': row.errors.date_of_payment}">
                                            <el-date-picker v-model="row.date_of_payment"
                                                            type="date"
                                                            :clearable="false"
                                                            format="dd/MM/yyyy"
                                                            value-format="yyyy-MM-dd"></el-date-picker>
                                            <small class="form-control-feedback" v-if="row.errors.date_of_payment" v-text="row.errors.date_of_payment[0]"></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-group mb-0" :class="{'has-danger': row.errors.payment_method_type_id}">
                                            <el-select v-model="row.payment_method_type_id">
                                                <el-option v-for="option in payment_method_types" v-show="['cash', 'account'].includes(option.collection_kind)" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                            </el-select>
                                            <receipt-currency-fields :row="row" :methods="payment_method_types" :document-currency="document.currency_type_id" :document-rate="document.exchange_rate_sale" />
                                            <small class="form-control-feedback" v-if="row.errors.payment_method_type_id" v-text="row.errors.payment_method_type_id[0]"></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-group mb-0" :class="{'has-danger': row.errors.payment_destination_id}">
                                            <el-select v-model="row.payment_destination_id" filterable :disabled="classifiedPaymentDestinationDisabled(payment_destinations, row)">
                                                <el-option v-for="option in classifiedPaymentDestinations(payment_destinations, row)" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                            </el-select>
                                            <small class="form-control-feedback" v-if="row.errors.payment_destination_id" v-text="row.errors.payment_destination_id[0]"></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-group mb-0" :class="{'has-danger': row.errors.reference}">
                                            <el-input v-model="row.reference" @focus="$event.target.select()"></el-input>
                                            <small class="form-control-feedback" v-if="row.errors.reference" v-text="row.errors.reference[0]"></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-group mb-0">

                                            <el-upload
                                                    :data="{'index': index}"
                                                    :headers="headers"
                                                    :multiple="false"
                                                    :on-remove="handleRemove"
                                                    :action="`/finances/payment-file/upload`"
                                                    :show-file-list="true"
                                                    :file-list="fileList"
                                                    :on-success="onSuccess"
                                                    :on-error="onUploadError"
                                                    :limit="1"
                                                    >
                                                <el-button slot="trigger" type="primary"><i class="fas fa-file-upload"></i></el-button>
                                            </el-upload>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-group mb-0" :class="{'has-danger': row.errors.payment}">
                                            <el-input v-model="row.payment" @focus="$event.target.select()"></el-input>
                                            <small class="form-control-feedback" v-if="row.errors.payment" v-text="row.errors.payment[0]"></small>
                                        </div>
                                    </td>
                                    <td class="series-table-actions text-right">
                                        <button type="button" class="btn waves-effect waves-light btn-xs btn-info" @click.prevent="clickSubmit(index)" :disabled="row.loading">
                                            <i v-if="!row.loading" class="fa fa-check"></i>
                                            <i v-else class="fa fa-spinner fa-spin"></i>
                                        </button>
                                        <button type="button" class="btn waves-effect waves-light btn-xs btn-danger" :disabled="row.loading" @click.prevent="clickCancel(index)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </template>
                            </tr>
                            </tbody>
                            <tfoot>
                            <tr>
                                <td colspan="6" class="text-right">TOTAL PAGADO</td>
                                <td class="text-right">{{ document.total_paid }}</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="6" class="text-right">TOTAL A PAGAR</td>
                                <td class="text-right">{{ document.total }}</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="6" class="text-right">PENDIENTE DE PAGO</td>
                                <td class="text-right">{{ document.total_difference }}</td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="col-md-12 text-center pt-2" v-if="showAddButton && (document.total_difference > 0)">
                    <el-button :loading="loadingFetch" type="primary" icon="el-icon-plus" @click="clickAddRow">Nuevo</el-button>
                </div>
            </div>
        </div>

        <document-options
            :recordId="this.documentId"
            :showDialogOptions.sync="showDialogOptions"
            :showClose="showDialogClose"
            :type="this.type"
            :configuration="this.configuration"
        ></document-options>

    </el-dialog>

</template>

<style>
.el-upload-list__item-name [class^="el-icon"] {
    display: none;
}
.el-upload-list__item-name {
    margin-right: 25px;
}
.el-upload-list__item {
    font-size: 10px;
}
</style>

<script>
    // ######## INICIO CALCULOS EXACTOS PRO9 ########
    import {exactAmount, exactInput, exactValue, appliedReceiptAmount} from '../../../../helpers/exchange-rate-math'
    import {paymentValidationMessage} from '../../../../helpers/payment-classification'
    // ######## FIN CALCULOS EXACTOS PRO9 ########

    import {deletable} from '../../../../mixins/deletable'
    import DocumentOptions from '../../../../../../modules/Finance/Resources/assets/js/views/unpaid/partials/options.vue'
    import ReceiptCurrencyFields from '@components/ReceiptCurrencyFields.vue'

    export default {
        props: ['showDialog', 'documentId','external','configuration'],
        mixins: [deletable],
        components: {
            DocumentOptions, ReceiptCurrencyFields
        },
        data() {
            return {
                title: null,
                resource: 'sale_note_payments',
                records: [],
                payment_destinations: [],
                payment_method_types: [],
                headers: headers_token,
                index_file: null,
                fileList: [],
                showAddButton: true,
                loadingFetch: false,
                document: {},
                showDialogOptions: false,
                showDialogClose:false,
                type:'sale',
            }
        },
        async created() {
            await this.initForm();
            await this.$http.get(`/${this.resource}/tables`)
                .then(response => {
                    this.payment_destinations = response.data.payment_destinations
                    this.payment_method_types = response.data.payment_method_types;
                    //this.initDocumentTypes()
                })
        },
        methods: {
            // ######## INICIO CALCULOS EXACTOS PRO9 ########
            hasCashAdjustment(row) { return exactValue(row, 'cash_adjustment').compare(0) !== 0; },
            hasChange(row) { return exactValue(row, 'change').compare(0) !== 0; },
            cashAdjustmentText(row) {
                const value = exactValue(row, 'cash_adjustment');
                const text = value.decimal();
                if (text !== '0' || value.compare(0) === 0) return text;
                const fraction = value.fraction();
                return `${fraction.n}/${fraction.d}`;
            },
            // ######## FIN CALCULOS EXACTOS PRO9 ########
            clickDownloadFile(filename) {
                window.open(
                    `/finances/payment-file/download-file/${filename}/sale_notes`,
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
                    this.$message.error(response.message || 'No se pudo cargar el archivo.')
                }

                // console.log(this.records)

            },
            onUploadError(error, file, fileList) {
                this.cleanFileList()
                this.$message.error(this.getRequestErrorMessage(error))
            },
            getRequestErrorMessage(error) {
                const data = error && error.response ? error.response.data : null

                if (typeof data === 'string' && data.trim()) {
                    return data
                }

                if (data && data.message) {
                    return data.message
                }

                if (error && error.message) {
                    return error.message
                }

                return 'Ocurrió un error al procesar la solicitud.'
            },
            cleanFileList(){
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
                this.loadingFetch = true;
                this.initForm();
                await this.$http.get(`/${this.resource}/document/${this.documentId}`)
                    .then(response => {
                        this.document = response.data;
                        this.title = 'Pagos del comprobante: '+this.document.number_full;
                    });
                await this.$http.get(`/${this.resource}/records/${this.documentId}`)
                    .then(response => {
                        this.records = response.data.data
                    });

                // await this.$eventHub.$emit('reloadDataUnpaid')
                this.loadingFetch = false;

            },
            clickAddRow() {
                this.records.push({
                    id: null,
                    date_of_payment: moment().format('YYYY-MM-DD'),
                    payment_method_type_id: null,
                    payment_destination_id:null,
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
                // ######## INICIO CALCULOS EXACTOS PRO9 ########
                const row = this.records[index];
                if (row.loading) return;
                let requested, applied;
                try {
                    row.payment = exactInput(String(row.payment));
                    requested = exactAmount(row.payment);
                    applied = appliedReceiptAmount(row, this.document.currency_type_id);
                    if (requested.compare(0) <= 0 || applied.compare(0) <= 0) throw new Error('Indique un cobro positivo y liquidable en céntimos.');
                    row.payment = applied.final(2);
                } catch (error) { this.$message.error(error.message); return; }
                if(applied.compare(exactValue(this.document, 'total_difference')) > 0) {
                    this.$message.error('El monto ingresado supera al monto pendiente de pago, verifique.');
                    return;
                }

                row.loading = true;

                const paid = applied.compare(exactValue(this.document, 'total_difference')) === 0;
                // ######## FIN CALCULOS EXACTOS PRO9 ########


                let form = {
                    id: this.records[index].id,
                    sale_note_id: this.documentId,
                    date_of_payment: this.records[index].date_of_payment,
                    payment_method_type_id: this.records[index].payment_method_type_id,
                    payment_destination_id: this.records[index].payment_destination_id,
                    reference: this.records[index].reference,
                    filename: this.records[index].filename,
                    temp_path: this.records[index].temp_path,
                    payment: this.records[index].payment,
                    currency_type_id: this.records[index].currency_type_id,
                    original_amount: this.records[index].original_amount,
                    exchange_rate: this.records[index].exchange_rate,
                    paid: paid
                };
                this.$http.post(`/${this.resource}`, form)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message);
                            this.getData();
                            // this.initDocumentTypes()
                            this.$eventHub.$emit('reloadData')
                            this.showAddButton = true;
                        } else {
                            this.$message.error(response.data.message);
                        }
                    })
                    .catch(error => {
                        if (error.response && error.response.status === 422) {
                            const data = error.response.data || {};
                            row.errors = data.errors || (data.message && typeof data.message === 'object' ? data.message : data);
                            const message = paymentValidationMessage(data);
                            if (message) this.$message.error(message);
                        } else {
                            this.$message.error(this.getRequestErrorMessage(error))
                        }
                    })
                    .finally(() => {
                        row.loading = false;
                    })
            },
            // filterDocumentType(row){
            //
            //     if(row.contingency){
            //         this.document_types = _.filter(this.all_document_types, item => (item.id == '01' || item.id =='03'))
            //         row.document_type_id = (this.document_types.length > 0)?this.document_types[0].id:null
            //     }else{
            //         row.document_type_id = null
            //         this.document_types = this.all_document_types
            //     }
            // },
            // initDocumentTypes(){
            //     this.document_types = (this.all_document_types.length > 0) ? this.all_document_types : []
            // },
            close() {
                this.$emit('update:showDialog', false);
                // this.initDocumentTypes()
                // this.initForm()
            },
            clickDelete(id) {
                this.destroy(`/${this.resource}/${id}`).then(() =>{
                        this.getData()
                        this.$eventHub.$emit('reloadData')
                    }
                    // this.initDocumentTypes()
                )
            },
            clickPrint(external_id) {
                 window.open(`/sale-notes/print/${external_id}/a4`, '_blank');
            },
            clickOptions() {
                this.showDialogOptions = true
                this.showDialogClose=true
            },
        }
    }
</script>
