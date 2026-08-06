<template>
  <div v-if="visibleBadges.length" class="sp-trust-badges" :class="rootClass">
    <div
      v-for="(badge, index) in visibleBadges"
      :key="index"
      class="sp-trust-badge"
    >
      <span class="sp-trust-badge__icon" aria-hidden="true" v-html="iconSvg(badge.icon)"></span>
      <span class="sp-trust-badge__text">{{ badge.text }}</span>
    </div>
  </div>
</template>

<script>
const ICONS = {
  shield: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
  refresh: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>',
  truck: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
  lock: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
  check: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
};

const DEFAULT_BADGES = [
  { icon: 'shield', text: 'Pago 100% Seguro' },
  { icon: 'refresh', text: 'Devolución Garantizada' },
  { icon: 'truck', text: 'Envío Rápido' },
];

export default {
  name: 'TrustBadges',
  props: {
    badges: {
      type: Array,
      default: null,
    },
    enabled: {
      type: Boolean,
      default: true,
    },
    compact: {
      type: Boolean,
      default: false,
    },
    fetchOnMount: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      remoteBadges: null,
      remoteEnabled: true,
      loaded: !this.fetchOnMount,
    };
  },
  computed: {
    visibleBadges() {
      if (!this.enabled || !this.remoteEnabled) {
        return [];
      }
      const source = Array.isArray(this.badges) && this.badges.length
        ? this.badges
        : (Array.isArray(this.remoteBadges) && this.remoteBadges.length ? this.remoteBadges : DEFAULT_BADGES);
      return source.filter((b) => b && b.text);
    },
    rootClass() {
      return {
        'sp-trust-badges--compact': this.compact,
      };
    },
  },
  created() {
    if (this.fetchOnMount) {
      this.loadRemote();
    }
  },
  methods: {
    iconSvg(name) {
      return ICONS[name] || ICONS.shield;
    },
    loadRemote() {
      const url = '/ecommerce/social-proof/trust-badges';
      fetch(url, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((payload) => {
          this.remoteEnabled = payload.enabled !== false;
          this.remoteBadges = Array.isArray(payload.data) ? payload.data : [];
        })
        .catch(() => {
          this.remoteBadges = DEFAULT_BADGES;
        })
        .finally(() => {
          this.loaded = true;
        });
    },
  },
};
</script>

<style scoped>
.sp-trust-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 16px;
  margin: 12px 0;
}
.sp-trust-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #2f3a4a;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.2;
}
.sp-trust-badge__icon {
  display: inline-flex;
  color: #1f7a4c;
}
.sp-trust-badges--compact {
  gap: 8px 12px;
  margin: 8px 0;
}
.sp-trust-badges--compact .sp-trust-badge {
  font-size: 12px;
  font-weight: 500;
}
</style>
