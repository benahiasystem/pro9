<template>
    <el-dialog
        :title="titleDialog"
        :visible="showDialog"
        append-to-body
        top="7vh"
        @close="close"
    >
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div v-if="warehouses" class="col-md-12">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ubicación</th>
                                    <th class="text-right">Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, index) in warehouses"
                                    :key="index"
                                >
                                    <th>{{ row.warehouse_description }}</th>
                                    <th
                                        class="text-right"
                                        :class="{
                                            'text-danger': Number(row.stock) <= 0
                                        }"
                                    >
                                        {{ (row.stock == null) ? '-' : Number(row.stock).toFixed(2) }}
                                    </th>
                                </tr>
                            </tbody>
                        </table>

                        <template v-if="variations && variations.length > 0">
                            <h5>Stock por variación</h5>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Variación</th>
                                        <th>Código interno</th>
                                        <th class="text-right">Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="variation in variations"
                                        :key="'variation-stock-' + variation.id"
                                    >
                                        <th>{{ variation.variation_label || variation.description }}</th>
                                        <th>{{ variation.internal_id }}</th>
                                        <th
                                            class="text-right"
                                            :class="{
                                                'text-danger': Number(variation.stock) <= 0
                                            }"
                                        >
                                            {{ (variation.stock == null) ? '-' : Number(variation.stock).toFixed(2) }}
                                        </th>
                                    </tr>
                                </tbody>
                            </table>
                        </template>

                        <template v-if="item_unit_types.length > 0">
                            <h5>Lista de Precios Creados</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Unidad</th>
                                            <th>Description</th>
                                            <th>Factor</th>

                                            <template v-for="pl in price_labels">
                                                <th v-if="pl.is_active !== false">{{ pl.label }}</th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row, index) in item_unit_types"
                                            :key="index"
                                        >
                                            <th>{{ row.unit_type_id }}</th>
                                            <th>{{ row.description }}</th>
                                            <th>{{ row.quantity_unit }}</th>
                                            <template v-for="(price, priceIndex) in row.prices">
                                                <th v-if="price_labels[priceIndex] && price_labels[priceIndex].is_active !== false">
                                                    {{ price.price }}
                                                </th>
                                            </template>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <div class="form-actions text-right pt-2">
                <el-button class="second-buton" @click.prevent="close()"
                    >Cerrar</el-button
                >
            </div>
        </form>
    </el-dialog>
</template>

<script>
export default {
    props: {
        showDialog: {default: false},
        warehouses: {default: () => []},
        item_unit_types: {default: () => []},
        config: {default: null},
        price_labels: {default: () => []},
        variations: {type: Array, default: () => []},
    },
    data() {
        return {
            showImportDialog: false,
            resource: "items",
            recordId: null,
            titleDialog: "Stock de producto"
        };
    },
    created() {
        //console.log(this.typeUser)
    },
    methods: {
        close() {
            console.log(this.price_labels);
            this.$emit("update:showDialog", false);
        }
    }
};
</script>
