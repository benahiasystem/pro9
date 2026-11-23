<template>
    <el-dialog   :title="titleDialog" :visible="showDialog" :close-on-click-modal="false" @close="close" @open="create" append-to-body top="7vh">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-12">
                        <el-tabs v-model="activeName"   type="card">
                            <el-tab-pane label="General" name="first">


                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group" >
                                                <label class="control-label">Imágen <span class="text-danger"></span></label>
                                                <el-upload class="avatar-uploader"
                                                        :data="{'type': 'items'}"
                                                        :headers="headers"
                                                        :action="`/${resource}/upload`"
                                                        :show-file-list="false"
                                                        :on-success="onSuccess">
                                                    <img v-if="form.image_url" :src="form.image_url" class="avatar">
                                                    <i v-else class="el-icon-plus avatar-uploader-icon"></i>
                                                </el-upload>
                                            </div>
                                        </div>

                                        <div class="col-md-9">
                                            <div class="row">

                                                <!-- <div class="short-div col-md-6">
                                                    <div class="form-group" :class="{'has-danger': errors.name}">
                                                        <label class="control-label">Nombre <span class="text-danger">*</span></label>
                                                        <el-input v-model="form.name" dusk="name"></el-input>
                                                        <small class="form-control-feedback" v-if="errors.name" v-text="errors.name[0]"></small>
                                                    </div>
                                                </div> -->

                                                <div class="short-div col-md-6">
                                                    <div class="form-group" :class="{'has-danger': errors.description}">
                                                        <label class="control-label">Nombre<span class="text-danger">*</span></label>
                                                        <el-input v-model="form.description" dusk="description" @focus="$event.target.select()"></el-input>
                                                        <small class="form-control-feedback" v-if="errors.description" v-text="errors.description[0]"></small>
                                                    </div>
                                                </div>

                                                <div class="short-div col-md-6">
                                                    <div class="form-group" :class="{'has-danger': errors.second_name}">
                                                        <label class="control-label">Nombre secundario</label>
                                                        <el-input v-model="form.second_name" dusk="second_name" @focus="$event.target.select()"></el-input>
                                                        <small class="form-control-feedback" v-if="errors.second_name" v-text="errors.second_name[0]"></small>
                                                    </div>
                                                </div>

                                                <!-- <div class="short-div col-md-8">
                                                    <div class="form-group" :class="{'has-danger': errors.description}">
                                                        <label class="control-label">Descripción <span class="text-danger">*</span></label>
                                                        <el-input v-model="form.description" dusk="description"></el-input>
                                                        <small class="form-control-feedback" v-if="errors.description" v-text="errors.description[0]"></small>
                                                    </div>
                                                </div> -->


                                                <div class="short-div col-md-8">
                                                    <div class="form-group" :class="{'has-danger': errors.name}">
                                                        <label class="control-label">Descripción</label>
                                                        <el-input v-model="form.name" dusk="name" @focus="$event.target.select()"></el-input>
                                                        <small class="form-control-feedback" v-if="errors.name" v-text="errors.name[0]"></small>
                                                    </div>
                                                </div>


                                                <div class="short-div col-md-4">
                                                    <div class="form-group" :class="{'has-danger': errors.unit_type_id}">
                                                        <label class="control-label">Unidad</label>
                                                        <el-select v-model="form.unit_type_id" dusk="unit_type_id" @focus="$event.target.select()">
                                                            <el-option v-for="option in unit_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                                        </el-select>
                                                        <small class="form-control-feedback" v-if="errors.unit_type_id" v-text="errors.unit_type_id[0]"></small>
                                                    </div>
                                                </div>
                                                <div class="short-div col-md-8">

                                                     <div class="form-group" >
                                                        <label class="control-label">Almacén
                                                        </label>
                                                        <el-input v-model="warehouse.description" readonly @focus="$event.target.select()"></el-input>
                                                    </div>

                                                </div>
                                                <div class="short-div col-md-4">

                                                     <div class="form-group" :class="{'has-danger': errors.internal_id}">
                                                        <label class="control-label">Código Interno
                                                            <el-tooltip class="item" effect="dark" content="Código interno de la empresa para el control de sus productos" placement="top-start">
                                                                <i class="fa fa-info-circle"></i>
                                                            </el-tooltip>
                                                        </label>
                                                        <el-input v-model="form.internal_id" dusk="internal_id" @focus="$event.target.select()"></el-input>
                                                        <small class="form-control-feedback" v-if="errors.internal_id" v-text="errors.internal_id[0]"></small>
                                                    </div>

                                                </div>

                                                <div class="short-div col-md-4">
                                                    <div class="form-group" :class="{'has-danger': errors.stock}">
                                                        <label class="control-label">Stock Inicial</label>
                                                        <el-input v-model="form.stock" @focus="$event.target.select()"></el-input>
                                                        <small class="form-control-feedback" v-if="errors.stock" v-text="errors.stock[0]"></small>
                                                    </div>
                                                </div>
                                                <div class="short-div col-md-4">
                                                     <div class="form-group" :class="{'has-danger': errors.stock_min}">
                                                        <label class="control-label">Stock Mínimo</label>
                                                        <el-input v-model="form.stock_min" @focus="$event.target.select()"></el-input>
                                                        <small class="form-control-feedback" v-if="errors.stock_min" v-text="errors.stock_min[0]"></small>
                                                    </div>
                                                </div>
                                                <div class="short-div col-md-4">
                                                    <div :class="{'has-danger': errors.category_id}"
                                                         class="form-group">
                                                        <label class="control-label">Categoría</label>
                                                        <el-input v-if="form_category.add == true"
                                                                  v-model="form_category.name"
                                                                  @focus="$event.target.select()"
                                                                  dusk="item_code"
                                                                  style="margin-bottom:1.5%;"></el-input>

                                                        <el-select v-if="form_category.add == false"
                                                                   v-model="form.category_id"
                                                                   clearable
                                                                   filterable
                                                                   :filter-method="filterCategories"
                                                                   @visible-change="onCategoryDropdownChange"
                                                                   @keydown.enter.native.prevent="createCategoryFromSearch">
                                                            <el-option v-for="option in filteredCategories"
                                                                       :key="option.id"
                                                                       :label="option.name"
                                                                       :value="option.id"></el-option>
                                                            <template slot="empty">
                                                                <p v-if="loading_search" class="el-select-dropdown__empty">
                                                                    Cargando...
                                                                </p>
                                                                <p v-else-if="categorySearchQuery" class="el-select-dropdown__empty">
                                                                    No se encontraron resultados
                                                                </p>

                                                                <p v-else class="el-select-dropdown__empty">
                                                                    No hay categorías. <br> Escriba el nombre y presione Enter para crear
                                                                </p>

                                                                <div
                                                                    v-if="!loading_search && categorySearchQuery"
                                                                    class="el-select-dropdown__item new-option"
                                                                    @click.stop="createCategoryFromSearch"
                                                                >
                                                                    <span>Crear categoría "{{ categorySearchQuery }}"</span>
                                                                </div>
                                                            </template>
                                                        </el-select>
                                                        <small v-if="errors.category_id"
                                                               class="form-control-feedback"
                                                               v-text="errors.category_id[0]"></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>




                            </el-tab-pane>



                            <el-tab-pane label="Precio" name="second">

                                <div class="row">


                                    <div class="col-md-4">
                                        <div class="form-group" :class="{'has-danger': errors.sale_unit_price}">
                                            <label class="control-label">Precio Unitario (Venta) <small v-if="form.has_igv">(con IVA)</small> <small v-else>(sin IVA)</small><span class="text-danger">*</span></label>
                                            <el-input v-model="form.sale_unit_price" dusk="sale_unit_price" @input="calculatePercentageOfProfitBySale" @focus="$event.target.select()"></el-input>
                                            <small v-if="saleUnitPriceBreakdown" class="text-muted">{{ saleUnitPriceBreakdown }}</small>
                                            <small class="form-control-feedback" v-if="errors.sale_unit_price" v-text="errors.sale_unit_price[0]"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group" :class="{'has-danger': errors.sale_affectation_igv_type_id}">
                                            <label class="control-label">Tipo de afectación (Venta)</label>
                                            <el-select v-model="form.sale_affectation_igv_type_id" @change="changeAffectationIgvType">
                                                <el-option v-for="option in affectation_igv_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                            </el-select>
                                            <small class="form-control-feedback" v-if="errors.sale_affectation_igv_type_id" v-text="errors.sale_affectation_igv_type_id[0]"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group" :class="{'has-danger': errors.currency_type_id}">
                                            <label class="control-label">Moneda</label>
                                            <el-select v-model="form.currency_type_id" dusk="currency_type_id" @focus="$event.target.select()">
                                                <el-option v-for="option in currency_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                            </el-select>
                                            <small class="form-control-feedback" v-if="errors.currency_type_id" v-text="errors.currency_type_id[0]"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 center-el-checkbox">
                                        <div class="form-group" :class="{'has-danger': errors.calculate_quantity}">
                                            <el-checkbox v-model="form.calculate_quantity">Calcular cantidad por precio</el-checkbox><br>
                                            <small class="form-control-feedback" v-if="errors.calculate_quantity" v-text="errors.calculate_quantity[0]"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 center-el-checkbox" v-show="show_has_igv && !globalIgvHandling">
                                        <div class="form-group" :class="{'has-danger': errors.has_igv}">
                                            <el-checkbox v-model="form.has_igv">Incluye IVA</el-checkbox><br>
                                            <small class="form-control-feedback" v-if="errors.has_igv" v-text="errors.has_igv[0]"></small>
                                        </div>
                                    </div>





                                    <div class="col-md-12">
                                        <h5 class="separator-title">Campos adicionales</h5>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group" :class="{'has-danger': errors.purchase_unit_price}">
                                            <label class="control-label">Precio Unitario (Compra) <small v-if="purchaseIncludesIgv">(con IVA)</small> <small v-else>(sin IVA)</small></label>
                                            <el-input v-model="form.purchase_unit_price" dusk="purchase_unit_price" @input="calculatePercentageOfProfitByPurchase"></el-input>
                                            <small v-if="purchaseUnitPriceBreakdown" class="text-muted">{{ purchaseUnitPriceBreakdown }}</small>
                                            <small class="form-control-feedback" v-if="errors.purchase_unit_price" v-text="errors.purchase_unit_price[0]"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group" :class="{'has-danger': errors.purchase_affectation_igv_type_id}">
                                            <label class="control-label">Tipo de afectación (Compra)</label>
                                            <el-select v-model="form.purchase_affectation_igv_type_id" @focus="$event.target.select()">
                                                <el-option v-for="option in affectation_igv_types" :key="option.id" :value="option.id" :label="option.description"></el-option>
                                            </el-select>
                                            <small class="form-control-feedback" v-if="errors.purchase_affectation_igv_type_id" v-text="errors.purchase_affectation_igv_type_id[0]"></small>
                                            <el-checkbox v-if="!globalIgvHandling" v-model="form.purchase_has_igv">Precio de compra incluye IVA</el-checkbox>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group" :class="{'has-danger': errors.percentage_of_profit}">
                                            <label class="control-label">Porcentaje de ganancia (%)</label>
                                            <el-input v-model="form.percentage_of_profit" @input="calculatePercentageOfProfitByPercentage" @focus="$event.target.select()"></el-input>
                                            <small class="form-control-feedback" v-if="errors.percentage_of_profit" v-text="errors.percentage_of_profit[0]"></small>
                                        </div>
                                    </div>


                                </div>
                            </el-tab-pane>

                            <!-- ########## INICIO CAMBIO NELSON: RETIRO CÓDIGO SUNAT -->
                            <!-- Se retiró la pestaña UNSPSC porque su único control era el campo fiscal item_code. -->
                            <!-- ######### FIN CAMBIO NELSON: RETIRO CÓDIGO SUNAT -->

                        </el-tabs>
                    </div>


                </div>


            </div>
            <div class="form-actions text-end mt-4">
                <el-button class="second-buton me-2" @click.prevent="close()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
            </div>
        </form>
    </el-dialog>
