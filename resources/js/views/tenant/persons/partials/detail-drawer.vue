<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="detail-drawer person-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="detail-drawer__inner person-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header detail-drawer__header person-detail-drawer__header">
                <div class="detail-drawer__title person-detail-drawer__title">
                    <h5>{{ entityLabel }}</h5>
                    <small v-if="record">{{ record.name }}</small>
                </div>
                <a class="close-btn detail-drawer__close" href="#" aria-label="Cerrar panel" @click.prevent="visibleDrawer = false">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="20"  height="20"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                </a>
            </div>

            <template v-if="record">
                <div class="detail-drawer__status-bar person-detail-drawer__status-bar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge badge-info">{{ entityTypeBadgeLabel }}</span>
                        <span
                            v-if="record.enabled !== undefined && record.enabled !== null"
                            class="badge"
                            :class="personIsEnabled ? 'badge-success' : 'badge-danger'"
                        >
                            {{ personIsEnabled ? 'Habilitado' : 'Inhabilitado' }}
                        </span>
                    </div>
                    <small class="text-muted">#{{ record.id }}</small>
                </div>

                <div v-if="type === 'customers'" class="person-detail-drawer__metrics">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="shad-kpi" v-loading="loadingMetrics">
                                <div class="shad-kpi-header">
                                    <span class="shad-kpi-label">Total Pedidos</span>
                                </div>
                                <div class="shad-kpi-value">{{ metrics.totalOrders }}</div>
                                <div class="shad-kpi-desc">
                                    <span v-if="metrics.orderForms > 0">{{ metrics.orderForms }} pedidos</span>
                                    <span v-if="metrics.orderForms > 0 && metrics.documents > 0"> · </span>
                                    <span v-if="metrics.documents > 0">{{ metrics.documents }} comprobantes</span>
                                    <span v-if="metrics.totalOrders === 0">Sin operaciones registradas</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="shad-kpi shad-kpi-danger" v-loading="loadingMetrics">
                                <div class="shad-kpi-header">
                                    <span class="shad-kpi-label">Balance (Saldo)</span>
                                    <button
                                        v-if="metrics.balance > 0"
                                        type="button"
                                        class="person-detail-drawer__metric-action"
                                        title="Gestionar cuenta por cobrar"
                                        @click="openUnpaidModule"
                                    >
                                        <i class="el-icon-edit-outline"></i>
                                    </button>
                                </div>
                                <div class="shad-kpi-value">Bs. {{ formatAmount(metrics.balance) }}</div>
                                <div class="shad-kpi-desc">
                                    <span v-if="metrics.balance > 0" class="shad-kpi-red">Cuenta por cobrar pendiente</span>
                                    <span v-else class="shad-kpi-green">Sin saldo pendiente</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-drawer__body person-detail-drawer__body">
                    <el-tabs v-model="activeTab" @tab-click="handleTabClick">
                        <el-tab-pane label="General" name="general">
                            <div class="detail-drawer__customer-section">
                                <div class="detail-drawer__customer-heading">
                                    <h5 class="section-title">Identificación</h5>
                                    <p class="section-subtitle">Datos principales del {{ entityShortLabel }}</p>
                                </div>

                                <div class="detail-drawer__customer-summary">
                                    <div class="detail-drawer__customer-avatar" aria-hidden="true">{{ personInitials }}</div>
                                    <div class="detail-drawer__customer-identity">
                                        <strong class="detail-drawer__customer-name">{{ record.name || '—' }}</strong>
                                        <span v-if="personDocumentTypeLabel" class="detail-drawer__customer-badge">{{ personDocumentTypeLabel }}</span>
                                    </div>
                                </div>

                                <div class="detail-drawer__customer-grid">
                                    <div class="detail-drawer__customer-field"><span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-id"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 4m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v10a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/><path d="M9 10m-2 0a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M15 8l2 0"/><path d="M15 12l2 0"/><path d="M7 16l10 0"/></svg></span><div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Documento</span><strong>{{ personDocumentNumber }}</strong></div></div>
                                    <div class="detail-drawer__customer-field"><span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-hash"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 9l14 0"/><path d="M5 15l14 0"/><path d="M11 4l-2 16"/><path d="M17 4l-2 16"/></svg></span><div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Código interno</span><strong>{{ record.internal_code || '—' }}</strong></div></div>
                                    <div class="detail-drawer__customer-field"><span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-mail"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/><path d="M3 7l9 6l9 -6"/></svg></span><div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Correo</span><strong>{{ record.email || '—' }}</strong></div></div>
                                    <div class="detail-drawer__customer-field"><span class="detail-drawer__customer-field-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-phone"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2"/></svg></span><div class="detail-drawer__customer-field-content"><span class="detail-drawer__customer-field-label">Teléfono</span><strong>{{ record.telephone || '—' }}</strong></div></div>
                                </div>
                            </div>

                            <div v-if="hasGeneralAdditionalData" class="detail-drawer__details-section mt-3">
                                <div class="detail-drawer__details-heading"><h5 class="section-title">Contacto adicional</h5><p class="section-subtitle">Información complementaria y canales de contacto</p></div>
                                <div class="detail-drawer__details-grid">
                                    <div v-if="record.trade_name" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Nombre comercial</span><strong>{{ record.trade_name }}</strong></div>
                                    <div v-if="record.person_type" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Tipo de cliente</span><strong>{{ record.person_type }}</strong></div>
                                    <div v-if="contactDisplay" class="detail-drawer__details-card detail-drawer__details-card--wide"><span class="detail-drawer__details-label">Persona de contacto</span><strong>{{ contactDisplayLabel }}</strong></div>
                                    <div v-if="record.website" class="detail-drawer__details-card detail-drawer__details-card--wide"><span class="detail-drawer__details-label">Sitio web</span><strong>{{ record.website }}</strong></div>
                                </div>
                            </div>

                            <div class="detail-drawer__details-section mt-3">
                                <div class="detail-drawer__details-heading"><h5 class="section-title">Comercial</h5><p class="section-subtitle">Asignación comercial y condiciones</p></div>
                                <div v-if="hasCommercialData" class="detail-drawer__details-grid">
                                    <div v-if="record.seller && record.seller.name" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Vendedor</span><strong>{{ record.seller.name }}</strong></div>
                                    <div v-if="record.zone" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Zona</span><strong>{{ record.zone.name || '—' }}</strong></div>
                                    <div v-if="record.credit_days" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Días de crédito</span><strong>{{ record.credit_days }}</strong></div>
                                    <div v-if="record.observation" class="detail-drawer__details-card detail-drawer__details-card--wide detail-drawer__details-card--notice"><span class="detail-drawer__details-label">Observaciones</span><p>{{ record.observation }}</p></div>
                                </div>
                                <p v-else class="text-muted small mb-0">Sin datos comerciales adicionales.</p>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Ubicación" name="location">
                            <div v-if="locationMapQuery" class="detail-drawer__section-card person-detail-drawer__section-card person-detail-drawer__map-card">
                                <div class="detail-drawer__section-card-header person-detail-drawer__section-card-header">
                                    <h5 class="section-title">Mapa</h5>
                                    <p class="section-subtitle">Ubicación geográfica del {{ entityShortLabel }}</p>
                                </div>
                                <div class="person-detail-drawer__map-wrap">
                                    <iframe
                                        :key="mapEmbedUrl"
                                        :src="mapEmbedUrl"
                                        class="person-detail-drawer__map"
                                        loading="lazy"
                                        referrerpolicy="no-referrer-when-downgrade"
                                        allowfullscreen
                                        title="Mapa de ubicación"
                                    ></iframe>
                                </div>
                            </div>

                            <div class="detail-drawer__details-section">
                                <div class="detail-drawer__details-heading">
                                    <h5 class="section-title">Dirección principal</h5>
                                    <p class="section-subtitle">Ubigeo y domicilio fiscal</p>
                                </div>
                                <div class="detail-drawer__details-grid">
                                    <div class="detail-drawer__details-card detail-drawer__details-card--wide"><span class="detail-drawer__details-label">Dirección</span><strong>{{ record.address || '—' }}</strong></div>
                                    <div class="detail-drawer__details-card"><span class="detail-drawer__details-label">Departamento</span><strong>{{ locationDepartment }}</strong></div>
                                    <div class="detail-drawer__details-card"><span class="detail-drawer__details-label">Provincia</span><strong>{{ locationProvince }}</strong></div>
                                    <div class="detail-drawer__details-card"><span class="detail-drawer__details-label">Distrito</span><strong>{{ locationDistrict }}</strong></div>
                                    <div v-if="record.state" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Estado contribuyente</span><strong>{{ record.state }}</strong></div>
                                    <div v-if="record.condition" class="detail-drawer__details-card"><span class="detail-drawer__details-label">Condición</span><strong>{{ record.condition }}</strong></div>
                                </div>
                            </div>

                            <template v-if="secondaryAddresses.length">
                                <div class="detail-drawer__details-section mt-3">
                                    <div class="detail-drawer__details-heading">
                                        <h5 class="section-title">Direcciones adicionales</h5>
                                        <p class="section-subtitle">Otros domicilios registrados</p>
                                    </div>
                                    <div class="detail-drawer__details-grid">
                                        <div v-for="(address, index) in secondaryAddresses" :key="address.id || index" class="detail-drawer__details-card detail-drawer__details-card--wide">
                                            <span class="detail-drawer__details-label">Dirección {{ index + 1 }}<span v-if="address.main"> · Principal</span></span>
                                            <strong>{{ address.address || '—' }}</strong>
                                            <p v-if="address.phone || address.email">{{ secondaryAddressContact(address) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <p v-else-if="!record.address" class="text-muted small mb-0">
                                No hay direcciones registradas.
                            </p>
                        </el-tab-pane>

                        <el-tab-pane label="Documentos" name="documents">
                            <div class="detail-drawer__section-card person-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header person-detail-drawer__section-card-header">
                                    <h5 class="section-title">Comprobantes asociados</h5>
                                    <p class="section-subtitle">Últimos documentos emitidos a este {{ entityShortLabel }}</p>
                                </div>

                                <p v-if="type !== 'customers'" class="text-muted small mb-0">Los comprobantes de venta se registran únicamente para clientes.</p>

                                <div v-else v-loading="loadingDocuments">
                                    <div v-if="documents.length" class="table-responsive">
                                    <table class="table table-sm detail-drawer__items-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Comprobante</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="doc in documents" :key="doc.id">
                                                <td>{{ doc.date_of_issue }}</td>
                                                <td>
                                                    <span class="d-block">{{ doc.number }}</span>
                                                    <small class="text-muted">{{ doc.document_type_description }}</small>
                                                </td>
                                                <td class="text-end">
                                                    {{ doc.currency_type_id }} {{ formatAmount(doc.total) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    </div>
                                    <p v-else-if="!loadingDocuments" class="text-muted small mb-0">No se encontraron comprobantes para este {{ entityShortLabel }}.</p>
                                </div>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Historial" name="history">
                            <div class="detail-drawer__section-card person-detail-drawer__section-card">
                                <div class="detail-drawer__section-card-header person-detail-drawer__section-card-header">
                                    <h5 class="section-title">Historial</h5>
                                    <p class="section-subtitle">Actividad reciente del {{ entityShortLabel }}</p>
                                </div>

                                <ul v-if="historyItems.length" class="person-detail-drawer__history">
                                    <li v-for="(item, index) in historyItems" :key="index">
                                        <span class="person-detail-drawer__history-date">{{ item.date }}</span>
                                        <span class="person-detail-drawer__history-label">{{ item.label }}</span>
                                        <small v-if="item.detail" class="text-muted d-block">{{ item.detail }}</small>
                                    </li>
                                </ul>
                                <p v-else class="text-muted small mb-0">Sin actividad registrada.</p>
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <div class="detail-drawer__footer detail-drawer__footer--actions person-detail-drawer__footer">
                    <button
                        v-if="typeUser === 'admin'"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="deleting"
                        @click="clickDelete"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                        Eliminar {{ entityShortLabel }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" /><path d="M16 5l3 3" /></svg>
                        Editar {{ entityShortLabel }}
                    </button>
                </div>
            </template>
        </div>
    </el-drawer>
</template>

<script>
export default {
    props: {
        showDrawer: {
            type: Boolean,
            default: false
        },
        recordId: {
            type: [Number, String],
            default: null
        },
        type: {
            type: String,
            default: 'customers'
        },
        typeUser: {
            type: String,
            default: ''
        }
    },
    data() {
        return {
            loading: false,
            loadingDocuments: false,
            loadingMetrics: false,
            toggling: false,
            deleting: false,
            record: null,
            documents: [],
            metrics: {
                totalOrders: 0,
                orderForms: 0,
                documents: 0,
                balance: 0
            },
            activeTab: 'general',
            documentsLoaded: false
        };
    },
    computed: {
        visibleDrawer: {
            get() {
                return this.showDrawer;
            },
            set(value) {
                this.$emit('update:showDrawer', value);
            }
        },
        entityLabel() {
            return this.type === 'customers' ? 'Detalle del cliente' : 'Detalle del proveedor';
        },
        entityShortLabel() {
            return this.type === 'customers' ? 'cliente' : 'proveedor';
        },
        entityTypeBadgeLabel() {
            return this.type === 'customers' ? 'Cliente' : 'Proveedor';
        },
        personIsEnabled() {
            return this.record?.enabled === true
                || this.record?.enabled === 1
                || this.record?.enabled === '1';
        },
        personInitials() {
            const words = String(this.record?.name || '')
                .trim()
                .split(/\s+/)
                .filter(Boolean);

            if (!words.length) {
                return 'VE';
            }

            const initials = words.length === 1
                ? words[0].slice(0, 2)
                : `${words[0][0]}${words[words.length - 1][0]}`;

            return initials.toUpperCase();
        },
        personDocumentNumber() {
            return this.record?.number || '—';
        },
        personDocumentTypeLabel() {
            const type = this.record?.document_type;
            if (type && typeof type === 'object') {
                return type.description || type.name || null;
            }

            return type || null;
        },
        personDocument() {
            const number = this.record?.number || null;
            const type = this.record?.document_type || null;

            if (number && type) {
                return `${number} (${type})`;
            }

            if (number) {
                return number;
            }

            return '—';
        },
        locationDepartment() {
            return this.record?.department?.description || '—';
        },
        locationProvince() {
            return this.record?.province?.description || '—';
        },
        locationDistrict() {
            return this.record?.district?.description || '—';
        },
        contactDisplay() {
            return this.parseContact(this.record?.contact);
        },
        contactDisplayLabel() {
            if (!this.contactDisplay) {
                return '—';
            }

            return [this.contactDisplay.full_name, this.contactDisplay.phone]
                .filter(Boolean)
                .join(' · ');
        },
        hasGeneralAdditionalData() {
            return Boolean(
                this.record?.trade_name
                || this.record?.person_type
                || this.contactDisplay
                || this.record?.website
            );
        },
        hasCommercialData() {
            if (!this.record) {
                return false;
            }

            return Boolean(
                (this.record.seller && this.record.seller.name) ||
                this.record.zone ||
                this.record.credit_days ||
                this.record.observation
            );
        },
        secondaryAddresses() {
            if (!this.record || !Array.isArray(this.record.addresses)) {
                return [];
            }

            return this.record.addresses;
        },
        locationMapQuery() {
            if (!this.record) {
                return '';
            }

            const parts = [
                this.record.address,
                this.locationDistrict !== '—' ? this.locationDistrict : null,
                this.locationProvince !== '—' ? this.locationProvince : null,
                this.locationDepartment !== '—' ? this.locationDepartment : null
            ].filter(part => part && String(part).trim() !== '');

            return parts.length ? [...parts, 'Venezuela'].join(', ') : '';
        },
        mapEmbedUrl() {
            if (this.locationMapQuery) {
                return `https://maps.google.com/maps?q=${encodeURIComponent(this.locationMapQuery)}&z=15&output=embed`;
            }

            return 'https://maps.google.com/maps?q=Caracas,Venezuela&z=12&output=embed';
        },
        historyItems() {
            if (!this.record) {
                return [];
            }

            const items = [];

            if (this.record.created_at) {
                items.push({
                    date: this.formatDateTime(this.record.created_at),
                    label: `${this.entityShortLabel.charAt(0).toUpperCase()}${this.entityShortLabel.slice(1)} registrado`,
                    detail: null,
                    sort: new Date(this.record.created_at).getTime()
                });
            }

            if (this.record.updated_at && this.record.updated_at !== this.record.created_at) {
                items.push({
                    date: this.formatDateTime(this.record.updated_at),
                    label: 'Datos actualizados',
                    detail: null,
                    sort: new Date(this.record.updated_at).getTime()
                });
            }

            this.documents.forEach(doc => {
                items.push({
                    date: doc.date_of_issue,
                    label: `Comprobante ${doc.number}`,
                    detail: `${doc.document_type_description} · ${doc.state_type_description}`,
                    sort: this.parseDocumentDate(doc.date_of_issue)
                });
            });

            return items.sort((a, b) => b.sort - a.sort);
        }
    },
    watch: {
        showDrawer(value) {
            if (value && this.recordId) {
                this.openDrawer();
            }
        },
        recordId(value) {
            if (this.showDrawer && value) {
                this.openDrawer();
            }
        }
    },
    methods: {
        openDrawer() {
            this.activeTab = 'general';
            this.documents = [];
            this.documentsLoaded = false;
            this.loadingMetrics = this.type === 'customers';
            this.metrics = {
                totalOrders: 0,
                orderForms: 0,
                documents: 0,
                balance: 0
            };
            this.loadRecord();
        },
        handleTabClick(tab) {
            if (tab.name === 'documents' && !this.documentsLoaded) {
                this.loadDocuments();
            }
        },
        loadRecord() {
            this.loading = true;
            this.$http
                .get(`/persons/record/${this.recordId}`)
                .then(response => {
                    this.record = response.data.data || response.data;
                    if (this.type === 'customers') {
                        this.loadMetrics();
                    }
                })
                .catch(() => {
                    this.$message.error('No se pudo cargar el detalle del cliente.');
                    this.visibleDrawer = false;
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        async loadMetrics() {
            const customerId = this.recordId;
            if (!customerId || this.type !== 'customers') {
                this.loadingMetrics = false;
                return;
            }

            this.loadingMetrics = true;

            let documentsRes = 0;
            let orderFormsRes = 0;
            let balance = 0;

            try {
                documentsRes = await this.fetchPaginatedTotal('/documents/records', {
                    customer_id: customerId
                });
            } catch (error) {
                documentsRes = 0;
            }

            if (customerId !== this.recordId) {
                this.loadingMetrics = false;
                return;
            }

            try {
                orderFormsRes = await this.fetchPaginatedTotal('/order-forms/records', {
                    column: 'customer_id',
                    value: customerId
                });
            } catch (error) {
                orderFormsRes = 0;
            }

            if (customerId !== this.recordId) {
                this.loadingMetrics = false;
                return;
            }

            try {
                balance = await this.fetchCustomerBalance(customerId);
            } catch (error) {
                balance = 0;
            }

            if (customerId !== this.recordId) {
                this.loadingMetrics = false;
                return;
            }

            this.metrics.documents = documentsRes;
            this.metrics.orderForms = orderFormsRes;
            this.metrics.totalOrders = documentsRes + orderFormsRes;
            this.metrics.balance = balance;
            this.loadingMetrics = false;
        },
        async fetchPaginatedTotal(url, params = {}) {
            const response = await this.$http.get(url, { params: { ...params, page: 1 } });
            return Number(response.data.meta?.total || 0);
        },
        async fetchCustomerBalance(customerId = this.recordId) {
            let balance = 0;
            let page = 1;
            let lastPage = 1;

            do {
                const response = await this.$http.get('/finances/unpaid/records', {
                    params: {
                        customer_id: customerId,
                        stablishmentUnpaidAll: 1,
                        page
                    }
                });

                const rows = response.data.data || [];
                balance += rows.reduce((sum, row) => sum + parseFloat(row.total_to_pay || 0), 0);
                lastPage = Number(response.data.meta?.last_page || 1);
                page += 1;
            } while (page <= lastPage);

            return balance;
        },
        openUnpaidModule() {
            window.location.href = '/finances/unpaid';
        },
        loadDocuments() {
            if (!this.recordId || this.type !== 'customers') {
                this.documentsLoaded = true;
                return;
            }

            this.loadingDocuments = true;
            this.$http
                .get('/documents/records', {
                    params: {
                        customer_id: this.recordId,
                        page: 1
                    }
                })
                .then(response => {
                    this.documents = (response.data.data || []).slice(0, 10);
                    this.documentsLoaded = true;
                })
                .catch(() => {
                    this.documents = [];
                    this.documentsLoaded = true;
                })
                .finally(() => {
                    this.loadingDocuments = false;
                });
        },
        toggleEnabled(value) {
            const previous = !value;

            const applyChange = () => {
                this.toggling = true;
                this.$http
                    .get(`/persons/enabled/${value ? 1 : 0}/${this.record.id}`)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message);
                            this.$eventHub.$emit('reloadData');
                            return;
                        }

                        this.record.enabled = previous;
                        this.$message.error(response.data.message || 'No se pudo actualizar el estado.');
                    })
                    .catch(() => {
                        this.record.enabled = previous;
                        this.$message.error('No se pudo actualizar el estado.');
                    })
                    .finally(() => {
                        this.toggling = false;
                    });
            };

            if (!value) {
                this.$confirm(
                    `¿Desea inhabilitar este ${this.entityShortLabel}?`,
                    'Inhabilitar',
                    {
                        confirmButtonText: 'Inhabilitar',
                        cancelButtonText: 'Cancelar',
                        type: 'warning'
                    }
                )
                    .then(() => applyChange())
                    .catch(() => {
                        this.record.enabled = previous;
                    });
                return;
            }

            applyChange();
        },
        clickDelete() {
            if (!this.record?.id) {
                return;
            }

            this.deleting = true;

            this.$confirm('¿Desea eliminar el registro?', 'Eliminar', {
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                type: 'warning'
            })
                .then(() => this.$http.delete(`/persons/${this.record.id}`))
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.visibleDrawer = false;
                        this.$eventHub.$emit('reloadData');
                        return;
                    }

                    this.$message.error(response.data.message);
                })
                .catch(error => {
                    if (error === 'cancel' || error === 'close') {
                        return;
                    }

                    if (error.response?.status === 500) {
                        this.$message.error('Error al intentar eliminar');
                    }
                })
                .finally(() => {
                    this.deleting = false;
                });
        },
        formatAmount(value) {
            const amount = Number(value || 0);
            return amount.toLocaleString('es-VE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        parseContact(contact) {
            if (contact === null || contact === undefined || contact === '') {
                return null;
            }

            let data = contact;

            if (typeof contact === 'string') {
                const trimmed = contact.trim();
                if (!trimmed || trimmed === 'null') {
                    return null;
                }

                try {
                    data = JSON.parse(trimmed);
                } catch (error) {
                    return { full_name: trimmed, phone: null };
                }
            }

            if (typeof data !== 'object' || data === null) {
                return null;
            }

            const fullName = data.full_name ?? data.fullName ?? null;
            const phone = data.phone ?? data.telephone ?? null;
            const hasValue = value => value !== null && value !== undefined && String(value).trim() !== '';

            if (!hasValue(fullName) && !hasValue(phone)) {
                return null;
            }

            return {
                full_name: hasValue(fullName) ? String(fullName).trim() : null,
                phone: hasValue(phone) ? String(phone).trim() : null
            };
        },
        secondaryAddressContact(address) {
            return [
                address?.phone ? `Tel: ${address.phone}` : null,
                address?.email ? `Email: ${address.email}` : null
            ].filter(Boolean).join(' · ');
        },
        formatDateTime(value) {
            if (!value) {
                return '—';
            }

            const date = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString('es-VE', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        parseDocumentDate(value) {
            if (!value) {
                return 0;
            }

            const parts = value.split('-');
            if (parts.length === 3) {
                return new Date(`${parts[2]}-${parts[1]}-${parts[0]}`).getTime();
            }

            return 0;
        },
        handleClosed() {
            this.record = null;
            this.documents = [];
            this.documentsLoaded = false;
            this.loadingMetrics = false;
            this.activeTab = 'general';
            this.metrics = {
                totalOrders: 0,
                orderForms: 0,
                documents: 0,
                balance: 0
            };
        }
    }
};
</script>

<style scoped>

.person-detail-drawer__metrics {
    padding: 14px 16px 0;
}

.person-detail-drawer__metric-action {
    background: none;
    border: none;
    padding: 0;
    color: #64748b;
    cursor: pointer;
    line-height: 1;
    font-size: 16px;
}

.person-detail-drawer__metric-action:hover {
    color: #1f3a8a;
}

.shad-kpi {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 14px 12px;
    height: 100%;
    transition: box-shadow .15s;
}

.shad-kpi-danger {
    border-left: 3px solid var(--danger);
}

.shad-kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    gap: 6px;
}

.shad-kpi-label {
    font-size: 12px;
    font-weight: 500;
    color: #64748b;
}

.shad-kpi-icon {
    color: #94a3b8;
    flex-shrink: 0;
}

.shad-kpi-value {
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1;
    margin-bottom: 4px;
}

.shad-kpi-desc {
    font-size: 11px;
    color: #94a3b8;
    line-height: 1.35;
}

.shad-kpi-green {
    color: var(--success);
    font-weight: 600;
}

.shad-kpi-red {
    color: var(--danger);
    font-weight: 600;
}

.person-detail-drawer__map-card {
    padding-bottom: 12px;
}

.person-detail-drawer__map-wrap {
    overflow: hidden;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #eef2f7;
}

.person-detail-drawer__map {
    display: block;
    width: 100%;
    height: 180px;
    border: 0;
}

.person-detail-drawer__history {
    list-style: none;
    margin: 0;
    padding: 0;
}

.person-detail-drawer__history li {
    position: relative;
    padding: 0 0 16px 16px;
    border-left: 2px solid #dbeafe;
    margin-left: 6px;
}

.person-detail-drawer__history li:last-child {
    padding-bottom: 0;
}

.person-detail-drawer__history-date {
    display: block;
    font-size: 11px;
    color: #909399;
    margin-bottom: 2px;
}

.person-detail-drawer__history-label {
    font-weight: 600;
    color: #303133;
}

</style>
