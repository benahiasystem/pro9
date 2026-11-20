<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
    <el-dialog
        :show-close="false"
        :title="titleDialog"
        :visible="showDialogTransfer"
        @open="create">
        <div v-loading="!fullLoad">

            <div
                class="col-12 row">

                <div class="col-6">
                    <label class="control-label">
                        Origen
                    </label>
                    <el-select v-model="transfer_amount.from"
                               :disabled="!fullLoad"
                               filterable
                               @change="setMaxAmount"
                    >
                        <!-- Bancos -->
                        <el-option v-for="option in tempFrom"
                                   :key="option.id"
                                   :disabled="alreadyOnTo(option.id)"
                                   :label="option.description"
                                   :value="option.id">

                        </el-option>


                    </el-select>
                </div>
                <div class="col-6">
                    <label class="control-label">
                        Destino
                    </label>
                    <el-select v-model="transfer_amount.to"
                               :disabled="!fullLoad"
                               filterable
                               @change="convertTotal"
                    >
                        <el-option v-for="option in tempTo"
                                   :key="option.id"
                                   :disabled="alreadyOnFrom(option.id)"
                                   :label="option.description"
                                   :value="option.id">

                        </el-option>

                    </el-select>
                </div>

                <div class="col-6">
                    <label class="control-label">
                        Cantidad
                        <el-tooltip
                            class="item"
                            content="Ingresa el monto a transferir"
                            effect="dark"
                            placement="top-start">
                            <i class="fa fa-info-circle"></i>
                        </el-tooltip>
                    </label>

                    <el-input
                        v-model="transfer_amount.amount_transform_temp"
                        :disabled="!fullLoad"
                        inputmode="decimal"
                        @input="convertTotal"
                    />
                    <small v-if="amountError" class="text-danger">{{ amountError }}</small>
                </div>
                <div class="col-6">
                    <label class="control-label">
                        Monto a transferir
                        <el-tooltip
                            class="item"
                            content="Si la moneda de la cuenta bancaria difiere de bolívares, se realiza la conversion."
                            effect="dark"
                            placement="top-start">
                            <i class="fa fa-info-circle"></i>
                        </el-tooltip>
                    </label>

                    <el-input v-model="transfer_amount.amount_transform" :disabled="true" />
                </div>
                <div class="col-12">
                    &nbsp;
                </div>
                <div class="col-4">
                    &nbsp;
                </div>
                <div class="col-4">
                    <el-button
                        class="submit"
                        style="width: 100%;"
                        @click.prevent="clickClose">
                        Cerrar
                    </el-button>
                </div>
                <div class="col-4">
                    <el-button
                        :disabled="!fullLoad || !canTransfer()"
                        :loading="loading"
                        class="submit "
                        style="width: 100%;"
                        type="primary"
                        @click.prevent="transferAmount">
                        <i class="fa fa-cash-register"></i>
                        Transferir
                    </el-button>
                </div>


            </div>
        </div>
    </el-dialog>
</template>

<script>
// ######## INICIO TASAS OCHO DECIMALES ########
import { exactAmount, exactInput, exactValue, normalizeExchangeRate } from "../../../../../../../resources/js/helpers/exchange-rate-math"
// ######## FIN TASAS OCHO DECIMALES ########

import {mapActions, mapState} from "vuex/dist/vuex.mjs";

