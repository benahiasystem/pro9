<template>
    <!-- ########## INICIO CAMBIO RIF SUPER ADMIN -->
    <el-input
        :value="value"
        :maxlength="10"
        placeholder="Ej. J123456789"
        show-word-limit
        @input="handleInput">
        <el-button
            v-if="available"
            slot="append"
            type="primary"
            icon="el-icon-search"
            :loading="loading"
            :disabled="loading || !isValid"
            @click.prevent="search">
            Buscar
        </el-button>
    </el-input>
    <!-- ######### FIN CAMBIO RIF SUPER ADMIN -->
</template>

<script>
// ########## INICIO CAMBIO RIF SUPER ADMIN
export default {
    name: 'SystemClientRifInput',
    props: {
        value: {
            type: String,
            default: '',
        },
        available: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            loading: false,
        }
    },
    computed: {
        normalizedValue() {
            return String(this.value || '').trim().toUpperCase().replace(/[\s.\-]+/g, '')
        },
        isValid() {
            return /^[VEJPG][0-9]{9}$/.test(this.normalizedValue)
        },
    },
    methods: {
        handleInput(value) {
            this.$emit('input', String(value || '').toUpperCase().replace(/[\s.\-]+/g, ''))
        },
        async search() {
            if (this.loading || !this.isValid) return

            this.loading = true
            try {
                const response = await this.$http.get(`/services/rif/${encodeURIComponent(this.normalizedValue)}`)
                if (response.data.success) {
                    this.$emit('search', response.data.data)
                }
            } catch (error) {
                const message = error.response && error.response.data && error.response.data.message
                    ? error.response.data.message
                    : 'No fue posible consultar el RIF. Puede continuar con la carga manual.'
                this.$message.error(message)
            } finally {
                this.loading = false
            }
        },
    },
}
// ######### FIN CAMBIO RIF SUPER ADMIN
</script>
