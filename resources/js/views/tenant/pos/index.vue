<template>
    <div class="pos container-fluid p-0">
        <span class="module-title-marker" data-page-title="Punto de Venta"></span>
        <div class="row page-header pos-toolbar pe-0 no-gutters" style="min-height:48px">
            <Keypress
                key-event="keyup"
                :key-code="112"
                @success="handleFn112"
            />
            <div class="pos-toolbar__scanner ps-3 d-flex flex-column align-items-start justify-content-center">
                <el-switch
                    class="pos-toolbar__scanner-switch"
                    v-model="search_item_by_barcode"
                    active-text="Buscar con escáner de código de barras"
                    @change="changeSearchItemBarcode"
                >
                </el-switch>
                <div class="bar-code-checkbox pt-1" v-if="search_item_by_barcode">
                    <div class="pos-toolbar__option">
                        <el-checkbox
                            class="font-weight-bold"
                            v-model="search_item_by_barcode_presentation"
                            >Por presentación</el-checkbox
                        >
                    </div>
                    <div class="pos-toolbar__option">
                        <el-checkbox
                            class="font-weight-bold"
                            v-model="electronic_scale_barcode"
                        >
                            Balanza electrónica

                        <el-tooltip
                            class="item ms-1"
                            effect="dark"
                            placement="top-start"
                        >
                            <div slot="content">
                                <b
                                    >El código de barras generado por la balanza
                                    debe tener 16 caracteres:</b
                                ><br /><br />
                                - Los 5 primeros caracteres representan el
                                código de barras del producto.<br />
                                - Los 5 siguientes caracteres representan el
                                peso, los 2 primeros son el valor entero y los 3
                                siguientes son decimales.<br />
                                - Los 6 siguientes caracteres representan el
                                total, los 4 primeros son el valor entero y los
                                2 siguientes son decimales.<br />
                                <br />

                                <b>Ejemplo: Para el código 1000314964299280</b>
                                <br /><br />
                                <b>10003</b> = Código de barras del producto
                                <br />
                                <b>14964</b> = Peso = 14.964 <br />
                                <b>299280</b> = Total = 2992.80
                            </div>
                            <i class="fa fa-info-circle"></i>
                        </el-tooltip>
                        </el-checkbox>
                    </div>
                    <div class="pos-toolbar__option">
                        <el-checkbox
                            class="font-weight-bold"
                            v-model="barcode_stop_presentation"
                            >Seleccionar listado de precio</el-checkbox
                        >
                    </div>
                </div>
            </div>
            <div class="pos-toolbar__actions">
                <div class="pos-toolbar__actions-inner">
                    <div v-if="!configuration.enable_list_product" class="pos-toolbar__price">
                        <el-select
                            v-model="selected_option_price"
                            @change="onPriceOptionChange"
                            filterable
                        >
                            <el-option
                                v-for="option in price_options"
                                :key="option.id"
                                :label="option.description"
                                :value="option.id"
                            ></el-option>
                        </el-select>
                    </div>
                    <div class="pos-toolbar__views">
                        <el-button-group class="d-flex">
                            <el-tooltip
                                class="item"
                                effect="dark"
                                content="Todas las categorías"
                                placement="top-start"
                            >
                                <el-button
                                    type="button"
                                    @click="back()"
                                    class="btn btn-custom btn-sm me-2 me-sm-0"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M14 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M4 14m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M14 14m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /></svg>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip
                                class="item"
                                effect="dark"
                                content="Categorías y productos"
                                placement="top-start"
                            >
                                <el-button
                                    type="button"
                                    :disabled="place == 'cat2'"
                                    @click="setView('cat2')"
                                    class="btn btn-custom btn-sm me-2 me-sm-0"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" /><path d="M4 14m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z" /></svg>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip
                                class="item"
                                effect="dark"
                                content="Listado de todos los productos"
                                placement="top-start"
                            >
                                <el-button
                                    type="button"
                                    :disabled="place == 'cat3'"
                                    @click="setView('cat3')"
                                    class="btn btn-custom btn-sm me-2 me-sm-0"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 5a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14z" /><path d="M3 10h18" /><path d="M10 3v18" /></svg>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip
                                class="item"
                                effect="dark"
                                content="Regresar"
                                placement="top-start"
                            >
                                <el-button
                                    type="button"
                                    :disabled="place == 'cat'"
                                    @click="back()"
                                    class="btn btn-custom btn-sm me-2 me-sm-0"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 14l-4 -4l4 -4" /><path d="M5 10h11a4 4 0 1 1 0 8h-1" /></svg>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip
                                class="item"
                                effect="dark"
                                content="Configuración de vista"
                                placement="top-start"
                            >
                                <el-button
                                    type="button"
                                    @click="openPosViewSettings"
                                    class="btn btn-custom btn-sm me-2 me-sm-0"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg>
                                </el-button>
                            </el-tooltip>
                        </el-button-group>
                    </div>
                </div>
            </div>
            <div class="pos-toolbar__currency" v-if="currency_types.length > 1">
                <div class="h-100 d-flex align-items-center">
                    <p class="exchange-currency m-0">
                        T.C.
                        <span>S/ {{ form.exchange_rate_sale }}</span> Cambiar
                        Moneda
                        <a
                            class="btn btn-sm btn-default"
                            @click="selectCurrencyType"
                        >
                            <template v-if="form.currency_type_id == 'PEN'">
                                <strong>S/</strong>
                            </template>
                            <template v-else>
                                <strong>$</strong>
                            </template>
                            <!-- <i class="fa fa-usd" aria-hidden="true"></i> -->
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <div
            v-if="!is_payment"
            class="row col-lg-12 m-0 p-0 pos-container"
            :class="{'margin-top-switch-active': search_item_by_barcode}"
            v-loading="loading"
        >
            <div class="col-lg-8 col-md-6 px-4 hyo pt-2">
                <template v-if="!search_item_by_barcode">
                    <el-input
                        v-show="
                            place == 'prod' ||
                                place == 'cat2' ||
                                place == 'cat3'
                        "
                        placeholder="Buscar productos"
                        size="medium"
                        remote-search
                        v-model="input_item"
                        @input="searchItems"
                        @keyup.native="keyupTabCustomer"
                        @keyup.enter.native="keyupEnterAddItem"
                        class="m-bottom input-search-pos mt-0 pos-m-search"
                        ref="ref_search_items"
                    >
                        <template v-if="validteCreateProduct">
                            <el-button
                                slot="append"
                                @click.prevent="showDialogNewItem = true"
                                class="btn-add-product-pos pos-m-new-item"
                                >Nuevo Producto</el-button
                            >
                        </template>
                    </el-input>
                </template>

                <template v-else>
                    <el-input
                        v-show="
                            place == 'prod' ||
                                place == 'cat2' ||
                                place == 'cat3'
                        "
                        placeholder="Buscar productos"
                        size="medium"
                        v-model="input_item"
                        @change="searchItemsBarcode"
                        @keyup.native="keyupTabCustomer"
                        ref="ref_search_items"
                        class="m-bottom input-search-pos mt-0 pos-m-search"
                        @focus="searchFromBarcode = true"
                        @blur="searchFromBarcode = false"
                    >
                        <template v-if="validteCreateProduct">
                            <el-button
                                slot="append"
                                @click.prevent="showDialogNewItem = true"
                                class="pos-m-new-item"
                                >Nuevo Producto</el-button
                            >
                        </template>
                    </el-input>
                </template>

                <div v-if="place == 'cat2'" class="container testimonial-group">
                    <div class="row text-center flex-nowrap">
                        <div
                            v-for="(item, index) in categories"
                            @click="filterCategorie(item.id, true)"
                            :style="{ backgroundColor: item.color }"
                            :key="index"
                            class="col-sm-3 pointer col-sm-3-name"
                        >
                            {{ item.name }}
                        </div>
                    </div>
                </div>
                <br />

                <div v-if="place == 'cat'" class="row no-gutters">
                    <template v-for="(item, index) in categories">
                        <div class="col" :key="index">
                            <div
                                @click="filterCategorie(item.id)"
                                class="card p-0 m-0 mb-1 me-1 text-center"
                            >
                                <div
                                    :style="{ backgroundColor: item.color }"
                                    class="card-body pointer"
                                    style="font-weight: bold;color: white;font-size: 18px;"
                                >
                                    {{ item.name }}
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div
                    v-if="place == 'prod' || place == 'cat2'"
                    class="product-pos-container"
                    :class="layout_mode"
                >
                    <template v-for="(item, index) in items">
                        <div :key="index">
                            <section
                                class="card product-item"
                                :class="{ 'pos-m-in-cart': cartQty(item) > 0 }"
                            >
                                <div
                                    v-if="cartQty(item) > 0"
                                    class="pos-m-card-qty"
                                    :class="{
                                        'is-busy': card_busy_id === item.item_id,
                                        'is-flash': card_flash_id === item.item_id
                                    }"
                                >
                                    <button
                                        type="button"
                                        class="pos-m-card-qty__btn"
                                        @click.stop="cardRemoveItem(item)"
                                    >&minus;</button>
                                    <span class="pos-m-card-qty__num">{{ cartQtyLabel(item) }}</span>
                                    <button
                                        type="button"
                                        class="pos-m-card-qty__btn"
                                        @click.stop="cardAddItem(item, index)"
                                    >+</button>
                                </div>
                                <div
                                    class="card-body pointer px-2 pt-2"
                                    @click="cardAddItem(item, index)"
                                >
                                    <!-- <p
                                        class="font-weight-semibold mb-0"
                                        v-if="DescriptionLength(item) > 50"
                                        data-toggle="tooltip"
                                        data-placement="top"
                                        :title="item.description"
                                    >
                                        {{ item.description.substring(0, 50) }}
                                    </p>
                                    <p
                                        class="font-weight-semibold mb-0"
                                        v-if="DescriptionLength(item) <= 50"
                                    >
                                        {{ item.description }}
                                    </p> -->
                                    <div
                                        class="pos-card-media"
                                        :class="[
                                            posImageAspectClass,
                                            posImageFitClass
                                        ]"
                                    >
                                        <img
                                            :src="item.image_url"
                                            class="img-thumbail img-custom"
                                        />
                                        <el-tooltip
                                            v-if="item.sets.length > 0"
                                            class="item"
                                            effect="dark"
                                            :content="
                                                item.sets.flat().join(',\n')
                                            "
                                            placement="bottom"
                                        >
                                            <span class="pos-card-media__badge"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 16.5l-5 -3l5 -3l5 3v5.5l-5 3z" /><path d="M2 13.5v5.5l5 3" /><path d="M7 16.545l5 -3.03" /><path d="M17 16.5l-5 -3l5 -3l5 3v5.5l-5 3z" /><path d="M12 19l5 3" /><path d="M17 16.5l5 -3" /><path d="M12 13.5v-5.5l-5 -3l5 -3l5 3v5.5" /><path d="M7 5.03v5.455" /><path d="M12 8l5 -3" /></svg></span>
                                        </el-tooltip>
                                    </div>
                                    <p
                                        class="text-muted mb-0 py-1"
                                        style="display: flex; justify-content: space-between; align-items: center;"
                                    >
                                        <small class="text-primary">{{
                                            item.internal_id
                                        }}</small>

                                        <small
                                            class="measuring-unit text-end"
                                            >
                                            <el-tag v-if="item.variations_count > 0" size="mini" class="me-1">
                                                {{ item.variations_count }} var.
                                            </el-tag>
                                            <el-tag type="primary" size="mini">
                                                {{ item.unit_type_id }}
                                            </el-tag>
                                            </small
                                        >

                                        <!-- <el-popover v-if="item.warehouses" placement="right" width="280"  trigger="hover">
                      <el-table  :data="item.warehouses">
                        <el-table-column width="150" property="warehouse_description" label="Ubicación"></el-table-column>
                        <el-table-column width="100" property="stock" label="Stock"></el-table-column>
                      </el-table>
                      <el-button slot="reference"><i class="fa fa-search"></i></el-button>
                    </el-popover> -->
                                    </p>
                                    <span
                                        v-if="
                                            configuration.show_complete_name_pos
                                        "
                                        class="font-weight-semibold mb-0 d-flex justify-content-center product-name-description "
                                    >
                                        {{ item.description }}
                                    </span>
                                    <span
                                        v-else
                                        class="font-weight-semibold mb-0 d-flex justify-content-center product-name-description "
                                    >
                                        {{ item.description.substring(0, 50) }}
                                    </span>
                                </div>
                                <div class="card-footer pointer text-center">
                                    <!-- <button type="button" class="btn waves-effect waves-light btn-xs btn-danger m-1__2" @click="clickHistorySales(item.item_id)"><i class="fa fa-list"></i></button>
                  <button type="button" class="btn waves-effect waves-light btn-xs btn-success m-1__2" @click="clickHistoryPurchases(item.item_id)"><i class="fas fa-cart-plus"></i></button> -->
                                    <template v-if="!item.edit_unit_price">
                                        <h5
                                            class="font-weight-semibold text-center"
                                        >
                                            {{ item.currency_type_symbol }}
                                            {{ itemSetSaleUnitPrice(item) }}
                                            <button
                                                v-if="
                                                    configuration.options_pos &&
                                                        edit_unit_price
                                                "
                                                type="button"
                                                class="pos-card-action pos-card-action--edit edit-price"
                                                @click="
                                                    clickOpenInputEditUP(index)
                                                "
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4" /><path d="M13.5 6.5l4 4" /></svg>
                                            </button>
                                        </h5>
                                    </template>
                                    <template v-else>
                                        <div class="pos-price-edit">
                                            <el-input
                                                min="0"
                                                inputmode="decimal"
                                                v-model="item.edit_sale_unit_price"
                                                class="pos-price-edit__input"
                                                size="mini"
                                                @focus="valueInputSelect"
                                                @click.native="valueInputSelect"
                                            >
                                            </el-input>
                                            <button
                                                type="button"
                                                class="pos-price-edit__btn is-confirm"
                                                title="Guardar precio"
                                                @click="
                                                    clickEditUnitPriceItem(
                                                        index
                                                    )
                                                "
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="pos-price-edit__btn is-cancel"
                                                title="Cancelar"
                                                @click="
                                                    clickCancelUnitPriceItem(
                                                        index
                                                    )
                                                "
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                                <div
                                    v-if="configuration.options_pos"
                                    class=" card-footer btn-group flex-wrap configuration-options"
                                >
                                    <!-- <el-popover v-if="item.warehouses" placement="right" width="280"  trigger="hover">
                    <el-table  :data="item.warehouses">
                      <el-table-column width="150" property="warehouse_description" label="Ubicación"></el-table-column>
                      <el-table-column width="100" property="stock" label="Stock"></el-table-column>
                    </el-table>
                    <button type="button" style="width:100% !important;" slot="reference" class="btn btn-xs btn-default " @click="clickHistorySales(item.item_id)"><i class="fa fa-search"></i></button>
                  </el-popover> -->
                                    <!--<el-tooltip class="item" effect="dark" content="Visualizar stock" placement="bottom-end">
                    <button type="button" style="width:25% !important;"   class="btn btn-xs btn-primary-pos" @click="clickWarehouseDetail(item)">
                      <i class="fa fa-search"></i>
                    </button>
                  </el-tooltip>

                  <el-tooltip class="item" effect="dark" content="Visualizar historial de ventas del producto (precio venta) y cliente" placement="bottom-end">
                    <button type="button" style="width:25% !important;"   class="btn btn-xs btn-primary-pos" @click="clickHistorySales(item.item_id)"><i class="fa fa-list"></i></button>
                  </el-tooltip>

                  <el-tooltip class="item" effect="dark" content="Visualizar historial de compras del producto (precio compra)" placement="bottom-end">
                    <button type="button" style="width:25% !important;"  class="btn btn-xs btn-primary-pos" @click="clickHistoryPurchases(item.item_id)"><i class="fas fa-cart-plus"></i></button>
                  </el-tooltip>

                  <el-popover
                    placement="top-start"
                    title="Title"
                    width="400"
                    trigger="hover"
                    content="this is content, this is content, this is content">
                    <el-button slot="reference">Hov</el-button>
                </el-popover>-->

                                    <el-row style="width:100%">
                                        <el-col :span="6">
                                            <el-tooltip
                                                class="item"
                                                effect="dark"
                                                content="Ver stock"
                                                placement="bottom-end"
                                            >
                                                <button
                                                    style="width:100%"
                                                    type="button"
                                                    class="pos-card-action"
                                                    @click="
                                                        clickWarehouseDetail(
                                                            item
                                                        )
                                                    "
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" /><path d="M12 12l8 -4.5" /><path d="M12 12l0 9" /><path d="M12 12l-8 -4.5" /><path d="M16 5.25l-8 4.5" /></svg>
                                                </button>
                                            </el-tooltip>
                                        </el-col>
                                        <el-col
                                            :span="6"
                                            v-if="canSeeHistoryPurchase"
                                        >
                                            <el-tooltip
                                                class="item"
                                                effect="dark"
                                                content="Ver historial de ventas (precio venta) y cliente"
                                                placement="bottom-end"
                                            >
                                                <button
                                                    type="button"
                                                    style="width:100%;"
                                                    class="pos-card-action"
                                                    @click="
                                                        clickHistorySales(
                                                            item.item_id
                                                        )
                                                    "
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 8l0 4l2 2" /><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5" /></svg>
                                                </button>
                                            </el-tooltip>
                                        </el-col>
                                        <el-col
                                            :span="6"
                                            v-if="canSeePriceCost"
                                        >
                                            <el-tooltip
                                                class="item"
                                                effect="dark"
                                                content="Ver historial de compras (precio compra)"
                                                placement="bottom-end"
                                            >
                                                <button
                                                    type="button"
                                                    style="width:100%"
                                                    class="pos-card-action"
                                                    @click="
                                                        clickHistoryPurchases(
                                                            item.item_id
                                                        )
                                                    "
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17h-11v-14h-2" /><path d="M6 5l14 1l-1 7h-13" /></svg>
                                                </button>
                                            </el-tooltip>
                                        </el-col>
                                        <el-col :span="6">
                                            <el-tooltip
                                                v-if="priceOptionsCount(item) > 0"
                                                class="item"
                                                effect="dark"
                                                content="Ver precios disponibles"
                                                placement="bottom-end"
                                            >
                                                <el-popover
                                                    placement="top"
                                                    width="370"
                                                    trigger="click"
                                                >
                                                    <div class="el-popover__title d-flex justify-content-between">
                                                        Precios
                                                        <el-tag v-if="priceOptionsCount(item) > 0">
                                                            {{ priceOptionsCount(item) }} OPCIONES
                                                        </el-tag>
                                                        <el-tag v-else>
                                                            SIN REGISTROS
                                                        </el-tag>
                                                    </div>
                                                    <table
                                                        v-if="item.item_unit_types"
                                                        class="table table-sm mb-0 table-prices-popover">
                                                        <thead>
                                                            <tr>
                                                                <td class="text-start">Precio</td>
                                                                <td class="text-start">Unidad</td>
                                                                <td class="text-start">Descripción</td>
                                                                <td class="text-end"></td>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <template v-if="item.item_unit_types.length == 1">
                                                                <template v-for="(price, _index) in item.item_unit_types[0].prices">
                                                                    <tr v-if="Number(price.price) > 0">
                                                                        <td class="text-start font-weight-semibold">
                                                                            {{ currency_type.symbol }}
                                                                            {{ price.price }}
                                                                        </td>
                                                                        <td class="text-start">
                                                                            {{ item.item_unit_types[0].unit_type_id }}
                                                                        </td>
                                                                        <td class="text-start">
                                                                            {{ item.item_unit_types[0].description }}
                                                                        </td>
                                                                        <td class="text-end">
                                                                            <button
                                                                                @click="
                                                                                    setPriceItem(
                                                                                        price,
                                                                                        index
                                                                                    )
                                                                                "
                                                                                type="button"
                                                                                class="btn btn-sm btn-custom"
                                                                                :class="{'btn-success': price.selected}"
                                                                            >
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                </template>
                                                            </template>
                                                            <template v-else-if="item.item_unit_types.length == 0">
                                                                <tr>
                                                                    <td colspan="4" class="text-center">
                                                                        <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                                                                            <div class="circle-container p-2">
                                                                                <div class="circle-child p-2">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-credit-card text-muted svg-bounce"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3l0 -8" /><path d="M3 10l18 0" /><path d="M7 15l.01 0" /><path d="M11 15l2 0" /></svg>
                                                                                </div>
                                                                            </div>
                                                                            <div>
                                                                                <span class="small text-muted">
                                                                                    Aún no hay precios disponibles para este artículo.
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </template>
                                                            <template v-else>
                                                                <template v-for="(item_unit_type, _index) in item.item_unit_types">
                                                                    <template v-for="(price, _index_price) in item_unit_type.prices">
                                                                        <tr v-if="Number(price.price) > 0">
                                                                            <td class="text-start font-weight-semibold">
                                                                                {{ currency_type.symbol }}
                                                                                {{ price.price }}
                                                                            </td>
                                                                            <td class="text-start">
                                                                                {{ item_unit_type.unit_type_id }}
                                                                            </td>
                                                                            <td class="text-start">
                                                                                {{ item_unit_type.description }}
                                                                            </td>
                                                                            <td class="text-end">
                                                                                <button
                                                                                    @click="
                                                                                        setPriceItem(
                                                                                            price,
                                                                                            index
                                                                                        )
                                                                                    "
                                                                                    type="button"
                                                                                    class="btn btn-custom btn-sm"
                                                                                    :class="{'btn-success': price.selected}"
                                                                                >
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                                                                                </button>
                                                                            </td>
                                                                        </tr>

                                                                    </template>
                                                                </template>



                                                            </template>


                                                        </tbody>
                                                    </table>
                                                    <!-- <el-table
                                                        v-if="item.item_unit_types"
                                                        :data="item.item_unit_types"
                                                    >
                                                        <el-table-column
                                                            width="90"
                                                            label="Precio"
                                                        >
                                                            <template
                                                                slot-scope="{
                                                                    row
                                                                }"
                                                            >
                                                                <template v-for="p in row">
                                                                    <span
                                                                        v-if="Number(p.price) > 0"
                                                                    >
                                                                        {{
                                                                            p.price
                                                                        }}
                                                                    </span>

                                                                </template>
                                                            </template>
                                                        </el-table-column>
                                                        <el-table-column
                                                            width="80"
                                                            label="Unidad"
                                                            property="unit_type_id"
                                                        ></el-table-column>
                                                        <el-table-column
                                                            width="120"
                                                            label="Descripción"
                                                            property="description"
                                                        ></el-table-column>

                                                        <el-table-column
                                                            width="80"
                                                            label=""
                                                        >
                                                            <template
                                                                slot-scope="{
                                                                    row
                                                                }"
                                                            >
                                                                <button
                                                                    @click="
                                                                        setPriceItem(
                                                                            row,
                                                                            index
                                                                        )
                                                                    "
                                                                    type="button"
                                                                    class="btn btn-custom btn-xs"
                                                                >
                                                                    <i
                                                                        class="fas fa-check"
                                                                    ></i>
                                                                </button>
                                                            </template>
                                                        </el-table-column>
                                                    </el-table> -->
                                                    <button
                                                        slot="reference"
                                                        type="button"
                                                        style="width:100%"
                                                        class="pos-card-action"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7.5 7.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M3 6v5.172a2 2 0 0 0 .586 1.414l7.71 7.71a2.41 2.41 0 0 0 3.408 0l5.592 -5.592a2.41 2.41 0 0 0 0 -3.408l-7.71 -7.71a2 2 0 0 0 -1.414 -.586h-5.172a3 3 0 0 0 -3 3z" /></svg>
                                                    </button>
                                                </el-popover>
                                            </el-tooltip>
                                            <el-tooltip
                                                v-else
                                                class="item"
                                                effect="dark"
                                                content="Sin lista de precios"
                                                placement="bottom-end"
                                            >
                                                <span class="pos-card-action is-disabled" style="width:100%">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7.5 7.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M3 6v5.172a2 2 0 0 0 .586 1.414l7.71 7.71a2.41 2.41 0 0 0 3.408 0l5.592 -5.592a2.41 2.41 0 0 0 0 -3.408l-7.71 -7.71a2 2 0 0 0 -1.414 -.586h-5.172a3 3 0 0 0 -3 3z" /></svg>
                                                </span>
                                            </el-tooltip>
                                        </el-col>
                                    </el-row>
                                </div>
                            </section>
                        </div>
                    </template>
                </div>

                <table-items
                    ref="table_items"
                    @clickAddItem="clickAddItem"
                    @escape="onTableEscape"
                    @clickWarehouseDetail="clickWarehouseDetail"
                    @clickHistorySales="clickHistorySales"
                    @clickHistoryPurchases="clickHistoryPurchases"
                    v-if="place == 'cat3'"
                    :records="items"
                    :typeUser="typeUser"
                    :visibleTagsCustomer="focusClienteSelect"
                    :searchFromBarcode.sync="search_item_by_barcode"
                ></table-items>

                <div v-if="place == 'prod' || place == 'cat2'" class="row">
                    <div class="col-md-12 text-center">
                        <el-pagination
                            @current-change="getRecords"
                            layout="total, prev, pager, next"
                            :total="pagination.total"
                            :current-page.sync="pagination.current_page"
                            :page-size="pagination.per_page"
                        >
                        </el-pagination>
                    </div>
                </div>
            </div>
            <aside
                class="col-lg-4 col-md-6 pos-cart"
                :class="{ 'pos-m-cart-open': show_cart_mobile }"
            >
                <div class="pos-cart__body">
                    <div v-if="form.items.length === 0" class="pos-cart__empty">
                        <svg xmlns="http://www.w3.org/2000/svg" width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17h-11v-14h-2" /><path d="M6 5l14 1l-1 7h-13" /></svg>
                        <p class="pos-cart__empty-title">El carrito está vacío</p>
                        <p class="pos-cart__empty-text">
                            Busca o selecciona un producto para agregarlo a la venta.
                        </p>
                    </div>

                    <ul v-else class="pos-cart__list">
                        <li
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="pos-cart-item"
                        >
                            <div class="pos-cart-item__top">
                                <p class="pos-cart-item__name" :title="item.item.description">
                                    {{ item.item.description }}
                                    <template v-if="item.presentation &&
                                        item.presentation.hasOwnProperty(
                                            'description'
                                        )
                                     " >
                                     {{ item.item.presentation
                                                  .description
                                      }}
                                    </template>
                                </p>
                                <button
                                    type="button"
                                    class="pos-cart-item__remove"
                                    title="Quitar producto"
                                    @click="clickDeleteItem(item, index)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                </button>
                            </div>

                            <div class="pos-cart-item__meta">
                                <span class="pos-cart-item__unit">{{ item.unit_type_id }}</span>
                                <span class="pos-cart-item__unit-price">
                                    {{ currency_type.symbol }} {{ rowUnitPrice(item) }} c/u
                                </span>
                                <small
                                    class="pos-cart-item__sets"
                                    v-html="nameSets(item.item_id)"
                                ></small>
                            </div>

                            <div class="pos-cart-item__bottom">
                                <div class="pos-qty">
                                    <button
                                        type="button"
                                        class="pos-qty__btn"
                                        title="Quitar una unidad"
                                        @click="changeCartQuantity(item, index, -1)"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /></svg>
                                    </button>
                                    <el-input
                                        class="pos-qty__input"
                                        inputmode="decimal"
                                        v-model="item.item.aux_quantity"
                                        @focus="valueInputSelect"
                                        @click.native="valueInputSelect"
                                        @input="
                                                clickAddItem(
                                                    item,
                                                    index,
                                                    true,
                                                )
                                        "
                                        @keyup.enter.native="
                                            keyupEnterQuantity
                                        "
                                    ></el-input>
                                    <button
                                        type="button"
                                        class="pos-qty__btn"
                                        title="Agregar una unidad"
                                        @click="changeCartQuantity(item, index, 1)"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                                    </button>
                                </div>

                                <div class="pos-cart-item__total">
                                    <template v-if="edit_unit_price">
                                        <span class="pos-cart-item__total-edit">
                                            <span class="pos-cart-item__currency">
                                                {{ currency_type.symbol }}
                                            </span>
                                            <el-input
                                                v-model="item.total"
                                                size="mini"
                                                inputmode="decimal"
                                                @focus="valueInputSelect"
                                                @click.native="valueInputSelect"
                                                @blur="changeRowTotal(index)"
                                                :readonly="!edit_unit_price && !item.item.calculate_quantity"
                                            ></el-input>
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="pos-cart-item__total-text">
                                            {{ currency_type.symbol }} {{ rowTotal(item) }}
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <footer class="pos-cart__footer" v-if="form.items.length > 0">
                    <div class="pos-cart__footer-top">
                        <span class="pos-cart__count">
                            {{ form.items.length }}
                            {{ form.items.length === 1 ? 'producto' : 'productos' }}
                        </span>
                        <button
                            type="button"
                            class="pos-m-cart-toggle"
                            @click="show_cart_mobile = !show_cart_mobile"
                        >
                            {{ show_cart_mobile ? 'Ocultar carrito' : 'Ver carrito' }}
                            <span class="pos-m-cart-toggle__badge">
                                {{ form.items.length }}
                            </span>
                        </button>
                        <button
                            type="button"
                            class="pos-cart__clear"
                            @click="clickClearCart"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                            Vaciar carrito
                        </button>
                    </div>

                    <div class="pos-cart__customer"
                         :class="{ 'is-required': !form.customer_id }">
                        <div class="pos-cart__customer-row">
                            <el-select
                                ref="select_person"
                                v-model="form.customer_id"
                                filterable
                                remote
                                reserve-keyword
                                :remote-method="searchCustomers"
                                :loading="loading_customers"
                                clearable
                                placeholder="Seleccione un cliente"
                                loading-text="Buscando..."
                                no-data-text="Sin coincidencias"
                                popper-class="pos-customer-dropdown"
                                @visible-change="visibleChangeCustomer"
                                @change="changeCustomer"
                                @keyup.native="keyupCustomer"
                                @keyup.enter.native="keyupEnterCustomer"
                                @focus="focusClienteSelect = true"
                                @blur="focusClienteSelect = false"
                            >
                                <el-option
                                    v-for="option in all_customers"
                                    :key="option.id"
                                    :label="option.description"
                                    :value="option.id"
                                >
                                    <span class="pos-customer-option__text">
                                        <span class="pos-customer-option__track">
                                            <span class="pos-customer-option__chunk">{{ option.description }}</span><span
                                                class="pos-customer-option__chunk pos-customer-option__chunk--clone"
                                                aria-hidden="true"
                                            >{{ option.description }}</span>
                                        </span>
                                    </span>
                                </el-option>
                            </el-select>
                            <el-tooltip
                                class="item"
                                effect="dark"
                                content="Registrar nuevo cliente"
                                placement="top"
                            >
                                <button
                                    type="button"
                                    class="pos-cart__icon-btn"
                                    @click.prevent="showDialogNewPerson = true"
                                >
                                    <i class="fas fa-plus"></i>
                                </button>
                            </el-tooltip>
                        </div>
                    </div>

                    <div class="pos-cart__totals">
                        <div v-if="form.total_exonerated > 0" class="pos-cart__total-row">
                            <span>Op. exoneradas</span>
                            <span>{{ currency_type.symbol }} {{ money(form.total_exonerated) }}</span>
                        </div>
                        <div v-if="form.total_free > 0" class="pos-cart__total-row">
                            <span>Op. gratuitas</span>
                            <span>{{ currency_type.symbol }} {{ money(form.total_free) }}</span>
                        </div>
                        <div v-if="form.total_unaffected > 0" class="pos-cart__total-row">
                            <span>Op. inafectas</span>
                            <span>{{ currency_type.symbol }} {{ money(form.total_unaffected) }}</span>
                        </div>
                        <div v-if="form.total_taxed > 0 && !isNrus" class="pos-cart__total-row">
                            <span>Op. gravada</span>
                            <span>{{ currency_type.symbol }} {{ money(form.total_taxed) }}</span>
                        </div>
                        <div v-if="form.total_igv > 0 && !isNrus" class="pos-cart__total-row">
                            <span>IGV</span>
                            <span>{{ currency_type.symbol }} {{ money(form.total_igv) }}</span>
                        </div>
                        <template v-if="form.has_retention && !isNrus">
                            <div
                                v-if="form.retention && form.retention.amount > 0"
                                class="pos-cart__total-row"
                            >
                                <span>M. retención ({{ configuration.igv_retention_percentage }}%)</span>
                                <span>{{ currency_type.symbol }} {{ money(form.retention.amount) }}</span>
                            </div>
                        </template>
                        <div v-if="form.total_isc > 0 && !isNrus" class="pos-cart__total-row">
                            <span>ISC</span>
                            <span>{{ currency_type.symbol }} {{ money(form.total_isc) }}</span>
                        </div>
                        <div v-if="form.total_plastic_bag_taxes > 0" class="pos-cart__total-row">
                            <span>ICBPER</span>
                            <span>{{ currency_type.symbol }} {{ money(form.total_plastic_bag_taxes) }}</span>
                        </div>

                        <div class="pos-cart__total-row pos-cart__total-row--grand">
                            <span>TOTAL</span>
                            <span>{{ currency_type.symbol }} {{ money(form.total) }}</span>
                        </div>

                    </div>

                    <button
                        type="button"
                        class="pos-cart__pay"
                        :class="{ 'is-disabled': !canPay }"
                        :disabled="!canPay"
                        :title="!form.customer_id ? 'Seleccione un cliente para cobrar' : ''"
                        @click="clickPayment"
                    >
                        <span class="pos-cart__pay-label">PAGAR</span>
                        <span class="pos-cart__pay-amount">
                            {{ currency_type.symbol }} {{ money(form.total) }}
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /><path d="M13 18l6 -6" /><path d="M13 6l6 6" /></svg>
                    </button>
                </footer>
            </aside>

            <person-form
                :showDialog.sync="showDialogNewPerson"
                type="customers"
                :input_person="input_person"
                :external="true"
                :document_type_id="form.document_type_id"
            ></person-form>

            <item-form
                :showDialog.sync="showDialogNewItem"
                :external="true"
            ></item-form>
        </div>
        <template v-else>
            <payment-form
                :is_payment.sync="is_payment"
                :form="form"
                :currency-type-id-active="form.currency_type_id"
                :currency-type-active="currency_type"
                :exchange-rate-sale="form.exchange_rate_sale"
                :customer="customer"
                :customer_email="customerEmail"
                :config="config"
                :soapCompany="soapCompany"
                :businessTurns="businessTurns"
                :is-print="isPrint"
                :globalDiscountTypeId="configuration.global_discount_type_id"
                :enabledTipsPos="configuration.enabled_tips_pos"
                :hidePdfViewDocuments="configuration.hide_pdf_view_documents"
                :enabledPointSystem="configuration.enabled_point_system"
                :affectation-igv-types="affectation_igv_types"
                :percentage-igv="percentage_igv"
                :configuration="configuration"
                :typeUser="typeUser"
                :authUser="config.user"
            ></payment-form>
        </template>

        <history-sales-form
            :showDialog.sync="showDialogHistorySales"
            :item_id="history_item_id"
            :customer_id="form.customer_id"
            :type="false"
        ></history-sales-form>

        <history-purchases-form
            :showDialog.sync="showDialogHistoryPurchases"
            :item_id="history_item_id"
        ></history-purchases-form>

        <warehouses-detail
            :showDialog.sync="showWarehousesDetail"
            :warehouses="warehousesDetail"
            :unit_type="unittypeDetail"
            :item_unit_types="[]"
            :variations="variationsDetail"
        >
        </warehouses-detail>

        <variations-modal
            :showDialog.sync="showDialogVariations"
            :parent="selectedVariationParent"
            @select="selectVariationFromModal"
        >
        </variations-modal>

        <item-unit-types
            :showDialog.sync="showDialogItemUnitTypes"
            :itemUnitTypes="itemUnitTypes"
        >
        </item-unit-types>

        <el-dialog
            title="Configuración de vista"
            :visible.sync="showDialogPosView"
            class="pos-view-dialog"
            width="440px"
        >
            <div class="pos-view-dialog__body">
                <label class="control-label"
                    >Relación de aspecto de la imagen</label
                >
                <el-radio-group
                    v-model="pos_view_form.pos_image_aspect_ratio"
                    class="pos-view-dialog__ratios"
                >
                    <div
                        v-for="ratio in pos_image_aspect_ratios"
                        :key="ratio.value"
                        class="pos-view-dialog__ratio"
                        :class="{
                            'is-active':
                                pos_view_form.pos_image_aspect_ratio ===
                                ratio.value
                        }"
                        @click="
                            pos_view_form.pos_image_aspect_ratio = ratio.value
                        "
                    >
                        <el-radio :label="ratio.value">
                            <span
                                class="pos-view-dialog__shape"
                                :class="
                                    'pos-view-dialog__shape--' +
                                        ratio.value.replace(':', '-')
                                "
                            ></span>
                            <span class="pos-view-dialog__ratio-text">{{
                                ratio.label
                            }}</span>
                        </el-radio>
                    </div>
                </el-radio-group>

                <label class="control-label pt-3"
                    >Cómo se acomoda la foto</label
                >
                <el-radio-group
                    v-model="pos_view_form.pos_image_fit"
                    class="pos-view-dialog__fits"
                >
                    <div
                        v-for="fit in pos_image_fits"
                        :key="fit.value"
                        class="pos-view-dialog__fit"
                        :class="{
                            'is-active':
                                pos_view_form.pos_image_fit === fit.value
                        }"
                        @click="pos_view_form.pos_image_fit = fit.value"
                    >
                        <el-radio :label="fit.value">
                            <span class="pos-view-dialog__fit-text">
                                <span class="pos-view-dialog__fit-title">{{
                                    fit.label
                                }}</span>
                                <small class="pos-view-dialog__fit-hint">{{
                                    fit.hint
                                }}</small>
                            </span>
                        </el-radio>
                    </div>
                </el-radio-group>

                <label class="control-label pt-3"
                    >Visualización de productos</label
                >
                <el-select
                    v-model="pos_view_form.colums_grid_item"
                    class="w-100"
                >
                    <el-option
                        v-for="option in pos_grid_options"
                        :key="option.value"
                        :label="option.label"
                        :value="option.value"
                    ></el-option>
                </el-select>
            </div>

            <span slot="footer" class="dialog-footer">
                <el-button @click="showDialogPosView = false"
                    >Cancelar</el-button
                >
                <el-button
                    type="primary"
                    :loading="loading_pos_view"
                    @click="savePosViewSettings"
                    >Guardar</el-button
                >
            </span>
        </el-dialog>
    </div>
