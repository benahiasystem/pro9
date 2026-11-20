<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
  <div class="row top px-2 kpi-row">
    <div class="kpi-col col">
      <div class="card card-dashboard h-100">
        <div class="card-body card-kpi justify-content-start">
          <small class="kpi-title text-muted">{{ salesTitle }}</small>
          <div class="kpi-main">
            <div class="kpi-values">
              <h3 class="kpi-amount font-weight-bold m-0">Bs. {{ monthly_sales | formatNumber }}</h3>
            </div>
            <kpi-sparkline class="kpi-spark" :data="trend.monthly_sales" :labels="trend.labels" :formatter="trendFormatter('monthly_sales')" color="#0f766e"></kpi-sparkline>
          </div>
          <small
            v-if="changes.monthly_sales"
            class="kpi-change"
            :class="changes.monthly_sales.up ? 'is-up' : 'is-down'"
          >
            {{ changes.monthly_sales.up ? "▲" : "▼" }} {{ changes.monthly_sales.pct | formatNumber(1, 1) }}%
            <span class="kpi-change-label text-muted">{{ changeLabel }}</span>
          </small>
        </div>
      </div>
    </div>
    <div class="kpi-col col">
      <div class="card card-dashboard h-100">
        <div class="card-body card-kpi justify-content-start">
          <small class="kpi-title text-muted">Ticket promedio</small>
          <div class="kpi-main">
            <div class="kpi-values">
              <h3 class="kpi-amount font-weight-bold m-0">Bs. {{ average_ticket | formatNumber }}</h3>
            </div>
            <kpi-sparkline class="kpi-spark" :data="trend.average_ticket" :labels="trend.labels" :formatter="trendFormatter('average_ticket')" color="#0d9488"></kpi-sparkline>
          </div>
          <small
            v-if="changes.average_ticket"
            class="kpi-change"
            :class="changes.average_ticket.up ? 'is-up' : 'is-down'"
          >
            {{ changes.average_ticket.up ? "▲" : "▼" }} {{ changes.average_ticket.pct | formatNumber(1, 1) }}%
            <span class="kpi-change-label text-muted">{{ changeLabel }}</span>
          </small>
        </div>
      </div>
    </div>
    <div class="kpi-col col">
      <div class="card card-dashboard h-100">
        <div class="card-body card-kpi justify-content-start">
          <small class="kpi-title text-muted">Por cobrar</small>
          <div class="kpi-main">
            <div class="kpi-values">
              <h3 class="kpi-amount font-weight-bold m-0">Bs. {{ accounts_receivable | formatNumber }}</h3>
            </div>
            <kpi-sparkline class="kpi-spark" :data="trend.accounts_receivable" :labels="trend.labels" :formatter="trendFormatter('accounts_receivable')" color="#f59e0b"></kpi-sparkline>
          </div>
          <small
            v-if="changes.accounts_receivable"
            class="kpi-change"
            :class="changes.accounts_receivable.up ? 'is-up' : 'is-down'"
          >
            {{ changes.accounts_receivable.up ? "▲" : "▼" }} {{ changes.accounts_receivable.pct | formatNumber(1, 1) }}%
            <span class="kpi-change-label text-muted">{{ changeLabel }}</span>
          </small>
        </div>
      </div>
    </div>
    <div class="kpi-col col">
      <div class="card card-dashboard h-100">
        <div class="card-body card-kpi justify-content-start">
          <small class="kpi-title text-muted">Utilidad neta</small>
          <div class="kpi-main">
            <div class="kpi-values">
              <h3 class="kpi-amount font-weight-bold m-0">Bs. {{ net_utility | formatNumber }}</h3>
            </div>
            <kpi-sparkline class="kpi-spark" :data="trend.net_utility" :labels="trend.labels" :formatter="trendFormatter('net_utility')" color="#16a34a"></kpi-sparkline>
          </div>
          <small
            v-if="changes.net_utility"
            class="kpi-change"
            :class="changes.net_utility.up ? 'is-up' : 'is-down'"
          >
            {{ changes.net_utility.up ? "▲" : "▼" }} {{ changes.net_utility.pct | formatNumber(1, 1) }}%
            <span class="kpi-change-label text-muted">{{ changeLabel }}</span>
          </small>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import moment from "moment";
import KpiSparkline from "./KpiSparkline.vue";
import { readOriginal, rawChange, originalNumber, formatUnitOriginal } from "../../../../../../resources/js/helpers/dashboard-originals";
// ######## INICIO CALCULOS EXACTOS SAB ########
const emptyTrend = () => ({labels: [], monthly_sales: [], average_ticket: [], accounts_receivable: [], net_utility: [], calculation_snapshots: []});

