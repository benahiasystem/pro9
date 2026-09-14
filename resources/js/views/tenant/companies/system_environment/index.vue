<template>
	<div class="card card-config">
		<div class="card-header bg-info">
			<h3 class="my-0">Entorno del sistema</h3>
		</div>
		<div class="card-body">
			<form autocomplete="off" @submit.prevent="submit">
				<div class="form-body">
					<div class="row">
						<div class="col-md-6">
							<div :class="{'has-danger': errors.soap_type_id}"
								 class="form-group">
								<label class="control-label">SOAP Tipo</label>
								<el-select v-model="form.soap_type_id"
										   :disabled="!form.config_system_env"
										   @change="verifyDocumentsInDemo">
									<el-option v-for="option in soap_types"
											   :key="option.id"
											   :label="option.description"
											   :value="option.id"></el-option>
								</el-select>
								<small v-if="errors.soap_type_id"
									   class="form-control-feedback"
									   v-text="errors.soap_type_id[0]"></small>
							</div>
						</div>
						<div v-if="form.soap_type_id != '03'"
							 class="col-md-6">
							<div :class="{'has-danger': errors.soap_send_id}"
								 class="form-group">
								<label class="control-label">SOAP Envio</label>
								<el-select v-model="form.soap_send_id"
										   :disabled="!form.config_system_env">
									<el-option v-for="(option, index) in soap_sends"
											   :key="index"
											   :label="option"
											   :value="index"></el-option>
								</el-select>
								<small v-if="errors.soap_send_id"
									   class="form-control-feedback"
									   v-text="errors.soap_send_id[0]"></small>
							</div>
						</div>
					</div>

					<template v-if="form.soap_type_id == '02' || form.soap_send_id == '02'">						
						<div class="row mt-4">
                            <h4 class="col-12 m-0 fw-medium">Usuario Secundario Sunat/OSE</h4>
							<div class="col-md-6">
								<div :class="{'has-danger': errors.soap_username}"
									 class="form-group">
									<label class="control-label">SOAP Usuario <span
										class="text-danger">*</span></label>
									<el-input v-model="form.soap_username"
											  :disabled="!form.config_system_env"></el-input>
									<div class="sub-title text-muted"><small>RUC + Usuario. Ejemplo:
										01234567890ELUSUARIO</small></div>
									<small v-if="errors.soap_username"
										   class="form-control-feedback"
										   v-text="errors.soap_username[0]"></small>
								</div>
							</div>
							<div class="col-md-6">
								<div :class="{'has-danger': errors.soap_password}"
									 class="form-group">
									<label class="control-label">SOAP Password
										<span class="text-danger">*</span></label>
									<el-input v-model="form.soap_password"
											  :disabled="!form.config_system_env"></el-input>
									<small v-if="errors.soap_password"
										   class="form-control-feedback"
										   v-text="errors.soap_password[0]"></small>
								</div>
							</div>
						</div>
					</template>

					<div v-if="form.soap_send_id == '02'" class="row">
						<div class="col-md-12">
							<div :class="{'has-danger': errors.soap_url}"
								 class="form-group">
								<label class="control-label">SOAP Url</label>
								<el-input v-model="form.soap_url"></el-input>
								<small v-if="errors.soap_url"
									   class="form-control-feedback"
									   v-text="errors.soap_url[0]"></small>
							</div>
						</div>
					</div>
				</div>

				<div class="form-actions text-end pt-2">
					<el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
				</div>
			</form>
		</div>

		<el-dialog :visible.sync="showDialogDemo"
				   :close-on-click-modal="!loading_delete"
				   :close-on-press-escape="!loading_delete"
				   :show-close="!loading_delete"
				   append-to-body
				   custom-class="demo-documents-dialog"
				   width="460px">
			<div class="demo-documents">
				<div class="demo-documents__icon">
					<i class="el-icon-warning-outline"></i>
				</div>
				<h4 class="demo-documents__title">Hay documentos creados en demo</h4>
				<p class="demo-documents__text">
					Antes de pasar a producción, elimina los documentos de prueba para evitar conflictos.
				</p>

				<ul v-if="demoTypes.length" class="demo-documents__list">
					<li v-for="(type, index) in demoTypes" :key="index">
						<i class="el-icon-document"></i>
						<span>{{ type.description }}</span>
						<span v-if="type.count" class="demo-documents__count">{{ type.count }}</span>
					</li>
				</ul>
				<p v-else-if="verifyDemo" class="demo-documents__text" v-text="verifyDemo.message"></p>

				<div class="demo-documents__alert">
					<i class="el-icon-info"></i>
					<span>Se eliminarán todos los registros de prueba (ventas, compras, cotizaciones, gastos, etc.). Esta acción no se puede deshacer.</span>
				</div>
			</div>
			<div slot="footer" class="demo-documents__footer">
				<el-button :disabled="loading_delete" @click="showDialogDemo = false">Cancelar</el-button>
				<el-button type="danger" icon="el-icon-delete" :loading="loading_delete" @click="deleteDemoDocuments">
					Eliminar documentos de prueba
				</el-button>
			</div>
		</el-dialog>
	</div>
</template>

