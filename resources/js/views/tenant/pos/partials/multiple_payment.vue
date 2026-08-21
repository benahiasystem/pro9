<template>
    <el-dialog :title="titleDialog"   :visible="showDialog"  @open="create"  :close-on-click-modal="false" :close-on-press-escape="false" :show-close="false">

        <div class="form-body">
            <div class="row" >
                <div class="col-lg-12">

                    <table>
                    <thead>
                        <tr width="100%">
                            <th v-if="draftPayments.length>0">Método de pago</th>
                            <template v-if="enabled_payments">
                                 <th v-if="draftPayments.length>0">Destino</th>
                                <th v-if="draftPayments.length>0">Referencia</th>
                                <th v-if="draftPayments.length>0">Monto</th>
                                <th width="15%"><a href="#" @click.prevent="clickAddPayment()" class="text-center font-weight-bold text-info">[+ Agregar]</a></th>
                            </template>
                           
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in draftPayments" :key="index">
                            <td>
                                <div class="form-group mb-2 mr-2">
                                    <el-select v-model="row.payment_method_type_id" @change="changePaymentMethodType(index)">
                                        <el-option v-for="option in payment_method_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                    </el-select>
                                </div>
                            </td>
                            <template v-if="enabled_payments">
                                <td>
                                    <div class="form-group mb-2 mr-2">
                                        <el-select v-model="row.payment_destination_id" filterable :disabled="row.payment_destination_disabled">
                                            <el-option v-for="option in payment_destinations" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                        </el-select>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-group mb-2 mr-2"  >
                                        <el-input v-model="row.reference"
                                                  @focus="valueInputSelect"
                                                  @click.native="valueInputSelect"></el-input>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-group mb-2 mr-2" >
                                        <el-input v-model="row.payment"
                                                  @focus="valueInputSelect"
                                                  @click.native="valueInputSelect"></el-input>
                                    </div>
                                </td>
                                <td class="series-table-actions text-center">
                                    <button  type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickCancel(index)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                                <br>
                            </template>
                            
                        </tr>
                    </tbody>
                </table>


                </div>

            </div>
        </div>

        <div class="form-actions pt-2 d-flex justify-content-between">
            <el-button @click.prevent="close()">Cerrar</el-button>
            <el-button type="primary" @click.prevent="accept()">Aceptar</el-button>
        </div>
    </el-dialog>
</template>

<script>
    export default {
        props: ['showDialog', 'payments', 'total'],
        data() {
            return {
                titleDialog: 'Pagos',
                loading: false,
                errors: {},
                form: {},
                company: {},
                configuration: {},
                activeName: 'first',
                payment_method_types:[],
                payment_destinations: [],
                cards_brand:[],
                enabled_payments: true,
                draftPayments: [],
                paymentsSnapshot: [],
            }
        },
        async created() {

            await this.$http.get(`/pos/payment_tables`)
                .then(response => {
                    this.payment_method_types = response.data.payment_method_types
                    this.cards_brand = response.data.cards_brand
                    this.payment_destinations = response.data.payment_destinations
                    this.getFormPosLocalStorage()
                })

            this.events()
        },
        methods: {
            clonePayments(list) {
                return JSON.parse(JSON.stringify(Array.isArray(list) ? list : []))
            },
            getFormPosLocalStorage(){

                let form_pos = localStorage.getItem('form_pos');
                form_pos = JSON.parse(form_pos)
                if (form_pos) {

                    if(form_pos.payments.length == 0){

                        this.clickAddPayment(this.total)

                    }else{
                        form_pos.payments[0].payment = this.total
                        this.$eventHub.$emit('localSPayments', (form_pos.payments))
                        this.$emit('add', form_pos.payments);

                    }
                }

            },
            create(){
                this.enabled_payments = true
                this.paymentsSnapshot = this.clonePayments(this.payments)
                this.draftPayments = this.clonePayments(this.payments)

                if (this.draftPayments.length === 0) {
                    this.pushDraftPayment(this.total)
                }
            },
            valueInputSelect(event) {
                const target = event && event.target
                if (!target) return
                const input = (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA')
                    ? target
                    : (target.querySelector && target.querySelector('input'))
                if (!input || typeof input.select !== 'function') return
                this.$nextTick(() => input.select())
            },
            buildPaymentRow(total = 0) {
                return {
                    id: null,
                    document_id: null,
                    sale_note_id: null,
                    date_of_payment:  moment().format('YYYY-MM-DD'),
                    payment_method_type_id: '01',
                    payment_destination_id: 'cash',
                    reference: null,
                    payment: total,
                }
            },
            pushDraftPayment(total = 0) {
                this.draftPayments.push(this.buildPaymentRow(total))
                this.calculatePayments(this.draftPayments)
            },
            clickAddPayment(total = 0) {
                // Modal abierto: solo edita el borrador (se confirma con Aceptar).
                if (this.showDialog) {
                    this.pushDraftPayment(total)
                    return
                }

                // Inicialización (p. ej. localStorage) antes de abrir el modal.
                this.payments.push(this.buildPaymentRow(total))
                this.calculatePayments(this.payments)
                this.$emit('add', this.payments)
            },
            calculatePayments(list = null) {
                const payments = list || (this.showDialog ? this.draftPayments : this.payments)
                let payment_count = payments.length;
                if (payment_count === 0) return;

                let total = parseFloat(this.total) || 0;
                let payment = 0;
                let amount = _.round(total / payment_count, 2);

                _.forEach(payments, row => {
                    payment += amount;
                    if (total - payment < 0) {
                        amount = _.round(total - payment + amount, 2);
                    }
                    this.$set(row, 'payment', amount);
                });
            },
            accept() {
                const payments = this.enabled_payments ? this.clonePayments(this.draftPayments) : []
                this.payments.splice(0, this.payments.length, ...payments)
                this.$emit('add', payments)
                this.$emit('setPaymentMethod', this.enabled_payments ? null : '09')
                this.$emit('update:showDialog', false)
            },
            close() {
                // Descartar cambios del modal: no emitir add.
                const snapshot = this.clonePayments(this.paymentsSnapshot)
                this.payments.splice(0, this.payments.length, ...snapshot)
                this.draftPayments = snapshot
                this.$emit('update:showDialog', false)
            },
            clickCancel(index) {
                this.draftPayments.splice(index, 1);
                this.calculatePayments(this.draftPayments);
            },
            async events() {
                // se elimina porque genera error, registro de pagos duplicados
                // await this.$eventHub.$on("cancelSale", () => {
                //     console.info('multiplepayment');
                //     this.getFormPosLocalStorage()
                // });
            },
            changePaymentMethodType(index){

                let payment_method_type = _.find(this.payment_method_types, {'id':this.draftPayments[index].payment_method_type_id})

                if(payment_method_type.id == '09' || payment_method_type.is_credit){

            
                    this.enabled_payments = false

                }else{

                
                    this.enabled_payments = true

                }

            },
        }
    }
</script>
