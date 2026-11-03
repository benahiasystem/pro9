<template>
    <div class="mt-2" aria-live="polite">
        <small v-if="settings.loading" class="text-muted" role="status">Cargando configuración de cobros…</small>
        <small v-if="settings.loaded && !settings.enabled && !settings.loading" class="d-block text-muted">IGTF está deshabilitado en la configuración de la empresa.</small>
        <div v-if="settings.error" class="text-danger" role="alert">
            {{ settings.error }}
            <el-button type="text" native-type="button" :disabled="settings.loading" @click="retry">Reintentar</el-button>
        </div>
    </div>
</template>
<script>
import {igtfSettings, loadIgtfSettings} from '../helpers/igtf-settings'

export default {
    data: () => ({settings: igtfSettings}),
    methods: {retry() { return loadIgtfSettings(this.$http) }},
}
</script>
