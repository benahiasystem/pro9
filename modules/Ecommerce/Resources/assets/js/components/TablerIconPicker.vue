<template>
  <div class="tabler-icon-picker el-input--small">
    <button type="button" class="tip-trigger el-input__inner" :class="{ 'is-empty': !icon }" @click.prevent="open">
      <span class="tip-trigger__icon" v-html="wrap(previewSvg)"></span>
      <span class="tip-trigger__label">{{ icon || 'Elegir ícono' }}</span>
      <i class="el-icon-arrow-down tip-trigger__caret"></i>
    </button>

    <el-dialog
      title="Elegir ícono"
      :visible.sync="visible"
      width="760px"
      append-to-body
      custom-class="tip-dialog"
      @opened="onOpened"
    >
      <div class="row mb-3 mt-2">
        <div class="col-md-7">
          <el-input
            ref="search"
            v-model="query"
            clearable
            placeholder="Buscar: escudo, envío, candado, pago, garantía…"
            prefix-icon="el-icon-search"
          ></el-input>
        </div>
        <div class="col-md-5 mt-2 mt-md-0">
          <el-select v-model="category" class="w-100" placeholder="Todas las categorías" clearable>
            <el-option v-for="cat in categories" :key="cat" :label="cat" :value="cat"></el-option>
          </el-select>
        </div>
      </div>

      <div v-loading="loading" element-loading-text="Cargando íconos…" class="tip-body">
        <p v-if="error" class="text-danger mb-0 py-4 text-center">
          {{ error }}
        </p>
        <p v-else-if="!loading && !results.length" class="text-muted mb-0 py-4 text-center">
          No se encontraron íconos para “{{ query }}”.
        </p>
        <div v-else class="tip-grid">
          <button
            v-for="item in visibleResults"
            :key="item.n"
            type="button"
            class="tip-cell"
            :class="{ 'is-active': item.n === icon }"
            :title="item.n"
            @click.prevent="choose(item)"
          >
            <span class="tip-cell__icon" v-html="wrap(item.s)"></span>
            <span class="tip-cell__name">{{ item.n }}</span>
          </button>
        </div>
      </div>

      <div slot="footer" class="d-flex justify-content-between align-items-center">
        <small class="text-muted">
          <template v-if="!loading && !error">
            Mostrando {{ visibleResults.length }} de {{ results.length }} íconos
          </template>
        </small>
        <el-button size="small" @click.prevent="visible = false">Cerrar</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
const CATALOG_URL = '/json/tabler-icons.json';
const MAX_RENDERED = 240;
const ES_EN = {
  agenda: 'calendar', alerta: 'alert', ambulancia: 'ambulance', avion: 'plane',
  aviso: 'alert bell', ayuda: 'help question', banco: 'bank', bandera: 'flag',
  bicicleta: 'bike', bolsa: 'bag shopping', boleta: 'receipt', buscar: 'search',
  caja: 'box package', calendario: 'calendar', calidad: 'award certificate',
  camara: 'camera', camion: 'truck', campana: 'bell', candado: 'lock',
  carrito: 'shopping-cart cart', casa: 'home house', celular: 'mobile phone',
  certificado: 'certificate', chat: 'message', cliente: 'user', cohete: 'rocket',
  comentario: 'message', comprobante: 'receipt', contacto: 'address',
  corazon: 'heart', correo: 'mail', cupon: 'ticket discount', delivery: 'truck-delivery',
  descarga: 'download', descuento: 'discount percentage', devolucion: 'return refresh rotate',
  direccion: 'map-pin', dinero: 'cash coin currency', efectivo: 'cash',
  entrega: 'truck-delivery package', envio: 'truck package send',
  escudo: 'shield', estrella: 'star', etiqueta: 'tag', factura: 'invoice receipt',
  fuego: 'flame', garantia: 'shield certificate award', gratis: 'gift',
  huella: 'fingerprint', likes: 'thumb-up', llave: 'key', mapa: 'map',
  medalla: 'medal award', mensaje: 'message', moneda: 'coin currency',
  mundo: 'world globe', musica: 'music', oferta: 'discount tag',
  pago: 'credit-card cash payment', paquete: 'package box', pedido: 'shopping-cart order',
  precio: 'tag price', premio: 'award trophy', proteccion: 'shield lock',
  rapido: 'bolt rocket', recibo: 'receipt', regalo: 'gift', reloj: 'clock',
  reembolso: 'return cash refresh', seguridad: 'shield lock security',
  seguro: 'shield lock', soles: 'currency-sol', soporte: 'headset help',
  tarjeta: 'credit-card', telefono: 'phone', tienda: 'store shop',
  tiempo: 'clock', ubicacion: 'map-pin', usuario: 'user', verificado: 'check verified',
  video: 'video movie', whatsapp: 'whatsapp',
};

const TOKEN_DECAY = 0.85;

const SCORE = {
  exactName: 100,
  nameSegment: 60,
  namePrefix: 40,
  nameIncludes: 25,
  exactTag: 15,
  tagIncludes: 6,
  category: 2,
};

function scoreToken(icon, token, weight) {
  let base = 0;

  if (icon.n === token) {
    base = SCORE.exactName;
  } else if (icon.segments.indexOf(token) !== -1) {
    base = SCORE.nameSegment;
  } else if (icon.n.indexOf(token) === 0) {
    base = SCORE.namePrefix;
  } else if (icon.n.indexOf(token) !== -1) {
    base = SCORE.nameIncludes;
  } else if (icon.tagList.indexOf(token) !== -1) {
    base = SCORE.exactTag;
  } else if (icon.t.indexOf(token) !== -1) {
    base = SCORE.tagIncludes;
  } else if (icon.cl.indexOf(token) !== -1) {
    base = SCORE.category;
  }

  return base * weight;
}