</template>
<style>
.el-select-dropdown__item.hover {
    /* background-color: red; */
    background-color: #e6e9ee;
}

/* The heart of the matter */
.testimonial-group > .row {
    overflow-x: auto;
    white-space: nowrap;
    overflow-y: hidden;
}

.testimonial-group > .row > .col-sm-3-name {
    display: inline-block;
    float: none;
}

/* Decorations */
.col-sm-3-name {
    height: 70px;
    margin-right: 0.5%;
    color: white;
    font-size: 18px;
    padding-bottom: 20px;
    padding-top: 18px;
    font-weight: bold;
}

.card-block {
    min-height: 220px;
}

.ex1 {
    overflow-x: scroll;
}

.cat_c {
    width: 100px;
    margin: 1%;
    padding: 3px;
    font-weight: bold;
    color: white;
    min-height: 90px;
}

.cat_c p {
    color: white;
}

.c-width {
    width: 80px !important;
    padding: 0 !important;
    margin-right: 0 !important;
}

.el-select-dropdown {
    max-width: 80% !important;
    margin-right: 1% !important;
}
.el-select-dropdown.pos-customer-dropdown {
    max-width: none !important;
    margin-right: 0 !important;
    box-sizing: border-box;
}
.pos-customer-dropdown .el-scrollbar {
    overflow: visible;
}

