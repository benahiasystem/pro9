<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
  <section class="card card-dashboard cf-panel">
    <div class="card-body">
      <div class="cf-head">
        <div>
          <h5 class="cf-title m-0">Flujo de caja</h5>
          <small class="text-muted">{{ subtitle }}</small>
        </div>
        <div class="cf-legend">
          <span class="cf-legend-item"><i class="cf-dot cf-dot--income"></i>Ingresos</span>
          <span class="cf-legend-item"><i class="cf-dot cf-dot--egress"></i>Egresos</span>
        </div>
      </div>
      <apexchart type="area" height="260" :options="chartOptions" :series="series"></apexchart>
    </div>
  </section>
</template>

<script>
// ######## INICIO CALCULOS EXACTOS PRO9 ########
import { readVector, chartCoordinate, formatUnitOriginal, formatAxisTick } from "../../../../../../../resources/js/helpers/dashboard-originals";
export default {
  name: "CashFlowChart",
  props: {
    filters: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      fetchVersion: 0, loading: true, fetchFailed: false,
      labels: [],
      income: [],
      egress: [],
      subtitle: "Ingresos vs egresos - ultimos 6 meses",
      incomeColor: "#00c666",
      egressColor: "#ff8400",
    };
  },
  mounted() {
    this.resolveColors();
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
    series() {
      return [
        { name: "Ingresos", data: this.income.map(chartCoordinate) },
        { name: "Egresos", data: this.egress.map(chartCoordinate) },
      ];
    },
    chartOptions() {
      return {
        chart: { toolbar: { show: false }, fontFamily: "inherit", zoom: { enabled: false } },
        colors: [this.incomeColor, this.egressColor],
        stroke: { curve: "smooth", width: 2 },
        fill: {
          type: "gradient",
          gradient: { opacityFrom: 0.25, opacityTo: 0, stops: [0, 100] },
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        grid: { borderColor: "#eef0f3", strokeDashArray: 4, padding: { left: 8, right: 8 } },
        markers: { size: 0, hover: { size: 5 } },
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
          y: {
            formatter: (_coordinate, options) => formatUnitOriginal(
              (options && options.seriesIndex === 1 ? this.egress : this.income)[options && options.dataPointIndex], 'money'),
          },
        },
      };
    },
  },
  methods: {
    resolveColors() {
      const styles = getComputedStyle(document.documentElement);
      const success = styles.getPropertyValue("--success").trim();
      const warning = styles.getPropertyValue("--warning").trim();
      if (success) this.incomeColor = success;
      if (warning) this.egressColor = warning;
    },
    async fetchData() {
      const version = ++this.fetchVersion; this.loading = true; this.fetchFailed = false;
      this.labels = []; this.income = []; this.egress = [];
      try {
        const response = await this.$http.get("/dashboard/cash-flow", {params: {...(this.filters || {})}});
        if (version !== this.fetchVersion) return;
        const data = response.data;
        this.labels = data.labels || [];
        this.income = readVector(data, 'income_fractions'); this.egress = readVector(data, 'egress_fractions');
        this.subtitle = data.subtitle || this.subtitle;
      } catch (_) {
        if (version === this.fetchVersion) this.fetchFailed = true;
      } finally {
        if (version === this.fetchVersion) this.loading = false;
      }
    },
    formatK(val) { return formatAxisTick(val, 'count'); },
  },
};
// ######## FIN CALCULOS EXACTOS PRO9 ########
</script>

<style scoped>
.cf-panel {
  height: 100%;
}
.cf-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}
.cf-title {
  font-size: 1.05rem;
  font-weight: 600;
}
.cf-legend {
  display: flex;
  gap: 1rem;
}
.cf-legend-item {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.78rem;
  color: #6b7280;
}
.cf-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  display: inline-block;
}
.cf-dot--income {
  background: var(--success);
}
.cf-dot--egress {
  background: var(--warning);
}
</style>

<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