<script>
export default {
	name: 'SystemEnvironment',
	data() {
		return {
			resource: 'companies',
			loading_submit: false,
			errors: {},
			form: {},
			soap_sends: [],
			soap_types: [],
			verifyDemo: null,
			showDialogDemo: false,
			loading_delete: false,
			pending_soap_type_id: null,
		}
	},
	computed: {
		demoTypes() {
			return (this.verifyDemo && this.verifyDemo.types) || []
		},
	},
	async created() {
		await this.initForm()
		await this.getTables()
		await this.getRecord()
	},
	methods: {
		async getTables() {
			return this.$http.get(`/${this.resource}/tables`)
				.then(response => {
					this.soap_sends = response.data.soap_sends
					this.soap_types = response.data.soap_types
					this.verifyDemo = response.data.verifyDocumentsInDemo
				})
		},
		async getRecord() {
			return this.$http.get(`/${this.resource}/record`)
				.then(response => {
					if (response.data !== '') {
						this.form = response.data.data
					}
				})
		},
		verifyDocumentsInDemo(value) {
			if (value !== '01' && this.verifyDemo && this.verifyDemo.success) {
				this.pending_soap_type_id = value
				this.form.soap_type_id = '01'
				this.showDialogDemo = true
			}
		},
		async deleteDemoDocuments() {
			this.loading_delete = true
			try {
				const response = await this.$http.post('/options/delete_documents')
				if (!response.data.success) {
					this.$message.error(response.data.message)
					return
				}
				this.$message.success(response.data.message)
				await this.getTables()
				if (this.verifyDemo && this.verifyDemo.success) {
					this.$message.warning(this.verifyDemo.message)
					return
				}
				this.form.soap_type_id = this.pending_soap_type_id
				this.showDialogDemo = false
			} catch (error) {
				this.$message.error('No se pudieron eliminar los documentos de prueba')
				console.log(error)
			} finally {
				this.loading_delete = false
			}
		},
		initForm() {
			this.errors = {}
			this.form = {
				id: null,
				identity_document_type_id: '06000006',
				number: null,
				name: null,
				trade_name: null,
				soap_send_id: '01',
				soap_type_id: '01',
				soap_username: null,
				soap_password: null,
				soap_url: null,
				certificate: null,
				certificate_due: null,
				logo: null,
				logo_dark: null,
				logo_store: null,
				detraction_account: null,
				operation_amazonia: false,
				toggle: false,
				config_system_env: false,
				img_firm: null,
				is_pharmacy: false,
				cod_digemid: null,
				integrated_query_client_id: null,
				integrated_query_client_secret: null,
				app_logo: null,
				soap_sunat_username: null,
				soap_sunat_password: null,
				api_sunat_id: null,
				api_sunat_secret: null,
				title_web: null
			}
		},
		submit() {
			this.loading_submit = true
			this.$http.post(`/${this.resource}`, this.form)
				.then(response => {
					if (response.data.success) {
						this.$message.success(response.data.message)
					} else {
						this.$message.error(response.data.message)
					}
				})
				.catch(error => {
					if (error.response && error.response.status === 422) {
						this.errors = error.response.data
					} else {
						console.log(error)
					}
				})
				.then(() => {
					this.loading_submit = false
				})
		}
	}
}
</script>

<style>
.demo-documents-dialog {
	border-radius: 12px;
	max-width: calc(100% - 32px);
}
.demo-documents-dialog .el-dialog__header {
	padding: 12px 12px 0;
}
.demo-documents-dialog .el-dialog__body {
	padding: 0 28px 8px;
}
.demo-documents-dialog .el-dialog__footer {
	padding: 16px 28px 24px;
}
.demo-documents {
	text-align: center;
	word-break: normal;
	overflow-wrap: break-word;
}
.demo-documents__icon {
	width: 56px;
	height: 56px;
	margin: 0 auto 14px;
	border-radius: 50%;
	background: #fdf6ec;
	color: #e6a23c;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 30px;
}
.demo-documents__title {
	margin: 0 0 6px;
	font-size: 18px;
	font-weight: 600;
	color: #303133;
}
.demo-documents__text {
	margin: 0 0 16px;
	font-size: 14px;
	line-height: 1.5;
	color: #606266;
}
.demo-documents__list {
	list-style: none;
	margin: 0 0 16px;
	padding: 0;
	text-align: left;
}
.demo-documents__list li {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px 12px;
	border: 1px solid #ebeef5;
	border-radius: 8px;
	font-size: 14px;
	color: #303133;
}
.demo-documents__list li + li {
	margin-top: 8px;
}
.demo-documents__list li > i {
	color: #909399;
	font-size: 16px;
}
.demo-documents__list li > span:first-of-type {
	flex: 1;
}
.demo-documents__count {
	min-width: 26px;
	padding: 2px 8px;
	border-radius: 10px;
	background: #fef0f0;
	color: #f56c6c;
	font-size: 12px;
	font-weight: 600;
	text-align: center;
}
.demo-documents__alert {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	padding: 10px 12px;
	border-radius: 8px;
	background: #f4f4f5;
	font-size: 12px;
	line-height: 1.5;
	color: #909399;
	text-align: left;
}
.demo-documents__alert i {
	margin-top: 2px;
}
.demo-documents__footer {
	display: flex;
	justify-content: flex-end;
	flex-wrap: wrap;
	gap: 10px;
}
.demo-documents__footer .el-button + .el-button {
	margin-left: 0;
}
</style>
