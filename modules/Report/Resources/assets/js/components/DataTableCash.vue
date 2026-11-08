<template>
    <div>
        <div class="row">

            <div class="col-md-12 col-lg-12 col-xl-12 ">
                  
                <div class="row mt-2">  
                        
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="control-label">Usuario / Vendedor</label>
                                <el-select v-model="form.user_id" clearable filterable>
                                    <el-option v-for="option in users" :key="option.id" :value="option.id" :label="option.name"></el-option>
                                </el-select>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="control-label">Tipo de documento</label>
                                <el-select v-model="form.document_type_id" >
                                    <el-option v-for="option in document_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                </el-select>
                            </div>
                        </div>

                        <div class="col-lg-9 col-md-9 col-md-9 col-sm-12" style="margin-top:29px"> 
                            <el-button class="submit" type="primary" @click.prevent="getRecordsByFilter" :loading="loading_submit" icon="el-icon-search" >Buscar</el-button>
                            
                            <template v-if="records.length>0"> 

                                <el-button class="submit" type="danger" :disabled="hasUnappliedFilters || loading_submit" icon="el-icon-tickets" @click.prevent="clickDownload('pdf')" >Exportar PDF</el-button>

                                <el-button class="submit" type="success" :disabled="hasUnappliedFilters || loading_submit" @click.prevent="clickDownload('excel')"><i class="fa fa-file-excel" ></i>  Exportar Excel</el-button>
                                <span v-if="hasUnappliedFilters">Los filtros cambiaron. Pulse Buscar para actualizar el reporte.</span>

                            </template>

                        </div>             
                    
                </div>
                <div class="row mt-1 mb-4">
                    
                </div> 
            </div>


            <div class="col-md-12">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                        <slot name="heading"></slot>
                        </thead>
                        <tbody>
                            <slot v-for="(row, index) in records" :row="row" :index="customIndex(index)"></slot>
                        </tbody> 
                    </table>
                    <div>
                        <el-pagination
                                @current-change="getRecords"
                                layout="total, prev, pager, next"
                                :total="pagination.total"
                                :current-page.sync="pagination.current_page"
                                :page-size="pagination.per_page">
                        </el-pagination>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>
<style>
.font-custom{
    font-size:15px !important
}
</style>
<script>

    import moment from 'moment'
    import queryString from 'query-string'

    export default { 
        props: {
            resource: String,
        },
        data () {
            return {
                loading_submit:false,
                columns: [],
                records: [],
                appliedForm: null,
                recordsRequestId: 0,
                headers: headers_token,
                document_types: [],
                pagination: {}, 
                search: {}, 
                totals: {}, 
                establishment: null,
                users: [],       
                form: {}, 
            }
        },
        computed: {
            hasUnappliedFilters() {
                return !!this.appliedForm && JSON.stringify(this.form) !== JSON.stringify(this.appliedForm)
            },
        },
        created() {
            this.initForm()
            this.$eventHub.$on('reloadData', () => {
                this.getRecords()
            })
        },
        async mounted () { 

            await this.$http.get(`/${this.resource}/filter`)
                .then(response => {
                    this.document_types = response.data.document_types;
                    this.users = response.data.users;
                });


            await this.getRecords()

        },
        methods: {  
            clickDownload(type) {                 
                if (!this.appliedForm || this.hasUnappliedFilters || this.loading_submit) {
                    return this.$message.error('Pulse Buscar para actualizar el reporte antes de exportar.')
                }
                let query = queryString.stringify({
                    ...this.appliedForm
                });
                window.open(`/${this.resource}/${type}/?${query}`, '_blank');
            },
            initForm(){
 
                this.form = {
                    document_type_id:'01',
                    user_id:null,
                }

            },  
            customIndex(index) {
                return (this.pagination.per_page * (this.pagination.current_page - 1)) + index + 1
            }, 
            async getRecordsByFilter(){
                return this.getRecords({...this.form}, 1)
            },
            getRecords(filters, page) {
                const selected = filters && typeof filters === 'object' ? filters : (this.appliedForm || this.form)
                const snapshot = {...selected}
                const pageNumber = typeof filters === 'number' ? filters : (page || this.pagination.current_page || 1)
                const requestId = this.recordsRequestId = (this.recordsRequestId || 0) + 1
                this.loading_submit = true
                return this.$http.get(`/${this.resource}/records?${this.getQueryParameters(snapshot, pageNumber)}`).then((response) => {
                    if (requestId !== this.recordsRequestId) return false
                    this.records = response.data.data
                    this.pagination = response.data.meta
                    this.pagination.per_page = parseInt(response.data.meta.per_page)
                    this.appliedForm = snapshot
                    return true
                }).catch(error => {
                    if (requestId !== this.recordsRequestId) return false
                    this.records = []
                    this.pagination = {}
                    this.appliedForm = null
                    const data = error.response && error.response.data
                    const errors = data && (data.errors || (typeof data.message === 'object' && data.message))
                    this.$message.error(errors ? Object.values(errors).flat().join(' ') : (data && data.message) || 'No se pudo consultar el reporte.')
                    return false
                }).finally(() => {
                    if (requestId === this.recordsRequestId) this.loading_submit = false
                })


            },
            getQueryParameters(filters = this.appliedForm || this.form, page = this.pagination.current_page) {
                return queryString.stringify({
                    page,
                    limit: this.limit,
                    ...filters
                })
            },
             
        }
    }
</script>