.pos-customer-dropdown .el-select-dropdown__wrap,
.pos-customer-dropdown .el-scrollbar__wrap {
    margin-right: 0 !important;
    margin-bottom: 0 !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    max-height: 274px;
    scrollbar-width: thin;
}

.pos-customer-dropdown .el-scrollbar__bar {
    display: none;
}

.pos-customer-dropdown .el-select-dropdown__list {
    box-sizing: border-box;
}

.pos-customer-dropdown .pos-customer-option__text {
    display: block;
    width: 100%;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.pos-customer-dropdown .pos-customer-option__track {
    display: inline-block;
    white-space: nowrap;
}

.pos-customer-dropdown .pos-customer-option__chunk--clone {
    display: none;
}
.pos-customer-dropdown .pos-customer-option__text.is-marquee {
    text-overflow: clip;
}

.pos-customer-dropdown .pos-customer-option__text.is-marquee .pos-customer-option__chunk--clone {
    display: inline;
    padding-left: var(--marquee-gap, 40px);
}

.pos-customer-dropdown .pos-customer-option__text.is-marquee .pos-customer-option__track {
    will-change: transform;
    animation: pos-customer-option-marquee var(--marquee-duration, 6s) linear infinite;
}

@keyframes pos-customer-option-marquee {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(calc(-1 * var(--marquee-shift, 0px)));
    }
}

@media (prefers-reduced-motion: reduce) {
    .pos-customer-dropdown .pos-customer-option__text.is-marquee .pos-customer-option__track {
        animation: none;
    }
    .pos-customer-dropdown .pos-customer-option__text.is-marquee .pos-customer-option__chunk--clone {
        display: none;
    }
}

.el-input-group__append {
    padding: 0 10px !important;
}
.el-tooltip__popper {
    white-space: pre-line;
}
.product-pos-container {
    display: grid;
}

.product-pos-container.default {
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
    gap: 1rem;
}

.product-pos-container.comfortable {
    grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
    gap: 0.9rem;
}

.product-pos-container.compact {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.5rem;
}

.product-pos-container.stacked {
    grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
    gap: 0.25rem;
}
.pos-toolbar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto max-content;
    align-items: stretch;
}

.pos-toolbar__scanner {
    min-width: 0;
    display: grid;
    grid-template-columns: max-content minmax(0, 1fr);
    align-items: center;
    column-gap: 24px;
    padding-right: 20px;
}

.pos-toolbar__scanner-switch,
.pos-toolbar__option,
.pos-toolbar__currency {
    white-space: nowrap;
}

.bar-code-checkbox {
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: space-evenly;
    gap: 18px;
}

.pos-toolbar__option {
    min-width: 0;
}

.pos-toolbar .el-checkbox__label {
    max-width: none;
}

.pos-toolbar__actions {
    display: flex;
    align-items: center;
    padding: 0 18px;
    border-left: 1px solid rgba(47, 112, 86, 0.14);
}

.pos-toolbar__actions-inner {
    display: flex;
    align-items: center;
    gap: 16px;
}

.pos-toolbar__price {
    width: 205px;
}

.pos-toolbar__views .el-button-group {
    gap: 6px;
}

.pos-toolbar__currency {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 0 16px 0 18px;
    border-left: 1px solid rgba(47, 112, 86, 0.14);
}

.pos-toolbar__currency .exchange-currency {
    display: flex;
    align-items: center;
    gap: 5px;
}

@media only screen and (max-width: 1450px) and (min-width: 896px) {
    .pos-toolbar__scanner {
        grid-template-columns: minmax(0, 1fr);
        align-content: center;
        row-gap: 3px;
    }

    .bar-code-checkbox {
        justify-content: flex-start;
    }
}

