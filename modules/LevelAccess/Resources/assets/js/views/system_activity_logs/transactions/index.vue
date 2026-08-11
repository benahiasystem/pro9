<template>
    <div>
        <div class="page-header pr-0">
            <h2>
                <a href="/dashboard"><i class="fas fa-file-alt"></i></a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>Transacciones de documentos electrónicos</span>
                </li>
            </ol>
            <div class="right-wrapper pull-right"></div>
        </div>
        <div class="card mb-0 tab-content-default row-new">
            <!-- <div class="card-header bg-info">
                <h3 class="my-0">Transacciones de documentos electrónicos</h3>
            </div> -->
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <th>#</th>
                        <th class="text-left">Fecha y hora emisión</th>
                        <th>Usuario asociado</th>
                        <th>Tipo Documento</th>
                        <th class="text-center">Documento</th>
                        <th class="text-left">Fecha y hora registro</th>
                        <th class="text-left">Fecha y hora actualización</th>
                    </tr>

                    <tr></tr>
                    <tr slot-scope="{ index, row }">
                        <td>{{ index }}</td>
                        <td class="text-left">
                            {{
                                formatDateTime(
                                    row.date_of_issue,
                                    row.time_of_issue
                                )
                            }}
                        </td>
                        <td>{{ row.user_name }}</td>
                        <td>{{ row.document_type_description }}</td>
                        <td class="text-center">{{ row.number_full }}</td>
                        <td class="text-left">
                            {{ formatDateTime(row.created_at) }}
                        </td>
                        <td class="text-left">
                            {{ formatDateTime(row.updated_at) }}
                        </td>
                    </tr>
                </data-table>
            </div>
        </div>
    </div>
</template>

<script>
import DataTable from "../../../components/DataTable.vue";

export default {
    components: { DataTable },
    data() {
        return {
            showDialog: false,
            showDialogOptions: false,
            open_cash: true,
            resource: "system-activity-logs/transactions",
            recordId: null,
            cash: null
        };
    },
    async created() {},
    methods: {
        formatDateTime(date, time = null) {
            if (!date) return null;
            if (!time) {
                const parsed = moment(date, ["YYYY-MM-DD HH:mm:ss", "YYYY-MM-DD"]);
                return parsed.isValid() ? parsed.format("DD-MM-YYYY h:mmA") : null;
            }
            const dateTimeString = `${date} ${time}`;
            const parsedDate = moment(dateTimeString, ["YYYY-MM-DD HH:mm:ss", "YYYY-MM-DD HH:mm:ss.SSSSSS"]);
            return parsedDate.isValid()
                ? parsedDate.format("DD-MM-YYYY h:mmA")
                : null;
        }
    }
};
</script>
