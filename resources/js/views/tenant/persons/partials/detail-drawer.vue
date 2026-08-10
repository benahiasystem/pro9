<template>
    <el-drawer
        :visible.sync="visibleDrawer"
        :with-header="false"
        size="560px"
        direction="rtl"
        custom-class="person-detail-drawer"
        append-to-body
        @closed="handleClosed"
    >
        <div class="person-detail-drawer__inner" v-loading="loading">
            <div class="theme-sidebar-header person-detail-drawer__header">
                <div class="person-detail-drawer__title">
                    <h4>{{ entityLabel }}</h4>
                    <small v-if="record">{{ record.name }}</small>
                </div>
                <button
                    type="button"
                    class="close-theme-sidebar person-detail-drawer__close"
                    aria-label="Cerrar panel"
                    @click="visibleDrawer = false"
                >
                    <i class="el-icon-close"></i>
                </button>
            </div>

            <template v-if="record">
                <div v-if="type === 'customers'" class="person-detail-drawer__metrics">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="shad-kpi" v-loading="loadingMetrics">
                                <div class="shad-kpi-header">
                                    <span class="shad-kpi-label">Total Pedidos</span>
                                    <svg class="shad-kpi-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17h-11v-14l-2 -2h-3v16" /></svg>
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
                                    <svg v-else class="shad-kpi-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h12a1 1 0 0 0 1 -1v-3" /><path d="M20 12h-7l3 -3m0 6l-3 -3" /></svg>
                                </div>
                                <div class="shad-kpi-value">S/ {{ formatAmount(metrics.balance) }}</div>
                                <div class="shad-kpi-desc">
                                    <span v-if="metrics.balance > 0" class="shad-kpi-red">Cuenta por cobrar pendiente</span>
                                    <span v-else class="shad-kpi-green">Sin saldo pendiente</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="person-detail-drawer__body">
                    <el-tabs v-model="activeTab" @tab-click="handleTabClick">
                        <el-tab-pane label="General" name="general">
                            <div class="person-detail-drawer__section-card">
                                <div class="person-detail-drawer__section-card-header">
                                    <h5 class="section-title">Identificación</h5>
                                    <p class="section-subtitle">Datos principales del {{ entityShortLabel }}</p>
                                </div>
                                <dl class="person-detail-drawer__list">
                                    <dt>Nombre / Razón social</dt>
                                    <dd>{{ record.name || '—' }}</dd>

                                    <dt v-if="record.trade_name">Nombre comercial</dt>
                                    <dd v-if="record.trade_name">{{ record.trade_name }}</dd>

                                    <dt>Documento</dt>
                                    <dd>{{ personDocument }}</dd>

                                    <dt>Cód. interno</dt>
                                    <dd>{{ record.internal_code || '—' }}</dd>

                                    <dt v-if="record.person_type">Tipo de cliente</dt>
                                    <dd v-if="record.person_type">{{ record.person_type }}</dd>
                                </dl>
                            </div>

                            <div class="person-detail-drawer__section-card">
                                <div class="person-detail-drawer__section-card-header">
                                    <h5 class="section-title">Contacto</h5>
                                    <p class="section-subtitle">Canales de comunicación</p>
                                </div>
                                <dl class="person-detail-drawer__list">
                                    <dt>Correo</dt>
                                    <dd>{{ record.email || '—' }}</dd>

                                    <dt>Teléfono</dt>
                                    <dd>{{ record.telephone || '—' }}</dd>

                                    <template v-if="contactDisplay">
                                        <dt>Persona de contacto</dt>
                                        <dd>
                                            <span v-if="contactDisplay.full_name">{{ contactDisplay.full_name }}</span>
                                            <template v-if="contactDisplay.full_name && contactDisplay.phone">
                                                <br>
                                            </template>
                                            <span v-if="contactDisplay.phone" class="text-muted">{{ contactDisplay.phone }}</span>
                                        </dd>
                                    </template>

                                    <dt v-if="record.website">Sitio web</dt>
                                    <dd v-if="record.website">{{ record.website }}</dd>
                                </dl>
                            </div>

                            <div class="person-detail-drawer__section-card">
                                <div class="person-detail-drawer__section-card-header">
                                    <h5 class="section-title">Comercial</h5>
                                    <p class="section-subtitle">Asignación comercial y condiciones</p>
                                </div>
                                <dl class="person-detail-drawer__list">
                                    <dt v-if="record.seller && record.seller.name">Vendedor</dt>
                                    <dd v-if="record.seller && record.seller.name">{{ record.seller.name }}</dd>

                                    <dt v-if="record.zone">Zona</dt>
                                    <dd v-if="record.zone">{{ record.zone.name || '—' }}</dd>

                                    <dt v-if="record.credit_days">Días de crédito</dt>
                                    <dd v-if="record.credit_days">{{ record.credit_days }}</dd>

                                    <dt v-if="record.observation">Observaciones</dt>
                                    <dd v-if="record.observation">{{ record.observation }}</dd>

                                    <template v-if="!hasCommercialData">
                                        <dd class="text-muted mb-0">Sin datos comerciales adicionales.</dd>
                                    </template>
                                </dl>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Ubicación" name="location">
                            <div class="person-detail-drawer__section-card person-detail-drawer__map-card">
                                <div class="person-detail-drawer__section-card-header">
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

                            <div class="person-detail-drawer__section-card">
                                <div class="person-detail-drawer__section-card-header">
                                    <h5 class="section-title">Dirección principal</h5>
                                    <p class="section-subtitle">Ubigeo y domicilio fiscal</p>
                                </div>
                                <dl class="person-detail-drawer__list">
                                    <dt>Dirección</dt>
                                    <dd>{{ record.address || '—' }}</dd>

                                    <dt>Departamento</dt>
                                    <dd>{{ locationDepartment }}</dd>

                                    <dt>Provincia</dt>
                                    <dd>{{ locationProvince }}</dd>

                                    <dt>Distrito</dt>
                                    <dd>{{ locationDistrict }}</dd>

                                    <dt v-if="record.state">Estado contribuyente</dt>
                                    <dd v-if="record.state">{{ record.state }}</dd>

                                    <dt v-if="record.condition">Condición</dt>
                                    <dd v-if="record.condition">{{ record.condition }}</dd>
                                </dl>
                            </div>

                            <template v-if="secondaryAddresses.length">
                                <div class="person-detail-drawer__section-card">
                                    <div class="person-detail-drawer__section-card-header">
                                        <h5 class="section-title">Direcciones adicionales</h5>
                                        <p class="section-subtitle">Otros domicilios registrados</p>
                                    </div>
                                    <div
                                        v-for="(address, index) in secondaryAddresses"
                                        :key="address.id || index"
                                        class="person-detail-drawer__address-card"
                                    >
                                        <small class="text-muted d-block mb-1">
                                            Dirección {{ index + 1 }}
                                            <span v-if="address.main" class="badge badge-info ms-1">Principal</span>
                                        </small>
                                        <p class="mb-1">{{ address.address || '—' }}</p>
                                        <small v-if="address.phone" class="text-muted d-block">Tel: {{ address.phone }}</small>
                                        <small v-if="address.email" class="text-muted d-block">Email: {{ address.email }}</small>
                                    </div>
                                </div>
                            </template>
                            <p v-else-if="!record.address" class="text-muted small mb-0">
                                No hay direcciones registradas.
                            </p>
                        </el-tab-pane>

                        <el-tab-pane label="Documentos" name="documents">
                            <div class="section-header section-header-first">
                                <h5 class="section-title">Comprobantes asociados</h5>
                                <p class="section-subtitle">Últimos documentos emitidos a este {{ entityShortLabel }}</p>
                            </div>

                            <p v-if="type !== 'customers'" class="text-muted small mb-0">
                                Los comprobantes de venta se registran únicamente para clientes.
                            </p>

                            <div v-else v-loading="loadingDocuments">
                                <div v-if="documents.length" class="table-responsive">
                                    <table class="table table-sm mb-0">
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
                                <p v-else-if="!loadingDocuments" class="text-muted small mb-0">
                                    No se encontraron comprobantes para este {{ entityShortLabel }}.
                                </p>
                            </div>
                        </el-tab-pane>

                        <el-tab-pane label="Historial" name="history">
                            <div class="person-detail-drawer__section-card">
                                <div class="person-detail-drawer__section-card-header">
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

                <div class="person-detail-drawer__footer">
                    <button
                        v-if="typeUser === 'admin'"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="deleting"
                        @click="clickDelete"
                    >
                        <i class="fa fa-trash"></i> Eliminar {{ entityShortLabel }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-custom btn-sm"
                        @click="$emit('edit', record.id)"
                    >
                        <i class="fa fa-edit"></i> Editar {{ entityShortLabel }}
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
                this.locationDepartment !== '—' ? this.locationDepartment : null,
                'Perú'
            ].filter(part => part && String(part).trim() !== '');

            return parts.join(', ');
        },
        mapEmbedUrl() {
            if (this.locationMapQuery) {
                return `https://maps.google.com/maps?q=${encodeURIComponent(this.locationMapQuery)}&z=15&output=embed`;
            }

            return 'https://maps.google.com/maps?q=Lima,Peru&z=12&output=embed';
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
            return amount.toLocaleString('es-PE', {
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
        formatDateTime(value) {
            if (!value) {
                return '—';
            }

            const date = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString('es-PE', {
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
.person-detail-drawer__inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
}

.person-detail-drawer__header {
    flex-shrink: 0;
    width: 100%;
    min-height: 56px;
    box-sizing: border-box;
    overflow: hidden;
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    direction: ltr;
}

.person-detail-drawer__title {
    flex: 1;
    min-width: 0;
    padding-right: 4px;
    text-align: left;
}

.person-detail-drawer__close {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    min-width: 32px;
    margin: 0;
    padding: 0;
    line-height: 1;
    border-radius: 6px;
    align-self: center;
}

.person-detail-drawer__title h4 {
    margin: 0;
    line-height: 1.2;
}

.person-detail-drawer__title small {
    display: block;
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.person-detail-drawer__section-card {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.person-detail-drawer__section-card:last-child {
    margin-bottom: 0;
}

.person-detail-drawer__section-card-header {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.person-detail-drawer__section-card-header .section-title {
    margin-bottom: 2px;
}

.person-detail-drawer__section-card-header .section-subtitle {
    margin-bottom: 0;
}

.person-detail-drawer__section-card .person-detail-drawer__list dd:last-child {
    margin-bottom: 0;
}

.person-detail-drawer__status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    border-bottom: 1px solid #ebeef5;
    background: #f8fafc;
}

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

.shad-kpi:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.07);
}

.shad-kpi-danger {
    border-left: 3px solid #ef4444;
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
    color: #16a34a;
    font-weight: 600;
}

.shad-kpi-red {
    color: #ef4444;
    font-weight: 600;
}

.person-detail-drawer__body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 16px;
}

.person-detail-drawer__body >>> .el-tabs__header {
    margin-bottom: 12px;
}

.person-detail-drawer__list {
    margin: 0 0 8px;
}

.person-detail-drawer__list dt {
    font-size: 12px;
    color: #909399;
    margin-bottom: 4px;
}

.person-detail-drawer__list dd {
    margin: 0 0 14px;
    font-weight: 500;
    color: #303133;
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

.person-detail-drawer__address-card {
    padding: 12px;
    margin-bottom: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
}

.person-detail-drawer__address-card:last-child {
    margin-bottom: 0;
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

.person-detail-drawer__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 20px;
    border-top: 1px solid #ebeef5;
    background: #fff;
}

.section-title {
    font-size: 1.05rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #1f3a8a;
    margin-bottom: 0.2rem;
}

.section-subtitle {
    color: #6b7280;
    font-size: 0.86rem;
    margin-bottom: 0.5rem;
}

.section-header {
    border-top: 1px solid #d4e4f4;
    margin-top: 1.2rem;
    padding-top: 0.8rem;
    padding-bottom: 0.5rem;
}

.section-header.section-header-first {
    border-top: none;
    margin-top: 0.5rem;
    padding-top: 0;
}
</style>

<style>
.person-detail-drawer.el-drawer .el-drawer__body {
    padding: 0;
    height: 100%;
    overflow: hidden;
}

.person-detail-drawer .theme-sidebar-header.person-detail-drawer__header {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    box-sizing: border-box;
    overflow: hidden;
    padding: 14px 16px;
    gap: 12px;
    direction: ltr;
}

.person-detail-drawer .person-detail-drawer__close {
    position: static;
    top: auto;
    right: auto;
    margin: 0;
    transform: none;
}
</style>
