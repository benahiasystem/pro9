<template>
    <section class="branding-panel card h-100">
        <div class="card-header bg-info bg-info-customer-admin">
            <h3 class="my-0">Configuración de {{ appName }}</h3>
        </div>

        <div class="card-body">
            <el-form ref="form" :model="form" :rules="rules" label-position="top">
                <div class="form-group branding-name-field mb-0">
                    <label class="control-label">Nombre de la marca</label>
                    <el-input
                        v-model="form.brandName"
                        :disabled="loading"
                        maxlength="100"
                        show-word-limit
                        :placeholder="`Ejemplo: ${appName}.pe`"
                    />
                </div>
            </el-form>

            <div class="branding-section-head">
                <span class="branding-section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="4" />
                        <circle cx="9" cy="9" r="2" />
                        <path d="M21 15l-5 -5l-9 9" />
                    </svg>
                    Logo
                </span>
            </div>

            <div class="logo-origin-row">
                <div class="logo-origin-copy">
                    <p class="logo-origin-title">Origen del logo</p>
                    <p class="logo-origin-description">
                        {{ logo.useSystemLogo
                            ? `${appName} usa el logo del sistema o el predeterminado cuando no existe uno general.`
                            : `El logo personalizado se utilizará únicamente dentro de ${appName}.` }}
                    </p>
                    <small v-if="logo.useSystemLogo && !logo.hasSystemLogo" class="d-block text-warning mt-1">
                        No hay un logo general configurado; se está usando el predeterminado.
                    </small>
                </div>
                <div class="logo-origin-control">
                    <span class="logo-origin-state">{{ logo.useSystemLogo ? 'Sistema' : 'Personalizado' }}</span>
                    <el-switch
                        v-model="logo.useSystemLogo"
                        :disabled="loading || logo.saving || logo.deleting"
                    />
                    <small v-if="logo.saving" class="d-block text-muted mt-1 text-end">
                        <i class="el-icon-loading me-1"></i>Guardando…
                    </small>
                </div>
            </div>

            <div v-if="!logo.useSystemLogo" class="form-group mt-3 mb-0">

                <div v-if="logo.saving || logo.deleting" class="img-thumbnail w-100 d-flex align-items-center justify-content-center bg-light image-skeleton">
                    <i class="el-icon-loading me-2"></i>
                    <span>Cargando…</span>
                </div>

                <div v-else class="image-container image-container-fluid">
                    <div v-if="logoPreview && !logo.previewError">
                        <img
                            :src="logoPreview"
                            :alt="`Logo ${appName}`"
                            class="img-fluid img-small img-fluid-light img-thumbnail w-100"
                            @error="logo.previewError = true"
                        />
                        <div class="overlay">
                            <el-button
                                class="btn btn-sm me-2"
                                :disabled="loading"
                                @click="showLogoFilePicker"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 upload-icon">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                                    <path d="M7 9l5 -5l5 5" />
                                    <path d="M12 4l0 12" />
                                </svg>
                                Cambiar
                            </el-button>
                            <el-button
                                v-if="logo.hasCustomLogo || logo.file"
                                type="danger"
                                plain
                                class="btn btn-sm delete-logo-btn"
                                :loading="logo.deleting"
                                @click="deleteLogo"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M4 7l16 0" />
                                    <path d="M10 11l0 6" />
                                    <path d="M14 11l0 6" />
                                    <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                                    <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                                </svg>
                                Eliminar
                            </el-button>
                        </div>
                    </div>

                    <div
                        v-else
                        class="d-flex flex-column justify-content-center align-items-center gap-2 p-2 drop-zone"
                        :class="{ 'drop-zone-active': isDraggingLogo }"
                        @dragover="onLogoDragOver"
                        @dragleave="onLogoDragLeave"
                        @drop="onLogoDrop"
                    >
                        <div class="p-2 bg-light rounded">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted">
                                <path d="M15 8h.01" />
                                <path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z" />
                                <path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5" />
                                <path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-center mb-1">Arrastra una imagen aquí o haz clic para seleccionar</p>
                            <p class="text-muted text-center mb-1">SVG, PNG o JPG · Máx. 2 MB</p>
                        </div>
                        <el-button class="btn btn-sm" :disabled="loading" @click="showLogoFilePicker">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 upload-icon">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                                <path d="M7 9l5 -5l5 5" />
                                <path d="M12 4l0 12" />
                            </svg>
                            Seleccionar imagen
                        </el-button>
                    </div>
                </div>

                <input
                    ref="logoInput"
                    type="file"
                    class="d-none"
                    accept=".svg,.png,.jpg,.jpeg"
                    :disabled="loading || logo.saving || logo.deleting"
                    @change="onLogoFileChange"
                />

                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small v-if="logo.fileName" class="text-muted">Seleccionado: {{ logo.fileName }}</small>
                </div>
            </div>

            <div v-if="!logo.useSystemLogo && logo.file" class="text-end mt-3">
                <el-button
                    type="primary"
                    plain
                    :loading="logo.saving"
                    :disabled="loading || saving || logo.deleting"
                    @click="saveLogo()"
                >
                    Guardar logo
                </el-button>
            </div>

            <div class="branding-section-head palette-section-head">
                <span class="branding-section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="13.5" cy="6.5" r=".5" fill="currentColor" />
                        <circle cx="17.5" cy="10.5" r=".5" fill="currentColor" />
                        <circle cx="8.5" cy="7.5" r=".5" fill="currentColor" />
                        <circle cx="6.5" cy="12.5" r=".5" fill="currentColor" />
                        <path d="M12 2a10 10 0 1 0 0 20c1.4 0 2-1 2-2c0-.5-.2-1-.6-1.4c-.4-.4-.6-.9-.6-1.4c0-1.1.9-2 2-2h2.4c2.3 0 4.2-1.9 4.2-4.2c0-5-4.2-9-9.4-9z" />
                    </svg>
                    Paleta de colores
                </span>

                <div class="palette-controls">
                    <button
                        v-if="hasColorChanges"
                        type="button"
                        class="btn btn-sm d-flex align-items-center palette-action"
                        :disabled="loading || saving"
                        @click="resetColors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                            <path d="M3 12a9 9 0 1 0 3 -6.7" />
                            <path d="M3 4v6h6" />
                        </svg>
                        Restablecer colores
                    </button>

                    <button type="button" class="btn btn-sm d-flex align-items-center palette-action" @click="togglePaletteMode">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                            <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                        </svg>
                        {{ paletteMode === 'light' ? 'Claro' : 'Oscuro' }}
                    </button>
                </div>
            </div>

            <div v-if="presetPalettes.length" class="palette-presets">
                <button
                    v-for="palette in presetPalettes"
                    :key="palette.name"
                    type="button"
                    class="palette-preset"
                    :class="{ 'is-active': activePaletteName === palette.name }"
                    :disabled="loading || saving"
                    :title="`Aplicar paleta ${palette.name}`"
                    @click="applyPalette(palette)"
                >
                    <span class="palette-preset__swatches">
                        <span
                            v-for="(hex, index) in palettePreview(palette)"
                            :key="`${palette.name}-${index}`"
                            class="palette-preset__dot"
                            :style="{ backgroundColor: hex }"
                        ></span>
                    </span>
                    <span class="palette-preset__name">{{ palette.name }}</span>
                </button>
            </div>

            <div class="row palette-grid">
                <div v-for="entry in visibleColorEntries" :key="entry.key" class="col-md-6 col-12 mb-2">
                    <div class="color-row">
                        <label
                            class="color-row__swatch"
                            :style="{ backgroundColor: isValidHex(colors[entry.key].hex) ? colors[entry.key].hex : '#ffffff' }"
                        >
                            <input v-model="colors[entry.key].hex" type="color" :disabled="loading || saving" />
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3l-12.5 12.5l-4 1l1 -4z" />
                            </svg>
                        </label>

                        <div class="color-row__meta">
                            <div class="color-row__title">{{ entry.label }}</div>
                            <div class="color-row__key">{{ entry.key }}</div>
                        </div>

                        <el-input
                            v-model.trim="colors[entry.key].hex"
                            maxlength="7"
                            placeholder="#RRGGBB"
                            class="color-row__input"
                            :class="{ 'is-invalid': colors[entry.key].hex && !isValidHex(colors[entry.key].hex) }"
                            :disabled="loading || saving"
                        />
                    </div>
                </div>
            </div>

            <div class="branding-actions">
                <el-button type="primary" :loading="saving" :disabled="loading" @click="saveConfiguration">
                    Guardar cambios
                </el-button>
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: 'SystemBrandingPanel',

    props: {
        appName: {
            type: String,
            required: true
        },
        endpoint: {
            type: String,
            required: true
        },
        initialColors: {
            type: Object,
            required: true
        },
        palettes: {
            type: Array,
            default: () => []
        }
    },

    data() {
        const colors = {}
        Object.keys(this.initialColors).forEach(key => {
            colors[key] = { ...this.initialColors[key] }
        })

        return {
            loading: false,
            saving: false,
            paletteMode: 'light',
            form: { brandName: '' },
            logo: {
                useSystemLogo: true,
                hasCustomLogo: false,
                hasSystemLogo: false,
                serverUrl: '',
                localPreview: '',
                fileName: '',
                file: null,
                saving: false,
                deleting: false,
                initialized: false,
                suppressWatch: false,
                previewError: false
            },
            isDraggingLogo: false,
            colors,
            rules: {
                brandName: [
                    { required: true, message: 'Ingresa el nombre de la marca', trigger: 'blur' },
                    { max: 100, message: 'El nombre no puede superar los 100 caracteres', trigger: 'blur' }
                ]
            }
        }
    },

    computed: {
        visibleColorEntries() {
            return Object.keys(this.colors)
                .filter(key => this.paletteMode === 'dark'
                    ? key.toLowerCase().startsWith('dark')
                    : !key.toLowerCase().startsWith('dark'))
                .map(key => ({ key, label: this.colors[key].label }))
        },
        logoPreview() {
            return this.logo.localPreview || this.logo.serverUrl
        },
        presetPalettes() {
            const defaultColors = {}
            Object.keys(this.initialColors).forEach(key => {
                defaultColors[key] = this.initialColors[key].hex
            })
            return [
                { name: 'Predeterminada', colors: defaultColors },
                ...this.palettes
            ]
        },
        activePaletteName() {
            const active = this.presetPalettes.find(palette =>
                Object.keys(palette.colors).every(key =>
                    !this.colors[key] ||
                    String(this.colors[key].hex || '').toLowerCase() === String(palette.colors[key] || '').toLowerCase()
                )
            )
            return active ? active.name : null
        },
        hasColorChanges() {
            return Object.keys(this.colors).some(key => {
                const current = String(this.colors[key].hex || '').toLowerCase()
                const original = String(this.initialColors[key].hex || '').toLowerCase()
                return current !== original
            })
        }
    },

    watch: {
        'logo.useSystemLogo'(useSystem, previousValue) {
            if (!this.logo.initialized || this.logo.suppressWatch) return
            if (useSystem) this.clearLogoSelection()
            this.saveLogo(true, previousValue)
        }
    },

    created() {
        this.loadConfiguration()
    },

    beforeDestroy() {
        this.revokeLocalPreview()
    },

    methods: {
        isValidHex(value) {
            return /^#[0-9a-fA-F]{6}$/.test(value)
        },
        loadConfiguration() {
            this.loading = true
            this.$http.get(`${this.endpoint}/record`)
                .then(response => {
                    this.form.brandName = response.data.brandName || ''
                    Object.keys(this.colors).forEach(key => {
                        if (response.data[key]) this.colors[key].hex = response.data[key]
                    })
                    this.logo.useSystemLogo = response.data.useSystemLogo !== false
                    this.logo.hasCustomLogo = !!response.data.hasCustomLogo
                    this.logo.hasSystemLogo = !!response.data.hasSystemLogo
                    this.logo.serverUrl = response.data.logoUrl || ''
                    this.logo.previewError = false
                    this.$nextTick(() => { this.logo.initialized = true })
                })
                .catch(error => this.showError(error, `No se pudo cargar la configuración de ${this.appName}.`))
                .then(() => {
                    this.loading = false
                    if (!this.logo.initialized) this.logo.initialized = true
                })
        },
        saveConfiguration() {
            this.$refs.form.validate(valid => {
                if (!valid || this.saving) return
                const invalidColor = Object.keys(this.colors).find(key => !this.isValidHex(this.colors[key].hex))
                if (invalidColor) {
                    this.$message.error(`El valor de ${this.colors[invalidColor].label} debe tener el formato #RRGGBB.`)
                    return
                }

                const payload = { brandName: this.form.brandName }
                Object.keys(this.colors).forEach(key => { payload[key] = this.colors[key].hex })
                this.saving = true
                this.$http.put(this.endpoint, payload)
                    .then(response => {
                        const configuration = response.data.configuration || {}
                        this.form.brandName = configuration.brandName || this.form.brandName
                        Object.keys(this.colors).forEach(key => {
                            if (configuration[key]) this.colors[key].hex = configuration[key]
                        })
                        this.$message.success(response.data.message)
                    })
                    .catch(error => this.showError(error, `No se pudo guardar la configuración de ${this.appName}.`))
                    .then(() => { this.saving = false })
            })
        },
        togglePaletteMode() {
            this.paletteMode = this.paletteMode === 'light' ? 'dark' : 'light'
        },
        palettePreview(palette) {
            return ['Primary', 'Secondary', 'Background', 'Text']
                .filter(key => palette.colors[key])
                .map(key => palette.colors[key])
        },
        applyPalette(palette) {
            Object.keys(palette.colors).forEach(key => {
                if (this.colors[key]) this.colors[key].hex = palette.colors[key]
            })
            this.$message.info(`Se aplicó la paleta "${palette.name}" en ${this.appName}. Guarda la configuración para aplicar el cambio.`)
        },
        resetColors() {
            Object.keys(this.colors).forEach(key => {
                this.colors[key].hex = this.initialColors[key].hex
            })
            this.$message.info(`Los colores de ${this.appName} volvieron a sus valores predeterminados. Guarda la configuración para aplicar el cambio.`)
        },
        revokeLocalPreview() {
            if (this.logo.localPreview) URL.revokeObjectURL(this.logo.localPreview)
        },
        clearLogoSelection() {
            this.revokeLocalPreview()
            this.isDraggingLogo = false
            this.logo.localPreview = ''
            this.logo.file = null
            this.logo.fileName = ''
            this.logo.previewError = false
            if (this.$refs.logoInput) this.$refs.logoInput.value = ''
        },
        showLogoFilePicker() {
            if (!this.loading && !this.logo.saving && !this.logo.deleting && this.$refs.logoInput) {
                this.$refs.logoInput.click()
            }
        },
        onLogoFileChange(event) {
            const file = event.target.files && event.target.files[0]
            if (!file) return
            this.setLogoFile(file)
            event.target.value = ''
        },
        onLogoDragOver(event) {
            event.preventDefault()
            event.stopPropagation()
            if (!this.loading && !this.logo.saving && !this.logo.deleting) this.isDraggingLogo = true
        },
        onLogoDragLeave(event) {
            event.preventDefault()
            event.stopPropagation()
            this.isDraggingLogo = false
        },
        onLogoDrop(event) {
            event.preventDefault()
            event.stopPropagation()
            this.isDraggingLogo = false
            if (this.loading || this.logo.saving || this.logo.deleting) return
            const files = event.dataTransfer && event.dataTransfer.files
            if (files && files.length) this.setLogoFile(files[0])
        },
        setLogoFile(file) {
            if (!/\.(svg|png|jpe?g)$/i.test(file.name)) {
                this.$message.error('El logo debe ser un archivo SVG, PNG o JPG.')
                this.clearLogoSelection()
                return
            }
            if (file.size > 2 * 1024 * 1024) {
                this.$message.error('El logo no puede superar los 2MB.')
                this.clearLogoSelection()
                return
            }
            this.revokeLocalPreview()
            this.logo.file = file
            this.logo.fileName = file.name
            this.logo.localPreview = URL.createObjectURL(file)
            this.logo.previewError = false
        },
        saveLogo(automatic = false, previousValue = null) {
            if (this.logo.saving || this.logo.deleting) return

            const formData = new FormData()
            formData.append('useSystemLogo', this.logo.useSystemLogo ? '1' : '0')
            if (!this.logo.useSystemLogo && this.logo.file) formData.append('logo', this.logo.file)
            this.logo.saving = true

            this.$http.post(`${this.endpoint}/logo`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
                .then(response => {
                    this.logo.useSystemLogo = response.data.useSystemLogo !== false
                    this.logo.hasCustomLogo = !!response.data.hasCustomLogo
                    this.logo.hasSystemLogo = !!response.data.hasSystemLogo
                    this.logo.serverUrl = response.data.logoUrl || ''
                    this.clearLogoSelection()
                    this.$message.success(response.data.message)
                })
                .catch(error => {
                    if (automatic && previousValue !== null) {
                        this.logo.suppressWatch = true
                        this.logo.useSystemLogo = previousValue
                        this.$nextTick(() => { this.logo.suppressWatch = false })
                    }
                    this.showError(error, `No se pudo guardar el logo de ${this.appName}.`)
                })
                .then(() => { this.logo.saving = false })
        },
        deleteLogo() {
            if (this.logo.saving || this.logo.deleting) return

            if (this.logo.file && !this.logo.hasCustomLogo) {
                this.clearLogoSelection()
                this.$message.info('Se canceló la selección del logo.')
                return
            }

            if (!this.logo.hasCustomLogo) return

            this.$confirm(
                `¿Deseas eliminar el logo personalizado de ${this.appName} y restaurar el predeterminado?`,
                'Confirmar eliminación',
                {
                    confirmButtonText: 'Eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }
            ).then(() => {
                this.logo.deleting = true
                this.$http.delete(`${this.endpoint}/logo`)
                    .then(response => {
                        this.logo.useSystemLogo = false
                        this.logo.hasCustomLogo = false
                        this.logo.hasSystemLogo = !!response.data.hasSystemLogo
                        this.logo.serverUrl = response.data.logoUrl || ''
                        this.clearLogoSelection()
                        this.$message.success(response.data.message)
                    })
                    .catch(error => this.showError(error, `No se pudo eliminar el logo de ${this.appName}.`))
                    .then(() => { this.logo.deleting = false })
            }).catch(() => {})
        },
        showError(error, fallback) {
            const data = error.response && error.response.data
            let message = data && data.message
            if (data && data.errors) {
                const firstKey = Object.keys(data.errors)[0]
                message = firstKey ? data.errors[firstKey][0] : message
            }
            this.$message.error(message || fallback)
        }
    }
}
</script>

<style>
.branding-panel .color-row__input .el-input__inner {
    height: 36px !important;
    padding: 0 9px;
    text-align: center;
    font-family: monospace;
    font-size: 12px;
}
</style>

<style scoped>
.branding-panel { overflow: hidden; }
.branding-name-field { margin-top: 2px; }
.branding-section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 26px 0 12px; }
.branding-section-title { display: inline-flex; align-items: center; gap: 7px; color: var(--dark-color, #282425); font-size: 13px; font-weight: 700; }
.branding-section-title svg { color: var(--muted, #7c8594); }
.logo-origin-row { display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 12px 14px; border: 1px solid #e0e6f7; border-radius: 10px; background: var(--light-color, #f8f9fb); }
.logo-origin-copy { flex: 1 1 auto; min-width: 0; }
.logo-origin-title { margin: 0 0 3px; font-size: 14px; font-weight: 600; }
.logo-origin-description { margin: 0; color: var(--muted, #7c8594); font-size: 12px; line-height: 1.45; }
.logo-origin-control { flex: 0 0 auto; text-align: right; }
.logo-origin-state { display: inline-block; margin-right: 8px; color: var(--dark-color, #282425); font-size: 12px; font-weight: 600; }
.palette-section-head { flex-wrap: wrap; }
.palette-controls { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.palette-action { color: #606266; background: #fff; border: 1px solid #dcdfe6; border-radius: 6px; transition: all .15s ease; }
.palette-action:hover:not(:disabled) { color: var(--primary, #32a56a); border-color: var(--primary, #32a56a); }
.palette-action:disabled { cursor: not-allowed; opacity: .6; }
.palette-presets { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.palette-preset { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; color: #606266; background: #fff; border: 1px solid #dcdfe6; border-radius: 8px; cursor: pointer; transition: all .15s ease; }
.palette-preset:hover:not(:disabled) { color: var(--primary, #32a56a); border-color: var(--primary, #32a56a); }
.palette-preset:disabled { cursor: not-allowed; opacity: .6; }
.palette-preset.is-active { color: var(--primary, #32a56a); border-color: var(--primary, #32a56a); box-shadow: 0 0 0 2px var(--accent-color, #eef2ff); }
.palette-preset__swatches { display: inline-flex; }
.palette-preset__dot { width: 16px; height: 16px; margin-left: -5px; border: 1.5px solid #fff; border-radius: 50%; box-shadow: 0 0 0 1px rgba(0,0,0,.06); }
.palette-preset__dot:first-child { margin-left: 0; }
.palette-preset__name { font-size: 12px; font-weight: 600; }
.palette-grid { margin-right: -5px; margin-left: -5px; }
.palette-grid > div { padding-right: 5px; padding-left: 5px; }
.color-row { display: flex; align-items: center; gap: 9px; min-height: 62px; padding: 8px; border: 1px solid #e0e6f7; border-radius: 10px; background: #fff; transition: border-color .15s ease, box-shadow .15s ease; }
.color-row:focus-within { border-color: var(--primary-color, #32a56a); box-shadow: 0 0 0 2px var(--accent-color, #eef2ff); }
.color-row__swatch { position: relative; flex: 0 0 42px; width: 42px; height: 42px; margin: 0; display: inline-flex; align-items: flex-end; justify-content: flex-end; padding: 4px; overflow: hidden; color: #303133; border: 1px solid rgba(0,0,0,.08); border-radius: 9px; cursor: pointer; }
.color-row__swatch input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
.color-row__swatch svg { padding: 3px; box-sizing: content-box; color: #303133; background: rgba(255,255,255,.9); border-radius: 50%; pointer-events: none; }
.color-row__meta { flex: 1 1 auto; min-width: 68px; }
.color-row__title { overflow: hidden; color: var(--dark-color, #282425); font-size: 12px; font-weight: 600; line-height: 1.2; text-overflow: ellipsis; white-space: nowrap; }
.color-row__key { margin-top: 2px; color: var(--muted, #7c8594); font-size: 10px; }
.color-row__input { flex: 0 0 88px; width: 88px; }
.branding-actions { display: flex; justify-content: flex-end; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e0e6f7; }
.image-container { position: relative; display: inline-block; width: 100%; }
.image-skeleton { height: 150px; }
.overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background-color: rgba(255,255,255,.908); opacity: 0; transition: opacity .3s ease; }
.image-container:hover .overlay { opacity: 1; }
.img-small { height: 150px; object-fit: contain; }
.drop-zone { min-height: 150px; border: 2px dashed transparent; border-radius: .5rem; transition: all .3s ease; }
.drop-zone-active { border-color: #409eff; background: #f5f9ff; }
.upload-icon { margin-top: -2px; }
@media (max-width: 575.98px) {
    .logo-origin-row { align-items: flex-start; flex-direction: column; }
    .logo-origin-control { width: 100%; text-align: left; }
    .palette-controls { width: 100%; }
    .color-row__input { flex-basis: 96px; width: 96px; }
}
</style>
