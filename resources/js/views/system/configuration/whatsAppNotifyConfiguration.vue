<template>
    <div class="card">
        <div class="card-header bg-info bg-info-customer-admin">
            <h3 class="my-0">Número de WhatsApp (notificaciones)</h3>
        </div>
        <div class="card-body">
            <small class="text-muted d-block mb-3">
                Conecta un número de WhatsApp para el superadmin escaneando un código QR. Se usa
                únicamente para <strong>enviar</strong> notificaciones (recordatorios de pago a los
                tenants) — no responde mensajes.
            </small>

            <!-- Step 1: input de nombre de instancia -->
            <div v-if="step === 'input'" class="row">
                <div class="col-md-7 form-field">
                    <label class="control-label">Nombre de la instancia</label>
                    <el-input
                        v-model="instanceName"
                        placeholder="ej. superadmin-notificaciones"
                        @keyup.enter.native="startConnection">
                    </el-input>
                    <small class="text-muted">3 a 40 caracteres. Sin espacios ni símbolos especiales.</small>
                </div>
                <div class="col-md-5 d-flex align-items-center">
                    <el-button
                        :loading="loading"
                        :disabled="!canStart"
                        type="primary"
                        @click="startConnection">
                        Conectar
                    </el-button>
                </div>
            </div>

            <!-- Step intermedio: instancia desconectada -->
            <div v-else-if="step === 'disconnected'">
                <el-alert type="warning" :closable="false" class="mb-3">
                    <span slot="title">Instancia desconectada</span>
                    Tu instancia <strong>{{ form.instance }}</strong> está en estado
                    <code>{{ lastState || 'desconocido' }}</code>. Vuelve a escanear el QR para reconectar,
                    o renueva la instancia si reconectar no funciona.
                </el-alert>
                <div class="action-row">
                    <el-button size="small" :loading="loading_check" icon="el-icon-view" @click="checkStateManual">
                        Comprobar estado
                    </el-button>
                    <el-button size="small" type="primary" :loading="loading" icon="el-icon-link" @click="startReconnect">
                        Reconectar
                    </el-button>
                    <el-button size="small" type="danger" plain :loading="loading_renew" icon="el-icon-refresh-right" @click="confirmRenew">
                        Renovar instancia
                    </el-button>
                    <el-button size="small" type="warning" :loading="loading_disconnect" @click="confirmDisconnect">
                        Desconectar
                    </el-button>
                </div>
            </div>

            <!-- Step 2: QR + polling -->
            <div v-else-if="step === 'qr'" class="text-center">
                <p class="text-muted">
                    Escanea el código QR desde WhatsApp: <strong>Dispositivos vinculados → Vincular un dispositivo</strong>.
                </p>
                <div v-if="qrImage" class="my-3">
                    <img :src="qrImage" alt="QR de WhatsApp" style="max-width: 280px; border: 1px solid #eee; padding: 8px; background: white;">
                </div>
                <div v-else class="my-3 py-4 text-muted">Generando código QR…</div>
                <p class="text-muted small">Esperando escaneo… <span v-if="lastState">({{ lastState }})</span></p>
                <div>
                    <el-button size="small" @click="cancelReconnect">Volver</el-button>
                    <el-button size="small" :loading="loading" icon="el-icon-refresh" @click="refreshQr">
                        Refrescar QR
                    </el-button>
                </div>
            </div>

            <!-- Step 3: conectado -->
            <div v-else-if="step === 'connected'">
                <el-alert type="success" :closable="false" class="mb-3">
                    <span slot="title">WhatsApp conectado correctamente</span>
                </el-alert>

                <div class="row">
                    <div class="col-md-6">
                        <div class="field-box">
                            <label class="control-label">Número conectado</label>
                            <div class="metric-value">{{ connectedPhoneFormatted }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="field-box">
                            <label class="control-label">Estado</label>
                            <div>
                                <el-tag :type="lastState === 'open' ? 'success' : 'warning'" size="medium">
                                    {{ lastState || 'desconocido' }}
                                </el-tag>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="d-flex align-items-center">
                            <label class="control-label me-3 mb-0">Usar este número para notificaciones</label>
                            <el-switch v-model="form.enabled" @change="onToggleEnabled"></el-switch>
                        </div>
                        <small class="text-muted d-block mt-1">
                            Si lo desactivas, las notificaciones se enviarán solo por email hasta que lo vuelvas a activar.
                        </small>
                    </div>
                </div>

                <div class="action-row mt-3">
                    <el-button size="small" type="primary" icon="el-icon-position" @click="openSendDialog">
                        Enviar mensaje
                    </el-button>
                    <el-button size="small" :loading="loading_check" icon="el-icon-view" @click="checkStateManual">
                        Comprobar estado
                    </el-button>
                    <el-button size="small" :loading="loading_restart" icon="el-icon-refresh" @click="restart">
                        Reiniciar conexión
                    </el-button>
                    <el-button size="small" type="danger" plain :loading="loading_renew" icon="el-icon-refresh-right" @click="confirmRenew">
                        Renovar instancia
                    </el-button>
                    <el-button size="small" type="warning" :loading="loading_disconnect" @click="confirmDisconnect">
                        Desconectar
                    </el-button>
                </div>

                <el-dialog title="Enviar mensaje" :visible.sync="sendDialogVisible" width="480px" @close="resetSendForm">
                    <div class="form-field">
                        <label class="control-label">Número de destino</label>
                        <div class="d-flex phone-input-group">
                            <el-select v-model="sendForm.countryCode" class="phone-country-select">
                                <el-option
                                    v-for="c in countryCodes"
                                    :key="c.code"
                                    :value="c.code"
                                    :label="`${c.flag} +${c.code}`">
                                </el-option>
                            </el-select>
                            <el-input
                                v-model="sendForm.localNumber"
                                placeholder="987654321"
                                @input="sendForm.localNumber = sendForm.localNumber.replace(/\D/g, '')">
                            </el-input>
                        </div>
                    </div>

                    <div class="form-field">
                        <label class="control-label">Mensaje</label>
                        <el-input
                            v-model="sendForm.message"
                            type="textarea"
                            :rows="4"
                            placeholder="Escribe el mensaje (opcional si adjuntas un PDF)">
                        </el-input>
                    </div>

                    <div class="form-field">
                        <label class="control-label">Adjuntar PDF (opcional)</label>
                        <div v-if="!sendForm.file" class="pdf-upload">
                            <input ref="pdfInput" type="file" accept="application/pdf" @change="onPdfSelected">
                        </div>
                        <div v-else class="pdf-selected">
                            <i class="el-icon-document"></i>
                            <span>{{ sendForm.filename }}</span>
                            <el-button type="text" icon="el-icon-close" @click="removePdf"></el-button>
                        </div>
                    </div>

                    <span slot="footer">
                        <el-button @click="sendDialogVisible = false">Cancelar</el-button>
                        <el-button type="primary" :loading="loading_send" :disabled="!canSendMessage" @click="sendMessage">
                            Enviar
                        </el-button>
                    </span>
                </el-dialog>

                <hr class="my-4">

                <h4 class="mb-2">API para servicios externos</h4>
                <small class="text-muted d-block mb-3">
                    Cualquier servicio externo puede mandar mensajes por este número autenticándose con
                    el token de abajo (header <code>Authorization: Bearer &lt;token&gt;</code>).
                </small>

                <div class="form-field mb-3">
                    <label class="control-label">Token de API</label>
                    <div class="d-flex phone-input-group">
                        <el-input
                            :value="form.api_token"
                            :type="tokenVisible ? 'text' : 'password'"
                            readonly>
                            <template slot="suffix">
                                <i class="el-icon-view" style="cursor: pointer; padding: 0 8px;" @click="tokenVisible = !tokenVisible"></i>
                            </template>
                        </el-input>
                        <el-button icon="el-icon-document-copy" @click="copyText(form.api_token)"></el-button>
                        <el-button type="warning" plain :loading="loading_regenerate" @click="confirmRegenerateToken">
                            Regenerar
                        </el-button>
                    </div>
                </div>

                <div class="examples-toggle" @click="showExamples = !showExamples">
                    <i :class="showExamples ? 'el-icon-arrow-down' : 'el-icon-arrow-right'"></i>
                    Ejemplos de payload
                </div>

                <div v-if="showExamples" class="examples-panel">
                    <el-tabs v-model="activeExample">
                        <el-tab-pane label="Enviar texto" name="text"></el-tab-pane>
                        <el-tab-pane label="Enviar media" name="media"></el-tab-pane>
                        <el-tab-pane label="Enviar PDF" name="pdf"></el-tab-pane>
                    </el-tabs>

                    <div class="code-block-group">
                        <div class="code-block">
                            <div class="code-block-label">
                                <span>URL</span>
                                <i class="el-icon-document-copy copy-icon" @click="copyText(currentExample.url)"></i>
                            </div>
                            <pre>{{ currentExample.url }}</pre>
                        </div>

                        <div class="code-block">
                            <div class="code-block-label"><span>Method</span></div>
                            <pre>{{ currentExample.method }}</pre>
                        </div>

                        <div class="code-block">
                            <div class="code-block-label"><span>Headers</span></div>
                            <pre>{{ prettyJson(currentExample.headers) }}</pre>
                        </div>

                        <div class="code-block">
                            <div class="code-block-label">
                                <span>Body</span>
                                <i class="el-icon-document-copy copy-icon" @click="copyText(prettyJson(currentExample.body))"></i>
                            </div>
                            <pre>{{ prettyJson(currentExample.body) }}</pre>
                        </div>

                        <div class="code-block">
                            <div class="code-block-label"><span>Response</span></div>
                            <pre>{{ prettyJson(currentExample.response) }}</pre>
                        </div>
                    </div>
                </div>
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
            form: {
                instance: this.configuration?.notify_wa_instance || null,
                connection_state: this.configuration?.notify_wa_connection_state || 'disconnected',
                connected_phone: this.configuration?.notify_wa_connected_phone || null,
                profile_name: this.configuration?.notify_wa_profile_name || null,
                enabled: this.configuration?.notify_wa_enabled ?? false,
                api_token: this.configuration?.notify_wa_api_token || null,
            },
            instanceName: '',
            qrImage: null,
            reconnecting: false,
            lastState: this.configuration?.notify_wa_connection_state || null,
            loading: false,
            loading_restart: false,
            loading_disconnect: false,
            loading_check: false,
            loading_renew: false,
            loading_regenerate: false,
            loading_send: false,
            tokenVisible: false,
            showExamples: false,
            activeExample: 'text',
            sendDialogVisible: false,
            sendForm: {
                countryCode: '51',
                localNumber: '',
                message: '',
                file: null,
                filename: null,
            },
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
            if (!this.form.instance) return 'input';
            if (this.form.connection_state === 'open') return 'connected';
            if (this.reconnecting) return 'qr';
            return 'disconnected';
        },
        canStart() {
            return /^[A-Za-z0-9_\-]{3,40}$/.test(this.instanceName);
        },
        connectedPhoneFormatted() {
            return this.form.connected_phone ? `+${this.form.connected_phone}` : '—';
        },
        apiBaseUrl() {
            return window.location.origin;
        },
        maskedApiToken() {
            const t = this.form.api_token;
            if (!t) return '<token>';
            if (this.tokenVisible) return t;
            if (t.length <= 8) return '•'.repeat(t.length);
            return t.slice(0, 4) + '•'.repeat(Math.max(t.length - 8, 4)) + t.slice(-4);
        },
        textExample() {
            return {
                url: `${this.apiBaseUrl}/api/whatsapp-notify/text`,
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.maskedApiToken}`,
                },
                body: {
                    number: '51999999999',
                    message: 'Hola, este es un mensaje de prueba',
                },
                response: {
                    success: true,
                    data: {
                        key: { remoteJid: '51999999999@s.whatsapp.net', fromMe: true, id: '3EB0C767D26A1D712F1A' },
                        message: { conversation: 'Hola, este es un mensaje de prueba' },
                        messageTimestamp: '1733950893',
                        status: 'PENDING',
                    },
                },
            };
        },
        mediaExample() {
            return {
                url: `${this.apiBaseUrl}/api/whatsapp-notify/media`,
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.maskedApiToken}`,
                },
                body: {
                    number: '51999999999',
                    media: 'https://midominio.com/imagenes/promo.jpg',
                    media_type: 'image',
                    caption: 'Mira nuestra promoción de este mes',
                },
                response: {
                    success: true,
                    data: {
                        key: { remoteJid: '51999999999@s.whatsapp.net', fromMe: true, id: '3EB0C767D26A1D712F1B' },
                        messageTimestamp: '1733950893',
                        status: 'PENDING',
                    },
                },
            };
        },
        pdfExample() {
            return {
                url: `${this.apiBaseUrl}/api/whatsapp-notify/pdf`,
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.maskedApiToken}`,
                },
                body: {
                    number: '51999999999',
                    file: 'JVBERi0xLjQKJcOkw7zDtsO...(base64 del PDF)',
                    filename: 'factura-001.pdf',
                    message: 'Aquí tienes tu comprobante',
                },
                response: {
                    success: true,
                    data: {
                        key: { remoteJid: '51999999999@s.whatsapp.net', fromMe: true, id: '3EB0C767D26A1D712F1C' },
                        messageTimestamp: '1733950893',
                        status: 'PENDING',
                    },
                },
            };
        },
        currentExample() {
            if (this.activeExample === 'media') return this.mediaExample;
            if (this.activeExample === 'pdf') return this.pdfExample;
            return this.textExample;
        },
        canSendMessage() {
            const validNumber = /^\d{6,13}$/.test(this.sendForm.localNumber);
            const hasContent = !!this.sendForm.message.trim() || !!this.sendForm.file;
            return validNumber && hasContent;
        },
    },
    mounted() {
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
        async onToggleEnabled(value) {
            try {
                const { data } = await this.$http.post('/configurations/whatsapp-notify/toggle-enabled', { enabled: value });
                this.form.enabled = data.enabled;
                this.$message({ message: data.message, type: data.enabled ? 'success' : 'warning' });
            } catch (e) {
                this.form.enabled = !value;
                this.$message({ message: 'No se pudo cambiar el estado', type: 'error' });
            }
        },
        async startConnection() {
            this.loading = true;
            try {
                const { data } = await this.$http.post('/configurations/whatsapp-notify/connect', {
                    instance_name: this.instanceName,
                });
                if (data.success) {
                    this.form.instance = data.instance_name;
                    this.form.connection_state = 'connecting';
                    this.form.enabled = true;
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
        async refreshQr(attempts = 4) {
            this.loading = true;
            try {
                for (let i = 0; i < attempts; i++) {
                    const { data } = await this.$http.get('/configurations/whatsapp-notify/qr');
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
                const { data } = await this.$http.get('/configurations/whatsapp-notify/state');
                if (!data.success) return;
                this.lastState = data.state;
                if (data.connected_phone) this.form.connected_phone = data.connected_phone;
                if (data.profile_name) this.form.profile_name = data.profile_name;
                if (data.api_token) this.form.api_token = data.api_token;
                if (data.connected && this.form.connection_state !== 'open') {
                    this.form.connection_state = 'open';
                    this.reconnecting = false;
                    this.qrImage = null;
                    this.stopPolling();
                    this.startPolling(15000);
                    this.$message({ message: 'WhatsApp conectado correctamente', type: 'success' });
                } else if (!data.connected && this.form.connection_state === 'open') {
                    this.form.connection_state = data.state || 'close';
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
                const { data } = await this.$http.get('/configurations/whatsapp-notify/state');
                if (data.success) {
                    this.lastState = data.state;
                    if (data.connected_phone) this.form.connected_phone = data.connected_phone;
                    if (data.profile_name) this.form.profile_name = data.profile_name;
                    if (data.api_token) this.form.api_token = data.api_token;
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
                'Renovar la instancia elimina la actual y crea una nueva con el mismo nombre. Tendrás que volver a escanear el QR. ¿Continuar?',
                'Renovar instancia',
                { confirmButtonText: 'Sí, renovar', cancelButtonText: 'Cancelar', type: 'warning' }
            ).then(() => this.renew()).catch(() => {});
        },
        async renew() {
            this.loading_renew = true;
            try {
                const { data } = await this.$http.post('/configurations/whatsapp-notify/renew');
                if (data.success) {
                    this.form.connection_state = 'connecting';
                    this.form.connected_phone = null;
                    this.form.profile_name = null;
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
                const { data } = await this.$http.post('/configurations/whatsapp-notify/restart');
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
            this.$confirm(
                'Esto desconectará el número actual y eliminará la instancia en Evolution. Tendrás que escanear un nuevo QR para reconectar. ¿Continuar?',
                'Desconectar',
                { confirmButtonText: 'Sí, desconectar', cancelButtonText: 'Cancelar', type: 'warning' }
            ).then(() => this.disconnect()).catch(() => {});
        },
        async disconnect() {
            this.loading_disconnect = true;
            try {
                const { data } = await this.$http.post('/configurations/whatsapp-notify/disconnect');
                if (data.success) {
                    this.form.instance = null;
                    this.form.connection_state = 'disconnected';
                    this.form.connected_phone = null;
                    this.form.profile_name = null;
                    this.form.enabled = false;
                    this.instanceName = '';
                    this.qrImage = null;
                    this.lastState = null;
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
        prettyJson(obj) {
            return JSON.stringify(obj, null, 2);
        },
        async copyText(text) {
            if (!text) return;
            try {
                await navigator.clipboard.writeText(text);
                this.$message({ message: 'Copiado al portapapeles', type: 'success' });
            } catch (e) {
                this.$message({ message: 'No se pudo copiar', type: 'error' });
            }
        },
        confirmRegenerateToken() {
            this.$confirm(
                'El token actual dejará de funcionar de inmediato. Cualquier servicio externo que lo use deberá actualizarlo. ¿Continuar?',
                'Regenerar token',
                { confirmButtonText: 'Sí, regenerar', cancelButtonText: 'Cancelar', type: 'warning' }
            ).then(() => this.regenerateToken()).catch(() => {});
        },
        async regenerateToken() {
            this.loading_regenerate = true;
            try {
                const { data } = await this.$http.post('/configurations/whatsapp-notify/regenerate-token');
                if (data.success) {
                    this.form.api_token = data.api_token;
                    this.tokenVisible = true;
                    this.$message({ message: data.message, type: 'success' });
                } else {
                    this.$message({ message: data.message || 'No se pudo regenerar el token', type: 'error' });
                }
            } catch (e) {
                this.$message({ message: 'Error al regenerar el token', type: 'error' });
            } finally {
                this.loading_regenerate = false;
            }
        },
        openSendDialog() {
            this.resetSendForm();
            this.sendDialogVisible = true;
        },
        resetSendForm() {
            this.sendForm = {
                countryCode: this.sendForm?.countryCode || '51',
                localNumber: '',
                message: '',
                file: null,
                filename: null,
            };
            if (this.$refs.pdfInput) this.$refs.pdfInput.value = '';
        },
        onPdfSelected(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (file.type !== 'application/pdf') {
                this.$message({ message: 'Solo se permiten archivos PDF.', type: 'error' });
                event.target.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onloadend = () => {
                this.sendForm.file = reader.result.split(',')[1]; // quitar el prefijo "data:...;base64,"
                this.sendForm.filename = file.name;
            };
            reader.onerror = () => {
                this.$message({ message: 'No se pudo leer el archivo.', type: 'error' });
            };
            reader.readAsDataURL(file);
        },
        removePdf() {
            this.sendForm.file = null;
            this.sendForm.filename = null;
            if (this.$refs.pdfInput) this.$refs.pdfInput.value = '';
        },
        async sendMessage() {
            this.loading_send = true;
            try {
                const { data } = await this.$http.post('/configurations/whatsapp-notify/send', {
                    number: `${this.sendForm.countryCode}${this.sendForm.localNumber}`,
                    message: this.sendForm.message.trim() || null,
                    file: this.sendForm.file,
                    filename: this.sendForm.filename,
                });
                if (data.success) {
                    this.$message({ message: data.message, type: 'success' });
                    this.sendDialogVisible = false;
                } else {
                    this.$message({ message: data.message || 'No se pudo enviar', type: 'error' });
                }
            } catch (e) {
                this.$message({ message: 'Error al enviar el mensaje', type: 'error' });
            } finally {
                this.loading_send = false;
            }
        },
    },
};
</script>

<style scoped>
.form-field {
    margin-bottom: 12px;
}
.phone-country-select {
    flex: 0 0 110px;
}
.pdf-upload input[type="file"] {
    width: 100%;
    font-size: 0.85rem;
}
.pdf-selected {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f5f7fa;
    border: 1px solid #e4e7ed;
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 0.85rem;
}
.pdf-selected span {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.form-field .control-label,
.field-box .control-label {
    display: block;
    font-size: 0.8rem;
    color: #909399;
    margin-bottom: 6px;
}
.field-box {
    background: #f5f7fa;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    padding: 10px 12px;
    margin-bottom: 12px;
}
.metric-value {
    font-size: 1.05rem;
    color: #303133;
}
.action-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.phone-input-group {
    gap: 8px;
}
.examples-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-weight: 600;
    color: #303133;
    user-select: none;
    margin-bottom: 8px;
}
.examples-toggle:hover {
    color: #2563eb;
}
.examples-panel {
    margin-top: 8px;
}
.code-block-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.code-block-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.75rem;
    font-weight: 600;
    color: #909399;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    margin-bottom: 4px;
}
.copy-icon {
    cursor: pointer;
    color: #909399;
}
.copy-icon:hover {
    color: #2563eb;
}
.code-block pre {
    background: #1e1e2e;
    color: #e4e6eb;
    border-radius: 6px;
    padding: 10px 12px;
    margin: 0;
    font-size: 0.8rem;
    white-space: pre-wrap;
    word-break: break-word;
}
</style>
