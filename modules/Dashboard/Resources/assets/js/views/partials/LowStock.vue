<template>
  <section class="card card-dashboard ls-panel mt-2">
    <div class="card-body mt-0">
      <div class="ls-head">
        <h5 class="ls-title m-0">Stock por agotarse</h5>
        <small class="text-muted">{{ total }} {{ total === 1 ? "producto" : "productos" }} con 10 o menos en stock</small>
      </div>

      <ul class="ls-list stock">
        <li v-for="(row, index) in items" :key="index" class="ls-item">
          <span class="ls-name text-truncate">{{ row.product }}</span>
          <span class="ls-ratio">{{ stockText(row) }}</span>
        </li>
      </ul>

      <div v-if="!items.length" class="ls-empty text-muted text-center">Sin productos con 10 o menos en stock.</div>
    </div>
  </section>
</template>

<script>
// ######## INICIO CALCULOS EXACTOS PRO9 ########
import { readOriginal, originalDecimal } from "../../../../../../../resources/js/helpers/dashboard-originals";

export default {
  name: "LowStock",
  props: {
    filters: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      fetchVersion: 0, loading: true, fetchFailed: false,
      items: [],
      total: null,
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
  methods: {
    stockText(row) {
      const amount = readOriginal(row, 'stock');
      // The legacy stock contract also permits an unrounded stored decimal string.
      return originalDecimal(amount ?? (typeof row.stock === 'string' ? row.stock : null));
    },
    async fetchData() {
      const version = ++this.fetchVersion; this.loading = true; this.fetchFailed = false;
      this.items = []; this.total = null;
      try {
        const response = await this.$http.get("/dashboard/low-stock", {params: {...(this.filters || {})}});
        if (version !== this.fetchVersion) return;
        const data = response.data;
        this.items = data.items || []; this.total = Number.isInteger(data.total) && data.total >= 0 ? data.total : null;
      } catch (_) {
        if (version === this.fetchVersion) this.fetchFailed = true;
      } finally {
        if (version === this.fetchVersion) this.loading = false;
      }
    },
  },
};
// ######## FIN CALCULOS EXACTOS PRO9 ########
</script>

<style scoped>
.ls-panel {
  height: 100%;
  margin-bottom: 0 !important;
}
.ls-head {
  margin-bottom: 1rem;
}
.ls-title {
  font-size: 1.05rem;
  font-weight: 600;
}
.ls-list {
  list-style: none;
  margin: 0;
  max-height: 100px;
  overflow-y: auto;
  padding: 0 4px 0 0;
  scrollbar-color: rgba(155, 161, 173, 0.32) transparent;
  scrollbar-width: thin;
}
.ls-list::-webkit-scrollbar {
  width: 5px;
}
.ls-list::-webkit-scrollbar-track {
  background: transparent;
}
.ls-list::-webkit-scrollbar-thumb {
  background: rgba(155, 161, 173, 0.28);
  border-radius: 999px;
}
.ls-list:hover::-webkit-scrollbar-thumb {
  background: rgba(155, 161, 173, 0.46);
}
.ls-item {
  align-items: center;
  border-bottom: 1px solid #f1f3f5;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
  padding: 0.1rem 0;
  font-size: 14px;
}
.ls-item:last-child {
  border-bottom: 0;
}
.ls-name {
  font-weight: 500;
  min-width: 0;
}
.ls-ratio {
  color: var(--danger);
  flex-shrink: 0;
  font-weight: 700;
}
.ls-empty {
  padding: 2rem 0;
}
</style>
