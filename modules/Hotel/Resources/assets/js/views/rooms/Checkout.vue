<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
    <div>
        <div class="page-header pe-0">
            <h2>
                <a href="/hotels/reception">
                    <svg  xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-building-skyscraper"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l18 0" /><path d="M5 21v-14l8 -4v18" /><path d="M19 21v-10l-6 -4" /><path d="M9 9l0 .01" /><path d="M9 12l0 .01" /><path d="M9 15l0 .01" /><path d="M9 18l0 .01" /></svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>CHECK-OUT: <b>{{ title }}</b> (Registro de salida)</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <div class="btn-group flex-wrap">
                    <button
                        class="btn btn-custom btn-sm mt-2 me-2"
                        type="button"
                        @click="onGotoBack"
                    >
                        <i class="fa fa-arrow-left"></i> Atras
                    </button>
                </div>
            </div>
        </div>
        <div class="card mb-0 tab-content-default row-new">
            <template v-if="canMakePayment">
                <div class="card-body invoice p-3">
                    <div class="row card-body row card-body shadow-none mb-2">
                        <div class="col-12 col-md-3 h1 m-0 pt-1">
                            Salida
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-door-exit" style="transform: translateY(-4px);"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M13 12v.01"></path><path d="M3 21h18"></path><path d="M5 21v-16a2 2 0 0 1 2 -2h7.5m2.5 10.5v7.5"></path><path d="M14 7h7m-3 -3l3 3l-3 3"></path></svg>
                        </div>
                        <div class="col-9">
                            <span class="text-muted"><svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-user"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" /></svg> Cliente</span>
                            <h4 class="mt-0"><b>
                                {{ currentRent.customer.name }}</b>
                            </h4>
                        </div>
                        <!-- ######## INICIO PC-6 IDENTIFICACIÓN VENEZOLANA HOTEL ######## -->
                        <div class="col-12 col-md-3 card card-body bg-light-color my-1 mx-1 p-3">
                            <span class="text-muted"><svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-id"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 4m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v10a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" /><path d="M9 10m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M15 8l2 0" /><path d="M15 12l2 0" /><path d="M7 16l10 0" /></svg> Identificación (Cédula, RIF o Extranjero)</span>
                            <h4 class="m-0"><b>
                                {{ currentRent.customer.formatted_number || currentRent.customer.number }}</b>
                            </h4>
                        </div>
                        <!-- ######## FIN PC-6 IDENTIFICACIÓN VENEZOLANA HOTEL ######## -->
                        <div class="col-12 col-md-3 card card-body bg-light-color my-1 mx-1 p-3">
                            <span class="text-muted"><svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-door"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 12v.01" /><path d="M3 21h18" /><path d="M6 21v-16a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v16" /></svg> {{ currentRent.room.category.description }}</span>
                            <h4 class="m-0"><b>
                                {{ currentRent.room.name }}</b></h4>
                        </div>
                        <div class="col-12 col-md-3 card card-body bg-light-color my-1 mx-1 p-3">
                            <span class="text-muted"><svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-calendar-up"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12.5 21h-6.5a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v5" /><path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M19 22v-6" /><path d="M22 19l-3 -3l-3 3" /></svg> Check-IN</span>
                            <h4 class="m-0"><b>
                                {{ new Date(currentRent.input_date).toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }).replace(/ de /g, ' ')  }} <br>
                                {{ new Date(`2000-01-01T${currentRent.input_time}`).toLocaleTimeString('es-ES', { hour: 'numeric', minute: '2-digit', hour12: true }) }}</b></h4>
                        </div>
                        <div class="col-12 col-md-3 card card-body bg-light-color my-1 mx-1 p-3">
                            <span class="text-muted"><svg  xmlns="http://www.w3.org/2000/svg"  width="16"  height="16"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-calendar-down"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12.5 21h-6.5a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v5" /><path d="M19 16v6" /><path d="M22 19l-3 3l-3 -3" /><path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /></svg> Check-OUT</span>
                            <h4 class="m-0"><b>
                                {{ new Date(currentRent.output_date).toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }).replace(/ de /g, ' ')  }} <br>
                                {{ new Date(`2000-01-01T${currentRent.output_time}`).toLocaleTimeString('es-ES', { hour: 'numeric', minute: '2-digit', hour12: true }) }}</b></h4>
                        </div>
                    </div>
                    <div class="row card-body shadow-none mb-2">
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table text-end">
                                    <tbody>
                                    <tr class="text-start">
                                        <td colspan="6"><b class="h6">
                                            <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-coins"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 14c0 1.657 2.686 3 6 3s6 -1.343 6 -3s-2.686 -3 -6 -3s-6 1.343 -6 3z" /><path d="M9 14v4c0 1.656 2.686 3 6 3s6 -1.344 6 -3v-4" /><path d="M3 6c0 1.072 1.144 2.062 3 2.598s4.144 .536 6 0c1.856 -.536 3 -1.526 3 -2.598c0 -1.072 -1.144 -2.062 -3 -2.598s-4.144 -.536 -6 0c-1.856 .536 -3 1.526 -3 2.598z" /><path d="M3 6v10c0 .888 .772 1.45 2 2" /><path d="M3 11c0 .888 .772 1.45 2 2" /></svg> Alojamiento: Tarifas y Cargos Adicionales</b></td>
                                    </tr>
                                    <tr class="bg-light-color">
                                        <td>#</td>
                                        <td class="text-start">Tarifa por día</td>
                                        <td>Cant. noches</td>
                                        <td>Cargo por salir tarde</td>
                                        <td>Comprobante</td>
                                        <td>Total</td>
                                    </tr>
                                    <tr>
                                        <td>1</td>
                                        <td class="text-start">{{ room.item.unit_price | toDecimals }}</td>
                                        <td>{{ room.item.quantity }}</td>
                                        <td class="float-right">
                                            <div class="d-d-inline-block"
                                                style="max-width: 120px">
                                                <el-input v-model="arrears"
                                                        type="number"></el-input>
                                            </div>
                                        </td>
                                        <td>{{ room.document }}</td>
                                        <td class="float-right">
                                            <div class="d-d-inline-block"
                                                style="max-width: 120px">
                                                <el-input
                                                    v-model="total"
                                                    readonly
                                                    type="number"
                                                ></el-input>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="text-start" v-if="rentPaidItems.length > 0">
                                        <td colspan="6"><b class="h6">
                                            <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-receipt-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2" /><path d="M14 8h-2.5a1.5 1.5 0 0 0 0 3h1a1.5 1.5 0 0 1 0 3h-2.5m2 0v1.5m0 -9v1.5" /></svg> Servicio a la habitación (Pagado)</b></td>
                                    </tr>
                                    <tr class="bg-light-color" v-if="rentPaidItems.length > 0">
                                        <td>#</td>
                                        <td class="text-start">Descripción</td>
                                        <td>Precio unitario</td>
                                        <td>Cantidad</td>
                                        <td>Comprobante</td>
                                        <td>Total</td>
                                    </tr>
                                    <tr
                                        v-for="(it, i) in rentPaidItems"
                                        :key="i"
                                    >
                                        <td>{{ i + 1 }}</td>
                                        <td class="text-start">{{ it.item.item.description }}</td>
                                        <td>{{ it.item.input_unit_price_value | toDecimals }}</td>
                                        <td>{{ it.item.quantity | toDecimals }}</td>
                                        <td>{{ it.document}}</td>
                                        <td>{{ it.item.total | toDecimals }}</td>
                                    </tr>
                                    <tr><td></td></tr>
                                    <tr class="text-start" v-if="rentDebtItems.length > 0">
                                        <td colspan="6"><b class="h6 text-danger">
                                            <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-receipt-off"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 21v-16m2 -2h10a2 2 0 0 1 2 2v10m0 4.01v1.99l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2" /><path d="M11 7l4 0" /><path d="M9 11l2 0" /><path d="M13 15l2 0" /><path d="M15 11l0 .01" /><path d="M3 3l18 18" /></svg> Servicio a la habitación (Cargo Pendiente)</b></td>
                                    </tr>
                                    <tr class="bg-light-color" v-if="rentDebtItems.length > 0">
                                        <td>#</td>
                                        <td class="text-start">Descripción</td>
                                        <td>Precio unitario</td>
                                        <td>Cantidad</td>
                                        <td>Estado</td>
                                        <td>Total</td>
                                    </tr>
                                    <tr
                                        v-for="(it, i) in rentDebtItems"
                                        :key="i"
                                        class="text-danger"
                                    >
                                        <td>{{ i + 1 }}</td>
                                        <td class="text-start">{{ it.item.item.description }}</td>
                                        <td>{{ it.item.input_unit_price_value | toDecimals }}</td>
                                        <td>{{ it.item.quantity | toDecimals }}</td>
                                        <td>
                                            {{ it.payment_status === "PAID" ? "PAGADO" : "DEBE" }}
                                        </td>
                                        <td>{{ it.item.total | toDecimals }}</td>
                                    </tr>
                                    </tbody>
                                    <tfoot>
                                    <tr>
                                        <td class="text-end"
                                            colspan="5">Pagado
                                        </td>
                                        <td>
                                            <h3 class="my-0">
                                                <span class="badge badge-pill badge-info">
                                                    {{ formatHotelAmount(totalPaid) }}
                                                </span>
                                            </h3>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-end"
                                            colspan="5">Debe
                                        </td>
                                        <td>
                                            <h3 class="my-0">
                                                <span class="badge badge-pill badge-danger">
                                                    {{ formatHotelAmount(totalDebt) }}
                                                </span>
                                            </h3>
                                        </td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="row card-body shadow-none mb-2">
                        <div class="col-12 h6 m-0">
                            <b><svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-file-dollar"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><path d="M14 11h-2.5a1.5 1.5 0 0 0 0 3h1a1.5 1.5 0 0 1 0 3h-2.5" /><path d="M12 17v1m0 -8v1" /></svg> Información del comprobante (Solo para el monto pendiente de pago)</b>
                        </div>
                        <div class="col-lg-3">
                            <div
                                :class="{ 'has-danger': errors.document_type_id }"
                                class="form-group"
                            >
                                <label class="control-label">Tipo comprobante</label>
                                <el-select
                                    v-model="document.document_type_id"
                                    class="border-left rounded-left border-info"
                                    dusk="document_type_id"
                                    popper-class="el-select-document_type"
                                    @change="changeDocumentType"
                                >
                                    <el-option
                                        v-for="option in document_types"
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
                        <div v-if="showSeriesSelector || errors.series_id" class="col-lg-3">
                            <div :class="{ 'has-danger': errors.series_id }"
                                class="form-group">
                                <template v-if="showSeriesSelector">
                                    <label class="control-label">Serie</label>
                                    <el-select v-model="document.series_id">
                                        <el-option
                                            v-for="option in series"
                                            :key="option.id"
                                            :label="option.number || '\u00a0'"
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
                        <div class="col-lg-3">
                            <div
                                :class="{ 'has-danger': errors.date_of_issue }"
                                class="form-group"
                            >
                                <label class="control-label">Fecha de emisión</label>
                                <el-date-picker
                                    v-model="document.date_of_issue"
                                    :clearable="false"
                                    readonly
                                    type="date"
                                    value-format="yyyy-MM-dd"
                                    @change="changeDateOfIssue"
                                ></el-date-picker>
                                <small
                                    v-if="errors.date_of_issue"
                                    class="form-control-feedback"
                                    v-text="errors.date_of_issue[0]"
                                ></small>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div
                                :class="{ 'has-danger': errors.date_of_due }"
                                class="form-group"
                            >
                                <label class="control-label">Fecha de vencimiento</label>
                                <el-date-picker
                                    v-model="document.date_of_due"
                                    :clearable="false"
                                    type="date"
                                    value-format="yyyy-MM-dd"
                                ></el-date-picker>
                                <small
                                    v-if="errors.date_of_due"
                                    class="form-control-feedback"
                                    v-text="errors.date_of_due[0]"
                                ></small>
                            </div>
                        </div>
                    </div>
                    <div class="row card-body bg-accent-color mb-2 mx-0">
                        <div class="col-12 h6 m-0">
                            <b><svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-cash-register"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M21 15h-2.5c-.398 0 -.779 .158 -1.061 .439c-.281 .281 -.439 .663 -.439 1.061c0 .398 .158 .779 .439 1.061c.281 .281 .663 .439 1.061 .439h1c.398 0 .779 .158 1.061 .439c.281 .281 .439 .663 .439 1.061c0 .398 -.158 .779 -.439 1.061c-.281 .281 -.663 .439 -1.061 .439h-2.5" /><path d="M19 21v1m0 -8v1" /><path d="M13 21h-7c-.53 0 -1.039 -.211 -1.414 -.586c-.375 -.375 -.586 -.884 -.586 -1.414v-10c0 -.53 .211 -1.039 .586 -1.414c.375 -.375 .884 -.586 1.414 -.586h2m12 3.12v-1.12c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-2" /><path d="M16 10v-6c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-4c-.53 0 -1.039 .211 -1.414 .586c-.375 .375 -.586 .884 -.586 1.414v6m8 0h-8m8 0h1m-9 0h-1" /><path d="M8 14v.01" /><path d="M8 17v.01" /><path d="M12 13.99v.01" /><path d="M12 17v.01" /></svg> Registro de pagos pendientes</b>
                        </div>
                        <div class="col-12">
                            <table class="table">
                                <thead>
                                <tr width="100%">
                                    <th v-if="document.payments.length > 0">M. Pago</th>
                                    <th v-if="document.payments.length > 0">Destino</th>
                                    <th v-if="document.payments.length > 0">Referencia</th>
                                    <th v-if="document.payments.length > 0">Monto</th>
                                    <th width="15%">
                                        <a
                                            class="text-center font-weight-bold text-info"
                                            href="#"
                                            @click.prevent="clickAddPayment"
                                        >[+ Agregar]</a
                                        >
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="(row, index) in document.payments"
                                    :key="index">
                                    <td>
                                        <div class="form-group mb-2 me-2">
                                            <el-select v-model="row.payment_method_type_id">
                                                <el-option
                                                    v-for="option in receiptMethods"
                                                    :key="option.id"
                                                    :label="option.description"
                                                    :value="option.id"
                                                ></el-option>
                                            </el-select>
                                            <small v-if="!row.payment_method_type_id" class="text-muted">No hay efectivo activo para la moneda del documento; seleccione un método.</small>
                                            <igtf-settings-status v-if="fiscalPaymentsApplicable" />
                                            <igtf-payment-fields v-if="fiscalPaymentsApplicable" :payment="row" :payment-methods="paymentMethodTypes"
                                                :document-currency="document.currency_type_id" :document-rate="document.exchange_rate_sale"
                                                :customer-id="document.customer_id" :igtf-enabled="igtfEnabled" :settings-version="igtfSettings.revision" />
                                            <receipt-currency-fields v-else :row="row" :methods="paymentMethodTypes"
                                                :document-currency="document.currency_type_id" :document-rate="document.exchange_rate_sale" />
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-group mb-2 me-2">
                                            <el-select
                                                v-model="row.payment_destination_id"
                                                :disabled="row.payment_destination_disabled || isFixedBankDestination(row)"
                                                filterable
                                            >
                                                <el-option
                                                    v-for="option in destinationsFor(row)"
                                                    :key="option.id"
                                                    :label="option.description"
                                                    :value="option.id"
                                                ></el-option>
                                            </el-select>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-group mb-2 me-2">
                                            <el-input v-model="row.reference"></el-input>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-group mb-2 me-2">
                                            <el-input v-model="row.payment"></el-input>
                                        </div>
                                    </td>
                                    <td class="series-table-actions text-center">
                                        <button
                                            class="btn waves-effect waves-light btn-xs btn-danger"
                                            type="button"
                                            @click.prevent="clickCancel(index)"
                                        >
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                    <br/>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="row card-body shadow-none mb-2 border-0">
                        <div class="col-12 pt-3 text-end">
                            <template v-if="canMakePayment && (totalDebt > 0 || pendingCheckout || pendingAtomic)">
                                <el-button
                                    :disabled="loading || preparingCheckout"
                                    :loading="loading || preparingCheckout"
                                    class="btn btn-primary"
                                    @click="onGoToInvoice"
                                >
                                <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-device-floppy"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 4h10l4 4v10a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2" /><path d="M12 14m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M14 4l0 4l-6 0l0 -4" /></svg> {{ (pendingCheckout || pendingAtomic) ? 'Reintentar cierre' : 'Guardar y Generar Comprobante' }}
                                </el-button>
                            </template>
                            <template v-else-if="canMakePayment">
                                <el-button
                                    :disabled="loading"
                                    :loading="loading"
                                    class="btn btn-primary"
                                    @click="onGoToFinalizeRent"
                                >
                                <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-device-floppy"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 4h10l4 4v10a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2" /><path d="M12 14m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M14 4l0 4l-6 0l0 -4" /></svg> Guardar
                                </el-button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="card text-center">
                   <div>
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="96"  height="96"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="1.5"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-rosette-discount-check text-success"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 7.2a2.2 2.2 0 0 1 2.2 -2.2h1a2.2 2.2 0 0 0 1.55 -.64l.7 -.7a2.2 2.2 0 0 1 3.12 0l.7 .7c.412 .41 .97 .64 1.55 .64h1a2.2 2.2 0 0 1 2.2 2.2v1c0 .58 .23 1.138 .64 1.55l.7 .7a2.2 2.2 0 0 1 0 3.12l-.7 .7a2.2 2.2 0 0 0 -.64 1.55v1a2.2 2.2 0 0 1 -2.2 2.2h-1a2.2 2.2 0 0 0 -1.55 .64l-.7 .7a2.2 2.2 0 0 1 -3.12 0l-.7 -.7a2.2 2.2 0 0 0 -1.55 -.64h-1a2.2 2.2 0 0 1 -2.2 -2.2v-1a2.2 2.2 0 0 0 -.64 -1.55l-.7 -.7a2.2 2.2 0 0 1 0 -3.12l.7 -.7a2.2 2.2 0 0 0 .64 -1.55v-1" /><path d="M9 12l2 2l4 -4" /></svg>
                    <h2>Checkout éxitoso en {{this.currentRent.room.name}}</h2>
                    <p class="text-sm">Ya se finalizó el proceso y puede volver a recepción.</p>
                    <el-button
                        @click="onExitPage"
                        type="primary"
                        class="btn btn-primary mt-4"
                    >
                        <span class="ms-2">
                            Volver a recepción
                        </span>
                    </el-button>
                </div>

                </div>
            </template>
        </div>
        <document-options
            :isContingency="false"
            :recordId="documentNewId"
            :showClose="true"
            :showDialog.sync="showDialogDocumentOptions"
        ></document-options>

        <sale-note-options :configuration="config"
                           :recordId="documentNewId"
                           :showClose="true"
                           :showDialog.sync="showDialogSaleNoteOptions">
        </sale-note-options>

    </div>
