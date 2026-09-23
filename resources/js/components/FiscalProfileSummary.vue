<template>
    <!-- ######## INICIO NUMERACIÓN FISCAL VENEZUELA ######## -->
    <div aria-label="Numeración y emisión fiscal">
        <template v-if="profile">
            <strong>{{ profile.name }}</strong>
            <small class="d-block">{{ modeLabel }} · Serie: {{ profile.series_code || 'Sin serie' }}</small>
            <small class="d-block">Próximo estimado: {{ profile.next_number }}. Se asigna al guardar.</small>
        </template>
        <small v-else class="text-danger">Configure un perfil fiscal para esta sucursal y punto de emisión.</small>
    </div>
    <!-- ######## FIN NUMERACIÓN FISCAL VENEZUELA ######## -->
</template>
<script>
// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
export default {
    props: { profile: { type: Object, default: null } },
    computed: {
        modeLabel() {
            const label = { free_form: 'Forma libre', digital: 'Medios digitales', fiscal_machine: 'Máquina fiscal' }[this.profile.mode] || this.profile.mode;
            return this.profile.mode === 'free_form' ? label : `${label} (en desarrollo)`;
        }
    }
};
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
</script>
