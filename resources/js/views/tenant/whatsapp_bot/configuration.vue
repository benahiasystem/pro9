<template>
    <div>
        <div class="page-header pe-0">
            <h2><a href="#"><i class="fas fa-cogs"></i></a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Configuración</span></li>
                <li><span class="text-muted">Configuración de WhatsApp</span></li>
            </ol>
        </div>

        <div class="card card-dashboard border tab-content-default row-new row-mx-0">
            <div class="card-body">
                <el-tabs v-model="activeName">
                    <el-tab-pane class="mb-3" name="qr_api">
                        <span slot="label"><h3 class="m-0 mt-2">Envío de comprobantes</h3></span>
                        <div class="mt-3">
                            <tenant-qr-api></tenant-qr-api>
                        </div>
                    </el-tab-pane>
                    <el-tab-pane class="mb-3" name="first">
                        <span slot="label"><h3 class="m-0 mt-2">Bot conversacional</h3></span>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="d-flex align-items-center">
                                    <label class="control-label me-3 mb-0">Bot activo</label>
                                    <el-switch
                                        v-model="form.whatsapp_bot_enabled"
                                        active-text="Sí"
                                        inactive-text="No"
                                        @change="onToggleBotEnabled">
                                    </el-switch>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Cuando está desactivado, el bot deja de procesar mensajes entrantes. La instancia de WhatsApp sigue conectada y el envío de comprobantes no se ve afectado.
                                </small>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <label class="control-label me-3">Usar el mismo número de QR Api</label>
                                <el-switch
                                    :value="form.evolution_use_qr_api_instance"
                                    active-text="Sí"
                                    inactive-text="No"
                                    @change="onToggleUseQrApi">
                                </el-switch>
                                <small v-if="form.evolution_use_qr_api_instance" class="text-muted d-block mt-2">
                                    Se usará la instancia <strong>{{ form.qr_api_instance || '(QR Api no conectado)' }}</strong>
                                    de QR Api. Asegúrate de conectarlo primero desde la pestaña "Envío de comprobantes".
                                </small>
                                <small v-else class="text-muted d-block mt-2">
                                    Conecta un número distinto al de QR Api escaneando un nuevo QR más abajo.
                                </small>
                            </div>
                        </div>
                        <hr>

                        <template v-if="!form.evolution_use_qr_api_instance">
                        <!-- Step 1: input de nombre de instancia -->
                        <div v-if="step === 'input'" class="row">
                            <div class="col-md-12 mt-3">
                                <p class="text-muted">
                                    Conecta tu WhatsApp escaneando un código QR. Asigna un nombre único para esta instancia
                                    (puedes usar letras, números, guiones y guión bajo).
                                </p>
                            </div>
                            <div class="col-md-6 mt-3 form-modern">
                                <label class="control-label">Nombre de la instancia</label>
                                <div class="form-group">
                                    <el-input
                                        v-model="instanceName"
                                        placeholder="ej. pro8-bot, miempresa-wa"
                                        @keyup.enter.native="startConnection">
                                    </el-input>
                                    <small class="text-muted">3 a 40 caracteres. Sin espacios ni símbolos especiales.</small>
                                </div>
                            </div>
                            <div class="col-md-12 mt-3 text-end">
                                <el-button
                                    :loading="loading"
                                    :disabled="!canStart"
                                    class="btn-primary"
                                    @click="startConnection">
                                    Conectar
                                </el-button>
                            </div>

                            <div class="col-md-12 mt-4">
                                <hr>
                                <div v-if="!linkMode" class="text-center">
                                    <el-button type="text" size="small" @click="linkMode = true">
                                        ¿Ya tienes un canal conectado en ChatBuho? Vincúlalo aquí
                                    </el-button>
                                </div>
                                <div v-else>
                                    <p class="text-muted small">
                                        Vincula un canal que ya está conectado y escaneado en ChatBuho, sin volver a
                                        escanear el QR.
                                    </p>
                                    <div class="row">
                                        <div class="col-md-12 form-modern">
                                            <label class="control-label">Nombre de canal/instancia</label>
                                            <el-input v-model="linkInstanceName" placeholder="ej. 51987654321"></el-input>
                                        </div>
                                        <div class="col-md-12 mt-3 form-modern">
                                            <label class="control-label">Número de WhatsApp conectado a ese canal</label>
                                            <div class="d-flex phone-input-group">
                                                <el-select v-model="linkCountryCode" class="phone-country-select">
                                                    <el-option
                                                        v-for="c in countryCodes"
                                                        :key="c.code"
                                                        :value="c.code"
                                                        :label="`${c.flag} +${c.code}`">
                                                    </el-option>
                                                </el-select>
                                                <el-input
                                                    v-model="linkLocalNumber"
                                                    placeholder="987654321"
                                                    @input="linkLocalNumber = linkLocalNumber.replace(/\D/g, '')">
                                                </el-input>
                                            </div>
                                        </div>
                                        <div class="col-md-12 mt-3 form-modern">
                                            <label class="control-label">Token de la instancia</label>
                                            <el-input v-model="linkToken" placeholder="ej. 26459C33-29BF-43F4-AE55-E02B23B74483" show-password></el-input>
                                            <small class="text-muted">
                                                Cópialo desde el Evolution Manager de esa instancia (no es el número, es
                                                el código que aparece oculto debajo del nombre del canal). Es necesario
                                                para confirmar que administras esa instancia.
                                            </small>
                                        </div>
                                    </div>
                                    <div class="mt-3 text-end">
                                        <el-button size="small" @click="linkMode = false">Cancelar</el-button>
                                        <el-button
                                            size="small"
                                            type="primary"
                                            :loading="loading_link"
                                            :disabled="!canLink"
                                            @click="linkExisting">
                                            Conectar con ChatBuho
                                        </el-button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step intermedio: instancia desconectada -->
                        <div v-else-if="step === 'disconnected'" class="row">
                            <div class="col-md-12 mt-3">
                                <el-alert type="warning" :closable="false" class="mb-3">
                                    <span slot="title">Instancia desconectada de WhatsApp</span>
                                    Tu instancia <strong>{{ form.evolution_instance }}</strong> está en estado
                                    <code>{{ lastState || 'desconocido' }}</code>. Vuelve a escanear el QR para reconectar,
                                    o renueva la instancia si reconectar no funciona.
                                </el-alert>
                            </div>
                            <div class="col-md-12 mt-2">
                                <div class="bot-action-row">
                                    <el-button size="small" :loading="loading_check" icon="el-icon-view" @click="checkStateManual">
                                        Comprobar estado
                                    </el-button>
                                    <el-button size="small" type="primary" :loading="loading" icon="el-icon-link" @click="startReconnect">
                                        Reconectar instancia
                                    </el-button>
                                    <el-button
                                        v-if="!form.instance_adopted"
                                        size="small" type="danger" plain :loading="loading_renew" icon="el-icon-refresh-right" @click="confirmRenew">
                                        Renovar instancia
                                    </el-button>
                                    <el-button size="small" type="warning" :loading="loading_disconnect" @click="confirmDisconnect">
                                        {{ form.instance_adopted ? 'Desvincular' : 'Conectar nuevo número' }}
                                    </el-button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: QR + polling -->
                        <div v-else-if="step === 'qr'" class="row">
                            <div class="col-md-12 mt-3 text-center">
                                <p class="text-muted">
                                    Escanea el código QR desde WhatsApp en tu teléfono:
                                    <strong>Configuración → Dispositivos vinculados → Vincular un dispositivo</strong>.
                                </p>
                                <div v-if="qrImage" class="my-3">
                                    <img :src="qrImage" alt="QR de WhatsApp" style="max-width: 320px; border: 1px solid #eee; padding: 8px; background: white;">
                                </div>
                                <div v-else class="my-3 py-5 text-muted">
                                    Generando código QR…
                                </div>
                                <p class="text-muted small">
                                    Esperando escaneo… <span v-if="lastState">({{ lastState }})</span>
                                </p>
                            </div>
                            <div class="col-md-12 mt-2 text-center">
                                <el-button @click="cancelReconnect">Volver</el-button>
                                <el-button :loading="loading" icon="el-icon-refresh" @click="refreshQr">
                                    Refrescar QR
                                </el-button>
                            </div>
                        </div>

                        <!-- Step 3: monitoreo persistente -->
                        <div v-else-if="step === 'connected'" class="row">
                            <div class="col-md-12 mt-3">
                                <el-alert type="success" :closable="false" class="mb-3">
                                    <span slot="title">WhatsApp conectado correctamente</span>
                                </el-alert>
                            </div>

                            <div class="col-md-6 mt-3 form-modern">
                                <label class="control-label">Instancia</label>
                                <div class="metric-value">{{ form.evolution_instance || '—' }}</div>
                            </div>
                            <div class="col-md-6 mt-3 form-modern">
                                <label class="control-label">Estado</label>
                                <div>
                                    <el-tag :type="lastState === 'open' ? 'success' : 'warning'" size="medium">
                                        {{ lastState || 'desconocido' }}
                                    </el-tag>
                                </div>
                            </div>

                            <div v-if="form.instance_token" class="col-md-12 mt-3 form-modern">
                                <label class="control-label">Token de la instancia</label>
                                <small class="text-muted d-block mb-1">
                                    Cópialo si quieres vincular esta misma instancia desde ChatBuho.
                                </small>
                                <div class="d-flex phone-input-group">
                                    <el-input
                                        :value="form.instance_token"
                                        :type="tokenVisible ? 'text' : 'password'"
                                        readonly>
                                        <template slot="suffix">
                                            <i
                                                class="el-icon-view"
                                                style="cursor: pointer; padding: 0 8px;"
                                                @click="tokenVisible = !tokenVisible">
                                            </i>
                                        </template>
                                    </el-input>
                                    <el-button icon="el-icon-document-copy" @click="copyToken(form.instance_token)"></el-button>
                                </div>
                            </div>

                            <div class="col-md-12 mt-4">
                                <p class="text-muted small">
                                    El bot está listo para recibir mensajes. Habilita el bot por usuario desde
                                    <strong>Usuarios</strong> → editar → tab "Datos personales".
                                </p>
                            </div>

                            <div class="col-md-12 mt-2">
                                <div class="bot-action-row">
                                    <el-button size="small" :loading="loading_check" icon="el-icon-view" @click="checkStateManual">
                                        Comprobar estado
                                    </el-button>
                                    <el-button size="small" :loading="loading_restart" icon="el-icon-refresh" @click="restart">
                                        Reiniciar conexión
                                    </el-button>
                                    <el-button
                                        v-if="!form.instance_adopted"
                                        size="small" type="danger" plain :loading="loading_renew" icon="el-icon-refresh-right" @click="confirmRenew">
                                        Renovar instancia
                                    </el-button>
                                    <el-button size="small" type="warning" :loading="loading_disconnect" @click="confirmDisconnect">
                                        {{ form.instance_adopted ? 'Desvincular' : 'Conectar nuevo número' }}
                                    </el-button>
                                </div>
                            </div>
                        </div>
                        </template>
                    </el-tab-pane>
                    <el-tab-pane class="mb-3" name="commands">
                        <span slot="label"><h3 class="m-0 mt-2">Comandos del bot</h3></span>
                        <div class="mt-3">
                            <tenant-whatsapp-bot-commands :configuration="configuration"></tenant-whatsapp-bot-commands>
                            <div class="mt-4 pt-3 border-top text-end">
                                <el-button type="info" plain icon="el-icon-document" @click="openBotManual">
                                    Abrir manual del bot
                                </el-button>
                            </div>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>
    </div>
