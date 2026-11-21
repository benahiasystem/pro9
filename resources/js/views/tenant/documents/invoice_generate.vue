<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
<!-- ######## INICIO CAMBIO GEOPOLITICO VENEZUELA -->
<div :class="{ 'content-opacity': isVisible }" @click.self="toggleInformation">
    <MiniTour
        :steps="miniTourStepsVisible"
        storage-key="tour_doc_generate_buttons"
        :version="1"
        fab-avoid-selector=".ws-flotante"
        auto
    />
    <span class="module-title-marker" data-page-title="Nuevo Comprobante"></span>
    <Keypress key-event="keyup" @success="checkKey" />
    <Keypress
        key-event="keyup"
        :multiple-keys="multiple"
        @success="checkKeyWithAlt"
    />
    <div class="tab-content tab-content-light row-new tab-content-default" v-if="loading_form">
        <div class="invoice p-0">
            <form
            autocomplete="off"
            class="row no-gutters mx-0"
            :class="{ 'layout-editing-active': editingLayout }"
            @submit.prevent="submit"
            >
            <div v-if="invoiceCalculationError" class="col-12 alert alert-danger" role="alert">{{ invoiceCalculationError }}</div>
            <div class="col-xl-12 col-md-12 col-12 px-0">
                <header class="clearfix clearfix-default py-2 px-0 px-md-2 border-0">
                    <div
                        class="row mx-1 my-1 mx-md-1 my-md-0"
                    >
                        <div class="col-md-6 text-start d-flex align-items-end">
                            <h2 class="m-0 fw-bold title-document" style="line-height: 25px;">
                                Nuevo Combrobante Electrónico
                                <button type="button" title="Personalizar datos generales" @click="enterLayoutEditFromHeader" class="btn btn-sm second-buton ms-3 edit-layout-btn" v-if="!editingLayout">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-adjustments-horizontal"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M12 6a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M4 6l8 0" /><path d="M16 6l4 0" /><path d="M6 12a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M4 12l2 0" /><path d="M10 12l10 0" /><path d="M15 18a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M4 18l11 0" /><path d="M19 18l1 0" /></svg>
                                </button>
                            </h2>
                        </div>
                        <div class="row p-0 m-0 col-md-6 justify-content-end">
                            <div class="col-md-4 d-flex align-items-end justify-content-end">
                                <button type="button" data-tour="info-adicional" class="btn btn-sm second-buton" @click="toggleInformation">
                                    Información adicional
                                </button>
                            </div>
                            <div class="col-3 align-self-end invoice-type">
                                <div
                                    :class="{
                                        'has-danger': errors.document_type_id
                                    }"
                                    class="form-group"
                                >
                                    <label
                                        class="control-label font-weight-bold"
                                        >Tipo comprobante</label
                                    >
                                    <el-select
                                        v-model="form.document_type_id"
                                        class="border-left rounded-left border-info"
                                        dusk="document_type_id"
                                        popper-class="el-select-document_type"
                                        @change="changeDocumentType"
                                        :disabled="isUpdateDocument"
                                    >
                                        <el-option
                                            v-for="option in documentTypesAvailable"
                                            :key="option.id"
                                            :label="option.description"
                                            :value="option.id"
                                        ></el-option>
                                    </el-select>
                                    <small
                                        v-if="errors.document_type_id"
                                        class="form-control-feedback"
                                        v-text="errors.document_type_id[0]"
                                    ></small>
                                </div>
                            </div>
                            <div v-if="hasNamedSeries || errors.series_id" class="align-self-end serie-input col-2">
                                <div
                                    :class="{ 'has-danger': errors.series_id }"
                                    class="form-group"
                                >
                                    <template v-if="hasNamedSeries">
                                        <label class="control-label">Serie</label>
                                        <el-select
                                            v-model="form.series_id"
                                            :disabled="isUpdateDocument || disabledSeries()"
                                        >
                                            <el-option
                                                v-for="option in series"
                                                :key="option.id"
                                                :label="option.number || '\u00a0'"
                                                :disabled="option.disabled"
                                                :value="option.id"
                                            ></el-option>
                                        </el-select>
                                    </template>
                                    <small
                                        v-if="errors.series_id"
                                        class="form-control-feedback"
                                        v-text="errors.series_id[0]"
                                    ></small>
                                </div>
                            </div>

                            <div
                                v-if="showOperationTypeField"
                                class="col-md-3 align-self-end operation-type"
                            >
                                <div
                                    :class="{
                                        'has-danger': errors.operation_type_id
                                    }"
                                    class="form-group"
                                >
                                    <label class="control-label"
                                        >Tipo Operación
                                        <!-- ########## INICIO SIN DETRACCIONES E ISC -->
                                        <!-- ######### FIN SIN DETRACCIONES E ISC -->
                                    </label>
                                    <el-select
                                        v-model="operation_type_id_view"
                                        @change="changeOperationType"
                                    >
                                        <el-option
                                            v-for="option in operation_types_filter"
                                            :key="option.id"
                                            :label="option.description"
                                            :value="option.id"
                                        ></el-option>
                                    </el-select>
                                    <small
                                        v-if="errors.operation_type_id"
                                        class="form-control-feedback"
                                        v-text="errors.operation_type_id[0]"
                                    ></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row px-2" v-if="specialTaxRegimeMode && (specialTaxRegimeAvailable || savedSpecialTaxRegime)">
                        <div class="col-md-8 mb-3" :class="{'has-danger': errors['fiscal_data.special_tax_regime_id']}">
                            <label class="control-label">Régimen especial de tributación</label>
                            <el-select v-model="form.fiscal_data.special_tax_regime_id" :disabled="!specialTaxRegimeAvailable" @change="changeSpecialTaxRegime">
                                <el-option :value="null" label="Sin régimen especial" />
                                <el-option v-for="regime in specialTaxRegimes" :key="regime.id" :value="regime.id" :label="regime.description" :disabled="regime.inactive" />
                            </el-select>
                            <small v-if="savedSpecialTaxRegime" class="d-block">Régimen registrado: {{ savedSpecialTaxRegime }}</small>
                            <small class="d-block text-muted">Conserva el IVA de cada producto. Requiere Contado y saldo cero.</small>
                            <small v-if="errors['fiscal_data.special_tax_regime_id']" class="form-control-feedback d-block text-danger" v-text="errors['fiscal_data.special_tax_regime_id'][0]" />
                        </div>
                    </div>
                    <div class="row px-2" v-if="thirdPartyMode && (thirdPartyAvailable || originalThirdPartyInvoice)">
                        <div class="col-md-8 mb-3">
                            <div class="form-group position-relative" :class="{'has-danger': errors.third_party_id}">
                                <label class="control-label font-weight-bold">Cliente tercero titular de la venta</label>
                                <el-select v-model="form.third_party_id" filterable remote :remote-method="searchRemoteThirdParties" :loading="loadingThirdParties" class="border-left rounded-left border-info customer-select-clearable" popper-class="el-select-customers" placeholder="Buscar por RIF o razón social" @focus="loadThirdParties(thirdPartySearchTerm)">
                                    <el-option v-for="third in thirdParties" :key="third.id" :value="third.id" :label="third.third_party_rif + ' · ' + third.name" />
                                    <template slot="empty">
                                        <p v-if="loadingThirdParties" class="el-select-dropdown__empty">Cargando...</p>
                                        <p v-else class="el-select-dropdown__empty">No se encontraron resultados</p>
                                        <div v-if="!loadingThirdParties" class="el-select-dropdown__item new-option" @click.stop="openThirdPartyDialog()">
                                            <span>{{ thirdPartySearchTerm ? `Crear cliente "${thirdPartySearchTerm}"` : 'Crear cliente' }}</span>
                                        </div>
                                    </template>
                                </el-select>
                                <span v-if="form.third_party_id" class="btn-add-new btn-edit-person btn-add-new-invoice" @click.prevent="openThirdPartyDialog(true)" title="Editar cliente tercero">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h3.5" /><path d="M18.42 15.61a2.1 2.1 0 0 1 2.97 2.97l-3.39 3.42h-3v-3l3.42 -3.39" /></svg>
                                </span>
                                <span class="btn-add-new btn-add-new-invoice" @click.prevent="openThirdPartyDialog()" title="Agregar nuevo cliente tercero">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M16 19h6" /><path d="M19 16v6" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4" /></svg>
                                </span>
                                <small v-if="errors.third_party_id" class="form-control-feedback d-block text-danger" v-text="errors.third_party_id[0]" />
                            </div>
                            <small class="text-muted">Un titular por factura, venta interna al contado y completamente pagada.</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-end justify-content-start gap-1 p-0 py-1 py-md-2 px-md-2 mx-1" v-if="editingLayout">
                        <button type="button" class="btn btn-sm second-buton mt-1" @click="cancelLayoutEditFromHeader">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-x" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                            Cancelar
                        </button>
                        <button type="button" @click="resetLayoutFromHeader" class="btn btn-sm second-buton" title="Restablecer">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-refresh" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" /><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" /></svg>
                            Restablecer
                        </button>
                        <button type="button" :disabled="layout_saving" @click="confirmLayoutFromHeader" class="btn btn-sm btn-primary" title="Guardar">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-device-floppy" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M6 4h10l4 4v10a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2" /><path d="M10 14a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M14 4l0 4l-6 0l0 -4" /></svg>
                            Guardar
                        </button>
                    </div>
                </header>
                <document-form-pinned-bar
                    ref="pinnedBar"
                    variant="invoice"
                    class="card-body card-body-invoice no-gutters border-0 shadow-none p-0 py-1 py-md-2 px-md-2 mx-1"
                    :pinned-fields="pinned_fields"
                    :hidden-fields="hiddenLayoutFields"
                    @editing-changed="editingLayout = $event"
                    @save="onSaveLayout"
                >
                    <template #customer_id>
                        <div :class="{ 'has-danger': errors.customer_id }" class="form-group position-relative">
                            <label class="control-label font-weight-bold">
                                <el-badge type="success" :value="getCustomer.person_type" class="item">
                                    <span>Cliente</span>
                                </el-badge>
                            </label>
                            <el-select
                                v-model="form.customer_id"
                                :loading="loading_search"
                                :remote-method="searchRemoteCustomers"
                                class="border-left rounded-left border-info customer-select-clearable"
                                dusk="customer_id"
                                filterable
                                @focus="focus_on_client = true"
                                @blur="focus_on_client = false"
                                placeholder="Escriba el nombre o número de documento del cliente"
                                popper-class="el-select-customers"
                                remote
                                @change="changeCustomer"
                                @keyup.enter.native="keyupCustomer"
                            >
                                <el-option
                                    v-for="option in customers"
                                    :key="option.id"
                                    :label="option.description"
                                    :value="option.id"
                                ></el-option>

                                <template slot="empty">
                                    <p v-if="loading_search" class="el-select-dropdown__empty">
                                        Cargando...
                                    </p>

                                    <p v-else class="el-select-dropdown__empty">
                                        No se encontraron resultados
                                    </p>

                                    <div
                                        v-if="!loading_search"
                                        class="el-select-dropdown__item new-option"
                                        @click.stop="openNewPersonDialog"
                                    >
                                        <span>{{ customerSearchTerm ? `Crear cliente "${customerSearchTerm}"` : 'Crear cliente' }}</span>
                                    </div>
                                </template>
                            </el-select>
                            <template v-if="form.customer_id">
                                <span class="btn-add-new btn-edit-person btn-add-new-invoice" @click.prevent="showDialogNewPerson = true; editPerson = true" title="Editar cliente">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user-edit"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h3.5" /><path d="M18.42 15.61a2.1 2.1 0 0 1 2.97 2.97l-3.39 3.42h-3v-3l3.42 -3.39" /></svg>
                                </span>
                            </template>
                            <template>
                                <span class="btn-add-new btn-add-new-invoice" @click.prevent="showDialogNewPerson = true; editPerson = false" title="Agregar nuevo cliente">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-user-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M16 19h6" /><path d="M19 16v6" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4" /></svg>
                                </span>
                            </template>
                            <small
                                v-if="errors.customer_id"
                                class="form-control-feedback"
                                v-text="errors.customer_id[0]"
                            ></small>
                        </div>

                        <!-- <div v-if="form.operation_type_id === '0101'" class="mt-2">
                            <el-checkbox v-model="form.is_itinerant" @change="changeItineratOption">
                                ¿Venta itinerante?
                            </el-checkbox>
                        </div> -->
                    <div class="points-system">
                            <div
                                v-if="config.enabled_point_system && form.customer_id"
                                class="d-flex align-items-center justify-content-between content-points"
                            >
                                <p class="fs-point-system m-0">
                                    <label class="font-weight-bold text-info"
                                        >Puntos acumulados: </label
                                    >
                                    <b>{{ customer_accumulated_points }}</b>

                                    <template v-if="hasExchangePoints()">
                                        -
                                        <b class="text-danger">{{ total_exchange_points}}</b>
                                        <b>{{ calculate_customer_accumulated_points }}</b>
                                    </template>
                                </p>
                                <span class="mx-1 text-muted">|</span>
                                <p class="fs-point-system m-0">
                                    <label class="font-weight-bold text-danger"
                                        >Puntos por la compra: </label
                                    >
                                    <b>{{ total_points_by_sale }}</b>
                                </p>
                            </div>
                        </div>
                    </template>

                    <template #customer_address_id>
                        <div v-if="customer_addresses.length > 0 && itinerant_option_id == 1" class="form-group mb-0">
                            <label class="control-label font-weight-bold">Dirección</label>
                            <el-select v-model="form.customer_address_id">
                                <el-option
                                    v-for="(option, addressIndex) in customer_addresses"
                                    :key="option.id != null ? option.id : 'principal-' + addressIndex"
                                    :label="option.address"
                                    :value="option.id"
                                ></el-option>
                            </el-select>
                        </div>
                        <div v-else-if="selectedCustomerAddressLabel" class="form-group mb-0">
                            <label class="control-label font-weight-bold">Dirección</label>
                            <el-input :value="selectedCustomerAddressLabel" readonly></el-input>
                        </div>
                        <div v-else class="form-group mb-0">
                            <label class="control-label font-weight-bold label-ghost">Dirección</label>
                            <el-input class="input-ghost" placeholder="Seleccione un cliente para ver dirección">
                            </el-input>
                        </div>
                    </template>

                    <template #date_of_issue>
                        <div :class="{ 'has-danger': errors.date_of_issue }" class="form-group">
                            <label class="control-label">Fec. Emisión</label>
                            <el-date-picker
                                v-model="form.date_of_issue"
                                :clearable="false"
                                :picker-options="datEmision"
                                :readonly="readonly_date_of_due"
                                type="date"
                                :format="dpDateFormat"
                                value-format="yyyy-MM-dd"
                                @change="changeDateOfIssue"
                            ></el-date-picker>
                            <small
                                v-if="errors.date_of_issue"
                                class="form-control-feedback"
                                v-text="errors.date_of_issue[0]"
                            ></small>
                        </div>
                    </template>

                    <template #date_of_due>
                        <div :class="{ 'has-danger': errors.date_of_due }" class="form-group">
                            <label class="control-label">Fec. Vencimiento</label>
                            <el-date-picker
                                v-model="form.date_of_due"
                                :clearable="false"
                                :readonly="readonly_date_of_due"
                                type="date"
                                :format="dpDateFormat"
                                value-format="yyyy-MM-dd"
                            ></el-date-picker>
                            <small
                                v-if="errors.date_of_due"
                                class="form-control-feedback"
                                v-text="errors.date_of_due[0]"
                            ></small>
                        </div>
                    </template>

                    <template #consigned_id>
                        <div :class="{ 'has-danger': errors.consigned_id }" class="form-group">
                            <label class="control-label fw-bold text-info">
                                Consignado
                                <a href="#" @click.prevent="showDialogConsignedForm = true">[+ Nuevo]</a>
                            </label>
                            <el-select class="w-100"
                                    v-model="form.consigned_id"
                                    @change="getConsignedAddresses"
                                    filterable
                                    placeholder="Seleccionar consignado">
                                <el-option v-for="option in consigneds"
                                        :key="option.id"
                                        :label="option.name"
                                        :value="option.id"></el-option>
                            </el-select>
                            <small v-if="errors.consigned_id"
                                class="invalid-feedback"
                                v-text="errors.consigned_id[0]"></small>
                        </div>
                    </template>

                    <template #consigned_address_id>
                        <div class="form-group mb-0">
                            <label class="control-label fw-bold text-info">Dirección</label>
                            <el-select v-model="form.consigned_address_id"
                                @change="changeConsignedAddresses">
                                <el-option v-for="option in consigned_addresses"
                                        :key="option.id"
                                        :label="option.address"
                                        :value="option.id"></el-option>
                            </el-select>
                        </div>
                    </template>

                    <template #currency_type_id>
                        <div :class="{ 'has-danger': errors.currency_type_id }" class="form-group money-input">
                            <label class="control-label">Moneda</label>
                            <el-select
                                :value="form.currency_type_id"
                                :disabled="isUpdateDocument"
                                @input="changeInvoiceCurrencyType"
                            >
                                <el-option
                                    v-for="option in documentCurrencies"
                                    :key="option.id"
                                    :label="option.description"
                                    :value="option.id"
                                ></el-option>
                            </el-select>
                            <small
                                v-if="errors.currency_type_id"
                                class="form-control-feedback"
                                v-text="errors.currency_type_id[0]"
                            ></small>
                        </div>
                    </template>

                    <template #exchange_rate_sale>
                        <div :class="{ 'has-danger': errors.exchange_rate_sale }" class="form-group change-type">
                            <label class="control-label"
                                >Tipo de cambio
                                <!-- ########## INICIO CAMBIO NELSON: RETIRO PALABRA SUNAT -->
                                <el-tooltip
                                    class="item"
                                    content="Tipo de cambio del día"
                                    effect="dark"
                                    placement="top-end"
                                >
                                    <i class="fa fa-info-circle"></i>
                                </el-tooltip>
                                <!-- ######### FIN CAMBIO NELSON: RETIRO PALABRA SUNAT -->
                            </label>
                            <el-input v-model="form.exchange_rate_sale" @input="recalculateItemsForExchangeRate"></el-input>
                            <small
                                v-if="errors.exchange_rate_sale"
                                class="form-control-feedback"
                                v-text="errors.exchange_rate_sale[0]"
                            ></small>
                        </div>
                    </template>

                    <template #itinerant_option_id>
                        <div class="form-group">
                            <label class="control-label">Punto de venta itinerante</label>
                            <el-select
                                v-model="itinerant_option_id"
                                class="border-left rounded-left border-info"
                                dusk="customer_id"
                                filterable
                                popper-class="el-select-customers"
                                @change="changeItineratOption"
                            >
                                <el-option
                                    v-for="option in option_address_itinerant"
                                    :key="option.id"
                                    :label="option.description"
                                    :value="option.id"
                                ></el-option>
                            </el-select>
                        </div>
                    </template>

                    <template #ruc_itinerant>
                        <div :class="{ 'has-danger': errors.exchange_rate_sale }" class="form-group">
                            <label class="control-label"
                                >RIF del establecimiento
                                <el-tooltip
                                    class="item"
                                    content=""
                                    effect="dark"
                                    placement="top-end"
                                >
                                    <i class="fa fa-info-circle"></i>
                                </el-tooltip>
                            </label>
                            <x-input-service v-model="ruc_itinerant"
                                             :identity_document_type_id="'6'"
                                             @search="searchNumber"></x-input-service>
                            <small
                                v-if="errors.exchange_rate_sale"
                                class="form-control-feedback"
                                v-text="errors.exchange_rate_sale[0]"
                            ></small>
                        </div>
                    </template>

                    <!-- Campos traídos desde Información Adicional -->
                    <template #purchase_order>
                        <div :class="{ 'has-danger': errors.purchase_order }" class="form-group">
                            <label class="control-label">Orden de Compra</label>
                            <el-input v-model="form.purchase_order" type="textarea"></el-input>
                            <small
                                v-if="errors.purchase_order"
                                class="form-control-feedback"
                                v-text="errors.purchase_order[0]"
                            ></small>
                        </div>
                    </template>

                    <template #additional_information>
                        <div class="form-group">
                            <label class="control-label">Observaciones</label>
                            <el-input v-model="form.additional_information" autosize type="textarea"></el-input>
                        </div>
                    </template>

                    <template #plate_number>
                        <div :class="{ 'has-danger': errors.plate_number }" class="form-group">
                            <label class="control-label">N° Placa</label>
                            <el-input v-model="form.plate_number" type="textarea"></el-input>
                            <small
                                v-if="errors.plate_number"
                                class="form-control-feedback"
                                v-text="errors.plate_number[0]"
                            ></small>
                        </div>
                    </template>

                    <template #seller_id>
                        <div class="form-group">
                            <label class="control-label">Vendedor</label>
                            <el-select v-model="form.seller_id" :disabled="typeUser == 'seller'">
                                <el-option
                                    v-for="option in filteredSellers"
                                    :key="option.id"
                                    :label="option.name"
                                    :value="option.id"
                                ></el-option>
                            </el-select>
                        </div>
                    </template>
                </document-form-pinned-bar>
                <custom-fields-renderer
                    ref="customFieldsRenderer"
                    document-type="documents"
                    :form-data.sync="form.custom_fields_data">
                </custom-fields-renderer>
                <div class="card-body card-body-invoice no-gutters border-0 shadow-none px-2 px-md-4">
                    <template v-if="showSearchItemsMainForm">
                        <div class="row">
                            <div
                                class="col-md-9 mb-4"
                                :class="{
                                    'col-md-12':
                                        configuration.enable_list_product
                                }"
                            >
                                <item-search-quick-sale
                                    @changeItem="changeItemQuickSale"
                                    :resource="resource"
                                    :showDetailButton="
                                        configuration.show_all_item_details
                                    "
                                    :selectedOptionPrice="
                                        selected_option_price
                                    "
                                    :configuration="config"
                                    ref="item_search_quick_sale"
                                >
                                </item-search-quick-sale>
                            </div>
                            <div class="col-md-3">
                                <el-select
                                    v-if="!configuration.enable_list_product"
                                    v-model="selected_option_price"
                                    filterable
                                    popper-class="price-list"
                                    style="width:100%;"
                                    class="input-price-default"
                                >
                                    <el-option
                                        v-for="option in price_options"
                                        :key="option.id"
                                        :label="option.description"
                                        :value="option.id"
                                    ></el-option>
                                </el-select>
                            </div>
                        </div>
                    </template>

                    <!-- Información Adicional -->
                    <div>
                        <!-- Botón para mostrar/ocultar el componente -->
                        <!-- <span
                            class="toggle-button toggle-button-invoice"
                            :class="{ shift: isVisible }"
                            @click="toggleInformation"
                            :title="isVisible ? 'Cerrar Información Adicional' : 'Abrir Información Adicional'"
                            v-if="isVisible"
                        >
                            <span class="toggle-button-text">
                                {{
                                    isVisible
                                        ? "Cerrar Información Adicional"
                                        : "Abrir Información Adicional"
                                }}
                            </span>
                        </span> -->

                        <div
                            class="additional-information px-4"
                            :class="{ show: isVisible }"
                        >
                            <h3 class="text-center">
                                Información Adicional
                            </h3>

                            <div class="close-container">
                                <i class="el-icon el-icon-close"
                                    @click="toggleInformation">
                                </i>
                            </div>
                            <div class="w-100">
                                <div
                                    v-if="relocatedFields.length"
                                    class="mt-5 no-gutters w-100"
                                >
                                    <div
                                        v-for="rf in relocatedFields"
                                        :key="rf.key"
                                        class="col-12 field-pinnable"
                                    >
                                        <button v-if="editingLayout" type="button" class="pin-from-form-btn" @click.prevent="pinFromForm(rf.key)"><i class="el-icon-top"></i> Fijar</button>
                                        <remote-slot
                                            :source="pinnedBarInstance"
                                            :slot-name="rf.key"
                                            :slot-scope-data="{ field: rf.field, width: 12 }"
                                        />
                                    </div>
                                </div>

                                <div class="mt-5 w-100">
                                    <div v-if="specialTaxRegimeAvailable || savedSpecialTaxRegime" class="col-12 py-2 switch-container">
                                        <div class="row no-gutters">
                                            <div class="col-10">Régimen especial de tributación</div>
                                            <div class="col-2 text-end">
                                                <el-switch v-model="specialTaxRegimeMode" :disabled="!specialTaxRegimeAvailable" @change="changeSpecialTaxRegimeMode" />
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="thirdPartyAvailable || originalThirdPartyInvoice" class="col-12 py-2 switch-container">
                                        <div class="row no-gutters">
                                            <div class="col-10">Factura por cuenta de terceros</div>
                                            <div class="col-2 text-end">
                                                <el-switch v-model="thirdPartyMode" :disabled="isUpdateDocument" @change="changeThirdPartyMode" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 switch-container">
                                        <div class="row no-gutters">
                                            <div class="col-10">
                                                ¿Es comprobante de
                                                contingencia?
                                            </div>
                                            <div class="col-2 text-end">
                                                <el-switch
                                                    v-model="is_contingency"
                                                    @change="
                                                        changeEstablishment
                                                    "
                                                ></el-switch>
                                            </div>
                                        </div>
                                    </div>
                                    <template v-if="!is_client">
                                        <div v-if="!prepayment_deduction" class="col-12 py-2 switch-container">
                                            <div class="row no-gutters">
                                                <div class="col-10">
                                                    ¿Es un pago anticipado?
                                                </div>
                                                <div
                                                    class="col-2 text-end"
                                                >
                                                    <el-switch
                                                        v-model="
                                                            form.has_prepayment
                                                        "
                                                        @change="
                                                            changeHasPrepayment
                                                        "
                                                    ></el-switch>
                                                </div>
                                            </div>
                                            <div v-if="form.has_prepayment || prepayment_deduction" class="mt-3">
                                                <el-select
                                                    v-model="
                                                        form.affectation_type_prepayment
                                                    "
                                                    class="mb-2"
                                                    @change="
                                                        changeAffectationTypePrepayment
                                                    "
                                                >
                                                    <el-option
                                                        :key="10"
                                                        :value="10"
                                                        label="Gravado"
                                                    ></el-option>
                                                    <el-option
                                                        :key="20"
                                                        :value="20"
                                                        label="Exonerado"
                                                    ></el-option>
                                                    <el-option
                                                        :key="30"
                                                        :value="30"
                                                        label="Inafecto"
                                                    ></el-option>
                                                </el-select>
                                            </div>
                                        </div>
                                        <div v-if="!form.has_prepayment" class="col-12 py-2 switch-container">
                                            <div class="row no-gutters">
                                                <div class="col-10">
                                                    Deducción de los pagos
                                                    anticipados
                                                </div>
                                                <div
                                                    class="col-2 text-end"
                                                >
                                                    <el-switch
                                                        v-model="
                                                            prepayment_deduction
                                                        "
                                                        @change="
                                                            changePrepaymentDeduction
                                                        "
                                                    ></el-switch>
                                                </div>
                                            </div>
                                            <div v-if="form.has_prepayment || prepayment_deduction" class="mt-3">
                                                <el-select
                                                    v-model="
                                                        form.affectation_type_prepayment
                                                    "
                                                    class="mb-2"
                                                    @change="
                                                        changeAffectationTypePrepayment
                                                    "
                                                >
                                                    <el-option
                                                        :key="10"
                                                        :value="10"
                                                        label="Gravado"
                                                    ></el-option>
                                                    <el-option
                                                        :key="20"
                                                        :value="20"
                                                        label="Exonerado"
                                                    ></el-option>
                                                    <el-option
                                                        :key="30"
                                                        :value="30"
                                                        label="Inafecto"
                                                    ></el-option>
                                                </el-select>
                                            </div>
                                            <template v-if="!is_client">
                                                <div
                                                    v-if="prepayment_deduction"
                                                    class=""
                                                >
                                                    <div class="form-group">
                                                        <table
                                                            style="width: 100%"
                                                        >
                                                            <tr
                                                                v-for="(row,
                                                                index) in form.prepayments"
                                                                :key="index"
                                                            >
                                                                <td>
                                                                    <el-select
                                                                        v-model="
                                                                            row.document_id
                                                                        "
                                                                        filterable
                                                                        @change="
                                                                            changeDocumentPrepayment(
                                                                                index
                                                                            )
                                                                        "
                                                                    >
                                                                        <el-option
                                                                            v-for="option in prepayment_documents"
                                                                            :key="
                                                                                option.id
                                                                            "
                                                                            :label="
                                                                                option.description
                                                                            "
                                                                            :value="
                                                                                option.id
                                                                            "
                                                                        ></el-option>
                                                                    </el-select>
                                                                </td>
                                                                <td>
                                                                    <el-input
                                                                        v-model="
                                                                            row.amount
                                                                        "
                                                                        @input="
                                                                            inputAmountPrepayment(
                                                                                index,
                                                                                $event
                                                                            )
                                                                        "
                                                                    ></el-input>
                                                                </td>
                                                                <td
                                                                    align="right"
                                                                >
                                                                    <button
                                                                        class="btn waves-effect waves-light btn-xs btn-danger"
                                                                        type="button"
                                                                        @click.prevent="
                                                                            clickRemovePrepayment(
                                                                                index
                                                                            )
                                                                        "
                                                                    >
                                                                        <i
                                                                            class="fa fa-trash"
                                                                        ></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        </table>

                                                        <label
                                                            class="control-label"
                                                        >
                                                            <a
                                                                class=""
                                                                href="#"
                                                                @click.prevent="
                                                                    clickAddPrepayment
                                                                "
                                                                ><i
                                                                    class="fa fa-plus font-weight-bold text-info"
                                                                ></i>
                                                                <span
                                                                    style="color: #777777"
                                                                    >Agregar
                                                                    comprobante
                                                                    anticipado</span
                                                                ></a
                                                            >
                                                        </label>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>

                                        <div
                                            v-if="
                                                !isExportInvoice && config.active_allowance_charge &&
                                                    hasPositiveInvoiceTotal
                                            "
                                            class="col-12 py-2 px-0"
                                        >
                                            <div class="row no-gutters">
                                                <div class="col-8">
                                                    <strong
                                                        >Porcentaje otros
                                                        cargos</strong
                                                    >
                                                </div>
                                                <div class="col-4">
                                                    <exact-decimal-input
                                                        ref="invoiceAllowanceInput"
                                                        :generation="invoiceDraftGeneration"
                                                        :value="invoiceAllowancePercent === null ? config.percentage_allowance_charge : invoiceAllowancePercent"
                                                        :apply="(value, input) => applyInvoiceDecimal('allowance', value, null, input)"
                                                        @cancelled="$message.warning($event)"
                                                        size="mini"
                                                    ></exact-decimal-input>
                                                </div>
                                            </div>
                                        </div>




                                        <div class="col-12 py-2 switch-container">
                                            <div class="row no-gutters">
                                                <div class="col-10">
                                                    Mostrar términos y
                                                    condiciones.
                                                </div>
                                                <div
                                                    class="col-2 text-end"
                                                >
                                                    <el-switch
                                                        v-model="
                                                            form.show_terms_condition
                                                        "
                                                    ></el-switch>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div
                                    class="mt-5 no-gutters w-100"
                                >
                                    <div class="col-12 field-pinnable" v-show="!isLayoutPinned('purchase_order')">
                                        <button v-if="editingLayout" type="button" class="pin-from-form-btn" @click.prevent="pinFromForm('purchase_order')"><i class="el-icon-top"></i>Fijar</button>
                                        <div
                                            :class="{
                                                'has-danger':
                                                    errors.purchase_order
                                            }"
                                            class="form-group"
                                        >
                                            <label class="control-label"
                                                >Orden de Compra</label
                                            >
                                            <el-input
                                                v-model="
                                                    form.purchase_order
                                                "
                                                type="textarea"
                                            >
                                            </el-input>
                                            <small
                                                v-if="errors.purchase_order"
                                                class="form-control-feedback"
                                                v-text="
                                                    errors.purchase_order[0]
                                                "
                                            ></small>
                                        </div>
                                    </div>
                                    <div class="col-12 field-pinnable" v-show="!isLayoutPinned('additional_information')">
                                        <button v-if="editingLayout" type="button" class="pin-from-form-btn" @click.prevent="pinFromForm('additional_information')"><i class="el-icon-top"></i> Fijar</button>
                                        <div class="form-group">
                                            <label class="control-label"
                                                >Observaciones</label
                                            >
                                            <el-input
                                                v-model="
                                                    form.additional_information
                                                "
                                                autosize
                                                type="textarea"
                                            >
                                            </el-input>
                                        </div>
                                    </div>
                                    <div class="col-12 field-pinnable" v-show="showPlateNumberField && !isLayoutPinned('plate_number')">
                                        <button v-if="editingLayout" type="button" class="pin-from-form-btn" @click.prevent="pinFromForm('plate_number')"><i class="el-icon-top"></i> Fijar</button>
                                        <div
                                            :class="{
                                                'has-danger':
                                                    errors.plate_number
                                            }"
                                            class="form-group"
                                        >
                                            <label class="control-label"
                                                >N° Placa</label
                                            >
                                            <el-input
                                                v-model="form.plate_number"
                                                type="textarea"
                                            >
                                            </el-input>
                                            <small
                                                v-if="errors.plate_number"
                                                class="form-control-feedback"
                                                v-text="
                                                    errors.plate_number[0]
                                                "
                                            ></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-5 w-100">
                                    <div class="form-group field-pinnable" v-show="!isLayoutPinned('seller_id')">
                                        <button v-if="editingLayout" type="button" class="pin-from-form-btn" @click.prevent="pinFromForm('seller_id')"><i class="el-icon-top"></i> Fijar</button>
                                        <label class="control-label"
                                            >Vendedor</label
                                        >
                                        <el-select
                                            v-model="form.seller_id"
                                            :disabled="typeUser == 'seller'"
                                        >
                                            <el-option
                                                v-for="option in filteredSellers"
                                                :key="option.id"
                                                :label="option.name"
                                                :value="option.id"
                                            ></el-option>
                                        </el-select>
                                    </div>
                                    <template
                                        v-if="!isActiveBussinessTurn('tap')"
                                    >
                                        <template v-if="!is_client">
                                            <div class="form-group">
                                                <label
                                                    class="control-label"
                                                >
                                                    Órdenes de entrega
                                                </label>
                                                <table style="width: 100%">
                                                    <tr
                                                        v-for="(guide,
                                                        index) in form.guides"
                                                    >
                                                        <td>
                                                            <el-select
                                                                v-model="
                                                                    guide.document_type_id
                                                                "
                                                            >
                                                                <el-option
                                                                    v-for="option in document_types_guide"
                                                                    :key="
                                                                        option.id
                                                                    "
                                                                    :label="
                                                                        option.description
                                                                    "
                                                                    :value="
                                                                        option.id
                                                                    "
                                                                ></el-option>
                                                            </el-select>
                                                        </td>
                                                        <td>
                                                            <el-input
                                                                v-model="
                                                                    guide.number
                                                                "
                                                            ></el-input>
                                                        </td>
                                                        <td align="right">
                                                            <button
                                                                class="btn waves-effect waves-light btn-xs btn-danger"
                                                                type="button"
                                                                @click.prevent="
                                                                    clickRemoveGuide(
                                                                        index
                                                                    )
                                                                "
                                                            >
                                                                <i
                                                                    class="fa fa-trash"
                                                                ></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="3">
                                                            <label
                                                                class="control-label"
                                                            >
                                                                <a
                                                                    class=""
                                                                    href="#"
                                                                    @click.prevent="
                                                                        clickAddGuide
                                                                    "
                                                                    ><i
                                                                        class="fa fa-plus font-weight-bold text-info"
                                                                    ></i>
                                                                    <span
                                                                        >Agregar orden
                                                                        de entrega</span
                                                                    ></a
                                                                >
                                                            </label>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </template>
                                    </template>
                                    <template v-else>
                                        <template v-if="!is_client">
                                            <div class="form-group">
                                                <label
                                                    class="control-label"
                                                >
                                                    Órdenes de entrega
                                                </label>
                                                <table style="width: 100%">
                                                    <tr
                                                        v-for="(guide,
                                                        index) in form.guides"
                                                    >
                                                        <td>
                                                            <el-select
                                                                v-model="
                                                                    guide.document_type_id
                                                                "
                                                            >
                                                                <el-option
                                                                    v-for="option in document_types_guide"
                                                                    :key="
                                                                        option.id
                                                                    "
                                                                    :label="
                                                                        option.description
                                                                    "
                                                                    :value="
                                                                        option.id
                                                                    "
                                                                ></el-option>
                                                            </el-select>
                                                        </td>
                                                        <td>
                                                            <el-input
                                                                v-model="
                                                                    guide.number
                                                                "
                                                            ></el-input>
                                                        </td>
                                                        <td align="right">
                                                            <button
                                                                class="btn waves-effect waves-light btn-xs btn-danger"
                                                                type="button"
                                                                @click.prevent="
                                                                    clickRemoveGuide(
                                                                        index
                                                                    )
                                                                "
                                                            >
                                                                <i
                                                                    class="fa fa-trash"
                                                                ></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="3">
                                                            <label
                                                                class="control-label"
                                                            >
                                                                <a
                                                                    class=""
                                                                    href="#"
                                                                    @click.prevent="
                                                                        clickAddGuide
                                                                    "
                                                                    ><i
                                                                        class="fa fa-plus font-weight-bold text-info"
                                                                    ></i>
                                                                    <span
                                                                        style="color: #777777"
                                                                        >Agregar orden
                                                                        de entrega</span
                                                                    ></a
                                                                >
                                                            </label>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </template>
                                    </template>
                                    <!-- propinas -->
                                    <template
                                        v-if="
                                            config.enabled_tips_pos &&
                                                !isUpdateDocument
                                        "
                                    >
                                        <set-tip
                                            class="full py-2 border-top mb-1 mt-2"
                                            @changeDataTip="changeDataTip"
                                        ></set-tip>
                                    </template>
                                    <!-- propinas -->
                                </div>
                                <!-- <div
                                    v-if="isActiveBussinessTurn('hotel')"
                                >
                                    <el-tooltip
                                        class="item my-2"
                                        content="Datos personales para reserva de hospedaje"
                                        effect="dark"
                                        placement="bottom-end"
                                    >
                                        <button
                                            class="btn btn-primary btn-block"
                                            @click.prevent="
                                                clickAddDocumentHotel
                                            "
                                        >
                                            Datos de reserva
                                        </button>
                                    </el-tooltip>
                                </div> -->
                                <div
                                    v-if="isActiveBussinessTurn('transport')"
                                    class="px-5"
                                >
                                    <el-tooltip
                                        class="item my-2"
                                        content="Datos para transporte de pasajeros"
                                        effect="dark"
                                        placement="bottom-end"
                                    >
                                        <button
                                            class="btn btn-primary btn-block"
                                            @click.prevent="
                                                clickAddDocumentTransport
                                            "
                                        >
                                            Datos de transporte
                                        </button>
                                    </el-tooltip>
                                </div>
                                <div
                                    class="d-flex col-12 justify-content-center"
                                >
                                    <div class="w-md-50">
                                        <button
                                            class="btn btn-primary btn-block mt-2"
                                            :disabled="
                                                form.customer_id == null
                                            "
                                            @click.prevent="
                                                visibleDialogReportCustomer
                                            "
                                        >
                                            Consulta de documentos
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Fin de informacion adicional -->

                    <div class="col add-row-table mx-0 py-3 fs-6" v-if="form.items <= 0" @click.prevent="clickAddItemInvoice">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-circle-plus"><path data-v-cdc5f86e="" stroke="none" d="M0 0h24v24H0z" fill="none"></path><path data-v-cdc5f86e="" d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path><path data-v-cdc5f86e="" d="M9 12h6"></path><path data-v-cdc5f86e="" d="M12 9v6"></path></svg>
                        Agregar producto
                        <kbd>F2</kbd>
                    </div>
                    <div class="table-responsive" v-loading="loading_items" v-else>
                        <table class="table table-sm">
                            <thead>
                                <tr class="table-titles-default">
                                    <th width="0.5%"></th>
                                    <th
                                        class="font-weight-bold"
                                        width="30%"
                                    >
                                        Descripción
                                    </th>
                                    <th
                                        class="text-center font-weight-bold"
                                        width="8%"
                                    >
                                        Unidad
                                    </th>
                                    <th class="text-end font-weight-bold"
                                        width="8%">
                                        Cantidad
                                    </th>
                                    <!-- <th class="text-end font-weight-bold">
                                        Valor Unitario
                                    </th> -->
                                    <th class="text-end font-weight-bold">
                                        Precio Unitario
                                    </th>
                                    <!-- <th class="text-end font-weight-bold">
                                        Subtotal
                                    </th> -->
                                    <th class="text-end font-weight-bold">
                                        Descuento
                                    </th>
                                    <!--<th class="text-end font-weight-bold">Cargo</th>-->
                                    <th class="text-end font-weight-bold">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- ########### INICIO CONTRATO FLUJO DE PRODUCTOS -->
                                <tr
                                    v-for="(row, index) in form.items"
                                    :key="invoiceInputKey(row)"
                                >
                                    <td></td>
                                    <td>
                                        <template v-if="canAddDescriptionToDocumentItem">
                                            <label
                                                v-if="row.name_product_pdf"
                                                v-html="row.name_product_pdf"
                                            ></label>
                                            <span
                                                v-else
                                                v-text="setDescriptionOfItem(row.item)"
                                            ></span>
                                        </template>
                                        <template v-else>
                                            {{ setDescriptionOfItem(row.item) }}
                                        </template>

                                        <pack-item-description
                                            v-if="row.item.is_set && configuration.show_item_description_pack"
                                            :item-id="row.item_id"
                                        ></pack-item-description>

                                        <template v-if="row.item.presentation && row.item.presentation.description">
                                            {{ row.item.presentation.description }}
                                        </template>
                                        <br>
                                        <small v-if="row.affectation_igv_type">
                                            {{ row.affectation_igv_type.description }}
                                        </small>

                                        <template v-if="row.item.lots && row.item.lots.length > 0">
                                            <br>Series: {{ showItemSeries(row.item.lots) }}
                                        </template>

                                        <template v-if="itemRequiresLot(row)">
                                            <br>
                                            <button
                                                v-if="rowNeedsLotAssignment(row)"
                                                type="button"
                                                class="btn waves-effect waves-light btn-xs btn-warning mt-1"
                                                @click.prevent="openLotGroupDialog(index, row)"
                                            >
                                                Asignar lote
                                            </button>
                                            <template v-else>
                                                <small class="text-success">
                                                    Lotes: {{ showItemLots(resolveIdLoteSelected(row)) }}
                                                </small>
                                                <button
                                                    type="button"
                                                    class="btn waves-effect waves-light btn-xs btn-outline-secondary ms-1"
                                                    @click.prevent="openLotGroupDialog(index, row)"
                                                >
                                                    Cambiar lote
                                                </button>
                                            </template>
                                        </template>

                                        <template
                                            v-if="config.enabled_point_system && hasAccumulatedPoints() && row.item.exchange_points"
                                        >
                                            <el-checkbox
                                                v-model="row.item.exchanged_for_points"
                                                class="mt-2 mb-2"
                                                @change="changeRowExchangePoints(row, index)"
                                            >
                                                <b>{{ getExchangePointDescription(row) }}</b>
                                            </el-checkbox>
                                        </template>

                                        <span
                                            v-if="fnApplyRestrictSaleItemsCpe && isGeneratedFromExternal && fnIsRestrictedForSale(row.item, form.document_type_id)"
                                            class="text-danger mt-1 mb-2 d-block"
                                        >
                                            Restringido para venta
                                        </span>

                                        <p class="control-label font-weight-bold text-info mt-2">
                                            <a
                                                v-if="configuration.show_all_item_details"
                                                class="btn btn-sm second-buton btn-xs"
                                                href="#"
                                                @click.prevent="clickShowItemDetail(row.item_id)"
                                            >Ver detalle</a>
                                            <button
                                                class="btn waves-effect waves-light btn-xs btn-info ms-1"
                                                type="button"
                                                title="Editar producto"
                                                @click="ediItem(row, index)"
                                            >
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <button
                                                class="btn waves-effect waves-light btn-xs btn-danger ms-1"
                                                type="button"
                                                title="Eliminar producto"
                                                @click.prevent="clickRemoveItem(index)"
                                            >
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </p>
                                    </td>
                                    <td class="text-center">
                                        {{ row.item.unit_type_id }}
                                    </td>
                                    <td class="text-end">
                                        <el-input
                                            v-if="showEditableItems"
                                            v-model="row.quantity"
                                            :disabled="hasRowAdvancedOption(row)"
                                            class="input-custom"
                                            style="min-width: 70px !important"
                                            @change="changeRowQuantity(row)"
                                            @focus="beginEditableInvoiceRow(row, 'quantity', $event)"
                                         inputmode="decimal"></el-input>
                                        <template v-else>{{ row.quantity }}</template>
                                    </td>
                                    <td class="text-end">
                                        <div v-if="showEditableItems" class="input-with-currency">
                                            <span class="currency-symbol">{{ documentCurrencySymbol }}</span>
                                            <el-input
                                                v-model="row.unit_price"
                                                :disabled="hasRowAdvancedOption(row) || !hasPermissionEditItemPrices(authUser.permission_edit_item_prices)"
                                                class="input-custom"
                                                style="min-width: 98px !important"
                                                @change="changeRowUnitPrice(row)"
                                                @focus="beginEditableInvoiceRow(row, 'unit_price', $event)"
                                             inputmode="decimal"></el-input>
                                        </div>
                                        <template v-else>
                                            {{ documentCurrencySymbol }}
                                            {{ documentRowUnitPrice(row) }}
                                        </template>
                                    </td>
                                    <td class="text-end">
                                        <div v-if="showEditableItems" class="input-with-currency">
                                            <span class="currency-symbol">{{ documentCurrencySymbol }}</span>
                                            <exact-decimal-input
                                                ref="invoiceRowDiscountInputs"
                                                :generation="invoiceDraftGeneration"
                                                :value="rowDiscountValue(row)"
                                                :precision="2"
                                                :context-key="form.currency_type_id"
                                                :disabled="isRowDiscountLocked(row) || !hasPermissionEditItemPrices(authUser.permission_edit_item_prices)"
                                                class="input-custom"
                                                style="min-width: 98px !important"
                                                :apply="(value, input) => applyInvoiceDecimal('row', value, index, input)"
                                                @cancelled="$message.warning($event)"
                                                @focus="valueInputSelect($event)"
                                            ></exact-decimal-input>
                                        </div>
                                        <template v-else>
                                            {{ documentCurrencySymbol }} {{ setTextDiscountItem(row) }}
                                        </template>
                                    </td>
                                    <td class="text-end">
                                        <div v-if="showEditableItems" class="input-with-currency">
                                            <span class="currency-symbol">{{ documentCurrencySymbol }}</span>
                                            <el-input
                                                v-model="row.total"
                                                :disabled="hasRowAdvancedOption(row) || !hasPermissionEditItemPrices(authUser.permission_edit_item_prices)"
                                                class="input-custom"
                                                style="min-width: 98px !important"
                                                @change="changeRowTotal(row)"
                                                @focus="beginEditableInvoiceRow(row, 'total', $event)"
                                             inputmode="decimal"></el-input>
                                        </div>
                                        <template v-else>
                                            {{ documentCurrencySymbol }} {{ row.total }}
                                        </template>
                                    </td>
                                </tr>
                                <tr v-if="form.items.length > 0">
                                    <td colspan="7" class="pt-1">
                                        <button
                                            class="btn waves-effect waves-light add-row-table m-0 py-2 px-4 fs-6"
                                            type="button"
                                            @click.prevent="clickAddItemInvoice"
                                        >
                                            <i class="fa fa-plus-circle"></i>
                                            Agregar producto
                                            <kbd>F2</kbd>
                                        </button>
                                        <span class="total-rows ms-3">
                                            Total de ítems: {{ form.items.length }}
                                        </span>
                                    </td>
                                </tr>
                                <!-- ########### FIN CONTRATO FLUJO DE PRODUCTOS -->
                            </tbody>
                        </table>
                    </div>
                    <!-- ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA -->
                    <div
                        class="row justify-content-end mt-3"
                        data-testid="invoice-totals-payments"
                    >
                        <!-- ######## INICIO RESUMEN REACTIVO IVA E IGTF ######## -->
                        <div v-if="!isUpdateDocument" class="col-12 col-lg-5 mb-3 mb-lg-0" data-testid="invoice-tax-summary-column">
                            <document-tax-summary
                                :document="form"
                                :iva-rate="percentage_igv"
                                :currency-types="currency_types"
                                :settings="igtfSettings"
                                :quote="displayFiscalQuote"
                                :quote-pending="igtfQuoteLoading || (!!fiscalQuoteKey && igtfQuoteKey !== fiscalQuoteKey)"
                                :quote-error="igtfQuoteError"
                            />
                        </div>
                        <!-- ######## FIN RESUMEN REACTIVO IVA E IGTF ######## -->
                        <div class="col-12 col-lg-7 text-center table-responsive">
                            <calculation-review :context="localCalculationContext" v-if="useLocalCalculations" :summary="localCalculationReview && localCalculationReview.summary" :loading="igtfQuoteLoading" :error="igtfQuoteError" :retryable="!localCalculationContext" @retry="refreshLocalContext(true)" />
                            <table
                                class="table table-sm text-end invoice-totals"
                            >

                                <tr v-if="is_restaurant_active">
                                    <td >
                                        RECARGO POR CONSUMO Y/O PROPINA
                                            ({{ restaurant_tip_factor ? restaurant_tip_factor : 0 }}%)
                                            <el-checkbox
                                                v-model="is_consumption_charge"
                                                class="ml-1 mr-1"
                                                @change="chargeConsumptionSurcharge"
                                            ></el-checkbox>
                                        :
                                    </td>
                                        <td>
                                            {{ documentCurrencySymbol }}
                                            {{ total_consumption_charge }}
                                        </td>
                                </tr>
                                <tr
                                    v-if="
                                        hasPositiveInvoiceTotal &&
                                            (enabled_discount_global || recordDiscountsGlobal)
                                    "
                                >
                                    <td>
                                        <el-tooltip
                                            class="item"
                                            :content="
                                                global_discount_type.description
                                            "
                                            effect="dark"
                                            placement="top"
                                        >
                                            <i
                                                class="fa fa-info-circle"
                                            ></i>
                                        </el-tooltip>

                                        DESCUENTO
                                        <template v-if="is_amount">
                                            MONTO</template
                                        >
                                        <template v-else>
                                            %</template
                                        >
                                        <el-checkbox
                                            :value="is_amount"
                                            class="ml-1 mr-1"
                                            @input="changeInvoiceDiscountMode"
                                        ></el-checkbox>
                                        :
                                    </td>
                                    <td>
                                        <exact-decimal-input
                                            ref="invoiceDiscountInput"
                                            :generation="invoiceDraftGeneration"
                                            :value="total_global_discount"
                                            :exact-source="invoiceGlobalDiscountSource ? invoiceGlobalDiscountSource.amount : null"
                                            :apply="(value, input) => applyInvoiceDecimal('discount', value, null, input)"
                                            :context-key="form.currency_type_id + '|' + is_amount"
                                            @cancelled="$message.warning($event)"
                                            class="input-custom"
                                        ></exact-decimal-input>

                                        <!-- <el-input v-model="total_global_discount"
                                                  class="input-custom"
                                                  @input="calculateTotal"></el-input> -->
                                    </td>
                                </tr>








                                <tr v-if="hasPositiveInvoiceAmount('total_exportation')">
                                    <td>EXPORTACIÓN (IVA 0 %):</td>
                                    <td>
                                        {{ documentCurrencySymbol }}
                                        {{ form.total_exportation }}
                                    </td>
                                </tr>


                                <tr v-if="hasPositiveInvoiceAmount('total_exonerated')">
                                    <td>OP.EXONERADAS:</td>
                                    <td>
                                        {{ documentCurrencySymbol }}
                                        {{ form.total_exonerated }}
                                    </td>
                                </tr>
                                <tr v-if="hasPositiveInvoiceAmount('total_taxed')">
                                    <td>Op. gravada:</td>
                                    <td>
                                        {{ documentCurrencySymbol }}
                                        {{ form.total_taxed }}
                                    </td>
                                </tr>
                                <tr v-if="hasPositiveInvoicePrepayment">
                                    <td>ANTICIPOS:</td>
                                    <td>
                                        {{ documentCurrencySymbol }}
                                        {{ form.total_prepayment }}
                                    </td>
                                </tr>
                                <tr v-if="hasPositiveInvoiceAmount('total_igv')">
                                    <!-- ########### INICIO CAMBIO IVA VENEZUELA -->
                                    <td>IVA:</td>
                                    <!-- ########### FIN CAMBIO IVA VENEZUELA -->
                                    <td>
                                        {{ documentCurrencySymbol }}
                                        {{ form.total_igv }}
                                    </td>
                                </tr>



                                <tr
                                    v-if="
                                        hasPositiveInvoiceAmount('subtotal') &&
                                            (hasPositiveInvoiceAmount('total_discount') || hasPositiveInvoiceDiscount)
                                    "
                                >
                                    <td>SUBTOTAL:</td>
                                    <td>
                                        {{ documentCurrencySymbol }}
                                        {{ displaySubtotalBeforeDiscount }}
                                    </td>
                                </tr>

                                <tr v-if="hasPositiveInvoiceDiscount">
                                    <td>DESCUENTOS TOTALES:</td>
                                    <td>
                                        {{ documentCurrencySymbol }}
                                        {{ totalDiscount }}
                                    </td>
                                </tr>

                                <tr v-if="hasPositiveInvoiceTotal">
                                    <td>Otros cargos:</td>
                                    <td>
                                        {{ documentCurrencySymbol }}
                                        <exact-decimal-input
                                            ref="invoiceChargeInput"
                                            :generation="invoiceDraftGeneration"
                                            :value="total_global_charge"
                                            :exact-source="invoiceGlobalChargeSource ? invoiceGlobalChargeSource.amount : null"
                                            :apply="(value, input) => applyInvoiceDecimal('charge', value, null, input)"
                                            :context-key="form.currency_type_id"
                                            @cancelled="$message.warning($event)"
                                            :disabled="
                                                !isExportInvoice && config.active_allowance_charge ==
                                                true
                                                    ? true
                                                    : false
                                            "
                                            class="input-custom"
                                        ></exact-decimal-input>
                                    </td>
                                </tr>

                                <tr v-if="hasPositiveInvoiceTotal" :class="{'invoice-total-due': !fiscalPaymentsApplicable || isUpdateDocument}">
                                    <td><strong v-if="fiscalPaymentsApplicable && !isUpdateDocument">Total de la venta</strong><template v-else><strong>Total a pagar</strong></template>:</td>
                                    <td>
                                        {{ documentCurrencySymbol }}
                                        {{ form.total }}
                                    </td>
                                </tr>
                                <tr v-if="invoiceSettlementAdjustmentLabel">
                                    <td>Ajuste de liquidación:</td>
                                    <td>{{ documentCurrencySymbol }} {{ invoiceSettlementAdjustmentLabel }}</td>
                                </tr>
                                <!-- ######## INICIO TOTAL A PAGAR CON IGTF ######## -->
                                <tr v-if="hasPositiveInvoiceTotal && fiscalPaymentsApplicable && !isUpdateDocument" class="invoice-total-due" data-testid="invoice-total-with-igtf">
                                    <td><strong>Total a pagar:</strong></td>
                                    <td>
                                        <template v-if="invoiceTotalWithIgtf !== null">{{ documentCurrencySymbol }} {{ invoiceTotalWithIgtf }}</template>
                                        <template v-else>—</template>
                                        <small v-if="igtfQuoteLoading || (!!fiscalQuoteKey && igtfQuoteKey !== fiscalQuoteKey)" class="text-muted">Evaluando…</small>
                                    </td>
                                </tr>
                                <!-- ######## FIN TOTAL A PAGAR CON IGTF ######## -->
                                <tr v-if="hasPositiveInvoiceTotal && fiscalPaymentsApplicable && !isUpdateDocument" data-testid="invoice-change">
                                    <td><strong>Vuelto:</strong></td>
                                    <td>
                                        <template v-if="displayFiscalQuote && displayFiscalQuote.payments.length">
                                            <span v-if="fiscalChangeTotal !== null">{{ fiscalChangeTotal }} {{ form.currency_type_id }} ⇄ {{ fiscalEquivalent(fiscalChangeTotal, {currency_type_id: form.currency_type_id, exchange_rate: form.exchange_rate_sale}) }}</span>
                                            <span v-else>—</span>
                                            <span v-for="entry in fiscalChangeReceipts" :key="entry.index" :class="{'d-block': fiscalChangeReceipts.length > 1}">
                                                <small v-if="fiscalChangeReceipts.length > 1">Pago {{ entry.index + 1 }}: {{ entry.payment.change }} {{ entry.payment.currency_type_id }}</small>
                                                <change-disbursement-selector v-if="fiscalSourceForQuote(entry.index)" :receipt="entry.payment"
                                                    :lines="fiscalSourceForQuote(entry.index).change_disbursements || null" :methods="payment_method_types" :destinations="payment_destinations" :establishment-id="form.establishment_id"
                                                    :disabled="igtfQuoteLoading || igtfQuoteKey !== fiscalQuoteKey"
                                                    @apply="$set(fiscalSourceForQuote(entry.index), 'change_disbursements', $event)"
                                                    @clear="$delete(fiscalSourceForQuote(entry.index), 'change_disbursements')" />
                                            </span>
                                        </template>
                                        <template v-else>—</template>
                                        <small v-if="igtfQuoteLoading || (!!fiscalQuoteKey && igtfQuoteKey !== fiscalQuoteKey)" class="text-muted">Evaluando…</small>
                                    </td>
                                </tr>
                            </table>
                            <table class="table table-sm text-end" style="width: 100%;">
                                <tr v-if="hasPositiveInvoiceTotal">
                                    <!-- Metodos de pago -->
                                    <td class="p-0" colspan="2">
                                      <div class="payment-section">
                                        <div class="payment-section__condition">
                                        <span>CONDICIÓN DE PAGO:</span>
                                        <el-select
                                            v-model="
                                                form.payment_condition_id
                                            "
                                            :disabled="isUpdateDocument"
                                            dusk="document_type_id"
                                            popper-class="el-select-document_type"
                                            style="max-width: 200px;"
                                            @change="changePaymentCondition"
                                        >
                                            <el-option
                                                label="Crédito con cuotas"
                                                value="03"
                                                :disabled="customer_has_expired"
                                            ></el-option>
                                            <el-option
                                                label="Crédito"
                                                value="02"
                                                :disabled="customer_has_expired"
                                            ></el-option>
                                            <el-option
                                                label="Contado"
                                                value="01"
                                            ></el-option>
                                        </el-select>
                                        </div>
                                    <div v-if="customer_has_expired">
                                        <div class="alert alert-danger mt-2 mb-0 text-center">
                                            El cliente excede los {{ config.finances.max_expired_days }} días de vencimiento de crédito. Solo puede emitir comprobantes al contado.
                                        </div>
                                    </div>
                                        <!-- Crédito con cuotas -->
                                        <div
                                            v-if="
                                                form.payment_condition_id ===
                                                    '03' && !isUpdateDocument
                                            "
                                        >
                                            <table
                                                class="text-start"
                                                width="100%"
                                            >
                                                <thead>
                                                    <tr
                                                        v-if="
                                                            form.fee
                                                                .length > 0
                                                        "
                                                    >
                                                        <th
                                                            class="text-start"
                                                            style="width: 100px"
                                                        >
                                                            Fecha
                                                        </th>
                                                        <th
                                                            class="text-start"
                                                            style="width: 100px"
                                                        >
                                                            Monto
                                                        </th>
                                                        <th
                                                            style="width: 30px"
                                                        ></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="(row,
                                                        index) in form.fee"
                                                        :key="index"
                                                    >
                                                        <td>
                                                            <el-date-picker
                                                                v-model="
                                                                    row.date
                                                                "
                                                                :clearable="
                                                                    false
                                                                "
                                                                :format="dpDateFormat"
                                                                type="date"
                                                                @change="
                                                                    changeCreditFeeDate(
                                                                        index
                                                                    )
                                                                "
                                                                value-format="yyyy-MM-dd"
                                                            ></el-date-picker>
                                                        </td>
                                                        <td>
                                                            <el-input
                                                                v-model="
                                                                    row.amount
                                                                "
                                                            ></el-input>
                                                        </td>
                                                        <td
                                                            class="text-center"
                                                        >
                                                            <button
                                                                v-if="
                                                                    index >
                                                                        0
                                                                "
                                                                class="btn waves-effect waves-light btn-xs btn-danger"
                                                                type="button"
                                                                @click.prevent="
                                                                    clickRemoveFee(
                                                                        index
                                                                    )
                                                                "
                                                            >
                                                                <i
                                                                    class="fa fa-trash"
                                                                ></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="5">
                                                            <label
                                                                class="control-label"
                                                            >
                                                                <a
                                                                    class=""
                                                                    href="#"
                                                                    @click.prevent="
                                                                        clickAddFee
                                                                    "
                                                                    ><i
                                                                        class="fa fa-plus font-weight-bold text-info"
                                                                    ></i>
                                                                    <span
                                                                        style="color: #777777"
                                                                        >Agregar
                                                                        cuota</span
                                                                    ></a
                                                                >
                                                            </label>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- Credito -->
                                        <div
                                            v-if="
                                                form.payment_condition_id ===
                                                    '02' && !isUpdateDocument
                                            "
                                        >
                                            <table
                                                v-if="form.fee.length > 0"
                                                class="text-start"
                                                width="100%"
                                            >
                                                <thead>
                                                    <tr>
                                                        <th
                                                            v-if="
                                                                form.fee
                                                                    .length >
                                                                    0
                                                            "
                                                            style="width: 120px"
                                                        >
                                                            Método de pago
                                                        </th>
                                                        <th
                                                            class="text-start"
                                                            style="width: 100px"
                                                        >
                                                            Fecha
                                                        </th>
                                                        <th
                                                            class="text-start"
                                                            style="width: 100px"
                                                        >
                                                            Monto
                                                        </th>
                                                        <th
                                                            style="width: 30px"
                                                        ></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="(row,
                                                        index) in form.fee"
                                                        :key="index"
                                                    >
                                                        <td>
                                                            <el-select
                                                                v-model="row.payment_method_type_id"
                                                                @change="changePaymentMethodType(index, true)"
                                                            >
                                                                <el-option
                                                                    v-for="option in credit_payment_metod"
                                                                    :key="option.id"
                                                                    :label="option.description"
                                                                    :value="option.id"
                                                                ></el-option>
                                                            </el-select>
                                                        <small v-if="!row.payment_method_type_id" class="text-muted">{{ credit_payment_metod.length ? 'Seleccione un método de crédito.' : 'No hay un método de crédito activo disponible.' }}</small>
                                                        </td>
                                                        <td>
                                                            <el-date-picker
                                                                v-model="
                                                                    row.date
                                                                "
                                                                :clearable="
                                                                    false
                                                                "
                                                                :format="dpDateFormat"
                                                                type="date"
                                                                value-format="yyyy-MM-dd"
                                                            >
                                                            </el-date-picker>
                                                        </td>
                                                        <td>
                                                            <el-input
                                                                v-model="
                                                                    row.amount
                                                                "
                                                                :readonly="
                                                                    true
                                                                "
                                                            ></el-input>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div v-if="isUpdateDocument" class="mt-4" data-testid="existing-payments-readonly">
                                            <p>Los cobros registrados se conservan. Adminístrelos desde Pagos.</p>
                                            <table class="table"><thead><tr><th>Fecha</th><th>Medio</th><th>Monto aplicado</th></tr></thead>
                                                <tbody><tr v-for="payment in existingPayments" :key="payment.id">
                                                    <td>{{ String(payment.date_of_payment || '').slice(0, 10) }}</td>
                                                    <td>{{ payment.payment_method_type ? payment.payment_method_type.description : payment.payment_method_type_id }}</td>
                                                    <td>{{ payment.payment }} {{ payment.currency_type_id }}</td>
                                                </tr></tbody>
                                            </table>
                                        </div>
                                        <div v-if="isUpdateDocument" class="text-muted">
                                            <p v-for="retention in existingRetentions" :key="retention.id">Retención {{ retention.tax_kind }} {{ retention.voucher_number }}: {{ retention.applied_amount }} {{ form.currency_type_id }}</p>
                                            <p v-if="existingGuaranteeFund">Fondo de garantía: {{ existingGuaranteeFund.amount }} {{ form.currency_type_id }}</p>
                                        </div>
                                        <!-- Contado -->
                                        <div
                                            v-if="
                                                !isUpdateDocument && !is_receivable && form.payment_condition_id === '01'
                                            "
                                            class="table-responsive payment mt-4"
                                        >
                                            <table
                                                class="text-start table payment-method"
                                            >
                                                <!-- ######## INICIO ANCHO DEL METODO DE PAGO ######## -->
                                                <colgroup>
                                                    <col v-if="showLoadVoucher" style="width: 55px">
                                                    <col class="payment-method-column">
                                                    <col v-if="enabled_payments">
                                                    <col v-if="enabled_payments">
                                                    <col v-if="enabled_payments">
                                                    <col v-if="enabled_payments" style="width: 40px">
                                                </colgroup>
                                                <!-- ######## FIN ANCHO DEL METODO DE PAGO ######## -->
                                                <thead>
                                                    <tr>
                                                        <template
                                                            v-if="
                                                                showLoadVoucher &&
                                                                    form
                                                                        .payments
                                                                        .length >
                                                                        0
                                                            "
                                                        >
                                                            <th
                                                                style="min-width:55px"
                                                            >
                                                                Voucher
                                                            </th>
                                                        </template>

                                                        <!-- ######## INICIO ANCHO DEL METODO DE PAGO ######## -->
                                                        <th
                                                            v-if="
                                                                form
                                                                    .payments
                                                                    .length >
                                                                    0
                                                            "
                                                            class="payment-method-column"
                                                        >
                                                            Método de pago
                                                        </th>
                                                        <!-- ######## FIN ANCHO DEL METODO DE PAGO ######## -->
                                                        <template
                                                            v-if="
                                                                enabled_payments
                                                            "
                                                        >
                                                            <th
                                                                v-if="
                                                                    form
                                                                        .payments
                                                                        .length >
                                                                        0
                                                                "
                                                                style="min-width: 140px"
                                                            >
                                                                Destino
                                                                <el-tooltip
                                                                    class="item"
                                                                    content="Aperture caja o cuentas bancarias"
                                                                    effect="dark"
                                                                    placement="top-start"
                                                                >
                                                                    <i
                                                                        class="fa fa-info-circle"
                                                                    ></i>
                                                                </el-tooltip>
                                                            </th>
                                                            <th
                                                                v-if="
                                                                    form
                                                                        .payments
                                                                        .length >
                                                                        0
                                                                "
                                                                style="min-width: 140px"
                                                            >
                                                                Referencia
                                                            </th>
                                                            <th
                                                                v-if="
                                                                    form
                                                                        .payments
                                                                        .length >
                                                                        0
                                                                "
                                                                style="min-width: 90px"
                                                            >
                                                                {{ fiscalPaymentsApplicable ? 'Total entregado' : 'Monto' }}
                                                            </th>
                                                            <th
                                                                style="min-width: 40px"
                                                            ></th>
                                                        </template>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="(row,
                                                        index) in form.payments"
                                                        :key="index"
                                                    >
                                                        <template
                                                            v-if="
                                                                showLoadVoucher
                                                            "
                                                        >
                                                            <td
                                                                class=""
                                                                style="width: 50px"
                                                            >
                                                                <!-- <el-tooltip class="item" content="Cargar voucher" effect="dark" placement="top-start"> -->
                                                                <el-upload
                                                                    v-if="!isRecordedReceipt(row)"
                                                                    :data="{
                                                                        index: index
                                                                    }"
                                                                    :headers="
                                                                        headers_token
                                                                    "
                                                                    :multiple="
                                                                        false
                                                                    "
                                                                    :on-remove="
                                                                        (
                                                                            file,
                                                                            fileList
                                                                        ) =>
                                                                            handleRemoveUploadVoucher(
                                                                                file,
                                                                                fileList,
                                                                                index
                                                                            )
                                                                    "
                                                                    :action="
                                                                        `/finances/payment-file/upload`
                                                                    "
                                                                    :show-file-list="
                                                                        true
                                                                    "
                                                                    :file-list="
                                                                        row.file_list
                                                                    "
                                                                    :on-success="
                                                                        (
                                                                            response,
                                                                            file,
                                                                            fileList
                                                                        ) =>
                                                                            onSuccessUploadVoucher(
                                                                                response,
                                                                                file,
                                                                                fileList,
                                                                                index
                                                                            )
                                                                    "
                                                                    :on-error="
                                                                        (error, file, fileList) =>
                                                                            onUploadErrorVoucher(
                                                                                error,
                                                                                file,
                                                                                fileList,
                                                                                index
                                                                            )
                                                                    "
                                                                    :limit="
                                                                        1
                                                                    "
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        class="btn btn-sm btn-primary"
                                                                        slot="trigger"
                                                                    >
                                                                        <i
                                                                            class="fas fa-fw fa-upload"
                                                                        ></i>
                                                                    </button>
                                                                </el-upload>
                                                                <!-- </el-tooltip> -->
                                                            </td>
                                                        </template>

                                                        <!-- ######## INICIO ANCHO DEL METODO DE PAGO ######## -->
                                                        <td class="payment-method-column">
                                                            <!-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## -->
                                                            <span v-if="isRecordedReceipt(row)">{{ (row.payment_method_snapshot || {}).description || (row.payment_method_type || {}).description || row.payment_method_type_id }}</span>
                                                            <el-select
                                                                v-else
                                                                v-model="
                                                                    row.payment_method_type_id
                                                                "
                                                                @change="
                                                                    changePaymentMethodType(
                                                                        index
                                                                    )
                                                                "
                                                            >
                                                                <el-option
                                                                    v-for="option in cash_payment_metod"
                                                                    :key="
                                                                        option.id
                                                                    "
                                                                    :label="
                                                                        option.description
                                                                    "
                                                                    :value="
                                                                        option.id
                                                                    "
                                                                ></el-option>
                                                            </el-select>
                                                        <template v-if="isRecordedReceipt(row)">
                                                            <small class="d-block">Cobro registrado: {{ row.original_amount }} {{ row.currency_type_id }}; aplicado: {{ row.payment }} {{ form.currency_type_id }}.</small>
                                                            <small v-if="row.currency_type_id !== form.currency_type_id" class="d-block">Tasa VES por USD: {{ row.exchange_rate }}</small>
                                                            <!-- El cobro registrado conserva su contexto original. -->
                                                        </template>
                                                            <!-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## -->
                                                        <small v-if="!row.payment_method_type_id" class="text-muted">{{ paymentMethodsLoaded ? "No hay efectivo activo para la moneda del documento; seleccione un método." : "Cargando métodos de pago…" }}</small>
