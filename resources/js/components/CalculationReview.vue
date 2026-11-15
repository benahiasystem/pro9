<template>
    <div v-if="summary || loading || error" class="alert alert-warning calculation-review" role="alert" aria-live="polite">
        <template v-if="!summary">
            <span>{{ error || 'Cargando el contexto fiscal…' }}</span>
            <button v-if="retryable && error" type="button" class="btn btn-sm btn-default ms-2" @click="$emit('retry')">Reintentar</button>
        </template>
        <template v-else>
        <strong>Valores verificados por el servidor</strong>
        <p>Revise estos valores actualizados y pulse Generar nuevamente para confirmarlos.</p>
        <div>Moneda: {{ summary.currency_type_id }} · Tasa: {{ summary.exchange_rate_sale }}</div>
        <table class="table table-sm">
            <tbody>
                <tr v-for="(label,key) in labels" :key="key"><td>{{ label }}</td><td class="text-end">{{ money(summary.totals[key]) }}</td></tr>
                <tr><td><strong>Total a pagar</strong></td><td class="text-end"><strong>{{ money(summary.total_with_tax) }}</strong></td></tr>
                <tr><td>Pendiente</td><td class="text-end">{{ money(summary.balance) }}</td></tr>
            </tbody>
        </table>
        <details open><summary>Productos, cobros y cuotas verificados</summary>
            <div v-for="(row,index) in summary.items" :key="'item'+index">Producto {{ index + 1 }}: {{ decimal(row.quantity) }} × {{ money(row.unit_price) }} · IVA {{ money(row.total_igv) }} · Total {{ money(row.total) }}</div>
            <div v-for="(row,index) in summary.payments" :key="'payment'+index">Pago {{ index + 1 }}: {{ methodLabel(row) }} · {{ destinationLabel(row) }} · {{ row.original_amount }} {{ row.currency_type_id }} · IGTF {{ row.tax_amount }} · Vuelto {{ row.change }}
                <span v-for="(line,j) in row.change_disbursements" :key="j"> · Salida {{ methodLabel(line) }} / {{ destinationLabel(line) }}: {{ line.amount }} {{ line.currency_type_id }}</span>
            </div>
            <div v-for="(row,index) in summary.fee" :key="'fee'+index">Cuota {{ index + 1 }}: {{ row.date }} · {{ money(row.amount) }}</div>
        </details>
        </template>
    </div>
</template>
<script>
// ######## INICIO REVISION DE CALCULOS ########
import {exactAmount} from '../helpers/exchange-rate-math'
export default {
    props:{summary:{type:Object,default:null},context:{type:Object,default:null},loading:Boolean,error:{type:String,default:''},retryable:Boolean},
    data:()=>({labels:{total_taxed:'Operación gravada',total_exonerated:'Operación exonerada',total_igv:'IVA',total_discount:'Descuentos',total_charge:'Cargos',total:'Total de la venta'}}),
    methods:{
        decimal(value) { return exactAmount(value).decimal() },
        money(value) { return `${this.summary.currency_type_id === 'VES' ? 'Bs.' : '$'} ${exactAmount(value).final(2)}` },
        methodLabel(row) { return this.context?.methods?.find(method=>String(method.id) === row.payment_method_type_id)?.description || row.payment_method_type_id },
        destinationLabel(row) {
            if (row.reused) return 'Cobro ya registrado'
            const destination=this.context?.destinations?.find(value=>String(value.id) === row.payment_destination_id)
            return row.cash_id ? `${destination?.description || 'Caja General'} · turno ${row.cash_id}` : destination?.description || row.payment_destination_id
        },
    },
}
// ######## FIN REVISION DE CALCULOS ########
</script>
<style scoped>
.calculation-review { text-align: left; overflow-wrap: anywhere; }
.calculation-review table { width: 100%; }
</style>
