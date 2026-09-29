<template>
    <div>
        <div class="page-header pe-0">
            <h2><a href="/dashboard"><i class="fas fa-tachometer-alt"></i></a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Tipos de motivos de traslado</span></li>
            </ol>
        </div>
        <div class="card tab-content-default row-new">
            <div class="card-body">
                <div class="alert alert-info">
                    Los códigos y descripciones son contractuales. Sólo puede configurarse si el motivo descuenta stock.
                </div>
                <div class="col-md-12">
                    <div class="scroll-shadow shadow-left" v-show="showLeftShadow"></div>
                    <div class="scroll-shadow shadow-right" v-show="showRightShadow"></div>
                    <div class="table-responsive" ref="scrollContainer">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th class="text-center">Descuenta stock</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(row, index) in records" :key="row.id">
                                <td>{{ index + 1 }}</td>
                                <td>{{ row.id }}</td>
                                <td>{{ row.description }}</td>
                                <td class="text-center">
                                    <el-switch
                                        v-model="row.discount_stock_value"
                                        active-text="Sí"
                                        inactive-text="No"
                                        @change="updateDiscountStock(row)">
                                    </el-switch>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: ['typeUser'],
    data() {
        return {
            resource: 'transfer-reason-types',
            records: [],
            showLeftShadow: false,
            showRightShadow: false,
        }
    },
    created() {
        this.getData()
    },
    mounted() {
        this.$nextTick(() => {
            const el = this.$refs.scrollContainer
            if (el) {
                el.addEventListener('scroll', this.checkScrollShadows)
                this.checkScrollShadows()
            }
        })
    },
    methods: {
        checkScrollShadows() {
            const el = this.$refs.scrollContainer
            if (!el) return
            this.showLeftShadow = el.scrollLeft > 1
            this.showRightShadow = el.scrollWidth - el.clientWidth - el.scrollLeft > 1
        },
        getData() {
            this.$http.get(`/${this.resource}/records`).then(response => {
                this.records = response.data.data
            })
        },
        updateDiscountStock(row) {
            this.$http.post(`/${this.resource}`, {
                id: row.id,
                discount_stock: row.discount_stock_value,
            }).then(response => {
                this.$message.success(response.data.message)
            }).catch(() => {
                row.discount_stock_value = !row.discount_stock_value
                this.$message.error('No se pudo actualizar la configuración de inventario')
            })
        },
    },
}
</script>