<igtf-settings-status v-if="fiscalPaymentsApplicable && !useLocalCalculations" />
<igtf-payment-fields :local-calculation="useLocalCalculations" :local-context="localCalculationContext" :hide-classification="true" :total-tendered="true" :igtf-rate="igtfSettings.rate" :use-document-rate="true" :payment-methods="payment_method_types" v-if="fiscalPaymentsApplicable && !isRecordedReceipt(row)" :igtf-enabled="igtfEnabled" :settings-version="igtfSettings.revision"
    :payment="row" :document-currency="form.currency_type_id" :document-rate="form.exchange_rate_sale" :customer-id="form.customer_id" />
                                                        </td>
                                                        <!-- ######## FIN ANCHO DEL METODO DE PAGO ######## -->
                                                        <template
                                                            v-if="
                                                                enabled_payments
                                                            "
                                                        >
                                                            <td>
                                                                <span v-if="isRecordedReceipt(row)">{{ row.payment_destination_description || 'Destino registrado' }}</span>
                                                                <el-select
                                                                    v-else
                                                                    v-model="
                                                                        row.payment_destination_id
                                                                    "
                                                                    filterable
                                                                >
                                                                    <el-option
                                                                        v-for="option in classifiedPaymentDestinations(payment_destinations, row)"
                                                                        :key="
                                                                            option.id
                                                                        "
                                                                        :label="
                                                                            option.description
                                                                        "
                                                                        :value="
                                                                            option.id
                                                                        "
                                                                    ></el-option>
                                                                </el-select>
                                                            </td>
                                                            <td>
                                                                <el-input
                                                                    :disabled="isRecordedReceipt(row)"
                                                                    v-model="
                                                                        row.reference
                                                                    "
                                                                ></el-input>
                                                            </td>
                                                            <td>
                                                                <el-popover placement="top" trigger="hover" :disabled="!fiscalPaymentsApplicable || isRecordedReceipt(row)">
                                                                    <div class="text-start" data-testid="fiscal-receipt-popover">
                                                                        <small class="d-block">{{ row.currency_type_id || form.currency_type_id }}</small>
                                                                        <template v-for="detail in [fiscalReceiptBreakdown(row)]">
                                                                        <div v-if="detail" :key="'receipt-' + index" data-testid="fiscal-receipt-breakdown">
                                                                        <small class="d-block">Principal recibido: {{ detail.principal }} {{ detail.currency }}</small>
                                                                        <small class="d-block">IGTF: {{ detail.tax }} {{ detail.currency }}</small>
                                                                        <small class="d-block"><strong>Total cobrado: {{ detail.total }} {{ detail.currency }} ⇄ {{ fiscalEquivalent(detail.total, detail) }}</strong></small>
                                                                        </div>
                                                                        </template>
                                                                    </div>
                                                                    <el-input slot="reference"
                                                                        :disabled="isRecordedReceipt(row)"
                                                                        :value="fiscalPaymentsApplicable && !isRecordedReceipt(row) ? row.tendered_amount : fiscalAppliedPayment(row)"
                                                                        @input="fiscalPaymentsApplicable ? updateTenderedPayment(row, $event) : updatePaymentPrincipal(row, $event)"
                                                                    ></el-input>
                                                                </el-popover>
                                                            </td>
                                                            <td
                                                                class="text-center"
                                                            >
                                                                <button
                                                                    v-if="!isRecordedReceipt(row)"
                                                                    class="btn waves-effect waves-light btn-xs btn-danger"
                                                                    type="button"
                                                                    @click.prevent="
                                                                        clickCancel(
                                                                            index
                                                                        )
                                                                    "
                                                                >
                                                                    <i
                                                                        class="fa fa-trash"
                                                                    ></i>
                                                                </button>
                                                            </td>
                                                        </template>
                                                    </tr>
                                                    <tr>
                                                        <!-- ######## INICIO RESUMEN PAGADO Y FALTA ######## -->
                                                        <td :colspan="showLoadVoucher ? 6 : 5">
                                                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                                <label class="control-label">
                                                                    <a href="#" @click.prevent="clickAddPayment">
                                                                        <i class="fa fa-plus font-weight-bold text-info"></i>
                                                                        <span style="color: #777777">Agregar pago</span>
                                                                    </a>
                                                                </label>
                                                                <document-payment-progress
                                                                    :document="form"
                                                                    :quote="displayFiscalQuote"
                                                                    :pending="igtfQuoteLoading || igtfSettings.loading || (!!fiscalQuoteKey && igtfQuoteKey !== fiscalQuoteKey)"
                                                                    :error="igtfQuoteError || igtfSettings.error"
                                                                    :currency-types="currency_types"
                                                                />
                                                            </div>
                                                        </td>
                                                        <!-- ######## FIN RESUMEN PAGADO Y FALTA ######## -->
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                      </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <!-- ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA -->
                </div>
                <el-alert v-if="editBlocked" :title="form.edit_block_reason" type="warning" :closable="false" show-icon />
                <!-- @todo: Mejorar evitando duplicar codigo -->
                <!-- Ocultar en cel -->
                <div
                    class="card-footer card-footer-invoice card-footer-default text-end d-none d-md-flex justify-content-between px-3 py-2"
                >
                    <button
                        class="btn btn-default second-buton"
                        style="min-width: 180px"
                        @click.prevent="close()"
                    >
                        Cancelar
                    </button>
                    <div class="d-flex" style="gap: 8px;">
                        <button
                            class="btn btn-success"
                            style="min-width: 180px"
                            v-if="form.items.length > 0 && this.dateValid"
                            @click.prevent="openDialogPreview()"
                        >
                            Vista Previa
                        </button>
                        <el-popover
                            placement="top-start"
                            :open-delay="1000"
                            width="145"
                            trigger="hover"
                            content="Presiona ALT + G"
                        >
                            <el-button
                                slot="reference"
                                v-if="form.items.length > 0 && this.dateValid"
                                :loading="loading_submit"
                                :disabled="editBlocked"
                                class="submit btn btn-primary"
                                native-type="submit"
                                style="min-width: 180px"
                            >
                                {{ btnText }} <kbd>ALT</kbd>+<kbd>G</kbd>
                            </el-button>
                        </el-popover>
                    </div>
                </div>
                <!-- @todo: Mejorar evitando duplicar codigo -->
                <!-- Ocultar en cel -->

                <!-- @todo: Mejorar evitando duplicar codigo -->
                <!-- Mostrar en cel -->
                <div class="card-footer d-md-none">
                    <div class="row g-2 text-center px-3 pb-3">
                        <!-- Vista previa -->
                        <div class="col-6">
                            <button
                                class="btn btn-success w-100"
                                v-if="form.items.length > 0 && dateValid"
                                @click.prevent="openDialogPreview()"
                            >
                                Vista previa
                            </button>
                        </div>

                        <!-- Cancelar -->
                        <div class="col-6">
                            <button
                                class="btn btn-outline-secondary w-100"
                                @click.prevent="close()"
                            >
                                Cancelar
                            </button>
                        </div>

                        <!-- Enviar -->
                        <div class="col-12">
                            <el-button
                                v-if="form.items.length > 0 && dateValid"
                                :loading="loading_submit"
                                :disabled="editBlocked"
                                class="btn btn-primary w-100"
                                native-type="submit"
                            >
                                {{ btnText }}
                            </el-button>
                        </div>
                    </div>
                </div>
                <!-- Mostrar en cel -->
                <!-- @todo: Mejorar evitando duplicar codigo -->
            </div>
        </form>
        </div>
    </div>

    <document-report-customer
        :showDialog.sync="showDialogReportCustomer"
        :customerId="report_to_customer_id"
    ></document-report-customer>

    <document-form-item
        :configuration="config"
        :currency-type-id-active="form.currency_type_id"
        :documentId="documentId"
        :editNameProduct="config.edit_name_product"
        :exchange-rate-sale="form.exchange_rate_sale"
        :isEditItemNote="false"
        :operation-type-id="form.operation_type_id"
        :recordItem="recordItem"
        :showDialog.sync="showDialogAddItem"
        :typeUser="typeUser"
        :customer-id="form.customer_id"
        :currency-types="currency_types"
        :is-from-invoice="true"
        :percentage-igv="percentage_igv"
        :isUpdateDocument="isUpdateDocument"
        :permissionEditItemPrices="authUser.permission_edit_item_prices"
        :displayDiscount="config.show_item_discounts_charges_attributes"
        ref="form_add_item"
        :selectedOptionPrice.sync="selected_option_price"
        @add="addRow"
    ></document-form-item>

    <person-form
        :document_type_id="form.document_type_id"
        :external="true"
        :input_person="personFormInput"
        :recordId="editPerson ? form.customer_id : null"
        :showDialog.sync="showDialogNewPerson"
        type="customers"
    ></person-form>

    <person-form :external="true" selection_target="third_party" :input_person="thirdPartyRecordId ? null : thirdPartyCreateInput" :recordId="thirdPartyRecordId" :showDialog.sync="showDialogThirdParty" type="customers" @saved="thirdPartySaved" />

    <document-options
        :configuration="config"
        :isContingency="is_contingency"
        :isUpdate="isUpdate"
        :recordId="documentNewId"
        :table="table"
        :showClose="false"
        :failsInSend="failSendDocument"
        :failsMessage='failsMessage'
        :showDialog.sync="showDialogOptions"
    ></document-options>

    <!-- <document-hotel-form
        :hotel="form.hotel"
        :showDialog.sync="showDialogFormHotel"
        @addDocumentHotel="addDocumentHotel"
    ></document-hotel-form> -->

    <!-- <document-transport-form
        :showDialog.sync="showDialogFormTransport"
        :transport="form.transport"
        @addDocumentTransport="addDocumentTransport"
    ></document-transport-form> -->

    <!-- ########## INICIO SIN DETRACCIONES E ISC -->
    <!-- ######### FIN SIN DETRACCIONES E ISC -->

    <store-item-series-index
        :show-dialog.sync="showDialogItemSeriesIndex"
        :item="recordItem"
        :document-id="documentId"
        @success="successItemSeries"
    ></store-item-series-index>

    <lots-group
        :lots-group="lotModalLotsGroup"
        :quantity="lotModalQuantity"
        :exact-quantities="true"
        :showDialog.sync="showDialogLotsGroup"
        @addRowLotGroup="addRowLotGroupFromTable"
    >
    </lots-group>

    <document-form-preview
        :showDialog.sync="showDialogPreview"
        :preview="preview"
        :digital-invoice="company && company.fiscal_emission_mode === 'digital' && form.document_type_id === '01'"
    >
    </document-form-preview>

    <!-- <item-detail-form
        :recordId="itemDetailId"
        :showDialog.sync="showDialogItemDetail"
        :onlyShowAllDetails="configuration.show_all_item_details"
    >
    </item-detail-form> -->
    <consigned-form
        :personId="form.customer_id"
        :showDialog.sync="showDialogConsignedForm">
    </consigned-form>
