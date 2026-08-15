<template>
  <ul class="wg-ranking">
    <li v-for="(row, index) in rows" :key="index" class="wg-ranking-item">
      <span class="wg-ranking-rank">{{ index + 1 }}</span>
      <div class="wg-ranking-main">
        <div class="wg-ranking-head">
          <span class="wg-ranking-label text-truncate" :title="row.label">{{ row.label }}</span>
          <span class="wg-ranking-value">{{ row.valueStr }}</span>
        </div>
        <div class="wg-ranking-track">
          <div class="wg-ranking-bar" :style="{ width: row.pct + '%' }"></div>
        </div>
      </div>
    </li>
    <li v-if="!rows.length" class="wg-empty text-muted text-center">Sin datos en el periodo.</li>
  </ul>
</template>

<script>
import { formatValue } from '../registry'

/** Ranking con barras de progreso relativo al mayor valor. */
export default {
  name: 'RankingRenderer',
  props: {
    dataset: { type: Object, required: true },
    limit: { type: Number, default: 8 },
  },
  computed: {
    rows() {
      const labels = this.dataset.breakdown.labels || []
      const values = this.dataset.breakdown.values || []
      const max = values.reduce((m, v) => Math.max(m, Number(v) || 0), 0)

      return labels.slice(0, this.limit).map((label, i) => ({
        label,
        valueStr: formatValue(values[i], this.dataset.unit),
        pct: max > 0 ? Math.max(4, Math.round(((Number(values[i]) || 0) / max) * 100)) : 0,
      }))
    },
  },
}
</script>

<style scoped>
.wg-ranking {
  flex: 1 1 auto;
  list-style: none;
  margin: 0;
  min-height: 0;
  overflow-y: auto;
  padding: 0;
}
.wg-ranking-item {
  align-items: center;
  display: flex;
  gap: 0.6rem;
  padding: 0.35rem 0;
}
.wg-ranking-rank {
  color: #9ca3af;
  flex-shrink: 0;
  font-size: 0.78rem;
  font-weight: 700;
  text-align: center;
  width: 1.2rem;
}
.wg-ranking-main {
  flex: 1 1 auto;
  min-width: 0;
}
.wg-ranking-head {
  align-items: baseline;
  display: flex;
  gap: 0.5rem;
  justify-content: space-between;
}
.wg-ranking-label {
  font-size: 0.85rem;
  min-width: 0;
}
.wg-ranking-value {
  flex-shrink: 0;
  font-size: 0.82rem;
  font-weight: 700;
}
.wg-ranking-track {
  background: color-mix(in srgb, var(--primary) 10%, transparent);
  border-radius: 3px;
  height: 5px;
  margin-top: 0.25rem;
  overflow: hidden;
}
.wg-ranking-bar {
  background: var(--primary);
  border-radius: 3px;
  height: 100%;
}
.wg-empty {
  padding: 2rem 0;
}
</style>
