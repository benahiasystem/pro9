<template>
  <div class="emoji-picker el-input--small">
    <button type="button" class="emp-trigger el-input__inner" :class="{ 'is-empty': !emoji }" @click.prevent="open">
      <span class="emp-trigger__emoji" v-if="emoji">{{ emoji }}</span>
      <span class="emp-trigger__label">{{ triggerLabel }}</span>
      <i class="el-icon-arrow-down emp-trigger__caret"></i>
    </button>

    <el-dialog
      title="Elegir emoji"
      :visible.sync="visible"
      width="760px"
      append-to-body
      custom-class="emp-dialog"
      @opened="onOpened"
    >
      <div class="row mb-3 mt-2">
        <div class="col-md-7">
          <el-input
            ref="search"
            v-model="query"
            clearable
            placeholder="Buscar: regalo, fuego, corazón, camión, fiesta…"
            prefix-icon="el-icon-search"
          ></el-input>
        </div>
        <div class="col-md-5 mt-2 mt-md-0">
          <el-select v-model="group" class="w-100" placeholder="Todas las categorías" clearable>
            <el-option v-for="item in groups" :key="item.g" :label="item.n" :value="item.g"></el-option>
          </el-select>
        </div>
      </div>

      <div v-loading="loading" element-loading-text="Cargando emojis…" class="emp-body">
        <p v-if="error" class="text-danger mb-0 py-4 text-center">
          {{ error }}
        </p>
        <p v-else-if="!loading && !results.length" class="text-muted mb-0 py-4 text-center">
          No se encontraron emojis para “{{ query }}”.
        </p>
        <div v-else class="emp-grid">
          <button
            v-for="item in visibleResults"
            :key="item.u"
            type="button"
            class="emp-cell"
            :class="{ 'is-active': item.u === emoji }"
            :title="item.l"
            @click.prevent="choose(item)"
          >{{ item.u }}</button>
        </div>
      </div>

      <div slot="footer" class="d-flex justify-content-between align-items-center">
        <small class="text-muted">
          <template v-if="!loading && !error">
            Mostrando {{ visibleResults.length }} de {{ results.length }} emojis
          </template>
        </small>
        <el-button size="small" @click.prevent="visible = false">Cerrar</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
const CATALOG_URL = '/json/emojis.json';
const MAX_RENDERED = 300;

const TOKEN_DECAY = 0.85;

const SCORE = {
  exactLabel: 100,
  labelWord: 60,
  labelPrefix: 40,
  labelIncludes: 25,
  exactTag: 15,
  tagIncludes: 6,
};

const stripAccents = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');

function scoreToken(item, token, weight) {
  let base = 0;

  if (item.label === token) {
    base = SCORE.exactLabel;
  } else if (item.words.indexOf(token) !== -1) {
    base = SCORE.labelWord;
  } else if (item.label.indexOf(token) === 0) {
    base = SCORE.labelPrefix;
  } else if (item.label.indexOf(token) !== -1) {
    base = SCORE.labelIncludes;
  } else if (item.tagList.indexOf(token) !== -1) {
    base = SCORE.exactTag;
  } else if (item.tags.indexOf(token) !== -1) {
    base = SCORE.tagIncludes;
  }

  return base * weight;
}

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
      .then((payload) => {
        const emojis = (Array.isArray(payload.emojis) ? payload.emojis : []).map((item) => {
          const label = stripAccents(item.l.toLowerCase());
          const tags = stripAccents((item.t || '').toLowerCase());

          return {
            u: item.u,
            l: item.l,
            g: item.g,
            label,
            tags,
            words: label.split(' '),
            tagList: tags ? tags.split(' ') : [],
          };
        });

        return {
          groups: Array.isArray(payload.groups) ? payload.groups : [],
          emojis,
        };
      })
      .catch((err) => {
        catalogPromise = null;
        throw err;
      });
  }

  return catalogPromise;
}

export default {
  name: 'EmojiPicker',
  props: {
    emoji: {
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
      group: '',
      groups: [],
      emojis: [],
      debounceTimer: null,
    };
  },
  computed: {
    triggerLabel() {
      if (!this.emoji) {
        return 'Elegir emoji';
      }

      const match = this.emojis.find((item) => item.u === this.emoji);

      return match ? match.l : 'Emoji seleccionado';
    },
    searchTokens() {
      const raw = stripAccents(this.debouncedQuery.toLowerCase()).trim();

      if (!raw) {
        return [];
      }

      return raw.split(/\s+/).filter(Boolean);
    },
    results() {
      let list = this.emojis;

      if (this.group !== '' && this.group !== null) {
        list = list.filter((item) => item.g === this.group);
      }

      const tokens = this.searchTokens;
      if (!tokens.length) {
        return list;
      }

      const scored = [];

      for (let i = 0; i < list.length; i += 1) {
        const item = list[i];
        let total = 0;
        let matched = 0;

        for (let t = 0; t < tokens.length; t += 1) {
          const value = scoreToken(item, tokens[t], Math.pow(TOKEN_DECAY, t));
          if (value > 0) {
            matched += 1;
            total += value;
          }
        }

        if (matched > 0) {
          scored.push({ item, total, matched });
        }
      }

      scored.sort((a, b) => (
        b.total - a.total
        || b.matched - a.matched
        || a.item.l.length - b.item.l.length
        || a.item.l.localeCompare(b.item.l)
      ));

      return scored.map((entry) => entry.item);
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
  mounted() {
    if (this.emoji) {
      this.fetchEmojis();
    }
  },
  beforeDestroy() {
    clearTimeout(this.debounceTimer);
  },
  methods: {
    open() {
      this.visible = true;
      this.fetchEmojis();
    },
    onOpened() {
      const input = this.$refs.search;
      if (input && typeof input.focus === 'function') {
        input.focus();
      }
    },
    fetchEmojis() {
      if (this.emojis.length || this.loading) {
        return;
      }

      this.loading = true;
      this.error = '';

      loadCatalog()
        .then((payload) => {
          this.groups = payload.groups;
          this.emojis = payload.emojis;
        })
        .catch(() => {
          this.error = 'No se pudo cargar el catálogo de emojis. Recarga la página e inténtalo de nuevo.';
        })
        .finally(() => {
          this.loading = false;
        });
    },
    choose(item) {
      this.$emit('select', item.u);
      this.visible = false;
    },
  },
};
</script>

<style scoped>
.emp-trigger {
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
.emp-trigger:hover {
  border-color: #c0c4cc;
}
.emp-trigger.is-empty {
  color: #c0c4cc;
}
.emp-trigger__emoji {
  flex: 0 0 auto;
  font-size: 18px;
  line-height: 1;
}
.emp-trigger__label {
  flex: 1 1 auto;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: left;
}
.emp-trigger__caret {
  flex: 0 0 auto;
  color: #c0c4cc;
}
.emp-body {
  min-height: 220px;
  max-height: 52vh;
  overflow-y: auto;
}
.emp-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(48px, 1fr));
  gap: 6px;
}
.emp-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 48px;
  padding: 0;
  font-size: 24px;
  line-height: 1;
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  cursor: pointer;
  transition: all .15s;
}
.emp-cell:hover {
  border-color: #409eff;
  background: #ecf5ff;
}
.emp-cell.is-active {
  border-color: #409eff;
  background: #ecf5ff;
  box-shadow: 0 0 0 1px #409eff inset;
}
</style>