</div>
</template>

<style scoped>
.invoice table.invoice-totals {
    width: 55%;
    margin-left: auto;
}
.invoice .invoice-totals tr > td:first-child:not([colspan]) {
    text-align: left;
}
@media (max-width: 767px) {
    .invoice table.invoice-totals { width: 100%; }
}
.invoice table.table tr.invoice-total-due > td {
    border-top: 1px solid #e5e5e5 !important;
    font-size: 20px;
    font-weight: 700;
}
.invoice-total-due > td:first-child { white-space: nowrap; }
.invoice-total-due small { font-size: 12px; font-weight: 400; }
.payment-section {
    border: 1px solid #dedede;
    border-radius: 10px;
    padding: 16px;
    text-align: left;
}
.payment-section__condition {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e5e5e5;
}
.input-with-currency {
    display: flex;
    align-items: center;
    justify-content: end;
    position: relative;
}
.input-with-currency div {
    width: 100%;
}
.module-title-marker {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    border: 0;
}
.input-custom {
    width: 50% !important;
}
.input-with-currency div .input-custom {
    width: 100% !important;
}
.el-textarea__inner {
    height: 65px !important;
    min-height: 65px !important;
}

.card-header + .card-body {
    border-radius: 0px;
}

.card-body {
    border-radius: 0px;
}

.el-collapse-item__content {
    padding-bottom: 0px;
}

.content-body {
    padding: 20px;
}

.el-upload-list__item {
    max-width: 100px;
}
.card-header-invoice {
    background-color: transparent !important;
}
.toggle-button {
    position: fixed;
    top: 35%;
    right: -105px;
    transform: translateY(-50%) rotate(-90deg);
    transform-origin: center;
    background-color: rgba(115, 183, 255, 0.6);
    color: white;
    padding: 5px 10px;
    cursor: pointer;
    border-radius: 5px;
    z-index: 2;
    transition: all 0.3s ease-in-out;
    font-weight: 400;
    font-size: 16px;
    line-height: 1;
    display: block;
    height: auto;
    border: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
}
.toggle-button-invoice.shift:hover {
    box-shadow: none;
}
.additional-information {
    position: fixed;
    top: 0;
    right: -100%;
    height: 100%;
    background-color: #f9f9f9;
    box-shadow: -2px 0 5px rgba(0, 0, 0, 0.1);
    transition: right 0.3s ease-in-out;
    overflow-y: auto;
    z-index: 1022;
}
.additional-information::-webkit-scrollbar {
    width: 8px;
}
.additional-information::-webkit-scrollbar-thumb {
    background-color: #d3dbf3;
    border-radius: 4px;
}
.additional-information::-webkit-scrollbar-thumb:hover {
    background-color: #cacfe1;
}
.additional-information::-webkit-scrollbar-track {
    background-color: transparent;
}
.additional-information.show {
    right: 0;
}
.content-opacity {
    position: relative;
}
.content-opacity::after {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1021;
}
.table-responsive.payment th {
    border-bottom: none !important;
}
.invoice table.table {
    table-layout: auto !important;
}
/* ######## INICIO ANCHO DEL METODO DE PAGO ######## */
.invoice table.payment-method {
    width: 100%;
    table-layout: fixed !important;
}
.payment-method .payment-method-column {
    width: 180px;
    min-width: 160px;
    max-width: 180px;
    white-space: normal;
    overflow-wrap: anywhere;
}
.payment-method .payment-method-column > .el-select {
    max-width: 180px;
}
/* ######## FIN ANCHO DEL METODO DE PAGO ######## */
.input-custom.el-input-number{
    padding: 0px;
}
.customer-select-clearable >>> .el-input__suffix {
    right: 33px;
}
.edit-layout-btn {
    opacity: 0;
    pointer-events: none;
}
.title-document:hover .edit-layout-btn {
    opacity: 1;
    pointer-events: all;
}
@media only screen and (min-width: 992px) {
    .table-responsive {
        overflow-x: visible !important;
    }
}

@media only screen and (max-width: 991px) {
    .form-client-default {
        width: 100% !important;
    }
}
@media only screen and (max-width: 767px) {
    .input-price-default {
        margin-bottom: 20px;
    }
    .is-hidden-mobile {
        display: none;
    }
    datetime-container {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
    }
    .datetime-container > div {
        width: 50%;
    }
    .inputs-container .invoice-type {
        width: 70%;
    }
    .inputs-container .serie-input {
        width: 30%;
    }
    .money-input {
        width: 25%;
    }
    .operation-type {
        width: 40%;
    }
    .change-type {
        width: 35%;
    }
}
@media only screen and (max-width: 550px) {
    .additional-information {
        width: 95%;
    }
    .toggle-button.shift {
        right: 64%;
    }
}
</style>
    <!-- ######## FIN CAMBIO GEOPOLITICO VENEZUELA -->
<script>
import { confirmInvoiceSourcePrice } from '@helpers/confirm-invoice-source-price';
import {selectPrepayment, updatePrepaymentInput, applyPrepaymentDiscount} from '../../../helpers/exact-prepayments'
import invoicePrepayments from '../../../mixins/invoice-prepayments'

import {defaultCashForCurrency, proposeCashDestination, restoreDeferredInvoiceCash, isRecordedReceipt} from '@helpers/payment-classification'
import fiscalPaymentSettings from "@mixins/fiscal-payment-settings"
import {validateInvoicePayments} from "@helpers/invoice-payment-validation"
import {restoreSaleNoteContext, documentRateRequest, mayApplyDocumentRate} from "../../../helpers/sale-note-conversion"

import {withoutDraftRetentions, suggestRemainingPayment, markPaymentEdited, suggestInstallments, paymentConditionPayload, invoicePaymentsForCondition} from "@helpers/document-creation"
import {recalculateInvoiceRow} from "@helpers/invoice-row-edit"
import {captureInvoiceEdit} from "@helpers/invoice-edit-state"
import documentCreationMixin from "@mixins/document-creation"
// ######## INICIO TASAS OCHO DECIMALES ########
import { exactAmount, normalizeExchangeRate, exactValue, exactSum } from "../../../helpers/exchange-rate-math"
import { documentTotals } from "../../../helpers/exact-document"
// ######## FIN TASAS OCHO DECIMALES ########

import IgtfPaymentFields from "./partials/igtf_fields.vue"
// ######## INICIO RESUMEN REACTIVO IVA E IGTF ########
import DocumentTaxSummary from "./partials/tax_summary.vue"
import ExactDecimalInput from "./partials/exact_decimal_input.vue"
// ######## FIN RESUMEN REACTIVO IVA E IGTF ########
// ######## INICIO RESUMEN PAGADO Y FALTA ########
import DocumentPaymentProgress from "./partials/payment_progress.vue"
import ChangeDisbursementSelector from "./partials/change_disbursement.vue"
import {defaultChangeDisbursements, validateChangeDisbursements} from "../../../helpers/change-disbursements"
// ######## FIN RESUMEN PAGADO Y FALTA ########

