<template>
    <!-- ######## INICIO REPARTO DE VUELTO ######## -->
    <el-popover v-model="visible" placement="bottom-end" trigger="click" width="420" @show="open">
        <div class="text-start" data-testid="change-disbursement-selector">
            <strong>Métodos para dar vuelto</strong>
            <p v-if="!eligible.length" class="text-muted mt-2 mb-2">No hay métodos disponibles para vueltos.</p>
            <div v-for="(line, index) in draft" :key="index" class="mt-2">
                <div class="d-flex align-items-center" style="gap: 6px">
                    <el-select v-model="line.payment_method_type_id" placeholder="Método de pago" @change="suggestAmount(line)">
                        <el-option v-for="method in eligible" :key="method.id" :value="method.id" :label="method.description + ' (' + method.currency_type_id + ')'" />
                    </el-select>
                    <el-input v-model="line.amount" placeholder="Monto" aria-label="Monto del vuelto" />
                    <button type="button" class="btn btn-sm btn-danger" aria-label="Quitar método de vuelto" @click="draft.splice(index, 1)">×</button>
                </div>
                <el-input v-if="isAccount(line)" v-model="line.reference" class="mt-1" placeholder="Referencia (opcional)" />
            </div>
            <button type="button" class="btn btn-link btn-sm px-0 mt-1" :disabled="!eligible.length || draft.length >= 20" @click="add">+ Agregar método</button>
            <small v-if="error" class="text-danger d-block" role="alert">{{ error }}</small>
            <div class="text-end mt-2">
                <el-button size="mini" @click="clear">Quitar selección</el-button>
                <el-button size="mini" type="primary" @click="apply">Aplicar</el-button>
            </div>
        </div>
        <button slot="reference" type="button" class="btn btn-link btn-sm p-0 ms-1" aria-label="Seleccionar métodos para dar vuelto" :disabled="disabled"><i class="el-icon-more"></i></button>
    </el-popover>
    <!-- ######## FIN REPARTO DE VUELTO ######## -->
</template>
<script>
import {exactAmount, normalizeExchangeRate} from '../../../../helpers/exchange-rate-math'
import {defaultChangeDisbursements, eligibleChangeMethods, validateChangeDisbursements} from '../../../../helpers/change-disbursements'

export default {
    props: {receipt:{type:Object, required:true}, lines:{type:Array, default:null}, methods:{type:Array, default:()=>[]}, destinations:{type:Array, default:()=>[]}, establishmentId:{default:null}, disabled:Boolean},
    data:()=>({visible:false, draft:[], error:''}),
    computed: {eligible() { return eligibleChangeMethods(this.methods, this.destinations, this.establishmentId) }},
    methods: {
        open() {
            this.error=''
            if (this.lines) { this.draft=this.lines.map(line => ({...line})); return }
            this.draft=defaultChangeDisbursements(this.receipt, this.methods, this.destinations, this.establishmentId)
                || [{payment_method_type_id:null, amount:'', reference:''}]
        },
        add() { this.draft.push({payment_method_type_id:null, amount:'', reference:''}) },
        isAccount(line) { return this.eligible.find(method => String(method.id) === String(line.payment_method_type_id))?.collection_kind === 'account' },
        suggestAmount(line) {
            if (this.draft.length !== 1 || line.amount !== '') return
            try {
                const method=this.eligible.find(item => String(item.id) === String(line.payment_method_type_id))
                const amount=exactAmount(this.receipt.change), rate=normalizeExchangeRate(this.receipt.exchange_rate)
                line.amount=(method.currency_type_id === this.receipt.currency_type_id ? amount : method.currency_type_id === 'USD' ? amount.dividedBy(rate) : amount.times(rate)).final(2)
            } catch (_) { /* La tasa o el método aún no están listos. */ }
        },
        apply() {
            const result=validateChangeDisbursements(this.draft, this.receipt, this.methods, this.destinations, this.establishmentId)
            if (!result.valid) { this.error=result.message; return }
            this.$emit('apply', this.draft.map(line => ({...line}))); this.visible=false
        },
        clear() { this.$emit('clear'); this.visible=false },
    },
}
</script>
