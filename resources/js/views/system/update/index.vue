<template>
    <div class="p-3">
        <div class="premium-gradient-header premium-gradient-header--static">
            <div class="row align-items-center">
                <div class="col-12 col-md-8">
                    <h1 class="text-white mb-2 font-bold" style="font-size: 1.8rem; letter-spacing: -0.5px;">Actualización del Sistema</h1>
                    <p class="text-white-50 mb-0">Mantén tu facturador actualizado a la última versión estable de forma segura y automatizada.</p>
                </div>
                <div class="col-12 col-md-4 text-md-right mt-3 mt-md-0">
                    <el-tag
                        effect="plain"
                        size="medium"
                        style="border-radius: 8px; font-weight: bold; font-size: 0.9rem; padding: 6px 12px; height: auto; background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.25); color: #f1f5f9;">
                        <i class="el-icon-info"></i> Versión Actual: <strong>{{ version || 'Cargando...' }}</strong>
                    </el-tag>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 col-lg-7">
                <div class="card mb-0 update-wizard-card">
                    <div class="card-body p-4">
                        <nav class="update-stepper" aria-label="Progreso de actualización">
                            <div
                                v-for="(step, index) in wizardSteps"
                                :key="step.key"
                                class="update-stepper__item"
                                :class="{
                                    'is-active': currentStep === index,
                                    'is-done': currentStep > index
                                }">
                                <div class="update-stepper__indicator">
                                    <i v-if="currentStep > index" class="el-icon-check"></i>
                                    <i v-else :class="step.icon"></i>
                                </div>
                                <span class="update-stepper__label">{{ step.title }}</span>
                            </div>
                        </nav>

                        <div style="min-height: 380px;">
                        <div v-show="currentStep === 0" class="px-1 py-2">
                            <div class="update-step-title">
                                <i class="el-icon-odometer"></i>
                                <span>Paso 1: Pre-Validaciones del Servidor</span>
                            </div>

                            <p class="text-muted mb-4">Antes de iniciar la actualización, analizamos el entorno para garantizar la seguridad de tu base de datos y archivos.</p>

                            <div v-if="preCheck.loading" class="text-center py-4">
                                <i class="el-icon-loading text-primary" style="font-size: 2.5rem; margin-bottom: 12px;"></i>
                                <p class="text-muted font-weight-bold">Realizando comprobaciones de seguridad en el contenedor FPM...</p>
                            </div>

                            <div v-else-if="preCheck.done" class="row g-3 mb-4">
                                <div class="col-sm-6 col-xl-4">
                                    <div class="card h-100 text-center shadow-sm" :class="validationCardBorder(preCheck.data.token_valid)">
                                        <div class="card-body py-3">
                                            <i :class="validationIconClass(preCheck.data.token_valid ? 'ok' : 'error')" style="font-size: 2rem;" class="mb-2 d-block"></i>
                                            <h6 class="card-title mb-1">Token GitLab</h6>
                                            <p class="card-text text-muted small mb-0">{{ preCheck.data.token_message }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-xl-4">
                                    <div class="card h-100 text-center shadow-sm" :class="validationCardBorder(preCheck.data.git_connected)">
                                        <div class="card-body py-3">
                                            <i :class="validationIconClass(preCheck.data.git_connected ? 'ok' : 'error')" style="font-size: 2rem;" class="mb-2 d-block"></i>
                                            <h6 class="card-title mb-1">Conexión Git</h6>
                                            <p class="card-text text-muted small mb-0">{{ preCheck.data.git_message }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-xl-4">
                                    <div class="card h-100 text-center shadow-sm" :class="validationCardBorder(preCheck.data.git_writable)">
                                        <div class="card-body py-3">
                                            <i :class="validationIconClass(preCheck.data.git_writable ? 'ok' : 'error')" style="font-size: 2rem;" class="mb-2 d-block"></i>
                                            <h6 class="card-title mb-1">Permisos .git (pull)</h6>
                                            <p class="card-text text-muted small mb-0">{{ preCheck.data.git_writable_message }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-xl-4">
                                    <div class="card h-100 text-center shadow-sm" :class="validationCardBorder(preCheck.data.has_git_dir, 'warning')">
                                        <div class="card-body py-3">
                                            <i :class="validationIconClass(preCheck.data.has_git_dir ? 'ok' : 'warning')" style="font-size: 2rem;" class="mb-2 d-block"></i>
                                            <h6 class="card-title mb-1">Repositorio .git</h6>
                                            <p class="card-text text-muted small mb-0">{{ preCheck.data.git_dir_message }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-xl-4">
                                    <div class="card h-100 text-center shadow-sm" :class="validationCardBorder(!!preCheck.data.version_resolved, 'warning')">
                                        <div class="card-body py-3">
                                            <i :class="validationIconClass(preCheck.data.version_resolved ? 'ok' : 'warning')" style="font-size: 2rem;" class="mb-2 d-block"></i>
                                            <h6 class="card-title mb-1">Versión detectada</h6>
                                            <p class="card-text text-muted small mb-0">{{ preCheck.data.version_message }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="preCheck.done && (!preCheck.data.token_valid || !preCheck.data.git_connected || !preCheck.data.git_writable)" class="mt-3">
                                <el-alert
                                    title="No se cumplen todos los requisitos"
                                    type="error"
                                    show-icon
                                    :closable="false">
                                    <p class="mb-0 small">Revisa token GitLab, conexión remota y permisos de escritura en .git.</p>
                                    <pre v-if="preCheck.data.setup_command" class="bg-light p-2 rounded small mt-2 mb-0">{{ preCheck.data.setup_command }}</pre>
                                </el-alert>
                            </div>

                            <div class="text-right mt-4 pt-2">
                                <el-button
                                    v-if="!preCheck.done"
                                    type="primary"
                                    :loading="preCheck.loading"
                                    @click.prevent="runPreChecks()">
                                    Comenzar Comprobaciones
                                </el-button>

                                <el-button
                                    v-else
                                    type="success"
                                    :disabled="!preCheck.data.token_valid || !preCheck.data.git_connected || !preCheck.data.git_writable"
                                    @click.prevent="goToBranches()">
                                    Siguiente: Seleccionar Rama <i class="el-icon-arrow-right"></i>
                                </el-button>
                            </div>
                        </div>

                        <div v-show="currentStep === 1" class="px-1 py-2">
                            <div class="update-step-title">
                                <i class="el-icon-share"></i>
                                <span>Paso 2: Selección y Cambio de Rama</span>
                            </div>

                            <p class="text-muted mb-4">Solo se listan ramas protegidas en GitLab (producción y clientes). Las de prueba no aparecen. Si este servidor ya está en otra rama, también se muestra la actual para poder actualizarla in situ.</p>

                            <div v-if="branchesData.loading" class="text-center py-4">
                                <i class="el-icon-loading text-primary" style="font-size: 2.5rem; margin-bottom: 12px;"></i>
                                <p class="text-muted">Cargando ramas del repositorio remoto...</p>
                            </div>

                            <div v-else class="row align-items-center">
                                <div class="col-12 col-md-6 mb-3 mb-md-0">
                                    <label class="font-weight-bold text-muted d-block mb-2">Rama del Repositorio</label>
                                    <el-select v-model="branchesData.selected" placeholder="Selecciona una rama" style="width: 100%;">
                                        <el-option
                                            v-for="item in branchesData.list"
                                            :key="item"
                                            :label="item"
                                            :value="item">
                                            <span style="float: left">{{ item }}</span>
                                            <span v-if="item === branchesData.current" style="float: right; color: #8492a6; font-size: 13px;">(Actual)</span>
                                        </el-option>
                                    </el-select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="alert alert-info mb-0 h-100">
                                        <div class="d-flex align-items-center mb-2 fw-bold">
                                            <i class="el-icon-info me-2"></i> Rama Actual en Host
                                        </div>
                                        <p class="text-muted mb-0 small">Tu servidor se encuentra apuntando actualmente a la rama: <strong>{{ branchesData.current }}</strong>.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-4">
                                <div class="card-body py-3">
                                    <el-checkbox v-model="exec_migration">
                                        Ejecutar migraciones de base de datos (administrador y tenants)
                                    </el-checkbox>
                                    <p class="text-muted mb-0 mt-2 small">
                                        Desmarca esta opción si solo deseas actualizar código y limpiar cachés, sin modificar el esquema de la base de datos.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 pt-2 d-flex justify-content-between">
                                <el-button type="info" plain @click.prevent="currentStep = 0">
                                    <i class="el-icon-arrow-left"></i> Regresar
                                </el-button>
                                <el-button
                                    type="success"
                                    :disabled="!branchesData.selected"
                                    @click.prevent="confirmAndStartUpdate()">
                                    Actualizar Sistema <i class="el-icon-arrow-right"></i>
                                </el-button>
                            </div>
                        </div>

                        <div v-show="currentStep >= 2" class="px-1 py-2">
                            <div class="update-step-title">
                                <i :class="getStepIcon()"></i>
                                <span>{{ getStepTitle() }}</span>
                            </div>

                            <p class="text-muted mb-4">{{ getStepDesc() }}</p>

                            <div class="terminal-console">
                                <div class="terminal-header">
                                    <div class="terminal-dots">
                                        <span class="terminal-dot dot-red"></span>
                                        <span class="terminal-dot dot-yellow"></span>
                                        <span class="terminal-dot dot-green"></span>
                                    </div>
                                    <div>Log del Servidor: auto-update</div>
                                    <div style="font-size: 1.1em; color: #cbd5e1;"><i class="el-icon-monitor"></i></div>
                                </div>

                                <div class="terminal-line info">Inicializando consola de logs de actualización...</div>
                                <div class="terminal-line info" v-if="terminalLogs" v-html="formattedTerminalLogs"></div>
                                <div class="terminal-line cmd" v-if="loading_submit">Ejecutando proceso en segundo plano... <i class="el-icon-loading"></i></div>
                            </div>

                            <div v-if="updateCompleted" class="alert alert-success mt-4">
                                <div class="d-flex align-items-center gap-2 mb-2 fw-bold">
                                    <i class="el-icon-circle-check"></i>
                                    <span>Validación post-actualización</span>
                                </div>
                                <ul class="mb-0 ps-3 small">
                                    <li>Accede a la aplicación en el navegador y confirma que carga sin errores 500.</li>
                                    <li>Revisa <code>storage/logs/laravel.log</code> en busca de excepciones recientes.</li>
                                    <li>Prueba un tenant y funciones críticas (login, emisión de comprobante).</li>
                                    <li>Versión reportada: <strong>{{ version || '—' }}</strong></li>
                                </ul>
                            </div>

                            <div class="mt-4 pt-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <el-button
                                        v-if="currentStep >= 2 && !loading_submit"
                                        type="primary"
                                        @click.prevent="restartProcess()">
                                        Terminar y Reiniciar
                                    </el-button>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="card mb-0">
                    <div class="card-header bg-info bg-info-customer-admin d-flex align-items-center gap-2">
                        <i class="el-icon-document"></i>
                        <h3 class="my-0">Registro de Cambios (Changelog)</h3>
                    </div>
                    <div class="card-body changelog-content update-changelog-body" v-html="changelog"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                resource: 'auto-update',
                version: '',
                changelog: '',
                currentStep: 0,
                wizardSteps: [
                    { key: 'validations', title: 'Validaciones', icon: 'el-icon-circle-check' },
                    { key: 'branch', title: 'Rama', icon: 'el-icon-share' },
                    { key: 'pull', title: 'Git Pull', icon: 'el-icon-download' },
                    { key: 'composer', title: 'Composer', icon: 'el-icon-setting' },
                    { key: 'artisan', title: 'Artisan', icon: 'el-icon-cpu' },
                ],
                preCheck: {
                    loading: false,
                    done: false,
                    data: {},
                },
                branchesData: {
                    loading: false,
                    current: '',
                    list: [],
                    selected: '',
                },
                terminalLogs: '',
                loading_submit: false,
                exec_migration: true,
                updateCompleted: false,
            }
        },
        computed: {
            formattedTerminalLogs() {
                if (!this.terminalLogs) return '';
                return this.terminalLogs
                    .replace(/\n/g, '<br>')
                    .replace(/\[Creado\]/g, '<span class="text-success" style="font-weight: bold;">[Creado]</span>')
                    .replace(/\[Existe\]/g, '<span style="color: #6366f1; font-weight: bold;">[Existe]</span>')
                    .replace(/\[Error\]/g, '<span class="text-danger" style="font-weight: bold;">[Error]</span>')
                    .replace(/=== COMPOSER INSTALL ===/g, '<span style="color: #f59e0b; font-weight: bold; font-size: 1.05em;">=== COMPOSER INSTALL ===</span>')
                    .replace(/=== CREACIÓN DE DIRECTORIOS ===/g, '<span style="color: #00f0ff; font-weight: bold; font-size: 1.05em;">=== CREACIÓN DE DIRECTORIOS ===</span>')
                    .replace(/=== ASIGNACIÓN DE PERMISOS \(chmod 775\) ===/g, '<span style="color: #00f0ff; font-weight: bold; font-size: 1.05em;">=== ASIGNACIÓN DE PERMISOS (chmod 775) ===</span>')
                    .replace(/=== CAMBIO DE PROPIETARIO \(chown www-data\) ===/g, '<span style="color: #00f0ff; font-weight: bold; font-size: 1.05em;">=== CAMBIO DE PROPIETARIO (chown www-data) ===</span>')
                    .replace(/=== COMPOSER DUMP-AUTOLOAD ===/g, '<span style="color: #f59e0b; font-weight: bold; font-size: 1.05em;">=== COMPOSER DUMP-AUTOLOAD ===</span>')
                    .replace(/=== CONFIGURAR PDF WRITER \(mpdf 777\) ===/g, '<span style="color: #00f0ff; font-weight: bold; font-size: 1.05em;">=== CONFIGURAR PDF WRITER (mpdf 777) ===</span>');
            }
        },
        created() {
            this.getVersion();
            this.getChangelog();
        },
        methods: {
            validationCardBorder(ok, mode = 'error') {
                if (ok) {
                    return 'border-start border-success border-3';
                }

                return mode === 'warning'
                    ? 'border-start border-warning border-3'
                    : 'border-start border-danger border-3';
            },
            validationIconClass(state) {
                const map = {
                    ok: 'el-icon-success text-success',
                    warning: 'el-icon-warning text-warning',
                    error: 'el-icon-error text-danger',
                };

                return map[state] || map.error;
            },
            getVersion() {
                this.$http.get(`/${this.resource}/version`, {
                    params: { _t: Date.now() }
                })
                .then(response => {
                    if (response.data !== '') {
                        this.version = response.data;
                    }
                }).catch(() => {});
            },
            getChangelog() {
                this.$http.get(`/${this.resource}/changelog`)
                .then(response => {
                    if (response.data !== '') {
                        this.changelog = response.data;
                    }
                }).catch(() => {});
            },
            runPreChecks() {
                this.preCheck.loading = true;
                this.$http.get(`/${this.resource}/pre-check`)
                .then(response => {
                    this.preCheck.data = response.data || {};
                    this.preCheck.done = true;
                    if (this.preCheck.data.token_valid && this.preCheck.data.git_connected && this.preCheck.data.git_writable) {
                        this.$message.success('Pre-validaciones superadas con éxito');
                    } else {
                        this.$message.error('Fallo en las pre-validaciones. Por favor, revisa las tarjetas.');
                    }
                }).catch(() => {
                    this.$message.error('Error al ejecutar las comprobaciones de seguridad.');
                }).finally(() => {
                    this.preCheck.loading = false;
                });
            },
            goToBranches() {
                this.currentStep = 1;
                this.getBranchesList();
            },
            getBranchesList() {
                this.branchesData.loading = true;
                this.$http.get(`/${this.resource}/branches`)
                .then(response => {
                    this.branchesData.current = response.data.current;
                    this.branchesData.list = response.data.list;
                    this.branchesData.selected = response.data.current;
                }).catch(() => {
                    this.$message.error('Error al listar las ramas del repositorio.');
                }).finally(() => {
                    this.branchesData.loading = false;
                });
            },
            getStepIcon() {
                if (this.currentStep === 2) return 'el-icon-download';
                if (this.currentStep === 3) return 'el-icon-setting';
                if (this.currentStep === 4) return 'el-icon-cpu';
                return 'el-icon-monitor';
            },
            getStepTitle() {
                if (this.currentStep === 2) return 'Paso 3: Descarga de Cambios (Git Pull)';
                if (this.currentStep === 3) return 'Paso 4: Composer & Permisos de Directorios';
                if (this.currentStep === 4) return 'Paso 5: Migración de Base de Datos y Caché';
                return 'Ejecución del Servidor';
            },
            getStepDesc() {
                if (this.currentStep === 2) return 'Restauramos skins CSS, public/mozo y public/vendeya si molestan al pull, y descargamos la rama seleccionada. No se hace reset del repositorio.';
                if (this.currentStep === 3) return 'Instalamos dependencias de Composer solo si cambió composer.lock, y aplicamos permisos de directorios en el contenedor FPM.';
                if (this.currentStep === 4) return 'Ejecutamos migraciones (si están habilitadas), config:cache, cache:clear y optimize:clear según el plan de actualización.';
                return 'Logs de los procesos del servidor.';
            },
            confirmAndStartUpdate() {
                const selected = this.branchesData.selected;
                const current = this.branchesData.current;

                if (selected !== current) {
                    this.$confirm(
                        `Vas a cambiar de la rama "${current}" a "${selected}". Solo se restauran skins CSS, public/mozo y public/vendeya si están modificados. El resto de cambios locales se conserva. ¿Confirmas que es el entorno correcto (desarrollo / staging / producción)?`,
                        'Confirmar cambio de rama',
                        {
                            confirmButtonText: 'Sí, actualizar',
                            cancelButtonText: 'Cancelar',
                            type: 'warning',
                        }
                    ).then(() => {
                        this.startUpdateProcess();
                    }).catch(() => {});
                } else {
                    this.startUpdateProcess();
                }
            },
            startUpdateProcess() {
                this.updateCompleted = false;
                this.currentStep = 2;
                this.terminalLogs = `Iniciando pull de la rama: ${this.branchesData.selected}...\n`;
                this.loading_submit = true;

                this.$http.get(`/${this.resource}/pull/${encodeURIComponent(this.branchesData.selected)}`)
                .then(response => {
                    const data = response.data || {};
                    const output = typeof data === 'string' ? data : (data.output || '');
                    const restored = Array.isArray(data.restored_paths) ? data.restored_paths : [];
                    const cleaned = Array.isArray(data.cleaned_paths) ? data.cleaned_paths : [];

                    if (restored.length) {
                        this.terminalLogs += `\nRutas restauradas (git checkout --):\n- ${restored.join('\n- ')}\n`;
                    }
                    if (cleaned.length) {
                        this.terminalLogs += `\nRutas limpiadas (git clean -fd):\n- ${cleaned.join('\n- ')}\n`;
                    }

                    this.terminalLogs += `\n[Git Pull Exitoso]\n${output}\n`;

                    const alreadyUpToDate = data.already_up_to_date === true
                        || output.includes('Already up to date.')
                        || output.includes('Already up-to-date.');
                    const composerLockChanged = data.composer_lock_changed === true;

                    if (alreadyUpToDate) {
                        this.terminalLogs += `\nEl sistema ya se encuentra en la versión más reciente.\n`;
                        this.$message.success('El repositorio ya estaba actualizado.');
                    } else {
                        this.$message.success('Actualizaciones descargadas correctamente.');
                    }

                    if (composerLockChanged) {
                        this.goToComposerInstall();
                    } else {
                        this.terminalLogs += `\ncomposer.lock no cambió. Se omite Composer e instalación de dependencias.\n`;
                        this.continueAfterPull();
                    }
                }).catch(error => {
                    const data = error.response && error.response.data;
                    const detail = (data && data.message) ? data.message : error.message;
                    this.terminalLogs += `\n[ERROR Git Pull]\n${detail}\n`;
                    this.$message.error('Fallo en la descarga de cambios del repositorio.');
                    this.loading_submit = false;
                });
            },
            continueAfterPull() {
                if (this.exec_migration) {
                    this.goToArtisanMigrate();
                } else {
                    this.goToPostUpdateCaches();
                }
            },
            goToComposerInstall() {
                this.currentStep = 3;
                this.terminalLogs += `\nInstalando dependencias mediante Composer e inicializando permisos de directorios en el contenedor FPM...\n`;
                this.loading_submit = true;

                this.$http.get(`/${this.resource}/composer/install`)
                .then(response => {
                    this.terminalLogs += `\n${response.data}\n`;
                    this.$message.success('Composer y permisos configurados.');
                    this.continueAfterPull();
                }).catch(error => {
                    this.terminalLogs += `\n[ERROR Composer/Permisos]\n${error.response ? error.response.data.message : error.message}\n`;

                    if (error.response && error.response.status === 504) {
                        this.terminalLogs += `\n[Aviso] Se detectó un timeout del servidor web (504), pero Composer suele seguir ejecutándose en segundo plano. Continuando...\n`;
                        this.$message.warning('Timeout del servidor. Continuando con el siguiente paso.');
                        this.continueAfterPull();
                    } else {
                        this.$message.error('Fallo en la instalación de dependencias Composer.');
                        this.loading_submit = false;
                    }
                });
            },
            goToArtisanMigrate() {
                this.currentStep = 4;
                this.terminalLogs += `\nIniciando migraciones de la base de datos principal de Laravel...\n`;
                this.loading_submit = true;

                this.$http.get(`/${this.resource}/artisan/migrate`)
                .then(response => {
                    this.terminalLogs += `\n[Migraciones Administrador]\n${response.data || 'Sin salida (Migraciones completadas)'}\n`;
                    this.$message.success('Migraciones de administrador ejecutadas.');
                    this.goToArtisanMigrateTenant();
                }).catch(error => {
                    this.terminalLogs += `\n[ERROR Migraciones Administrador]\n${error.response ? error.response.data.message : error.message}\n`;
                    this.$message.error('Error al migrar la base de datos principal.');
                    this.loading_submit = false;
                });
            },
            goToArtisanMigrateTenant() {
                this.terminalLogs += `\nIniciando migraciones de Tenancy para todos los clientes (Multi-tenant)...\n`;
                this.loading_submit = true;

                this.$http.get(`/${this.resource}/artisan/migrate/tenant`)
                .then(response => {
                    this.terminalLogs += `\n[Migraciones Multi-tenant]\n${response.data || 'Sin salida (Migraciones completadas)'}\n`;
                    this.$message.success('Migraciones de Tenancy completadas.');
                    this.goToPostUpdateCaches();
                }).catch(error => {
                    this.terminalLogs += `\n[ERROR Migraciones Tenancy]\n${error.response ? error.response.data.message : error.message}\n`;
                    this.$message.error('Error al migrar base de datos de Tenants.');
                    this.loading_submit = false;
                });
            },
            goToPostUpdateCaches() {
                this.currentStep = 4;
                this.terminalLogs += `\nCacheando configuración (php artisan config:cache)...\n`;
                this.loading_submit = true;

                this.$http.get(`/${this.resource}/artisan/config-cache`)
                .then(response => {
                    this.terminalLogs += `\n[Config Cache]\n${response.data || 'Configuration cached successfully.'}\n`;
                    return this.$http.get(`/${this.resource}/artisan/cache-clear`);
                })
                .then(response => {
                    this.terminalLogs += `\n[Cache Clear]\n${response.data || 'Application cache cleared.'}\n`;
                    return this.$http.get(`/${this.resource}/artisan/clear`);
                })
                .then(response => {
                    this.terminalLogs += `\n[Optimize Clear]\n${response.data}\n`;
                    this.finishUpdate();
                })
                .catch(error => {
                    const msg = error.response && error.response.data && error.response.data.message
                        ? error.response.data.message
                        : error.message;
                    this.terminalLogs += `\n[ERROR Caché]\n${msg}\n`;
                    this.$message.error('Error al procesar cachés del sistema.');
                    this.loading_submit = false;
                });
            },
            finishUpdate() {
                this.terminalLogs += `\n[ACTUALIZACIÓN COMPLETADA CON ÉXITO]\nEl sistema se encuentra actualizado, optimizado y listo para operar.\n`;
                this.$message.success('Actualización finalizada correctamente.');
                this.updateCompleted = true;
                this.getVersion();
                this.loading_submit = false;
            },
            restartProcess() {
                this.currentStep = 0;
                this.preCheck.done = false;
                this.preCheck.data = {};
                this.terminalLogs = '';
                this.loading_submit = false;
                this.updateCompleted = false;
            }
        }
    }
</script>

<style lang="scss">
.changelog-content h1,
.changelog-content h2,
.changelog-content h3 {
    color: #1e1b4b;
    margin-top: 24px;
    margin-bottom: 12px;
    font-weight: 600;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 8px;
}

.changelog-content li {
    margin-bottom: 8px;
}
</style>
