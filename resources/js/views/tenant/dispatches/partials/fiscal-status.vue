<template>
    <div v-if="record.fiscal_emission && record.fiscal_emission.status" :class="{ 'mt-2': !statusOnly }">
        <el-popover v-if="statusOnly" placement="top" trigger="hover">
            <div>N° de control: {{ record.fiscal_emission.control_number || 'Sin asignar' }}</div>
            <div>Estado: {{ record.fiscal_emission.description }}</div>
            <span slot="reference" class="badge" :class="record.fiscal_emission.status === 'confirmed' ? 'bg-success' : 'bg-secondary'" tabindex="0">{{ record.fiscal_emission.description }}</span>
        </el-popover>
        <el-tooltip v-else :content="record.fiscal_emission.diagnostic || record.fiscal_emission.description" placement="top">
            <span class="badge" :class="record.fiscal_emission.status === 'confirmed' ? 'bg-success' : 'bg-secondary'">HKA · {{ record.fiscal_emission.description }}</span>
        </el-tooltip>
        <div v-if="!statusOnly" class="mt-1">
            <el-button v-if="record.fiscal_emission.can_send" size="mini" :loading="busy" @click="perform('send-hka')">Enviar HKA</el-button>
            <el-button v-if="showActions && record.fiscal_emission.can_query" size="mini" :loading="busy" @click="perform('query-hka')">Consultar HKA</el-button>
            <el-button v-if="showActions && record.fiscal_emission.status === 'confirmed' && record.reference_document_id && isAdmin" size="mini" :loading="busy" @click="perform('relate-hka')">Conciliar factura</el-button>
            <a v-if="showActions && record.pdf_downloads && record.pdf_downloads.hka_a4.available" :href="record.pdf_downloads.hka_a4.url" class="btn btn-xs btn-success">PDF original HKA</a>
        </div>
        <small v-if="!statusOnly && record.fiscal_emission.relations && record.fiscal_emission.relations.description" class="d-block">{{ record.fiscal_emission.relations.description }}</small>
    </div>
</template>
<script>
import performFiscalAction from './fiscal-action';
export default {
    props: {
        record: {type: Object, required: true},
        showActions: {type: Boolean, default: true},
        statusOnly: {type: Boolean, default: false}
    },
    computed: {
        busy() {return Boolean(this.record.hka_action_busy);},
        isAdmin() {return this.$store.state.config.typeUser === 'admin';}
    },
    methods: {
        async perform(action) {
            const current = await performFiscalAction.call(this, this.record, action);
            if (current) this.$emit('updated', current);
        }
    }
};
</script>