// ######## INICIO SCRIPT GEOPOLITICO VENEZUELA
import DocumentFormItem from "./partials/item.vue";
import PersonForm from "../persons/form.vue";
import DocumentOptions from "../documents/partials/options.vue";
import {
    exchangeRate,
    functions,
    pointSystemFunctions,
    fnRestrictSaleItemsCpe,
    fnItemSearchQuickSale
} from "../../../mixins/functions";
import {
    calculateRowItem,
    canMergeRowQuantity,
    canMergeRowPrice,
    showNamePdfOfDescription
} from "../../../helpers/functions";
import Logo from "../companies/logo.vue";
import DocumentHotelForm from "../../../../../modules/BusinessTurn/Resources/assets/js/views/hotels/form.vue";
import DocumentTransportForm from "../../../../../modules/BusinessTurn/Resources/assets/js/views/transports/form.vue";
// ########## INICIO SIN DETRACCIONES E ISC
// ######### FIN SIN DETRACCIONES E ISC
import moment from "moment";
import { mapActions, mapState } from "vuex/dist/vuex.mjs";
import Keypress from "vue-keypress";
import StoreItemSeriesIndex from "../Store/ItemSeriesIndex.vue";
import DocumentReportCustomer from "./partials/report_customer.vue";
import SetTip from "@components/SetTip.vue";

import LotsForm from "./partials/lots.vue";
import LotsGroup from "./partials/lots_group.vue";
import {
    itemRequiresLot,
    rowNeedsLotAssignment,
    validateItemsLots,
    hydrateItemLots,
    resolveIdLoteSelected,
    normalizeInvoiceLots, lotQuantity, invoiceSeriesValid, sameInvoiceRecordId,
} from "../../../helpers/invoice-lots";
import { editableRowItems } from "@mixins/editable-row-items";
import { buhoprinter } from "@mixins/buhoprinter";
import ItemSearchQuickSale from "@components/items/ItemSearchQuickSale.vue";
import PackItemDescription from "@components/items/PackItemDescription.vue";
// import ItemDetailForm from '@views/items/form.vue'
import DocumentFormPreview from "./partials/preview.vue";
import ConsignedForm from './partials/consigned.vue';
import CustomFieldsRenderer from '@viewsModuleCustomField/custom_fields/custom_field_renderer.vue'
import DocumentFormPinnedBar from './_document_pinned_bar.vue';
import MiniTour from "@components/MiniTour.vue";
import {
    getDefaultLayout as getDocumentDefaultLayout,
    getAvailableFields as getDocumentAvailableFields,
} from './_document_form_fields_catalog';

const RemoteSlot = {
    name: 'RemoteSlot',
    functional: true,
    props: {
        source: { type: Object, default: null },
        slotName: { type: String, required: true },
        slotScopeData: { type: Object, default: () => ({}) },
    },
    render(h, ctx) {
        const src = ctx.props.source;
        const slot = src && src.$scopedSlots ? src.$scopedSlots[ctx.props.slotName] : null;
        const content = typeof slot === 'function' ? slot(ctx.props.slotScopeData) : slot;
        return h('div', { class: 'relocated-field-slot' }, content || []);
    },
};