export default {
    props: [
        'showDialogTransfer',
    ],
    computed: {
        ...mapState([
            'config',
            'exchange_rate_sale',
        ]),
    },
    data() {
        return {
            titleDialog: "Transferencia entre cuentas",
            loading: false,
            resource: 'finances/balance',
            maxTransfer: '99999',
            amountError: null,
            tempFrom: [],
            tempTo: [],
            banks: [],
            cashs: [],
            fullLoad: false,
            bankLoad: false,
            cashLoad: false,
            transfer_amount: {
                amount_transform_temp: '0',
                amount_transform: '0',
                from: null,
                to: null,
            },
        }
    },
    created() {
        this.loadConfiguration();
        this.clearForm()
    },
    methods: {

        ...mapActions([
            'loadConfiguration',
        ]),
        alreadyOnTo(id) {
            return id == this.transfer_amount.to;
        },
        alreadyOnFrom(id) {
            return id == this.transfer_amount.from;
        },
        setMaxAmount() {
            const bank = this.banks.find(item => item.id === this.transfer_amount.from);
            try {
                const max = bank ? exactValue(bank, 'balance') : exactAmount('9999');
                this.maxTransfer = max.compare(0) < 0 ? exactAmount('0') : max;
            } catch (error) {
                this.maxTransfer = '0';
            }
            this.convertTotal();
        },
        clearForm() {
            this.transfer_amount.amount_transform = '0';
            this.transfer_amount.amount_transform_temp = '0';
            this.maxTransfer = '99999';
            this.amountError = null;

            this.transfer_amount.from = null;
            this.transfer_amount.to = null;

            this.tempFrom = [];
            this.tempTo = [];
        },

        create() {
            this.tempFrom = [];
            this.tempTo = [];
            this.getBankAccounts();
            this.getCashAccounts();
        },
        getBankAccounts() {

            this.bankLoad = false;
            this.fullLoad = false;
            this.$http.post(`/${this.resource}/bank_accounts`, {}).then((resource) => {
                let data = resource.data;
                this.banks = data.banks

                for (var j = 0; j < data.banks.length; j++) {
                    this.tempFrom.push(data.banks[j]);
                    this.tempTo.push(data.banks[j]);
                }
            }).finally(() => {
                this.bankLoad = true;
                if (this.bankLoad && this.cashLoad) {
                    this.fullLoad = true;
                }
            })

        },

        getCashAccounts() {
            this.cashLoad = false;
            this.fullLoad = false;

            this.$http.post(`/${this.resource}/cash`, {}).then((resource) => {
                let data = resource.data;
                this.cashs = data.cash

                for (var j = 0; j < data.cash.length; j++) {
                    this.tempFrom.push(data.cash[j]);
                    this.tempTo.push(data.cash[j]);
                }
            }).finally(() => {
                this.cashLoad = true;
                if (this.bankLoad && this.cashLoad) {
                    this.fullLoad = true;
                }
            })
        },
        validatedTransfer() {
            const origin = exactInput(this.transfer_amount.amount_transform_temp);
            const destination = exactInput(this.transfer_amount.amount_transform);
            if (!this.transfer_amount.from || !this.transfer_amount.to || this.transfer_amount.from === this.transfer_amount.to) {
                throw new Error('Seleccione cuentas de origen y destino diferentes.');
            }
            if (exactAmount(origin).compare(0) <= 0 || exactAmount(destination).compare(0) <= 0) {
                throw new Error('El importe del traslado debe ser positivo.');
            }
            if (exactAmount(origin).compare(exactAmount(this.maxTransfer)) > 0) {
                throw new Error('El importe supera el saldo disponible para el traslado.');
            }
            return {...this.transfer_amount, amount_transform_temp: origin, amount_transform: destination,
                cash_currency_type_id: this.config.currency_type_id};
        },
        canTransfer() {
            try { this.validatedTransfer(); return true; }
            catch (error) { return false; }
        },
        transferAmount() {
            let data;
            try { data = this.validatedTransfer(); }
            catch (error) { this.$message.error(error.message); return; }
            this.loading = true;
            // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
            return this.$http.post(`/${this.resource}/transfer`, {data}).then((resource) => {
            // ######## FIN MONEDA DEL MEDIO DE COBRO ########
                let data = resource.data;
                this.cashs = data.cash
            }).then(() => {
                this.$emit('reloadAccounts', true)
                this.clickClose()
            }).catch((error) => {
                this.$message.error(error.response?.data?.errors?.cash_currency_type_id?.[0]
                    || error.response?.data?.errors?.currency_type_id?.[0]
                    || error.response?.data?.errors?.['data.amount_transform_temp']?.[0]
                    || error.response?.data?.errors?.['data.amount_transform']?.[0]
                    || error.response?.data?.message || 'No se pudo registrar el traslado.');
            }).finally(() => { this.loading = false; })

        },
        clickFinalize() {
            location.href = `/${this.resource}`
        },
        clickNewDocument() {
            this.clickClose()
        },
        clickClose() {
            this.$emit('update:showDialogTransfer', false)
            this.clearForm()
        },
        convertTotal() {
            this.amountError = null;
            const fromAccount = this.tempFrom.find(item => item.id === this.transfer_amount.from);
            const toAccount = this.tempTo.find(item => item.id === this.transfer_amount.to);
            this.transfer_amount.amount_transform = '0';
            if (!fromAccount || !toAccount || this.transfer_amount.amount_transform_temp === '') return;
            try {
                const coinIn = fromAccount.currency_type_id || this.config.currency_type_id;
                const coinOut = toAccount.currency_type_id || this.config.currency_type_id;
                const source = exactInput(this.transfer_amount.amount_transform_temp);
                let total = source;
                if (coinIn !== coinOut) {
                    const rate = normalizeExchangeRate(this.exchange_rate_sale);
                    total = coinIn === 'VES'
                        ? exactAmount(source).dividedBy(rate).final(2)
                        : exactAmount(source).times(rate).final(2);
                }
                this.transfer_amount.amount_transform = exactInput(total);
                if (exactAmount(source).compare(this.maxTransfer) > 0) {
                    this.amountError = 'El importe supera el saldo disponible para el traslado.';
                }
            } catch (error) {
                this.amountError = error.message;
            }
        }
    }
}
</script>

<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
