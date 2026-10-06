<template>
    <div class="mt-2">
        <label>Clasificación IGTF</label>
        <el-select v-model="payment.igtf_status" placeholder="No aplica">
            <el-option value="not_applicable" label="No aplica" />
            <el-option value="subject" label="Sujeto" />
            <el-option value="exempt" label="Exento" />
        </el-select>
        <el-input v-if="payment.igtf_status === 'exempt'" v-model="payment.exemption_reason" placeholder="Motivo de exención" />
        <small v-if="payment.igtf_status === 'subject'">El IGTF se cobra adicional al importe neto aplicado.</small>
    </div>
</template>
<script>
export default {
    props: {payment: {type: Object, required: true}},
    created() {
        if (!this.payment.igtf_status) this.$set(this.payment, 'igtf_status', 'not_applicable')
        if (!Object.prototype.hasOwnProperty.call(this.payment, 'exemption_reason')) this.$set(this.payment, 'exemption_reason', null)
    }
}
</script>