@media only screen and (max-width: 1200px) {
    .bar-code-checkbox{
        display: flex;
        flex-direction: column;
    }
    .pos-container.margin-top-switch-active {
        margin-top: 60px !important;
    }
}
@media only screen and (max-width: 895px) {
    .bar-code-checkbox{
        display: flex;
        flex-direction: row;
    }
    .pos-container{
        margin-top: 110px !important;
    }
    .pos-container.margin-top-switch-active {
        margin-top: 182px !important;
    }
    .row.page-header {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    .pos-toolbar__scanner {
        order: 1;
        text-align: left;
        padding-left: 0;
    }
    .pos-toolbar__actions {
        order: 2;
        text-align: center;
        padding-right: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .pos-toolbar__currency {
        order: 3;
        text-align: right;
    }
    .pos-toolbar__scanner,
    .pos-toolbar__actions,
    .pos-toolbar__currency {
        width: 100%;
        max-width: 100%;
        flex: 0 0 100%;
        padding-left: 0;
        padding-right: 0;
    }
    .row.page-header .el-button-group {
        justify-content: center;
    }
    .exchange-currency {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        padding-right: 0;
    }
}
@media only screen and (max-width: 767px) {
    #main-wrapper {
        padding-top: 62px;
    }
    .pos-container, .pos-container.margin-top-switch-active{
        margin-top: -20px !important;
    }
}
@media (max-width: 767px) {
    .page-header {
        margin: 0px 0px 5px 0px;
    }
}
</style>
<style scoped>
.table-sm>:not(caption)>*>* {
    padding: 0;
}
.el-checkbox__label {
    display: inline-block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 150px;
    vertical-align: middle;
}
</style>
<script>
import Keypress from "vue-keypress";
import { calculateRowItem } from "../../../helpers/functions";
import PaymentForm from "./partials/payment.vue";
import ItemForm from "./partials/form.vue";
import { functions, exchangeRate } from "../../../mixins/functions";
import HistorySalesForm from "../../../../../modules/Pos/Resources/assets/js/views/history/sales.vue";
import HistoryPurchasesForm from "../../../../../modules/Pos/Resources/assets/js/views/history/purchases.vue";
import PersonForm from "../persons/form.vue";
import WarehousesDetail from "../items/partials/warehouses.vue";
import queryString from "query-string";
import TableItems from "./partials/table.vue";
import ItemUnitTypes from "./partials/item_unit_types.vue";
import VariationsModal from "./partials/variations_modal.vue";
import { mapState, mapActions } from "vuex/dist/vuex.mjs";

export default {
    props: [
        "configuration2",
        "configuration",
        "soapCompany",
        "businessTurns",
        "typeUser",
        "isPrint"
    ],
    components: {
        PaymentForm,
        ItemForm,
        HistorySalesForm,
        HistoryPurchasesForm,
        PersonForm,
        WarehousesDetail,
        ItemUnitTypes,
        VariationsModal,
        Keypress,
        TableItems
    },
    mixins: [functions, exchangeRate],

    data() {
        return {
            place: "cat",
            // Solo celular: despliega la lista del carrito dentro de la
            // barra fija inferior (en escritorio la lista siempre se ve)
            show_cart_mobile: false,
            // Producto cuya cantidad se está actualizando (la validación de
            // stock es una petición) y el que acaba de cambiar, para avisar
            // al usuario sin que tenga que mirar el número fijamente
            card_busy_id: null,
            card_flash_id: null,
            showDialogPosView: false,
            loading_pos_view: false,
            pos_image_aspect_ratios: [
                { value: "4:5", label: "4:5 Vertical" },
                { value: "5:4", label: "5:4 Horizontal" },
                { value: "1:1", label: "1:1 Cuadrado" }
            ],
            pos_image_fits: [
                {
                    value: "contain",
                    label: "Mostrar la foto completa",
                    hint:
                        "Se ve toda la foto, sin recortes. Puede quedar espacio a los lados."
                },
                {
                    value: "cover",
                    label: "Llenar el recuadro",
                    hint:
                        "La foto cubre todo el espacio. Se recortan los bordes que sobran."
                }
            ],
            pos_grid_options: [
                { value: 2, label: "Predeterminado" },
                { value: 3, label: "Cómodo" },
                { value: 4, label: "Compacto" },
                { value: 5, label: "Apilado" }
            ],
            // Lo que se está mostrando ahora en la grilla
            pos_view_settings: {
                pos_image_aspect_ratio: "1:1",
                pos_image_fit: "contain",
                colums_grid_item: 2
            },
            // Lo que se está editando en el diálogo, hasta que se guarde
            pos_view_form: {
                pos_image_aspect_ratio: "1:1",
                pos_image_fit: "contain",
                colums_grid_item: 2
            },
            showDialogItemUnitTypes: false,
            history_item_id: null,
            search_item_by_barcode: false,
            search_item_by_barcode_presentation: false,
            electronic_scale_barcode: false,
            electronic_scale_data: {},
            is_print: true,
            warehousesDetail: [],
            unittypeDetail: [],
            variationsDetail: [],
            showDialogVariations: false,
            selectedVariationParent: null,
            input_person: {},
            showDialogHistoryPurchases: false,
            showDialogHistorySales: false,
            showDialogNewPerson: false,
            showDialogNewItem: false,
            loading: false,
            is_payment: false, //aq
            // is_payment: true,//aq
            showWarehousesDetail: false,
            resource: "pos",
            recordId: null,
            input_item: "",
            items: [],
            all_items: [],
            customers: [],
            affectation_igv_types: [],
            all_customers: [],
            loading_customers: false,
            default_customer: null,
            customer_seed: [],
            customer_search_timer: null,
            customer_search_promise: null,
            customer_marquee_frame: null,
            establishment: null,
            currency_types: [],
            currency_type: {},
            form_item: {},
            customer: {},
            row: {},
            user: {},
            form: {},
            categories: [],
            colors: ["#1cb973", "#bf7ae6", "#fc6304", "#9b4db4", "#77c1f3"],
            pagination: {},
            category_selected: "",
            focusClienteSelect: false,
            itemUnitTypes: [],
            searchFromBarcode: false,
            barcode_stop_presentation: false,
            price_options: [
                {
                    id: 1,
                    description: "Precio principal"
                },
                {
                    id: "price1",
                    description: "Precio 1"
                },
                {
                    id: "price2",
                    description: "Precio 2"
                },
                {
                    id: "price3",
                    description: "Precio 3"
                }
            ],
            selected_option_price: null
        };
    },
    beforeDestroy() {
        this.cancelCustomerMarquee();
        if (this.customer_search_timer) {
            clearTimeout(this.customer_search_timer);
            this.customer_search_timer = null;
        }
    },
    async created() {
        await this.loadPriceOptions();
        this.loadConfiguration();
        this.loadPosViewSettings();
        this.enabledSearchItemByBarcode();
        this.$store.commit("setConfiguration", this.configuration2);

        await this.initForm();
        await this.getTables();
        await this.getPercentageIgv();
        this.events();

        await this.getFormPosLocalStorage();
        this.form.created_from_pos = true;
        this.form.show_terms_condition = true;
        const cfg = this.config || this.configuration || {};
        if (cfg.terms_condition_sale) {
            this.form.terms_condition = cfg.terms_condition_sale;
        }
        await this.initCurrencyType();
        this.customer = await this.getLocalStorageIndex("customer");

        // if (document.querySelector(".sidebar-toggle")) {
        //     document.querySelector(".sidebar-toggle").click();
        // }

        await this.selectDefaultCustomer();
        await this.enabledSearchItemByBarcode();
        await this.restoreViewPreference();
    },
    computed: {
        posImageAspectClass() {
            const ratio =
                this.pos_view_settings.pos_image_aspect_ratio || "1:1";
            return "pos-card-media--" + ratio.replace(":", "-");
        },
        posImageFitClass() {
            return (
                "pos-card-media--fit-" +
                (this.pos_view_settings.pos_image_fit || "contain")
            );
        },
        layout_mode() {
            const cols = parseInt(this.pos_view_settings.colums_grid_item, 10);
            switch (cols) {
                case 2:
                    return "default";
                case 3:
                    return "comfortable";
                case 4:
                    return "compact";
                case 5:
                case 6:
                    return "stacked";
                default:
                    return "default";
            }
        },
        // No se puede cobrar sin cliente: el botón queda inhabilitado hasta
        // que se seleccione uno
        canPay() {
            return this.form.total > 0 && !!this.form.customer_id;
        },
        ...mapState(["config"]),
        isNrus: function() {
            return !!(this.config && this.config.is_nrus);
        },
        canSeeHistoryPurchase: function() {
            if (this.typeUser !== "admin") {
                return this.configuration.pos_history;
            }
            return false;
        },
        canSeePriceCost: function() {
            if (this.typeUser !== "admin") {
                return this.configuration.pos_cost_price;
            }
            return true;
        },
        validteCreateProduct() {
            return (
                this.config.typeUser == "admin" ||
                (this.config.typeUser == "seller" &&
                    this.config.seller_can_create_product)
            );
        },
        classObjectCol() {
            let cols = this.pos_view_settings.colums_grid_item;

            let clase = "c3";
            switch (cols) {
                case 2:
                    clase = "50%";

                    break;
                case 3:
                    clase = "33.33%";

                    break;
                case 4:
                    clase = "25%";

                    break;
                case 5:
                    clase = "20%";

                    break;
                case 6:
                    clase = "16.66%";
                    break;
                default:
            }
            return {
                width: `${clase}`,
                padding: "5px"
            };
        },
        edit_unit_price() {
            return this.user && this.user.permission_edit_item_prices;
        },
        changeValuesElectronicScale() {
            return (
                this.electronic_scale_barcode &&
                this.electronic_scale_data.pass_validations
            );
        },
        customerEmail() {
            const customer = _.find(this.all_customers, c => String(c.id) === String(this.form.customer_id));
            console.log('found customer:', customer);
            return customer ? customer.email : null;
        }
    },
    methods: {
        enabledSearchItemByBarcode() {
            if (this.configuration.search_item_by_barcode) {
                this.search_item_by_barcode = true;
            } },
        changeRowTotal(index) {
            const item = this.form.items[index];
            let newTotal = parseFloat(item.total);
            let validated = false

            if (this.config.condition_sale_purchase_price_to_item) {

                if (newTotal < item.purchase_unit_price) {
                    validated = true
                    newTotal = item.item.sale_unit_price_original
                }

            }

            if (item.item.calculate_quantity) {
                this.blurCalculateQuantity(index);
                return;
            }

            this.form.items[index].total = newTotal
            const quantity = parseFloat(item.quantity);

            if (isNaN(newTotal) || isNaN(quantity) || quantity <= 0) {
                this.blurCalculateQuantity(index);
                return;
            }

            const newUnitPrice = newTotal / quantity;
            item.item.unit_price = newUnitPrice;
            item.item.sale_unit_price = item.item.has_igv
                ? newUnitPrice
                : newUnitPrice / (1 + this.percentage_igv);

            this.row = calculateRowItem(item, this.form.currency_type_id, 1, this.percentage_igv);
            this.row["unit_type_id"] = item.unit_type_id;
            this.form.items[index] = this.row;

            this.calculateTotal();
            this.setFormPosLocalStorage();


            if (validated) {
                return this.$message.error(
                    "El Precio Unitario debe ser mayor o igual al costo de compra"
                );

            }


        },
        ...mapActions(["loadConfiguration"]),
        /**
         * Cargar opciones de precio desde la API de price_labels activos
         */
        async loadPriceOptions() {
            try {
                const response = await this.$http.get('/price-labels/active');
                const labels = response.data.data || [];

                const mainLabel = (this.configuration && this.configuration.price1_label) ? this.configuration.price1_label : 'Precio principal';
                this.price_options = [
                    {
                        id: 1,
                        description: mainLabel,
                        price_label_id: null
                    }
                ];

                // Agregar las etiquetas de precio desde la API
                labels.forEach(label => {
                    this.price_options.push({
                        id: `price_label_${label.id}`,
                        description: label.label,
                        price_label_id: label.id
                    });
                });

                // Seleccionar el label marcado como default, o el primero como fallback
                const defaultLabel = labels.find(l => l.is_default);
                if (defaultLabel) {
                    this.selected_option_price = `price_label_${defaultLabel.id}`;
                } else if (this.price_options.length > 0) {
                    this.selected_option_price = this.price_options[0].id;
                }
            } catch (error) {
                console.error('Error al cargar price_options:', error);
                // Fallback a precio principal si falla la carga
                this.price_options = [
                    {
                        id: 1,
                        description: "Precio principal",
                        price_label_id: null
                    }
                ];
                this.selected_option_price = 1;
            }
        },
        enabledSearchItemByBarcode() {
            if (this.configuration.search_item_by_barcode) {
                this.search_item_by_barcode = true;
            }
        },
        enabledCategoriesProductsView() {
            if (this.configuration.enable_categories_products_view) {
                this.setView("cat2");
            }
        },
        // La vista elegida por el usuario persiste en el navegador
        // (recargas, nuevas ventas, salir y volver a entrar); si no hay
        // preferencia guardada se aplica la vista de la configuración
        async restoreViewPreference() {
            const saved = localStorage.getItem("pos_view_preference");
            if (saved === "cat2" || saved === "cat3") {
                await this.setView(saved);
            } else if (saved !== "cat") {
                this.enabledCategoriesProductsView();
            }
        },
        loadPosViewSettings() {
            const cfg = this.configuration || {};
            const ratio = this.pos_image_aspect_ratios.some(
                r => r.value === cfg.pos_image_aspect_ratio
            )
                ? cfg.pos_image_aspect_ratio
                : "1:1";

            const fit = this.pos_image_fits.some(
                f => f.value === cfg.pos_image_fit
            )
                ? cfg.pos_image_fit
                : "contain";

            this.pos_view_settings = {
                pos_image_aspect_ratio: ratio,
                pos_image_fit: fit,
                colums_grid_item: parseInt(cfg.colums_grid_item, 10) || 2
            };
        },
        openPosViewSettings() {
            this.pos_view_form = Object.assign({}, this.pos_view_settings);
            this.showDialogPosView = true;
        },
        savePosViewSettings() {
            this.loading_pos_view = true;

            this.$http
                .post(`/${this.resource}/view-settings`, this.pos_view_form)
                .then(response => {
                    if (!response.data.success) {
                        return this.$message.error(response.data.message);
                    }

                    this.pos_view_settings = {
                        pos_image_aspect_ratio:
                            response.data.data.pos_image_aspect_ratio,
                        pos_image_fit: response.data.data.pos_image_fit,
                        colums_grid_item: response.data.data.colums_grid_item
                    };
                    this.showDialogPosView = false;
                    this.$message.success(response.data.message);
                })
                .catch(error => {
                    if (error.response && error.response.status === 422) {
                        const errors = error.response.data.errors || {};
                        const first = Object.keys(errors)[0];
                        return this.$message.error(
                            first
                                ? errors[first][0]
                                : "No se pudo guardar la configuración de vista"
                        );
                    }

                    this.$message.error(
                        "No se pudo guardar la configuración de vista"
                    );
                })
                .finally(() => {
                    this.loading_pos_view = false;
                });
        },
        setFocusInInputSearch() {
            this.$nextTick(() => {
                this.initFocus();
            });
        },
        keyupEnterQuantity() {
            this.initFocus();
        },
        handleFn112(response) {
            this.search_item_by_barcode = !this.search_item_by_barcode;
        },
        handleFn113() {
            this.setView("cat3");
        },
        initFocus() {
            // En celular no se devuelve el foco al buscador: abre el teclado
            // en pantalla y el navegador hace scroll hasta arriba tras cada
            // producto agregado
            if (window.matchMedia("(max-width: 767.98px)").matches) return;
            this.$refs.ref_search_items.$el
                .getElementsByTagName("input")[0]
                .focus();
        },
        keyupTabCustomer(e) {
            // console.log(e.keyCode)
            // El selector de cliente sólo existe cuando el carrito tiene productos.
            if (e.keyCode === 9 && this.$refs.select_person) {
                this.$refs.select_person.$el
                    .getElementsByTagName("input")[0]
                    .focus();
            }
        },
        keyupEnterAddItem() {
            if (this.place == "cat3") {
                return false;
            }

            if (this.items.length == 1) {
                if (
                    this.items[0].unit_type.length > 0 &&
                    this.configuration.select_available_price_list
                ) {
                    // console.log(this.configuration.select_available_price_list)
                    this.itemUnitTypes = this.items[0].unit_type;
                    this.showDialogItemUnitTypes = true;
                } else {
                    this.clickAddItem(this.items[0], 0);
                    this.filterItems();
                    this.cleanInput();
                }
            } else {
                this.$message.warning(
                    "No puede añadir directamente el producto al listado, hay más de uno ubicado en la búsqueda"
                );
            }
        },
        filterCategorie(id, mod = false) {
            if (id) {
                this.category_selected = id;
                this.getRecords();
            } else {
                this.category_selected = "";
                this.getRecords();
            }

            if (mod) {
                this.place = "cat2";
            } else {
                this.place = "prod";
            }

            this.setFocusInInputSearch();
        },
        getRecords() {
            this.loading = true;
            return this.$http
                .get(
                    `/${this.resource}/items?${this.getQueryParameters()}&cat=${
                        this.category_selected
                    }`
                )
                .then(response => {
                    this.all_items = response.data.data;
                    this.filterItems();
                    this.pagination = response.data.meta;
                    this.pagination.per_page = parseInt(
                        response.data.meta.per_page
                    );
                    this.loading = false;
                    if (response.data.meta.total > 0) {
                        this.pagination.total = response.data.meta.total;
                    } else {
                        this.pagination.total = 0;
                    }
                    this.fixItems();
                    this.ChangeSelectedPrice()
                });
        },
        getQueryParameters() {
            return queryString.stringify({
                page: this.pagination.current_page
                    ? this.pagination.current_page
                    : 1,
                input_item: this.input_item,
                cat: this.category_selected,
                limit: this.limit,
                group_variations: 1
            });
        },
        async selectVariationFromModal(variation) {
            try {
                const response = await this.$http.get(`/${this.resource}/item/${variation.id}`);
                const row = (response.data.data || [])[0];
                if (!row) {
                    return this.$message.error("No se pudo cargar la variación seleccionada");
                }
                await this.clickAddItem(row, null);
            } catch (error) {
                this.$message.error("No se pudo cargar la variación seleccionada");
            }
        },
        getColor(i) {
            return this.colors[i % this.colors.length];
        },
        initCurrencyType() {
            const exists = _.find(this.currency_types, {
                id: this.form.currency_type_id
            });
            if (!exists && this.currency_types.length > 0) {
                this.form.currency_type_id = this.currency_types[0].id;
                this.changeCurrencyType();
                return;
            }
            this.currency_type = exists;
        },
        getFormPosLocalStorage() {
            let form_pos = localStorage.getItem("form_pos");
            form_pos = JSON.parse(form_pos);
            if (form_pos) {
                this.form = form_pos;
                this.initDateTimeIssue();
                // this.calculateTotal()
            }
        },
        initDateTimeIssue() {
            this.form.date_of_issue = moment().format("YYYY-MM-DD");
            this.form.time_of_issue = moment().format("HH:mm:ss");
            this.form.date_of_due = moment().format("YYYY-MM-DD");
        },
        setFormPosLocalStorage(form_param = null) {
            if (form_param) {
                localStorage.setItem("form_pos", JSON.stringify(form_param));
            } else {
                localStorage.setItem("form_pos", JSON.stringify(this.form));
            }
        },
        priceOptionsCount(item) {
            let count = 0;
            if (!item) return count;
            const iuts = item.item_unit_types;
            if (!iuts || !Array.isArray(iuts)) return count;

            iuts.forEach(iut => {
                if (!iut || !Array.isArray(iut.prices)) return;
                iut.prices.forEach(p => {
                    if (p && !isNaN(Number(p.price)) && Number(p.price) > 0) {
                        count++;
                    }
                });
            });

            return count;
        },
        cancelFormPosLocalStorage() {
            localStorage.setItem("form_pos", JSON.stringify(null));
            this.setLocalStorageIndex("customer", null);
        },
        clickOpenInputEditUP(index) {
            this.items[index].edit_unit_price = true;
        },
        clickEditUnitPriceItem(index) {
            // console.log(index)
            let item_search = this.items[index];
            let edit_sale_unit_price = this.items[
                index
            ].edit_sale_unit_price;
            let product = this.items[index];

            if (this.config.condition_sale_purchase_price_to_item) {

                if (edit_sale_unit_price < product.purchase_unit_price) {
                    return this.$message.error(
                        "El Precio Unitario debe ser mayor o igual al costo de compra"
                    );
                }


            }

            this.items[index].sale_unit_price = this.items[
                    index
                ].edit_sale_unit_price;
            this.items[index].edit_unit_price = false;
            // console.log(item_search)
        },
        clickCancelUnitPriceItem(index) {
            // console.log(index)
            this.items[index].edit_unit_price = false;
        },
        setPriceItem(price, index) {

            const item = this.items[index];
            if (item && item.item_unit_types && Array.isArray(item.item_unit_types)) {
                item.item_unit_types.forEach(iut => {
                    if (iut && iut.prices && Array.isArray(iut.prices)) {
                        iut.prices.forEach(p => {
                            if (p && p.selected) {
                                this.$set(p, 'selected', false);
                            }
                        });
                    }
                });
            }

            if (price) {
                this.$set(price, 'selected', true);
            }

            this.items[index].sale_unit_price = price.price;
            this.items[index].unit_type_id = price.unit_type_id;
            this.items[index].presentation = price
            // se marca la fila para que ChangeSelectedPrice no pise el precio elegido a mano
            this.$set(this.items[index], 'manual_price_selected', true);
            this.ChangeSelectedPrice()
            this.$message.success("Precio seleccionado");
        },
        clickWarehouseDetail(item) {
            this.unittypeDetail = item.unit_type;
            this.warehousesDetail = item.warehouses;
            this.variationsDetail = item.variations || [];
            this.showWarehousesDetail = true;
        },
        clickHistoryPurchases(item_id) {
            this.history_item_id = item_id;
            this.showDialogHistoryPurchases = true;
            // console.log(item)
        },
        clickHistorySales(item_id) {
            if (!this.form.customer_id)
                return this.$message.error("Debe seleccionar el cliente");

            this.history_item_id = item_id;
            this.showDialogHistorySales = true;
            // console.log(item)
        },
        async keyupEnterCustomer() {
            if (this.place == "cat3") {
                return false;
            }

            if (this.form.customer_id) {
                this.clickPayment();
                return;
            }

            // La búsqueda es remota y va con debounce: sin esperarla, Enter
            // podría abrir el modal de cliente nuevo para uno que sí existe.
            await this.flushCustomerSearch();

            if (this.form.customer_id) {
                this.clickPayment();
                return;
            }

            if (this.input_person.number) {
                if (!isNaN(parseInt(this.input_person.number))) {
                    switch (this.input_person.number.length) {
                        case 8:
                            this.input_person.identity_document_type_id = "1";
                            this.showDialogNewPerson = true;
                            break;

                        case 11:
                            this.input_person.identity_document_type_id = "6";
                            this.showDialogNewPerson = true;
                            break;
                        default:
                            this.input_person.identity_document_type_id = "6";
                            this.showDialogNewPerson = true;
                            break;
                    }
                }
            }
        },
        visibleChangeCustomer(visible) {
            if (visible) {
                this.scheduleCustomerMarquee();
            } else {
                this.cancelCustomerMarquee();
            }
        },
        /**
         * Un solo frame pendiente: al escribir en el selector esto se llamaba
         * en cada tecla y encolaba una medición completa por pulsación.
         */
        scheduleCustomerMarquee() {
            this.cancelCustomerMarquee();
            this.$nextTick(() => {
                this.customer_marquee_frame = window.requestAnimationFrame(
                    () => {
                        this.customer_marquee_frame = null;
                        this.setupCustomerMarquee();
                    }
                );
            });
        },
        cancelCustomerMarquee() {
            if (this.customer_marquee_frame) {
                window.cancelAnimationFrame(this.customer_marquee_frame);
                this.customer_marquee_frame = null;
            }
        },
        /**
         * Anima con marquee las opciones cuyo texto se desborda.
         *
         * Las lecturas y las escrituras de layout van en pasadas separadas: si
         * se intercalan (escribir clase -> leer ancho -> escribir estilos) el
         * navegador recalcula el layout una vez por opción y con dropdowns
         * grandes eso bloqueaba el hilo varios segundos ("Forced reflow").
         */
        setupCustomerMarquee() {
            const select = this.$refs.select_person;
            const dropdown =
                select && select.$refs.popper ? select.$refs.popper.$el : null;
            if (!select || !dropdown) return;

            const texts = Array.prototype.slice.call(
                dropdown.querySelectorAll(".pos-customer-option__text")
            );
            if (!texts.length) return;

            const gap = 40;
            const speed = 50; // px por segundo

            // 1) Escrituras: ancho del popper y reset del marquee anterior.
            const selectWidth = select.$el.getBoundingClientRect().width;
            if (selectWidth) {
                dropdown.style.width = `${selectWidth}px`;
            }
            texts.forEach(text => text.classList.remove("is-marquee"));

            // 2) Lecturas: una única medición para todas las opciones.
            const measures = texts.map(text => {
                const chunk = text.querySelector(".pos-customer-option__chunk");
                if (!chunk || text.offsetParent === null) return null;

                return {
                    text,
                    available: text.clientWidth,
                    full: chunk.getBoundingClientRect().width
                };
            });

            // 3) Escrituras: aplicar la animación a lo que se desborda.
            measures.forEach(measure => {
                if (!measure) return;
                const { text, available, full } = measure;
                if (!available || full <= available + 1) return;

                const shift = full + gap;
                text.style.setProperty("--marquee-gap", `${gap}px`);
                text.style.setProperty("--marquee-shift", `${shift}px`);
                text.style.setProperty(
                    "--marquee-duration",
                    `${Math.max(2, shift / speed).toFixed(2)}s`
                );
                text.classList.add("is-marquee");
            });
        },
        /**
         * Búsqueda remota de clientes (remote-method del selector).
         *
         * El POS ya no precarga la cartera completa; se consulta al servidor
         * con debounce y se conserva siempre el cliente seleccionado dentro de
         * all_customers para que changeCustomer() y customerEmail lo encuentren.
         */
        searchCustomers(query) {
            const input = (query || "").trim();

            if (this.customer_search_timer) {
                clearTimeout(this.customer_search_timer);
                this.customer_search_timer = null;
            }

            if (input.length < 2) {
                this.loading_customers = false;
                this.customer_search_promise = null;
                this.all_customers = this.withPinnedCustomers(
                    this.customer_seed
                );
                return;
            }

            this.loading_customers = true;

            this.customer_search_promise = new Promise(resolve => {
                this.customer_search_timer = setTimeout(() => {
                    this.customer_search_timer = null;
                    this.$http
                        .get(`/${this.resource}/search_customers`, {
                            params: { input }
                        })
                        .then(response => {
                            this.all_customers = this.withPinnedCustomers(
                                response.data.data
                            );
                        })
                        .catch(() => {})
                        .then(() => {
                            this.loading_customers = false;
                            this.scheduleCustomerMarquee();
                            resolve();
                        });
                }, 250);
            });
        },
        /**
         * Espera la búsqueda en vuelo para que Enter no decida sobre una lista
         * a medio actualizar (abrir el modal de cliente nuevo por error).
         */
        async flushCustomerSearch() {
            if (this.customer_search_promise) {
                await this.customer_search_promise;
            }
        },
        /**
         * Mantiene fijos en la lista el cliente por defecto del establecimiento
         * y el actualmente seleccionado: la lista ya no contiene toda la
         * cartera, y changeCustomer()/customerEmail los buscan ahí por id.
         */
        withPinnedCustomers(list) {
            const seen = new Set();
            const rows = [];

            const push = row => {
                if (!row || !row.id) return;
                const id = String(row.id);
                if (seen.has(id)) return;
                seen.add(id);
                rows.push(row);
            };

            // La lista real manda; los fijos van al final y solo si faltan,
            // para no meter dos filas que no coinciden encima de una búsqueda.
            (list || []).forEach(push);
            push(this.customer);
            push(this.default_customer);

            return rows;
        },
        keyupCustomer(e) {
            if (this.place == "cat3") {
                return false;
            }

            this.scheduleCustomerMarquee();

            if (e.key !== "Enter") {
                this.input_person.number = this.$refs.select_person.$el.getElementsByTagName(
                    "input"
                )[0].value;
                let exist_persons = this.all_customers.filter(customer => {
                    let pos = customer.description.search(
                        this.input_person.number
                    );
                    return pos > -1;
                });

                this.input_person.number =
                    exist_persons.length == 0 ? this.input_person.number : null;
            }
        },
        calculateQuantity(index) {
            // console.log(this.form.items[index])
            if (this.form.items[index].item.calculate_quantity) {
                let quantity = _.round(
                    parseFloat(this.form.items[index].total) /
                        parseFloat(this.form.items[index].unit_price),
                    4
                );

                if (quantity) {
                    this.form.items[index].quantity = quantity;
                    this.form.items[index].item.aux_quantity = quantity;
                } else {
                    this.form.items[index].quantity = 0;
                    this.form.items[index].item.aux_quantity = 0;
                }
                // this.calculateTotal()
            }

            //  this.clickAddItem(this.form.items[index],index, true)
        },
        blurCalculateQuantity(index) {
            this.row = calculateRowItem(
                this.form.items[index],
                this.form.currency_type_id,
                1,
                this.percentage_igv
            );

            // console.log(this.form.items[index])

            this.row["unit_type_id"] = this.form.items[index].unit_type_id;

            this.form.items[index] = this.row;
            this.calculateTotal();
            this.setFormPosLocalStorage();
        },
        blurCalculateQuantity2(index) {
            this.row = calculateRowItem(
                this.form.items[index],
                this.form.currency_type_id,
                1,
                this.percentage_igv
            );
            this.form.items[index] = this.row;
            this.calculateTotal();
        },
        changeCustomer() {
            // console.log('clien 13')

            let customer = _.find(this.all_customers, {
                id: this.form.customer_id
            });

            // Sin cliente (selector limpiado o sin cliente por defecto) no hay
            // nada que aplicar; si esto lanza, se corta el created() completo
            if (!customer) {
                this.customer = null;
                return;
            }

            this.customer = customer;
            this.form.has_retention = customer.is_agent_retention

            this.validateCustomerRetention(customer.identity_document_type_id);

            if (this.configuration.default_document_type_80) {
                this.form.document_type_id = "80";
            } else if (this.configuration.default_document_type_03) {
                this.form.document_type_id = "03";
            } else {
                this.form.document_type_id =
                    customer.identity_document_type_id === "6" ? "01" : "03";
            }

            if (this.form.has_retention && this.form.total > 700) {
                this.changeRetention();
            }

            this.setLocalStorageIndex("customer", this.customer);
            this.setFormPosLocalStorage();
        },
        changeRetention() {
            if (this.form.has_retention) {
                let base = this.form.total;
                let percentage = _.round(
                    parseFloat(this.configuration.igv_retention_percentage) / 100,
                    5
                );
                let amount = _.round(base * percentage, 2);

                let amount_pen = amount;
                let amount_usd = _.round(
                    amount / this.form.exchange_rate_sale,
                    2
                );
                if (this.form.currency_type_id === "USD") {
                    amount_usd = amount;
                    amount_pen = _.round(
                        amount * this.form.exchange_rate_sale,
                        2
                    );
                }
                this.form.retention = {
                    base: base,
                    code: "62", //Código de Retención del IGV
                    amount: amount,
                    percentage: percentage,
                    currency_type_id: this.form.currency_type_id,
                    exchange_rate: this.form.exchange_rate_sale,
                    amount_pen: amount_pen,
                    amount_usd: amount_usd
                };

                this.setDataVoucherRetention();
            } else {
                this.form.retention = {};
                this.form.total_pending_payment = 0;

            }
        },
        setDataVoucherRetention() {
            if (this.isUpdateDocument && this.retention_query_data) {
                this.form.retention.voucher_date_of_issue = this.retention_query_data.voucher_date_of_issue;
                this.form.retention.voucher_number = this.retention_query_data.voucher_number;
                this.form.retention.voucher_amount = this.retention_query_data.voucher_amount;
                this.form.retention.voucher_filename = this.retention_query_data.voucher_filename;
            }
        },
        validateCustomerRetention(identity_document_type_id) {

            if (identity_document_type_id != "6" || !this.form.has_retention) {
                if (this.form.has_retention) {
                    this.form.has_retention = false;
                    this.changeRetention();
                }
                this.show_has_retention = false;
            } else {
                this.show_has_retention = true;
            }
        },
        getLocalStorageIndex(key, re_default = null) {
            let ls_obj = localStorage.getItem(key);
            ls_obj = JSON.parse(ls_obj);

            if (ls_obj) {
                return ls_obj;
            }

            return re_default;
        },
        setLocalStorageIndex(key, obj) {
            localStorage.setItem(key, JSON.stringify(obj));
        },
        async events() {
            await this.$eventHub.$on("initInputPerson", () => {
                this.initInputPerson();
            });

            await this.$eventHub.$on(
                "eventSetFormPosLocalStorage",
                form_param => {
                    this.setFormPosLocalStorage(form_param);
                }
            );

            await this.$eventHub.$on("cancelSale", () => {
                this.is_payment = false;
                this.initForm();
                this.changeExchangeRate();
                this.cancelFormPosLocalStorage();
                this.selectDefaultCustomer();
                this.$nextTick(() => {
                    this.initFocus();
                });
            });

            // await this.$eventHub.$on("indexInitFocus", () => {
            //   if(!this.is_payment) this.initFocus()
            // });

            await this.$eventHub.$on("reloadDataPersons", customer_id => {
                this.reloadDataCustomers(customer_id);
                this.setFormPosLocalStorage();
            });

            await this.$eventHub.$on("reloadDataItems", item_id => {
                this.reloadDataItems(item_id);
            });

            await this.$eventHub.$on("saleSuccess", async () => {
                // this.is_payment = false
                this.initForm();
                await this.getTables();
                this.selectDefaultCustomer();
                this.setFormPosLocalStorage();
            });

            await this.$eventHub.$on("enterSelectItemUnitType", unit_type => {
                this.selectItemUnitType(unit_type);
            });
        },
        selectItemUnitType(unit_type) {
            this.setPriceItem(unit_type, 0);
            this.clickAddItem(this.items[0], 0);
            this.filterItems();
            this.cleanInput();
            this.initFocus();
        },
        initForm() {
            this.form = {
                establishment_id: null,
                document_type_id: "03",
                series_id: null,
                prefix: null,
                number: "#",
                date_of_issue: moment().format("YYYY-MM-DD"),
                time_of_issue: moment().format("HH:mm:ss"),
                customer_id: null,
                currency_type_id: "PEN",
                purchase_order: null,
                exchange_rate_sale: 1,
                total_prepayment: 0,
                total_charge: 0,
                total_discount: 0,
                total_exportation: 0,
                total_free: 0,
                total_taxed: 0,
                total_unaffected: 0,
                total_exonerated: 0,
                total_igv: 0,
                total_base_isc: 0,
                total_isc: 0,
                total_base_other_taxes: 0,
                total_other_taxes: 0,
                total_plastic_bag_taxes: 0,
                total_taxes: 0,
                total_value: 0,
                total: 0,
                subtotal: 0,
                total_igv_free: 0,
                operation_type_id: "0101",
                date_of_due: moment().format("YYYY-MM-DD"),
                items: [],
                charges: [],
                discounts: [],
                attributes: [],
                guides: [],
                payments: [],
                hotel: {},
                additional_information: null,
                actions: {
                    format_pdf: "a4"
                },
                reference_data: null,
                is_print: true,
                worker_full_name_tips: null, //propinas
                total_pending_payment: 0,
                total_tips: 0, //propinas
                created_from_pos: true,
                show_terms_condition: true,
                terms_condition: '',
                token_validated_for_discount: false,
                agent_id: null,
                dispatch_ticket_pdf: this.configuration
                    ? this.configuration.enabled_dispatch_ticket_pdf
                    : false
            };
            // console.log(this.configuration.show_terms_condition_pos);
            const cfg = this.config || this.configuration || {};
            if (cfg.terms_condition_sale) {
                this.form.terms_condition = cfg.terms_condition_sale;
            }

            this.initFormItem();
            this.changeDateOfIssue();
            this.initInputPerson();

            this.initElectronicScaleData();
        },
        initElectronicScaleData() {
            this.electronic_scale_data = {
                barcode: "",
                parse_weight: "",
                parse_total: "",

                integer_weight: 0,
                decimal_weight: 0,
                weight: 0,

                integer_total: 0,
                decimal_total: 0,
                total: 0,
                pass_validations: false
            };
        },
        initInputPerson() {
            this.input_person = {
                number: "",
                identity_document_type_id: ""
            };
        },
        initFormItem() {
            this.form_item = {
                item_id: null,
                item: {},
                affectation_igv_type_id: null,
                affectation_igv_type: {},
                has_isc: false,
                system_isc_type_id: null,
                calculate_quantity: false,
                percentage_isc: 0,
                suggested_price: 0,
                quantity: 1,
                aux_quantity: 1,
                unit_price_value: 0,
                unit_price: 0,
                charges: [],
                discounts: [],
                attributes: [],
                has_igv: false,
                has_plastic_bag_taxes: false
            };
        },
        async clickPayment() {
            if (!this.form.subtotal) {
                //fix para agregar subtotal si no existe prop en json almacenado en local storage
                this.form.subtotal = this.form.total;
            }

            let flag = 0;
            this.form.items.forEach(row => {
                if (row.aux_quantity < 0 || row.total < 0 || isNaN(row.total)) {
                    flag++;
                }
            });

            let unit_type_notAllowed = ['ZZ', 'NIU'];
            let errorZeroQuantity = false
            let errorFloatQuantity = false
            let existError = this.form.items.some(item => {
                if (Number(item.quantity) == 0) {
                    errorZeroQuantity = true
                    return true;
                }
                if (unit_type_notAllowed.includes(item.unit_type_id) && !Number.isInteger(Number(item.quantity))) {
                    errorFloatQuantity =  true
                    return  true
                }
                return item.quantity == 0 ? true : false
            });

            if(existError) {
                if (errorZeroQuantity) {
                    this.$message.error('Los productos deben tener cantidades mayor a 0');
                }
                if (errorFloatQuantity) {
                    this.$message.error('El producto con ese tipo de unidad no permite cantidad en decimales');
                }

                return
            }

            if (this.form.has_retention && this.form.total > 700) {
                this.changeRetention();
            }

            if (flag > 0)
                return this.$message.error("Cantidad negativa o incorrecta");
            if (!this.form.customer_id)
                return this.$message.error("Seleccione un cliente");
            if (!this.form.items[0])
                return this.$message.error("Seleccione un producto");
            this.form.establishment_id = this.establishment.id;
            this.loading = true;
            await this.sleep(800);
            this.form.payments = []
            this.payments = []
            this.is_payment = true;
            this.loading = false;
        },
        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },
        clickDeleteCustomer() {
            this.form.customer_id = null;
            this.setFormPosLocalStorage();
        },
        getQuantityFromElectronicScale() {
            return _.round(this.electronic_scale_data.weight, 4);
        },
        getUnitPriceFromElectronicScale() {
            return _.round(
                this.electronic_scale_data.total /
                    this.electronic_scale_data.weight,
                6
            );
        },
        setScaleQuantityIfNotExistItem() {
            if (this.changeValuesElectronicScale) {
                this.form_item.item.aux_quantity = this.getQuantityFromElectronicScale();
                this.form_item.quantity = this.getQuantityFromElectronicScale();
                this.form_item.aux_quantity = this.getQuantityFromElectronicScale();
            }
        },
        async clickAddItem(item, index, input = false) {
            // El padre con variaciones no es vendible: se elige una variación en el modal
            if (item.variations_count > 0) {
                this.selectedVariationParent = item;
                this.showDialogVariations = true;
                return;
            }

            //Validar precio mínimo

            if (parseFloat(item.sale_unit_price) < 0.1) {
                this.$message.error(
                    "El precio del producto debe ser mayor a 0.1"
                );
                this.loanding = false;
                return;
            }


            let exchangeRateSale = this.form.exchange_rate_sale;
            let presentation = item.presentation;
            let exist_item = false;

            // Al cambiar la cantidad desde el carrito, `item` ya es la fila que se
            // está editando: se usa esa y no la primera coincidencia por producto
            // (un mismo producto puede estar varias veces con precios distintos).
            if (input && this.form.items[index] === item) {
                exist_item = item;
            }
            else if (this.selected_option_price && !input) {
                exist_item = _.filter(this.form.items, {
                    item_id: item.item_id,
                    unit_type_id: item.unit_type_id
                });

                let price_list = this.itemSetSaleUnitPrice(item)

                let price = null
                if (this.selected_option_price === 1) {
                    price = item.sale_unit_price
                } else {
                    price = Number(price_list) == 0 ? item.sale_unit_price : price_list

                }

                exist_item = _.find(this.form.items, i => {
                    return i.item_id === item.item_id &&
                        i.unit_type_id === item.unit_type_id &&
                        i.item.sale_unit_price == price
                });

            }
            else if (presentation === undefined) {
                exist_item = _.find(this.form.items, {
                    item_id: item.item_id,
                    unit_type_id: item.unit_type_id
                });
            }
            else {
                // Se evalua si existe presentation de item
                exist_item = _.find(this.form.items, {
                    item_id: item.item_id,
                    presentation: presentation,
                    unit_type_id: item.unit_type_id
                });
            }

            /*
            console.log(exist_item)
            console.log(item.unit_type_id)
            console.log(exist_item)
            console.log(item)
            console.log(presentation)
            console.log(presentation)
            */

            let pos = this.form.items.indexOf(exist_item);
            let response = null;

            if (exist_item) {
                if (input) {
                    response = await this.getStatusStock(
                        item.item_id,
                        exist_item.item.aux_quantity
                    );
                    if (!response.success) {
                        item.item.aux_quantity = item.quantity;
                        this.loading = false;
                        return this.$message.error(response.message);
                    }

                    exist_item.quantity = exist_item.item.aux_quantity;
                } else {
                    response = await this.getStatusStock(
                        item.item_id,
                        parseFloat(exist_item.item.aux_quantity) + 1
                    );
                    if (!response.success) {
                        this.loading = false;
                        return this.$message.error(response.message);
                    }

                    // balanza
                    if (this.changeValuesElectronicScale) {
                        exist_item.quantity += this.getQuantityFromElectronicScale();
                        exist_item.item.aux_quantity += this.getQuantityFromElectronicScale();
                    }
                    // balanza
                    else {
                        exist_item.quantity++;
                        exist_item.item.aux_quantity++;
                    }
                }

                // console.log(exist_item)
                let search_item_bd = await _.find(this.items, {
                    item_id: item.item_id
                });

                if (search_item_bd) {
                    exist_item.item.unit_price = parseFloat(
                        search_item_bd.sale_unit_price
                    );
                }

                let unit_price = exist_item.item.has_igv
                    ? exist_item.item.sale_unit_price
                    : exist_item.item.sale_unit_price *
                      (1 + this.percentage_igv);
                // exist_item.unit_price = unit_price

                // balanza
                if (this.changeValuesElectronicScale) {
                    unit_price = this.getUnitPriceFromElectronicScale();
                }
                // balanza

                exist_item.item.unit_price = unit_price;

                exist_item.has_plastic_bag_taxes =
                    exist_item.item.has_plastic_bag_taxes;

                //asignar variables isc
                exist_item.has_isc = exist_item.item.has_isc;
                exist_item.percentage_isc = exist_item.item.percentage_isc;
                exist_item.system_isc_type_id =
                    exist_item.item.system_isc_type_id;

                this.row = calculateRowItem(
                    exist_item,
                    this.form.currency_type_id,
                    exchangeRateSale,
                    this.percentage_igv
                );

                this.row["unit_type_id"] = item.unit_type_id;

                this.row.item.sale_unit_price_original = this.row.item.sale_unit_price

                // Preservar la presentation (calculateRowItem no la copia)
                this.row.presentation = exist_item.presentation;

                this.form.items[pos] = this.row;
            } else {
                response = await this.getStatusStock(
                    item.item_id,
                    presentation ? parseInt(presentation.quantity_unit) : 1
                );
                if (!response.success) {
                    this.loading = false;
                    return this.$message.error(response.message);
                }

                // this.form_item.item = item;
                this.form_item.item = { ...item };

                this.form_item.unit_price_value = this.form_item.item.sale_unit_price;
                this.form_item.has_igv = this.form_item.item.has_igv;
                this.form_item.has_plastic_bag_taxes = this.form_item.item.has_plastic_bag_taxes;
                this.form_item.affectation_igv_type_id = this.form_item.item.sale_affectation_igv_type_id;
                this.form_item.quantity = 1;
                this.form_item.aux_quantity = 1;

                let unit_price = this.form_item.has_igv
                    ? this.form_item.unit_price_value
                    : this.form_item.unit_price_value *
                      (1 + this.percentage_igv);

                // balanza
                this.setScaleQuantityIfNotExistItem();

                if (this.changeValuesElectronicScale) {
                    unit_price = this.getUnitPriceFromElectronicScale();
                }
                // balanza

                this.form_item.unit_price = unit_price;
                this.form_item.item.unit_price = unit_price;
                this.form_item.presentation = presentation
                    ? presentation
                    : null;

                this.form_item.charges = [];
                this.form_item.discounts = [];
                this.form_item.attributes = [];
                this.form_item.affectation_igv_type = _.find(
                    this.affectation_igv_types,
                    { id: this.form_item.affectation_igv_type_id }
                );

                //asignar variables isc
                this.form_item.has_isc = this.form_item.item.has_isc;
                this.form_item.percentage_isc = this.form_item.item.percentage_isc;
                this.form_item.system_isc_type_id = this.form_item.item.system_isc_type_id;

                this.row = calculateRowItem(
                    this.form_item,
                    this.form.currency_type_id,
                    exchangeRateSale,
                    this.percentage_igv
                );

                this.row.item.sale_unit_price_original = this.row.item.sale_unit_price

                // this.row['unit_type_id'] = item.presentation ? item.presentation.unit_type_id : 'NIU';

                this.row["unit_type_id"] = presentation
                    ? presentation.unit_type_id
                    : this.form_item.item.unit_type_id;

                // Se añade la presentation directamente al item para filtrarlo posteriormente
                this.row.presentation = presentation;
                this.form.items.unshift(this.row);
                item.aux_quantity = 1;
            }

            // console.log("pos", this.row);


            if (!input) {
                this.$notify({
                    title: "",
                    message: "Producto añadido!",
                    type: "success",
                    duration: 1000
                });
            }

            this.cleanInput();

            if (!input) {
                this.initFocus();
            }

            // console.log(this.row)
            // console.log(this.form.items)
            await this.calculateTotal();

            await this.setFormPosLocalStorage();

            // balanza
            this.initElectronicScaleData();
        },
        async getStatusStock(item_id, quantity) {
            let data = {};
            if (!quantity) quantity = 0;
            await this.$http
                .get(`/${this.resource}/validate_stock/${item_id}/${quantity}`)
                .then(response => {
                    data = response.data;
                });
            return data;
        },
        // Cantidad total del producto en el carrito (suma todas sus filas)
        cartQty(item) {
            let total = 0;
            for (const row of this.form.items) {
                if (row.item_id === item.item_id) {
                    total += parseFloat(row.quantity) || 0;
                }
            }
            return total;
        },
        cartQtyLabel(item) {
            return _.round(this.cartQty(item), 2);
        },
        // Quita una unidad desde la tarjeta del producto; al llegar a 1
        // elimina la fila del carrito
        async decrementCardItem(item) {
            const index = this.form.items.findIndex(
                r => r.item_id === item.item_id
            );
            if (index < 0) return;

            const row = this.form.items[index];
            if (parseFloat(row.item.aux_quantity) <= 1) {
                return await this.clickDeleteItem(row, index);
            }
            await this.changeCartQuantity(row, index, -1);
        },
        // Agregar/quitar desde la tarjeta: marca el indicador mientras se
        // valida el stock y lo destaca al terminar, para que el cambio se
        // note sin mirar el número
        async cardAddItem(item, index) {
            this.card_busy_id = item.item_id;
            try {
                await this.clickAddItem(item, index);
            } finally {
                this.endCardFeedback(item.item_id);
            }
        },
        async cardRemoveItem(item) {
            this.card_busy_id = item.item_id;
            try {
                await this.decrementCardItem(item);
            } finally {
                this.endCardFeedback(item.item_id);
            }
        },
        endCardFeedback(item_id) {
            if (this.card_busy_id === item_id) this.card_busy_id = null;
            this.card_flash_id = item_id;
            setTimeout(() => {
                if (this.card_flash_id === item_id) this.card_flash_id = null;
            }, 600);
        },
        async clickDeleteItem(item, row_index = null) {
            let index =
                row_index !== null && this.form.items[row_index] === item
                    ? row_index
                    : this.form.items.indexOf(item);

            if (index < 0) {
                index = this.form.items.findIndex(
                    row => row.item_id === item.item_id
                );
            }

            if (index < 0) return;

            this.form.items.splice(index, 1);
            this.calculateTotal();
            await this.setFormPosLocalStorage();
        },
        /**
         * Formatea un importe para mostrarlo siempre con 2 decimales.
         */
        money(value) {
            let amount = parseFloat(value);
            if (isNaN(amount)) amount = 0;
            return amount.toFixed(2);
        },
        /**
         * Precio unitario (con IGV) de una fila del carrito.
         */
        rowUnitPrice(row) {
            let unit_price = parseFloat(row.unit_price);

            if (isNaN(unit_price)) {
                const total = parseFloat(row.total);
                const quantity = parseFloat(row.quantity);
                unit_price =
                    !isNaN(total) && !isNaN(quantity) && quantity > 0
                        ? total / quantity
                        : 0;
            }

            return _.round(unit_price, 2).toFixed(2);
        },
        /**
         * Importe de una fila del carrito (modo solo lectura).
         */
        rowTotal(row) {
            return this.money(row.total);
        },
        /**
         * Aumenta/disminuye en una unidad la cantidad de una fila del carrito.
         */
        changeCartQuantity(item, index, delta) {
            const current = parseFloat(item.item.aux_quantity);
            let quantity = (isNaN(current) ? 0 : current) + delta;

            if (quantity < 1) {
                if (delta < 0) return;
                quantity = 1;
            }

            item.item.aux_quantity = _.round(quantity, 4);
            return this.clickAddItem(item, index, true);
        },
        /**
         * Vacía el carrito conservando el cliente seleccionado.
         */
        async clickClearCart() {
            if (this.form.items.length === 0) return;

            try {
                await this.$confirm(
                    "Se quitarán todos los productos de la venta actual.",
                    "¿Vaciar el carrito?",
                    {
                        confirmButtonText: "Sí, vaciar",
                        cancelButtonText: "Cancelar",
                        type: "warning"
                    }
                );
            } catch (e) {
                return;
            }

            this.form.items = [];
            this.calculateTotal();
            await this.setFormPosLocalStorage();
            this.initFocus();
        },
        calculateTotal() {
            let total_discount = 0;
            let total_charge = 0;
            let total_exportation = 0;
            let total_taxed = 0;
            let total_exonerated = 0;
            let total_unaffected = 0;
            let total_free = 0;
            let total_igv = 0;
            let total_value = 0;
            let total = 0;
            let total_plastic_bag_taxes = 0;
            let total_base_isc = 0;
            let total_isc = 0;
            let total_igv_free = 0;

            this.form.items.forEach(row => {
                total_discount += parseFloat(row.total_discount);
                total_charge += parseFloat(row.total_charge);

                if (row.affectation_igv_type_id === "10") {
                    // total_taxed += parseFloat(row.total_value);
                    total_taxed += row.total_value_without_rounding
                        ? parseFloat(row.total_value_without_rounding)
                        : parseFloat(row.total_value);
                }

                if (row.affectation_igv_type_id === "20") {
                    // total_exonerated += parseFloat(row.total_value);
                    total_exonerated += row.total_value_without_rounding
                        ? parseFloat(row.total_value_without_rounding)
                        : parseFloat(row.total_value);
                }

                if (row.affectation_igv_type_id === "30") {
                    total_unaffected += parseFloat(row.total_value);
                }

                if (row.affectation_igv_type_id === "40") {
                    total_exportation += parseFloat(row.total_value);
                }

                if (
                    ["10", "20", "30", "40"].indexOf(
                        row.affectation_igv_type_id
                    ) < 0
                ) {
                    total_free += parseFloat(row.total_value);
                }

                // if (["10", "20", "30", "40"].indexOf(row.affectation_igv_type_id) > -1)
                if (
                    ["10", "20", "30", "40", "21"].indexOf(
                        row.affectation_igv_type_id
                    ) > -1
                ) {
                    // total_igv += parseFloat(row.total_igv);
                    // total += parseFloat(row.total);
                    total_igv += row.total_igv_without_rounding
                        ? parseFloat(row.total_igv_without_rounding)
                        : parseFloat(row.total_igv);
                    total += row.total_without_rounding
                        ? parseFloat(row.total_without_rounding)
                        : parseFloat(row.total);
                }

                // total_value += parseFloat(row.total_value);

                if (!["21", "37"].includes(row.affectation_igv_type_id)) {
                    total_value += row.total_value_without_rounding
                        ? parseFloat(row.total_value_without_rounding)
                        : parseFloat(row.total_value);
                }

                total_plastic_bag_taxes += parseFloat(
                    row.total_plastic_bag_taxes
                );

                if (
                    ["11", "12", "13", "14", "15", "16"].includes(
                        row.affectation_igv_type_id
                    )
                ) {
                    let unit_value = row.total_value / row.quantity;
                    let total_value_partial = unit_value * row.quantity;
                    row.total_taxes =
                        row.total_value -
                        total_value_partial +
                        parseFloat(row.total_plastic_bag_taxes); //sumar icbper al total tributos

                    row.total_igv =
                        total_value_partial * (row.percentage_igv / 100);
                    row.total_base_igv = total_value_partial;
                    total_value -= row.total_value;

                    total_igv_free += row.total_igv;
                    total += parseFloat(row.total); //se agrega suma al total para considerar el icbper
                }

                // isc
                total_isc += parseFloat(row.total_isc);
                total_base_isc += parseFloat(row.total_base_isc);
            });

            // isc
            this.form.total_base_isc = _.round(total_base_isc, 2);
            this.form.total_isc = _.round(total_isc, 2);

            this.form.total_igv_free = _.round(total_igv_free, 2);

            this.form.total_exportation = _.round(total_exportation, 2);
            this.form.total_exonerated = _.round(total_exonerated, 2);
            this.form.total_taxed = _.round(total_taxed, 2);
            this.form.total_exonerated = _.round(total_exonerated, 2);

            // this.form.total_taxed =
            //   _.round(total_taxed, 2) + this.form.total_exonerated;
            // this.form.total_exonerated = _.round(total_exonerated, 2)
            this.form.total_unaffected = _.round(total_unaffected, 2);
            this.form.total_free = _.round(total_free, 2);
            this.form.total_igv = _.round(total_igv, 2);
            this.form.total_value = _.round(total_value, 2);
            // this.form.total_taxes = _.round(total_igv, 2);

            //impuestos (isc + igv + icbper)
            this.form.total_taxes = _.round(
                total_igv + total_isc + total_plastic_bag_taxes,
                2
            );
            // this.form.total_taxes = _.round(total_igv + total_isc, 2);

            this.form.total_plastic_bag_taxes = _.round(
                total_plastic_bag_taxes,
                2
            );

            this.form.total = _.round(total, 2);

            if (this.verifyRecalculateTotalTaxed() && this.form.total_taxed > 0) {
                this.form.total_taxed = this.recalculateDecimalTotalTaxed(this.form.total, this.form.total_igv);
            }

            // this.form.total = _.round(total + this.form.total_plastic_bag_taxes, 2)

            this.form.subtotal = this.form.total;

        },
        recalculateDecimalTotalTaxed(total, igv) {
            return total - igv;
        },
        verifyRecalculateTotalTaxed() {
            const keysToCheck = [
                'total_isc', 'total_igv_free', 'total_discount', 'total_exportation',
                'total_exonerated', 'total_unaffected', 'total_free', 'total_plastic_bag_taxes'
            ];
            return !keysToCheck.some(key => this.form[key] > 0);
        },
        changeDateOfIssue() {
            // this.searchExchangeRateByDate(this.form.date_of_issue).then(response => {
            //     this.form.exchange_rate_sale = response
            // })
        },
        changeExchangeRate() {
            this.searchExchangeRateByDate(this.form.date_of_issue).then(
                response => {
                    this.form.exchange_rate_sale = response;
                }
            );
        },
        async getTables() {
            await this.$http.get(`/${this.resource}/tables`).then(response => {
                //this.all_items = response.data.items;
                this.affectation_igv_types =
                    response.data.affectation_igv_types;
                this.all_customers = response.data.customers || [];
                this.establishment = response.data.establishment;
                // El backend solo manda el cliente por defecto como semilla;
                // el resto llega por búsqueda remota (pos/search_customers).
                this.default_customer =
                    this.all_customers.find(
                        c =>
                            String(c.id) ===
                            String(this.establishment.customer_id)
                    ) || null;
                // Primera pantalla del desplegable: es a lo que se vuelve al
                // borrar la búsqueda o al empezar una venta nueva.
                this.customer_seed = this.all_customers.slice();
                this.currency_types = response.data.currency_types;
                this.user = response.data.user;
                this.form.establishment_id = this.establishment.id;
                this.form.currency_type_id =
                    this.currency_types.length > 0
                        ? this.currency_types[0].id
                        : null;
                this.renderCategories(response.data.categories);
                // this.currency_type = _.find(this.currency_types, {'id': this.form.currency_type_id})
                // this.changeCurrencyType();
                //this.filterItems();
                this.changeDateOfIssue();
                this.changeExchangeRate();
            });
        },
        selectDefaultCustomer() {
            // Tras una venta la lista puede contener solo el último resultado
            // de búsqueda; se vuelve a la semilla antes de fijar el defecto.
            this.all_customers = this.withPinnedCustomers(this.customer_seed);

            if (this.establishment.customer_id && !this.form.customer_id) {
                this.form.customer_id = this.establishment.customer_id;
            }
            this.changeCustomer();

        },
        renderCategories(source) {
            const contex = this;
            this.categories = source.map((obj, index) => {
                return {
                    id: obj.id,
                    name: obj.name,
                    color: contex.getColor(index)
                };
            });

            this.categories.unshift({
                id: null,
                name: "Todos",
                color: "#2C8DE3"
            });
        },
        async searchItems() {
            if (this.input_item.length > 0) {
                this.loading = true;
                let parameters = `input_item=${this.input_item}&cat=${
                    this.category_selected
                }&group_variations=1`;

                await this.$http
                    .get(`/${this.resource}/search_items_cat?${parameters}`)
                    .then(response => {
                        this.all_items = response.data.data;

                        if (response.data.data.length > 0) {
                            // this.all_items = response.data.data;
                            this.filterItems();
                            this.pagination = response.data.meta;
                            this.pagination.per_page = parseInt(
                                response.data.meta.per_page
                            );
                            this.fixItems();
                            this.loading = false;
                        } else {
                            this.loading = false;
                            this.filterItems();
                        }

                        this.ChangeSelectedPrice()
                    });
            } else {
                this.getRecords();
                this.filterItems();
            }
        },
        getResponseValidate(success, message) {
            return {
                success: success,
                message: message
            };
        },
        setDataToElectronicScaleData() {
            const start = 0;
            const end_barcode = 5;
            const end_parse_weight = 10;
            const str_input_item = this.input_item.trim();

            if (str_input_item.length !== 16)
                return this.getResponseValidate(
                    false,
                    "El código de barras ingresado no cumple el formato establecido."
                );

            // obtener valores del codigo de barras de la balanza
            this.electronic_scale_data.barcode = str_input_item.substring(
                start,
                end_barcode
            );
            this.electronic_scale_data.parse_weight = str_input_item.substring(
                end_barcode,
                end_parse_weight
            );
            this.electronic_scale_data.parse_total = str_input_item.substring(
                end_parse_weight
            );

            // obtener el peso del codigo
            const end_weight =
                this.electronic_scale_data.parse_weight.length - 3;
            this.electronic_scale_data.integer_weight = this.electronic_scale_data.parse_weight.substring(
                start,
                end_weight
            );
            this.electronic_scale_data.decimal_weight = this.electronic_scale_data.parse_weight.substring(
                end_weight
            );
            this.electronic_scale_data.weight = parseFloat(
                `${this.electronic_scale_data.integer_weight}.${
                    this.electronic_scale_data.decimal_weight
                }`
            );

            if (isNaN(this.electronic_scale_data.weight))
                return this.getResponseValidate(
                    false,
                    "El peso no cumple con el formato establecido, no se pudo obtener un valor numérico correcto."
                );

            // obtener el total del codigo
            const end_total = this.electronic_scale_data.parse_total.length - 2;
            this.electronic_scale_data.integer_total = this.electronic_scale_data.parse_total.substring(
                start,
                end_total
            );
            this.electronic_scale_data.decimal_total = this.electronic_scale_data.parse_total.substring(
                end_total
            );
            this.electronic_scale_data.total = parseFloat(
                `${this.electronic_scale_data.integer_total}.${
                    this.electronic_scale_data.decimal_total
                }`
            );

            if (isNaN(this.electronic_scale_data.total))
                return this.getResponseValidate(
                    false,
                    "El total no cumple con el formato establecido, no se pudo obtener un valor numérico correcto."
                );

            this.electronic_scale_data.pass_validations = true;

            // console.log("*******************************")
            // console.log("barcode", this.electronic_scale_data.barcode)
            // console.log("weight", this.electronic_scale_data.weight)
            // console.log("total", this.electronic_scale_data.total)

            // console.log("parse_weight", this.electronic_scale_data.parse_weight)
            // console.log("parse_total", this.electronic_scale_data.parse_total)

            // console.log("*******************************")
            // console.log("integer_weight", this.electronic_scale_data.integer_weight)
            // console.log("decimal_weight", this.electronic_scale_data.decimal_weight)
            // console.log("*******************************")
            // console.log("integer_total", this.electronic_scale_data.integer_total)
            // console.log("decimal_total", this.electronic_scale_data.decimal_total)

            return {
                success: true
            };
        },
        async searchItemsBarcode() {
            if (this.input_item.length > 1) {
                this.loading = true;
                let parameters = `input_item=${
                    this.input_item
                }&search_item_by_barcode_presentation=${
                    this.search_item_by_barcode_presentation
                }`;

                if (this.electronic_scale_barcode) {
                    const check_electronic_scale_data = this.setDataToElectronicScaleData();

                    if (!check_electronic_scale_data.success) {
                        this.loading = false;
                        return this.$message.error(
                            check_electronic_scale_data.message
                        );
                    }

                    parameters = `input_item=${
                        this.electronic_scale_data.barcode
                    }&search_item_by_barcode_presentation=${
                        this.search_item_by_barcode_presentation
                    }`;
                }

                await this.$http
                    .get(`/${this.resource}/search_items?${parameters}`)
                    .then(response => {
                        if (response.data.items.length > 0) {

                            let presentation = response.data.items[0].unit_type.length > 0 ? true: false

                            if (presentation && this.barcode_stop_presentation) {
                                this.items = response.data.items;
                                this.ChangeSelectedPrice()
                                this.loading = false;
                                return
                            }

                            this.items = response.data.items;
                            this.ChangeSelectedPrice()

                            this.enabledSearchItemsBarcode();
                            this.loading = false;
                            if (this.items.length == 0) {
                                this.filterItems();
                            }

                        } else {
                            this.$message.error('No se encontro el codigo de barra');
                            this.cleanInput();
                            this.loading = false;
                        }


                    });
            } else {
                await this.filterItems();
            }
        },
        fixItems() {
            this.items = this.all_items.map(i => {
                /** Si description es vacio y hay nombre */
                if (i.name !== undefined) {
                    if (i.description === undefined || i.description == null) {
                        i.description = i.name;
                    }
                }
                /** Si description es vacio aun */
                if (i.description == null) {
                    i.description = i.internal_id;
                }

                return i;
            });
            this.all_items = this.items;
        },
        enabledSearchItemsBarcode() {
            if (this.search_item_by_barcode) {
                //busqueda por presentacion
                if (this.search_item_by_barcode_presentation) {
                    if (this.items.length == 1) {
                        if (
                            this.items[0].unit_type.length === 1 &&
                            this.items[0].search_item_by_barcode_presentation
                        ) {
                            this.selectItemUnitType(this.items[0].unit_type[0]);
                        } else {
                            this.items = [];
                            this.filterItems();
                        }
                    }
                }
                //busqueda comun
                else {
                    if (this.items.length == 1) {
                        
                        this.clickAddItem(this.items[0], 0);
                        this.filterItems();
                    }
                }

                this.cleanInput();
            }
        },
        changeSearchItemBarcode() {
            this.cleanInput();
        },
        cleanInput() {
            this.input_item = null;
        },
        /**
         * Esc en el listado de productos: limpia la búsqueda, recarga el
         * listado completo y devuelve el foco al buscador.
         */
        async onTableEscape() {
            this.cleanInput();
            await this.getRecords();
            this.initFocus();
        },
        filterItems() {
            if (this.place === "cat3") {
                this.items = this.all_items;
            } else {
                this.items = this.all_items.map(i => {
                    // console.log(i.description);
                    // if (i.brand) {
                    //     var desc = `${i.description} - ${i.brand}`;
                    //     if(i.description != desc){
                    //         i.description = `${i.description} - ${i.brand}`;
                    //     }
                    // }
                    // console.log(i.description);
                    return i;
                });
            }
        },
        reloadDataCustomers(customer_id) {
            // Solo el cliente recién creado: recargar la cartera entera era lo
            // que devolvía miles de opciones al selector.
            this.$http
                .get(`/${this.resource}/search_customers`, {
                    params: { id: customer_id }
                })
                .then(response => {
                    const created = response.data.data || [];
                    // El cliente nuevo queda arriba, pero el desplegable sigue
                    // mostrando la lista inicial debajo.
                    this.all_customers = this.withPinnedCustomers(
                        created.concat(this.customer_seed)
                    );

                    this.customer_seed = this.all_customers.slice();
                    this.form.customer_id = customer_id;
                    this.changeCustomer();
                });
        },
        reloadDataItems(item_id) {
            this.$http.get(`/${this.resource}/table/items`).then(response => {
                this.all_items = response.data;
                this.fixItems();
                this.filterItems();
            });
        },
        selectCurrencyType() {
            this.form.currency_type_id =
                this.form.currency_type_id === "PEN" ? "USD" : "PEN";
            this.changeCurrencyType();
        },
        async changeCurrencyType() {
            // console.log(this.form.currency_type_id)
            this.currency_type = await _.find(this.currency_types, {
                id: this.form.currency_type_id
            });
            let items = [];
            this.form.items.forEach(row => {
                items.push(
                    calculateRowItem(
                        row,
                        this.form.currency_type_id,
                        this.form.exchange_rate_sale,
                        this.percentage_igv
                    )
                );
            });
            this.form.items = items;
            this.calculateTotal();

            await this.setFormPosLocalStorage();
        },
        openFullWindow() {
            location.href = `/${this.resource}/pos_full`;
        },
        back() {
            this.all_items = [];
            this.place = "cat";
            localStorage.setItem("pos_view_preference", "cat");
            this.loading = false;
        },
        async setView(view) {
            this.place = view;
            localStorage.setItem("pos_view_preference", view);

            if (view == "cat3") {
                this.category_selected = "";
                await this.getRecords();
                // Al restaurar la vista en el arranque la tabla aún no montó
                if (this.$refs.table_items) {
                    this.$refs.table_items.reset();
                }
            }

            // La vista de categorías y productos también debe cargar el
            // listado: al venir de la vista de categorías (back() vacía
            // all_items) quedaba en blanco hasta filtrar una categoría.
            if (view == "cat2") {
                this.category_selected = "";
                await this.getRecords();
            }

            this.setFocusInInputSearch();
        },
        nameSets(id) {
            let row = this.items.find(x => x.item_id == id);
            if (row) {
                if (row.sets.length > 0) {
                    return row.sets.join(",<br>");
                } else {
                    return "";
                }
            }
        },
        listReverse(items) {
            console.log(items);
            console.log(_.reverse(items));
            return _.reverse(items);
        },
        DescriptionLength(item) {
            if (item.description === undefined) return 0;
            if (item.description == null) return 0;
            return item.description.length;
        },
        onPriceOptionChange() {
            this.clearManualPriceSelection();
            this.ChangeSelectedPrice();
            const option = _.find(this.price_options, { id: this.selected_option_price });
            if (option) {
                this.$message({
                    message: `Precio de búsqueda: ${option.description}`,
                    type: "info",
                    duration: 3000
                });
            }
        },
        clearManualPriceSelection() {
            this.items.forEach(row => {
                if (row.manual_price_selected) {
                    this.$set(row, 'manual_price_selected', false);
                }

                if (Array.isArray(row.item_unit_types)) {
                    row.item_unit_types.forEach(iut => {
                        if (iut && Array.isArray(iut.prices)) {
                            iut.prices.forEach(p => {
                                if (p && p.selected) {
                                    this.$set(p, 'selected', false);
                                }
                            });
                        }
                    });
                }
            });
        },
        async ChangeSelectedPrice() {
            // recorrer items

            this.items.forEach(row => {
                    // precio elegido manualmente en la fila: la lista de precios no lo sobrescribe
                    if (row.manual_price_selected) return;

                    if(row.item_unit_types && row.item_unit_types.length > 0) {
                        let first_list = row.item_unit_types[0];
                        let original_price = parseFloat(row.sale_unit_price);

                        // Extraer price_label_id del selectedOptionPrice
                        let priceLabelId = null;
                        if(typeof this.selected_option_price === 'string' && this.selected_option_price.startsWith('price_label_')) {
                            priceLabelId = parseInt(this.selected_option_price.replace('price_label_', ''));
                        }

                        if (!row.affected_list_price) { // funcion candado, para colocar el valor original ya que sale_unit_price se ve modificado al cambiar la lista de precios
                            row.original_sale_unit_price = original_price;
                        }

                        // Buscar y asignar el precio correspondiente usando 'id'
                        if(priceLabelId && first_list.prices && first_list.prices.length > 0) {

                            const priceObj = first_list.prices.find(p => p.price_label_id == priceLabelId);

                            if(priceObj && Number(priceObj.price) > 0) {
                                row.sale_unit_price = parseFloat(priceObj.price);
                                row.affected_list_price = true;
                            } else {
                                row.sale_unit_price = row.original_sale_unit_price
                            }

                            // Si no se encuentra o es 0, mantener el sale_unit_price original
                        } else {
                            row.sale_unit_price = row.original_sale_unit_price
                        }
                    }

            });
        },
            itemSetSaleUnitPrice(row)
            {
                // precio elegido manualmente en la fila: se muestra y se usa tal cual
                if (row && row.manual_price_selected) {
                    return row.unit_price_value = parseFloat(row.sale_unit_price).toFixed(2);
                }

                if(!this.configuration.enable_list_product && this.selected_option_price !== 1) {
                    if(Array.isArray( row.item_unit_types) &&  row.item_unit_types.length) {
                        let first_list = row.item_unit_types[0];

                        // Extraer price_label_id del selectedOptionPrice (formato: "price_label_2")
                        let priceLabelId = null;
                        if(typeof this.selected_option_price === 'string' && this.selected_option_price.startsWith('price_label_')) {
                            priceLabelId = parseInt(this.selected_option_price.replace('price_label_', ''));
                        }

                        // Buscar el precio correspondiente en el array prices usando 'id'
                        if(priceLabelId && first_list.prices && first_list.prices.length > 0) {
                            const priceObj = first_list.prices.find(p => p.price_label_id === priceLabelId);

                            if(priceObj) {
                                return row.unit_price_value = parseFloat(priceObj.price).toFixed(2);
                            } else {
                                return row.unit_price_value = parseFloat(row.sale_unit_price).toFixed(2);
                            }
                        }

                        // Fallback: usar unit_price_value por defecto
                        return row.unit_price_value = parseFloat(row.sale_unit_price).toFixed(2);
                    } else {
                        return row.unit_price_value = parseFloat(row.sale_unit_price).toFixed(2);
                    }
                }

                return row.original_sale_unit_price ? row.original_sale_unit_price.toFixed(2) : parseFloat(row.sale_unit_price).toFixed(2);
            },
        }
};
</script>

<style scoped>
.circle-container {
  display: flex;
  align-items: center;
  justify-content: center;
}

.circle-child {
  display: flex;
  align-items: center;
  justify-content: center;
}

.svg-bounce {
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0);    }
  50%       { transform: translateY(-2px); }
}

.item-description {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
