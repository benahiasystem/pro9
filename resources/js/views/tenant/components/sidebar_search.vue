<template>
    <el-dialog
        :visible.sync="showDialog"
        :append-to-body="true"
        :modal-append-to-body="true"
        custom-class="sidebar-search-el-dialog"
        width="680px"
        top="9vh"
        @opened="focusInput"
        @closed="restoreFocus"
    >
        <template slot="title">
            <span class="el-dialog__title">Buscar en el sistema</span>
            <small class="sidebar-search-subtitle">Encuentra módulos, opciones y acciones por nombre o sinónimos.</small>
        </template>

        <el-input
            ref="searchInput"
            v-model="query"
            placeholder="Ej. sucursal, almacén, ventas..."
            prefix-icon="el-icon-search"
            class="mt-2"
            clearable
            @keydown.native.down.prevent="moveSelection(1)"
            @keydown.native.up.prevent="moveSelection(-1)"
            @keydown.native.enter.prevent="openSelected"
        ></el-input>

        <div class="sidebar-search-summary">{{ summary }}</div>

        <div ref="results" class="sidebar-search-results" role="listbox">
            <div v-if="normalizedQuery && !matches.length" class="sidebar-search-empty">
                <i class="fas fa-search" aria-hidden="true"></i><br>
                No hay coincidencias disponibles para tu usuario.
            </div>
            <button
                v-for="(entry, index) in matches"
                :key="entry.key"
                type="button"
                role="option"
                class="sidebar-search-result"
                :class="{ 'is-selected': index === selected }"
                :aria-selected="index === selected ? 'true' : 'false'"
                @mouseenter="selected = index"
                @click="navigateTo(entry)"
            >
                <span class="sidebar-search-result-icon" v-html="entry.iconHtml || fallbackIcon"></span>
                <span class="sidebar-search-result-copy">
                    <span class="sidebar-search-result-name">{{ entry.name }}</span>
                    <span class="sidebar-search-path text-muted">{{ entry.path }}</span>
                </span>
            </button>
        </div>
    </el-dialog>
</template>

<script>
    export default {
        data() {
            return {
                showDialog: false,
                query: '',
                indexSize: 0,
                matches: [],
                selected: -1,
                fallbackIcon: '<i class="fas fa-arrow-right" aria-hidden="true"></i>',
            }
        },
        computed: {
            normalizedQuery() {
                return this.query.trim()
            },
            summary() {
                if (!this.normalizedQuery) {
                    return `Escribe para buscar entre ${this.indexSize} opciones disponibles.`
                }
                return this.matches.length
                    ? `${this.matches.length} resultado(s) ordenados por relevancia.`
                    : `No encontramos opciones relacionadas con “${this.normalizedQuery}”.`
            },
        },
        watch: {
            query() {
                this.runSearch()
            },
        },
        created() {
            this.$eventHub.$on('sidebarSearchOpen', this.open)
            this.$eventHub.$on('sidebarSearchToggle', this.toggle)
        },
        beforeDestroy() {
            this.$eventHub.$off('sidebarSearchOpen', this.open)
            this.$eventHub.$off('sidebarSearchToggle', this.toggle)
        },
        methods: {
            engine() {
                return window.sidebarMenuSearch || null
            },
            open() {
                const engine = this.engine()
                if (!engine) return
                this.indexSize = engine.buildIndex().length
                this.query = ''
                this.matches = []
                this.selected = -1
                this.showDialog = true
            },
            toggle() {
                if (this.showDialog) this.showDialog = false
                else this.open()
            },
            runSearch() {
                const engine = this.engine()
                if (!engine || !this.normalizedQuery) {
                    this.matches = []
                    this.selected = -1
                    return
                }
                this.matches = engine.search(this.query)
                this.selected = this.matches.length ? 0 : -1
            },
            focusInput() {
                if (this.$refs.searchInput) this.$refs.searchInput.focus()
            },
            restoreFocus() {
                const trigger = document.getElementById('sidebar-search-trigger')
                if (trigger) trigger.focus()
            },
            moveSelection(delta) {
                if (!this.matches.length) return
                const next = this.selected + delta
                this.selected = Math.max(0, Math.min(next, this.matches.length - 1))
                this.$nextTick(this.scrollSelectionIntoView)
            },
            scrollSelectionIntoView() {
                const container = this.$refs.results
                if (!container) return
                const row = container.querySelectorAll('.sidebar-search-result')[this.selected]
                if (row) row.scrollIntoView({ block: 'nearest' })
            },
            openSelected() {
                if (this.selected < 0) return
                const entry = this.matches[this.selected]
                if (entry) this.navigateTo(entry)
            },
            navigateTo(entry) {
                this.showDialog = false
                if (entry.action === 'menu-config') {
                    this.$nextTick(() => {
                        this.$eventHub.$emit('sidebarMenuConfigOpen', 'sidebar-search-trigger')
                    })
                    return
                }
                if (!entry.href) return
                window.location.href = entry.href
            },
        },
    }
</script>

<style>
    .sidebar-search-el-dialog .el-dialog__body {
        padding: 0 20px 16px;
    }
    .sidebar-search-subtitle {
        display: block;
        margin-top: 2px;
        color: var(--menu-text-muted);
        font-size: 12px;
    }
    .sidebar-search-summary {
        padding: 10px 2px 6px;
        color: var(--menu-text-muted);
        font-size: 11px;
    }
    .sidebar-search-results {
        max-height: calc(78vh - 190px);
        overflow-y: auto;
    }
    .sidebar-search-result {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
        padding: 10px;
        border: 0;
        border-radius: 9px;
        color: inherit;
        text-align: left;
        background: transparent;
    }
    .sidebar-search-result:hover,
    .sidebar-search-result.is-selected {
        background: color-mix(in srgb, var(--primary) 10%, #ffffff00);
    }
    .sidebar-search-result-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        color: var(--primary);
        background: color-mix(in srgb, var(--primary) 15%, #ffffff00);
    }
    .sidebar-search-result-icon svg {
        width: 20px;
        height: 20px;
    }
    .sidebar-search-result-copy {
        min-width: 0;
    }
    .sidebar-search-result-name,
    .sidebar-search-path {
        display: block;
    }
    .sidebar-search-result-name {
        font-weight: 600;
    }
    .sidebar-search-path {
        margin-top: -5px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 11px;
    }
    .sidebar-search-empty {
        padding: 32px 16px;
        color: var(--menu-text-muted);
        text-align: center;
    }
</style>
