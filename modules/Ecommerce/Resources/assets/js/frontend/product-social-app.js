import Vue from 'vue';
import TrustBadges from './components/TrustBadges.vue';
import FrequentlyBoughtTogether from './components/FrequentlyBoughtTogether.vue';

function mountIfPresent(selector, component, props = {}) {
  const el = document.querySelector(selector);
  if (!el) {
    return null;
  }

  const datasetProps = { ...props };
  if (el.dataset.itemId) {
    datasetProps.itemId = parseInt(el.dataset.itemId, 10);
  }
  if (el.dataset.compact === '1' || el.dataset.compact === 'true') {
    datasetProps.compact = true;
  }
  if (el.dataset.fetchOnMount === '1' || el.dataset.fetchOnMount === 'true') {
    datasetProps.fetchOnMount = true;
  }
  if (el.dataset.title) {
    datasetProps.title = el.dataset.title;
  }

  return new Vue({
    el,
    render: (h) => h(component, { props: datasetProps }),
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const boot = window.__socialProofBoot || {};

  mountIfPresent('#product-trust-badges', TrustBadges, {
    badges: boot.trustBadges || null,
    enabled: boot.trustBadgesEnabled !== false,
    compact: false,
  });

  mountIfPresent('#product-frequently-bought', FrequentlyBoughtTogether, {
    itemId: boot.itemId || null,
    limit: boot.fbtLimit || 8,
    showCount: !!boot.showFbtCount,
  });
});
