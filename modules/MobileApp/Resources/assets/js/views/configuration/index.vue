<template>
    <div v-loading="loading">
        <div class="page-header pe-0">
            <h2><a href="/app/configuration">
                <svg  xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-device-mobile"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 5a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2v-14z" /><path d="M11 4h2" /><path d="M12 17v.01" /></svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span> App Móvil</span></li>
                <li class="active"><span> Configuración</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <button class="btn btn-sm btn-primary mt-2 me-2" @click="showColorDialog = true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg>
                </button>
            </div>
        </div>
        <div class="row">
            <div class="short-div col-md-8">
                <tenant-mobile-app-permissions></tenant-mobile-app-permissions>
            </div>
            <div class="short-div col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img
                            :src="previewImage"
                            alt="Vista previa de la app móvil"
                            class="img-fluid app-preview-img"
                        />
                    </div>
                </div>
            </div>
        </div>

        <el-dialog
            title="Gestionar color de la app"
            :visible.sync="showColorDialog"
            :close-on-click-modal="false"
            width="420px"
        >
            <app-color-config v-if="showColorDialog"></app-color-config>
        </el-dialog>
    </div>
</template>

<script>
    import AppColorConfig from './partials/AppColorConfig.vue'

    export default {
        components: { AppColorConfig },
        data() {
            return {
                form: {},
                loading_submit: false,
                resource: 'app/configurations',
                loading: false,
                showColorDialog: false,
                // Se sirve desde public/, no pasa por el bundle de Vite
                previewImage: '/images/mobile-app/preview.png',
            }
        },
        async created(){
            await this.initForm()
            await this.getRecord()
        },
        methods: {
            async getRecord(){
                this.loading = true
                await this.$http.get(`/${this.resource}`)
                        .then(response => {
                            this.form = response.data.data
                        })
                        .then(()=>{
                            this.loading = false
                        })
            },
            initForm(){
                this.form = {
                    theme_color: 'blue',
                    card_color: 'multicolored',
                    header_waves: false,
                    app_mode: 'default',
                    direct_send_documents_whatsapp: false,
                }
            },
            async submit() {
                this.loading_submit = true
                await this.$http.post(`/${this.resource}`, this.form)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(error => {
                        if (error.response.status === 422) {
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

<style scoped>
.app-preview-img {
    max-height: 640px;
    object-fit: contain;
}
</style>