</template>

<script>
import { hasNamedSeries } from '@helpers/document-series';
import {withoutDraftRetentions, suggestRemainingPayment} from "@helpers/document-creation"
import {newPaymentOperationKey} from "@helpers/payment-operation-key"
import {classifiedDestinations, defaultCashForCurrency, handleHotelPaymentError, isRecordedReceipt} from "@helpers/payment-classification"
import {exactAmount, exactValue} from "@helpers/exchange-rate-math"
import {documentTotals} from "@helpers/exact-document"
import fiscalPaymentSettings from "@mixins/fiscal-payment-settings"
import ReceiptCurrencyFields from "@components/ReceiptCurrencyFields.vue"
import IgtfPaymentFields from "@views/documents/partials/igtf_fields.vue"
import moment from "moment";
import DocumentOptions from "@views/documents/partials/options.vue";
import SaleNoteOptions from "@views/sale_notes/partials/options.vue";
import {calculateRowItem} from "@helpers/functions";
import {ensureExchangeRateSale} from "@helpers/ensure-exchange-rate-sale";
import {exchangeRate, functions} from "@mixins/functions";
import {mapActions, mapState} from "vuex/dist/vuex.mjs";

export default {
    components: {
        DocumentOptions,
        SaleNoteOptions,
        ReceiptCurrencyFields,
        IgtfPaymentFields,
    },
    mixins: [
        exchangeRate,
        functions,
        fiscalPaymentSettings
    ],
    props: {
        rent: {
            type: Object,
            required: true,
        },
        atomicCheckout: {
            type: Boolean,
            default: false,
        },
        customer: {
            type: Object,
            required: true,
        },
        room: {
            type: Object,
            required: true,
        },
        paymentMethodTypes: {
            type: Array,
            required: true,
        },
        paymentDestinations: {
            type: Array,
            required: true,
        },
        allSeries: {
            type: Array,
            required: true,
        },
        documentTypesInvoice: {
            type: Array,
            required: true,
        },
        configuration: {
            type: Object,
            required: false,
        },
        affectationIgvTypes: {
            type: Array,
            required: true,
        },
        payments: {
            type: Array,
            required: true,
        },
        rentItems: {
            type: Array,
            required: true,
        },
    },
    computed: {
        showSeriesSelector() {
            return hasNamedSeries(this.series);
        },
        ...mapState([
            'config',
        ]),
        receiptMethods() {
            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            return this.paymentMethodTypes.filter(method => method && [true, 1, '1'].includes(method.is_active)
                && ['cash', 'account'].includes(method.collection_kind))
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        },
        canMakePayment: function () {
            if (
                this.currentRent !== undefined &&
                this.currentRent.status !== undefined &&
                this.currentRent.status !== 'FINALIZADO'
            ) {
                return true;
            }
            return false;
        },
        hasDebt()
        {
            return this.totalDebt > 0
        },
        rentPaidItems()
        {
            return this.rentItems.filter(it => it.type === 'PRO' && it.payment_status === 'PAID')
        },
        rentDebtItems()
        {
            return this.rentItems.filter(it => it.type === 'PRO' && it.payment_status === 'DEBT')
        }
    },
    created() {
        this.loadConfiguration();
        this.$store.commit('setConfiguration', this.configuration);
        this.currentRent = this.rent
        this.pendingCheckout = this.readPendingHotelOperation('checkout', this.rent.id)
        this.pendingAtomic = this.readPendingHotelOperation('atomic', this.rent.id)
        this.currentRent.input_date =  moment(this.currentRent.input_date).toDate()
        this.currentRent.output_date =  moment(this.currentRent.output_date).toDate()
    },
    data() {
        return {
            title: "",
            currentRent: {},
            arrears: 0,
            total: 0,
            debtRoom: 0,
            loading: false,
            preparingCheckout: false,
            totalPaid: 0,
            totalDebt: 0,
            response: {},
            document: {
                payments: [],
            },
            errors: {},
            series: [],
            document_types: [],
            all_document_types: [],
            resource_documents: "documents",
            showDialogDocumentOptions: false,
            documentNewId: null,
            pendingCheckout: null,
            pendingAtomic: null,
            atomicDraft: null,
            form_cash_document: {},
            showDialogSaleNoteOptions: false,
            form: {
                establishment_id: null,
                date_of_issue: null
            },
        };
    },
    async mounted() {
        // console.log(this.config);

        this.form.establishment_id = this.config.establishment.id;
        this.form.date_of_issue = moment().format("YYYY-MM-DD");
        await this.getPercentageIgv();

        this.room.item = await calculateRowItem(this.room.item, "VES", 3, this.percentage_igv);

        this.initForm();
        await this.initDocument();
        this.all_document_types = this.documentTypesInvoice;
        this.title = this.currentRent.room.name;
        this.total = this.room.item.total;

        this.document.items = await this.currentRent.items
            .filter(it => it.payment_status === 'DEBT')
            .map(i => {
                if (!i.item.affectation_igv_type || _.isEmpty(i.item.affectation_igv_type)) {
                    i.item.affectation_igv_type = _.find(this.affectationIgvTypes, { id: i.item.affectation_igv_type_id });
                }
                return {...calculateRowItem(i.item, "VES", 3, this.percentage_igv), hotel_checkout_room: i.type === 'HAB'};
            });

        await this.onCalculateTotals();
        await this.onCalculatePaidAndDebts();

        if(this.totalDebt > 0){
            const method = defaultCashForCurrency(this.paymentMethodTypes, this.document.currency_type_id)
            this.document.payments.push({
                id: null,
                document_id: null,
                date_of_payment: moment().format("YYYY-MM-DD"),
                payment_method_type_id: method?.id || null,
                payment_destination_id: method?.collection_kind === 'cash' && this.paymentDestinations.some(item => item.id === 'cash') ? 'cash' : null,
                reference: null,
                payment: this.totalDebt,
            });
        }

        this.validateIdentityDocumentType();
        const date = moment().format("YYYY-MM-DD");
        await this.searchExchangeRateByDate(date).then((res) => {
            this.document.exchange_rate_sale = res;
        });
        if (this.atomicCheckout && this.pendingAtomic && this.currentRent.status === 'FINALIZADO') await this.onAtomicCheckout();
    },
    watch: {
        'document.payments': {
            deep: true,
            handler(rows) {
                if (!Array.isArray(rows)) return
                rows.forEach(row => {
                    if (isRecordedReceipt(row)) return
                    const method = this.paymentMethodTypes.find(item => item && String(item.id) === String(row.payment_method_type_id))
                    if (method?.collection_kind === 'account') {
                        const destination = this.destinationsFor(row).find(item => String(item.id) === String(method.bank_account_id))
                        const next = destination ? destination.id : null
                        if (row.payment_destination_id !== next) this.$set(row, 'payment_destination_id', next)
                        return
                    }
                    if (row.payment_destination_id != null && !this.destinationsFor(row).some(destination => destination.id === row.payment_destination_id)) {
                        this.$set(row, 'payment_destination_id', null)
                    }
                })
            },
        },
        arrears(value) {
            try {
                const amount = exactAmount(value || 0);
                if (amount.compare(0) < 0) return;
                this.total = exactValue(this.room.item, 'total').plus(amount).decimal();
                this.onCalculatePaidAndDebts();
            } catch (error) { /* Allow an incomplete decimal while it is being typed. */ }
        },
    },
    methods: {
        isFixedBankDestination(row) {
            return this.paymentMethodTypes.some(method => method && String(method.id) === String(row.payment_method_type_id) && method.collection_kind === 'account')
        },
        formatHotelAmount(value) {
            return exactAmount(value).final(2);
        },
        ...mapActions([
            'loadConfiguration',
        ]),
        validateIdentityDocumentType() {
            // ########## INICIO CAMBIO FACTURAS Y NOTAS DE VENTA HOTEL
            // ########## INICIO CAMBIO SOLO FACTURA Y NOTA DE VENTA
            const allowedOrder = ['80', '01'];
            this.document_types = allowedOrder
                .map(id => this.all_document_types.find(row => row.id === id))
                .filter(Boolean);
            // ######### FIN CAMBIO SOLO FACTURA Y NOTA DE VENTA
            // ######### FIN CAMBIO FACTURAS Y NOTAS DE VENTA HOTEL

            this.document.document_type_id =
                this.document_types.length > 0 ? this.document_types[0].id : null;
            this.changeDocumentType();
        },
        changeDateOfIssue() {
            this.document.date_of_due = this.document.date_of_issue;
        },
        changeDocumentType() {
            this.document.series_id = null;
            this.series = _.filter(this.allSeries, {
                document_type_id: this.document.document_type_id,
            });
            this.document.series_id =
                this.series.length > 0 ? this.series[0].id : null;
        },
        clickAddPayment() {
            const method = defaultCashForCurrency(this.paymentMethodTypes, this.document.currency_type_id)
            this.document.payments.push({
                id: null,
                document_id: null,
                date_of_payment: moment().format("YYYY-MM-DD"),
                payment_method_type_id: method?.id || null,
                payment_destination_id: method?.collection_kind === 'cash' && this.paymentDestinations.some(item => item.id === 'cash') ? 'cash' : null,
                reference: null,
                payment: 0,
            });
            suggestRemainingPayment({...this.document, total: this.totalDebt, total_payable: this.totalDebt});
        },
        destinationsFor(row) {
            return classifiedDestinations(this.paymentDestinations, this.paymentMethodTypes, row, this.document.currency_type_id)
        },
        onExitPage() {
            window.location.href = "/hotels/reception";
        },
        validatePaymentDestination() {
            let error_by_item = 0;

            this.document.payments.forEach((item) => {
                if (item.payment_destination_id == null) error_by_item++;
            });

            return {
                error_by_item: error_by_item,
            };
        },
        validateTotalPayments()
        {
            try {
                const total = this.document.payments.reduce((sum, row) => sum.plus(row.payment), exactAmount(0))
                if (total.compare(this.totalDebt) > 0) return this.getResponseValidations(false, 'El total de los pagos agregados es superior al monto.')
            } catch (error) {
                return this.getResponseValidations(false, 'Indique importes de pago válidos.')
            }

            return this.getResponseValidations()
        },
        initForm() {
            this.form_cash_document = {
                document_id: null,
                sale_note_id: null,
            };
        },
        updateDataForSend() {
            console.log('updateDataForSend');

            if (this.document.document_type_id === '80') {
                this.document.prefix = 'NV'
                this.resource_documents = 'sale-notes'
            } else {
                this.document.prefix = null
                this.document.type = 'invoice'
                this.document.series = this.series.find(row => row.id === this.document.series_id)?.number || null
                this.resource_documents = 'documents'
            }

        },
        successGoToInvoice() {
            console.log('successGoToInvoice');

            //inicializa form_cash_document
            this.initForm()

            if (this.document.document_type_id === '80') //NV
            {
                this.form_cash_document.sale_note_id = this.documentNewId
                this.showDialogSaleNoteOptions = true

            } else {
                this.form_cash_document.document_id = this.documentNewId
                this.showDialogDocumentOptions = true;
            }

        },
        async onGoToFinalizeRent() {
            this.loading = true;
            const payloadFinalizedRent = {
                arrears: this.arrears,
            };
            this.loading = true;
            this.$http.post( `/hotels/reception/${this.currentRent.id}/rent/finalized`,
                    payloadFinalizedRent
                )
                .then((response) => {
                    this.$message({
                        message: response.data.message,
                        type: "success",
                    });
                    window.location.href = "/hotels/reception";
                })
                .catch((error) => handleHotelPaymentError(error, this))
                .finally(() => {
                    this.loading = false
                });
        },
        readPendingHotelOperation(kind, rentId) {
            for (const prefix of ['sab', 'pro9']) {
                try {
                    const pending = JSON.parse(window.sessionStorage.getItem(`${prefix}-hotel-${kind}:${rentId}`) || 'null')
                    if (pending && ['01', '80'].includes(pending.type) &&
                        (kind === 'atomic' ? typeof pending.key === 'string' : pending.id)) return pending
                } catch (error) { /* Try the previous key before giving up recovery. */ }
            }
            return null
        },
        clearAtomicPending() {
            try { window.sessionStorage.removeItem(`sab-hotel-atomic:${this.currentRent.id}`) } catch (error) { /* The completed operation remains on the server. */ }
            try { window.sessionStorage.removeItem(`pro9-hotel-atomic:${this.currentRent.id}`) } catch (error) { /* Retry cleanup on the next completed operation. */ }
            this.pendingAtomic = null
            this.atomicDraft = null
        },
        completeAtomicCheckout(result) {
            const data = result.data
            if (!result.found || !data?.id || !['01','80'].includes(data.document_type_id) || !result.currentRent) {
                throw new Error('La respuesta del cierre no contiene el comprobante esperado.')
            }
            this.documentNewId = data.id
            this.document.document_type_id = data.document_type_id
            this.currentRent = result.currentRent
            this.response = result
            this.clearAtomicPending()
            this.successGoToInvoice()
            this.$emit('update:showDialog', false)
            this.saveCashDocument()
        },
        async onAtomicCheckout() {
            if (this.loading || this.preparingCheckout) return
            this.preparingCheckout = true
            this.loading = true
            try {
                if (this.pendingAtomic) {
                    const status = await this.$http.get(`/hotels/reception/${this.currentRent.id}/rent/checkout/operation/${this.pendingAtomic.key}`)
                    if (status.data.found) return this.completeAtomicCheckout(status.data)
                    if (!this.atomicDraft) {
                        this.clearAtomicPending()
                        return this.$message.error('No se registró el cierre anterior. Revise los datos y vuelva a intentarlo.')
                    }
                } else {
                    await this.onUpdateItemsWithExtras()
                    await this.onCalculateTotals()
                    if (this.validatePaymentDestination().error_by_item > 0) return this.$message.error('El destino del pago es obligatorio')
                    const valid = this.validateTotalPayments()
                    if (!valid.success) return this.$message.error(valid.message)
                    this.updateDataForSend()
                    try { await ensureExchangeRateSale(this.document, this.$http) }
                    catch (error) { return this.$message.error(error.message || 'No se pudo obtener el tipo de cambio.') }
                    if (!(await this.ensureFiscalPaymentsReady(this.document.payments))) return
                    const key = newPaymentOperationKey()
                    const pending = {key, type:this.document.document_type_id}
                    const draft = JSON.parse(JSON.stringify({operation_key:key, arrears:String(this.arrears || 0),
                        document:withoutDraftRetentions(this.document)}))
                    try { window.sessionStorage.setItem(`sab-hotel-atomic:${this.currentRent.id}`, JSON.stringify(pending)) }
                    catch (error) { throw new Error('No se puede guardar la clave necesaria para recuperar el cierre.') }
                    this.pendingAtomic = pending
                    this.atomicDraft = draft
                }
                const response = await this.$http.post(`/hotels/reception/${this.currentRent.id}/rent/checkout/operation`, this.atomicDraft)
                if (!response.data.success) throw new Error(response.data.message || 'No se pudo registrar el cierre.')
                this.completeAtomicCheckout(response.data)
            } catch (error) {
                if (error.response?.status >= 400 && error.response.status < 500) this.clearAtomicPending()
                handleHotelPaymentError(error, this)
            } finally {
                this.preparingCheckout = false
                this.loading = false
            }
        },
        async onGoToInvoice() {
            if (this.atomicCheckout && !this.pendingCheckout) return this.onAtomicCheckout()
            if (this.loading || this.preparingCheckout) return
            if (!this.pendingCheckout) this.preparingCheckout = true
            try {
                if (!this.pendingCheckout) {
                await this.onUpdateItemsWithExtras();
                await this.onCalculateTotals();
                const validate_payment_destination = this.validatePaymentDestination();
                if (validate_payment_destination.error_by_item > 0) {
                    return this.$message.error("El destino del pago es obligatorio");
                }
                const validate_total_payments = this.validateTotalPayments()
                if (!validate_total_payments.success) return this.$message.error(validate_total_payments.message)
                this.updateDataForSend()
                try {
                    await ensureExchangeRateSale(this.document, this.$http);
                } catch (error) {
                    return this.$message.error(error.message || 'No se pudo obtener el tipo de cambio.');
                }
                if (!(await this.ensureFiscalPaymentsReady(this.document.payments))) return
                }
                this.loading = true
                if (!this.pendingCheckout) {
                    const response = await this.$http.post(`/${this.resource_documents}`, withoutDraftRetentions(this.document))
                    if (!response.data.success) return this.$message.error(response.data.message)
                    this.pendingCheckout = {id: response.data.data.id, type: this.document.document_type_id, arrears: String(this.arrears || 0)}
                    try { window.sessionStorage.setItem(`sab-hotel-checkout:${this.currentRent.id}`, JSON.stringify(this.pendingCheckout)) } catch (error) { /* Retry remains available on this page. */ }
                    this.response = response.data
                }
                const pending = this.pendingCheckout
                const finalized = await this.$http.post(`/hotels/reception/${this.currentRent.id}/rent/finalized`, {
                    arrears: pending.arrears, checkout_document_type: pending.type, checkout_document_id: pending.id,
                })
                if (!finalized.data.success) return this.$message.error(finalized.data.message)
                this.documentNewId = pending.id
                this.document.document_type_id = pending.type
                this.currentRent = finalized.data.currentRent
                this.pendingCheckout = null
                try { window.sessionStorage.removeItem(`sab-hotel-checkout:${this.currentRent.id}`) } catch (error) { /* The completed server operation is idempotent. */ }
                try { window.sessionStorage.removeItem(`pro9-hotel-checkout:${this.currentRent.id}`) } catch (error) { /* The completed server operation is idempotent. */ }
                this.successGoToInvoice()
                this.$emit('update:showDialog', false)
                this.saveCashDocument()
            } catch (error) {
                handleHotelPaymentError(error, this)
            } finally {
                this.preparingCheckout = false
                this.loading = false
            }
        },
        onUpdateItemsWithExtras() {
            const amount = exactAmount(this.arrears || 0);
            if (amount.compare(0) < 0) throw new Error('El recargo no puede ser negativo.');
            const items = this.document.items.filter(row => !row.hotel_checkout_surcharge);
            const roomDue = items.some(row => row.hotel_checkout_room === true ||
                (row.hotel_checkout_room === undefined && row.item_id === this.room.item_id));
            this.document.items = items.map((it) => {
                if (it.hotel_checkout_room === true || (it.hotel_checkout_room === undefined && it.item_id === this.room.item_id)) {
                    it.hotel_checkout_base_name = it.hotel_checkout_base_name ?? it.item.name;
                    it.hotel_checkout_nights = it.hotel_checkout_nights ?? exactValue(it, 'quantity').decimal();
                    const name = `${it.hotel_checkout_base_name} x ${it.hotel_checkout_nights} noche(s)`;
                    it.item.description = name;
                    it.item.full_description = name;
                    it.name_product_pdf = name;
                    it.quantity = 1;
                    it.quantity_input = null;
                    // Preserve the original room amount across retries so arrears are applied once.
                    it.hotel_checkout_base_total = it.hotel_checkout_base_total ?? exactValue(it, 'total').fraction();
                    const newTotal = exactAmount(it.hotel_checkout_base_total).plus(this.arrears || 0);
                    it.input_unit_price_value = newTotal.decimal();
                    it.item.unit_price = newTotal.decimal();
                    it.unit_value = newTotal.decimal();
                    it.price_input = {amount: newTotal.fraction(), currency_type_id: 'VES', includes_iva: true};
                    const newItem = calculateRowItem(it, "VES", 3, this.percentage_igv);
                    return newItem;
                }
                return it;
            });
            if (!roomDue && amount.compare(0) > 0) {
                const source = this.room.item;
                if (!source?.item || source.item.unit_type_id !== 'SERV' || [true, 1, '1'].includes(source.item.is_set)) {
                    throw new Error('Para registrar el recargo de una habitación pagada, la habitación debe tener un servicio asociado.');
                }
                const item = {...source.item, unit_price: amount.decimal(), description:'Cargo por salida tardía',
                    full_description:'Cargo por salida tardía'};
                const row = {...source, id:null, record_id:null, item, item_id:this.room.item_id, quantity:'1',
                    quantity_input:null, price_input:{amount:amount.fraction(), currency_type_id:'VES', includes_iva:true},
                    discounts:[], charges:[], calculation_snapshot:null, input_unit_price_value:amount.decimal(),
                    name_product_pdf:'Cargo por salida tardía', hotel_checkout_surcharge:true, hotel_checkout_room:false};
                this.document.items.push(calculateRowItem(row, 'VES', 3, this.percentage_igv));
            }
        },
        // async getItemsForSaleNote()
        // {
        //   return await this.$http.post(`/sale-notes/items-by-ids`, { ids : _.map(this.document.items, 'item_id')})
        // },
        saveCashDocument() {
            this.$http
                .post(`/cash/cash_document`, this.form_cash_document)
                .then((response) => {
                    if (!response.data.success) {
                        this.$message.error(response.data.message);
                    }
                })
                .catch((error) => {
                    this.axiosError(error);
                });
        },
        onCalculatePaidAndDebts() {
            const sum = status => this.currentRent.items.reduce(
                (total, item) => item.payment_status === status ? total.plus(exactValue(item.item, 'total')) : total,
                exactAmount(0)
            );
            this.totalPaid = sum('PAID').decimal();
            this.totalDebt = sum('DEBT').plus(this.arrears || 0).decimal();
        },
        initDocument() {
            this.document = {
                customer_id: this.currentRent.customer_id,
                customer: this.currentRent.customer,
                document_type_id: null,
                series_id: null,
                prefix: null,
                establishment_id: this.config.establishment.id,
                number: "#",
                date_of_issue: moment().format("YYYY-MM-DD"),
                time_of_issue: moment().format("HH:mm:ss"),
                currency_type_id: "VES",
                purchase_order: null,
                exchange_rate_sale: 0,
                total_prepayment: 0,
                total_charge: 0,
                total_discount: 0,
                total_exportation: 0,

                total_taxed: 0,

                total_exonerated: 0,
                total_igv: 0,


                total_base_other_taxes: 0,
                total_other_taxes: 0,
                total_taxes: 0,
                total_value: 0,
                total: 0,
                subtotal: 0,
                operation_type_id: "0101",
                date_of_due: moment().format("YYYY-MM-DD"),
                delivery_date: moment().format("YYYY-MM-DD"),
                items: [],
                charges: [],
                discounts: [],
                attributes: [],
                guides: [],
                additional_information: null,
                actions: {
                    format_pdf: "a4",
                },
                dispatch_id: null,
                dispatch: null,
                is_receivable: false,
                payments: [],
                hotel: {},
                hotel_data_persons: this.currentRent.data_persons,
                source_module: 'HOTEL',
                hotel_rent_id: this.currentRent.id
            };
        },
        onGotoBack() {
            window.location.href = "/hotels/reception";
        },
        clickCancel(index) {
            this.document.payments.splice(index, 1);
        },
        onCalculateTotals() {
            Object.assign(this.document, documentTotals(this.document.items, this.document.discounts || [],
                this.document.charges || [], this.document.total_prepayment || '0'));
        },
    },
};
</script>

<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