export default {
  props: ["company", "utilities", "filters"],
  components: { KpiSparkline },
  data() {
    return {monthly_sales: null, average_ticket: null, accounts_receivable: null, net_utility: null,
      trend: emptyTrend(), fetchVersion: 0, loading: false, fetchFailed: false};
  },
  mounted() {
    this.onFetchData();
  },
  watch: {
    filters: {
      deep: true,
      handler() {
        this.onFetchData();
      },
    },
  },
  computed: {
    changes() {
      const snapshots = this.trend.calculation_snapshots || [];
      const previous = snapshots[snapshots.length - 2];
      const current = snapshots[snapshots.length - 1];
      const result = {};
      for (const key of ["monthly_sales", "average_ticket", "accounts_receivable", "net_utility"]) {
        result[key] = rawChange(readOriginal({calculation_snapshot: current}, key), readOriginal({calculation_snapshot: previous}, key));
      }
      return result;
    },
    changeLabel() {
      const period = this.filters && this.filters.period ? this.filters.period : "month";
      const labels = {
        all: "vs mes anterior",
        last_week: "vs semana anterior",
        month: "vs mes anterior",
        between_months: "vs periodo anterior",
        date: "vs día anterior",
        between_dates: "vs periodo anterior",
      };

      return labels[period] || "vs periodo anterior";
    },
    salesTitle() {
      const period = this.filters && this.filters.period ? this.filters.period : "month";
      const titles = {
        all: "Ventas totales",
        last_week: "Ventas de la semana",
        month: "Ventas del mes",
        between_months: "Ventas entre meses",
        date: "Venta del dia",
        between_dates: "Ventas entre fechas",
      };

      return titles[period] || "Ventas";
    },
    isDueWarning() {
      if (this.company.certificate_due) {
        const dueDate = moment(this.company.certificate_due);

        const now = moment();
        const diffInDays = dueDate.diff(now, 'days')
        return diffInDays <= 15;
      }
      return false;
    },
  },
  methods: {
    trendFormatter(key) {
      return (_coordinate, options) => formatUnitOriginal(readOriginal({
        calculation_snapshot: (this.trend.calculation_snapshots || [])[options && options.dataPointIndex]
      }, key), 'money');
    },
    async onFetchData() {
      const version = ++this.fetchVersion;
      this.loading = true; this.fetchFailed = false;
      for (const key of ["monthly_sales", "average_ticket", "accounts_receivable", "net_utility"]) this[key] = null;
      this.trend = emptyTrend();
      try {
        const response = await this.$http.get("/dashboard/global-data", {params: {...(this.filters || {})}});
        if (version !== this.fetchVersion) return;
        const data = response.data;
        for (const key of ["monthly_sales", "average_ticket", "accounts_receivable", "net_utility"]) this[key] = readOriginal(data, key);
        this.trend = data.trend || emptyTrend();
      } catch (_) {
        if (version === this.fetchVersion) this.fetchFailed = true;
      } finally {
        if (version === this.fetchVersion) this.loading = false;
      }
    },
  },
  filters: {
    formatNumber(value, baseDecimals = 2, suffixDecimals = 1) {
      return originalNumber(value, baseDecimals, suffixDecimals);
    },
  },
};
// ######## FIN CALCULOS EXACTOS SAB ########
</script>
<style>
.card-green {
  background-color: var(--success);
  color: white;
}
.is-due-warning {
  background-color: var(--danger);
}
.card-green .card-title {
  color: white;
}
.kpi-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(285px, 1fr));
  gap: 1rem;
}
.kpi-row .kpi-col,
.kpi-row .card.card-dashboard {
  min-width: 0;
}
.kpi-row .card.card-dashboard {
  overflow: hidden;
}
.kpi-row .card-kpi {
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  justify-content: flex-start;
  min-height: 96px;
  padding: 1rem 1.1rem;
}
.kpi-row .card-kpi .kpi-title {
  display: block;
  line-height: 1.15;
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
  letter-spacing: 0.04em;
  font-size: 0.72rem;
}
.kpi-row .kpi-main {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  justify-content: space-between;
  min-width: 0;
  width: 100%;
}
.kpi-row .kpi-values {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
}
.kpi-row .kpi-amount {
  font-size: clamp(1.25rem, 1.7vw, 1.6rem);
  line-height: 1.15;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.kpi-row .kpi-spark {
  flex: 0 0 96px;
  width: 96px;
  max-width: 96px;
}
.kpi-row .card-kpi .kpi-change {
  display: block;
  text-transform: none;
  letter-spacing: normal;
  font-size: 0.74rem;
  font-weight: 600;
  margin: 4px 0 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.kpi-row .card-kpi .kpi-change.is-up {
  color: var(--success);
}
.kpi-row .card-kpi .kpi-change.is-down {
  color: var(--danger);
}
.kpi-row .card-kpi .kpi-change .kpi-change-label {
  font-weight: 400;
  margin-left: 4px;
}
@media (max-width: 575px) {
  .kpi-row .card-kpi {
    min-height: 90px;
  }
  .kpi-row .kpi-spark {
    flex-basis: 76px;
    max-width: 76px;
    width: 76px;
  }
}
</style>

<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
