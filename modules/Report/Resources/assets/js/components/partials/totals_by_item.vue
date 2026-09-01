<template>
    <el-dialog :title="titleDialog" :visible="showDialog" @close="close" @open="create" :close-on-click-modal="false" append-to-body>
        <form autocomplete="off" @submit.prevent="submit" v-loading="loading">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="btn-group flex-wrap dropdown">
                            <button type="button" class="btn waves-effect waves-light btn-sm btn-primary dropdown-toggle"
                                    data-bs-toggle="dropdown" aria-expanded="false">Reporte <span class="caret"></span></button>
                            <div class="dropdown-menu" role="menu" x-placement="bottom-start" style="position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(0px, 42px, 0px);">
                                <a class="dropdown-item text-1" href="#" @click.prevent="clickDownload('pdf')">PDF A4</a>
                                <a class="dropdown-item text-1" href="#" @click.prevent="clickDownload('ticket')">PDF Ticket</a>
                                <a class="dropdown-item text-1" href="#" @click.prevent="clickDownload('ticket80')">PDF Ticket 80</a>
                                <!--
                               @todo validar si es posible, ajustar el alto automaticamente
                               -->
                                <a class="dropdown-item text-1" href="#" @click.prevent="clickDownload('excel')">Excel</a>
                                <!-- <a class="dropdown-item text-1" href="#" @click.prevent="clickDownloadProducts(row.id, 'excel')">Excel</a> -->
                            </div>
                        </div>
                        <!--
                        <el-button class="submit" type="danger"  icon="el-icon-tickets" @click.prevent="clickDownload('pdf')" >Exportar PDF</el-button>
                        <el-button class="submit" type="success" @click.prevent="clickDownload('excel')"><i class="fa fa-file-excel" ></i>  Exportal Excel</el-button>
                        -->
                        <template v-if="canGenerateGuide">
                            <el-button class="submit" type="success" @click.prevent="onGenerateGuide">Generar guía
                            </el-button>
                        </template>
                    </div>
                    <div class="col-md-12 mt-2">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <!-- <th class="">#</th> -->
                                        <th  class="text-center">Cod. Interno</th>
                                        <th  class="text-left">Producto</th>
                                        <th  class="text-center">Unidad</th>
                                        <th  class="text-center">Cantidad Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, index) in records" :key="index">
                                        <!-- <td>{{ index+1  }}</td>  -->
                                        <td  class="text-center">{{row.item_internal_id}}</td>
                                        <td  class="text-left">{{row.item_description}}</td>
                                        <td  class="text-center">{{row.item_unit_type_id}}</td>
                                        <td  class="text-center">{{row.quantity}}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>

            </div>
            <div class="form-actions text-right mt-4">
                <el-button @click.prevent="close()">Cerrar</el-button>
            </div>
        </form>
    </el-dialog>
</template>

<script>


    export default {
        props: [
            'resource',
            'showDialog',
            'parameters'
        ],
        data() {
            return {
                loading: false,
                titleDialog: 'Totales por productos',
                // resource: 'reports/sales-consolidated',
                records: [],
            }
        },
        computed:{
            canGenerateGuide(){
                if(
                    this.resource === 'reports/order-notes-consolidated' ||
                    this.resource === 'reports/guides'){
                    return false;
                }

                return true;
            }
        },
        methods: {
            onGenerateGuide() {
                const items = [];
                Object.keys(this.records).forEach((r) => {
                    items.push({
                        id: this.records[r].item_id,
                        quantity: this.records[r].quantity
                    })
                });
                localStorage.setItem('items', JSON.stringify(items));
                const tab = window.open('/dispatches/create', '_BLANK');
                tab.focus();
            },
            async clickDownload(type) {
                const routes = {
                    pdf: 'pdf-totals',
                    ticket: 'ticket-totals',
                    ticket80: 'ticket80-totals',
                    excel: 'excel-totals',
                };
                const mimeTypes = {
                    pdf: 'application/pdf',
                    ticket: 'application/pdf',
                    ticket80: 'application/pdf',
                    excel: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                };
                const extensions = {
                    pdf: 'pdf',
                    ticket: 'pdf',
                    ticket80: 'pdf',
                    excel: 'xlsx',
                };

                try {
                    const response = await this.$http.get(
                        `/${this.resource}/${routes[type]}/?${this.parameters}`,
                        { responseType: 'blob' }
                    );

                    if (response.data.type === 'application/json') {
                        const text = await response.data.text();
                        const data = JSON.parse(text);
                        this.$message.success(
                            data.message || 'El reporte se está procesando; revísalo en la bandeja de descargas.'
                        );
                        return;
                    }

                    const blob = new Blob([response.data], { type: mimeTypes[type] || response.data.type });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `Reporte_Consolidado_Totales_${Date.now()}.${extensions[type]}`;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    setTimeout(() => window.URL.revokeObjectURL(url), 10000);
                } catch (error) {
                    console.log(error);
                    this.$message.error('Ocurrió un error al generar el reporte');
                }
            },
            close() {
                this.$emit('update:showDialog', false)
            },
            create(){
                this.getRecords()
            },
            getRecords() {
                this.loading = true
                this.$http.get(`/${this.resource}/totals-by-item?${this.parameters}`)
                    .then((response) => {
                        this.records = response.data
                    })
                    .then(() => {
                        this.loading = false
                    })

            },
        }
    }
</script>
