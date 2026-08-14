<template>
<div>
    <div class="page-header pe-0">
        <h2>
            <a href="/dashboard"><i class="fas fa-tachometer-alt"></i></a>
        </h2>
        <ol class="breadcrumbs">
            <li class="active"><span>Links de pago</span></li>
        </ol>
        <div class="right-wrapper pull-right">
            <button class="btn btn-custom btn-sm mt-2 me-2" type="button" @click.prevent="clickCreate()">
                <i class="fa fa-plus-circle"></i> Nuevo
            </button>
        </div>
    </div>
    <div class="card tab-content-default row-new mb-0">
        <!-- <div class="card-header bg-info">
            <h3 class="my-0">Generador de links de pago</h3>
        </div> -->
        <div class="card-body">
            <data-table :resource="resource">

                <tr slot="heading" width="100%">
                    <th>#</th>
                    <th>Identificador</th>
                    <th>Cliente</th>
                    <th>Estado</th>
                    <th>Link</th>
                    <th>Total</th>
                    <th class="text-end"></th>
                </tr>

                <tr></tr>
                <tr slot-scope="{ index, row }">
                    <td>{{ index }}</td>
                    <td>{{ row.uuid }}</td>
                    <td>{{ row.customer_name }}</td>
                    <td>
                        <span class="badge" :class="row.is_paid ? 'bg-success' : 'bg-warning'">
                            {{ row.status_description }}
                        </span>
                    </td>
                    <td>

                        <button type="button"
                                style="min-width: 41px"
                                class="btn waves-effect waves-light btn-xs btn-info m-1__2"
                                v-clipboard:copy="row.user_payment_link"
                                v-clipboard:success="onCopyText"
                                v-clipboard:error="onErrorCopyText">
                                Copiar
                        </button>

                    </td>
                    <td>{{ row.total }}</td>

                    <td class="text-end">
                        <!--
                            Un link pagado no admite ninguna acción, se oculta el menú completo
                            para no abrir un desplegable vacío

                            Transacciones tambien queda fuera: solo el flujo antiguo de mercadopago
                            registraba filas, los checkouts actuales no generan ninguna
                        -->
                        <el-dropdown
                            v-if="typeUser === 'admin' && !row.is_paid"
                            trigger="click"
                            @command="handleDropdownCommand($event, row)"
                        >
                            <el-button
                                class="btn btn-default btn-sm btn-dropdown-toggle"
                                size="mini"
                                native-type="button"
                            >
                                <i class="fas fa-ellipsis-v"></i>
                                <i class="fas fa-ellipsis-h" style="display: none;"></i>
                            </el-button>
                            <el-dropdown-menu slot="dropdown">
                                <el-dropdown-item command="confirm_payment">
                                    Marcar como pagado
                                </el-dropdown-item>
                                <el-dropdown-item command="edit">
                                    Editar
                                </el-dropdown-item>
                                <el-dropdown-item command="delete">
                                    Eliminar
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </el-dropdown>
                    </td>
                </tr>
            </data-table>
        </div>

        <payment-link-form 
            :recordId="recordId" 
            :showDialog.sync="showDialog"
            ></payment-link-form>

        <payment-link-transactions 
            :recordId="recordId" 
            :showDialog.sync="showDialogTransactions"
            ></payment-link-transactions>
    </div>
</div>
</template>

<script>

    import PaymentLinkForm from "./form.vue";
    import PaymentLinkTransactions from "./partials/transactions.vue";
    import DataTable from "@components/DataTable.vue";
    import {deletable} from "@mixins/deletable";

    export default {
        props: [
            'typeUser'
        ],
        mixins: [deletable],
        components: {
            PaymentLinkForm,
            PaymentLinkTransactions,
            DataTable
        },
        data() {
            return { 
                resource: 'payment-links',
                recordId: null, 
                showDialog: false,
                showDialogTransactions: false,
            }
        },
        async created() {
        },
        methods: { 
            // el menú solo se renderiza para links pendientes, no hace falta revalidar is_paid
            handleDropdownCommand(command, row) {
                switch (command) {
                    case 'confirm_payment':
                        this.clickConfirmPayment(row.id);
                        break;
                    case 'edit':
                        this.clickCreate(row.id);
                        break;
                    case 'delete':
                        this.clickDelete(row.id);
                        break;
                    default:
                        break;
                }
            },
            clickConfirmPayment(id) {

                this.$confirm('Se registrarán los pagos de los comprobantes asociados al link. ¿Desea continuar?', 'Marcar como pagado', {
                    confirmButtonText: 'Aceptar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                })
                .then(() => {

                    this.$http.post(`/${this.resource}/confirm-payment`, {id})
                        .then(response => {
                            if (response.data.success) {
                                this.$message.success(response.data.message)
                                this.$eventHub.$emit('reloadData')
                            } else {
                                this.$message.error(response.data.message)
                            }
                        })
                        .catch(error => {
                            this.$message.error(error.response.data.message || 'No se pudo marcar el link como pagado')
                        })

                })
                .catch(() => {})

            },
            clickDelete(id) {
                this.destroy(`/${this.resource}/${id}`).then(() =>
                    this.$eventHub.$emit("reloadData")
                );
            },
            clickShowTransactions(recordId){
                this.recordId = recordId
                this.showDialogTransactions = true
            },
            onCopyText: function(e) {
                this.$message.success('Texto copiado al portapapeles')
            },
            onErrorCopyText: function(e) {
                this.$message.error('No se pudo copiar el texto al portapapeles')
                console.log(e)
            },
            clickCreate(recordId = null){
                this.recordId = recordId
                this.showDialog = true
            }
        }
    }
</script>
