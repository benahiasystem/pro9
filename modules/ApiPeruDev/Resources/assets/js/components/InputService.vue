<template>
    <el-input
        :value="value"
        :maxlength="maxLength"
        @input="handleInput($event)"
        show-word-limit>
        <template v-if="buttonText">
            <el-button type="primary"
                       class="btn-sunat-reniec"
                       slot="append"
                       :loading="loading"
                       icon="el-icon-search"
                       @click.prevent="clickSearch">{{ buttonText }}
            </el-button>
        </template>
    </el-input>
</template>
<style scoped>
.btn-sunat-reniec{
    position: absolute;
    top: -3px;
    right: 20px;
    height: 44px;
    border-radius: 0px 6px 6px 0;
    z-index: 1;
    color: #fff !important;
}
</style>
<script type="text/javascript">
    export default {
        name: 'ApiPeruDevInputService',
        props: {
            // Tipo de documento de identidad. Resuelve el servicio a consultar
            // para personas (1 = DNI, 6 = RUC, 4 = CE).
            identity_document_type_id: {
                required: false,
                type: String,
                default: null
            },
            // Fuerza el servicio a consultar, sin pasar por el tipo de documento.
            // Util para consultas que no son de identidad: 'placa', 'licencia'.
            service_type: {
                required: false,
                type: String,
                default: null
            },
            // Consulta ademas la licencia de conducir junto con el DNI. El MTC
            // resuelve la licencia a partir del numero de documento, no a
            // partir del numero de licencia, por eso se engancha aqui.
            search_license: {
                required: false,
                type: Boolean,
                default: false
            },
            // Sin restriccion de tipo: varios formularios inicializan el campo
            // en null y Vue avisaria por cada uno.
            value: {
                required: false,
                default: ''
            }
        },
        data() {
            return {
                loading: false,
                resource_base: 'service',
                resource: null,
                maxLength: 20,
                buttonText: null,
                service_config: {
                    ruc:      {maxLength: 11, buttonText: 'SUNAT'},
                    dni:      {maxLength: 8,  buttonText: 'RENIEC'},
                    ce:       {maxLength: 12, buttonText: 'CE'},
                    placa:    {maxLength: 8,  buttonText: 'SUNARP'},
                    licencia: {maxLength: 9,  buttonText: 'MTC'},
                },
                // El CE ('4') queda fuera a proposito: se mantiene el
                // comportamiento actual del formulario de personas. Se puede
                // consultar pasando service_type="ce".
                identity_types: {
                    '1': 'dni',
                    '6': 'ruc',
                },
                current_type: null
            }
        },
        computed: {
            // Los tipos de identidad devuelven datos de persona y se emiten
            // normalizados. El resto (placa, licencia) se emite tal cual.
            isIdentityType() {
                return ['dni', 'ruc', 'ce'].includes(this.current_type)
            },
            // La licencia solo se puede resolver desde un DNI.
            shouldSearchLicense() {
                return this.search_license && this.current_type === 'dni'
            }
        },
        created() {
            this.changeIdentityDocumentTypeId()
        },
        mounted() {
            this.$eventHub.$on('enableClickSearch',()=>{
                // El evento es global: solo deben reaccionar los inputs de
                // identidad, no los de placa o licencia de la misma vista.
                if (!this.isIdentityType) return
                this.clickSearch()
            })
        },
        watch: {
            identity_document_type_id() {
                this.changeIdentityDocumentTypeId()
            },
            service_type() {
                this.changeIdentityDocumentTypeId()
            },
        },
        methods: {
            changeIdentityDocumentTypeId() {
                this.buttonText = null;
                this.resource = null;
                this.maxLength = 20;

                const type = this.service_type || this.identity_types[this.identity_document_type_id] || null;
                this.current_type = type;

                if (type && this.service_config[type]) {
                    this.maxLength = this.service_config[type].maxLength;
                    this.buttonText = this.service_config[type].buttonText;
                    this.resource = this.resource_base + '/' + type;
                }
            },
            handleInput (value) {
                this.$emit('input', value)
            },
            // Consulta la licencia con el numero de documento ya validado. Es
            // una consulta complementaria: si falla, la busqueda principal
            // igual devuelve sus datos.
            searchLicenseByNumber(number) {
                return this.$http.get(`/${this.resource_base}/licencia/${encodeURIComponent(number)}`)
                    .then(response => {
                        const res = response.data

                        // Es una consulta secundaria: no se avisa al usuario
                        // para no ensuciar la busqueda de DNI cuando el
                        // servicio de licencias no esta disponible.
                        if (!res.success) {
                            console.log(res.message)
                            return null
                        }

                        return res.data
                    })
                    .catch(error => {
                        console.log(error.response)
                        return null
                    })
            },
            clickSearch() {
                if (!this.resource) return

                const number = (this.value === null || this.value === undefined) ? '' : String(this.value).trim();
                if (!number.length) {
                    return this.$message.error('Ingrese un número para realizar la consulta')
                }

                this.loading = true;
                this.$http.get(`/${this.resource}/${encodeURIComponent(number)}`)
                    .then(response => {
                        let res = response.data;

                        if (res.success) {
                            let data_return = res.data

                            // Placa y licencia no describen a una persona: se
                            // emiten tal cual llegan del backend.
                            if (!this.isIdentityType) {
                                this.$emit('search', data_return)
                                return
                            }

                            // Se añaden datos para que funcione en varias busqeudas internas
                            data_return.nombre_o_razon_social = null;
                            data_return.nombre_completo = null;
                            data_return.direccion_completa = null;
                            data_return.condicion = null;
                            data_return.estado = null;
                            data_return.ubigeo = [];
                            data_return.ubigeo[0] = null;// department_id
                            data_return.ubigeo[1] = null;// province_id
                            data_return.ubigeo[2] = null;// district_id
                            if (data_return.name !== undefined) {
                                data_return.nombre_completo = data_return.name
                                data_return.nombre_o_razon_social = data_return.name
                            }
                            if (
                                data_return.trade_name !== undefined  &&
                                data_return.trade_name !== null
                            ) {
                                if( data_return.trade_name != '') {
                                    data_return.nombre_o_razon_social = data_return.trade_name
                                }
                            }
                            if (data_return.address !== undefined) {
                                data_return.direccion_completa = data_return.address
                                data_return.direccion = data_return.address
                            }
                            if (data_return.condition !== undefined) {
                                data_return.condicion = data_return.condition
                            }
                            if (data_return.state !== undefined) {
                                data_return.estado = data_return.state
                            }
                            if (data_return.department_id !== undefined) {
                                data_return.ubigeo[0] = data_return.department_id
                            }
                            if (data_return.district_id !== undefined) {
                                data_return.ubigeo[2] = data_return.district_id
                            }
                            if (data_return.province_id !== undefined) {
                                data_return.ubigeo[1] = data_return.province_id
                            }

                            if (this.shouldSearchLicense) {
                                return this.searchLicenseByNumber(number).then(license => {
                                    if (license) {
                                        data_return.license = license.license
                                        data_return.license_data = license
                                    }
                                    this.$emit('search', data_return)
                                })
                            }

                            this.$emit('search', data_return)
                        } else {
                            this.$message.error(res.message)
                        }
                    })
                    .catch(error => {
                        console.log(error.response)
                    })
                    .finally(() => {
                        this.loading = false
                    })
            }
        }
    }
</script>