export default {
    localCalculations: true,
    props: [
        "idUser",
        "initialContingency",
        "typeUser",
        "configuration",
        "documentId",
        "table",
        "tableId",
        "isUpdate",
        "authUser"
    ],
    components: { IgtfPaymentFields, ExactDecimalInput,
        // ######## INICIO RESUMEN REACTIVO IVA E IGTF ########
        DocumentTaxSummary,
        // ######## FIN RESUMEN REACTIVO IVA E IGTF ########
        // ######## INICIO RESUMEN PAGADO Y FALTA ########
        DocumentPaymentProgress,
        ChangeDisbursementSelector,
        // ######## FIN RESUMEN PAGADO Y FALTA ########
        StoreItemSeriesIndex,
        DocumentFormItem,
        PersonForm,
        DocumentOptions,
        Logo,
        DocumentHotelForm,
        Keypress,
        // ########## INICIO SIN DETRACCIONES E ISC
        // ######### FIN SIN DETRACCIONES E ISC
        DocumentTransportForm,
        DocumentReportCustomer,
        SetTip,
        LotsForm,
        LotsGroup,
        ItemSearchQuickSale,
        // ItemDetailForm,
        PackItemDescription,
        DocumentFormPreview,
        ConsignedForm,
        CustomFieldsRenderer,
        DocumentFormPinnedBar,
        RemoteSlot,
        MiniTour,
    },
    mixins: [fiscalPaymentSettings, documentCreationMixin, invoicePrepayments,
        functions,
        exchangeRate,
        pointSystemFunctions,
        fnRestrictSaleItemsCpe,
        editableRowItems,
        fnItemSearchQuickSale,
        buhoprinter,
    ],
    data() {
        return {
            // ######## INICIO ESTABILIDAD DE EVALUACION FISCAL ########
            lastDisplayedFiscalQuote: null,
            // ######## FIN ESTABILIDAD DE EVALUACION FISCAL ########
            miniTourSteps: [
                {
                    target: ".edit-layout-btn",
                    tag: "Paso 1 de 2",
                    title: "Personaliza tus campos",
                    body: "Con este botón puedes <b>reordenar los campos</b> del formulario y <b>ajustar el ancho</b> de cada uno. Aparece al <b>pasar el cursor sobre el título</b>; configúralo una vez y el formulario quedará a tu medida.",
                    placement: "bottom"
                },
                {
                    target: "[data-tour='info-adicional']",
                    tag: "Paso 2 de 2",
                    badge: "Solo la primera vez",
                    title: "Información adicional",
                    body: "Aquí agregas datos extra al comprobante: <b>observaciones, orden de compra, órdenes de entrega</b> y más.",
                    placement: "bottom"
                }
            ],
            datEmision: {
                disabledDate(time) {
                    return time.getTime() > moment();
                }
            },
            multiple: [
                {
                    keyCode: 78, // N
                    modifiers: ["altKey"],
                    preventDefault: true
                },
                {
                    keyCode: 71, // g
                    modifiers: ["altKey"],
                    preventDefault: true
                }
            ],
            // default_document_type: null,
            // default_series_type: null,
            // Datos generales personalizables (ver módulo DocumentFormLayout)
            pinned_fields: [],
            editingLayout: false,
            layout_saving: false,
            // Instancia de la barra de datos generales, para reubicar sus slots no fijados.
            pinnedBarInstance: null,
            pinnedBarReady: false,
            isVisible: false,
            is_contingency: Boolean(this.initialContingency),
            focus_on_client: false,
            dateValid: false,
            input_person: {},
            showDialogFormHotel: false,
            showDialogFormTransport: false,
            showDialogItemSeriesIndex: false,
            showDialogLotsGroup: false,
            lotModalItemIndex: -1,
            lotModalLotsGroup: [],
            lotModalQuantity: 0,
            is_client: false,
            recordItem: null,
            resource: "documents",
            showDialogAddItem: false,
            showDialogNewPerson: false,
            showDialogThirdParty: false,
            thirdPartyRecordId: null,
            thirdPartySearchTerm: '',
            thirdPartyCreateInput: '',
            specialTaxRegimeMode: false,
            specialTaxRegimes: [],
            savedSpecialTaxRegime: null,
            thirdPartyMode: false,
            originalThirdPartyInvoice: false,
            thirdParties: [],
            thirdPartySavedOption: null,
            thirdPartySearchSequence: 0,
            loadingThirdParties: false,
            editPerson: false,
            showDialogOptions: false,
            failSendDocument: false,
            failsMessage: '',
            loading_submit: false,
            loading_form: false,
            errors: {},
            form: {},
            document_types: [],
            currency_types: [],
            discount_types: [],
            charges_types: [],
            all_customers: [],
            business_turns: [],
            form_payment: {},
            document_types_guide: [],
            customers: [],
            preloadedCustomerId: null,
            preloadedCustomer: null,
            sellers: [],
            company: null,
            document_type_03_filter: null,
            operation_types: [],
            establishments: [],
            payment_method_types: [],
            paymentMethodsLoaded: false,
            pendingDefaultPaymentRows: [],
            establishment: null,
            // all_series: [],
            // series: [],
            prepayment_documents: [],
            currency_type: {},
            documentNewId: null,
            customerCurrent: null,
            printTicketUrl: null,
            prepayment_deduction: false,
            activePanel: 0,
            total_global_discount: 0,
            invoiceGlobalDiscountSource: null,
            invoiceGlobalChargeSource: null,
            invoiceDiscountRestoreError: null,
            total_global_charge: 0,
            invoiceAllowancePercent: null,
            invoiceDraftGeneration: 0,
            invoiceCalculationError: null,
            // Subtotal UI antes del dto global tipo 02 (form.subtotal sigue = total para XML TaxInclusiveAmount)
            subtotal_before_global_discount: 0,
            loading_search: false,
            is_amount: true,
            enabled_discount_global: false,
            user: null,
            is_receivable: false,
            select_first_document_type_03: false,
            customer_addresses: [],
            payment_destinations: [],
            form_cash_document: {},
            enabled_payments: true,
            existingPayments: [],
            existingRetentions: [],
            existingGuaranteeFund: null,
            readonly_date_of_due: false,
            seller_class: "col-lg-6 pb-2",
            btnText: "Generar",
            payment_conditions: [],
            affectation_igv_types: [],
            total_discount_no_base: 0,

            global_discount_types: [],
            global_discount_type: {},
            error_global_discount: false,
            headers_token: headers_token,
            showDialogReportCustomer: false,
            report_to_customer_id: null,

            // porque el usuario la desmarcara. Solo esa se devuelve despues.
            // itemDetailId: null,
            // showDialogItemDetail: false,
            showDialogConsignedForm: false,
            price_options: [],
            selected_option_price: null,
            showDialogPreview: false,
            value_taxed_without_rounded: 0,
            total_without_rounded: 0,
            recordDiscountsGlobal: null,

            customer_expired_days: 0,
            customer_has_expired: false,
            option_address_itinerant: [
                {
                    id: 1,
                    description: "Dirección anexo al cliente"
                },
                {
                    id: 2,
                    description: "Establecimiento de un tercero inscrito en el RIF"
                },
            ],
            itinerant_option_id: 1,
            ruc_itinerant: null,
            is_restaurant_active: false,
            restaurant_tip_factor: 0,
            is_consumption_charge   : false,
            total_consumption_charge : 0,
            consigneds:[],
            consigned_addresses:[],
            customerSearchTerm: '',
            is_itinerant_operation: false
        };
    },
    computed: {
        // ######## INICIO MONEDA DOCUMENTAL DE VENTAS ########
        documentCurrencies() {
            return this.currency_types.filter(currency => !window.salesDocumentsVes || currency.id === 'VES');
        },
        // ######## FIN MONEDA DOCUMENTAL DE VENTAS ########
        hasNamedSeries() {
            return this.series.some(option => (option.number || '').trim() !== '');
        },
        // ######## INICIO TOTAL UNICO DE VUELTO ########
        fiscalChangeReceipts() {
            return (this.displayFiscalQuote?.payments || []).map((payment, index) => ({payment, index}))
                .filter(({payment}) => !payment.reused && this.hasFiscalChange(payment));
        },
        fiscalChangeTotal() {
            const currency = this.form.currency_type_id;
            if (!['VES', 'USD'].includes(currency)) return null;
            try {
                let total = exactAmount('0');
                for (const {payment} of this.fiscalChangeReceipts) {
                    let amount = exactAmount(payment.change);
                    if (!['VES', 'USD'].includes(payment.currency_type_id)) return null;
                    if (payment.currency_type_id !== currency) {
                        const rate = normalizeExchangeRate(payment.exchange_rate);
                        amount = currency === 'VES' ? amount.times(rate) : amount.dividedBy(rate);
                    }
                    total = total.plus(amount);
                }
                return total.final(2);
            } catch (_) { return null; }
        },
        // ######## FIN TOTAL UNICO DE VUELTO ########
        // ######## INICIO SIMBOLO DE MONEDA DOCUMENTAL ########
        documentCurrencySymbol() {
            const id = this.form?.currency_type_id;
            return this.currency_types.find(currency => currency.id === id)?.symbol || (id === 'VES' ? 'Bs.' : id === 'USD' ? '$' : '');
        },
        // ######## FIN SIMBOLO DE MONEDA DOCUMENTAL ########
        activeInvoicePayments() { return invoicePaymentsForCondition(this.form || {}); },
        fiscalQuoteRows() { return this.activeInvoicePayments; },
        // ######## INICIO TOTAL A PAGAR CON IGTF ########
        invoiceTotalWithIgtf() {
            if (this.igtfQuoteError || (this.fiscalQuoteKey && !this.displayFiscalQuote)) return null;
            if (this.displayFiscalQuote) return this.displayFiscalQuote.total_with_tax;
            return exactAmount(this.form.total_payable ?? this.form.total ?? '0').final(2);
        },
        // ######## FIN TOTAL A PAGAR CON IGTF ########
        // ######## INICIO ESTABILIDAD DE EVALUACION FISCAL ########
        displayFiscalQuote() {
            if (this.igtfQuoteError || !this.fiscalQuoteKey) return null;
            return this.igtfQuoteKey === this.fiscalQuoteKey && this.igtfQuote ? this.igtfQuote : this.lastDisplayedFiscalQuote;
        },
        // ######## FIN ESTABILIDAD DE EVALUACION FISCAL ########
        receiptAppliedAmounts() {
            return JSON.stringify(this.activeInvoicePayments.map(row => row.payment));
        },
        // La personalización de campos se opera pasando el cursor sobre el
        // título, gesto que no existe en celular: allí el botón se oculta
        // (mobile.css) y su paso del tutorial se omite para no señalar un
        // elemento invisible
        miniTourStepsVisible() {
            if (!window.matchMedia("(max-width: 767.98px)").matches) {
                return this.miniTourSteps;
            }
            const pasos = this.miniTourSteps.filter(
                step => step.target !== ".edit-layout-btn"
            );
            // Se renumeran: al quitar un paso, el rótulo "Paso 2 de 2"
            // quedaría en el único paso que se muestra
            return pasos.map((step, i) => ({
                ...step,
                tag: `Paso ${i + 1} de ${pasos.length}`
            }));
        },
        layoutPinnedKeysSet() {
            if (
                this.editingLayout &&
                this.pinnedBarInstance &&
                Array.isArray(this.pinnedBarInstance.draftPins)
            ) {
                return new Set(this.pinnedBarInstance.draftPins.map(p => p.field_key));
            }
            return new Set((this.pinned_fields || []).map(p => p.field_key));
        },
        relocatedFields() {
            if (!this.pinnedBarReady) return [];
            const hidden = new Set(this.hiddenLayoutFields);
            return getDocumentAvailableFields('invoice')
                .filter(f => f.group === 'main')
                .filter(f => !this.layoutPinnedKeysSet.has(f.key) && !hidden.has(f.key))
                .map(f => ({ key: f.key, field: f }));
        },
        hiddenLayoutFields() {
            const hidden = [];
            if (!this.showCurrencyExchangeFields) {
                hidden.push('currency_type_id', 'exchange_rate_sale');
            }
            if (!this.showItinerantPointField) {
                hidden.push('itinerant_option_id');
            }
            if (!this.showItinerantRucField) {
                hidden.push('ruc_itinerant');
            }
            if (!(this.configuration && this.configuration.enable_consigned && this.consigneds && this.consigneds.length)) {
                hidden.push('consigned_id', 'consigned_address_id');
            }
            if (!this.showPlateNumberField) {
                hidden.push('plate_number');
            }
            return hidden;
        },
        operation_type_id_view: {
            get() {
                if (
                    this.form.operation_type_id === "0101" &&
                    this.is_itinerant_operation
                ) {
                    return "0101_itinerant";
                }
                return this.form.operation_type_id;
            },
            set(val) {
                if ((this.thirdPartyMode || this.form.fiscal_data?.special_tax_regime_id) && val !== '0101') {
                    this.$message.error('La factura por terceros o con régimen especial requiere venta interna.');
                    return;
                }
                const nextExport = ['0201', '0202', '0203'].includes(val);
                const changedCategory = nextExport !== this.isExportInvoice;
                if (changedCategory && (this.isUpdateDocument || this.form.items.length > 0)) {
                    this.$message.error('Cambie entre venta interna y exportación antes de agregar productos. Las facturas registradas conservan su categoría.');
                    return;
                }
                if (changedCategory) {
                    this.form.customer_id = null;
                    this.form.customer_address_id = null;
                    this.preloadedCustomerId = null;
                    this.preloadedCustomer = null;
                }
                if (val === "0101_itinerant") {
                    this.is_itinerant_operation = true;
                    this.form.operation_type_id = "0101";
                    this.form.is_itinerant = true;
                } else {
                    if (this.is_itinerant_operation) {
                        this.form.is_itinerant = false;
                    }
                    this.is_itinerant_operation = false;
                    this.form.operation_type_id = val;
                }
            }
        },
        operation_types_filter() {
            return this.operation_types;
        },
        showOperationTypeField() {
            // Mantener visible el tipo seleccionado, incluso cuando el tenant
            // solo tenga una operación disponible.
            return this.operation_types_filter.length > 0;
        },
        isExportInvoice() {
            return ['0201', '0202', '0203'].includes(this.form.operation_type_id);
        },
        specialTaxRegimeAvailable() {
            return this.company?.fiscal_emission_mode === 'digital' && this.company?.fiscal_environment === 'demo'
                && this.form.document_type_id === '01' && this.form.operation_type_id === '0101' && !this.form.is_itinerant;
        },
        thirdPartyAvailable() {
            return this.company?.fiscal_emission_mode === 'digital' && this.company?.fiscal_environment === 'demo'
                && this.form.document_type_id === '01' && this.form.operation_type_id === '0101' && !this.form.is_itinerant;
        },
        hasPositiveInvoiceTotal() {
            return exactValue(this.form, 'total').compare(0) > 0;
        },
        invoiceSettlementAdjustmentLabel() {
            const amount = exactValue(this.form, 'settlement_adjustment');
            if (amount.compare(0) === 0) return null;
            const decimal = amount.decimal();
            if (decimal !== '0') return decimal;
            const {n, d} = amount.fraction();
            return `${n}/${d}`;
        },
        hasPositiveInvoicePrepayment() {
            return exactValue(this.form, 'total_prepayment').compare(0) > 0;
        },
        showCurrencyExchangeFields() {
            return this.currency_types.length > 1;
        },
        showItinerantPointField() {
            return this.form.operation_type_id === "0101" && this.form.is_itinerant;
        },
        showItinerantRucField() {
            return this.showItinerantPointField && this.itinerant_option_id == 2;
        },
        currencyColumnClass() {
            if (this.showItinerantRucField) {
                return "col-lg-3";
            }

            if (this.showItinerantPointField) {
                return "col-lg-4";
            }

            return "col-lg-6";
        },
        exchangeRateColumnClass() {
            if (this.showItinerantRucField) {
                return "col-lg-3";
            }

            if (this.showItinerantPointField) {
                return "col-lg-4";
            }

            return "col-lg-6";
        },
        itinerantPointColumnClass() {
            if (!this.showCurrencyExchangeFields) {
                return this.showItinerantRucField ? "col-lg-8" : "col-lg-12";
            }

            return "col-lg-4";
        },
        itinerantRucColumnClass() {
            if (!this.showCurrencyExchangeFields) {
                return "col-lg-4";
            }

            return "col-lg-2";
        },
        showPlateNumberField() {
            return Array.isArray(this.business_turns) &&
                this.business_turns.some(bt => bt.value === 'tap' && bt.active);
        },
        getCurrentLogo() {
            const isDarkMode = document.documentElement.classList.contains('dark');

            if (isDarkMode && this.company.logo_dark) {
                return `/storage/uploads/logos/${this.company.logo_dark}`;
            }
            if (this.company.logo) {
                return `/storage/uploads/logos/${this.company.logo}`;
            }
            return '';
        },
        isGeneratedFromExternal() {
            return (
                this.table != undefined &&
                this.table &&
                (this.tableId != undefined && this.tableId)
            );
        },
        showLoadVoucher() {
            return (
                this.configuration.show_load_voucher && !this.isUpdateDocument
            );
        },
        isGlobalDiscountBase: function() {
            if (this.recordDiscountsGlobal) {
               return  this.recordDiscountsGlobal.discount_type_id === "02";
            }
            return this.configuration.global_discount_type_id === "02" ;
        },
        ...mapState(["config", "series", "all_series"]),
        documentTypesAvailable() {
            // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
            return this.document_types.filter(dt => dt.id === "01");
            // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        },
        credit_payment_metod: function() {
            return this.payment_method_types.filter(method => method.collection_kind === 'credit' && [true, 1, '1'].includes(method.is_active));
        },
        cash_payment_metod: function() {
            return this.payment_method_types.filter(method => ['cash', 'account'].includes(method.collection_kind) && [true, 1, '1'].includes(method.is_active));
        },
        existDiscountsNoBase: function() {
            return this.total_discount_no_base > 0 ? true : false;
        },
        editBlocked() {
            return this.isUpdateDocument && this.form.can_edit === false;
        },
        isUpdateDocument: function() {
            return this.documentId ? true : false;
        },
        isCreditPaymentCondition: function() {
            return ["02", "03"].includes(this.form.payment_condition_id);
        },
        isAutoPrint: function() {
            if (this.configuration) {
                return this.configuration.auto_print;
            }

            return false;
        },
        hidePreviewPdf: function() {
            if (this.configuration) {
                return this.configuration.hide_pdf_view_documents;
            }

            return false;
        },
        isFromExternalDocument() {
            return this.table !== undefined && this.table !== null;
        },
        canAddDescriptionToDocumentItem() {
            if (this.configuration)
                return this.configuration.add_description_to_document_item;

            return false;
        },
        filteredSellers() {

            if (!this.isUpdateDocument) {
                return this.sellers.filter(seller => !seller.name.includes('(SUSPENDIDO)'));
            }
            return this.sellers;
        },
        personFormInput() {
            const term = (this.customerSearchTerm || '').trim()

            if (!term) return ''

            if (/^\d+$/.test(term)) {
                let identity_document_type_id = null
                if (term.length === 8) identity_document_type_id = '1'
                if (term.length === 11) identity_document_type_id = '6'

                return {
                    number: term,
                    ...(identity_document_type_id ? { identity_document_type_id } : {})
                }
            }

            return term
        },
        amountRetentionValidate() {
            let amount = 700;
            if (this.form.currency_type_id === "USD") {
                amount = exactAmount(700).dividedBy(exactAmount(this.form.exchange_rate_sale)).final(2);
            }
            return this.form.total > amount;
        },
        getCustomer(){
            const customer = this.customers.find(
                c => String(c.id) === String(this.form.customer_id)
            );
            return customer || {};
        },
        selectedCustomerAddressLabel() {
            const customer = this.getCustomer;
            if (!customer || !customer.address) {
                return '';
            }

            return customer.address;
        },
        totalDiscountExact() {
            const lineDiscount = exactSum(this.form.items.map(item => this.itemDiscountExactAmount(item)));
            const global = exactSum((this.form.discounts || []).map(discount => {
                const amount = exactAmount(discount.amount_exact ?? discount.amount_without_rounded ?? discount.amount ?? '0');
                return ['02','04'].includes(discount.discount_type_id) ? amount.times(exactAmount(1).plus(this.percentage_igv)) : amount;
            }));
            return lineDiscount.plus(global);
        },
        totalDiscount() {
            return this.totalDiscountExact.decimal();
        },
        hasPositiveInvoiceDiscount() {
            return this.totalDiscountExact.compare(0) > 0;
        },
        displaySubtotalBeforeDiscount() {
            // Tipo 02: mostrar total con IGV ANTES del dto (UI). form.subtotal = total post-dto (XML).
            if (
                this.isGlobalDiscountBase &&
                this.subtotal_before_global_discount > 0
            ) {
                return this.subtotal_before_global_discount;
            }
            return this.form.subtotal;
        }
    },
    mounted() {
        this.capturePinnedBar();
    },
    updated() {
        this.capturePinnedBar();
    },
    async created() {
        await this.initComponent();
        await this.getPercentageIgv();

        this.loading_form = true;
        this.$eventHub.$on("reloadDataPersons", customer_id => {
            this.reloadDataCustomers(customer_id);
            this.customerSearchTerm = ''
        });
        this.$eventHub.$on("initInputPerson", () => {
            this.initInputPerson();
        });
        this.$eventHub.$on("reloadDataConsigned", () => {
            this.getConsigneds();
        });
        this.$eventHub.$on("establishmentChanged", () => {
            this.initComponent();
        });
        if (this.documentId) {
            this.btnText = "Actualizar";
            this.loading_submit = true;
            await this.$http
                .get(`/documents/${this.documentId}/show`)
                .then(response => {
                    return this.onSetFormData(response.data.data);
                })
                .finally(() => (this.loading_submit = false));
        }

        /*
         * #830
         */
        if (this.table) {
            await this.$http
                .get(`/store/record/${this.table}/${this.tableId}`)
                .then(response => {
                    return this.onSetFormData(response.data.data);
                })
                .finally(() => (this.loading_submit = false));
        }
        /*
         * #830
         */

        const itemsFromDispatches = localStorage.getItem("items");
        if (itemsFromDispatches) {
            const itemsParsed = JSON.parse(itemsFromDispatches);
            const items = itemsParsed.map(i => i.item_id);
            const params = {
                items_id: items
            };
            localStorage.removeItem("items");
            await this.$http
                .get("/documents/search-items", { params })
                .then(response => {
                    this.form.items = response.data.items.map(item => this.calculateDispatchInvoiceRow(item, itemsParsed));
                });
        }

        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        try {
            restoreSaleNoteContext(this, localStorage);
        } catch (error) {
            this.$message.error(error.message || 'No se pudo recuperar la selección de notas.');
            return;
        }
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        const itemsFromNotes = localStorage.getItem("itemsForNotes");
        if (itemsFromNotes) {
            const itemsParsed = JSON.parse(itemsFromNotes);
            const items = itemsParsed.map(i => i.id);
            const params = {
                items_id: items
            };
            localStorage.removeItem("itemsForNotes");
            await this.$http
                .get("/documents/search-items", { params })
                .then(response => {
                    const itemsResponse = response.data.items.map(i => {
                        return this.setItemFromResponse(i, itemsParsed);
                    });
                    this.form.items = itemsResponse.map(i => {
                        return calculateRowItem(
                            i,
                            this.form.currency_type_id,
                            this.form.exchange_rate_sale,
                            this.percentage_igv, this.isExportInvoice
                        );
                    });
                });
        }

        //parse items from multiple sale notes not group
        this.processItemsForNotesNotGroup();

        const lotsItems = localStorage.getItem("lotsItems");
        if (lotsItems) {
            const lotsParsed = JSON.parse(lotsItems);
            localStorage.removeItem("lotsItems");
            this.form.items = this.form.items.map(row => {
                row.item.lots = _.filter(lotsParsed, { item_id: row.item_id });
                return row;
            });
        }

        const clientfromDispatchesOrNotes = localStorage.getItem("client");
        if (clientfromDispatchesOrNotes) {
            const client = JSON.parse(clientfromDispatchesOrNotes);
            // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
            this.form.document_type_id = "01";
            // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
            // ########## INICIO FACTURA DESDE MÚLTIPLES NOTAS PARA TODO CLIENTE ##########
            this.preloadedCustomerId = client.id;
            this.preloadedCustomer = client;
            await this.reloadDataCustomers(client.id);
            this.preloadedCustomer =
                this.customers.find(customer => sameInvoiceRecordId(customer.id, client.id)) || client;
            // ######### FIN FACTURA DESDE MÚLTIPLES NOTAS PARA TODO CLIENTE #########
            if (client.dispatch_establishment_id) this.form.establishment_id=client.dispatch_establishment_id;
            this.changeEstablishment();
            this.filterSeries();
            this.filterCustomers();
            this.changeCurrencyType();
            localStorage.removeItem("client");
        }
        const dispatchesNumbersFromDispatches = localStorage.getItem(
            "dispatches"
        );
        if (dispatchesNumbersFromDispatches) {
            this.form.dispatches_relateds = JSON.parse(
                dispatchesNumbersFromDispatches
            );
            localStorage.removeItem("dispatches");
        }
        const notesNumbersFromNotes = localStorage.getItem("notes");
        if (notesNumbersFromNotes) {
            this.form.sale_notes_relateds = JSON.parse(notesNumbersFromNotes);
            localStorage.removeItem("notes");
        }

        // if (this.form.currency_type_id === 'USD') { // Si los documentos precargados han sido establecidos y tienen dolar
        //     this.changeCurrencyType();
        // } else if (this.config.currency_type_id === 'USD' ) { // Si en configuracion tiene como dolar por defecto
        //     this.changeCurrencyType();
        // }

        this.startConnectionQzTray();



    },
    watch: {
        // ######## INICIO ESTABILIDAD DE EVALUACION FISCAL ########
        igtfQuote(value) { if (value) this.lastDisplayedFiscalQuote = value; },
        form() { this.lastDisplayedFiscalQuote = null; },
        // ######## FIN ESTABILIDAD DE EVALUACION FISCAL ########
        receiptAppliedAmounts() {
            if (['02', '03'].includes(this.form?.payment_condition_id) && this.form?.fee?.length) this.calculateFee();
        },
        'form.customer_id': 'checkCustomerExpiredDebt',
        'form.payment_condition_id': 'checkCustomerExpiredDebt',
        'form.fee' : {
            handler(newValue, oldValue) {
                if (this.form.payment_condition_id === '02' ) {
                    this.form.date_of_due = newValue[0].date
                }
            },
            deep: true
        },
        showDialogNewPerson(newVal) {
            if (!newVal) {
                this.customerSearchTerm = ''
            }
        }
    },
    methods: {
        documentRowUnitPrice(row) { return exactValue(row,'unit_price').decimal(); },
        beginEditableInvoiceRow(row, field, event) {
            this._invoiceRowBeforeEdit ||= new WeakMap();
            const values = this._invoiceRowBeforeEdit.get(row) || {};
            if (!(field in values)) values[field] = row[field];
            this._invoiceRowBeforeEdit.set(row,values);
            this.valueInputSelect(event);
        },
        commitEditableInvoiceRow(row, field) {
            const index = this.form.items.indexOf(row);
            if (index < 0) return false;
            const before = _.cloneDeep(this.form);
            const previous = this._invoiceRowBeforeEdit?.get(row);
            if (['quantity','unit_price','total'].includes(field)) {
                try { before.items[index][field] = previous && field in previous ? previous[field] : exactValue(row,field).decimal(); } catch (_) {}
            }
            const subtotalBefore = this.subtotal_before_global_discount, discountBefore = this.total_discount_no_base;
            try {
                const next = recalculateInvoiceRow(row, field, {
                    currency_type_id:this.form.currency_type_id, exchange_rate_sale:this.form.exchange_rate_sale,
                    percentage_igv:this.percentage_igv, exportation:this.isExportInvoice,
                });
                this.$set(this.form.items,index,next);
                this.calculateTotal();
                this._invoiceRowBeforeEdit?.delete(row);
                return true;
            } catch (error) {
                Object.assign(this.form,before);
                this.subtotal_before_global_discount = subtotalBefore;
                this.total_discount_no_base = discountBefore;
                this._invoiceRowBeforeEdit?.delete(row);
                this.$message.error(error.message);
                return false;
            }
        },
        changeRowQuantity(row) { return this.commitEditableInvoiceRow(row,'quantity'); },
        changeRowUnitPrice(row) { return this.commitEditableInvoiceRow(row,'unit_price'); },
        changeRowTotal(row) { return this.commitEditableInvoiceRow(row,'total'); },
        recalculateRowTotals(row) { return this.commitEditableInvoiceRow(row,'recalculate'); },
        // ######## INICIO REPARTO DE VUELTO ########
        fiscalSourceForQuote(index) {
            return this.fiscalQuoteRows.filter(row => {
                try { return exactAmount(row.payment || '0').compare(0) > 0; } catch (_) { return false; }
            })[index] || null;
        },
        hasFiscalChange(payment) {
            try { return exactAmount(payment.change || '0').compare(0) > 0; } catch (_) { return false; }
        },
        validateChangeSelections() {
            if (!this.fiscalPaymentsApplicable || !this.igtfQuote) return true;
            for (let index = 0; index < this.igtfQuote.payments.length; index++) {
                const payment = this.igtfQuote.payments[index], row = this.fiscalSourceForQuote(index);
                if (!row || payment.reused) continue;
                if (row.change_disbursements == null) {
                    const defaults = defaultChangeDisbursements(payment, this.payment_method_types, this.payment_destinations, this.form.establishment_id);
                    if (defaults) this.$set(row, 'change_disbursements', defaults);
                }
                const result = validateChangeDisbursements(row.change_disbursements, payment, this.payment_method_types, this.payment_destinations, this.form.establishment_id);
                if (!result.valid) { this.$message.error(result.message); return false; }
            }
            return true;
        },
        // ######## FIN REPARTO DE VUELTO ########
        // ######## INICIO EQUIVALENCIA DEL COBRO Y VUELTO ########
        fiscalEquivalent(amount, payment) {
            try {
                const value = exactAmount(amount), rate = normalizeExchangeRate(payment.exchange_rate);
                if ((payment.currency_type_id || payment.currency) === 'USD') return `Bs. ${value.times(rate).final(2)}`;
                if ((payment.currency_type_id || payment.currency) === 'VES') return `$ ${value.dividedBy(rate).final(2)}`;
            } catch (_) { /* Sin tasa válida no se presenta una equivalencia inventada. */ }
            return '—';
        },
        // ######## FIN EQUIVALENCIA DEL COBRO Y VUELTO ########
        // ######## INICIO DETALLE DEL COBRO IGTF ########
        fiscalReceiptBreakdown(row) {
            if (!this.igtfQuote || this.igtfQuoteLoading || this.igtfQuoteError || this.igtfQuoteKey !== this.fiscalQuoteKey) return null;
            const rows = this.fiscalQuoteRows.filter(value => {
                try { return exactAmount(value.payment || '0').compare(0) > 0; } catch (_) { return false; }
            });
            const quoted = this.igtfQuote.payments[rows.indexOf(row)];
            if (!quoted || quoted.reused) return null;
            try {
                const principal = exactAmount(quoted.original_amount), tax = exactAmount(quoted.tax_amount);
                const total = principal.plus(tax);
                return {principal: principal.final(2), tax: tax.final(2), total: total.final(2), change: exactAmount(quoted.change ?? '0').final(2), currency: quoted.currency_type_id, exchange_rate: quoted.exchange_rate};
            } catch (_) { return null; }
        },
        // ######## FIN DETALLE DEL COBRO IGTF ########
        updateTenderedPayment(row, value) {
            this.$set(row, '_tendered_edited', true);
            this.$set(row, 'tendered_amount', value);
            this.markPaymentEdited(row);
        },
        updatePaymentPrincipal(row, value) {
            this.$set(row, 'payment', value);
            this.markPaymentEdited(row);
        },
        markPaymentEdited(row) {
            markPaymentEdited(row);
            this.calculatePayments();
        },
        restoreFiscalOptions(data) {
            this.form.fiscal_data = {special_tax_regime_id: data.special_tax_regime_id ?? null};
            this.savedSpecialTaxRegime = data.special_tax_regime_description || null;
            if (data.special_tax_regime_id && !this.specialTaxRegimes.some(row => row.id === data.special_tax_regime_id)) {
                this.specialTaxRegimes.push({id: data.special_tax_regime_id, description: data.special_tax_regime_description, inactive: true});
            }
            this.originalThirdPartyInvoice = Boolean(data.is_third_party_invoice);
            this.thirdPartyMode = this.originalThirdPartyInvoice;
            this.form.third_party_id = data.third_party_id || null;
            this.thirdPartySavedOption = data.third_party ? {id: data.third_party_id, name: data.third_party.name,
                third_party_rif: data.third_party.rif, third_party_activity: data.third_party.activity} : null;
            this.thirdParties = this.thirdPartySavedOption ? [this.thirdPartySavedOption] : [];
            this.specialTaxRegimeMode = data.special_tax_regime_id != null;
            this.thirdPartySearchTerm = '';
        },
        changeSpecialTaxRegimeMode(value) {
            if (value && (!this.specialTaxRegimeAvailable || this.form.payment_condition_id !== '01')) {
                this.specialTaxRegimeMode = false;
                this.$message.error('El régimen especial requiere una Factura interna al contado en Medios digitales DEMO.');
                return;
            }
            if (!value) this.form.fiscal_data.special_tax_regime_id = null;
        },
        openThirdPartyDialog(edit = false) {
            this.thirdPartyRecordId = edit ? this.form.third_party_id : null;
            const term = (this.thirdPartySearchTerm || '').trim();
            this.thirdPartyCreateInput = /^\d+$/.test(term) ? {number: term}
                : /^[VJEGPCR]-?\d/i.test(term) ? '' : term;
            this.showDialogThirdParty = true;
        },
        searchRemoteThirdParties(input) {
            this.thirdPartySearchTerm = input;
            return this.loadThirdParties(input);
        },
        changeSpecialTaxRegime(value) {
            if (value != null && (!this.specialTaxRegimeAvailable || this.form.payment_condition_id !== '01')) {
                this.form.fiscal_data.special_tax_regime_id = null;
                this.$message.error('El régimen especial requiere una Factura interna al contado en Medios digitales DEMO.');
            }
        },
        async thirdPartySaved(saved) {
            const options = await this.loadThirdParties(saved.rif || '');
            if (!options) return;
            if (options.some(row => row.id === saved.id)) this.form.third_party_id = saved.id;
            else {
                if (this.form.third_party_id === saved.id) this.form.third_party_id = null;
                this.$message.error('El cliente debe estar habilitado y marcado como tercero para seleccionarlo.');
            }
        },
        changeThirdPartyMode(value) {
            if (this.isUpdateDocument || (value && (!this.thirdPartyAvailable || this.form.payment_condition_id !== '01'))) {
                this.thirdPartyMode = this.originalThirdPartyInvoice;
                this.$message.error('Seleccione una Factura de venta interna al contado para facturar por terceros.');
                return;
            }
            this.form.third_party_id = null;
            this.thirdPartySearchSequence += 1;
            this.thirdPartySearchTerm = '';
            this.loadingThirdParties = false;
            if (value) this.loadThirdParties('');
        },
        async loadThirdParties(input = '') {
            if (!this.thirdPartyMode) return;
            const sequence = ++this.thirdPartySearchSequence;
            this.loadingThirdParties = true;
            try {
                const response = await this.$http.get('/documents/search/third-parties', {params: {input}});
                if (sequence !== this.thirdPartySearchSequence || !this.thirdPartyMode) return;
                const eligible = response.data.customers || [];
                const options = [...eligible];
                if (this.thirdPartySavedOption && !options.some(row => row.id === this.thirdPartySavedOption.id)) options.push(this.thirdPartySavedOption);
                this.thirdParties = options;
                return eligible;
            } catch (_) {
                if (sequence === this.thirdPartySearchSequence) this.$message.error('No se pudo cargar la lista de clientes terceros.');
            } finally {
                if (sequence === this.thirdPartySearchSequence) this.loadingThirdParties = false;
            }
        },
        normalizeAddressText(address) {
            return (address || '').trim().toLowerCase();
        },
        buildCustomerAddresses(customer) {
            if (!customer) {
                return [];
            }

            const seen = new Set();
            const result = [];

            (customer.addresses || [])
                .filter(el => !el.has_consigned)
                .forEach(addressRow => {
                    const normalized = this.normalizeAddressText(addressRow.address);
                    if (!normalized || seen.has(normalized)) {
                        return;
                    }

                    seen.add(normalized);
                    result.push(addressRow);
                });

            if (customer.address) {
                const normalizedPrincipal = this.normalizeAddressText(customer.address);
                if (normalizedPrincipal && !seen.has(normalizedPrincipal)) {
                    result.unshift({
                        id: null,
                        address: customer.address
                    });
                }
            }

            return result;
        },
        selectDefaultCustomerAddress() {
            if (this.customer_addresses.length === 0) {
                this.form.customer_address_id = null;
                return;
            }

            const mainAddress = _.find(this.customer_addresses, { main: 1 });
            const defaultAddress = mainAddress || this.customer_addresses[0];
            this.form.customer_address_id = defaultAddress.id;
        },
        // ───── Datos generales personalizables (DocumentFormLayout) ─────
        capturePinnedBar() {
            if (this.pinnedBarInstance || !this.$refs.pinnedBar) return;
            this.pinnedBarInstance = this.$refs.pinnedBar;
            this.pinnedBarReady = true;
        },
        isLayoutPinned(fieldKey) {
            return this.layoutPinnedKeysSet.has(fieldKey);
        },
        loadFormLayout() {
            return this.$http
                .get('/document-form-layout/invoice')
                .then(({ data }) => {
                    const remote = data && data.data && Array.isArray(data.data.pinned_fields)
                        ? data.data.pinned_fields
                        : [];
                    this.pinned_fields = remote.length > 0
                        ? remote
                        : getDocumentDefaultLayout('invoice');
                })
                .catch(() => {
                    this.pinned_fields = getDocumentDefaultLayout('invoice');
                });
        },
        enterLayoutEditFromHeader() {
            if (this.$refs.pinnedBar && typeof this.$refs.pinnedBar.enterEditMode === 'function') {
                this.$refs.pinnedBar.enterEditMode();
            }
        },
        resetLayoutFromHeader() {
            if (this.$refs.pinnedBar && typeof this.$refs.pinnedBar.resetLayout === 'function') {
                this.$refs.pinnedBar.resetLayout();
            }
        },
        confirmLayoutFromHeader() {
            if (this.$refs.pinnedBar && typeof this.$refs.pinnedBar.confirmEdit === 'function') {
                this.$refs.pinnedBar.confirmEdit();
            }
        },
        cancelLayoutEditFromHeader() {
            if (this.$refs.pinnedBar && typeof this.$refs.pinnedBar.cancelEdit === 'function') {
                this.$refs.pinnedBar.cancelEdit();
            }
        },
        pinFromForm(fieldKey) {
            if (this.$refs.pinnedBar && typeof this.$refs.pinnedBar.pinField === 'function') {
                this.$refs.pinnedBar.pinField(fieldKey);
            }
        },
        onSaveLayout(pinned, done) {
            this.layout_saving = true;
            this.$http
                .put('/document-form-layout/invoice', { pinned_fields: pinned })
                .then(response => {
                    if (response.data && response.data.success) {
                        this.pinned_fields = response.data.data.pinned_fields;
                        this.$message.success(response.data.message || 'Configuración guardada con éxito');
                        if (typeof done === 'function') done();
                    } else {
                        this.$message.error((response.data && response.data.message) || 'No se pudo guardar la configuración');
                    }
                })
                .catch(() => {
                    this.$message.error('No se pudo guardar la configuración');
                })
                .finally(() => {
                    this.layout_saving = false;
                });
        },
        sortOperationTypes(operationTypes) {
            const sorted = operationTypes.slice();
            const internalSaleIndex = sorted.findIndex(ot => ot.id === "0101");
            const itinerantIndex = sorted.findIndex(
                ot => ot.id === "0101_itinerant"
            );

            if (
                internalSaleIndex !== -1 &&
                itinerantIndex !== -1 &&
                itinerantIndex !== internalSaleIndex + 1
            ) {
                const [itinerantOperation] = sorted.splice(itinerantIndex, 1);
                const newInternalSaleIndex = sorted.findIndex(
                    ot => ot.id === "0101"
                );
                sorted.splice(newInternalSaleIndex + 1, 0, itinerantOperation);
            }

            return sorted;
        },
        syncSingleOperationTypeSelection() {
            if (this.operation_types_filter.length !== 1) return;

            const operationType = this.operation_types_filter[0];
            const isCurrentItinerantOperation =
                operationType.id === "0101_itinerant" &&
                this.form.operation_type_id === "0101" &&
                this.is_itinerant_operation;

            if (
                this.operation_type_id_view === operationType.id &&
                (operationType.id !== "0101_itinerant" ||
                    isCurrentItinerantOperation)
            ) return;

            this.operation_type_id_view = operationType.id;
            this.changeOperationType();
        },
        async initComponent() {
            this.loadConfiguration();
            this.$store.commit("setConfiguration", this.configuration);

            // Cargar price_options desde la API de price labels activos
            await this.loadPriceOptions();

            await this.loadFormLayout();

            await this.initForm();
            await this.$http.get(`/${this.resource}/tables`).then(response => {
                this.document_types = response.data.document_types_invoice;
                this.document_types_guide = response.data.document_types_guide;
                this.currency_types = response.data.currency_types;
                this.business_turns = response.data.business_turns;
                this.establishments = response.data.establishments;
                this.operation_types = this.sortOperationTypes(
                    response.data.operation_types
                );
                this.is_restaurant_active = response.data.is_restaurant_active;
                this.restaurant_tip_factor = response.data.restaurant_tip_factor;
                this.$store.commit("setAllSeries", response.data.series);
                // this.all_series = response.data.series
                this.all_customers = response.data.customers;
                this.sellers = response.data.sellers;
                this.discount_types = response.data.discount_types;
                this.charges_types = response.data.charges_types;
                this.payment_method_types = response.data.payment_method_types;
                this.enabled_discount_global =
                    response.data.enabled_discount_global;
                this.company = response.data.company;
                this.specialTaxRegimes = response.data.special_tax_regimes || [];
                this.user = response.data.user;
                this.document_type_03_filter =
                    response.data.document_type_03_filter;
                this.select_first_document_type_03 =
                    response.data.select_first_document_type_03;
                // this.form.currency_type_id = window.salesDocumentsVes ? 'VES' : (this.currency_types.length > 0)?this.currency_types[0].id:null;
                this.form.establishment_id =
                    this.establishments.length > 0
                        ? this.establishments[0].id
                        : null;
                this.form.document_type_id = this.document_types.length > 0
                        ? this.document_types[0].id
                        : null;
                this.form.operation_type_id =
                    this.operation_types.length > 0
                        ? this.operation_types[0].id
                        : null;
                this.form.seller_id = this.sellers.length > 0 ? this.idUser : null;
                this.affectation_igv_types = response.data.affectation_igv_types;
                // this.prepayment_documents = response.data.prepayment_documents;
                this.is_client = response.data.is_client;
                this.payment_destinations = response.data.payment_destinations
                this.paymentMethodsLoaded = true;
                restoreDeferredInvoiceCash(this);;
                this.payment_conditions = response.data.payment_conditions;

                this.seller_class =
                    this.user == "admin" ? "col-lg-4 pb-2" : "col-lg-6 pb-2";
                this.global_discount_types = response.data.global_discount_types;

                // this.default_document_type = response.data.document_id;
                // this.default_series_type = response.data.series_id;
                this.selectDocumentType();
                this.changeEstablishment();
                this.changeDateOfIssue();
                this.changeDocumentType();
                this.changeDestinationSale();
                this.setDefaultDocumentType();
                this.setConfigGlobalDiscountType();
                this.startConnectionQzTray();
                this.verifySelectedSeller();
            });
        },

        searchNumber(data) {
            this.form.itinerant = {
                id: this.itinerant_option_id,
                description: this.option_address_itinerant.find(
                    item => item.id === this.itinerant_option_id
                ).description,
                address: {
                    address: data.address,
                    department_id: data.location_id[0],
                    province_id: data.location_id[1],
                    district_id: data.location_id[2],
                    country_id: "VE",
                    telephone: data.telephone
                }
            }

            this.$message.success(
                'Se agrego la dirección del RIF: ' + this.ruc_itinerant
                );


        },
        changeItineratOption()
        {
            if (this.form.is_itinerant) {
                if (this.itinerant_option_id == 1) {
                    this.form.itinerant = {
                        id: this.itinerant_option_id,
                        description: this.option_address_itinerant.find(
                            item => item.id === this.itinerant_option_id
                        ).description,
                    }
                } else if (this.itinerant_option_id == 2) {
                    this.form.customer_address_id = null;
                }

            } else {
                this.form.itinerant = null;
            }
        },
        toggleInformation() {
            this.isVisible = !this.isVisible;
        },
        handlePanelToggle(panelIndex) {
            if (this.activePanel === panelIndex) {
                this.activePanel = null; // Cierra el panel si ya está activo
            } else {
                this.activePanel = panelIndex; // Abre el panel seleccionado
            }
        },
        valueInputSelect(event) {
            event.target.select();
        },
        clickShowItemDetail(id) {
            // this.itemDetailId = id
            // this.showDialogItemDetail = true
            window.open(`/items/show-item-detail/${id}`);
        },
        changeDataTip(tip) {
            if (tip) {
                this.form.worker_full_name_tips = tip.worker_full_name_tips;
                this.form.total_tips = tip.total_tips;
            }
        },
        onSuccessUploadVoucher(response, file, fileList, index) {
            if (response.success) {
                this.form.payments[index].filename = response.data.filename;
                this.form.payments[index].temp_path = response.data.temp_path;
                this.form.payments[index].file_list = fileList;
            } else {
                this.cleanFileListUploadVoucher(index);
                this.$message.error(response.message || 'No se pudo cargar el archivo.');
            }
        },
        onUploadErrorVoucher(error, file, fileList, index) {
            this.cleanFileListUploadVoucher(index);
            this.$message.error(this.getPaymentFileErrorMessage(error));
        },
        getPaymentFileErrorMessage(error) {
            const data = error && error.response ? error.response.data : null;

            if (typeof data === 'string' && data.trim()) {
                return data;
            }

            if (data && data.message) {
                return data.message;
            }

            if (error && error.message) {
                return error.message;
            }

            return 'Ocurrió un error al procesar la solicitud.';
        },
        cleanFileListUploadVoucher(index) {
            this.form.payments[index].file_list = [];
        },
        handleRemoveUploadVoucher(file, fileList, index) {
            this.form.payments[index].filename = null;
            this.form.payments[index].temp_path = null;
            this.cleanFileListUploadVoucher(index);
        },
        ...mapActions(["loadConfiguration"]),
        /**
         * Cargar opciones de precio desde la API de price_labels activos
         */
        async loadPriceOptions() {
            try {
                const response = await this.$http.get('/price-labels/active');
                const labels = response.data.data || [];


                const mainLabel = (this.config && this.config.price1_label) ? this.config.price1_label : 'Precio principal';
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
        initForm() {
            this.invoiceDraftGeneration=(this.invoiceDraftGeneration || 0)+1;
            this.invoiceCalculationError=null;
            this.invoiceAllowancePercent = null;
            this.specialTaxRegimeMode = false;
            this.thirdPartySearchTerm = '';
            this.thirdPartyCreateInput = '';
            this.thirdPartyRecordId = null;
            this.savedSpecialTaxRegime = null;
            this.thirdPartyMode = false;
            this.originalThirdPartyInvoice = false;
            this.thirdPartySavedOption = null;
            this.thirdParties = [];
            this.thirdPartySearchSequence += 1;
            this.loadingThirdParties = false;
            this.errors = {};
            this.form = {
                establishment_id: null,
                document_type_id: null,
                series_id: null,
                seller_id: this.idUser,
                number: "#",
                is_itinerant: false,
                date_of_issue: moment().format("YYYY-MM-DD"),
                time_of_issue: moment().format("HH:mm:ss"),
                customer_id: null,
                third_party_id: null,
                fiscal_data: {special_tax_regime_id: null},
                currency_type_id: window.salesDocumentsVes ? 'VES' : this.config.currency_type_id,
                purchase_order: null,
                exchange_rate_sale: 0,
                total_prepayment: 0,
                total_charge: 0,
                total_discount: 0,
                total_exportation: 0,

                total_taxed: 0,
                // ######## INICIO RESUMEN REACTIVO IVA E IGTF ########
                calculation_snapshot: null,
                // ######## FIN RESUMEN REACTIVO IVA E IGTF ########

                total_exonerated: 0,
                total_igv: 0,


                total_base_other_taxes: 0,
                total_other_taxes: 0,

                total_taxes: 0,
                total_value: 0,
                total: 0,
                total_payable: 0,
                settlement_adjustment: 0,
                subtotal: 0,

                operation_type_id: null,
                date_of_due: moment().format("YYYY-MM-DD"),
                items: [],
                charges: [],
                discounts: [],
                attributes: [],
                guides: [],
                payments: [],
                prepayments: [],
                legends: [],
                additional_information: null,
                plate_number: null,
                has_prepayment: false,
                affectation_type_prepayment: null,
                actions: {
                    format_pdf: "a4"
                },
                hotel: {},
                transport: {},
                customer_address_id: null,
                pending_amount_prepayment: 0,
                payment_method_type_id: null,
                show_terms_condition: true,
                terms_condition: "",
                payment_condition_id: "01",
                fee: [],
                total_pending_payment: 0,


                quotation_id: null,

                worker_full_name_tips: null, //propinas
                total_tips: 0, //propinas
                consigned_id: null,
                consigned_address_id: null,
                consigned_address: null,
                consigned_ubigeo: null,
                custom_fields_data: {},
                source_module: "WEB",
            };

            this.form_cash_document = {
                document_id: null,
                sale_note_id: null
            };

            this.clickAddPayment();
            this.clickAddInitGuides();
            this.is_receivable = false;
            this.total_global_discount = 0;
            this.invoiceGlobalDiscountSource = null;
            this.invoiceGlobalChargeSource = null;
            this.invoiceDiscountRestoreError = null;
            this.recordDiscountsGlobal = null;
            this.total_global_charge = 0;
            this.is_amount = true;
            this.prepayment_deduction = false;
            this.$eventHub.$emit("eventInitForm");

            this.initInputPerson();

            if (!this.config.restrict_receipt_date) {
                this.datEmision = {};
            }

            this.enabled_payments = true;
            this.readonly_date_of_due = false;
            this.total_discount_no_base = 0;

            this.resetCustomerPoints();



            this.$eventHub.$emit("eventInitTip");
        },
        startConnectionQzTray() {
            if (!this.isBuhoActive && this.isAutoPrint) {
                this.startConnectionBuho();
            }
        },
        changeRowExchangePoints(row, index) {
            row.item.change_free_affectation_igv = !row.item
                .change_free_affectation_igv;
            row.item.used_points_for_exchange = row.item
                .change_free_affectation_igv
                ? this.getUsedPoints(row)
                : null;
            this.setTotalExchangePoints(); //in mixins
            this.changeRowFreeAffectationIgv(row, index);
        },
        async changeRowFreeAffectationIgv(row, index) {
            if (row.item.change_free_affectation_igv) {
                this.form.items[index].affectation_igv_type_id = "15";
                this.form.items[index].affectation_igv_type = await _.find(
                    this.affectation_igv_types,
                    { id: this.form.items[index].affectation_igv_type_id }
                );
            } else {
                this.form.items[
                    index
                ].affectation_igv_type_id = this.form.items[
                    index
                ].item.original_affectation_igv_type_id;
                this.form.items[index].affectation_igv_type = await _.find(
                    this.affectation_igv_types,
                    { id: this.form.items[index].affectation_igv_type_id }
                );
            }

            this.form.items[index] = await calculateRowItem(
                row,
                this.form.currency_type_id,
                this.form.exchange_rate_sale,
                this.percentage_igv, this.isExportInvoice
            );
            await this.calculateTotal();
        },
        async processItemsForNotesNotGroup() {
            let itemsNotGroupForNotes = localStorage.getItem(
                "itemsNotGroupForNotes"
            );

            if (itemsNotGroupForNotes) {
                let itemsParsed = JSON.parse(itemsNotGroupForNotes);

                // prepare - validate prop presentation and others
                this.form.items = await this.onPrepareItems(itemsParsed).map(
                    element => {
                        element.item.presentation = element.item.presentation
                            ? element.item.presentation
                            : [];
                        return element;
                    }
                );

                await this.calculateTotal();
                localStorage.removeItem("itemsNotGroupForNotes");
            }
        },
        setItemFromResponse(item, itemsParsed) {
            /* Obtiene el igv del item, si no existe, coloca el gravado*/
            if (item.sale_affectation_igv_type !== undefined) {
                item.affectation_igv_type = item.sale_affectation_igv_type;
            } else {
                item.affectation_igv_type = {
                    active: 1,
                    description: "Gravado - Operación Onerosa",
                    exportation: 0,
                    free: 0,
                    id: "10"
                };
            }
            item.presentation = {};

            item.item = {

                attributes: item.attributes,
                brand: item.brand,
                calculate_quantity: item.calculate_quantity,
                category: item.category,
                currency_type_id: item.currency_type_id,
                currency_type_symbol: item.currency_type_symbol,
                description: item.description,
                full_description: item.full_description,
                has_igv: item.has_igv,

                id: item.id,
                internal_id: item.internal_id,
                item_unit_types: item.item_unit_types,
                lots: item.lots,
                lots_enabled: item.lots_enabled,
                lots_group: item.lots_group,
                model: item.model,
                presentation: {},
                purchase_affectation_igv_type_id:
                    item.purchase_affectation_igv_type_id,
                purchase_unit_price: item.purchase_unit_price,
                sale_affectation_igv_type_id: item.sale_affectation_igv_type_id,
                sale_unit_price: item.sale_unit_price,
                series_enabled: item.series_enabled,
                stock: item.stock,
                unit_price: item.sale_unit_price,
                unit_type_id: item.unit_type_id,
                warehouses: item.warehouses
            };
            item.IdLoteSelected = null;
            if (item.affectation_igv_type_id === undefined) {
                item.affectation_igv_type_id = item.affectation_igv_type.id;
                // item.affectation_igv_type_id = "10";
            }
            item.discounts = [];
            item.charges = [];
            item.item_id = item.id;
            item.unit_price_value = item.sale_unit_price;
            item.input_unit_price_value = item.sale_unit_price;

            item.quantity = 1;

            const sourceItem = itemsParsed.find(ip => ip.item_id == item.id || ip.id == item.id);
            if (sourceItem !== undefined) {
                item.quantity = sourceItem.quantity;
            }

            // item.quantity = itemsParsed.find(ip => ip.item_id == item.id).quantity;
            item.warehouse_id = null;

            if (itemsParsed.length) {
                let itemParsed = itemsParsed.find(
                    element => element.id == item.item.id
                );
                if (itemParsed) {
                    item.item.unit_price = itemParsed.unit_price;
                }
            }

            return item;
        },
        calculateDispatchInvoiceRow(item, itemsParsed) {
            const row = this.setItemFromResponse(item, []);
            row._from_delivery_order = true;
            const matchingItems = itemsParsed.filter(source => String(source.item_id) === String(item.id));
            const quantity = exactSum(matchingItems.map(source => exactValue(source, 'quantity')));
            return calculateRowItem(
                row,
                this.form.currency_type_id,
                this.form.exchange_rate_sale,
                this.percentage_igv,
                this.isExportInvoice,
                quantity.compare(0) > 0 ? quantity.fraction() : null
            );
        },
        disabledSeries() {
            return (
                this.configuration.restrict_series_selection_seller &&
                this.typeUser !== "admin"
            );
        },
        // #307 Ajuste para seleccionar automaticamente el tipo de comprobante y serie
        setDefaultDocumentType(from_function) {
            if (this.authUser.multiple_default_document_types) return;
            if (this.shouldProtectPreloadedCustomer()) return;

            this.default_series_type = this.config.user.serie;
            this.default_document_type = this.config.user.document_id;
            // if (this.default_document_type === undefined) this.default_document_type = null;
            // if (this.default_series_type === undefined) this.default_series_type = null;

            let alt = _.find(this.document_types, {
                id: this.default_document_type
            });
            if (this.default_document_type !== null && alt !== undefined) {
                this.form.document_type_id = this.default_document_type;
                this.changeDocumentType();
                alt = _.find(this.series, { id: this.default_series_type });
                if (this.default_series_type !== null && alt !== undefined) {
                    this.form.series_id = this.default_series_type;
                }
            }
        },
        onPrepareDataEstablishment(data) {
            if (this.isFromExternalDocument) {
                this.form.establishment_id = this.establishment.id;
            } else {
                this.form.establishment_id = data.establishment_id;
            }
        },
        async onSetFormData(data) {
            this.invoiceDraftGeneration=(this.invoiceDraftGeneration || 0)+1;
            this.invoiceCalculationError=null;
            this.invoiceGlobalDiscountSource=null;
            this.invoiceGlobalChargeSource=null;
            this.total_global_charge='0';
            this.invoiceDiscountRestoreError=null;
            this.total_global_discount='0';
            this.recordDiscountsGlobal=null;
            this.is_amount=true;
            // carga informacion de un documento previo al formulario
            // console.log('onSetFormData')
            this.currency_type = await _.find(this.currency_types, {
                id: data.currency_type_id
            });

            /*
            this.form.establishment_id = data.establishment_id;
            */

            this.onPrepareDataEstablishment(data);

            this.form.document_type_id = data.document_type_id;

            this.customers = this.customers.filter(el => !sameInvoiceRecordId(el.id, data.customer_id))
            this.customers.push(data.customer)

            if (this.isGeneratedFromExternal && data.customer) {
                this.all_customers = this.all_customers.filter(el => !sameInvoiceRecordId(el.id, data.customer_id))
                this.all_customers.push(data.customer)
            }

            this.form.can_edit = data.can_edit;
            this.form.edit_block_reason = data.edit_block_reason;
            this.existingPayments = JSON.parse(JSON.stringify(data.payments || []));
            this.existingRetentions = data.received_retentions || [];
            this.existingGuaranteeFund = data.guarantee_fund || null;
            this.form.id = data.id;
            this.form.custom_fields_data = data.custom_fields_data;
            this.form.number = data.number;
            this.form.date_of_issue = moment(data.date_of_issue).format(
                "YYYY-MM-DD"
            );
            this.form.date_of_due = moment((data.invoice && data.invoice.date_of_due) || data.date_of_due || data.date_of_issue).format("YYYY-MM-DD");
            this.form.time_of_issue = data.time_of_issue;
            this.form.customer_id = data.customer_id;

            if (this.isGeneratedFromExternal && data.customer_id) {
                this.preloadedCustomerId = data.customer_id;
                this.preloadedCustomer = data.customer || null;
            }

            this.form.currency_type_id = window.salesDocumentsVes ? 'VES' : data.currency_type_id;
            this.form.exchange_rate_sale = data.exchange_rate_sale;
            this.form.external_id = data.external_id;
            this.form.filename = data.filename;
            this.form.group_id = data.group_id;

            this.form.note = data.note;
            this.restoreFiscalOptions(data);
            this.form.plate_number = data.plate_number;
            this.form.payments = data.payments || [];
            this.form.prepayments = data.prepayments || [];
            this.form.legends = [];
            this.form.sale_notes_relateds = data.sale_notes_relateds
                ? data.sale_notes_relateds
                : null;
            this.form.affectation_type_prepayment =
                data.affectation_type_prepayment;
            this.form.purchase_order = data.purchase_order;
            this.form.pending_amount_prepayment =
                data.pending_amount_prepayment || 0;
            this.form.payment_method_type_id = data.payment_method_type_id;
            this.form.charges = Object.values(data.charges || []);
            // this.form.discounts = this.prepareDataGlobalDiscount(data);
            // this.form.discounts = data.discounts || [];
            this.form.seller_id = data.seller_id;
            this.form.items = this.onPrepareItems(data.items);
            // this.series = this.onSetSeries(data.document_type_id, data.series);
            this.form.state_type_id = data.state_type_id;
            this.form.total_discount = String(data.total_discount ?? "0");
            this.form.total_exonerated = String(data.total_exonerated ?? "0");
            this.form.total_exportation = String(data.total_exportation ?? "0");

            this.form.total_igv = String(data.total_igv ?? "0");


            this.form.total_base_other_taxes = String(data.total_base_other_taxes ?? '0');
            this.form.total_other_taxes = String(data.total_other_taxes ?? "0");

            this.form.total_prepayment = String(data.total_prepayment ?? "0");
            this.form.total_taxed = String(data.total_taxed ?? "0");
            this.form.total_taxes = String(data.total_taxes ?? "0");

            this.form.total_value = String(data.total_value ?? "0");
            this.form.total_charge = String(data.total_charge ?? "0");
            this.form.total = String(data.total ?? "0");
            this.form.subtotal = String(data.subtotal ?? "0");

            this.form.operation_type_id = data.invoice
                ? data.invoice.operation_type_id
                : data.operation_type_id;
            if (this.isExportInvoice) {
                this.setInvoiceGlobalCharge(exactSum(this.form.charges.map(charge => exactAmount(charge.amount_exact ?? charge.amount ?? '0'))));
            } else if (!this.config.active_allowance_charge) {
                this.setInvoiceGlobalCharge(exactSum(this.form.charges.filter(charge=>charge.charge_type_id==='50')
                    .map(charge=>exactAmount(charge.amount_exact ?? charge.amount ?? '0'))));
            }
            this.form.terms_condition = data.terms_condition || "";
            this.form.guides = data.guides || [];
            this.form.show_terms_condition = data.terms_condition
                ? true
                : false;
            this.form.attributes = [];
            this.form.customer = data.customer;
            this.form.has_prepayment = false;
            this.form.actions = {
                format_pdf: "a4"
            };
            this.form.hotel = {};
            this.form.transport = {};
            this.form.customer_address_id = null;
            this.form.type = "invoice";
            this.form.invoice = {
                operation_type_id: data.invoice
                    ? data.invoice.operation_type_id
                    : data.operation_type_id,
                date_of_due: data.invoice
                    ? data.invoice.date_of_due
                    : data.date_of_due
            };
            // this.form.payment_condition_id = '01';

            let is_credit_installments = await _.find(data.fee, {
                payment_method_type_id: null
            });
            this.form.payment_condition_id = is_credit_installments
                ? "03"
                : data.payment_condition_id;
            this.form.fee = data.fee;


            this.form.quotation_id = data.quotation_id;

            this.form.additional_information = this.onPrepareAdditionalInformation(
                data.additional_information
            );

            // if (this.enabled_discount_global) {
            //     let discount_global =
            //         this.configuration.global_discount_type_id === "02" &&
            //         this.configuration.exact_discount
            //             ? _.round(
            //                   parseFloat(
            //                       (data.total_discount * 1.18).toFixed(3)
            //                   ),
            //                   2
            //               )
            //             : data.total_discount;
            //     this.total_global_discount = discount_global;
            // }

            // this.form.additional_information = data.additional_information;
            // this.form.fee = [];


            if (!data.guides) {
                this.clickAddInitGuides();
            }

            if (this.isGeneratedFromExternal) {
                this.preparePaymentsFee(data);
            }

            await this.reloadDataCustomers(this.form.customer_id);

            this.establishment = data.establishment;

            await this.changeDateOfIssue();
            // await this.filterCustomers();
            this.updateChangeDestinationSale();

            this.prepareDataCustomer();

            if (this.restoreInvoiceGlobalDiscount(data)===false) return false;
            this.regenerateItems();
            this.calculateTotal();
            // this.currency_type = _.find(this.currency_types, {'id': this.form.currency_type_id})

            if (this.table === "quotations") {
                this.changeDocumentType();
            } else if (this.isUpdateDocument) {
                this.filterSeries(this.onSetSeriesId(data.document_type_id, data.series));
            } else {
                this.filterSeriesForTable();
            }
            if (!this.form.custom_fields_data) {
                this.$set(this.form, 'custom_fields_data', {});
            }
        },
        filterSeriesForTable() {
            if (this.table) {
                this.filterSeries();
            }
        },
        regenerateItems(){
            let items = [];
            this.form.items.forEach(row => {
                items.push(
                    calculateRowItem(
                        row,
                        this.form.currency_type_id,
                        this.form.exchange_rate_sale,
                        this.percentage_igv, this.isExportInvoice
                    )
                );
            });
            this.form.items = items;
        },
        preparePaymentsFee(data) {
            if (this.isCreditPaymentCondition) {
                // credito
                if (this.form.payment_condition_id === "02") {
                    this.clickAddFeeNew();
                    const index = 0;
                    this.readonly_date_of_due = true;

                    this.form.fee[index].payment_method_type_id =
                        data.document_payment_method_type.id;
                    this.changePaymentMethodType(index, true);
                }
            }
        },
        prepareDataGlobalDiscount(data) {
            const discounts = data.discounts
                ? Object.values(data.discounts)
                : [];

            if (discounts.length === 1) {
                if (
                    discounts[0].is_amount !== undefined &&
                    discounts[0].is_amount !== null
                ) {
                    this.is_amount = discounts[0].is_amount;
                }

                const discount = discounts[0];
                if (this.is_amount) {
                    this.setInvoiceGlobalDiscount(exactAmount(discount.amount_exact ?? discount.amount));
                } else {
                    let percentage = discount.percentage != null
                        ? exactAmount(discount.percentage) : exactAmount(discount.factor).times(100);
                    if (this.isExportInvoice && discount.percentage == null && discount.amount_exact != null) {
                        const base = exactSum(this.form.items.filter(item => item.affectation_igv_type_id === 'EXP')
                            .map(item => exactValue(item, 'total_value')));
                        if (base.compare(0) > 0) percentage = exactAmount(discount.amount_exact).times(100).dividedBy(base);
                    }
                    this.setInvoiceGlobalDiscount(percentage);
                }
            }

            return discounts;
        },
        restoreInvoiceGlobalDiscount(data) {
            const restore=captureInvoiceEdit(this);
            try {
                const headers=Object.values(data.discounts || {});
                if (headers.length>1) throw new Error('La factura tiene varios descuentos globales. Revise sus ajustes antes de editarla.');
                if (headers.length===1) {
                    this.recordDiscountsGlobal=headers[0];
                    this.prepareDataGlobalDiscount(data);
                    if (!this.isExportInvoice && this.is_amount) {
                        this.setInvoiceGlobalDiscount(this.invoiceDiscountInputForBase(
                            exactAmount(headers[0].amount_exact ?? headers[0].amount),headers[0].discount_type_id));
                    }
                    return true;
                }
                if (this.isExportInvoice) return true;
                const portions=this.form.items.map(item=>{
                    const discounts=(item.discounts || []).filter(discount=>discount.from_global_distribution===true);
                    return {discounts,amount:exactSum(discounts.map(discount=>exactAmount(discount.amount_exact ?? discount.amount_without_rounded ?? discount.amount)))};
                });
                const kinds=new Set(portions.flatMap(row=>row.discounts.map(discount=>discount.discount_type_id)));
                if (!kinds.size) return true;
                if (kinds.size!==1 || !['00','01'].includes([...kinds][0])) throw new Error('No se puede reconstruir el descuento global guardado sin cambiar sus partidas.');
                const type=[...kinds][0]==='00' ? '02' : '03';
                const amount=exactSum(portions.map(row=>row.amount));
                this.clearGlobalDistributionDiscounts();
                const selected=this.form.items.filter(item=>this.isDiscountableAffectation(item));
                const base=exactSum(selected.map(item=>exactValue(item,'total_value')));
                if (base.compare(0)<=0) throw new Error('El descuento global guardado no tiene una base válida.');
                this.form.items.forEach((item,index)=>{
                    const expected=this.isDiscountableAffectation(item) ? exactValue(item,'total_value').times(amount).dividedBy(base) : exactAmount(0);
                    if (portions[index].amount.compare(expected)!==0) throw new Error('El descuento global guardado no coincide con su reparto original. No se modificaron sus importes.');
                });
                // Distributed lines retain the fixed shares, not the original
                // percentage input. Recover an equivalent fixed amount only.
                this.is_amount=true;this.recordDiscountsGlobal={discount_type_id:type};
                this.setInvoiceGlobalDiscount(this.invoiceDiscountInputForBase(amount,type));
                return true;
            } catch (error) {
                restore();this.invoiceDiscountRestoreError=error.message;this.invoiceCalculationError=error.message;
                this.$message.error(error.message);return false;
            }
        },
        invoiceDiscountInputForBase(amount,type) {
            if (type!=='02' || !this.configuration.exact_discount) return amount;
            const rows=this.form.items.filter(item=>this.isDiscountableAffectation(item));
            const base=exactSum(rows.map(item=>exactValue(item,'total_value')));
            if (base.compare(0)<=0) throw new Error('El descuento global no tiene una base válida.');
            const taxed=exactSum(rows.filter(item=>item.affectation_igv_type_id==='10').map(item=>exactValue(item,'total_value')));
            return amount.times(exactAmount(1).plus(exactAmount(this.percentage_igv).times(taxed).dividedBy(base)));
        },
        async prepareDataCustomer() {
            const customer = _.find(this.customers, {
                id: this.form.customer_id
            });

            this.customer_addresses = this.buildCustomerAddresses(customer);
            this.form.customer_address_id = this.form.customer
                ? this.form.customer.address_id
                : null;

            if (!this.form.customer_address_id && this.customer_addresses.length > 0) {
                this.selectDefaultCustomerAddress();
            }
        },

        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        updateChangeDestinationSale() {
            if (this.form.payment_condition_id === '01') this.changeDestinationSale();
        },
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        onPrepareAdditionalInformation(data) {
            let obs = null;
            if (Array.isArray(data)) {
                if (data.length > 0) {
                    if (data[0] == "") {
                        return obs;
                    }
                }
                return data.join("|");
            }

            return data;
        },
        onPrepareItems(items) {
            return items.map(i => {
                if (this.table) {
                    i.unit_price_value = i.unit_value;
                    i.input_unit_price_value = i.item.has_igv
                        ? i.item.unit_price
                        : i.unit_value;
                } else {
                    i.unit_price_value = i.unit_value;
                    i.input_unit_price_value = i.item.has_igv
                        ? i.unit_price
                        : i.unit_value;
                }

                i.discounts = i.discounts ? Object.values(i.discounts) : [];
                // i.discounts = i.discounts || [];
                i.charges = i.charges || [];
                i.attributes = i.attributes || [];
                i.item.id = i.item_id;
                i.additional_information = this.onPrepareAdditionalInformation(
                    i.additional_information
                );
                i.item = this.onPrepareIndividualItem(i);

                // Si la cotización/origen ya trae lote, precargarlo; si no, queda null y se muestra "Asignar Lote"
                hydrateItemLots(i);

                return i;
            });
        },
        onPrepareIndividualItem(data) {
            let new_item = data.item;
            let currency_type = _.find(this.currency_types, {
                id: this.form.currency_type_id
            });

            new_item.currency_type_id = currency_type.id;
            new_item.currency_type_symbol = currency_type.symbol;

            new_item.sale_affectation_igv_type_id =
                data.affectation_igv_type_id;

            if (this.table) {
                new_item.sale_unit_price = new_item.unit_price;
                new_item.unit_price = new_item.unit_price;
            } else {
                new_item.sale_unit_price = data.unit_price;
                new_item.unit_price = data.unit_price;
            }

            return new_item;
        },
        onSetSeriesId(documentType, serie) {
            const find = this.all_series.find(
                s => s.document_type_id == documentType && s.number == serie
            );
            if (find) {
                return find.id;
            }
            return null;
        },
        onSetSeries(documentType, serie) {
            // console.log('onSetSeries')
            const find = this.all_series.find(
                s => s.document_type_id == documentType && s.number == serie
            );
            if (find) {
                return [find];
            }
            return [];
        },
        getPrepayment(index) {
            return _.find(this.prepayment_documents, {
                id: this.form.prepayments[index].document_id
            });
        },
        inputAmountPrepayment(index, enteredAmount) {
            try {
                const result = updatePrepaymentInput(this.form.prepayments[index], this.getPrepayment(index),
                    this.form.affectation_type_prepayment, this.percentage_igv, enteredAmount);
                this.$set(this.form.prepayments, index, result.row);
                if (result.clamped) this.$message.error('El monto debe ser menor o igual al del anticipo');
                this.changeTotalPrepayment();
            } catch (error) { this.$message.error(error.message); }
        },
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        changeDestinationSale() {
            if (!this.config.destination_sale) return;
            proposeCashDestination(this.payment_destinations || [], this.payment_method_types || [],
                (this.form.payments || [])[0], this.form.currency_type_id);
        },
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        changePaymentDestination(index) {
            // if(this.form.payments[index].payment_method_type_id=='01'){
            //     this.payment_destinations = this.cash
            // }else{
            //     this.payment_destinations = this.payment_destinations
            // }
        },
        changeEnabledPayments() {
            // this.clickAddPayment()
            // this.form.date_of_due = this.form.date_of_issue
            // this.readonly_date_of_due = false
            // this.form.payment_method_type_id = null
        },
        changePaymentMethodType(index, isFee = false) {
            if (!isFee) return;
            const row = (isFee ? this.form.fee : this.form.payments)[index];
            const id = row?.payment_method_type_id;
            let payment_method_type = _.find(this.payment_method_types, {
                id: id
            });

            if (!payment_method_type) return;
            if (payment_method_type.collection_kind === 'credit' && Number(payment_method_type.number_days) > 0) {
                this.form.date_of_due = moment(this.form.date_of_issue)
                    .add(payment_method_type.number_days, "days")
                    .format("YYYY-MM-DD");
                // this.form.payments = []
                this.enabled_payments = true;
                this.readonly_date_of_due = true;
                this.form.payment_method_type_id = payment_method_type.id;

                let date = moment(this.form.date_of_issue)
                    .add(payment_method_type.number_days, "days")
                    .format("YYYY-MM-DD");

                // let date = moment()
                //     .add(payment_method_type.number_days, 'days')
                //     .format('YYYY-MM-DD')

                row.date = date;
            } else if (
                payment_method_type.collection_kind === 'credit'
            ) {
                this.form.payment_method_type_id = payment_method_type.id;
                this.form.date_of_due = this.form.date_of_issue;
                // this.form.payments = []
                this.enabled_payments = true;
            } else {
                this.form.date_of_due = this.form.date_of_issue;
                this.readonly_date_of_due = false;
                this.form.payment_method_type_id = null;
                this.enabled_payments = true;
            }
        },
        selectDocumentType() {
            // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
            this.form.document_type_id = "01";
            // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        },
        keyupCustomer() {
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
        clickAddItemInvoice() {
            this.recordItem = null;
            this.showDialogAddItem = true;
        },
        // ######## INICIO CALCULOS EXACTOS SAB ########
        getFormatUnitPriceRow(unit_price, row) {
            const price = exactAmount(unit_price || '0');
            if (this.form.currency_type_id === row.item.currency_type_id) return price.decimal();
            return (this.form.currency_type_id === 'USD' ? price.dividedBy(this.form.exchange_rate_sale) : price.times(this.form.exchange_rate_sale)).decimal();
        },
        discountGlobalPrepayment() {
            return applyPrepaymentDiscount(this.form);
        },
        // ######## FIN CALCULOS EXACTOS SAB ########
        async changeDocumentPrepayment(index) {
            try {
                const selected = this.getPrepayment(index);
                this.$set(this.form.prepayments, index, selectPrepayment(this.form.prepayments[index], selected));
                await this.changeTotalPrepayment();
            } catch (error) { this.$message.error(error.message); }
        },
        clickAddPrepayment() {
            this.form.prepayments.push({
                document_id: null,
                number: null,
                document_type_id: null,
                amount: 0,
                total: 0
            });

            this.changeTotalPrepayment();
        },
        clickRemovePrepayment(index) {
            this.form.prepayments.splice(index, 1);
            this.changeTotalPrepayment();
            if (this.form.prepayments.length == 0)
                this.deletePrepaymentDiscount();
        },
        async changePrepaymentDeduction() {
            this.resetInvoicePrepaymentOptions();
            this.form.prepayments = [];
            this.form.total_prepayment = 0;
            await this.deletePrepaymentDiscount();

            if (this.prepayment_deduction) {
                await this.initialValueATPrepayment();
                await this.changeTotalPrepayment();
                await this.getDocumentsPrepayment();
            } else {
                // this.form.total_prepayment = 0
                // await this.deletePrepaymentDiscount()
                this.cleanValueATPrepayment();
            }
        },
        setPendingAmount() {
            this.form.pending_amount_prepayment = this.form.has_prepayment
                ? this.form.total
                : 0;
        },
        initialValueATPrepayment() {
            this.form.affectation_type_prepayment = !this.form
                .affectation_type_prepayment
                ? 10
                : this.form.affectation_type_prepayment;
        },
        cleanValueATPrepayment() {
            this.form.affectation_type_prepayment = null;
        },
        changeHasPrepayment() {
            if (this.form.has_prepayment) {
                this.initialValueATPrepayment();
            } else {
                this.cleanValueATPrepayment();
            }

            this.setPendingAmount();
        },
        async changeAffectationTypePrepayment() {
            await this.initialValueATPrepayment();

            if (this.prepayment_deduction) {
                this.form.total_prepayment = 0;
                await this.deletePrepaymentDiscount();
                await this.changePrepaymentDeduction();
            }
        },
        async deletePrepaymentDiscount() {
            let discount = await _.find(this.form.discounts, {
                discount_type_id: "04"
            });
            let discount_exonerated = await _.find(this.form.discounts, {
                discount_type_id: "05"
            });
            let discount_unaffected = await _.find(this.form.discounts, {
                discount_type_id: "06"
            });

            let pos = this.form.discounts.indexOf(discount);
            if (pos > -1) {
                this.form.discounts.splice(pos, 1);
                this.changeTotalPrepayment();
            }

            let pos_exonerated = this.form.discounts.indexOf(
                discount_exonerated
            );
            if (pos_exonerated > -1) {
                this.form.discounts.splice(pos_exonerated, 1);
                this.changeTotalPrepayment();
            }

            let pos_unaffected = this.form.discounts.indexOf(
                discount_unaffected
            );
            if (pos_unaffected > -1) {
                this.form.discounts.splice(pos_unaffected, 1);
                this.changeTotalPrepayment();
            }
        },
        changeTotalPrepayment() {
            this.discountGlobalPrepayment();
            this.calculateTotal();
        },
        isActiveBussinessTurn(value) {
            return _.find(this.business_turns, { value: value }) ? true : false;
        },
        visibleDialogReportCustomer() {
            this.report_to_customer_id = this.form.customer_id;
            this.showDialogReportCustomer = true;
        },
        clickAddDocumentHotel() {
            this.showDialogFormHotel = true;
        },
        clickAddDocumentTransport() {
            this.showDialogFormTransport = true;
        },
        addDocumentHotel(hotel) {
            this.form.hotel = hotel;
        },
        addDocumentTransport(transport) {
            this.form.transport = transport;
        },
        changeIsReceivable() {},
        clickAddPayment() {
            const method = defaultCashForCurrency(this.payment_method_types || [], this.form.currency_type_id);
            const id = method?.id || null;
            this.form.date_of_due = moment().format("YYYY-MM-DD");

            this.form.payments.push({
                id: null,
                document_id: null,
                date_of_payment: moment().format("YYYY-MM-DD"),
                payment_method_type_id: id,
                reference: null,
                payment_destination_id: id && this.getPaymentDestinationId() === 'cash' ? 'cash' : null,
                payment: 0,

                payment_received: true,
                filename: null,
                temp_path: null,
                file_list: []
            });

            if (!this.paymentMethodsLoaded) this.pendingDefaultPaymentRows.push(this.form.payments[this.form.payments.length - 1]);
            this.calculatePayments();
        },
        getPaymentDestinationId() {
            if (
                this.config.destination_sale &&
                this.payment_destinations.length > 0
            ) {
                let cash = _.find(this.payment_destinations, { id: "cash" });

                return cash ? cash.id : this.payment_destinations[0].id;
            }

            return null;
        },
        clickCancel(index) {
            if (isRecordedReceipt(this.form.payments[index])) return;
            this.form.payments.splice(index, 1);
            this.calculatePayments();
        },
        async ediItem(row, index) {
            row.indexi = index;
            this.recordItem = row;
            this.showDialogAddItem = true;
        },
        searchRemoteCustomers(input) {
            this.customerSearchTerm = input;

            if (input.length > 0) {
                this.loading_search = true;
                let parameters = `input=${input}&document_type_id=${
                    this.form.document_type_id
                }&operation_type_id=${this.form.operation_type_id}`;

                this.$http
                    .get(`/${this.resource}/search/customers?${parameters}`)
                    .then(response => {
                        this.customers = response.data.customers;
                        this.loading_search = false;
                        this.input_person.number =
                            this.customers.length == 0 ? input : null;

                        /* if (this.customers.length == 0) {
                            this.filterCustomers()
                            this.input_person.number = input
                        } */
                    });
            } else {
                if (!this.shouldProtectPreloadedCustomer()) {
                    this.form.customer_id = null;
                }
                this.filterCustomers();
                this.input_person.number = null;
            }
        },



        initInputPerson() {
            this.input_person = {
                number: null,
                identity_document_type_id: null
            };
        },
        resetForm() {
            this.activePanel = 0;
            this.initForm();
            // this.form.currency_type_id = window.salesDocumentsVes ? 'VES' : (this.currency_types.length > 0)?this.currency_types[0].id:null
            this.form.establishment_id =
                this.establishments.length > 0
                    ? this.establishments[0].id
                    : null;
            this.form.document_type_id =
                this.document_types.length > 0
                    ? this.document_types[0].id
                    : null;
            this.form.operation_type_id =
                this.operation_types.length > 0
                    ? this.operation_types[0].id
                    : null;
            this.form.seller_id = this.sellers.length > 0 ? this.idUser : null;
            this.selectDocumentType();
            this.changeEstablishment();
            this.changeDocumentType();
            this.changeDateOfIssue();
            this.changeCurrencyType();
            // this.changeDestinationSale()
        },
        async changeOperationType() {
            await this.filterCustomers();
            this.calculateAmountToPayments();
            if(this.form.operation_type_id !== "0101") {
                this.form.is_itinerant = false;
            }
        },
        // },
        calculateAmountToPayments() {
            // if(this.form.payments.length > 0){
            //     // this.form.payments[0].payment = this.form.total_pending_payment
            // }
            this.calculatePayments();
            this.calculateFee();
        },
        changeEstablishment() {
            this.establishment = _.find(this.establishments, {
                id: this.form.establishment_id
            });
            this.filterSeries();
            this.selectDefaultCustomer();
        },
        async selectDefaultCustomer() {
            if (this.shouldProtectPreloadedCustomer()) {
                this.ensurePreloadedCustomerInList();
                return;
            }

            if (this.establishment.customer_id) {
                let temp_all_customers = this.all_customers;
                let temp_customers = this.customers;
                await this.$http
                    .get(
                        `/${this.resource}/search/customer/${
                            this.establishment.customer_id
                        }`
                    )
                    .then(response => {
                        let data_customer = response.data.customers;
                        temp_all_customers = temp_all_customers.push(
                            ...data_customer
                        );
                        temp_customers = temp_customers.push(...data_customer);
                    });
                temp_all_customers = this.all_customers.filter(
                    (item, index, self) =>
                        index === self.findIndex(t => sameInvoiceRecordId(t.id, item.id))
                );
                temp_customers = this.customers.filter(
                    (item, index, self) =>
                        index === self.findIndex(t => sameInvoiceRecordId(t.id, item.id))
                );
                this.all_customers = temp_all_customers;
                this.customers = temp_customers;
                await this.filterCustomers();
                // this.form.customer_id = (this.customers.length > 0) ? this.establishment.customer_id : null
                const alt = this.customers.find(customer => sameInvoiceRecordId(customer.id, this.establishment.customer_id));
                // console.log(alt)

                if (alt !== undefined) {
                    this.form.customer_id = alt.id;

                    let seller = this.sellers.find(
                        element => sameInvoiceRecordId(element.id, alt.seller_id)
                    );
                    if (seller !== undefined) {
                        this.form.seller_id = seller.id;
                    }

                    this.setCustomerAccumulatedPoints(
                        alt.id,
                        this.config.enabled_point_system
                    );
                }
            }
        },
        changeDocumentType() {
            this.validateDateOfIssue();
            this.filterSeries();
            if (!this.shouldProtectPreloadedCustomer()) {
                this.cleanCustomer();
            }
            this.filterCustomers();
            this.setDefaultSerieByDocument();
        },
        shouldProtectPreloadedCustomer() {
            return Boolean(this.preloadedCustomerId);
        },
        ensurePreloadedCustomerInList() {
            if (!this.shouldProtectPreloadedCustomer()) {
                return;
            }

            const customerId = this.preloadedCustomerId;
            const matchesCustomer = customer => sameInvoiceRecordId(customer.id, customerId);
            const selectedCustomer = this.customers.find(matchesCustomer);
            const catalogCustomer = this.all_customers.find(matchesCustomer);
            const existingCustomer =
                selectedCustomer || catalogCustomer || this.preloadedCustomer;

            if (!existingCustomer) {
                return;
            }

            if (!catalogCustomer) {
                this.all_customers.push(existingCustomer);
            }

            if (!selectedCustomer) {
                this.customers.push(existingCustomer);
            }

            this.form.customer_id = existingCustomer.id;
        },
        cleanCustomer() {
            if (this.shouldProtectPreloadedCustomer()) {
                return;
            }

            this.form.customer_id = null;
        },
        setDefaultSerieByDocument() {
            if (!this.authUser || !this.authUser.multiple_default_document_types)
                return;

            const default_document_type_serie = _.find(
                this.authUser.default_document_types,
                { document_type_id: this.form.document_type_id }
            );

            if (!default_document_type_serie || !Array.isArray(this.series))
                return;

            const exist_serie = _.find(this.series, {
                id: default_document_type_serie.series_id
            });

            if (exist_serie) {
                this.form.series_id = default_document_type_serie.series_id;
            }
        },
        dateValidError() {
            this.$message.error(
                `No puede seleccionar una fecha menor a ${
                    this.configuration.shipping_time_days
                } día(s).`
            );
            // this.$message.error('No puede seleccionar una fecha menor a 6 días.');
            this.dateValid = false;
        },
        validateDateOfIssue() {
            let minDate = moment().subtract(
                this.configuration.shipping_time_days,
                "days"
            );
            // let minDate = moment().subtract(7, 'days')

            // validar fecha de factura sin considerar configuracion
            if (
                moment(this.form.date_of_issue) < minDate &&
                this.form.document_type_id === "01"
            ) {
                this.dateValidError();
            } else if (
                moment(this.form.date_of_issue) < minDate &&
                this.config.restrict_receipt_date
            ) {
                this.dateValidError();
            } else {
                this.dateValid = true;
            }
        },
        async changeDateOfIssue() {
            this.validateDateOfIssue();

            if (!this.form.quotation_id && !this.isUpdateDocument) {
                this.form.date_of_due = this.form.date_of_issue;
            }
            if (!this.isUpdateDocument) {
                // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
                const request = documentRateRequest(this);
                await this.searchExchangeRateByDate(request.date).then(response => {
                    if (mayApplyDocumentRate(this, request) && !this.isUpdateDocument) {
                        this.form.exchange_rate_sale = response;
                    }
                });
                // ######## FIN MONEDA DEL MEDIO DE COBRO ########
            }
            await this.getPercentageIgv();
            // this.changeCurrencyType(); //
            // }
        },
        assignmentDateOfPayment() {
            this.form.payments.forEach(payment => {
                payment.date_of_payment = this.form.date_of_issue;
            });
        },
        filterSeries(preserveSeriesId = null) {
            // console.log('filterSeries');
            if (!preserveSeriesId) {
                this.form.series_id = null;
            }
            let series = this.all_series.filter(s =>
                String(s.establishment_id) === String(this.form.establishment_id) &&
                String(s.document_type_id) === String(this.form.document_type_id) &&
                Boolean(s.contingency) === Boolean(this.is_contingency)
            );
            if (
                this.form.document_type_id === this.config.user.document_id &&
                this.typeUser == "seller"
            ) {
                // Se filtra si el documento es el mismo que el establecido para el usuario.
                series = series.filter(s => String(s.id) === String(this.config.user.serie));
            }

            //console.log(series);

            this.$store.commit("setSeries", series);
            if (preserveSeriesId) {
                this.form.series_id = preserveSeriesId;
            } else {
                this.form.series_id =
                    this.series.length > 0 ? this.series[0].id : null;
            }
        },
        async filterCustomers() {
            // ######## INICIO POLITICA IDENTIDAD ACTIVA EN VENTAS ########
            if (this.isExportInvoice) {
                const operation = this.form.operation_type_id;
                const response = await this.$http.get(`/${this.resource}/search/customers`, {params: {input: '', document_type_id: '01', operation_type_id: operation}});
                if (this.form.operation_type_id !== operation) return;
                this.customers = response.data.customers;
            } else {
                this.customers = this.all_customers.filter(customer => customer.identity_document_type_id !== 'ND');
            }
            // ######## FIN POLITICA IDENTIDAD ACTIVA EN VENTAS ########

            this.ensurePreloadedCustomerInList();
        },
        clickAddInitGuides() {
            this.form.guides.push(
                {
                    document_type_id: "09",
                    number: null
                }
            );
        },
        clickAddGuide() {
            this.form.guides.push({
                document_type_id: null,
                number: null
            });
        },
        clickRemoveGuide(index) {
            this.form.guides.splice(index, 1);
        },
        async addRow(row) {
            let enable_barcode_quick_sale = false;
            if (
                this.$refs.item_search_quick_sale &&
                this.$refs.item_search_quick_sale.search_item_by_barcode
            ) {
                enable_barcode_quick_sale = this.$refs.item_search_quick_sale
                    .search_item_by_barcode;
            }
            let enable_search_on_enter = false;
            if (
                this.$refs.item_search_quick_sale &&
                this.$refs.item_search_quick_sale.searchOnEnter
            ) {
                enable_search_on_enter = this.$refs.item_search_quick_sale
                    .searchOnEnter;
            }
            // console.log('enable_barcode', this.$refs.item_search_quick_sale.search_item_by_barcode);
            if (this.recordItem) {
                //this.form.items.$set(this.recordItem.indexi, row)
                if (this.recordItem._from_delivery_order) row._from_delivery_order = true;
                this.form.items[this.recordItem.indexi] = row;
                this.recordItem = null;

                if (this.config.enabled_point_system) {
                    this.setTotalExchangePoints();
                    this.recalculateUsedPointsForExchange(row);
                }
            } else if (this.shouldUnifyAmountItems(row)) {
                const index = this.getUnifiedItemIndex(row);

                if (index !== -1) {
                    const newItem = JSON.parse(
                        JSON.stringify(this.form.items[index])
                    );

                    newItem.quantity =
                        exactAmount(newItem.quantity).plus(row.quantity).decimal();

                    const unifiedRow = await calculateRowItem(
                        newItem,
                        this.form.currency_type_id,
                        this.form.exchange_rate_sale,
                        this.percentage_igv, this.isExportInvoice
                    );

                    this.form.items[index] = unifiedRow;
                } else {
                    this.form.items.push(JSON.parse(JSON.stringify(row)));
                }
            } else if (enable_barcode_quick_sale || enable_search_on_enter) {
                let index = canMergeRowQuantity(row) ? this.getUnifiedItemIndex(row) : -1;
                if (index !== -1) {
                    let newItem = JSON.parse(JSON.stringify(this.form.items[index]));
                    newItem.quantity = exactAmount(newItem.quantity).plus(row.quantity).decimal();
                    let newRow = await calculateRowItem(
                        newItem,
                        this.form.currency_type_id,
                        this.form.exchange_rate_sale,
                        this.percentage_igv, this.isExportInvoice
                    );
                    this.form.items[index] = newRow;
                } else {
                    this.form.items.push(JSON.parse(JSON.stringify(row)));
                }
            } else {
                this.form.items.push(JSON.parse(JSON.stringify(row)));
            }

            await this.calculateTotal();
        },
        shouldUnifyAmountItems(row) {
            return (
                this.config &&
                this.config.show_unify_amount_items &&
                canMergeRowQuantity(row) &&
                row.item &&
                !row.item.series_enabled &&
                !row.item.lots_enabled
            );
        },
        getUnifiedItemIndex(row) {
            // Conversion rows already carry the grouping and provenance from the server.
            if (row.source_sale_note_items?.length) return -1;
            const presentation = row.item?.presentation;
            const factor = source => source?.quantity_unit_exact ??
                source?.calculation_snapshot?.values?.quantity_unit ?? source?.quantity_unit ?? '1';
            const samePresentation = item => {
                const current = item.item?.presentation;
                if (String(current?.id ?? '') !== String(presentation?.id ?? '')) return false;
                try {
                    return exactAmount(factor(current)).compare(factor(presentation)) === 0;
                } catch (_) {
                    return false;
                }
            };

            return this.form.items.findIndex(item =>
                !item.source_sale_note_items?.length &&
                canMergeRowQuantity(item) &&
                canMergeRowPrice(item, row) &&
                String(item.item_id) === String(row.item_id) &&
                samePresentation(item) &&
                String(item.affectation_igv_type_id || "") ===
                    String(row.affectation_igv_type_id || "") &&
                String(item.unit_price || "") ===
                    String(row.unit_price || "") &&
                String(item.warehouse_id || "") ===
                    String(row.warehouse_id || "")
            );
        },
        clickRemoveItem(index) {
            this.form.items.splice(index, 1);
            this.calculateTotal();

            if (this.config.enabled_point_system) this.setTotalExchangePoints();
        },
        // ######## INICIO RECALCULO REACTIVO DE TASA ########
        recalculateItemsForExchangeRate(value) {
            let rate;
            try { rate = normalizeExchangeRate(value); } catch (_) { return; }
            if (!this.form.items?.length) return;
            if (this.enabled_discount_global || this.recordDiscountsGlobal) this.clearGlobalDistributionDiscounts();
            this.form.items = this.form.items.map(row => calculateRowItem(row, this.form.currency_type_id, rate, this.percentage_igv, this.isExportInvoice));
            this.calculateTotal();
        },
        // ######## FIN RECALCULO REACTIVO DE TASA ########
        changeCurrencyType(previousCurrency = this.form.currency_type_id) {
            const discount = this.invoiceGlobalDiscountExact(previousCurrency);
            const charge = this.invoiceGlobalChargeExact(previousCurrency);
            if (this.enabled_discount_global || this.recordDiscountsGlobal) this.clearGlobalDistributionDiscounts();
            this.currency_type = _.find(this.currency_types, {
                id: this.form.currency_type_id
            });
            let items = [];
            this.form.items.forEach(row => {
                if (previousCurrency!==this.form.currency_type_id) row=this.convertInvoiceLineDiscounts(row,previousCurrency);
                items.push(
                    calculateRowItem(
                        row,
                        this.form.currency_type_id,
                        this.form.exchange_rate_sale,
                        this.percentage_igv, this.isExportInvoice
                    )
                );
            });
            this.form.items = items;

            if (previousCurrency!==this.form.currency_type_id) {
                const rate=exactAmount(normalizeExchangeRate(this.form.exchange_rate_sale));
                const convert=amount=>this.form.currency_type_id==='VES' ? amount.times(rate) : amount.dividedBy(rate);
                this.form.charges=(this.form.charges || []).map(row=>{
                    const amount=convert(exactAmount(row.amount_exact ?? row.amount ?? 0));
                    return {...row,amount:amount.decimal(),amount_exact:amount.fraction()};
                });
                if (this.isExportInvoice || !this.config.active_allowance_charge) this.setInvoiceGlobalCharge(convert(charge));
            }

            if (this.is_amount) {
                const currency=this.form.currency_type_id;
                if (!['VES','USD'].includes(previousCurrency) || !['VES','USD'].includes(currency)) throw new Error('Seleccione una moneda válida.');
                const rate=previousCurrency===currency ? null : exactAmount(normalizeExchangeRate(this.form.exchange_rate_sale));
                this.setInvoiceGlobalDiscount(previousCurrency===currency ? discount
                    : currency==='VES' ? discount.times(rate) : discount.dividedBy(rate));
            } else {
                this.setInvoiceGlobalDiscount(discount);
            }
            return this.calculateTotal();
        },
        // ######## INICIO CALCULOS EXACTOS SAB ########
        hasPositiveInvoiceAmount(field) {
            return exactValue(this.form, field).compare(0) > 0;
        },
        calculateTotal() {
            if (this.invoiceDiscountRestoreError) { this.invoiceCalculationError=this.invoiceDiscountRestoreError;return false; }
            this.invoiceCalculationError=null;
            if (this.enabled_discount_global || this.recordDiscountsGlobal) this.clearGlobalDistributionDiscounts();
            Object.assign(this.form, documentTotals(this.form.items, this.form.discounts, this.form.charges, this.form.total_prepayment, this.form.prepayments));
            this.subtotal_before_global_discount = this.form.total;
            this.total_discount_no_base = '0';
            if ((this.enabled_discount_global || this.recordDiscountsGlobal) && this.discountGlobalItems(this.form)===false) {
                this.invoiceCalculationError='Revise el descuento global: supera la base actual de la factura.';
                return false;
            }
            if (this.prepayment_deduction) this.discountGlobalPrepayment();
            if (this.isExportInvoice) this.applyExportGlobalCharge();
            else this.chargeGlobal();
            Object.assign(this.form, documentTotals(this.form.items, this.form.discounts, this.form.charges, this.form.total_prepayment, this.form.prepayments));
            this.setTotalDefaultPayment();
            this.setPendingAmount();
            this.calculateFee();
            this.setTotalPointsBySale(this.config);
            return true;
        },
        // ######## FIN CALCULOS EXACTOS SAB ########
        recalculateDecimalTotalTaxed(total, igv) {
            return exactAmount(total).minus(igv).decimal();
        },
        verifyRecalculateTotalTaxed() {
            const keysToCheck = [


                "total_discount",
                "total_exportation",
                "total_exonerated",



            ];
            return !keysToCheck.some(key => this.form[key] > 0);
        },
        sumDiscountsNoBaseByItem(row) {
            return exactSum((row.discounts || []).filter(d=>d.discount_type_id === '01').map(d=>d.amount_exact ?? d.amount ?? '0')).decimal();
        },
        setTotalDefaultPayment() {
            // if (this.form.payments.length > 0) {

            //     this.form.payments[0].payment = this.form.total
            // }
            this.calculatePayments();
        },
        // ######## INICIO CALCULOS EXACTOS SAB ########
        applyExportGlobalCharge() {
            const amount = this.invoiceGlobalChargeExact();
            const base = exactValue(documentTotals(this.form.items, this.form.discounts), 'total_value');
            const charges = this.form.charges.length ? this.form.charges : [{charge_type_id: '50', description: 'Cargos globales', amount: '1'}];
            const weights = charges.map(row => exactAmount(row.amount_exact ?? row.amount ?? '0'));
            const sum = exactSum(weights);
            this.form.charges = amount.compare(0) > 0 ? charges.map((charge, index) => {
                const share = amount.times(sum.compare(0) > 0 ? weights[index] : 1).dividedBy(sum.compare(0) > 0 ? sum : charges.length);
                return {...charge, amount: share.decimal(), amount_exact: share.fraction(), base: base.decimal(), factor: base.compare(0) > 0 ? share.dividedBy(base).decimal() : '0'};
            }) : [];
        },
        chargeGlobal() {
            const others = this.form.charges.filter(row => row.charge_type_id !== '50');
            const base = exactValue(documentTotals(this.form.items, this.form.discounts, others, this.form.total_prepayment, this.form.prepayments), 'total');
            const amount = this.config.active_allowance_charge ? base.times(this.invoiceAllowancePercent ?? this.config.percentage_allowance_charge ?? '0').dividedBy(100) : this.invoiceGlobalChargeExact();
            this.setInvoiceGlobalCharge(amount);
            this.form.charges = others;
            if (amount.compare(0) > 0) this.form.charges.push({charge_type_id: '50', description: 'Cargos globales que no afectan la base imponible del IVA',
                base: base.decimal(), amount: amount.decimal(), amount_exact: amount.fraction(), factor: base.compare(0) > 0 ? amount.dividedBy(base).decimal() : '0'});
        },
        chargeConsumptionSurcharge() {
            this.form.charges = this.form.charges.filter(row => row.charge_type_id !== '46');
            const base = exactValue(documentTotals(this.form.items, this.form.discounts, this.form.charges, this.form.total_prepayment, this.form.prepayments), 'total');
            const amount = this.is_consumption_charge ? base.times(this.restaurant_tip_factor || '0').dividedBy(100) : exactAmount(0);
            this.total_consumption_charge = amount.decimal();
            if (amount.compare(0) > 0) this.form.charges.push({charge_type_id: '46', description: 'Recargo al consumo y/o propinas',
                base: base.decimal(), amount: amount.decimal(), amount_exact: amount.fraction(), factor: exactAmount(this.restaurant_tip_factor).dividedBy(100).decimal()});
            this.calculateTotal();
        },
        // ######## FIN CALCULOS EXACTOS SAB ########
        deleteChargeGlobal() {
            let charge = _.find(this.form.charges, { charge_type_id: "50" });
            let index = this.form.charges.indexOf(charge);

            if (index > -1) {
                this.form.charges.splice(index, 1);
                this.form.total_charge = 0;
            }
        },
        changeTypeDiscount() {
            this.calculateTotal();
        },
        changeTotalGlobalDiscount() {
            this.calculateTotal();
        },
        invoiceInputKey(row) {
            const key=Symbol.for('sab.invoice.input.identity');
            if (!row[key]) row[key]=Symbol('invoice-line');
            return row[key];
        },
        currentInvoiceDecimal(kind,index) {
            if (kind==='row') return this.rowDiscountValue(this.form.items[index]);
            return String(kind==='allowance' ? this.invoiceAllowancePercent : kind==='discount' ? this.total_global_discount : this.total_global_charge);
        },
        invoiceGlobalDiscountExact(currency = this.form.currency_type_id) {
            const source=this.invoiceGlobalDiscountSource;
            return exactAmount(source && source.currency===currency && source.projection===String(this.total_global_discount)
                ? source.amount : this.total_global_discount || '0');
        },
        convertInvoiceLineDiscounts(row,previousCurrency) {
            if (!['VES','USD'].includes(previousCurrency) || !['VES','USD'].includes(this.form.currency_type_id)) throw new Error('Seleccione una moneda válida.');
            const rate=exactAmount(normalizeExchangeRate(this.form.exchange_rate_sale));
            const discounts=(row.discounts || []).map(discount=>{
                if (!discount.is_amount) return discount;
                const amount=exactAmount(discount.amount_exact ?? discount.amount_without_rounded
                    ?? (discount.use_input_amount===false ? discount.percentage : discount.amount ?? discount.percentage) ?? 0);
                const converted=this.form.currency_type_id==='VES' ? amount.times(rate) : amount.dividedBy(rate);
                return {...discount,amount:converted.decimal(),amount_exact:converted.fraction(),use_input_amount:true,
                    ...(discount.amount_without_rounded!=null ? {amount_without_rounded:converted.decimal()} : {})};
            });
            return {...row,discounts};
        },
        setInvoiceGlobalDiscount(amount) {
            this.total_global_discount=amount.decimal();
            this.invoiceGlobalDiscountSource={currency:this.form.currency_type_id,projection:this.total_global_discount,amount:amount.fraction()};
        },
        invoiceGlobalChargeExact(currency = this.form.currency_type_id) {
            const source=this.invoiceGlobalChargeSource;
            return exactAmount(source && source.currency===currency && source.projection===String(this.total_global_charge)
                ? source.amount : this.total_global_charge || '0');
        },
        setInvoiceGlobalCharge(amount) {
            this.total_global_charge=amount.decimal();
            this.invoiceGlobalChargeSource={currency:this.form.currency_type_id,projection:this.total_global_charge,amount:amount.fraction()};
        },
        applyInvoiceDecimal(kind, value, index = null, input = null) {
            if (input && !this._invoiceAdjustmentsCommitting) {
                return this.commitPendingInvoiceAdjustments() ? this.currentInvoiceDecimal(kind,index) : false;
            }
            const restore=captureInvoiceEdit(this);
            try {
                if (kind==='row') {
                    if (this.applyItemDiscount(index,value)===false) { restore();return false; }
                    return this.currentInvoiceDecimal(kind,index);
                }
                if (kind==='allowance') this.invoiceAllowancePercent=value;
                else if (kind==='discount') { this.total_global_discount=value;this.invoiceGlobalDiscountSource=null;this.invoiceDiscountRestoreError=null; }
                else if (kind==='charge') { this.total_global_charge=value;this.invoiceGlobalChargeSource=null; }
                else throw new Error('Ajuste de factura inválido.');
                if (this.calculateTotal()===false) { restore();return false; }
                return this.currentInvoiceDecimal(kind,index);
            } catch (error) {
                restore();this.$message.error(error.message);return false;
            }
        },
        commitPendingInvoiceAdjustments() {
            if (this._invoiceAdjustmentsCommitting) return true;
            const refs=this.$refs || {};
            const controls=[];
            for (const name of ['invoiceAllowanceInput','invoiceDiscountInput','invoiceChargeInput','invoiceRowDiscountInputs']) {
                const inputs=Array.isArray(refs[name]) ? refs[name] : [refs[name]];
                for (const input of inputs) if (input) controls.push(input);
            }
            if (!controls.length) return true;
            for (const input of controls) if (!input.validatePending().valid) return false;
            const restore=captureInvoiceEdit(this);
            const drafts=controls.map(input=>({input,draft:input.draft,dirty:input.dirty,error:input.error}));
            this._invoiceAdjustmentsCommitting=true;
            try {
                for (const input of controls) if (!input.commitPending()) {
                    const error=input.error;restore();
                    for (const state of drafts) Object.assign(state.input,{draft:state.draft,dirty:state.dirty,error:state.error});
                    input.error=error;return false;
                }
                return true;
            } catch (error) {
                restore();
                for (const state of drafts) Object.assign(state.input,{draft:state.draft,dirty:state.dirty,error:state.error});
                this.$message.error(error.message);return false;
            } finally { this._invoiceAdjustmentsCommitting=false; }
        },
        changeInvoiceCurrencyType(currency) {
            if (!this.commitPendingInvoiceAdjustments()) return false;
            const restore=captureInvoiceEdit(this);
            try {
                const previousCurrency=this.form.currency_type_id;
                if (this.prepayment_deduction && previousCurrency !== currency) this.clearInvoicePrepaymentRows();
                if (this.enabled_discount_global || this.recordDiscountsGlobal) this.clearGlobalDistributionDiscounts();
                this.form.currency_type_id=currency;
                if (this.changeCurrencyType(previousCurrency)===false) { restore();return false; }
                return true;
            } catch (error) { restore();this.$message.error(error.message);return false; }
        },
        changeInvoiceDiscountMode(mode) {
            if (!this.commitPendingInvoiceAdjustments()) return false;
            const restore=captureInvoiceEdit(this);
            try {
                this.is_amount=mode;
                this.invoiceGlobalDiscountSource=null;
                if (this.calculateTotal()===false) { restore();return false; }
                return true;
            } catch (error) { restore();this.$message.error(error.message);return false; }
        },
        deleteDiscountGlobal() {
            let discount = _.find(this.form.discounts, {
                discount_type_id: this.isExportInvoice && this.recordDiscountsGlobal ? this.recordDiscountsGlobal.discount_type_id : this.configuration.global_discount_type_id
            });
            // let discount = _.find(this.form.discounts, {'discount_type_id': '03'})
            let index = this.form.discounts.indexOf(discount);

            if (index > -1) {
                this.form.discounts.splice(index, 1);
                if (this.form.total_discount_item > 0 ) {
                    this.form.total_discount = this.form.total_discount_item;

                } else {
                    this.form.total_discount = 0;
                }
            }
        },
        setConfigGlobalDiscountType() {
            this.global_discount_type = _.find(this.global_discount_types, {
                id: this.configuration.global_discount_type_id
            });
        },
        setGlobalDiscount(factor, amount, base, amount_without_rounded, percentage = null) {
            this.form.discounts.push({
                discount_type_id: this.recordDiscountsGlobal ? this.recordDiscountsGlobal.discount_type_id : this.global_discount_type.id,
                description: this.recordDiscountsGlobal ? this.recordDiscountsGlobal.description : this.global_discount_type.description,
                factor: factor,
                amount: amount,
                base: base,
                is_amount: this.is_amount,
                percentage: percentage,
                amount_without_rounded: amount_without_rounded,
                amount_exact: exactAmount(amount_without_rounded ?? amount).fraction()
            });
        },
        //Parametro ctx, mantiene los valores sin redondeo
        clearGlobalDistributionDiscounts() {
            this.form.items.forEach((item, index) => {
                if (!item.discounts || item.discounts.length === 0) {
                    if (item._original_before_global_discount) {
                        this.restoreItemFromGlobalDiscountSnapshot(item);
                    }
                    return;
                }

                const beforeLen = item.discounts.length;
                item.discounts = item.discounts.filter(
                    d => !d.from_global_distribution
                );

                const removed = beforeLen !== item.discounts.length;
                if (!removed && !item._original_before_global_discount) return;

                if (item._original_before_global_discount) {
                    // El snapshot ya refleja descuentos de ítem previos al global
                    this.restoreItemFromGlobalDiscountSnapshot(item);
                } else if (removed) {
                    this.form.items.splice(
                        index,
                        1,
                        calculateRowItem(
                            item,
                            this.form.currency_type_id,
                            this.form.exchange_rate_sale,
                            this.percentage_igv, this.isExportInvoice
                        )
                    );
                }
            });
        },
        restoreItemFromGlobalDiscountSnapshot(item) {
            const original = item._original_before_global_discount;
            if (!original) return;
            Object.assign(item, original);
            delete item._original_before_global_discount;
        },
        /**
         * Afectaciones que participan del descuento global.
         *
         * Quedan fuera las gratuitas (11-16 bonificaciones, 21 exonerada
         * gratuita, 37 inafecta gratuita): su valor es referencial, no integra
         * el total a pagar y SUNAT no admite descuento sobre ellas. Si se
         * incluyeran, absorberian parte del descuento y este se perderia:
         * la suma de descuentos por linea no cuadraria con el descuento global.
         */
        isDiscountableAffectation(item) {
            return [
                "10", // Gravada - Operacion Onerosa
                "20", // Exonerada - Operacion Onerosa
                "30", // Inafecta - Operacion Onerosa
                "31", // Inafecta - Retiro por Bonificacion
                "32", // Inafecta - Retiro
                "33", // Inafecta - Retiro por Muestras Medicas
                "34", // Inafecta - Retiro por Convenio Colectivo
                "35", // Inafecta - Retiro por Premio
                "36", // Inafecta - Retiro por Publicidad
                "EXP"  // Exportacion
            ].includes(item.affectation_igv_type_id);
        },
        /**
         * Valor de la linea sobre el que se calcula un descuento.
         */
        itemValueForDiscount(item) { return exactValue(item, 'total_value').decimal(); },
        itemDiscountExactAmount(item, predicate) {
            const multiplier = item.affectation_igv_type_id === '10' ? exactAmount(1).plus(this.percentage_igv) : exactAmount(1);
            return exactSum((item.discounts || []).filter(discount => !predicate || predicate(discount)).map(discount => {
                const amount = exactAmount(discount.amount_exact ?? discount.amount_without_rounded ?? discount.amount ?? '0');
                return this.isBaseDiscount(discount) ? amount.times(multiplier) : amount;
            }));
        },
        itemDiscountDisplayAmount(item, predicate) { return this.itemDiscountExactAmount(item, predicate).decimal(); },
        isBaseDiscount(discount) {
            if (discount.discount_type) return !!discount.discount_type.base;

            return discount.discount_type_id === "00";
        },
        /**
         * Reparte `target` entre los descuentos propios de UNA linea: los escala
         * a todos por el mismo factor, o crea uno si la linea no tenia ninguno.
         *
         * El prorrateo del descuento global queda intacto: no entra en la suma
         * ni se escala, porque lo regenera calculateTotal en cada recalculo.
         */
        spreadItemDiscount(item, target) {
            item.discounts = item.discounts || [];
            target = exactAmount(target);
            const own = discount => !discount.from_global_distribution;
            const current = this.itemDiscountExactAmount(item, own);
            if (target.compare(0) <= 0) { item.discounts = item.discounts.filter(discount => !own(discount)); return; }
            if (current.compare(0) > 0) {
                const factor = target.dividedBy(current);
                item.discounts.forEach(discount => {
                    if (!own(discount)) return;
                    const amount = exactAmount(discount.amount_exact ?? discount.amount_without_rounded ?? discount.amount).times(factor);
                    Object.assign(discount, {amount: amount.decimal(), amount_exact: amount.fraction(), amount_without_rounded: amount.decimal(), is_amount: true, use_input_amount: true});
                });
                return;
            }
            const base = exactValue(item, 'total_value');
            if (base.compare(0) <= 0) return;
            const amount = item.affectation_igv_type_id === '10' ? target.dividedBy(exactAmount(1).plus(this.percentage_igv)) : target;
            item.discounts.push({discount_type_id: '00', discount_type: _.find(this.discount_types, {id: '00'}), description: 'Descuento',
                percentage: '0', factor: '0', amount: amount.decimal(), amount_exact: amount.fraction(), amount_without_rounded: amount.decimal(), base: base.decimal(), is_amount: true, use_input_amount: true});
        },
        recalculateDiscountedItems(indexes) {
            indexes.forEach(index => {
                const item = this.form.items[index];
                if (!item) return;

                const row = calculateRowItem(
                    item,
                    this.form.currency_type_id,
                    this.form.exchange_rate_sale,
                    this.percentage_igv, this.isExportInvoice
                );

                // Object.assign conserva los campos propios de la fila que
                this.form.items.splice(index, 1, Object.assign({}, item, row));
            });
        },
        /**
         * hasRowAdvancedOption bloquea la fila cuando ya tiene descuentos, asi
         * que en la celda de descuento dejaria el input inutilizable apenas se
         * pone el primero. Aqui solo bloquean cargos e ISC, que si tienen
         * calculo propio y no se llevan con el escalado de descuentos.
         */
        isRowDiscountLocked(row) {
            const has_charges =
                row.charges &&
                Array.isArray(row.charges) &&
                row.charges.length > 0;

            return !!(has_charges || (row.item && 0));
        },
        /**
         * Descuento vigente de una fila, redondeado, para pintar el input.
         */
        rowDiscountValue(row) { return this.itemDiscountDisplayAmount(row); },
        applyItemDiscount(index, value) {
            let item = this.form.items[index];
            if (!item) return;
            const global = this.itemDiscountExactAmount(item, discount => discount.from_global_distribution);
            let target = exactAmount(value || '0').minus(global);
            if (target.compare(0) < 0) target = exactAmount(0);
            const maximum = exactValue(item, 'total').plus(this.itemDiscountExactAmount(item, discount => !discount.from_global_distribution));
            if (target.compare(maximum) > 0) { target = maximum; this.$message.warning(`El descuento no puede superar ${maximum.decimal()}`); }
            this.clearGlobalDistributionDiscounts();
            item = this.form.items[index];
            this.spreadItemDiscount(item, target);
            this.recalculateDiscountedItems([index]);
            return this.calculateTotal();
        },
        // ######## INICIO CALCULOS EXACTOS SAB ########
        discountGlobalItems(ctx) {
            if (this.isExportInvoice) {
                this.deleteDiscountGlobal();
                const base = this.form.items ? exactSum(this.form.items.filter(item => item.affectation_igv_type_id === 'EXP').map(item => exactValue(item, 'total_value'))) : exactValue(ctx, 'total_exportation');
                const discount = this.invoiceGlobalDiscountExact();
                const amount = this.is_amount ? discount : base.times(discount).dividedBy(100);
                if (amount.compare(0) < 0 || amount.compare(base) > 0) { this.$message.error('El descuento supera el valor exportado.'); return false; }
                if (amount.compare(0) > 0 && base.compare(0) > 0) {
                    const percentage = discount.decimal();
                    this.setGlobalDiscount(amount.dividedBy(base).decimal(), amount.decimal(), base.decimal(), amount.fraction(),
                        !this.is_amount && discount.compare(percentage) === 0 ? percentage : null);
                }
                const total = base.minus(amount).decimal();
                Object.assign(this.form, {total_exportation: total, total_value: total, total, subtotal: total,
                    total_discount: exactAmount(this.form.total_discount || '0').plus(amount).decimal()});
                return;
            }
            this.clearGlobalDistributionDiscounts();
            if (this.invoiceGlobalDiscountExact().compare(0) <= 0) return;
            const selected = this.form.items.filter(item => this.isDiscountableAffectation(item));
            const base = exactSum(selected.map(item => exactValue(item, 'total_value')));
            if (base.compare(0) <= 0) { this.$message.error('No existe una base positiva para el descuento global.');return false; }
            const type = this.recordDiscountsGlobal?.discount_type_id || this.global_discount_type.id;
            const taxed = exactSum(selected.filter(item => item.affectation_igv_type_id === '10').map(item => exactValue(item, 'total_value')));
            let amount = this.invoiceGlobalDiscountExact();
            if (!this.is_amount) amount = base.times(amount).dividedBy(100);
            else if (type === '02' && this.configuration.exact_discount) amount = amount.dividedBy(exactAmount(1).plus(exactAmount(this.percentage_igv).times(taxed).dividedBy(base)));
            if (amount.compare(base) > 0) { this.$message.error('El descuento supera su base.'); return false; }
            this.deleteDiscountGlobal();
            selected.forEach(item => {
                const share = exactValue(item, 'total_value').times(amount).dividedBy(base);
                const snapshot = {...item, discounts: [...(item.discounts || [])]};
                item.discounts = [...snapshot.discounts, {discount_type_id: type === '02' ? '00' : '01',
                    description: 'Descuento global', is_amount: true, use_input_amount: true,
                    from_global_distribution: true, amount: share.decimal(), amount_exact: share.fraction()}];
                Object.assign(item, calculateRowItem(item, this.form.currency_type_id, this.form.exchange_rate_sale, this.percentage_igv, this.isExportInvoice));
                item._original_before_global_discount = snapshot;
            });
        },
        // ######## FIN CALCULOS EXACTOS SAB ########
        recalcItemBasesAndIgv(item) {
            Object.assign(item, calculateRowItem(item, this.form.currency_type_id, this.form.exchange_rate_sale, this.percentage_igv, this.isExportInvoice));
            return item;
        },
        // Descuento por item (incluye prorrateo del descuento global)
        setTextDiscountItem(item) { return this.itemDiscountDisplayAmount(item); },

        async deleteInitGuides() {
            await _.remove(this.form.guides, { number: null });
        },
        async asignPlateNumberToItems() {
            if (this.form.plate_number) {
                await this.form.items.forEach(item => {
                    let at = _.find(item.attributes, {
                        attribute_type_id: "5010"
                    });

                    if (!at) {
                        item.attributes.push({
                            attribute_type_id: "5010",
                            description: "Numero de Placa",
                            value: this.form.plate_number,
                            start_date: null,
                            end_date: null,
                            duration: null
                        });
                    } else {
                        if (this.isUpdate) {
                            at.value = this.form.plate_number;
                        }
                    }
                });
            }
        },
        async validateAffectationTypePrepayment() {
            let not_equal_affectation_type = 0;

            await this.form.items.forEach(item => {
                if (
                    item.affectation_igv_type_id !=
                    this.form.affectation_type_prepayment
                ) {
                    not_equal_affectation_type++;
                }
            });

            return {
                success: not_equal_affectation_type > 0 ? false : true,
                message:
                    "Los items deben tener tipo de afectación igual al seleccionado en el anticipo"
            };
        },
        validatePaymentDestination() {
            let error_by_item = 0;

            this.activeInvoicePayments.forEach(item => {
                // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
                const method = (this.payment_method_types || []).find(value => String(value.id) === String(item.payment_method_type_id));
                const credit = method?.collection_kind === 'credit';
                if (!item.id && !credit && item.payment_destination_id == null) error_by_item++;
                // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
            });

            return {
                error_by_item: error_by_item
            };
        },
        documentSubmission() {
            const form = paymentConditionPayload(withoutDraftRetentions(this.form));
            return this.isUpdateDocument ? {...form, payments: [], received_retentions: [], guarantee_fund: null} : {...form, payments: this.activeInvoicePayments};
        },
        async submit() {
            if (this.loading_submit) return;
            if (this.editBlocked) return this.$message.error(this.form.edit_block_reason);
            if (this.form.fiscal_data?.special_tax_regime_id && (!this.specialTaxRegimeAvailable || this.form.payment_condition_id !== '01')) {
                return this.$message.error('El régimen especial requiere una Factura de venta interna al contado.');
            }
            if (this.thirdPartyMode && (!this.form.third_party_id || !this.thirdPartyAvailable || this.form.payment_condition_id !== '01')) {
                return this.$message.error('Seleccione el tercero y mantenga una Factura de venta interna al contado.');
            }
            if (!this.commitPendingInvoiceAdjustments()) return;
            if (this.invoiceCalculationError) return this.$message.error(this.invoiceCalculationError);
            if (!this.thirdPartyMode) this.form.third_party_id = null;
            let customer = _.find(this.customers, {
                id: this.form.customer_id
            });

            if (customer) {

            }

            // Normalizar IdLoteSelected en cada ítem antes de validar y enviar
            if (this.ensureItemsLotsForSubmit() === false) return false;

            // Validando lotes (origen) — misma regla que DocumentRequest / lots_group.vue
            const lotsValidation = validateItemsLots(this.form.items);
            if (!lotsValidation.valid) {
                this.$message.error(lotsValidation.message);
                return false;
            }

            //Validando las series seleccionadas
            const errorSeries = !invoiceSeriesValid(this.form.items);

            // Validar campos personalizados requeridos
            if (this.$refs.customFieldsRenderer) {
                const customFieldsValidation = this.$refs.customFieldsRenderer.validateRequiredFields()
                if (!customFieldsValidation.valid) {
                    this.$message.error('Campos personalizados incompletos: ' + customFieldsValidation.errors.join(', '))
                    return false
                }
            }

            if(this.form.operation_type_id === '0101' && this.form.is_itinerant) {
                if (this.form.guides.length == 0 ) {
                    this.errors = { is_itinerant : ['Debe tener una orden de entrega vinculada al documento']}
                    return;
                }

            }
            if (errorSeries) {
                this.$message.error("No se han seleccionado todas las series");
                return false;
            }

            if (this.form.show_terms_condition) {
                this.form.terms_condition = this.config.terms_condition_sale;
            }
            if (this.form.has_prepayment || this.prepayment_deduction) {
                let error_prepayment = await this.validateAffectationTypePrepayment();
                if (!error_prepayment.success)
                    return this.$message.error(error_prepayment.message);
            }

            if (this.isUpdateDocument || this.is_receivable) {
                this.form.payments = [];
            } else {
                let validate = await this.validate_payments();
                if (
                    validate.error_by_item > 0
                ) {
                    return this.$message.error(
                        "Los montos ingresados superan al monto a pagar o son incorrectos"
                    );
                }

                let validate_payment_destination = await this.validatePaymentDestination();

                if (validate_payment_destination.error_by_item > 0) {
                    return this.$message.error(
                        "El destino del pago es obligatorio"
                    );
                }
            }

            await this.deleteInitGuides();
            await this.asignPlateNumberToItems();



            // validacion sistema por puntos
            if (this.config.enabled_point_system) {
                const validate_exchange_points = this.validateExchangePoints();
                if (!validate_exchange_points.success)
                    return this.$message.error(
                        validate_exchange_points.message
                    );
            }

            // validacion sistema por puntos

            if (this.isGeneratedFromExternal) {
                // validacion restriccion de productos
                const validate_restrict_sale_items_cpe = this.fnValidateRestrictSaleItemsCpe(
                    this.form
                );
                if (!validate_restrict_sale_items_cpe.success)
                    return this.$message.error(
                        validate_restrict_sale_items_cpe.message
                    );
            }

            // Asegurar IdLoteSelected a nivel de fila e ítem antes del POST
            // (la UI puede resolverlo desde item.IdLoteSelected / lots_group, pero el backend lee row.IdLoteSelected)
            if (this.ensureItemsLotsForSubmit() === false) return false;

            // Capturar el cliente antes del submit, ya que resetForm() limpia el customer
            this.customerCurrent = this.getCustomer;

            if (!await this.ensureFiscalPaymentsReady(this.documentSubmission().payments)) return
            if (!this.validateChangeSelections()) return

            const submission = this.documentSubmission();
            const approved = this.useLocalCalculations ? this.prepareCalculationSubmission(submission) : submission;
            this.loading_submit = true;
            const payload = this.isUpdate ? approved : await confirmInvoiceSourcePrice(this, approved);
            if (!payload) {
                this.loading_submit = false;
                return;
            }

            this.is_consumption_charge = false;
            let saleCreated = false;
            let path = `/${this.resource}`;
            if (this.isUpdate) {
                path = `/${this.resource}/${this.form.id}/update`;
            }
            this.$http
                .post(path, payload)
                .then(async (response) => {
                    if (response.data.success) {
                        saleCreated = true;
                        this.documentNewId = response.data.data.id;
                        this.printTicketUrl = response.data?.links?.print_ticket ?? null;

                        this.$eventHub.$emit("reloadDataItems", null);
                        this.resetForm();
                        this.showOptionsDialog(response);

                        this.form_cash_document.document_id =
                            response.data.data.id;

                        // this.savePaymentMethod();
                        if (!this.isUpdateDocument) this.saveCashDocument();

                        this.autoPrintDocument();
                        await this.autoSendPdfMail();
                    } else {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => {
                    if (this.handleCalculationChanged(error, this.documentSubmission())) return;
                    if (error.response && error.response.status === 422) {
                        const data = error.response.data || {};
                        this.errors = data.errors || (data.message && typeof data.message === 'object' ? data.message : data);
                        const firstLotError = this.firstValidationError(
                            this.errors,
                            "IdLoteSelected"
                        );
                        const firstChangeError = this.firstValidationError(this.errors, 'change_disbursements');
                        if (this.errors.quoted_tax_amount) {
                            this.$message.error(this.errors.quoted_tax_amount[0]);
                            this.refreshFiscalQuote(this.activeInvoicePayments);
                        } else if (firstChangeError) {
                            this.$message.error(firstChangeError);
                        } else if (firstLotError) {
                            this.$message.error(firstLotError);
                        } else if (this.errors.customer_id) {
                            this.$message.error(this.errors.customer_id[0]);
                            delete this.errors.customer_id;
                        } else if (data.message) {
                            this.$message.error(data.message);
                        }
                    } else {
                        this.$message.error(
                            (error.response &&
                                error.response.data &&
                                error.response.data.message) ||
                                "Error al registrar el comprobante"
                        );
                    }
                })
                .finally(() => {
                    this.loading_submit = false;
                    this.customerCurrent = null;
                    if (saleCreated || !this.useLocalCalculations) {
                        this.setDefaultDocumentType();
                        this.selectDefaultCustomer();
                    }
                });
        },
        showOptionsDialog(response) {
            if (this.hidePreviewPdf) {
                const response_data = response.data;

                this.$message.success(
                    `Comprobante registrado: ${response_data.data.number_full}`
                );
            } else {
                this.showDialogOptions = true;
            }
        },
        autoPrintDocument() {
            if (this.isAutoPrint && this.printTicketUrl) {
                this.printDocument(this.printTicketUrl, this.configuration.printer_name_documents);
            }
        },
        async autoSendPdfMail() {
            if(!this.config.auto_send_pdf_email) return;

            const customer = this.customerCurrent;
            if(!customer || !customer.email) {
                this.$message.warning('El cliente no tiene un correo electrónico registrado. No se pudo enviar el comprobante por correo.')
                return;
            }

            this.$http.post(`/${this.resource}/email`, {
                customer_email: customer.email,
                id: this.documentNewId
            })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success('El correo fue enviado satisfactoriamente')
                    } else {
                        this.$message.error('Error al enviar el correo')
                    }
                })
                .catch(error => {
                    if (error.response.status === 422) {
                        this.errors = error.response.data.errors
                    } else {
                        this.$message.error(error.response.data.message)
                    }
                })
        },
        saveCashDocument() {
            this.$http
                .post(`/cash/cash_document`, this.form_cash_document)
                .then(response => {
                    if (!response.data.success) {
                        this.$message.error(response.data.message);
                    }
                })
                .catch(error => console.log(error));
        },
        validate_payments() {
            const result = validateInvoicePayments(this.activeInvoicePayments, this.form.total, this.payment_method_types);
            if (!result.error_by_item && !this.isCreditPaymentCondition) this.form.payments = result.inputs;
            return result;
        },
        close() {
            if (this.table) {
                location.href = `/${this.table}`;
            } else {
                location.href = this.is_contingency
                    ? `/contingencies`
                    : `/${this.resource}`;
            }
        },
        async reloadDataCustomers(customer_id) {
            if (!customer_id) {
                return;
            }

            const matchesCustomer = customer => sameInvoiceRecordId(customer.id, customer_id);
            const preloadedCustomer = this.customers.find(matchesCustomer);

            await this.$http
                .get(`/${this.resource}/search/customer/${customer_id}`, {params: {operation_type_id: this.form.operation_type_id}})
                .then(response => {
                    if (response.data.customers && response.data.customers.length > 0) {
                        this.customers = response.data.customers;
                    } else if (preloadedCustomer) {
                        this.customers = [preloadedCustomer];
                    }

                    this.form.customer_id = this.customers.find(matchesCustomer)?.id ?? customer_id;

                    this.$nextTick(() => {
                        this.changeCustomer();
                    });
                });
        },
        changeCustomer() {
            if (
                this.preloadedCustomerId &&
                !sameInvoiceRecordId(this.form.customer_id, this.preloadedCustomerId)
            ) {
                this.preloadedCustomerId = null;
                this.preloadedCustomer = null;
            }

            this.checkCustomerExpiredDebt();
            this.customer_addresses = [];
            this.form.customer_address_id = null;

            const customer = this.customers.find(row => sameInvoiceRecordId(row.id, this.form.customer_id));

            if (!customer) {
                return;
            }

            this.customer_addresses = this.buildCustomerAddresses(customer);
            this.selectDefaultCustomerAddress();


            this.getConsigneds()

            this.setCustomerAccumulatedPoints(
                customer.id,
                this.config.enabled_point_system
            );

            let seller = this.sellers.find(
                element => sameInvoiceRecordId(element.id, customer.seller_id)
            );
            if (seller !== undefined) {
                this.form.seller_id = seller.id;
            }

            if (customer.price_label_id) {
                this.selected_option_price = `price_label_${customer.price_label_id}`;
            }
            // retencion para clientes con ruc



        },
        async getConsigneds() {
            this.consigneds = [];
            this.form.consigned_id = null;
            this.consigned_addresses = [];
            this.form.consigned_address_id = null;
            this.form.consigned_address = null;

            if (this.form.customer_id) {
                this.consigneds = []
                this.consigned_addresses = []
                await this.$http.get(`/consigneds/search_by_customer/${this.form.customer_id}`).then((response) => {
                    this.consigneds = response.data.consigneds
                })
            }
        },
        async getConsignedAddresses() {
            this.consigned_addresses = [];
            this.form.consigned_address_id = null;
            let parameters = `?consigned_id=${this.form.consigned_id}&person_id=${this.form.customer_id}`;
            await this.$http.get(`/consigneds/addresses/${parameters}`).then((response) => {
                    this.consigned_addresses = response.data.consigned_addresses
                })
        },
        changeConsignedAddresses() {
            this.form.consigned_address = null;
            let consigned_address = _.find(this.consigned_addresses, {'id': this.form.consigned_address_id});
            this.form.consigned_address = consigned_address.address;
            this.form.consigned_ubigeo = consigned_address.district_id;
        },

        initDataPaymentCondition01() {
            this.readonly_date_of_due = false;
            this.enabled_payments = true;
            this.form.date_of_due = this.form.date_of_issue;
            this.form.payment_method_type_id = null;
        },
        changePaymentCondition() {
            if (!Array.isArray(this.form.fee)) this.form.fee = [];
            if (this.form.payment_condition_id === "01") {
                if (!this.form.payments.length) this.clickAddPayment();
                this.initDataPaymentCondition01();
            }
            if (this.form.payment_condition_id === "02") {
                if (!this.form.fee.length) this.clickAddFeeNew();
                this.readonly_date_of_due = true;
            }
            if (this.form.payment_condition_id === "03") {
                if (!this.form.fee.length) this.clickAddFee();
            }
            if (this.isCreditPaymentCondition) this.calculateFee();

            // if(this.isCreditPaymentCondition){
            // this.changeRetention()
            // }

            ;
        },
        changeCreditFeeDate(index) {
            const last_index = this.getLastIndexFee();

            if (last_index === index) {
                this.setDateOfDue(this.getLastDateFee(last_index));
            }
        },
        getLastIndexFee() {
            return this.form.fee.length - 1;
        },
        getLastDateFee(input_last_index = null) {
            const last_index = input_last_index || this.getLastIndexFee();

            return this.form.fee[last_index].date;
        },
        setDateOfDue(date_of_due) {
            this.form.date_of_due = date_of_due;
        },
        clickAddFee() {
            this.form.date_of_due = moment().format("YYYY-MM-DD");
            this.form.fee.push({
                id: null,
                date: moment().format("YYYY-MM-DD"),
                currency_type_id: this.form.currency_type_id,
                amount: 0
            });
            this.calculateFee();
        },
        clickAddFeeNew() {
            let first = {
                id: null,
                number_days: 0
            };
            if (this.credit_payment_metod[0] !== undefined) {
                first = this.credit_payment_metod[0];
            }

            // let date = moment()
            //     .add(first.number_days, 'days')
            //     .format('YYYY-MM-DD')

            let date = moment(this.form.date_of_issue)
                .add(first.number_days, "days")
                .format("YYYY-MM-DD");

            this.form.date_of_due = date;
            this.form.fee.push({
                id: null,
                document_id: null,
                payment_method_type_id: first.id,
                // reference: null,
                // payment_destination_id: this.getPaymentDestinationId(),
                // payment: 0,

                date: date,
                currency_type_id: this.form.currency_type_id,
                amount: 0
            });
            this.calculateFee();
        },
        clickRemoveFee(index) {
            this.form.fee.splice(index, 1);
            this.calculateFee();
            this.setDateOfDue(this.getLastDateFee());
        },
        // ######## INICIO CALCULOS EXACTOS SAB ########
        calculatePayments() {
            if (this.form.payment_condition_id === '02') return;
            if (this.isUpdateDocument) return;
            suggestRemainingPayment(this.form, this.form.payments, {refreshSuggested: this.form.payment_condition_id === '01'});
        },
        calculateFee() {
            suggestInstallments({...this.form, payments: this.activeInvoicePayments}, this.getTotal());
        },
        // ######## FIN CALCULOS EXACTOS SAB ########
        getTotal() {
            let total_pay = this.collectionTarget;
            ;
            // console.log(this.form.retention)
            // console.log(this.form.total_pending_payment)
            // console.log(this.form.total)


            ;

            // console.log('2');
            return total_pay;
        },
        setDescriptionOfItem(item) {
            return showNamePdfOfDescription(item, this.config.show_pdf_name);
        },
        checkKeyWithAlt(e) {
            let code = e.event.code;
            if (this.showDialogOptions === true && code === "KeyN") {
                this.showDialogOptions = false;
            }

            if (
                code === "KeyG" && // key G
                !this.showDialogAddItem && // Modal hidden
                this.form.items.length > 0 && // with items
                this.focus_on_client === false // not client search
            ) {
                this.submit();
            }
        },
        checkKey(e) {
            let code = e.event.code;
            if (code === "F2") {
                //abrir el modal de agergar producto
                if (!this.showDialogAddItem) this.showDialogAddItem = true;
            }
            if (code === "Escape") {
                if (this.showDialogAddItem) this.showDialogAddItem = false;
            }
        },
        openDialogLots(item) {
            this.recordItem = item;
            this.showDialogItemSeriesIndex = true;
        },
        successItemSeries(series) {
            let itemIndex = _.findIndex(this.form.items, {
                item_id: this.recordItem.item_id
            });
            this.form.items[itemIndex].item.lots = series;
        },
        showItemSeries(series) {
            return series.map(o => o["series"]).join(", ");
        },
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
            if (!idLoteSelected) return "";
            if (!Array.isArray(idLoteSelected)) {
                return String(idLoteSelected);
            }
            return idLoteSelected
                .map(lot => {
                    const code = lot.code || lot.id;
                    const qty = lot.compromise_quantity || 0;
                    return `${code} (${qty})`;
                })
                .join(", ");
        },
        async openLotGroupDialog(index, row) {
            if (!row || !row.item_id) {
                return this.$message.error("No se pudo identificar el producto para asignar lotes.");
            }

            this.loading_submit = true;
            try {
                const response = await this.$http.get(
                    `/item-lots-group/available-data/${row.item_id}`
                );
                let lotsGroup = Array.isArray(response.data)
                    ? response.data
                    : [];

                // Restaurar cantidades ya comprometidas si el usuario reabre el modal
                if (row.IdLoteSelected && Array.isArray(row.IdLoteSelected)) {
                    lotsGroup = lotsGroup.map(lGroup => {
                        const selected = _.find(row.IdLoteSelected, {
                            id: lGroup.id
                        });
                        return {
                            ...lGroup,
                            compromise_quantity: selected
                                ? selected.compromise_quantity
                                : lGroup.compromise_quantity || 0
                        };
                    });
                }

                this.lotModalLotsGroup = lotsGroup;
                this.lotModalQuantity = lotQuantity(row.quantity);
                this.lotModalItemIndex = index;
                this.showDialogLotsGroup = true;
            } catch (e) {
                this.$message.error(
                    "No se pudieron cargar los lotes disponibles del producto."
                );
            } finally {
                this.loading_submit = false;
            }
        },
        addRowLotGroupFromTable(lotsSelected) {
            if (this.lotModalItemIndex < 0) return;
            const row = this.form.items[this.lotModalItemIndex];
            if (!row) return;

            // Estructura idéntica a lots_group.vue (id, code, compromise_quantity, date_of_due)
            const normalizedLots = Array.isArray(lotsSelected)
                ? lotsSelected.map(lot => ({
                      id: lot.id,
                      code: lot.code,
                      compromise_quantity: lot.compromise_quantity,
                      date_of_due: lot.date_of_due
                  }))
                : lotsSelected;

            this.$set(row, "IdLoteSelected", normalizedLots);
            if (row.item) {
                this.$set(row.item, "IdLoteSelected", normalizedLots);
            }
            // Forzar reactividad del array de ítems
            this.$set(this.form.items, this.lotModalItemIndex, row);

            this.lotModalItemIndex = -1;
            this.$message.success("Lotes asignados correctamente.");
        },
        /**
         * Garantiza que cada ítem con lote lleve IdLoteSelected en el payload
         * (nivel fila + item), con la misma estructura que lots_group.vue.
         * No toca item.lots (eso es series/seriales).
         */
        ensureItemsLotsForSubmit() {
            if (!Array.isArray(this.form.items)) return;
            try {
            this.form.items.forEach((row, index) => {
                if (!row) return;

                hydrateItemLots(row);

                const resolved = resolveIdLoteSelected(row);
                if (resolved === null || resolved === undefined || resolved === "") {
                    return;
                }

                const normalized = normalizeInvoiceLots(resolved);

                this.$set(row, "IdLoteSelected", normalized);
                if (row.item) {
                    this.$set(row.item, "IdLoteSelected", normalized);
                }
                this.$set(this.form.items, index, row);
            });
            return true;
            } catch (error) { this.$message.error(error.message); return false; }
        },
        firstValidationError(errors, fieldHint) {
            if (!errors || typeof errors !== "object") return null;
            const keys = Object.keys(errors);
            const preferred = keys.find(key => key.includes(fieldHint));
            const key = preferred || keys[0];
            if (!key) return null;
            const messages = errors[key];
            return Array.isArray(messages) ? messages[0] : String(messages);
        },
        handleEnterKey(event) {
            event.preventDefault();
            event.target.blur();
        },
        async openDialogPreview() {
            let validate = await this.validatePreview();
            if (!validate) {
                return;
            }
            this.showDialogPreview = true;
        },

        async validatePreview() {
            if (!this.commitPendingInvoiceAdjustments()) return false;
            if (this.invoiceCalculationError) { this.$message.error(this.invoiceCalculationError);return false; }
            const errorSeries = !invoiceSeriesValid(this.form.items);
            if (errorSeries) {
                this.$message.error("No se han seleccionado todas las series");
                return false;
            }

            if (!this.form.customer_id) {
                this.$message.error("El campo cliente es obligatorio.");
                return false;
            }

            if (this.form.show_terms_condition) {
                this.form.terms_condition = this.config.terms_condition_sale;
            }
            if (this.form.has_prepayment || this.prepayment_deduction) {
                let error_prepayment = await this.validateAffectationTypePrepayment();
                if (!error_prepayment.success) {
                    this.$message.error(error_prepayment.message);
                    return false;
                }
            }

            if (this.is_receivable) {
                this.form.payments = [];
            } else {
                let validate = await this.validate_payments();
                if (
                    validate.error_by_item > 0
                ) {
                    this.$message.error(
                        "Los montos ingresados superan al monto a pagar o son incorrectos"
                    );
                    return false;
                }

                let validate_payment_destination = await this.validatePaymentDestination();

                if (validate_payment_destination.error_by_item > 0) {
                    this.$message.error("El destino del pago es obligatorio");
                    return false;
                }
            }

            await this.deleteInitGuides();
            await this.asignPlateNumberToItems();






            if (this.config.enabled_point_system) {
                const validate_exchange_points = this.validateExchangePoints();
                if (!validate_exchange_points.success) {
                    this.$message.error(validate_exchange_points.message);
                    return false;
                }
            }

            return true;
        },
        async preview(format) {
            this.loading_submit = true;
            let path = `/${this.resource}/preview`;

            let url = null;
            let original_format_pdf = this.form.actions.format_pdf;
            this.form.actions.format_pdf = format;

            try {
                let response = await this.$http.post(path, paymentConditionPayload({...withoutDraftRetentions(this.form), payments: this.activeInvoicePayments}), {
                    responseType: "blob"
                });
                if (!response.headers?.['content-type']?.includes('application/pdf') || !response.data?.size) {
                    throw new Error('La vista previa no devolvió un PDF válido.');
                }
                const blob = response.data;
                url = URL.createObjectURL(blob);
            } catch (error) {
                let body = error.response?.data;
                if (typeof body?.text === 'function') {
                    try { body = JSON.parse(await body.text()); } catch (_) { body = null; }
                }
                throw new Error(body?.errors?.pdf?.[0] || body?.pdf?.[0] || body?.message
                    || error.message || 'No se pudo cargar la vista previa.');
            } finally {
                this.loading_submit = false;
                this.form.actions.format_pdf = original_format_pdf;
            }

            return url;
        },
        verifySelectedSeller() {

            if (this.form.seller_id) {
                const seller = this.filteredSellers.find(row => sameInvoiceRecordId(row.id, this.form.seller_id));
                this.form.seller_id = seller?.id ?? (this.idUser || null);
            }
        },

        async checkCustomerExpiredDebt() {
            this.customer_expired_days = 0;
            this.customer_has_expired = false;

            if (
                this.config.finances &&
                this.config.finances.restriction_expired_debt &&
                this.form.customer_id
            ) {
                try {
                    const response = await this.$http.get(`/finances/unpaid/customer-expired-days/${this.form.customer_id}?model=document`);
                    this.customer_expired_days = response.data.max_expired_days || 0;

                    this.customer_has_expired =
                        this.customer_expired_days > Number(this.config.finances.max_expired_days);

                    if (this.customer_has_expired) {
                        this.form.payment_condition_id = "01";
                    }
                } catch (e) {
                    this.customer_expired_days = 0;
                    this.customer_has_expired = false;
                }
            }
        },
        openNewPersonDialog() {
            this.showDialogNewPerson = true
        },
    }
};
// ######## FIN SCRIPT GEOPOLITICO VENEZUELA
</script>

<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
