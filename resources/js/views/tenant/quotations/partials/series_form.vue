<template>

        <div class="row" :loading="loading">
            <div class="col-lg-12">
                <div class="row">
                        <div class="col-md-12">
                            <div style="margin:3px" class="table-responsive">
                                <h5 class="separator-title">
                                    Productos
                                </h5>
                                <table class="table">
                                <thead>
                                    <tr>
                                        <th class="text-center">Nombre</th>
                                        <th class="text-center">Cantidad</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, index) in items" :key="index">
                                        <td class="text-center">{{ setDescriptionOfItem(row.item) }}
                                        {{
                                        row.item.presentation && row.item.presentation.hasOwnProperty('description') ?
                                        row.item.presentation.description : ''
                                        }}
                                        <template v-if="itemRequiresLot(row) && !rowNeedsLotAssignment(row)">
                                            <br />
                                            <small class="text-success">
                                                Lotes: {{ showItemLots(resolveIdLoteSelected(row)) }}
                                            </small>
                                        </template>
                                        </td>
                                        <td class="text-center">{{row.quantity}}</td>
                                        <td class="series-table-actions text-right">

                                            <template v-if="itemRequiresLot(row)">
                                                <button
                                                    v-if="rowNeedsLotAssignment(row)"
                                                    type="button"
                                                    class="btn waves-effect waves-light btn-xs btn-warning"
                                                    @click.prevent="openDialogLotsGroup(index, row)"
                                                >
                                                    Asignar Lote
                                                </button>
                                                <button
                                                    v-else
                                                    type="button"
                                                    class="btn waves-effect waves-light btn-xs btn-outline-secondary"
                                                    @click.prevent="openDialogLotsGroup(index, row)"
                                                >
                                                    Cambiar lote
                                                </button>
                                            </template>

                                            <button
                                                v-if="row.item && row.item.series_enabled"
                                                type="button"
                                                class="btn waves-effect waves-light btn-xs btn-success"
                                                @click.prevent="openDialogLots(row.item.lots, row)"
                                            >
                                                    <i class="el-icon-check"></i> Series
                                            </button>
                                            <button  type="button" class="btn waves-effect waves-light btn-xs btn-info" @click.prevent="openSelectWarehouses(row, index)">
                                                <i class="el-icon-search"></i> Stock
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>
                </div>
            </div>

            <!-- <lots :showDialog.sync="showDialogLots" :lots="lots"></lots> -->

            <select-lots-form
                :showDialog.sync="showDialogLots"
                :quantity="lots_quantity"
                :lots="lots"
                :itemId="item_id"
                @addRowSelectLot="addRowSelectLot">
            </select-lots-form>

            <select-warehouses
                :showDialog.sync="showSelectWarehouses"
                :warehouses="selectWarehouses"
                :item_index="item_index">
            </select-warehouses>

            <select-lots-group
                :lots-group="lots_group"
                :quantity="lots_group_quantity"
                :showDialog.sync="showDialogLotsGroup"
                @addRowLotGroup="addRowLotGroup">
            </select-lots-group>

        </div>

</template>

<script>
    // import Lots from "./lots.vue";
    import SelectLotsForm from '../../documents/partials/lots.vue'
    import SelectWarehouses from './select_warehouses.vue'
    import {showNamePdfOfDescription} from '../../../../helpers/functions'
    import SelectLotsGroup from '../../documents/partials/lots_group.vue'
    import {
        itemRequiresLot,
        rowNeedsLotAssignment,
        resolveIdLoteSelected,
        hydrateItemLots,
    } from '../../../../helpers/lotValidation'

    export default {
        props:['items','config'],
        components:{SelectLotsForm, SelectWarehouses, SelectLotsGroup},

        data()
        {
            return{
                showSelectWarehouses: false,
                selectWarehouses: [],
                showDialogLots: false,
                lots:[],
                item_id: null,
                item_index: -1,
                showDialogLotsGroup:false,
                lots_quantity: 0,
                lots_group_quantity:0,
                lots_group: [],
                current_index_item: -1,
                loading: false,
            }
        },
        created(){
            // console.log(this.items)

            // Hidratar lotes ya guardados en el JSON del ítem (cotización → conversión)
            if (Array.isArray(this.items)) {
                this.items.forEach(row => hydrateItemLots(row));
            }

            this.$eventHub.$on('selectWarehouseId', (data) => {
                // console.log(data)
                this.items[data.index].warehouse_id = data.warehouse_id
            })

        },
        methods:{
            itemRequiresLot(row) {
                return itemRequiresLot(row);
            },
            rowNeedsLotAssignment(row) {
                return rowNeedsLotAssignment(row);
            },
            resolveIdLoteSelected(row) {
                return resolveIdLoteSelected(row);
            },
            showItemLots(idLoteSelected) {
                if (!idLoteSelected) return '';
                if (!Array.isArray(idLoteSelected)) {
                    return String(idLoteSelected);
                }
                return idLoteSelected
                    .map(lot => {
                        const code = lot.code || lot.id;
                        const qty = lot.compromise_quantity || 0;
                        return `${code} (${qty})`;
                    })
                    .join(', ');
            },
            addRowLotGroup(lots_selecteds){
                if (this.current_index_item < 0 || !this.items[this.current_index_item]) {
                    return;
                }

                const row = this.items[this.current_index_item];

                // Reactividad Vue 2: IdLoteSelected puede no existir aún en el objeto
                this.$set(row, 'IdLoteSelected', lots_selecteds);
                if (row.item) {
                    this.$set(row.item, 'IdLoteSelected', lots_selecteds);
                }

                this.current_index_item = -1;
                this.$message.success('Lotes asignados correctamente.');
            },
            async openDialogLotsGroup(index, row){

                if (!row || !row.item_id) {
                    return this.$message.error('No se pudo identificar el producto para asignar lotes.');
                }

                await this.getLotsGroup(row.item_id)
                await this.regularizeCompromiseQuantity(row)
                this.current_index_item = index
                this.lots_group_quantity = parseFloat(row.quantity) || 0
                this.showDialogLotsGroup = true

            },
            async regularizeCompromiseQuantity(row){

                if(row.IdLoteSelected && Array.isArray(row.IdLoteSelected))
                {
                    this.lots_group.forEach(l_group => {

                        const lot = _.find(row.IdLoteSelected, {id : l_group.id})

                        if(lot) l_group.compromise_quantity = lot.compromise_quantity

                    })
                }

            },
            async getLotsGroup(item_id){

                this.loading = true

                await this.$http.get(`/item-lots-group/available-data/${item_id}`)
                    .then((response) => {
                        this.lots_group = response.data
                    })
                    .then(()=>{
                        this.loading = false
                    })

            },
            async openSelectWarehouses(row, index) {

                if(!row.consulted_stock){

                    await this.$http.get(`/quotations/item-warehouses/${row.item_id}`)
                        .then((response) => {

                            // this.selectWarehouses = response.data
                            row.item.warehouses = response.data
                            row.consulted_stock = true
                            // this.checkedWarehouse()
                            // row.item.warehouses = this.selectWarehouses

                        });

                }

                this.selectWarehouses = row.item.warehouses
                this.item_index = index
                this.showSelectWarehouses = true

            },
            openDialogLots(lt, row)
            {
                this.item_id = row.item_id
                this.lots_quantity = row.quantity
                this.showDialogLots = true
                this.lots = lt
            },
            addRowSelectLot(){

            },
            setDescriptionOfItem(item) {
                return showNamePdfOfDescription(item, this.config.show_pdf_name)
            },

        }
    }
</script>
