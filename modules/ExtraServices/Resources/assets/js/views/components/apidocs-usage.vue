<template>
  <div v-loading="loading" element-loading-background="rgba(255, 255, 255, 0.7)">
    <div class="mb-3">
      <strong>Uso del servicio</strong>
    </div>

    <div class="row mb-4" v-if="quota">
      <div class="col-md-4 mb-3 mb-md-0">
        <div class="border rounded p-3 h-100">
          <div class="text-muted small mb-1">Uso mensual</div>
          <div class="d-flex justify-content-between align-items-baseline mb-2">
            <span class="h4 mb-0">{{ formatNumber(currentUsage.monthly) }}</span>
            <span class="text-muted">/ {{ formatNumber(quota.monthly_limit) }}</span>
          </div>
          <el-progress
            :percentage="monthlyPercent"
            :status="monthlyPercent >= 100 ? 'exception' : monthlyPercent >= 80 ? 'warning' : null"
            :stroke-width="12"
          />
          <div class="text-muted small mt-2" v-if="periodMonth">
            Periodo: {{ formatPeriodMonth(periodMonth) }}
          </div>
        </div>
      </div>

      <div class="col-md-4 mb-3 mb-md-0">
        <div class="border rounded p-3 h-100">
          <div class="text-muted small mb-1">Uso diario</div>
          <div class="d-flex justify-content-between align-items-baseline mb-2">
            <span class="h4 mb-0">{{ formatNumber(currentUsage.daily) }}</span>
            <span class="text-muted">/ {{ formatNumber(quota.daily_limit) }}</span>
          </div>
          <el-progress
            :percentage="dailyPercent"
            :status="dailyPercent >= 100 ? 'exception' : dailyPercent >= 80 ? 'warning' : null"
            :stroke-width="12"
          />
          <div class="text-muted small mt-2" v-if="periodDay">
            Día: {{ formatPeriodDay(periodDay) }}
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="border rounded p-3 h-100">
          <div class="text-muted small mb-1">Días de overflow</div>
          <div class="d-flex justify-content-between align-items-baseline mb-2">
            <span class="h4 mb-0">{{ formatNumber(currentUsage.overflow_used) }}</span>
            <span class="text-muted">/ {{ formatNumber(quota.overflow_days) }}</span>
          </div>
          <el-progress
            :percentage="overflowPercent"
            :status="overflowPercent >= 100 ? 'exception' : overflowPercent >= 80 ? 'warning' : null"
            :stroke-width="12"
          />
          <div class="text-muted small mt-2" v-if="quota.updated_at">
            Actualizado: {{ formatDate(quota.updated_at) }}
          </div>
        </div>
      </div>
    </div>

    <div class="mb-2">
      <strong>Uso por cliente</strong>
    </div>

    <div class="table-responsive">
      <el-table
        :data="rows"
        border
        stripe
        style="width: 100%;"
        empty-text="No hay registros disponibles"
      >
        <el-table-column prop="client_id" label="ID Cliente" width="110" />
        <el-table-column prop="client_name" label="Cliente" min-width="180" />
        <el-table-column prop="hostname" label="Hostname" min-width="240" />
        <el-table-column prop="quantity" label="Cantidad" width="120" />
        <el-table-column prop="month" label="Mes" width="120" />
      </el-table>
    </div>

    <div class="d-flex justify-content-end mt-3" v-if="pagination.total > pagination.per_page">
      <el-pagination
        background
        layout="total, prev, pager, next"
        :current-page="pagination.current_page"
        :page-size="pagination.per_page"
        :total="pagination.total"
        @current-change="handlePageChange"
      />
    </div>
  </div>
</template>

<script>
import { Table, TableColumn, Pagination, Progress } from 'element-ui';

export default {
  name: 'ApidocsUsage',
  components: {
    'el-table': Table,
    'el-table-column': TableColumn,
    'el-pagination': Pagination,
    'el-progress': Progress,
  },
  data() {
    return {
      loading: false,
      rows: [],
      quota: null,
      currentUsage: {
        monthly: 0,
        daily: 0,
        overflow_used: 0,
        period: {},
      },
      pagination: {
        current_page: 1,
        per_page: 10,
        total: 0,
        last_page: 1,
      },
      resource: '/extra-services/apidocs/usage/records',
    };
  },
  computed: {
    periodMonth() {
      return this.currentUsage?.period?.month || null;
    },
    periodDay() {
      return this.currentUsage?.period?.day || null;
    },
    monthlyPercent() {
      return this.usagePercent(this.currentUsage.monthly, this.quota?.monthly_limit);
    },
    dailyPercent() {
      return this.usagePercent(this.currentUsage.daily, this.quota?.daily_limit);
    },
    overflowPercent() {
      return this.usagePercent(this.currentUsage.overflow_used, this.quota?.overflow_days);
    },
  },
  async created() {
    await this.getData();
  },
  methods: {
    usagePercent(used, limit) {
      const max = Number(limit) || 0;
      if (max <= 0) return 0;
      return Math.min(100, Math.round((Number(used) || 0) * 100 / max));
    },
    formatNumber(value) {
      if (value === null || value === undefined || value === '') return '0';
      return Number(value).toLocaleString('es-PE');
    },
    formatPeriodMonth(value) {
      if (!value || String(value).length !== 6) return value;
      const year = String(value).slice(0, 4);
      const month = String(value).slice(4, 6);
      return `${month}/${year}`;
    },
    formatPeriodDay(value) {
      if (!value || String(value).length !== 8) return value;
      const year = String(value).slice(0, 4);
      const month = String(value).slice(4, 6);
      const day = String(value).slice(6, 8);
      return `${day}/${month}/${year}`;
    },
    formatDate(value) {
      if (!value) return '';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('es-PE');
    },
    async getData(page = 1) {
      this.loading = true;
      try {
        const response = await this.$http.get(this.resource, {
          params: {
            page,
            per_page: this.pagination.per_page,
          },
        });
        const data = response?.data?.data || {};

        this.rows = data.clients || [];
        this.quota = data.quota || null;
        this.currentUsage = {
          monthly: data.current_usage?.monthly ?? 0,
          daily: data.current_usage?.daily ?? 0,
          overflow_used: data.current_usage?.overflow_used ?? 0,
          period: data.current_usage?.period || {},
        };
        this.pagination = {
          current_page: data.pagination?.current_page || 1,
          per_page: data.pagination?.per_page || 10,
          total: data.pagination?.total || 0,
          last_page: data.pagination?.last_page || 1,
        };
      } catch (error) {
        console.error('Error fetching apidocs usage:', error);
        this.rows = [];
        this.quota = null;
        this.currentUsage = {
          monthly: 0,
          daily: 0,
          overflow_used: 0,
          period: {},
        };
      } finally {
        this.loading = false;
      }
    },
    handlePageChange(page) {
      this.getData(page);
    },
  },
};
</script>
