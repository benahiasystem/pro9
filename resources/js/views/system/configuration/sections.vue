<template>
    <div class="row">

        <div class="col-lg-3 col-md-4 col-12">
            <div class="card" style="position: sticky; top: 10px; z-index: 5;">
                <div class="card-header bg-info bg-info-customer-admin">
                    <h3 class="my-0">Secciones</h3>
                </div>
                <div class="card-body pt-3 section-config">

                    <el-input v-model="search"
                              placeholder="Buscar configuración..."
                              prefix-icon="el-icon-search"
                              clearable
                              class="mb-3"></el-input>

                    <el-menu :default-active="active"
                             class="border-0"
                             @select="selectCategory">
                        <el-menu-item v-for="category in categories"
                                      :key="category.key"
                                      :index="category.key"
                                      :disabled="isSearching && countMatches(category.key) === 0"
                                      style="border-radius: 8px;">
                            <span class="me-2 align-middle" v-html="category.icon"></span>
                            <span slot="title">
                                {{ category.label }}
                                <small v-if="isSearching" class="ms-1">({{ countMatches(category.key) }})</small>
                            </span>
                        </el-menu-item>
                    </el-menu>

                    <div v-if="isSearching" class="pt-3">
                        <small>{{ totalMatches }} resultado(s) para "{{ search }}"</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-9 col-md-8 col-12">
            <div class="row">

                <div :class="colClass('other')" v-show="isVisible('other')">
                    <slot name="other"></slot>
                </div>
                <div :class="colClass('apk')" v-show="isVisible('apk')">
                    <slot name="apk"></slot>
                </div>

                <div :class="colClass('login')" v-show="isVisible('login')">
                    <slot name="login"></slot>
                </div>
                <div :class="colClass('themes')" v-show="isVisible('themes')">
                    <slot name="themes"></slot>
                </div>
                <div :class="colClass('columns')" v-show="isVisible('columns')">
                    <slot name="columns"></slot>
                </div>

                <div :class="colClass('openai')" v-show="isVisible('openai')">
                    <slot name="openai"></slot>
                </div>
                <div :class="colClass('maps')" v-show="isVisible('maps')">
                    <slot name="maps"></slot>
                </div>
                <div :class="colClass('ruc')" v-show="isVisible('ruc')">
                    <slot name="ruc"></slot>
                </div>

                <div :class="colClass('gateway')" v-show="isVisible('gateway')">
                    <slot name="gateway"></slot>
                </div>
                <div :class="colClass('cron')" v-show="isVisible('cron')">
                    <slot name="cron"></slot>
                </div>

                <div :class="colClass('email')" v-show="isVisible('email')">
                    <slot name="email"></slot>
                </div>
                <div :class="colClass('whatsapp')" v-show="isVisible('whatsapp')">
                    <slot name="whatsapp"></slot>
                </div>

                <div :class="colClass('support')" v-show="isVisible('support')">
                    <slot name="support"></slot>
                </div>
                <div :class="colClass('terms')" v-show="isVisible('terms')">
                    <slot name="terms"></slot>
                </div>

                <div class="col-12" v-show="isSearching && totalMatches === 0">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="el-icon-search" style="font-size: 32px;"></i>
                            <p class="mt-3 mb-0">No se encontraron configuraciones para "{{ search }}"</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</template>
