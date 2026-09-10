<template>
<div class="card">
    <div class="card-header bg-info bg-info-customer-admin">
        <h3 class="my-0">Publicidad</h3>
    </div>
    <div class="card-body ads-config">

        <p class="ads-intro">
            Configura los mensajes que verán los clientes al entrar al sistema. Puedes activar más de uno,
            pero conviene mostrar un solo mensaje a la vez para no saturar la pantalla.
        </p>

        <div class="ads-stack">

            <section class="ads-card" :class="{'is-on': form.tenant_show_ads}">
                <div class="ads-card__head">
                    <div class="ads-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-photo"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M15 8h.01" /><path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12" /><path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5" /><path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3" /></svg>
                    </div>
                    <div class="ads-card__title">
                        <h4 class="m-0">Modal con imagen</h4>
                        <p>Ocupa toda la pantalla al ingresar. Úsalo para campañas visuales que necesitan atención completa.</p>
                    </div>
                    <div class="ads-switch-wrap">
                        <span class="ads-switch-state">{{ form.tenant_show_ads ? 'Activada' : 'Desactivada' }}</span>
                        <el-switch v-model="form.tenant_show_ads" @change="submit"></el-switch>
                    </div>
                </div>

                <div class="ads-card__body" v-show="form.tenant_show_ads">
                    <div class="ads-grid">
                        <div class="ads-col-form">

                            <div class="ads-group">
                                <span class="ads-group__label">Imagen</span>

                                <el-upload v-if="!form.tenant_image_ads"
                                           class="ads-drop-upload"
                                           drag
                                           :headers="headers"
                                           :on-success="successUpload"
                                           :on-error="errorUpload"
                                           :show-file-list="false"
                                           accept="image/png,image/jpeg,image/gif,image/webp"
                                           action="/configurations/upload-tenant-ads">
                                    <div class="ads-drop">
                                        <div class="ads-drop__ico">
                                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                                <path d="M12 16V4m0 0L8 8m4-4l4 4" /><path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2" />
                                            </svg>
                                        </div>
                                        <strong>Arrastra la imagen o haz clic para elegirla</strong>
                                        <small>JPG, PNG, GIF o WEBP · hasta 2 MB</small>
                                    </div>
                                </el-upload>

                                <div class="ads-file" v-else>
                                    <img class="ads-file__thumb" :src="adsImageUrl" alt="Miniatura de la imagen cargada">
                                    <div class="ads-file__meta">
                                        <strong v-text="form.tenant_image_ads"></strong>
                                        <span>Imagen cargada</span>
                                    </div>
                                    <el-upload :headers="headers"
                                               :on-success="successUpload"
                                               :on-error="errorUpload"
                                               :show-file-list="false"
                                               accept="image/png,image/jpeg,image/gif,image/webp"
                                               action="/configurations/upload-tenant-ads">
                                        <button type="button" class="ads-icon-btn" title="Reemplazar imagen">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M20 11a8 8 0 10-2.3 5.7M20 6v5h-5" />
                                            </svg>
                                        </button>
                                    </el-upload>
                                </div>
                            </div>

                            <div class="ads-divider"></div>

                            <div class="form-group">
                                <label class="control-label">Enlace al hacer clic <em>· opcional</em></label>
                                <el-input type="text"
                                       v-model="form.tenant_ads_link"
                                       placeholder="https://ejemplo.com/promocion"
                                       @change="submit">
                                </el-input>
                            </div>
                        </div>

                        <aside class="ads-col-preview">
                            <div class="ads-preview__head">
                                <span>Vista previa</span>
                                <button type="button" class="ads-link-btn" @click="demo = 'modal'" :disabled="!adsImageUrl">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3l14 9-14 9z" /></svg>
                                    Probar en pantalla
                                </button>
                            </div>
                            <div class="ads-frame">
                                <div class="ads-frame__bar"><i></i><i></i><i></i><span class="url">app.tuempresa.com</span></div>
                                <div class="ads-stage">
                                    <div class="ads-skeleton"><i></i><i></i><i></i><div class="blocks"><i></i><i></i></div></div>
                                    <div class="pv-modal">
                                        <div class="box">
                                            <span class="x">&times;</span>
                                            <img v-if="adsImageUrl" :src="adsImageUrl" alt="Imagen de la campaña">
                                            <div v-else class="pv-modal__empty">Sin imagen</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="ads-hint">Los usuarios pueden cerrarlo con la × o con la tecla Esc.</p>
                        </aside>
                    </div>
                </div>
            </section>

            <section class="ads-card" :class="{'is-on': form.tenant_ads_toolbar.enabled}">
                <div class="ads-card__head">
                    <div class="ads-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-layout-navbar"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 6a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2l0 -12" /><path d="M4 9l16 0" /></svg>
                    </div>
                    <div class="ads-card__title">
                        <h4 class="m-0">Barra superior</h4>
                        <p>Una franja fija sobre el contenido. Sirve para avisos cortos que acompañan al usuario mientras navega.</p>
                    </div>
                    <div class="ads-switch-wrap">
                        <span class="ads-switch-state">{{ form.tenant_ads_toolbar.enabled ? 'Activada' : 'Desactivada' }}</span>
                        <el-switch v-model="form.tenant_ads_toolbar.enabled" @change="submit"></el-switch>
                    </div>
                </div>

                <div class="ads-card__body" v-show="form.tenant_ads_toolbar.enabled">
                    <div class="ads-grid">
                        <div class="ads-col-form">

                            <div class="form-group mb-0">
                                <label class="control-label">Mensaje</label>
                                <el-input type="text"
                                       maxlength="255"
                                       v-model="form.tenant_ads_toolbar.text"
                                       placeholder="Aprovecha 20% de descuento en tu renovación"
                                       @change="submit">
                                </el-input>
                            </div>

                            <div class="form-group mb-0">
                                <label class="control-label">Enlace de la barra <em>· opcional</em></label>
                                <el-input type="text"
                                       v-model="form.tenant_ads_toolbar.link"
                                       placeholder="https://ejemplo.com/promocion"
                                       @change="submit">
                                </el-input>
                            </div>

                            <div class="ads-divider"></div>

                            <div class="ads-group">
                                <span class="ads-group__label">Colores</span>
                                <div class="ads-color-row">
                                    <label class="ads-color">
                                        <input type="color" v-model="form.tenant_ads_toolbar.background_color" @change="submit">
                                        <span class="ads-color__txt">
                                            <span>Fondo</span>
                                            <em v-text="form.tenant_ads_toolbar.background_color.toUpperCase()"></em>
                                        </span>
                                    </label>
                                    <label class="ads-color">
                                        <input type="color" v-model="form.tenant_ads_toolbar.text_color" @change="submit">
                                        <span class="ads-color__txt">
                                            <span>Texto</span>
                                            <em v-text="form.tenant_ads_toolbar.text_color.toUpperCase()"></em>
                                        </span>
                                    </label>
                                </div>

                                <div class="ads-swatches">
                                    <button v-for="(pair, index) in colorPresets"
                                            :key="'sw-' + index"
                                            type="button"
                                            class="ads-sw"
                                            :title="pair.label"
                                            :style="{ background: pair.bg }"
                                            @click="applyColorPreset(pair)"></button>
                                </div>

                                <div class="ads-contrast" :data-level="contrastLevel">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5" /></svg>
                                    <span>Contraste <b>{{ contrastRatio }}:1</b> · {{ contrastMessage }}</span>
                                </div>
                            </div>

                            <div class="ads-divider"></div>

                            <div class="ads-sub">
                                <span class="ads-sub__txt">
                                    <strong>Permitir cerrar la barra</strong>
                                    <span>Si se desactiva, la barra no tendrá botón de cierre y se mostrará siempre.</span>
                                </span>
                                <el-switch v-model="form.tenant_ads_toolbar.dismissible" @change="submit"></el-switch>
                            </div>
                        </div>

                        <aside class="ads-col-preview">
                            <div class="ads-preview__head">
                                <span>Vista previa</span>
                                <button type="button" class="ads-link-btn" @click="demo = 'toolbar'">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3l14 9-14 9z" /></svg>
                                    Probar en pantalla
                                </button>
                            </div>
                            <div class="ads-frame">
                                <div class="ads-frame__bar"><i></i><i></i><i></i><span class="url">app.tuempresa.com</span></div>
                                <div class="ads-stage">
                                    <div class="pv-bar" :style="toolbarStyle">
                                        <span class="t" v-text="form.tenant_ads_toolbar.text || 'Mensaje de la barra'"></span>
                                        <span class="x" v-if="form.tenant_ads_toolbar.dismissible">&times;</span>
                                    </div>
                                    <div class="ads-skeleton" style="padding-top: 56px;"><i></i><i></i><i></i><div class="blocks"><i></i><i></i></div></div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </section>

            <section class="ads-card" :class="{'is-on': form.tenant_ads_notification.enabled}">
                <div class="ads-card__head">
                    <div class="ads-card__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-bell"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" /><path d="M9 17v1a3 3 0 0 0 6 0v-1" /></svg>
                    </div>
                    <div class="ads-card__title">
                        <h4 class="m-0">Notificación</h4>
                        <p>Un aviso pequeño en una esquina. Es la opción menos invasiva: no bloquea el trabajo del usuario.</p>
                    </div>
                    <div class="ads-switch-wrap">
                        <span class="ads-switch-state">{{ form.tenant_ads_notification.enabled ? 'Activada' : 'Desactivada' }}</span>
                        <el-switch v-model="form.tenant_ads_notification.enabled" @change="submit"></el-switch>
                    </div>
                </div>

                <div class="ads-card__body" v-show="form.tenant_ads_notification.enabled">
                    <div class="ads-grid">
                        <div class="ads-col-form">

                            <div class="form-group mb-0">
                                <label class="control-label">
                                    Título
                                </label>
                                <el-input
                                    type="text"
                                    maxlength="120"
                                    v-model="form.tenant_ads_notification.title"
                                    placeholder="Nuevo: guías de remisión electrónicas"
                                    @change="submit">
                                </el-input>
                            </div>

                            <div class="form-group mb-0">
                                <label class="control-label">
                                    Descripción
                                </label>
                                <el-input
                                    type="textarea"
                                    rows="2"
                                    maxlength="300"
                                    v-model="form.tenant_ads_notification.description"
                                    placeholder="Ya puedes emitirlas desde el módulo de Ventas."
                                    @change="submit">
                                </el-input>
                            </div>

                            <div class="form-group mb-0">
                                <label class="control-label">
                                    Enlace de la notificación <em>· opcional</em>
                                </label>
                                <el-input
                                    type="text"
                                    v-model="form.tenant_ads_notification.link"
                                    placeholder="https://ejemplo.com/novedades"
                                    @change="submit">
                                </el-input>
                            </div>

                            <div class="ads-divider"></div>

                            <div class="ads-row-2">
                                <div class="ads-group">
                                    <span class="ads-group__label">Posición en pantalla</span>
                                    <div class="ads-pos-grid">
                                        <button v-for="option in positions"
                                                :key="option.value"
                                                type="button"
                                                class="ads-pos"
                                                :data-v="option.v"
                                                :data-h="option.h"
                                                :aria-pressed="String(form.tenant_ads_notification.position === option.value)"
                                                :title="option.label"
                                                @click="selectPosition(option.value)"><i></i></button>
                                    </div>
                                </div>

                                <div class="ads-group">
                                    <span class="ads-group__label">Ícono</span>
                                    <div class="ads-seg">
                                        <button v-for="option in iconTypes"
                                                :key="option.value"
                                                type="button"
                                                :aria-pressed="String(form.tenant_ads_notification.icon_type === option.value)"
                                                @click="selectIconType(option.value)"
                                                v-text="option.label"></button>
                                    </div>

                                    <div v-if="form.tenant_ads_notification.icon_type === 'tabler'">
                                        <tabler-icon-picker :icon="form.tenant_ads_notification.icon"
                                                            :svg="form.tenant_ads_notification.icon_svg"
                                                            @select="applyNotificationIcon"></tabler-icon-picker>
                                    </div>

                                    <div v-if="form.tenant_ads_notification.icon_type === 'emoji'">
                                        <emoji-picker :emoji="form.tenant_ads_notification.emoji"
                                                      @select="selectEmoji"></emoji-picker>
                                    </div>
                                </div>
                            </div>

                            <div class="ads-divider"></div>

                            <div class="ads-group">
                                <span class="ads-group__label">Tema</span>
                                <div class="ads-seg">
                                    <button v-for="option in themes"
                                            :key="option.value"
                                            type="button"
                                            :aria-pressed="String(form.tenant_ads_notification.theme === option.value)"
                                            @click="selectTheme(option.value)"
                                            v-text="option.label"></button>
                                </div>
                                <p class="ads-hint" v-text="themeHint"></p>
                            </div>

                            <div class="ads-divider"></div>

                            <div class="ads-sub">
                                <span class="ads-sub__txt">
                                    <strong>Permanece en pantalla</strong>
                                    <span v-text="durationHint"></span>
                                </span>
                                <span class="ads-sub__controls">
                                    <el-input-number v-if="!notificationForever"
                                                     size="small"
                                                     controls-position="right"
                                                     step-strictly
                                                     :min="3"
                                                     :max="300"
                                                     :step="1"
                                                     v-model="form.tenant_ads_notification.duration"
                                                     @change="setDuration"></el-input-number>
                                    <el-switch v-model="notificationForever"></el-switch>
                                </span>
                            </div>
                        </div>

                        <aside class="ads-col-preview">
                            <div class="ads-preview__head">
                                <span>Vista previa</span>
                                <button type="button" class="ads-link-btn" @click="demo = 'notification'">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3l14 9-14 9z" /></svg>
                                    Probar en pantalla
                                </button>
                            </div>
                            <div class="ads-frame">
                                <div class="ads-frame__bar"><i></i><i></i><i></i><span class="url">app.tuempresa.com</span></div>
                                <div class="ads-stage">
                                    <div class="ads-skeleton"><i></i><i></i><i></i><div class="blocks"><i></i><i></i></div></div>
                                    <div class="pv-toast"
                                         :class="['pv-toast--' + form.tenant_ads_notification.position, 'pv-toast--' + form.tenant_ads_notification.theme]">
                                        <span class="ic" v-if="notificationIconSvg" v-html="notificationIconSvg"></span>
                                        <span class="ic ic--emoji"
                                              v-else-if="form.tenant_ads_notification.icon_type === 'emoji' && form.tenant_ads_notification.emoji"
                                              v-text="form.tenant_ads_notification.emoji"></span>
                                        <span class="tx">
                                            <strong v-text="form.tenant_ads_notification.title || 'Título de la notificación'"></strong>
                                            <em v-if="form.tenant_ads_notification.description"
                                                v-text="form.tenant_ads_notification.description"></em>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </section>

        </div>

        <div class="ads-demo-bar" v-if="demo === 'toolbar'" :style="toolbarStyle">
            <span class="t" v-text="form.tenant_ads_toolbar.text || 'Mensaje de la barra'"></span>
            <button type="button" @click="demo = null">&times;</button>
        </div>

        <div class="ads-demo-modal" v-if="demo === 'modal'" @click.self="demo = null">
            <div class="box">
                <button type="button" class="x" @click="demo = null">&times;</button>
                <img v-if="adsImageUrl" :src="adsImageUrl" alt="Imagen de la campaña">
            </div>
        </div>

        <div class="ads-demo-toast"
             v-if="demo === 'notification'"
             :class="['ads-demo-toast--' + form.tenant_ads_notification.position, 'ads-demo-toast--' + form.tenant_ads_notification.theme]">
            <span class="ic" v-if="notificationIconSvg" v-html="notificationIconSvg"></span>
            <span class="ic ic--emoji"
                  v-else-if="form.tenant_ads_notification.icon_type === 'emoji' && form.tenant_ads_notification.emoji"
                  v-text="form.tenant_ads_notification.emoji"></span>
            <span class="tx">
                <strong v-text="form.tenant_ads_notification.title || 'Título de la notificación'"></strong>
                <em v-if="form.tenant_ads_notification.description"
                    v-text="form.tenant_ads_notification.description"></em>
            </span>
            <button type="button" class="x" @click="demo = null">&times;</button>
        </div>

    </div>
