<template>
    <div v-if="record.fiscal_emission && record.fiscal_emission.status" class="mt-2">
        <el-tooltip :content="record.fiscal_emission.diagnostic || record.fiscal_emission.description" placement="top">
            <span class="badge" :class="record.fiscal_emission.status === 'confirmed' ? 'bg-success' : 'bg-secondary'">HKA · {{ record.fiscal_emission.description }}</span>
        </el-tooltip>
        <div class="mt-1">
            <el-button v-if="record.fiscal_emission.can_send" size="mini" :loading="busy" @click="perform('send-hka')">Enviar HKA</el-button>
            <el-button v-if="record.fiscal_emission.can_query" size="mini" :loading="busy" @click="perform('query-hka')">Consultar HKA</el-button>
            <el-button v-if="record.fiscal_emission.status === 'confirmed' && record.reference_document_id && isAdmin" size="mini" :loading="busy" @click="perform('relate-hka')">Conciliar factura</el-button>
            <a v-if="record.pdf_downloads && record.pdf_downloads.hka_a4.available" :href="record.pdf_downloads.hka_a4.url" class="btn btn-xs btn-success">PDF original HKA</a>
        </div>
        <small v-if="record.fiscal_emission.relations && record.fiscal_emission.relations.description" class="d-block">{{ record.fiscal_emission.relations.description }}</small>
    </div>
</template>
<script>
export default {
    props: ['record'],
    data() { return {busy:false}; },
    computed: {isAdmin() {return this.$store.state.config.typeUser === 'admin';}},
    methods: {
        async perform(action) {
            if (this.busy || !['send-hka','query-hka','relate-hka'].includes(action)) return;
            this.busy=true;
            try {
                await this.$http.post(`/dispatches/${this.record.id}/${action}`);
                const response=await this.$http.get(`/dispatches/record/${this.record.id}`);
                const current=response.data.data || response.data;
                this.$set(this.record,'fiscal_emission',current.fiscal_emission);
                this.$set(this.record,'pdf_downloads',current.pdf_downloads);
                this.$message.info(current.fiscal_emission.diagnostic || current.fiscal_emission.description);
                this.$emit('updated',current);
            } catch (error) {
                this.$message.error(error.response?.data?.message || 'No se pudo completar la operación HKA. La orden sigue guardada.');
            } finally { this.busy=false; }
        }
    }
};
</script>