</template>
<script>

import {mapActions, mapState} from "vuex/dist/vuex.mjs";
import {exactAmount} from "@helpers/exchange-rate-math";

    export default {
        props: ['showDialog', 'recordId', 'external'],

        computed: {
            ...mapState([
                'config',
            ]),
            globalIgvHandling() {
                if (this.config && this.config.global_igv_handling !== undefined) {
                    return !!this.config.global_igv_handling
                }
                return true
            },
            saleUnitPriceBreakdown() {
                return this.priceBreakdown('sale_unit_price', this.form.has_igv, this.form.sale_affectation_igv_type_id)
            },
            purchaseIncludesIgv() {
                return (this.form.purchase_has_igv !== undefined && this.form.purchase_has_igv !== null)
                    ? this.form.purchase_has_igv
                    : this.form.has_igv
            },
            purchaseUnitPriceBreakdown() {
                return this.priceBreakdown('purchase_unit_price', this.purchaseIncludesIgv, this.form.purchase_affectation_igv_type_id)
            },
        },
        data() {
            return {
                loading_submit: false,
                loading_search: false,
                headers: headers_token,
                warehouse:{},

                titleDialog: null,
                resource: 'items',
                errors: {},
                form: {},
                unit_types: [],
                currency_types: [],
                exact_tax_percentage: null,

                categories: [],
                filteredCategories: [],
                categorySearchQuery: '',
                form_category: {add: false, name: null, id: null},
                activeName: 'first',
                affectation_igv_types: [],
                show_has_igv:true,
                item_unit_type:{
                        id:null,
                        unit_type_id:null,
                        quantity_unit:0,
                        price1:0,
                        price2:0,
                        price3:0,
                        price_default:2,

                }
            }
        },
        async created() {
            this.loadConfiguration()

            await this.initForm()
            await this.$http.get(`/${this.resource}/tables`)
                .then(response => {

                    this.unit_types = response.data.unit_types
                    this.currency_types = response.data.currency_types
                    this.exact_tax_percentage = response.data.exact_tax_percentage

                    this.affectation_igv_types = response.data.affectation_igv_types
                    this.categories = response.data.categories || []
                    this.filteredCategories = this.categories
                    this.warehouse = (response.data.warehouse) ? response.data.warehouse:{id:1, establishment_id:1, description:'Almacén Oficina Principal'}

                    this.form.sale_affectation_igv_type_id =  (this.config.affectation_igv_type_id) ? this.config.affectation_igv_type_id : (this.affectation_igv_types.length > 0)?this.affectation_igv_types[0].id:null
                    this.form.purchase_affectation_igv_type_id = (this.affectation_igv_types.length > 0)?this.affectation_igv_types[0].id:null
                    this.$store.commit('setConfiguration', response.data.configuration)
                })
        },
        methods: {
            ...mapActions(['loadConfiguration']),
            priceBreakdown(field, includesTax, affectation) {
                const type = String(affectation || '')
                const exempt = ['20', '21', '30', '31', '32', '33', '34', '35', '36', '37', 'EXP'].includes(type)
                if (type !== '10' && !exempt) return null
                try {
                    const input = this.profitInput(this.form[field])
                    if (!input || !this.form.exact_profit_base || this.exact_tax_percentage == null) return null
                    const price = this.replayProfitEdits(this.form.exact_profit_sequence || [])[field]
                    if (input.decimal() !== price.decimal() || price.compare(0) <= 0) return null
                    const rate = exempt ? exactAmount(0) : exactAmount(this.exact_tax_percentage).dividedBy('100')
                    const base = includesTax ? price.dividedBy(exactAmount(1).plus(rate)) : price
                    const total = includesTax ? price : base.times(exactAmount(1).plus(rate))
                    const baseText = base.final(2), totalText = total.final(2)
                    const taxText = exactAmount(totalText).minus(baseText).final(2)
                    const currency = this.currency_types.find(row => row.id === this.form.currency_type_id)
                    const symbol = currency?.symbol || ({VES: 'Bs.', USD: '$'}[this.form.currency_type_id] || this.form.currency_type_id)
                    return `${baseText} + ${taxText} IVA = ${symbol} ${totalText}`
                } catch (_) { return null }
            },
            onSuccess(response, file, fileList) {
                if (response.success) {
                    this.form.image = response.data.filename
                    this.form.image_url = response.data.temp_image
                    this.form.temp_path = response.data.temp_path
                } else {
                    this.$message.error(response.message)
                }
            },
            initForm() {
                this.loading_submit = false,
                this.errors = {}
                this.form = {
                    id: null,
                    item_type_id: '01',
                    internal_id: null,
                    item_code: null,
                    item_code_gs1: null,
                    description: null,
                    second_name:null,
                    name:null,
                    category_id: null,
                    unit_type_id: 'UND',
                    currency_type_id: 'VES',
                    sale_unit_price: 0,
                    purchase_unit_price: 0,
                    exact_profit_base: {sale_unit_price:{n:'0',d:'1'},purchase_unit_price:{n:'0',d:'1'},percentage_of_profit:{n:'0',d:'1'}},
                    exact_profit_sequence: [],



                    suggested_price: 0,
                    sale_affectation_igv_type_id: null,
                    purchase_affectation_igv_type_id: null,
                    calculate_quantity: false,
                    stock: 0,
                    stock_min: 1,
                    has_igv: true,
                    purchase_has_igv: true,
                    item_unit_types:[],
                    percentage_of_profit: 0,
                    image: null,
                    image_url: null,
                    temp_path: null,

                }
                this.show_has_igv = true
            },
            changeAffectationIgvType(){

                let affectation_igv_type_exonerated = [20,21,30,31,32,33,34,35,36,37]
                let is_exonerated = affectation_igv_type_exonerated.includes((parseInt(this.form.sale_affectation_igv_type_id)));

                if(is_exonerated){
                    this.show_has_igv = false
                    this.form.has_igv = true
                }else{
                    this.show_has_igv = true
                }

            },
            resetForm() {
                this.initForm()
                this.activeName = 'first'
                this.form.sale_affectation_igv_type_id = (this.affectation_igv_types.length > 0)?this.affectation_igv_types[0].id:null
                this.form.purchase_affectation_igv_type_id = (this.affectation_igv_types.length > 0)?this.affectation_igv_types[0].id:null
            },
            create() {
                this.titleDialog = (this.recordId)? 'Editar Producto':'Nuevo Producto'
                if (this.recordId) {
                    this.$http.get(`/${this.resource}/record/${this.recordId}?fresh=1`)
                        .then(response => {
                            this.form = response.data.data
                            this.prepareExactProfitForm()
                            if (this.globalIgvHandling) {
                                this.form.has_igv = true
                            }
                            this.changeAffectationIgvType()
                        })
                }
            },
            loadRecord(){
                if (this.recordId) {
                    this.$http.get(`/${this.resource}/record/${this.recordId}?fresh=1`)
                        .then(response => {
                            this.form = response.data.data
                            this.prepareExactProfitForm()
                            this.changeAffectationIgvType()
                        })
                }
            },
            prepareExactProfitForm() {
                if (this.form.exact_profit_base) {
                    this.form.exact_profit_sequence = [];
                    delete this.form.exact_profit_unavailable;
                } else {
                    delete this.form.exact_profit_sequence;
                    this.form.exact_profit_unavailable = true;
                }
            },
            profitInput(value) {
                let text = String(value ?? '').trim();
                if (text.length > 64) return null;
                if (/^-?\d+\.$/.test(text)) text += '0';
                if (/^-?\.\d/.test(text)) text = text.replace(/^(-?)\./, (_, sign) => sign + '0.');
                try {
                    const value = exactAmount(text), limit = '1000000000000000000';
                    if (value.compare(limit) >= 0 || value.compare('-' + limit) <= 0 || value.compare(value.decimal()) !== 0) return null;
                    return value;
                } catch (_) { return null; }
            },
            markExactEdited(...fields) {
                this.form.exact_edited_fields = [...new Set([...(this.form.exact_edited_fields || []), ...fields])];
            },
            replayProfitEdits(sequence) {
                const base = this.form.exact_profit_base;
                let sale = exactAmount(base.sale_unit_price), cost = exactAmount(base.purchase_unit_price), margin = exactAmount(base.percentage_of_profit);
                for (const event of sequence) {
                    const entered = exactAmount(event.value);
                    if (event.field === 'sale_unit_price') {
                        sale = entered; margin = cost.compare(0) === 0 ? exactAmount(0) : sale.minus(cost).dividedBy(cost).times(100);
                    } else {
                        if (event.field === 'purchase_unit_price') cost = entered; else margin = entered;
                        sale = cost.times(margin.plus(100)).dividedBy(100);
                    }
                }
                return {sale_unit_price: sale, purchase_unit_price: cost, percentage_of_profit: margin};
            },
            recordProfitEdit(field) {
                if (!this.form.exact_profit_base) {
                    this.form.exact_profit_unavailable = true;
                    this.$message.error('Recargue el producto para editar sus precios con precisión.');
                    return;
                }
                const value = this.profitInput(this.form[field]);
                if (value === null) return;
                const sequence = [...(this.form.exact_profit_sequence || []), {field, value:value.decimal()}];
                if (sequence.length > 1024) { this.form.exact_profit_overflow = true; return; }
                const {sale_unit_price: sale, percentage_of_profit: margin} = this.replayProfitEdits(sequence);
                this.form.exact_profit_sequence = sequence;
                if (field === 'sale_unit_price') {
                    this.form.percentage_of_profit = margin.decimal();
                    this.markExactEdited('sale_unit_price', 'percentage_of_profit');
                } else {
                    this.form.sale_unit_price = sale.decimal();
                    this.markExactEdited(field, 'sale_unit_price');
                }
            },
            calculatePercentageOfProfitBySale() { this.recordProfitEdit('sale_unit_price'); },
            calculatePercentageOfProfitByPurchase() {
                if (this.form.percentage_of_profit === '') this.form.percentage_of_profit = '0';
                this.recordProfitEdit('purchase_unit_price');
            },
            calculatePercentageOfProfitByPercentage() {
                if (this.form.percentage_of_profit === '') this.form.percentage_of_profit = '0';
                this.recordProfitEdit('percentage_of_profit');
            },
            submit() {
                if (this.form.exact_profit_unavailable) {
                    this.$message.error('Recargue el producto para editar sus precios con precisión.');
                    return;
                }
                if (this.form.exact_profit_overflow) {
                    this.$message.error('Recargue el producto antes de continuar editando los precios.');
                    return;
                }
                for (const field of ['purchase_unit_price', 'sale_unit_price', 'percentage_of_profit']) {
                    if (this.profitInput(this.form[field]) === null) {
                        this.errors = {...this.errors, [field]: ['Indique un decimal válido de hasta 12 posiciones y 18 dígitos enteros.']};
                        return;
                    }
                }
                this.loading_submit = true
                if (this.globalIgvHandling) {
                    this.form.has_igv = true
                    this.form.purchase_has_igv = true
                }
                this.$http.post(`/${this.resource}`, this.form)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            if (this.external) {
                                this.$eventHub.$emit('reloadDataItems', response.data.id)
                            } else {
                                this.$eventHub.$emit('reloadData')
                            }
                            this.close()
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
            },
            close() {
                this.$emit('update:showDialog', false)
                this.resetForm()
            },


            filterCategories(query) {
                this.categorySearchQuery = query

                if (query) {
                    this.filteredCategories = this.categories.filter(category => {
                        return category.name.toLowerCase().includes(query.toLowerCase())
                    })
                } else {
                    this.filteredCategories = this.categories
                }
            },
            onCategoryDropdownChange(visible) {
                if (!visible) {
                    this.categorySearchQuery = ''
                } else {
                    this.filteredCategories = this.categories
                }
            },
            createCategoryFromSearch() {
                const categoryName = this.categorySearchQuery

                if (!categoryName || categoryName.trim() === '') {
                    return
                }

                this.form_category.name = categoryName

                this.$http.post(`/categories`, this.form_category)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            this.categories.push(response.data.data)
                            this.filteredCategories = this.categories

                            this.$nextTick(() => {
                                this.form.category_id = response.data.data.id
                            })

                            this.form_category.name = null
                            this.categorySearchQuery = ''
                        } else {
                            this.$message.error('No se guardaron los cambios')
                        }
                    })
                    .catch(error => {
                        this.$message.error('Error al crear la categoría')
                    })
            }
        }
    }
</script>
