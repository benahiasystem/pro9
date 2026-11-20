<template>
  <div class="wg-kpi">
    <div class="kpi-main wg-kpi-main">
      <div class="kpi-values">
        <h3 class="font-weight-bold m-0 text-nowrap wg-kpi-value">{{ valueStr }}</h3>
      </div>
      <kpi-sparkline
        v-if="spark"
        class="kpi-spark wg-kpi-spark"
        :data="sparkData"
        :labels="sparkLabels"
        :color="sparkColor"
        :height="sparkHeight"
        :formatter="sparkFormatter"
      ></kpi-sparkline>
    </div>
    <small v-if="hasDelta" class="kpi-change wg-kpi-delta" :class="deltaUp ? 'is-up' : 'is-down'">
      {{ deltaUp ? '▲' : '▼' }} {{ deltaStr }}
      <span class="kpi-change-label text-muted wg-kpi-delta-label">{{ changeLabel }}</span>
    </small>
  </div>
</template>

<script>
import KpiSparkline from '../../views/KpiSparkline.vue'
import { changeLabelForPeriod, formatKpi, formatValue, themeColors } from '../registry'
import { readOriginal, readVector, compareOriginal, absoluteOriginal, originalText, chartCoordinate } from '../../../../../../../resources/js/helpers/dashboard-originals'
// ######## INICIO CALCULOS EXACTOS PRO9 ########

/** KPI (valor + delta) con sparkline opcional (kpi_spark). */
export default {
  name: 'KpiRenderer',
  components: { KpiSparkline },
  props: {
    dataset: { type: Object, required: true },
    spark: { type: Boolean, default: false },
    sparkHeight: { type: Number, default: 46 },
    period: { type: String, default: '' },
  },
  computed: {
    unit() {
      return this.dataset.unit || 'money'
    },
    valueStr() {
      return formatKpi(readOriginal(this.dataset.totals, 'current'), this.unit)
    },
    hasDelta() {
      const totals = this.dataset.totals || {}
      const previous = readOriginal(totals, 'previous')
      return previous !== null && readOriginal(totals, 'delta') !== null && compareOriginal(previous, '0') !== 0
    },
    deltaUp() {
      const sign = compareOriginal(readOriginal(this.dataset.totals, 'delta'), '0')
      return sign !== null && sign >= 0
    },
    deltaStr() {
      const text = originalText(absoluteOriginal(readOriginal(this.dataset.totals, 'delta')), 1)
      return text === '—' ? text : text + '%'
    },
    changeLabel() {
      return changeLabelForPeriod(this.period)
    },
    sparkOriginals() {
      return readVector((this.dataset.series || [])[0], 'data_fractions')
    },
    sparkData() {
      return this.sparkOriginals.map(chartCoordinate)
    },
    sparkLabels() {
      return this.dataset.labels || []
    },
    sparkColor() {
      const theme = themeColors()
      const known = this.sparkOriginals.filter(value => value !== null)
      if (known.length >= 2) return compareOriginal(known[known.length - 1], known[0]) >= 0 ? theme.success : theme.danger
      return this.hasDelta ? (this.deltaUp ? theme.success : theme.danger) : theme.muted
    },
    sparkFormatter() {
      return (_coordinate, options) => formatValue(this.sparkOriginals[options && options.dataPointIndex], this.unit)
    },
  },
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
</script>

<style scoped>
.wg-kpi {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  justify-content: center;
  min-height: 0;
  min-width: 0;
  overflow: hidden;
}
.wg-kpi-main {
  display: flex;
  flex: 0 1 auto;
  flex-direction: column;
  justify-content: center;
  min-width: 0;
  overflow: hidden;
}
.wg-kpi-value {
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.05;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.wg-kpi-delta {
  align-items: center;
  display: flex;
  font-size: 0.8rem;
  font-weight: 600;
  gap: 0.3rem;
  line-height: 1.1;
  margin-top: 0.15rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.wg-kpi-delta-label {
  color: #9ca3af;
  font-weight: 500;
}
.wg-kpi-spark {
  align-self: stretch;
  flex: 1 1 auto;
  min-width: 60px;
  overflow: hidden;
}
</style>
