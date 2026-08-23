<template>
    <div class="card">
        <div class="card-header bg-info bg-info-customer-admin">
            <h3 class="my-0">Repositorio remoto (GitHub / GitLab)</h3>
        </div>
        <form class="row card-body px-0" autocomplete="off" @submit.prevent="submit">

            <div class="col-md-12">
                <p class="text-muted mb-3">
                    Credenciales que usa el sistema para conectarse al repositorio (auto-actualización).
                    Reemplazan a <code>GIT_REMOTE_URL</code>, <code>GIT_USER</code> y <code>GIT_TOKEN</code>;
                    si dejas un campo vacío se sigue usando el valor del archivo <code>.env</code>.
                </p>
            </div>

            <div class="col-md-8">
                <div class="form-group" :class="{'has-danger': errors.git_remote_url}">
                    <label class="control-label">
                        URL remota
                        <el-tooltip placement="right-start">
                            <div slot="content" style="max-width: 320px;">
                                URL HTTPS del repositorio, por ejemplo<br>
                                <code>https://github.com/usuario/proyecto.git</code><br>
                                <code>https://gitlab.com/grupo/proyecto.git</code><br><br>
                                No incluyas usuario ni token en la URL: se agregan automáticamente.
                            </div>
                            <i class="fa fa-info-circle"></i>
                        </el-tooltip>
                    </label>
                    <el-input v-model="form.git_remote_url"
                              placeholder="https://github.com/usuario/proyecto.git"></el-input>
                    <small class="form-control-feedback"
                           v-if="errors.git_remote_url"
                           v-text="errors.git_remote_url[0]"></small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group" :class="{'has-danger': errors.git_provider}">
                    <label class="control-label">
                        Proveedor
                        <el-tooltip placement="right-start">
                            <div slot="content" style="max-width: 320px;">
                                Define qué API se consulta (ramas protegidas, tags) y cómo se envía el token.<br>
                                Necesario si usas una instancia propia de GitLab, donde el dominio no lo delata.
                            </div>
                            <i class="fa fa-info-circle"></i>
                        </el-tooltip>
                    </label>
                    <el-select v-model="form.git_provider"
                               :placeholder="detectedProvider ? providerLabel + ' (detectado)' : 'Selecciona'"
                               clearable
                               style="width: 100%;">
                        <el-option label="GitHub" value="github"></el-option>
                        <el-option label="GitLab" value="gitlab"></el-option>
                    </el-select>
                    <small class="form-control-feedback"
                           v-if="errors.git_provider"
                           v-text="errors.git_provider[0]"></small>
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group" :class="{'has-danger': errors.git_user}">
                    <label class="control-label">
                        Usuario de {{ providerLabel }}
                        <el-tooltip placement="right-start">
                            <div slot="content" style="max-width: 320px;">
                                Nombre de usuario dueño del token.<br>
                                En GitLab también funciona <code>oauth2</code>.
                            </div>
                            <i class="fa fa-info-circle"></i>
                        </el-tooltip>
                    </label>
                    <el-input v-model="form.git_user" placeholder="mi-usuario"></el-input>
                    <small class="form-control-feedback"
                           v-if="errors.git_user"
                           v-text="errors.git_user[0]"></small>
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group" :class="{'has-danger': errors.git_token}">
                    <label class="control-label">
                        Access Token
                        <el-tooltip placement="right-start" effect="dark">
                            <div slot="content" style="max-width: 380px; line-height: 1.5;">
                                <strong>Permisos necesarios según el proveedor:</strong>
                                <br><br>
                                <span :style="provider === 'github' ? 'font-weight: bold;' : 'opacity: .7;'">
                                    <u>GitHub</u> — Personal Access Token
                                </span>
                                <ul class="mb-0 ps-3" :style="provider === 'github' ? '' : 'opacity: .7;'">
                                    <li>Clásico: scope <code>repo</code> (incluye <code>repo:status</code> y acceso a repos privados).</li>
                                    <li>Fine-grained: <code>Contents: Read-only</code> y <code>Metadata: Read-only</code>
                                        (usa <code>Read and write</code> solo si necesitas subir cambios).</li>
                                </ul>
                                <br>
                                <span :style="provider === 'gitlab' ? 'font-weight: bold;' : 'opacity: .7;'">
                                    <u>GitLab</u> — Personal / Project Access Token
                                </span>
                                <ul class="mb-0 ps-3" :style="provider === 'gitlab' ? '' : 'opacity: .7;'">
                                    <li>Scope <code>read_repository</code> para clonar y actualizar.</li>
                                    <li>Scope <code>read_api</code> para listar ramas protegidas y tags.</li>
                                    <li>Rol mínimo del token: <code>Reporter</code>
                                        (<code>write_repository</code> solo si necesitas subir cambios).</li>
                                </ul>
                                <br>
                                El token se guarda en la base de datos y solo se inyecta en la URL de conexión al actualizar; nunca queda escrito en el repositorio.
                            </div>
                            <i class="fa fa-info-circle"></i>
                        </el-tooltip>
                    </label>
                    <el-input v-model="form.git_token" show-password
                              placeholder="ghp_... / glpat-..."></el-input>
                    <small class="form-control-feedback"
                           v-if="errors.git_token"
                           v-text="errors.git_token[0]"></small>
                </div>
            </div>

            <div class="col-md-12 text-end pt-2">
                <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
            </div>

        </form>
    </div>
</template>

<script>
export default {
    data() {
        return {
            resource: 'configurations',
            loading_submit: false,
            errors: {},
            form: {
                git_remote_url: null,
                git_provider: null,
                git_user: null,
                git_token: null,
            },
        };
    },
    computed: {
        /** Proveedor deducido del dominio cuando no se ha seleccionado uno. */
        detectedProvider() {
            const url = (this.form.git_remote_url || '').toLowerCase();

            if (url.includes('gitlab')) return 'gitlab';
            if (url.includes('github')) return 'github';

            return null;
        },
        provider() {
            return this.form.git_provider || this.detectedProvider;
        },
        providerLabel() {
            if (this.provider === 'gitlab') return 'GitLab';
            if (this.provider === 'github') return 'GitHub';

            return 'Git';
        },
    },
    created() {
        this.fetch();
    },
    methods: {
        async fetch() {
            try {
                const {data} = await this.$http.get(`/${this.resource}/git-repository`);

                this.form.git_remote_url = data.git_remote_url;
                this.form.git_provider = data.git_provider;
                this.form.git_user = data.git_user;
                this.form.git_token = data.git_token;
            } catch (e) {
                console.log(e);
            }
        },
        async submit() {
            this.loading_submit = true;
            this.errors = {};

            try {
                const {data} = await this.$http.post(`/${this.resource}/git-repository`, this.form);

                if (data.success) {
                    this.$message.success(data.message);
                } else {
                    this.$message.error(data.message);
                }
            } catch (error) {
                if (error.response && error.response.status === 422) {
                    this.errors = error.response.data.errors || error.response.data;
                } else {
                    this.$message.error('Error al guardar la configuración');
                    console.log(error);
                }
            } finally {
                this.loading_submit = false;
            }
        },
    },
};
</script>
<style scoped></style>