</div>
</template>

<script>
import TablerIconPicker from '../../../../../modules/Ecommerce/Resources/assets/js/components/TablerIconPicker.vue'
import EmojiPicker from '../../../components/EmojiPicker.vue'

function defaultAdsToolbar() {
    return {
        enabled: false,
        text: '',
        link: null,
        background_color: '#3d6bf5',
        text_color: '#ffffff',
        dismissible: true,
    }
}

function defaultAdsNotification() {
    return {
        enabled: false,
        position: 'bottom-right',
        theme: 'light',
        duration: 8,
        icon_type: 'none',
        icon: '',
        icon_svg: '',
        emoji: '',
        title: '',
        description: '',
        link: null,
    }
}

function relativeLuminance(hex) {
    const value = hex.replace('#', '')
    const full = value.length === 3 ? value.split('').map(c => c + c).join('') : value

    if (!/^[0-9a-f]{6}$/i.test(full)) {
        return null
    }

    const channels = [0, 2, 4].map(offset => {
        const channel = parseInt(full.substr(offset, 2), 16) / 255
        return channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4)
    })

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]
}

export default {
    components: {
        TablerIconPicker,
        EmojiPicker,
    },
    data() {
        return {
            headers: headers_token,
            resource: 'configurations',
            demo: null,
            positions: [
                {value: 'top-left', v: 'top', h: 'left', label: 'Arriba izquierda'},
                {value: 'top-right', v: 'top', h: 'right', label: 'Arriba derecha'},
                {value: 'bottom-left', v: 'bottom', h: 'left', label: 'Abajo izquierda'},
                {value: 'bottom-right', v: 'bottom', h: 'right', label: 'Abajo derecha'},
            ],
            iconTypes: [
                {value: 'none', label: 'Ninguno'},
                {value: 'tabler', label: 'Ícono'},
                {value: 'emoji', label: 'Emoji'},
            ],
            themes: [
                {value: 'light', label: 'Claro'},
                {value: 'dark', label: 'Oscuro'},
            ],
            colorPresets: [
                {label: 'Azul', bg: '#3d6bf5', fg: '#ffffff'},
                {label: 'Verde', bg: '#00c666', fg: '#ffffff'},
                {label: 'Naranja', bg: '#ff8400', fg: '#ffffff'},
                {label: 'Rojo', bg: '#ff0066', fg: '#ffffff'},
                {label: 'Oscuro', bg: '#3a4658', fg: '#ffffff'},
                {label: 'Claro', bg: '#e7edfc', fg: '#3a4658'},
            ],
            form: {
                tenant_show_ads: false,
                tenant_image_ads: null,
                tenant_ads_link: null,
                tenant_ads_toolbar: defaultAdsToolbar(),
                tenant_ads_notification: defaultAdsNotification(),
            },
        }
    },
    computed: {
        adsImageUrl() {
            return this.form.tenant_image_ads
                ? `/storage/uploads/system_ads/${this.form.tenant_image_ads}`
                : null
        },
        toolbarStyle() {
            return {
                backgroundColor: this.form.tenant_ads_toolbar.background_color,
                color: this.form.tenant_ads_toolbar.text_color,
            }
        },
        contrastRatio() {
            const bg = relativeLuminance(this.form.tenant_ads_toolbar.background_color)
            const fg = relativeLuminance(this.form.tenant_ads_toolbar.text_color)

            if (bg === null || fg === null) {
                return '—'
            }

            const ratio = (Math.max(bg, fg) + 0.05) / (Math.min(bg, fg) + 0.05)

            return ratio.toFixed(1)
        },
        contrastLevel() {
            const ratio = parseFloat(this.contrastRatio)

            if (isNaN(ratio)) return 'warn'
            if (ratio >= 4.5) return 'pass'
            if (ratio >= 3) return 'warn'

            return 'fail'
        },
        contrastMessage() {
            const levels = {
                pass: 'el texto se lee bien',
                warn: 'el texto se lee justo; considera otro color',
                fail: 'el texto casi no se lee',
            }

            return levels[this.contrastLevel]
        },
        notificationForever: {
            get() {
                return this.form.tenant_ads_notification.duration === 0
            },
            set(value) {
                this.form.tenant_ads_notification.duration = value ? 0 : 8
                this.submit()
            }
        },
        durationHint() {
            return this.notificationForever
                ? 'Se queda hasta que el usuario la cierre con la ×.'
                : 'Se cierra sola después de estos segundos (entre 3 y 300).'
        },
        themeHint() {
            const hints = {
                light: 'Fondo blanco con texto oscuro.',
                dark: 'Fondo oscuro con texto claro.',
            }

            return hints[this.form.tenant_ads_notification.theme] || hints.light
        },
        notificationIconSvg() {
            const inner = this.form.tenant_ads_notification.icon_svg

            if (this.form.tenant_ads_notification.icon_type !== 'tabler' || !inner) {
                return ''
            }

            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"'
                + ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
                + ' stroke-linejoin="round">' + inner + '</svg>'
        },
    },
    created() {
        this.loadConfiguration()
    },
    methods: {
        selectPosition(value) {
            this.form.tenant_ads_notification.position = value
            this.submit()
        },
        selectIconType(value) {
            this.form.tenant_ads_notification.icon_type = value
            this.submit()
        },
        selectTheme(value) {
            this.form.tenant_ads_notification.theme = value
            this.submit()
        },
        setDuration(value) {
            const seconds = Math.round(Number(value))

            this.form.tenant_ads_notification.duration = Number.isFinite(seconds) && seconds > 0
                ? Math.min(300, Math.max(3, seconds))
                : 8

            this.submit()
        },
        selectEmoji(emoji) {
            this.form.tenant_ads_notification.emoji = emoji
            this.submit()
        },
        applyNotificationIcon(payload) {
            this.form.tenant_ads_notification.icon = payload.icon
            this.form.tenant_ads_notification.icon_svg = payload.svg
            this.submit()
        },
        applyColorPreset(preset) {
            this.form.tenant_ads_toolbar.background_color = preset.bg
            this.form.tenant_ads_toolbar.text_color = preset.fg
            this.submit()
        },
        loadConfiguration() {
            this.$http.get(`/${this.resource}/get-other-configuration`)
                .then(response => {
                    const data = response.data

                    this.form.tenant_show_ads = data.tenant_show_ads
                    this.form.tenant_image_ads = data.tenant_image_ads
                    this.form.tenant_ads_link = data.tenant_ads_link
                    this.form.tenant_ads_toolbar = Object.assign(defaultAdsToolbar(), data.tenant_ads_toolbar || {})
                    this.form.tenant_ads_notification = Object.assign(defaultAdsNotification(), data.tenant_ads_notification || {})
                })
                .catch(error => {
                    console.error('Error loading configuration:', error)
                })
        },
        successUpload(response) {
            if (response.success) {
                this.$message.success(response.message)
                this.form.tenant_image_ads = response.name
            } else {
                this.$message.error(response.message || 'Error al subir el archivo')
            }
        },
        errorUpload() {
            this.$message.error('Error al subir el archivo')
        },
        submit() {
            this.$http
                .post(`/${this.resource}/other-configuration`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)

                        if (response.data.tenant_ads_link !== undefined) {
                            this.form.tenant_ads_link = response.data.tenant_ads_link
                        }
                        if (response.data.tenant_ads_toolbar) {
                            this.form.tenant_ads_toolbar = Object.assign(defaultAdsToolbar(), response.data.tenant_ads_toolbar)
                        }
                        if (response.data.tenant_ads_notification) {
                            this.form.tenant_ads_notification = Object.assign(defaultAdsNotification(), response.data.tenant_ads_notification)
                        }
                    } else {
                        this.$message.error(response.data.message)
                    }
                })
                .catch(error => {
                    console.log(error)
                })
        },
    },
}
</script>
<style>
.ads-sub__controls .el-input-number .el-input.el-input--small .el-input__inner {
    width: 118px;
    height: 32px !important;
}
.ads-sub__controls .el-input-number .el-input-number__decrease,
.ads-sub__controls .el-input-number .el-input-number__increase {
    right: 13px;
}
</style>
<style scoped>
.ads-config {
    --ads-primary: var(--primary, #3d6bf5);
    --ads-primary-soft: var(--accent-color, #e7edfc);
    --ads-text: var(--dark-color, #3a4658);
    --ads-muted: var(--muted, #8492a6);
    --ads-faint: #a3aec0;
    --ads-surface: #ffffff;
    --ads-field: var(--light-color, #f5f8ff);
    --ads-border: #e4e8ee;
    --ads-border-strong: #d3d9e2;
    --ads-green: var(--success, #00c666);
    --ads-amber: var(--warning, #ff8400);
    --ads-red: var(--danger, #ff0066);
    --ads-shadow-sm: 0 1px 2px rgba(24, 36, 51, .06);
    --ads-shadow-md: 0 4px 16px -4px rgba(24, 36, 51, .14);
    --ads-shadow-lg: 0 18px 44px -12px rgba(24, 36, 51, .30);
    --ads-r-sm: 6px;
    --ads-r: 8px;
    --ads-r-lg: 12px;

    color: var(--ads-text);
}

.ads-intro {
    max-width: 70ch;
    margin: 0;
    color: var(--ads-muted);
}

/* chips de resumen */
.ads-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
}

/* tarjetas */
.ads-stack {
    margin-top: 20px;
}
.ads-card + .ads-card {
    margin-top: 22px;
    padding-top: 22px;
    border-top: 1px solid var(--ads-border);
}
.ads-card__head {
    display: flex;
    align-items: center;
    gap: 14px;
}
.ads-card__icon {
    display: grid;
    flex: none;
    place-items: center;
    width: 38px;
    height: 38px;
    color: var(--ads-faint);
    background: var(--ads-field);
    border: 1px solid var(--ads-border);
    border-radius: 10px;
}
.ads-card.is-on .ads-card__icon {
    color: var(--ads-primary);
    background: var(--ads-primary-soft);
    border-color: transparent;
}
.ads-card__title {
    flex: 1;
    min-width: 0;
}
.ads-card__title h2 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: var(--ads-text);
}
.ads-card__title p {
    margin: -4px 0 0;
    font-size: 13px;
    color: var(--ads-muted);
}
.ads-switch-wrap {
    display: flex;
    flex: none;
    align-items: center;
    gap: 10px;
}
.ads-switch-state {
    min-width: 74px;
    font-size: 13px;
    text-align: right;
    color: var(--ads-muted);
}
.ads-card.is-on .ads-switch-state {
    font-weight: 500;
    color: var(--ads-text);
}

.ads-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 380px;
    margin-top: 14px;
    overflow: hidden;
    border: 1px solid var(--ads-border);
    border-radius: var(--ads-r-lg);
}
.ads-col-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 18px;
    background: var(--ads-surface);
}
.ads-col-preview {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 18px;
    background: var(--ads-field);
    border-left: 1px solid var(--ads-border);
}

/* campos */
.ads-counter {
    font-variant-numeric: tabular-nums;
    font-size: 11px;
}
.ads-counter.is-warn {
    color: var(--ads-amber);
}

.ads-hint {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    margin: 0;
    font-size: 12px;
    color: var(--ads-muted);
}
.ads-hint svg {
    flex: none;
    margin-top: 2px;
}
.ads-divider {
    height: 1px;
    background: var(--ads-border);
}
.ads-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.ads-group__label {
    font-size: 12px;
    font-weight: 600;
    color: var(--ads-muted);
}
.ads-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.ads-sub {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    background: var(--ads-field);
    border: 1px solid var(--ads-border);
    border-radius: var(--ads-r);
}
.ads-sub__txt strong {
    display: block;
    font-size: 13px;
    font-weight: 500;
}
.ads-sub__txt span {
    font-size: 12px;
    color: var(--ads-muted);
}
.ads-sub__controls {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 12px;
}
/* uploader */
.ads-drop {
    padding: 22px 16px;
    text-align: center;
}
.ads-drop__ico {
    margin-bottom: 8px;
    color: var(--ads-faint);
}
.ads-drop strong {
    display: block;
    font-size: 13px;
    font-weight: 500;
}
.ads-drop small {
    font-size: 12px;
    color: var(--ads-muted);
}
.ads-file {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    background: var(--ads-field);
    border: 1px solid var(--ads-border);
    border-radius: var(--ads-r-lg);
}
.ads-file__thumb {
    flex: none;
    width: 74px;
    height: 46px;
    object-fit: cover;
    border: 1px solid var(--ads-border);
    border-radius: var(--ads-r-sm);
}
.ads-file__meta {
    flex: 1;
    min-width: 0;
}
.ads-file__meta strong {
    display: block;
    overflow: hidden;
    font-size: 13px;
    font-weight: 500;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ads-file__meta span {
    font-size: 12px;
    color: var(--ads-muted);
}
.ads-icon-btn {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    color: var(--ads-muted);
    background: transparent;
    border: 1px solid transparent;
    border-radius: var(--ads-r-sm);
    cursor: pointer;
}
.ads-icon-btn:hover {
    color: var(--ads-text);
    background: var(--ads-surface);
    border-color: var(--ads-border);
}

/* colores */
.ads-color-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.ads-color {
    display: flex;
    flex: 1;
    align-items: center;
    gap: 9px;
    min-width: 150px;
    margin: 0;
    padding: 6px 10px 6px 6px;
    background: var(--ads-field);
    border: 1px solid var(--ads-border);
    border-radius: var(--ads-r);
    cursor: pointer;
}
/*
    theme.css/admin_styles.css aplican -webkit-appearance: none a input[type=color],
    asi que el marco nativo se pierde y hay que dibujarlo aqui.
*/
.ads-color input[type="color"] {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    flex: none;
    width: 30px;
    height: 30px;
    padding: 0;
    background: none;
    border: 1px solid var(--ads-border-strong);
    border-radius: var(--ads-r-sm);
    cursor: pointer;
}
.ads-color input[type="color"]::-webkit-color-swatch-wrapper {
    padding: 2px;
}
.ads-color input[type="color"]::-webkit-color-swatch {
    border: 0;
    border-radius: 3px;
}
.ads-color input[type="color"]::-moz-color-swatch {
    border: 0;
    border-radius: 3px;
}
.ads-color__txt {
    min-width: 0;
}
.ads-color__txt span {
    display: block;
    font-size: 11px;
    color: var(--ads-muted);
}
.ads-color__txt em {
    font-size: 13px;
    font-style: normal;
    color: var(--ads-text);
}
.ads-swatches {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.ads-sw {
    width: 24px;
    height: 24px;
    padding: 0;
    border: 1px solid rgba(0, 0, 0, .12);
    border-radius: 6px;
    cursor: pointer;
}
.ads-sw:hover {
    transform: scale(1.08);
}
.ads-contrast {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 8px 11px;
    font-size: 12px;
    color: var(--ads-muted);
    background: var(--ads-field);
    border: 1px solid var(--ads-border);
    border-radius: var(--ads-r);
}
.ads-contrast[data-level="pass"] {
    color: var(--ads-green);
    background: rgba(0, 198, 102, .10);
    border-color: transparent;
}
.ads-contrast[data-level="warn"] {
    color: var(--ads-amber);
    background: rgba(255, 132, 0, .10);
    border-color: transparent;
}
.ads-contrast[data-level="fail"] {
    color: var(--ads-red);
    background: rgba(255, 0, 102, .10);
    border-color: transparent;
}
.ads-contrast b {
    font-variant-numeric: tabular-nums;
}

/* selector de posición */
.ads-pos-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    grid-template-rows: repeat(2, 44px);
    gap: 6px;
    padding: 6px;
    background: var(--ads-field);
    border: 1px solid var(--ads-border);
    border-radius: var(--ads-r-lg);
}
.ads-pos {
    display: grid;
    padding: 6px;
    background: transparent;
    border: 1px solid transparent;
    border-radius: var(--ads-r-sm);
    cursor: pointer;
}
.ads-pos i {
    display: block;
    width: 16px;
    height: 7px;
    background: var(--ads-border-strong);
    border-radius: 3px;
}
.ads-pos:hover {
    background: var(--ads-surface);
}
.ads-pos[aria-pressed="true"] {
    background: var(--ads-surface);
    border-color: var(--ads-primary);
    box-shadow: var(--ads-shadow-sm);
}
.ads-pos[aria-pressed="true"] i {
    background: var(--ads-primary);
}
.ads-pos[data-v="top"] { align-items: start; }
.ads-pos[data-v="bottom"] { align-items: end; }
.ads-pos[data-h="left"] { justify-items: start; }
.ads-pos[data-h="right"] { justify-items: end; }

/* segmentado */
.ads-seg {
    display: flex;
    gap: 2px;
    padding: 3px;
    background: var(--ads-field);
    border: 1px solid var(--ads-border);
    border-radius: var(--ads-r);
}
.ads-seg button {
    flex: 1;
    padding: 6px 10px;
    font-size: 13px;
    color: var(--ads-muted);
    white-space: nowrap;
    background: transparent;
    border: 0;
    border-radius: var(--ads-r-sm);
    cursor: pointer;
}
.ads-seg button[aria-pressed="true"] {
    font-weight: 500;
    color: var(--ads-text);
    background: var(--ads-surface);
    box-shadow: var(--ads-shadow-sm);
}

/* vista previa */
.ads-preview__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.ads-preview__head > span {
    font-size: 12px;
    font-weight: 600;
    color: var(--ads-muted);
}
.ads-link-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 6px;
    font-size: 13px;
    color: var(--ads-primary);
    background: none;
    border: 0;
    border-radius: var(--ads-r-sm);
    cursor: pointer;
}
.ads-link-btn:hover:not(:disabled) {
    background: var(--ads-primary-soft);
}
.ads-link-btn:disabled {
    color: var(--ads-faint);
    cursor: not-allowed;
}
.ads-frame {
    overflow: hidden;
    background: var(--ads-surface);
    border: 1px solid var(--ads-border);
    border-radius: var(--ads-r-lg);
    box-shadow: var(--ads-shadow-sm);
}
.ads-frame__bar {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 10px;
    background: var(--ads-field);
    border-bottom: 1px solid var(--ads-border);
}
.ads-frame__bar i {
    width: 8px;
    height: 8px;
    background: var(--ads-border-strong);
    border-radius: 50%;
}
.ads-frame__bar .url {
    flex: 1;
    margin-left: 6px;
    padding: 2px 10px;
    overflow: hidden;
    font-size: 11px;
    color: var(--ads-faint);
    text-overflow: ellipsis;
    white-space: nowrap;
    background: var(--ads-surface);
    border-radius: 99px;
}
.ads-stage {
    position: relative;
    height: 236px;
    overflow: hidden;
    background: var(--ads-surface);
}
.ads-skeleton {
    display: flex;
    flex-direction: column;
    gap: 9px;
    padding: 14px;
    opacity: .5;
}
.ads-skeleton i {
    display: block;
    height: 9px;
    background: var(--ads-border);
    border-radius: 5px;
}
.ads-skeleton i:nth-child(1) { width: 45%; }
.ads-skeleton i:nth-child(2) { width: 88%; }
.ads-skeleton i:nth-child(3) { width: 78%; }
.ads-skeleton .blocks {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px;
    margin-top: 4px;
}
.ads-skeleton .blocks i {
    width: auto;
    height: 44px;
}

.pv-bar {
    position: absolute;
    top: 0;
    right: 0;
    left: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 14px;
    font-size: 13px;
    font-weight: 500;
}
.pv-bar .t {
    flex: 1;
    text-align: center;
}
.pv-bar .x {
    font-size: 15px;
    line-height: 1;
    opacity: .75;
}

.pv-modal {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 16px;
    background: rgba(15, 23, 42, .5);
}
.pv-modal .box {
    position: relative;
    width: 100%;
    max-width: 230px;
    overflow: hidden;
    background: var(--ads-surface);
    border-radius: var(--ads-r-lg);
    box-shadow: var(--ads-shadow-lg);
}
.pv-modal img {
    display: block;
    width: 100%;
}
.pv-modal__empty {
    padding: 34px 10px;
    font-size: 12px;
    text-align: center;
    color: var(--ads-faint);
}
.pv-modal .x {
    position: absolute;
    top: 6px;
    right: 6px;
    display: grid;
    place-items: center;
    width: 24px;
    height: 24px;
    font-size: 14px;
    color: #fff;
    background: rgba(15, 23, 42, .55);
    border-radius: 50%;
}
.pv-toast,
.ads-demo-toast {
    --tst-bg: var(--ads-surface);
    --tst-border: var(--ads-border);
    --tst-title: var(--ads-text);
    --tst-desc: var(--ads-muted);
    --tst-icon-bg: var(--ads-primary-soft);
    --tst-icon-fg: var(--ads-primary);
}
.pv-toast--dark,
.ads-demo-toast--dark {
    --tst-bg: #1b2431;
    --tst-border: rgba(255, 255, 255, .12);
    --tst-title: #f2f5f9;
    --tst-desc: #9aa8bd;
    --tst-icon-bg: rgba(255, 255, 255, .09);
    --tst-icon-fg: color-mix(in srgb, var(--ads-primary) 45%, #ffffff);
}
@media (prefers-color-scheme: dark) {
    .pv-toast--auto,
    .ads-demo-toast--auto {
        --tst-bg: #1b2431;
        --tst-border: rgba(255, 255, 255, .12);
        --tst-title: #f2f5f9;
        --tst-desc: #9aa8bd;
        --tst-icon-bg: rgba(255, 255, 255, .09);
        --tst-icon-fg: color-mix(in srgb, var(--ads-primary) 45%, #ffffff);
    }
}

.pv-toast {
    position: absolute;
    display: flex;
    gap: 10px;
    align-items: flex-start;
    width: 200px;
    padding: 11px 12px;
    color: var(--tst-title);
    background: var(--tst-bg);
    border: 1px solid var(--tst-border);
    border-radius: var(--ads-r-lg);
    box-shadow: var(--ads-shadow-md);
    transition: top .2s, left .2s, right .2s, bottom .2s;
}
.pv-toast--top-left, .pv-toast--top-right { top: 14px; }
.pv-toast--bottom-left, .pv-toast--bottom-right { bottom: 14px; }
.pv-toast--top-left, .pv-toast--bottom-left { left: 14px; }
.pv-toast--top-right, .pv-toast--bottom-right { right: 14px; }
.pv-toast .ic {
    display: grid;
    flex: none;
    place-items: center;
    width: 32px;
    height: 32px;
    font-size: 17px;
    color: var(--tst-icon-fg);
    background: var(--tst-icon-bg);
    border-radius: 9px;
}
.pv-toast .tx {
    min-width: 0;
}
.pv-toast .tx strong {
    display: block;
    font-size: 13px;
    line-height: 1.3;
}
.pv-toast .tx em {
    display: block;
    margin-top: 2px;
    font-size: 12px;
    font-style: normal;
    color: var(--tst-desc);
}

/* demos a pantalla completa */
.ads-demo-bar {
    position: fixed;
    top: 0;
    right: 0;
    left: 0;
    z-index: 3000;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    font-size: 14px;
    font-weight: 500;
    box-shadow: var(--ads-shadow-md);
    animation: adsSlideDown .25s ease;
}
.ads-demo-bar .t {
    flex: 1;
    text-align: center;
}
.ads-demo-bar button {
    font-size: 18px;
    line-height: 1;
    color: inherit;
    background: none;
    border: 0;
    opacity: .8;
    cursor: pointer;
}
@keyframes adsSlideDown {
    from { transform: translateY(-100%); }
    to { transform: translateY(0); }
}

.ads-demo-modal {
    position: fixed;
    inset: 0;
    z-index: 3000;
    display: grid;
    place-items: center;
    padding: 20px;
    background: rgba(15, 23, 42, .6);
    animation: adsPop .2s ease;
}
.ads-demo-modal .box {
    position: relative;
    max-width: min(720px, 100%);
    max-height: 100%;
    overflow: hidden;
    background: var(--ads-surface);
    border-radius: var(--ads-r-lg);
    box-shadow: var(--ads-shadow-lg);
}
.ads-demo-modal img {
    display: block;
    width: auto;
    height: auto;
    max-width: 100%;
    max-height: min(720px, calc(100vh - 40px));
}
.ads-demo-modal .x {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 30px;
    height: 30px;
    font-size: 16px;
    color: #fff;
    background: rgba(15, 23, 42, .6);
    border: 0;
    border-radius: 50%;
    cursor: pointer;
}

.ads-demo-toast {
    position: fixed;
    z-index: 3000;
    display: flex;
    gap: 12px;
    width: 320px;
    max-width: calc(100vw - 32px);
    padding: 14px 34px 14px 14px;
    color: var(--tst-title);
    background: var(--tst-bg);
    border: 1px solid var(--tst-border);
    border-radius: var(--ads-r-lg);
    box-shadow: var(--ads-shadow-lg);
    animation: adsPop .22s ease;
}
.ads-demo-toast--top-left, .ads-demo-toast--top-right { top: 20px; }
.ads-demo-toast--bottom-left, .ads-demo-toast--bottom-right { bottom: 20px; }
.ads-demo-toast--top-left, .ads-demo-toast--bottom-left { left: 20px; }
.ads-demo-toast--top-right, .ads-demo-toast--bottom-right { right: 20px; }
.ads-demo-toast .ic {
    display: grid;
    flex: none;
    place-items: center;
    width: 40px;
    height: 40px;
    font-size: 21px;
    color: var(--tst-icon-fg);
    background: var(--tst-icon-bg);
    border-radius: 11px;
}
.ads-demo-toast .tx strong {
    display: block;
    font-size: 14px;
}
.ads-demo-toast .tx em {
    display: block;
    margin-top: 3px;
    font-size: 13px;
    font-style: normal;
    color: var(--tst-desc);
}
.ads-demo-toast .x {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 16px;
    line-height: 1;
    color: var(--tst-desc);
    background: none;
    border: 0;
    cursor: pointer;
}
@keyframes adsPop {
    from { opacity: 0; transform: scale(.96); }
    to { opacity: 1; transform: scale(1); }
}

@media (max-width: 991.98px) {
    .ads-grid {
        grid-template-columns: 1fr;
    }
    .ads-col-preview {
        border-top: 1px solid var(--ads-border);
        border-left: 0;
    }
    .ads-row-2 {
        grid-template-columns: 1fr;
    }
    .ads-switch-state {
        display: none;
    }
}
@media (prefers-reduced-motion: reduce) {
    .ads-demo-bar,
    .ads-demo-modal,
    .ads-demo-toast {
        animation-duration: .01ms;
    }
}
</style>

<style>
/* El dragger de el-upload se estiliza fuera de scoped porque lo renderiza el propio componente. */
.ads-config .ads-drop-upload .el-upload,
.ads-config .ads-drop-upload .el-upload-dragger {
    width: 100%;
    height: auto;
}
.ads-config .ads-drop-upload .el-upload-dragger {
    background: var(--light-color, #f5f8ff);
    border: 1.5px dashed #d3d9e2;
    border-radius: 12px;
}
.ads-config .ads-drop-upload .el-upload-dragger:hover {
    border-color: var(--primary, #3d6bf5);
    background: var(--accent-color, #e7edfc);
}
</style>
