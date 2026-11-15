<template>
    <!-- ######## INICIO REPARTO DE VUELTO ######## -->
    <el-popover v-model="visible" placement="bottom-end" trigger="click" width="420" @show="open">
        <div class="text-start" data-testid="change-disbursement-selector">
            <strong>Métodos para dar vuelto</strong>
            <p v-if="!eligible.length" class="text-muted mt-2 mb-2">No hay métodos disponibles para vueltos.</p>
            <div v-for="(line, index) in draft" :key="index" class="mt-2">
                <div class="d-flex align-items-center" style="gap: 6px">
                    <el-select :value="line.payment_method_type_id" placeholder="Método de pago" @change="suggestAmount(line, $event)">
                        <el-option v-for="method in eligible" :key="method.id" :value="method.id" :label="method.description + ' (' + method.currency_type_id + ')'" />
                    </el-select>
                    <el-input v-model="line.amount" placeholder="Monto" aria-label="Monto del vuelto" @input="markAmountEdited(line)" />
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
// ######## INICIO SUGERENCIA RESTANTE VUELTO ########
import {defaultChangeDisbursements, eligibleChangeMethods, suggestChangeDisbursementAmount, validateChangeDisbursements} from '../../../../helpers/change-disbursements'

export default {
    props: {receipt:{type:Object, required:true}, lines:{type:Array, default:null}, methods:{type:Array, default:()=>[]}, destinations:{type:Array, default:()=>[]}, establishmentId:{default:null}, disabled:Boolean},
    // La identificación de sugerencias queda fuera de los datos enviados al servidor.
    data:()=>({visible:false, draft:[], error:'', suggestedAmounts:new WeakMap()}),
    computed: {eligible() { return eligibleChangeMethods(this.methods, this.destinations, this.establishmentId) }},
    methods: {
        open() {
            this.error=''
            this.suggestedAmounts=new WeakMap()
            if (this.lines) { this.draft=this.lines.map(line => ({...line})); return }
            this.draft=defaultChangeDisbursements(this.receipt, this.methods, this.destinations, this.establishmentId)
                || [{payment_method_type_id:null, amount:'', reference:''}]
            for (const line of this.draft) {
                if (line.amount !== '') this.suggestedAmounts.set(line, line.amount)
            }
        },
        add() {
            try {
                const amount=suggestChangeDisbursementAmount(this.draft, this.receipt, this.eligible)
                const line={payment_method_type_id:null, amount, reference:''}
                this.suggestedAmounts.set(line, amount)
                this.draft.push(line)
                this.error=''
            } catch (error) { this.error=error.message || 'Revise los métodos de vuelto.' }
        },
        isAccount(line) { return this.eligible.find(method => String(method.id) === String(line.payment_method_type_id))?.collection_kind === 'account' },
        markAmountEdited(line) { this.suggestedAmounts.delete(line) },
        suggestAmount(line, paymentMethodTypeId = line.payment_method_type_id) {
            if (line.amount !== '' && this.suggestedAmounts.get(line) !== line.amount) {
                line.payment_method_type_id=paymentMethodTypeId
                return
            }
            try {
                const others=this.draft.filter(other => other !== line)
                const selected={...line, payment_method_type_id:paymentMethodTypeId}
                const amount=suggestChangeDisbursementAmount(others, this.receipt, this.eligible, selected)
                line.payment_method_type_id=paymentMethodTypeId
                line.amount=amount
                this.suggestedAmounts.set(line, amount)
                this.error=''
            } catch (error) { this.error=error.message || 'Revise los métodos de vuelto.' }
        },
        apply() {
            const result=validateChangeDisbursements(this.draft, this.receipt, this.methods, this.destinations, this.establishmentId)
            if (!result.valid) { this.error=result.message; return }
            this.$emit('apply', this.draft.map(line => ({...line}))); this.visible=false
        },
        clear() { this.$emit('clear'); this.visible=false },
    },
}
// ######## FIN SUGERENCIA RESTANTE VUELTO ########
</script>