</template>

<script>
const POLL_INTERVAL_MS = 5000;

export default {
    props: ['configuration'],
    data() {
        return {
            activeName: 'qr_api',
            form: {
                evolution_instance: this.configuration?.evolution_instance || null,
                evolution_connection_state: this.configuration?.evolution_connection_state || 'disconnected',
                connected_phone: this.configuration?.evolution_connected_phone || null,
                profile_name: this.configuration?.evolution_profile_name || null,
                whatsapp_bot_enabled: this.configuration?.whatsapp_bot_enabled ?? true,
                instance_adopted: this.configuration?.evolution_instance_adopted || false,
                instance_token: this.configuration?.evolution_instance_token || null,
                evolution_use_qr_api_instance: this.configuration?.evolution_use_qr_api_instance || false,
                qr_api_instance: this.configuration?.qr_api_instance || null,
                qr_api_instance_adopted: this.configuration?.qr_api_instance_adopted || false,
                qr_api_connected_phone: this.configuration?.qr_api_connected_phone || null,
                qr_api_profile_name: this.configuration?.qr_api_profile_name || null,
            },
            instanceName: '',
            qrImage: null,
            reconnecting: false,
            lastState: this.configuration?.evolution_connection_state || null,
            loading: false,
            loading_restart: false,
            loading_disconnect: false,
            loading_check: false,
            loading_renew: false,
            loading_link: false,
            tokenVisible: false,
            linkMode: false,
            linkInstanceName: '',
            linkCountryCode: '51',
            linkLocalNumber: '',
            linkToken: '',
            countryCodes: [
                { code: '51', flag: '🇵🇪', name: 'Perú' },
                { code: '57', flag: '🇨🇴', name: 'Colombia' },
                { code: '52', flag: '🇲🇽', name: 'México' },
                { code: '56', flag: '🇨🇱', name: 'Chile' },
                { code: '593', flag: '🇪🇨', name: 'Ecuador' },
                { code: '591', flag: '🇧🇴', name: 'Bolivia' },
                { code: '54', flag: '🇦🇷', name: 'Argentina' },
                { code: '58', flag: '🇻🇪', name: 'Venezuela' },
                { code: '507', flag: '🇵🇦', name: 'Panamá' },
                { code: '595', flag: '🇵🇾', name: 'Paraguay' },
                { code: '598', flag: '🇺🇾', name: 'Uruguay' },
                { code: '506', flag: '🇨🇷', name: 'Costa Rica' },
                { code: '1', flag: '🇩🇴', name: 'Rep. Dominicana' },
                { code: '34', flag: '🇪🇸', name: 'España' },
            ],
            pollTimer: null,
        };
    },
    computed: {
        step() {
            if (!this.form.evolution_instance) return 'input';
            if (this.form.evolution_connection_state === 'open') return 'connected';
            if (this.reconnecting) return 'qr';
            return 'disconnected';
        },
        canStart() {
            return /^[A-Za-z0-9_\-]{3,40}$/.test(this.instanceName);
        },
        linkFullNumber() {
            return `${this.linkCountryCode}${this.linkLocalNumber}`;
        },
        canLink() {
            return /^[A-Za-z0-9_\-]{3,40}$/.test(this.linkInstanceName)
                && /^\d{6,13}$/.test(this.linkLocalNumber)
                && this.linkToken.trim().length > 0;
        },
        connectedPhoneFormatted() {
            const p = this.form.connected_phone;
            return p ? `+${p}` : '—';
        },
    },
    watch: {
        activeName(newVal) {
            if (newVal === 'first') this.refreshQrApiInfo();
        },
    },
    mounted() {
        this.refreshQrApiInfo();
        if (this.step === 'qr') {
            this.refreshQr();
            this.startPolling();
        } else if (this.step === 'connected') {
            this.checkState();
            this.startPolling(15000);
        }
    },
    beforeDestroy() {
        this.stopPolling();
    },
    methods: {
        openBotManual() {
            this.$eventHub.$emit('openHelpDrawer', 'whatsapp-bot/manual');
        },
        async copyToken(token) {
            try {
                await navigator.clipboard.writeText(token);
                this.$message({ message: 'Token copiado', type: 'success' });
            } catch (e) {
                this.$message({ message: 'No se pudo copiar el token', type: 'error' });
            }
        },
        // El widget de QR Api es un componente hermano independiente — si se
        // conecta/desconecta ahí después de cargar esta página, este formulario
        // se queda con el dato viejo. Se refresca al montar para no mostrar
        // "QR Api no conectado" estando conectado.
        async refreshQrApiInfo() {
            try {
                const { data } = await this.$http.get('/qrapi/configuration');
                this.form.qr_api_instance = data.qr_api_instance || null;
                this.form.qr_api_instance_adopted = !!data.qr_api_instance_adopted;
                this.form.qr_api_connected_phone = data.qr_api_connected_phone || null;
                this.form.qr_api_profile_name = data.qr_api_profile_name || null;
            } catch (e) {
                // silencioso — no es crítico para el resto del formulario
            }
        },
        async onToggleBotEnabled(value) {
            try {
                const { data } = await this.$http.post('/whatsapp-bot/toggle-enabled', { enabled: value });
                this.form.whatsapp_bot_enabled = data.enabled;
                this.$message({ message: data.message, type: data.enabled ? 'success' : 'warning' });
            } catch (e) {
                this.form.whatsapp_bot_enabled = !value;
                this.$message({ message: 'No se pudo cambiar el estado del bot', type: 'error' });
            }
        },
        async onToggleUseQrApi(value) {
            // Si se activa el toggle y ya hay un número conectado, confirmar la desconexión
            if (value && this.form.evolution_instance) {
                const message = this.form.instance_adopted
                    ? `Tu número actual (${this.form.evolution_instance}) se desvinculará del bot, pero seguirá conectado en ` +
                      `ChatBuho (no se elimina en Evolution). ¿Continuar?`
                    : `Tu número actual (${this.form.evolution_instance}) se desconectará y se eliminará. ` +
                      `Si quieres volver a usarlo, tendrás que escanear un QR nuevo. ¿Continuar?`;
                try {
                    await this.$confirm(
                        message,
                        'Usar el número de QR Api',
                        { confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar', type: 'warning' }
                    );
                } catch {
                    return;
                }
            }

            if (value && !this.form.qr_api_instance) {
                this.$message({
                    message: 'QR Api no tiene un número conectado. Conéctalo primero desde su sección.',
                    type: 'warning',
                });
            }

            try {
                const { data } = await this.$http.post('/whatsapp-bot/toggle-use-qr-api-instance', { enabled: value });
                this.$message({ message: data.message || 'Configuración actualizada', type: 'success' });
            } catch (e) {
                this.$message({ message: 'Error al guardar', type: 'error' });
                return;
            }

            this.form.evolution_use_qr_api_instance = value;
            if (value) {
                this.form.evolution_instance = null;
                this.form.instance_adopted = false;
                this.form.connected_phone = null;
                this.form.profile_name = null;
                this.form.evolution_connection_state = 'disconnected';
                this.lastState = null;
                this.qrImage = null;
                this.stopPolling();
            }
        },
        async startConnection() {
            this.loading = true;
            try {
                const { data } = await this.$http.post('/whatsapp-bot/connect', {
                    instance_name: this.instanceName,
                });
                if (data.success) {
                    this.form.evolution_instance = data.instance_name;
                    this.form.evolution_connection_state = 'connecting';
                    this.form.instance_adopted = false;
                    this.reconnecting = true;
                    await this.$nextTick();
                    this.refreshQr();
                    this.startPolling();
                } else {
                    this.$message({ message: data.message || 'No se pudo iniciar', type: 'error' });
                }
            } catch (e) {
                this.$message({ message: 'Error al iniciar la conexión', type: 'error' });
            } finally {
                this.loading = false;
            }
        },
        async linkExisting() {
            this.loading_link = true;
            try {
                const { data } = await this.$http.post('/whatsapp-bot/link-existing', {
                    instance_name: this.linkInstanceName,
                    phone_number: this.linkFullNumber,
                    token: this.linkToken,
                });
                if (data.success) {
                    this.form.evolution_instance = data.instance_name;
                    this.form.evolution_connection_state = 'open';
                    this.form.instance_adopted = true;
                    this.lastState = 'open';
                    this.linkMode = false;
                    this.linkInstanceName = '';
                    this.linkLocalNumber = '';
                    this.linkToken = '';
                    this.$message({ message: data.message, type: 'success' });
                    await this.checkState();
                    this.startPolling(15000);
                } else {
                    this.$message({ message: data.message || 'No se pudo vincular la instancia', type: 'error' });
                }
            } catch (e) {
                this.$message({ message: 'Error al vincular la instancia', type: 'error' });
            } finally {
                this.loading_link = false;
            }
        },
        async refreshQr(attempts = 4) {
            this.loading = true;
            try {
                for (let i = 0; i < attempts; i++) {
                    const { data } = await this.$http.get('/whatsapp-bot/qr');
                    if (data.success && data.qr) {
                        this.qrImage = data.qr;
                        return;
                    }
                    if (i < attempts - 1) {
                        await new Promise(r => setTimeout(r, 1500));
                    }
                }
                this.$message({ message: 'Evolution no devolvió un QR todavía. Pulsa "Refrescar QR" en unos segundos.', type: 'warning' });
            } catch (e) {
                this.$message({ message: 'Error al refrescar QR', type: 'error' });
            } finally {
                this.loading = false;
            }
        },
        startPolling(interval) {
            this.stopPolling();
            this.pollTimer = setInterval(() => this.checkState(), interval || POLL_INTERVAL_MS);
        },
        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },
        async checkState() {
            try {
                const { data } = await this.$http.get('/whatsapp-bot/state');
                if (!data.success) return;
                this.lastState = data.state;
                if (data.connected_phone) this.form.connected_phone = data.connected_phone;
                if (data.profile_name) this.form.profile_name = data.profile_name;
                if (data.instance_adopted !== undefined) this.form.instance_adopted = data.instance_adopted;
                this.form.instance_token = data.instance_token || null;
                if (data.connected && this.form.evolution_connection_state !== 'open') {
                    this.form.evolution_connection_state = 'open';
                    this.reconnecting = false;
                    this.qrImage = null;
                    this.stopPolling();
                    this.startPolling(15000);
                    this.$message({ message: 'WhatsApp conectado correctamente', type: 'success' });
                } else if (!data.connected && this.form.evolution_connection_state === 'open') {
                    this.form.evolution_connection_state = data.state || 'close';
                }
            } catch (e) {
                // silent retry
            }
        },
        async startReconnect() {
            this.reconnecting = true;
            this.qrImage = null;
            await this.$nextTick();
            await this.refreshQr();
            this.startPolling();
        },
        cancelReconnect() {
            this.reconnecting = false;
            this.qrImage = null;
            this.stopPolling();
        },
        async checkStateManual() {
            this.loading_check = true;
            try {
                const { data } = await this.$http.get('/whatsapp-bot/state');
                if (data.success) {
                    this.lastState = data.state;
                    if (data.connected_phone) this.form.connected_phone = data.connected_phone;
                    if (data.profile_name) this.form.profile_name = data.profile_name;
                    if (data.instance_adopted !== undefined) this.form.instance_adopted = data.instance_adopted;
                    this.form.instance_token = data.instance_token || null;
                    this.$message({
                        message: data.connected ? `Conectado (${data.state})` : `Estado: ${data.state}`,
                        type: data.connected ? 'success' : 'warning',
                    });
                } else {
                    this.$message({ message: data.message || 'No se pudo consultar estado', type: 'error' });
                }
            } catch (e) {
                this.$message({ message: 'Error al consultar estado', type: 'error' });
            } finally {
                this.loading_check = false;
            }
        },
        confirmRenew() {
            this.$confirm(
                'Renovar la instancia elimina la actual y crea una nueva con el mismo nombre y configuración. Tendrás que volver a escanear el QR. ¿Continuar?',
                'Renovar instancia',
                { confirmButtonText: 'Sí, renovar', cancelButtonText: 'Cancelar', type: 'warning' }
            ).then(() => this.renew()).catch(() => {});
        },
        async renew() {
            this.loading_renew = true;
            try {
                const { data } = await this.$http.post('/whatsapp-bot/renew');
                if (data.success) {
                    this.form.evolution_connection_state = 'connecting';
                    this.form.connected_phone = null;
                    this.form.profile_name = null;
                    this.form.instance_adopted = false;
                    this.qrImage = null;
                    this.lastState = 'connecting';
                    this.reconnecting = true;
                    this.$message({ message: data.message, type: 'success' });
                    await this.$nextTick();
                    this.refreshQr();
                    this.startPolling();
                } else {
                    this.$message({ message: data.message || 'No se pudo renovar', type: 'error' });
                }
            } catch (e) {
                this.$message({ message: 'Error al renovar instancia', type: 'error' });
            } finally {
                this.loading_renew = false;
            }
        },
        async restart() {
            this.loading_restart = true;
            try {
                const { data } = await this.$http.post('/whatsapp-bot/restart');
                this.$message({
                    message: data.message || 'Reinicio solicitado',
                    type: data.success ? 'success' : 'warning',
                });
            } catch (e) {
                this.$message({ message: 'Error al reiniciar', type: 'error' });
            } finally {
                this.loading_restart = false;
            }
        },
        confirmDisconnect() {
            const message = this.form.instance_adopted
                ? 'Esto desvinculará el bot de esta instancia, pero seguirá conectada en ChatBuho (no se elimina en Evolution). ¿Continuar?'
                : 'Esto desconectará el WhatsApp actual y eliminará la instancia en Evolution. Tendrás que escanear un nuevo QR para reconectar. ¿Continuar?';
            const title = this.form.instance_adopted ? 'Desvincular' : 'Conectar nuevo número';
            this.$confirm(
                message,
                title,
                { confirmButtonText: this.form.instance_adopted ? 'Sí, desvincular' : 'Sí, desconectar', cancelButtonText: 'Cancelar', type: 'warning' }
            ).then(() => this.disconnect()).catch(() => {});
        },
        async disconnect() {
            this.loading_disconnect = true;
            try {
                const { data } = await this.$http.post('/whatsapp-bot/disconnect');
                if (data.success) {
                    this.form.evolution_instance = null;
                    this.form.evolution_connection_state = 'disconnected';
                    this.form.connected_phone = null;
                    this.form.profile_name = null;
                    this.form.instance_adopted = false;
                    this.instanceName = '';
                    this.qrImage = null;
                    this.lastState = null;
                    this.linkMode = false;
                    this.linkInstanceName = '';
                    this.linkLocalNumber = '';
                    this.linkToken = '';
                    this.stopPolling();
                    this.$message({ message: data.message, type: 'success' });
                } else {
                    this.$message({ message: data.message || 'No se pudo desconectar', type: 'error' });
                }
            } catch (e) {
                this.$message({ message: 'Error al desconectar', type: 'error' });
            } finally {
                this.loading_disconnect = false;
            }
        },
    },
};
</script>

<style scoped>
.metric-value {
    font-size: 1.05rem;
    color: #303133;
}
.bot-action-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
    align-items: center;
}
.bot-action-row > .el-button {
    margin-left: 0 !important;
}
.phone-input-group {
    gap: 8px;
}
.phone-country-select {
    flex: 0 0 130px;
}
</style>