function scoreWord(icon, tokens) {
  let best = 0;

  for (let i = 0; i < tokens.length; i += 1) {
    const value = scoreToken(icon, tokens[i], Math.pow(TOKEN_DECAY, i));
    if (value > best) {
      best = value;
    }
  }

  return best;
}

const LEGACY = {
  shield: '<path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3"/>',
  refresh: '<path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>',
  truck: '<path d="M5 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M15 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5"/>',
  lock: '<path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6"/><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0"/><path d="M8 11v-4a4 4 0 1 1 8 0v4"/>',
  check: '<path d="M5 12l5 5l10 -10"/>',
};

let catalogPromise = null;

function loadCatalog() {
  if (!catalogPromise) {
    catalogPromise = fetch(CATALOG_URL, { headers: { Accept: 'application/json' } })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
      })
      .then((payload) => (Array.isArray(payload.icons) ? payload.icons : []).map((icon) => ({
        ...icon,
        segments: icon.n.split('-'),
        tagList: icon.t ? icon.t.split(' ') : [],
        cl: (icon.c || '').toLowerCase(),
      })))
      .catch((err) => {
        catalogPromise = null;
        throw err;
      });
  }

  return catalogPromise;
}

const stripAccents = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');

export default {
  name: 'TablerIconPicker',
  props: {
    icon: {
      type: String,
      default: '',
    },
    svg: {
      type: String,
      default: '',
    },
  },
  data() {
    return {
      visible: false,
      loading: false,
      error: '',
      query: '',
      debouncedQuery: '',
      category: '',
      icons: [],
      debounceTimer: null,
    };
  },
  computed: {
    previewSvg() {
      if (this.svg) {
        return this.svg;
      }

      const match = this.icons.find((item) => item.n === this.icon);

      return match ? match.s : (LEGACY[this.icon] || '');
    },
    categories() {
      return [...new Set(this.icons.map((item) => item.c).filter(Boolean))].sort();
    },
    searchTerms() {
      const raw = stripAccents(this.debouncedQuery.toLowerCase()).trim();
      if (!raw) {
        return [];
      }

      return raw
        .split(/\s+/)
        .filter(Boolean)
        .map((word) => (ES_EN[word] ? [word].concat(ES_EN[word].split(' ')) : [word]));
    },
    results() {
      let list = this.icons;

      if (this.category) {
        list = list.filter((item) => item.c === this.category);
      }

      const words = this.searchTerms;
      if (!words.length) {
        return list;
      }

      const scored = [];

      for (let i = 0; i < list.length; i += 1) {
        const icon = list[i];
        let total = 0;
        let matched = 0;

        for (let w = 0; w < words.length; w += 1) {
          const value = scoreWord(icon, words[w]);
          if (value > 0) {
            matched += 1;
            total += value;
          }
        }

        if (matched > 0) {
          scored.push({ icon, total, matched });
        }
      }

      scored.sort((a, b) => (
        b.total - a.total
        || b.matched - a.matched
        || a.icon.n.length - b.icon.n.length
        || a.icon.n.localeCompare(b.icon.n)
      ));

      return scored.map((entry) => entry.icon);
    },
    visibleResults() {
      return this.results.slice(0, MAX_RENDERED);
    },
  },
  watch: {
    query(value) {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => {
        this.debouncedQuery = value || '';
      }, 150);
    },
  },
  beforeDestroy() {
    clearTimeout(this.debounceTimer);
  },
  methods: {
    open() {
      this.visible = true;
      this.fetchIcons();
    },
    onOpened() {
      const input = this.$refs.search;
      if (input && typeof input.focus === 'function') {
        input.focus();
      }
    },
    fetchIcons() {
      if (this.icons.length || this.loading) {
        return;
      }

      this.loading = true;
      this.error = '';

      loadCatalog()
        .then((icons) => {
          this.icons = icons;
        })
        .catch(() => {
          this.error = 'No se pudo cargar el catálogo de íconos. Recarga la página e inténtalo de nuevo.';
        })
        .finally(() => {
          this.loading = false;
        });
    },
    wrap(inner) {
      if (!inner) {
        return '';
      }

      return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${inner}</svg>`;
    },
    choose(item) {
      this.$emit('select', { icon: item.n, svg: item.s });
      this.visible = false;
    },
  },
};
</script>

<style scoped>
.tip-trigger {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 0 10px;
  background: #fff;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  color: #606266;
  font-size: 13px;
  line-height: 1;
  cursor: pointer;
  transition: border-color .2s;
}
.tip-trigger:hover {
  border-color: #c0c4cc;
}
.tip-trigger.is-empty {
  color: #c0c4cc;
}
.tip-trigger__icon {
  display: inline-flex;
  flex: 0 0 auto;
  color: #1f7a4c;
}
.tip-trigger__label {
  flex: 1 1 auto;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: left;
}
.tip-trigger__caret {
  flex: 0 0 auto;
  color: #c0c4cc;
}
.tip-body {
  min-height: 220px;
  max-height: 52vh;
  overflow-y: auto;
}
.tip-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
  gap: 8px;
}
.tip-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 10px 4px;
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  cursor: pointer;
  transition: all .15s;
}
.tip-cell:hover {
  border-color: #409eff;
  background: #ecf5ff;
}
.tip-cell.is-active {
  border-color: #409eff;
  background: #ecf5ff;
  box-shadow: 0 0 0 1px #409eff inset;
}
.tip-cell__icon {
  display: inline-flex;
  color: #303133;
}
.tip-cell__icon >>> svg {
  width: 24px;
  height: 24px;
}
.tip-cell__name {
  font-size: 10px;
  line-height: 1.2;
  color: #909399;
  width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: center;
}
</style>
