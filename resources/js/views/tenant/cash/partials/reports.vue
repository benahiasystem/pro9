<template>
    <el-dialog
        title="Generar reporte"
        :visible="showDialog"
        @open="onOpen"
        @close="clickClose"
        width="860px"
        :close-on-click-modal="true"
        append-to-body
    >
        <p class="text-muted small mb-3" v-if="cashLabel">{{ cashLabel }}</p>

        <div class="cash-reports-tabs d-inline-flex rounded-3 p-1 mb-3" v-if="catalog.length">
            <button
                v-for="category in catalog"
                :key="category.key"
                type="button"
                class="btn btn-sm border-0 px-4 rounded-3"
                :class="selectedCategory && selectedCategory.key === category.key ? 'bg-white fw-bold shadow-sm' : 'text-muted'"
                @click="selectCategory(category)"
            >
                {{ category.label }}
            </button>
        </div>

        <div v-if="loadingCatalog" class="text-center py-4">
            <i class="el-icon-loading"></i> Cargando reportes...
        </div>

        <template v-else-if="selectedCategory">
            <p class="text-muted small mb-3">{{ selectedCategory.description }}</p>

            <div class="row g-2 mb-3">
                <div class="col-md-6" v-for="report in selectedCategory.reports" :key="report.type">
                    <div
                        class="cash-reports-card border rounded-3 p-3 h-100"
                        :class="{ 'is-selected': selectedReport && selectedReport.type === report.type }"
                        @click="selectReport(report)"
                    >
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <strong class="small">{{ report.label }}</strong>
                            <div class="d-flex gap-1 flex-shrink-0">
                                <span
                                    v-for="format in report.formats"
                                    :key="format"
                                    class="badge"
                                    :class="format === 'pdf' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'"
                                >{{ format === 'pdf' ? 'PDF' : 'Excel' }}</span>
                            </div>
                        </div>
                        <div class="text-muted" style="font-size: 11.5px;">{{ report.description }}</div>
                    </div>
                </div>
            </div>

            <div class="border-top pt-3 d-flex flex-wrap align-items-end gap-4" v-if="selectedReport">
                <div>
                    <div class="text-muted small mb-1">Formato</div>
                    <el-radio-group v-model="form.format" size="mini">
                        <el-radio-button
                            v-for="format in selectedReport.formats"
                            :key="format"
                            :label="format"
                        >{{ format === 'pdf' ? 'PDF' : 'Excel' }}</el-radio-button>
                    </el-radio-group>
                </div>

                <div v-if="form.format === 'pdf' && selectedReport.papers.length > 1">
                    <div class="text-muted small mb-1">Papel</div>
                    <el-radio-group v-model="form.paper" size="mini">
                        <el-radio-button
                            v-for="paper in selectedReport.papers"
                            :key="paper"
                            :label="paper"
                        >{{ paperLabels[paper] || paper }}</el-radio-button>
                    </el-radio-group>
                </div>

                <div class="d-flex flex-column" v-if="visibleOptions.length">
                    <el-checkbox
                        v-for="option in visibleOptions"
                        :key="option.key"
                        v-model="form.options[option.key]"
                    >{{ option.label }}</el-checkbox>
                </div>
            </div>
        </template>

        <span slot="footer" class="dialog-footer">
            <el-button @click="clickClose">Cancelar</el-button>
            <el-button
                v-if="form.format === 'pdf'"
                @click="generate('download')"
                :disabled="!selectedReport"
            >Descargar</el-button>
            <el-button
                type="primary"
                @click="generate(form.format === 'pdf' ? 'preview' : 'download')"
                :disabled="!selectedReport"
            >{{ form.format === 'pdf' ? 'Previsualizar' : 'Descargar Excel' }}</el-button>
        </span>
    </el-dialog>
</template>

<style scoped>
.cash-reports-tabs { background: #f1f1f3; }
.cash-reports-tabs .btn:focus { box-shadow: none; }
.cash-reports-tabs .btn.shadow-sm { color: #111; }
.cash-reports-card { cursor: pointer; }
.cash-reports-card:hover { border-color: #bbb !important; }
.cash-reports-card.is-selected { border-color: #111 !important; box-shadow: 0 0 0 1px #111 inset; }
</style>
<script>
export default {
    props: ['showDialog', 'recordId', 'cashLabel'],
    data() {
        return {
            resource: 'cash-reports',
            catalog: [],
            loadingCatalog: false,
            selectedCategory: null,
            selectedReport: null,
            paperLabels: {
                a4: 'A4',
                ticket80: 'Ticket 80mm',
                ticket58: 'Ticket 58mm',
            },
            form: {
                format: 'pdf',
                paper: 'a4',
                options: {},
            },
        };
    },
    computed: {
        visibleOptions() {
            if (!this.selectedReport || this.form.format !== 'pdf') return [];

            return (this.selectedReport.options || []).filter(option => {
                return !option.papers || option.papers.includes(this.form.paper);
            });
        },
    },
    methods: {
        async onOpen() {
            if (!this.catalog.length) {
                await this.loadCatalog();
            }
            if (this.catalog.length && !this.selectedCategory) {
                this.selectCategory(this.catalog[0]);
            }
        },
        async loadCatalog() {
            this.loadingCatalog = true;
            await this.$http.get(`/${this.resource}/catalog`)
                .then(response => {
                    this.catalog = response.data.filter(category => category.reports.length);
                })
                .catch(() => {
                    this.$message.error('No se pudo cargar el catálogo de reportes');
                })
                .then(() => {
                    this.loadingCatalog = false;
                });
        },
        selectCategory(category) {
            this.selectedCategory = category;
            this.selectReport(category.reports[0] || null);
        },
        selectReport(report) {
            this.selectedReport = report;
            if (!report) return;

            this.form.format = report.formats[0];
            this.form.paper = report.papers[0];
            const options = {};
            (report.options || []).forEach(option => { options[option.key] = false; });
            this.form.options = options;
        },
        buildUrl(action) {
            const params = new URLSearchParams();
            params.set('format', this.form.format);
            params.set('action', action);

            if (this.form.format === 'pdf') {
                params.set('paper', this.form.paper);
                this.visibleOptions.forEach(option => {
                    if (this.form.options[option.key]) params.set(option.key, 1);
                });
            }

            return `/${this.resource}/generate/${this.selectedReport.type}/${this.recordId}?${params.toString()}`;
        },
        generate(action) {
            if (!this.selectedReport) return;
            window.open(this.buildUrl(action), '_blank');
        },
        clickClose() {
            this.$emit('update:showDialog', false);
        },
    },
};
</script>
