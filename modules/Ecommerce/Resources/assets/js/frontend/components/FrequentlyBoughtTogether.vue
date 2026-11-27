<template>
  <div v-if="showSection" class="sp-fbt">
    <div class="sp-fbt__header">
      <h3 class="sp-fbt__title">{{ title }}</h3>
      <p v-if="subtitle" class="sp-fbt__subtitle">{{ subtitle }}</p>
    </div>

    <div v-if="loading" class="sp-fbt__loading">Cargando recomendaciones…</div>

    <div v-else-if="items.length" class="sp-fbt__track-wrap">
      <button
        v-if="items.length > 1"
        type="button"
        class="sp-fbt__nav sp-fbt__nav--prev"
        aria-label="Anterior"
        @click="scrollBy(-1)"
      >‹</button>

      <div ref="track" class="sp-fbt__track">
        <a
          v-for="(item, index) in items"
          :key="item.id"
          class="sp-fbt__card"
          :href="item.url"
        >
          <div class="sp-fbt__image-wrap">
            <img v-catalog-image="{ small: item.image_url_small, original: item.image_url, eager: index < 4 }" :alt="item.description" />
          </div>
          <div class="sp-fbt__body">
            <div class="sp-fbt__name" :title="item.description">{{ item.description }}</div>
            <div class="sp-fbt__price">
              {{ item.currency_symbol }} {{ formatPrice(item.sale_unit_price, item.calculation_snapshot) }}
            </div>
            <div v-if="showCount && item.times_bought_together" class="sp-fbt__meta">
              Juntos {{ item.times_bought_together }} veces
            </div>
          </div>
        </a>
      </div>

      <button
        v-if="items.length > 1"
        type="button"
        class="sp-fbt__nav sp-fbt__nav--next"
        aria-label="Siguiente"
        @click="scrollBy(1)"
      >›</button>
    </div>
  </div>
</template>

<script>
import {catalogImage} from '../../../../../../../resources/js/tenant/catalog-images'

import {cartRequestIdentifier} from '../../../../../../../resources/js/helpers/storefront-cart-amounts'
import {exactAmount} from '../../../../../../../resources/js/helpers/exchange-rate-math'

export default {
  directives: { catalogImage },
  name: 'FrequentlyBoughtTogether',
  props: {
    itemId: {
      type: [Number, String],
      default: null,
    },
    itemIds: {
      type: Array,
      default: () => [],
    },
    limit: {
      type: Number,
      default: 8,
    },
    title: {
      type: String,
      default: 'Clientes que compraron esto también eligieron…',
    },
    subtitle: {
      type: String,
      default: 'Recomendaciones basadas en compras reales',
    },
    showCount: {
      type: Boolean,
      default: false,
    },
    autoFetch: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      items: [],
      loading: false,
      fetched: false,
    };
  },
  computed: {
    showSection() {
      return this.loading || this.items.length > 0 || !this.fetched;
    },
    resolvedIds() {
      if (Array.isArray(this.itemIds) && this.itemIds.length) {
        return this.itemIds.map(cartRequestIdentifier).filter((id) => id !== null);
      }
      const single = cartRequestIdentifier(this.itemId);
      return single === null ? [] : [single];
    },
  },
  watch: {
    resolvedIds: {
      deep: true,
      handler() {
        if (this.autoFetch) {
          this.fetchItems();
        }
      },
    },
  },
  created() {
    if (this.autoFetch) {
      this.fetchItems();
    }
  },
  methods: {
    formatPrice(value, snapshot) {
      return value == null ? '—' : exactAmount(snapshot?.values?.sale_unit_price ?? value).final(2);
    },
    scrollBy(direction) {
      const el = this.$refs.track;
      if (!el) return;
      const amount = Math.max(220, Math.floor(el.clientWidth * 0.8)) * direction;
      el.scrollBy({ left: amount, behavior: 'smooth' });
    },
    fetchItems() {
      if (!this.resolvedIds.length) {
        this.items = [];
        this.fetched = true;
        return;
      }

      this.loading = true;
      let url = `/ecommerce/social-proof/frequently-bought-together?limit=${this.limit}`;
      if (this.resolvedIds.length === 1) {
        url = `/ecommerce/social-proof/frequently-bought-together/${this.resolvedIds[0]}?limit=${this.limit}`;
      } else {
        url += `&item_ids=${this.resolvedIds.join(',')}`;
      }

      fetch(url, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((payload) => {
          this.items = Array.isArray(payload.data) ? payload.data : [];
        })
        .catch(() => {
          this.items = [];
        })
        .finally(() => {
          this.loading = false;
          this.fetched = true;
          if (!this.items.length) {
            this.$emit('empty');
          }
        });
    },
  },
};
</script>

<style scoped>
.sp-fbt {
  margin: 28px 0 8px;
}
.sp-fbt__header {
  margin-bottom: 14px;
}
.sp-fbt__title {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: #222;
}
.sp-fbt__subtitle {
  margin: 4px 0 0;
  font-size: 0.85rem;
  color: #6c757d;
}
.sp-fbt__loading {
  color: #6c757d;
  font-size: 0.9rem;
}
.sp-fbt__track-wrap {
  position: relative;
}
.sp-fbt__track {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  padding: 4px 2px 10px;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
}
.sp-fbt__card {
  flex: 0 0 160px;
  scroll-snap-align: start;
  border: 1px solid #e9ecef;
  border-radius: 10px;
  background: #fff;
  text-decoration: none;
  color: inherit;
  transition: box-shadow .15s ease, transform .15s ease;
  overflow: hidden;
}
.sp-fbt__card:hover {
  box-shadow: 0 6px 18px rgba(0,0,0,.08);
  transform: translateY(-2px);
  text-decoration: none;
  color: inherit;
}
.sp-fbt__image-wrap {
  aspect-ratio: 1 / 1;
  background: #f8f9fa;
  display: flex;
  align-items: center;
  justify-content: center;
}
.sp-fbt__image-wrap img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}
.sp-fbt__body {
  padding: 10px;
}
.sp-fbt__name {
  font-size: 0.82rem;
  font-weight: 600;
  line-height: 1.3;
  height: 2.6em;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
.sp-fbt__price {
  margin-top: 6px;
  font-weight: 700;
  color: #c70404;
  font-size: 0.95rem;
}
.sp-fbt__meta {
  margin-top: 4px;
  font-size: 0.72rem;
  color: #6c757d;
}
.sp-fbt__nav {
  position: absolute;
  top: 40%;
  z-index: 2;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 1px solid #dee2e6;
  background: #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,.08);
  cursor: pointer;
  font-size: 20px;
  line-height: 1;
  color: #333;
}
.sp-fbt__nav--prev { left: -6px; }
.sp-fbt__nav--next { right: -6px; }
@media (max-width: 575px) {
  .sp-fbt__card { flex-basis: 140px; }
  .sp-fbt__nav { display: none; }
}
</style>
