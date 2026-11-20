<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
  <section class="card card-dashboard pm-panel">
    <div class="card-body">
      <div class="pm-head">
        <h5 class="pm-title m-0">¿Cómo me pagan?</h5>
        <small class="text-muted">{{ subtitle }}</small>
      </div>

      <div v-if="labels.length" class="pm-body">
        <div class="pm-chart">
          <apexchart type="donut" height="220" :options="chartOptions" :series="series"></apexchart>
        </div>
        <ul class="pm-legend">
          <li v-for="(label, index) in labels" :key="index" class="pm-legend-item">
            <span class="pm-dot" :style="{ background: palette[index % palette.length] }"></span>
            <span class="pm-label text-truncate">{{ label }}</span>
            <span class="pm-value">Bs. {{ values[index] | pmMoney }}</span>
          </li>
        </ul>
      </div>

      <div v-else class="pm-empty text-muted text-center">{{ emptyText }}</div>
    </div>
  </section>
</template>

<script>
// ######## INICIO CALCULOS EXACTOS PRO9 ########
import { readOriginal, readVector, chartCoordinate, originalNumber, formatUnitOriginal } from "../../../../../../../resources/js/helpers/dashboard-originals";
export default {
  name: "PaymentMethods",
  props: {
    filters: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      fetchVersion: 0, loading: true, fetchFailed: false,
      labels: [],
      values: [],
      total: null,
      subtitle: "Distribucion de cobros - mes actual",
      palette: ["var(--primary)", "var(--danger)", "var(--success)", "var(--info)", "var(--warning)", "#ffdd00"],
    };
  },
  mounted() {
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
    series() { return this.values.map(chartCoordinate); },
    emptyText() {
      if (this.loading) return "Cargando cobros…";
      if (this.fetchFailed) return "No se pudieron cargar los cobros.";
      return this.subtitle ? `Sin cobros registrados (${this.subtitle.toLowerCase()}).` : "Sin cobros registrados.";
    },
    chartOptions() {
      return {
        chart: { fontFamily: "inherit" },
        labels: this.labels,
        colors: this.palette,
        stroke: { width: 0 },
        legend: { show: false },
        dataLabels: { enabled: false },
        plotOptions: {
          pie: {
            donut: {
              size: "72%",
              labels: {
                show: true,
                value: {
                  fontSize: "18px",
                  fontWeight: 700,
                  formatter: () => this.total === null ? "—" : "Bs. " + this.formatK(this.total),
                },
                total: {
                  show: true,
                  showAlways: true,
                  label: "COBRADO",
                  color: "#9ca3af",
                  fontSize: "11px",
                  formatter: () => "Bs. " + this.formatK(this.total),
                },
              },
            },
          },
        },
        tooltip: {
          theme: "dark",
          style: { fontSize: "12px" },
          y: {
            formatter: (_coordinate, options) => formatUnitOriginal(this.values[options && options.seriesIndex], 'money'),
          },
        },
      };
    },
  },
  methods: {
    async fetchData() {
      const version = ++this.fetchVersion; this.loading = true; this.fetchFailed = false;
      this.labels = []; this.values = []; this.total = null;
      try {
        const response = await this.$http.get("/dashboard/payment-methods", {params: {...(this.filters || {})}});
        if (version !== this.fetchVersion) return;
        const data = response.data;
        this.labels = data.labels || []; this.values = readVector(data, 'values_fractions');
        this.total = readOriginal(data, 'total'); this.subtitle = data.subtitle || this.subtitle;
      } catch (_) {
        if (version === this.fetchVersion) this.fetchFailed = true;
      } finally {
        if (version === this.fetchVersion) this.loading = false;
      }
    },
    formatK(val) { return originalNumber(val); },
  },
  filters: {
    pmMoney(value) { return originalNumber(value); },
  },
};
// ######## FIN CALCULOS EXACTOS PRO9 ########
</script>

<style scoped>
.pm-panel {
  height: 100%;
}
.pm-head {
  margin-bottom: 1rem;
}
.pm-title {
  font-size: 1.05rem;
  font-weight: 600;
}
.pm-body {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}
.pm-chart {
  flex: 1 1 180px;
  min-width: 160px;
}
.pm-legend {
  flex: 1 1 160px;
  list-style: none;
  margin: 0;
  padding: 0;
}
.pm-legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0;
}
.pm-dot {
  flex-shrink: 0;
  width: 10px;
  height: 10px;
  border-radius: 3px;
}
.pm-label {
  flex: 1 1 auto;
  min-width: 0;
  font-size: 0.85rem;
}
.pm-value {
  flex-shrink: 0;
  font-weight: 700;
  font-size: 0.85rem;
}
.pm-empty {
  padding: 2rem 0;
}
.pm-panel ::v-deep .apexcharts-tooltip {
  background: rgba(33, 37, 41, 0.96) !important;
  border: 0 !important;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25) !important;
  color: #fff !important;
}
.pm-panel ::v-deep .apexcharts-tooltip *,
.pm-panel ::v-deep .apexcharts-tooltip-title {
  color: #fff !important;
  background: transparent !important;
  border: 0 !important;
}
</style>

<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
