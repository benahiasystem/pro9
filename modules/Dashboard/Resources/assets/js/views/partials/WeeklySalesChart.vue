<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
  <section class="card card-dashboard ws-panel">
    <div class="card-body">
      <div class="ws-head">
        <div>
          <h5 class="ws-title m-0">{{ chartTitle }}</h5>
          <small class="text-muted">{{ subtitle }}</small>
        </div>
        <span v-if="variation !== null" class="ws-badge" :class="variation.up ? 'is-up' : 'is-down'">
          {{ variation.up ? "+" : "-" }}{{ percentText(variation.pct) }}% vs periodo ant.
        </span>
      </div>
      <apexchart type="bar" height="300" :options="chartOptions" :series="series"></apexchart>
    </div>
  </section>
</template>

<script>
// ######## INICIO CALCULOS EXACTOS PRO9 ########
import { readOriginal, readVector, rawChange, chartCoordinate, formatUnitOriginal, formatAxisTick, originalText } from "../../../../../../../resources/js/helpers/dashboard-originals";
export default {
  name: "WeeklySalesChart",
  props: {
    filters: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      fetchVersion: 0, loading: true, fetchFailed: false,
      labels: [],
      previousLabels: [],
      current: [],
      previous: [],
      currentTotal: null, previousTotal: null,
      subtitle: "Barras solidas = esta semana - gris = semana anterior",
      barColor: "#0f766e",
    };
  },
  mounted() {
    this.resolveColor();
    this.fetchData();
  },
  watch: {
    filters: {
      deep: true,
      handler() {
        this.fetchData();
      },
    },
  },
  computed: {
    chartTitle() {
      const period = this.filters && this.filters.period ? this.filters.period : "last_week";
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
    series() {
      return [
        { name: "Actual", data: this.current.map(chartCoordinate) },
        { name: "Periodo anterior", data: this.previous.map(chartCoordinate) },
      ];
    },
    variation() { return rawChange(this.currentTotal, this.previousTotal, false); },
    chartOptions() {
      return {
        chart: { toolbar: { show: false }, fontFamily: "inherit" },
        colors: [this.barColor, "#e2e8f0"],
        plotOptions: { bar: { columnWidth: "55%", borderRadius: 4 } },
        dataLabels: { enabled: false },
        legend: { show: false },
        grid: { borderColor: "#eef0f3", strokeDashArray: 4 },
        xaxis: {
          categories: this.labels,
          axisBorder: { show: false },
          axisTicks: { show: false },
          labels: { style: { colors: "#9ca3af", fontSize: "12px" } },
        },
        yaxis: {
          labels: {
            style: { colors: "#9ca3af", fontSize: "12px" },
            formatter: (val) => this.formatK(val),
          },
        },
        tooltip: {
          shared: false,
          intersect: true,
          x: {
            formatter: (val, opts) => {
              const idx = opts && opts.dataPointIndex != null ? opts.dataPointIndex : 0;
              const seriesIndex = opts ? opts.seriesIndex : 0;
              if (seriesIndex === 1) {
                return this.previousLabels[idx] || val;
              }
              return this.labels[idx] || val;
            },
          },
          y: {
            formatter: (_coordinate, options) => formatUnitOriginal(
              (options && options.seriesIndex === 1 ? this.previous : this.current)[options && options.dataPointIndex], 'money'),
          },
        },
      };
    },
  },
  methods: {
    resolveColor() {
      const success = getComputedStyle(document.documentElement).getPropertyValue("--success").trim();
      if (success) this.barColor = success;
    },
    async fetchData() {
      const version = ++this.fetchVersion; this.loading = true; this.fetchFailed = false;
      this.labels = []; this.previousLabels = []; this.current = []; this.previous = []; this.currentTotal = null; this.previousTotal = null;
      try {
        const response = await this.$http.get("/dashboard/sales-week", {params: {...(this.filters || {})}});
        if (version !== this.fetchVersion) return;
        const data = response.data;
        this.labels = data.labels || []; this.previousLabels = data.previous_labels || [];
        this.current = readVector(data, 'current_fractions'); this.previous = readVector(data, 'previous_fractions');
        this.currentTotal = readOriginal(data, 'current_total'); this.previousTotal = readOriginal(data, 'previous_total');
        this.subtitle = data.subtitle || this.subtitle;
      } catch (_) {
        if (version === this.fetchVersion) this.fetchFailed = true;
      } finally {
        if (version === this.fetchVersion) this.loading = false;
      }
    },
    formatK(val) { return formatAxisTick(val, 'count'); },
    percentText(value) { return originalText(value, 0); },
  },
};
// ######## FIN CALCULOS EXACTOS PRO9 ########
</script>

<style scoped>
.ws-panel {
  height: 100%;
}
.ws-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}
.ws-title {
  font-size: 1.05rem;
  font-weight: 600;
}
.ws-badge {
  flex-shrink: 0;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
}
.ws-badge.is-up {
  color: var(--success);
  background: color-mix(in srgb, var(--success) 12%, transparent);
}
.ws-badge.is-down {
  color: var(--danger);
  background: color-mix(in srgb, var(--danger) 12%, transparent);
}
</style>

<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
