<template>
    <div>
        <div class="page-header pr-0">
            <h2>
                <a href="/list-reports">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>Ventas por marca</span>
                </li>
            </ol>
        </div>
        <div class="card mb-0 pt-2 pt-md-0 tab-content-default row-new">
            <div class="card mb-0">
                <div class="card-body">
                    <data-table-sales-by-brand
                        :resource="resource"
                        @update:records="records = $event"
                    ></data-table-sales-by-brand>
                    <div class="table-responsive mt-3">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Marca actual</th>
                                    <th>Unidad</th>
                                    <th>Cantidad</th>
                                    <th>Ventas con IVA (Bs.)</th>
                                    <th>Ganancia estimada (Bs.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in records" :key="row.id">
                                    <td>{{ index + 1 }}</td>
                                    <td>{{ row.brand_name }}</td>
                                    <td>{{ row.unit_type_id || 'Sin unidad' }}</td>
                                    <td>{{ row.quantity_sold }}</td>
                                    <td>{{ row.display.total_sale_value }}</td>
                                    <td>{{ row.display.total_profit }}</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="records.length">
                                <tr>
                                    <td colspan="3" class="text-right">TOTALES:</td>
                                    <td><div v-for="(quantity, unit) in quantitiesByUnit" :key="unit">{{ quantity }} {{ unit }}</div></td>
                                    <td>{{ totalSaleValue }}</td>
                                    <td>{{ totalProfit }}</td>
                                </tr>
                            </tfoot>
                        </table>
                        <p v-if="hasUnknownUnit">Las partidas sin unidad identificada no se incluyen en los totales de cantidad.</p>
                        <p>La marca corresponde al catálogo actual; la ganancia es una estimación basada en compras o costos actuales.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import DataTableSalesByBrand from "../../components/DataTableSalesByBrand.vue";
import { exactValue, exactSum } from '../../../../../../../resources/js/helpers/exchange-rate-math';

export default {
    components: { DataTableSalesByBrand },
    data() {
        return {
            resource: "reports/sales-by-brand",
            records: []
        };
    },
    computed: {
        hasUnknownUnit() { return this.records.some(row=>!row.unit_type_id) },
        quantitiesByUnit() {
            const groups = {};
            this.records.forEach(row => {
                if (!row.unit_type_id) return;
                groups[row.unit_type_id] = (groups[row.unit_type_id] || []).concat([exactValue(row, 'quantity_sold')]);
            });
            return Object.fromEntries(Object.entries(groups).map(([unit, values])=>[unit, exactSum(values).decimal()]));
        },
        totalSaleValue() {
            return exactSum(this.records.map(row=>exactValue(row, 'total_sale_value'))).final(2)
        },
        totalProfit() {
            return exactSum(this.records.map(row=>exactValue(row, 'total_profit'))).final(2)
        }
    }
};
</script>
