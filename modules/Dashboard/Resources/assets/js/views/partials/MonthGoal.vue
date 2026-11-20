<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
  <section class="card card-dashboard mg-panel">
    <div class="card-body">
      <div class="mg-head">
        <h5 class="mg-title m-0">Meta del mes</h5>
        <small class="text-muted">Faltan Bs. {{ remaining | mgMoney }}</small>
      </div>

      <apexchart type="radialBar" height="260" :options="chartOptions" :series="[chartPercent]"></apexchart>

      <div class="mg-foot text-center">
        <div class="mg-detail">Bs. {{ sales | mgMoney }} de Bs. {{ goal | mgMoney }}</div>
        <small class="text-muted">A este ritmo cierras el mes en ~Bs. {{ projected | mgMoney }}</small>
      </div>
    </div>
  </section>
</template>

<script>
// ######## INICIO CALCULOS EXACTOS SAB ########
import { readOriginal, relativeWidth, originalText } from "../../../../../../../resources/js/helpers/dashboard-originals";
export default {
  name: "MonthGoal",
  data() {
    return {
      fetchVersion: 0, loading: true, fetchFailed: false,
      goal: null,
      sales: null,
      percent: null,
      remaining: null,
      projected: null,
      arcColor: "#0e2a33",
      themeObserver: null,
      themeRefreshTimer: null,
    };
  },
  mounted() {
    this.resolveColor();
    this.watchThemeChanges();
    this.fetchData();
  },
  beforeDestroy() {
    if (this.themeObserver) {
      this.themeObserver.disconnect();
    }
    clearTimeout(this.themeRefreshTimer);
  },
  computed: {
    chartPercent() {
      return relativeWidth(this.percent, '100');
    },
    chartOptions() {
      return {
        chart: { fontFamily: "inherit", sparkline: { enabled: true } },
        colors: [this.arcColor],
        plotOptions: {
          radialBar: {
            startAngle: -110,
            endAngle: 110,
            hollow: { size: "62%" },
            track: { background: "#e9edf1", strokeWidth: "100%" },
            dataLabels: {
              name: { show: false },
              value: {
                offsetY: 6,
                fontSize: "28px",
                fontWeight: 700,
                formatter: () => this.percent === null ? "—" : originalText(this.percent, 1) + "%",
              },
            },
          },
        },
        stroke: { lineCap: "round" },
      };
    },
  },
  methods: {
    resolveColor() {
      const styles = getComputedStyle(document.documentElement);
      const primary = styles.getPropertyValue("--primary").trim() || styles.getPropertyValue("--primary-color").trim();

      if (primary && primary !== this.arcColor) {
        this.arcColor = primary;
      }
    },
    scheduleColorRefresh() {
      clearTimeout(this.themeRefreshTimer);
      this.themeRefreshTimer = setTimeout(() => {
        this.resolveColor();
      }, 50);
    },
    watchThemeChanges() {
      if (typeof MutationObserver === "undefined") return;

      this.themeObserver = new MutationObserver(() => {
        this.scheduleColorRefresh();
      });

      this.themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["class", "style"],
      });

      if (document.body) {
        this.themeObserver.observe(document.body, {
          attributes: true,
          attributeFilter: ["class", "style"],
        });
      }

      if (document.head) {
        this.themeObserver.observe(document.head, {
          attributes: true,
          childList: true,
          subtree: true,
          attributeFilter: ["href", "class", "style"],
        });
      }
    },
    async fetchData() {
      const version = ++this.fetchVersion; this.loading = true; this.fetchFailed = false;
      for (const key of ["goal", "sales", "percent", "remaining", "projected"]) this[key] = null;
      try {
        const response = await this.$http.get("/dashboard/month-goal");
        if (version !== this.fetchVersion) return;
        const data = response.data;
        for (const key of ["goal", "sales", "percent", "remaining", "projected"]) this[key] = readOriginal(data, key);
      } catch (_) {
        if (version === this.fetchVersion) this.fetchFailed = true;
      } finally {
        if (version === this.fetchVersion) this.loading = false;
      }
    },
  },
  filters: {
    mgMoney(value) { return originalText(value); },
  },
};
// ######## FIN CALCULOS EXACTOS SAB ########
</script>

<style scoped>
.mg-panel {
  height: 100%;
}
.mg-head {
  margin-bottom: 0.5rem;
}
.mg-title {
  font-size: 1.05rem;
  font-weight: 600;
}
.mg-foot {
  margin-top: 0.5rem;
}
.mg-detail {
  font-weight: 600;
}
</style>

<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