<style>
.section-config .el-menu-item:hover {
    background-color: var(--accent-color);
}
.section-config .el-menu-item.is-active,
.section-config .el-menu-item:focus {
    background-color: var(--primary-color);
    color: #fff;
}
</style>
<script>
export default {
    data() {
        return {
            storageKey: 'system-configuration-active-section',
            search: '',
            active: 'general',
            primaryColor: '#3d6bf5',
            categories: [
                {
                    key: 'general',
                    label: 'General',
                    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-adjustments-horizontal"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M12 6a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M4 6l8 0" /><path d="M16 6l4 0" /><path d="M6 12a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M4 12l2 0" /><path d="M10 12l10 0" /><path d="M15 18a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M4 18l11 0" /><path d="M19 18l1 0" /></svg>`,
                },
                {
                    key: 'apariencia',
                    label: 'Apariencia',
                    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-paint"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z" /><path d="M19 6h1a2 2 0 0 1 2 2a5 5 0 0 1 -5 5l-5 0v2" /><path d="M10 15m0 1a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1z" /></svg>`,
                },
                {
                    key: 'integraciones',
                    label: 'Integraciones',
                    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-link"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M9 15l6 -6" /><path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464" /><path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463" /></svg>`,
                },
                {
                    key: 'pagos',
                    label: 'Pagos',
                    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-credit-card"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 5m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" /><path d="M3 10l18 0" /><path d="M7 15l.01 0" /><path d="M11 15l2 0" /></svg>`,
                },
                {
                    key: 'notificaciones',
                    label: 'Notificaciones',
                    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-bell"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" /><path d="M9 17v1a3 3 0 0 0 6 0v-1" /></svg>`,
                },
                {
                    key: 'soporte',
                    label: 'Soporte y legal',
                    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-text"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2" /><path d="M9 9l1 0" /><path d="M9 13l6 0" /><path d="M9 17l6 0" /></svg>`,
                },
            ],
            sections: [
                {key: 'other', category: 'general', label: 'Otras configuraciones', keywords: 'registro invitados validar ruc planes url registro', half: false},
                {key: 'apk', category: 'general', label: 'URL de descarga de aplicación móvil', keywords: 'apk android app movil descarga', half: true},

                {key: 'login', category: 'apariencia', label: 'Login de los clientes', keywords: 'login acceso logo imagen fondo redes sociales facebook instagram tiktok', half: false},
                {key: 'themes', category: 'apariencia', label: 'Temas del sistema', keywords: 'tema color estilo dark modo oscuro', half: false},
                {key: 'columns', category: 'apariencia', label: 'Columnas por defecto', keywords: 'columnas visibles tablas listados', half: false},

                {key: 'openai', category: 'integraciones', label: 'Configuración de IA', keywords: 'openai ia inteligencia artificial token chatgpt', half: false},
                {key: 'maps', category: 'integraciones', label: 'Google Maps', keywords: 'google maps mapa api key ubicacion', half: false},
                {key: 'ruc', category: 'integraciones', label: 'Consulta RUC/DNIe', keywords: 'ruc dni sunat reniec token api consulta', half: false},

                {key: 'gateway', category: 'pagos', label: 'Pasarela de pago', keywords: 'pago pasarela culqi mercadopago niubiz metodo', half: true},
                {key: 'cron', category: 'pagos', label: 'Tareas automáticas', keywords: 'cron tareas automaticas pagos pedidos programado', half: true},

                {key: 'email', category: 'notificaciones', label: 'Configuración de correo', keywords: 'email correo smtp mail servidor remitente', half: false},
                {key: 'whatsapp', category: 'notificaciones', label: 'Número de WhatsApp (notificaciones)', keywords: 'whatsapp numero notificaciones evolution mensajes', half: false},

                {key: 'support', category: 'soporte', label: 'Configuración de soporte', keywords: 'soporte ayuda contacto telefono', half: false},
                {key: 'terms', category: 'soporte', label: 'Términos y condiciones', keywords: 'terminos condiciones legal politicas privacidad', half: false},
            ],
        }
    },
    computed: {
        isSearching() {
            return this.normalize(this.search).length > 0
        },
        matchedKeys() {
            if (!this.isSearching) return []
            const query = this.normalize(this.search)
            return this.sections
                .filter(section => this.normalize(`${section.label} ${section.keywords}`).includes(query))
                .map(section => section.key)
        },
        totalMatches() {
            return this.matchedKeys.length
        },
    },
    created() {
        const styles = getComputedStyle(document.documentElement)
        const color = (styles.getPropertyValue('--primary-color') || '').trim()

        if (color) this.primaryColor = color

        const stored = window.location.hash.replace('#', '') || localStorage.getItem(this.storageKey)

        if (stored && this.categories.some(category => category.key === stored)) {
            this.active = stored
        }
    },
    methods: {
        normalize(value) {
            return (value || '')
                .toString()
                .toLowerCase()
                .normalize('NFD')
                .replace(new RegExp('[' + String.fromCharCode(0x0300) + '-' + String.fromCharCode(0x036f) + ']', 'g'), '')
                .trim()
        },
        selectCategory(key) {
            this.active = key
            this.search = ''
            localStorage.setItem(this.storageKey, key)
            window.history.replaceState(null, '', `#${key}`)
        },
        countMatches(categoryKey) {
            return this.sections
                .filter(section => section.category === categoryKey && this.matchedKeys.includes(section.key))
                .length
        },
        findSection(key) {
            return this.sections.find(section => section.key === key)
        },
        isVisible(key) {
            const section = this.findSection(key)

            if (!section) return false
            if (this.isSearching) return this.matchedKeys.includes(key)

            return section.category === this.active
        },
        colClass(key) {
            const section = this.findSection(key)

            return (section && section.half) ? 'col-xl-6 col-12' : 'col-12'
        },
    },
}
</script>
