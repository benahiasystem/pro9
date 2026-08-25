<template>
    <el-dialog
        :visible.sync="showDialog"
        :append-to-body="true"
        :modal-append-to-body="true"
        custom-class="sidebar-menu-config-el-dialog"
        width="920px"
        top="6vh"
        @opened="focusSearch"
        @closed="onClosed"
    >
        <template slot="title">
            <span class="el-dialog__title">Configurar menú</span>
            <small class="sidebar-menu-config-subtitle text-muted">Organiza tus favoritos y elige qué accesos mantener a la vista.</small>
        </template>

        <div class="sidebar-menu-config-toolbar">
            <label class="sidebar-menu-config-compact" :class="{ 'is-disabled': !hasFavorites }">
                <el-switch
                    :value="showOnlyActive"
                    :disabled="!hasFavorites"
                    @change="onShowOnlyActiveChange"
                ></el-switch>
                <span>Mostrar únicamente los favoritos en el menú lateral</span>
            </label>
            <el-input
                ref="searchInput"
                v-model="query"
                class="sidebar-menu-config-search"
                placeholder="Buscar una opción del menú..."
                prefix-icon="el-icon-search"
                clearable
            ></el-input>
        </div>

        <div class="sidebar-menu-config-content">
            <section class="sidebar-menu-config-section">
                <div class="sidebar-menu-config-section-title">
                    <strong>Opciones disponibles</strong>
                    <small class="text-muted">Selecciona los módulos que quieres mantener visibles.</small>
                </div>
                <div class="sidebar-menu-config-list">
                    <section
                        v-for="group in groups"
                        :key="group.groupKey"
                        class="sidebar-menu-config-group"
                        :class="{ 'is-expanded': isExpanded(group.groupKey), 'is-flat': group.isSingle }"
                    >
                        <draggable
                            v-if="group.isSingle"
                            :value="[group.parent]"
                            :group="availableGroup"
                            :sort="false"
                            filter=".is-disabled"
                            handle=".sidebar-menu-config-drag-handle"
                            animation="150"
                            ghost-class="sidebar-menu-config-row--ghost"
                            chosen-class="sidebar-menu-config-row--chosen"
                        >
                            <div
                                class="sidebar-menu-config-row"
                                :class="rowClasses(group.parent)"
                            >
                            <span
                                class="sidebar-menu-config-drag-handle text-muted"
                                tabindex="0"
                                title="Arrastrar a elementos seleccionados"
                                aria-label="Arrastrar a elementos seleccionados"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-grip-vertical"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M8 5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M8 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M8 19a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M14 5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M14 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M14 19a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /></svg>
                            </span>
                            <div class="sidebar-menu-config-row-copy">
                                <span class="sidebar-menu-config-row-name">{{ group.parent.name }}</span>
                                <span class="sidebar-menu-config-row-path text-muted">{{ group.parent.path }}</span>
                            </div>
                            <div class="sidebar-menu-config-row-actions">
                                <button
                                    type="button"
                                    title="Mover a elementos seleccionados"
                                    aria-label="Mover a elementos seleccionados"
                                    @click="toggleEntry(group.parent, false)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                                </button>
                            </div>
                            </div>
                        </draggable>
                        <div v-else class="sidebar-menu-config-group-header">
                            <button
                                type="button"
                                class="sidebar-menu-config-group-toggle"
                                :aria-expanded="isExpanded(group.groupKey) ? 'true' : 'false'"
                                :aria-label="(isExpanded(group.groupKey) ? 'Contraer ' : 'Expandir ') + group.name"
                                @click="toggleGroup(group.groupKey)"
                            >
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </button>
                            <div class="sidebar-menu-config-group-copy">
                                <span class="sidebar-menu-config-group-name">{{ group.name }}</span>
                                <span class="sidebar-menu-config-group-count text-muted">{{ group.countLabel }}</span>
                            </div>
                            <el-checkbox
                                class="sidebar-menu-config-group-select"
                                :value="group.parentSelected"
                                :indeterminate="group.indeterminate"
                                :aria-label="'Seleccionar todo el módulo ' + group.name"
                                @change="setGroupSelection(group.groupKey, $event)"
                            >Todo</el-checkbox>
                        </div>
                        <draggable
                            v-if="!group.isSingle"
                            :value="group.visibleEntries"
                            :group="availableGroup"
                            :sort="false"
                            filter=".is-disabled"
                            handle=".sidebar-menu-config-drag-handle"
                            animation="150"
                            ghost-class="sidebar-menu-config-row--ghost"
                            chosen-class="sidebar-menu-config-row--chosen"
                            class="sidebar-menu-config-group-items"
                        >
                            <div
                                v-for="entry in group.visibleEntries"
                                :key="entry.key"
                                class="sidebar-menu-config-row"
                                :class="rowClasses(entry)"
                            >
                                <span
                                    class="sidebar-menu-config-drag-handle text-muted"
                                    :tabindex="isCovered(entry) ? -1 : 0"
                                    :title="isCovered(entry) ? coveredLabel : 'Arrastrar a elementos seleccionados'"
                                    :aria-label="isCovered(entry) ? coveredLabel : 'Arrastrar a elementos seleccionados'"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-grip-vertical"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M8 5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M8 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M8 19a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M14 5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M14 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M14 19a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /></svg>
                                </span>
                                <div class="sidebar-menu-config-row-copy">
                                    <span class="sidebar-menu-config-row-name">{{ entry.name }}</span>
                                    <span class="sidebar-menu-config-row-path text-muted">{{ entry.path }}</span>
                                </div>
                                <div class="sidebar-menu-config-row-actions">
                                    <button
                                        type="button"
                                        :disabled="isCovered(entry)"
                                        :title="isCovered(entry) ? coveredLabel : 'Mover a elementos seleccionados'"
                                        :aria-label="isCovered(entry) ? coveredLabel : 'Mover a elementos seleccionados'"
                                        @click="toggleEntry(entry, false)"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                                    </button>
                                </div>
                            </div>
                            <div
                                v-if="!group.visibleEntries.length"
                                slot="footer"
                                class="sidebar-menu-config-empty"
                            >
                                Todos los elementos de este bloque están seleccionados.
                            </div>
                        </draggable>
                    </section>
                    <div v-if="!groups.length" class="sidebar-menu-config-empty">
                        No hay opciones que coincidan con la búsqueda.
                    </div>
                </div>
            </section>

            <section class="sidebar-menu-config-section">
                <div class="sidebar-menu-config-section-title">
                    <strong>Elementos seleccionados / activos</strong>
                    <small class="text-muted">Arrastra desde los tres puntos para cambiar la prioridad.</small>
                </div>
                <draggable
                    :value="pinnedRows"
                    :group="pinnedGroup"
                    handle=".sidebar-menu-config-drag-handle"
                    animation="150"
                    ghost-class="sidebar-menu-config-row--ghost"
                    chosen-class="sidebar-menu-config-row--chosen"
                    class="sidebar-menu-config-list"
                    @change="onPinnedChange"
                >
                    <div
                        v-for="entry in pinnedRows"
                        :key="entry.key"
                        class="sidebar-menu-config-row"
                        :class="rowClasses(entry)"
                    >
                        <span
                            class="sidebar-menu-config-drag-handle text-muted"
                            tabindex="0"
                            title="Arrastrar para cambiar la prioridad"
                            aria-label="Arrastrar para cambiar la prioridad"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-grip-vertical"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M8 5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M8 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M8 19a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M14 5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M14 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M14 19a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /></svg>
                        </span>
                        <div class="sidebar-menu-config-row-copy">
                            <span class="sidebar-menu-config-row-name">{{ entry.name }}</span>
                            <span class="sidebar-menu-config-row-path text-muted">{{ entry.path }}</span>
                        </div>
                        <div class="sidebar-menu-config-row-actions">
                            <button
                                type="button"
                                title="Mover a opciones disponibles"
                                aria-label="Mover a opciones disponibles"
                                @click="toggleEntry(entry, true)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-x"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M18 6l-12 12" /><path d="M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    <div
                        v-if="!pinnedRows.length"
                        slot="footer"
                        class="sidebar-menu-config-empty"
                    >
                        Todavía no fijaste ningún acceso.
                    </div>
                </draggable>
            </section>
        </div>

        <template slot="footer">
            <div class="sidebar-menu-config-footer">
                <small class="text-muted">Los cambios se guardan automáticamente.</small>
                <el-button type="primary" size="small" @click="showDialog = false">Listo</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script>
    import draggable from 'vuedraggable'

    const COVERED_LABEL = 'Incluido por el módulo completo'
    const DRAG_GROUP = 'sidebar-menu-config'

    export default {
        components: { draggable },
        data() {
            return {
                showDialog: false,
                entries: [],
                pinned: [],
                showOnlyActive: false,
                hasFavorites: false,
                query: '',
                expandedGroups: [],
                returnFocusId: null,
                coveredLabel: COVERED_LABEL,
                availableGroup: { name: DRAG_GROUP, pull: 'clone', put: false },
                pinnedGroup: { name: DRAG_GROUP, pull: true, put: true },
            }
        },
        computed: {
            pinnedSet() {
                return new Set(this.pinned)
            },
            entriesByKey() {
                const map = new Map()
                this.entries.forEach((entry) => map.set(entry.key, entry))
                return map
            },
            pinnedRows() {
                return this.pinned
                    .map((key) => this.entriesByKey.get(key))
                    .filter(Boolean)
            },
            normalizedQuery() {
                const query = this.query
                const engine = this.engine()
                return engine ? engine.normalize(query) : ''
            },
            matchingEntries() {
                const query = this.normalizedQuery
                const engine = this.engine()
                if (!engine) return this.entries.slice()
                return this.entries
                    .map((entry) => ({ entry, score: engine.score(entry, query) }))
                    .filter((result) => result.score > 0)
                    .sort((left, right) => right.score - left.score || left.entry.dashboardOrder - right.entry.dashboardOrder)
                    .map((result) => result.entry)
            },
            groups() {
                const grouped = new Map()
                this.matchingEntries.forEach((entry) => {
                    if (!grouped.has(entry.group)) grouped.set(entry.group, [])
                    grouped.get(entry.group).push(entry)
                })

                const result = []
                grouped.forEach((matched, groupName) => {
                    const all = this.entries.filter((entry) => entry.group === groupName)
                    if (!all.length) return
                    const parent = all.find((entry) => !(entry.ancestorKeys || []).length) || all[0]
                    const parentSelected = this.pinnedSet.has(parent.key)
                    const isSingle = all.length === 1
                    if (isSingle && parentSelected) return
                    const children = all.filter((entry) => entry.key !== parent.key)
                    const selectedCount = children.filter((entry) => this.pinnedSet.has(entry.key)).length
                    result.push({
                        name: groupName,
                        groupKey: all[0].groupKey,
                        parent: parent,
                        isSingle: isSingle,
                        parentSelected: parentSelected,
                        indeterminate: !parentSelected && selectedCount > 0,
                        countLabel: `${parentSelected ? children.length : selectedCount} de ${children.length} seleccionados`,
                        visibleEntries: parentSelected ? [] : matched.filter((entry) => {
                            return entry.key !== parent.key && !this.pinnedSet.has(entry.key)
                        }),
                    })
                })
                return result
            },
        },
        created() {
            this.$eventHub.$on('sidebarMenuConfigOpen', this.open)
            this.$eventHub.$on('sidebarMenuConfigChanged', this.applyState)
        },
        beforeDestroy() {
            this.$eventHub.$off('sidebarMenuConfigOpen', this.open)
            this.$eventHub.$off('sidebarMenuConfigChanged', this.applyState)
        },
        methods: {
            engine() {
                return window.sidebarMenuConfig || null
            },
            open(triggerId) {
                const engine = this.engine()
                if (!engine) return
                this.returnFocusId = triggerId || null
                this.query = ''
                this.applyState(engine.open())
                this.expandedGroups = this.entries.length ? [this.entries[0].groupKey] : []
                this.showDialog = true
            },
            applyState(state) {
                if (!state) return
                this.entries = state.entries || []
                this.pinned = state.pinned || []
                this.showOnlyActive = !!state.showOnlyActive
                this.hasFavorites = !!state.hasFavorites
            },
            onClosed() {
                const engine = this.engine()
                if (engine) engine.close()
                const trigger = this.returnFocusId ? document.getElementById(this.returnFocusId) : null
                if (trigger) trigger.focus()
            },
            focusSearch() {
                if (this.$refs.searchInput) this.$refs.searchInput.focus()
            },
            refocusSearch() {
                window.requestAnimationFrame(() => {
                    if (this.$refs.searchInput) this.$refs.searchInput.focus()
                })
            },
            isCovered(entry) {
                return (entry.ancestorKeys || []).some((key) => this.pinnedSet.has(key))
            },
            isExpanded(groupKey) {
                return !!this.normalizedQuery || this.expandedGroups.includes(groupKey)
            },
            toggleGroup(groupKey) {
                const index = this.expandedGroups.indexOf(groupKey)
                if (index >= 0) this.expandedGroups.splice(index, 1)
                else this.expandedGroups.push(groupKey)
            },
            rowClasses(entry) {
                return { 'is-disabled': this.isCovered(entry) }
            },
            toggleEntry(entry, pinned) {
                const engine = this.engine()
                if (!engine) return
                if (!pinned && this.isCovered(entry)) return
                engine.toggle(entry.key)
                if (!pinned) this.refocusSearch()
            },
            setGroupSelection(groupKey, selected) {
                const engine = this.engine()
                if (!engine) return
                engine.setGroupSelection(groupKey, selected)
                this.refocusSearch()
            },
            onShowOnlyActiveChange(value) {
                const engine = this.engine()
                if (!engine) return
                engine.setShowOnlyActive(!!value)
            },
            onPinnedChange(event) {
                const engine = this.engine()
                if (!engine) return

                if (event.moved) {
                    const order = this.pinned.slice()
                    const [key] = order.splice(event.moved.oldIndex, 1)
                    order.splice(event.moved.newIndex, 0, key)
                    engine.reorder(order)
                    return
                }

                if (event.added) {
                    const entry = event.added.element
                    if (!entry || this.isCovered(entry)) return
                    const order = this.pinned.filter((key) => key !== entry.key)
                    order.splice(event.added.newIndex, 0, entry.key)
                    engine.reorder(order)
                }
            },
        },
    }
</script>

<style>
    .sidebar-menu-config-el-dialog .el-dialog__body {
        display: flex;
        flex-direction: column;
        height: min(620px, 72vh);
        padding: 0;
        overflow: hidden;
    }
    .sidebar-menu-config-subtitle {
        display: block;
        margin-top: 2px;
        font-size: 12px;
    }
    .sidebar-menu-config-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 12px 20px;
        border-top: 1px solid color-mix(in srgb, var(--primary) 10%, #ffffff00);
        border-bottom: 1px solid color-mix(in srgb, var(--primary) 10%, #ffffff00);
        background: color-mix(in srgb, var(--primary) 6%, #ffffff00);
    }
    .sidebar-menu-config-compact {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        font-weight: 400;
        cursor: pointer;
    }
    .sidebar-menu-config-compact.is-disabled {
        opacity: .55;
        cursor: not-allowed;
    }
    .sidebar-menu-config-search {
        width: min(360px, 100%);
    }
    .sidebar-menu-config-content {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 16px;
        flex: 1;
        min-height: 0;
        padding: 16px 20px;
        overflow: hidden;
    }
    .sidebar-menu-config-section {
        display: flex;
        flex-direction: column;
        min-height: 0;
        border: 1px solid color-mix(in srgb, var(--primary) 10%, #ffffff00);
        border-radius: 10px;
        overflow: hidden;
    }
    .sidebar-menu-config-section-title {
        padding: 11px 13px;
        border-bottom: 1px solid color-mix(in srgb, var(--primary) 10%, #ffffff00);
        background: color-mix(in srgb, var(--primary) 6%, #ffffff00);
    }
    .sidebar-menu-config-section-title strong,
    .sidebar-menu-config-section-title small,
    .sidebar-menu-config-row-name,
    .sidebar-menu-config-row-path {
        display: block;
    }
    .sidebar-menu-config-section-title small,
    .sidebar-menu-config-row-path,
    .sidebar-menu-config-footer small {
        color: var(--menu-text-muted);
    }
    .sidebar-menu-config-list {
        flex: 1;
        min-height: 180px;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 7px;
    }
    .sidebar-menu-config-group {
        margin-bottom: 7px;
        border: 1px solid color-mix(in srgb, var(--primary) 10%, #ffffff00);
        border-radius: 8px;
        overflow: hidden;
    }
    .sidebar-menu-config-group:last-child {
        margin-bottom: 0;
    }
    /* Modulo sin submenus: una fila suelta, sin chevron ni contenido anidado. */
    .sidebar-menu-config-group.is-flat {
        background: color-mix(in srgb, var(--primary) 6%, #ffffff00);
    }
    .sidebar-menu-config-group.is-flat .sidebar-menu-config-row {
        padding: 7px 9px;
    }
    .sidebar-menu-config-group-header {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        min-width: 0;
        min-height: 42px;
        padding: 7px 9px;
        border: 0;
        color: inherit;
        background: color-mix(in srgb, var(--primary) 6%, #ffffff00);
        text-align: left;
    }
    .sidebar-menu-config-group-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 24px;
        width: 24px;
        height: 24px;
        border: 0;
        color: var(--menu-text-muted);
        background: transparent;
    }
    .sidebar-menu-config-group-toggle i {
        transition: transform .18s ease;
    }
    .sidebar-menu-config-group.is-expanded .sidebar-menu-config-group-toggle i {
        transform: rotate(90deg);
    }
    .sidebar-menu-config-group-copy {
        flex: 1;
        width: 0;
        min-width: 0;
    }
    .sidebar-menu-config-group-name,
    .sidebar-menu-config-group-count {
        display: block;
    }
    .sidebar-menu-config-group-name {
        overflow: hidden;
        font-weight: 600;
        font-size: 12px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .sidebar-menu-config-group-count {
        color: var(--menu-text-muted);
        font-size: 10px;
    }
    .sidebar-menu-config-group-select {
        flex: 0 0 auto;
        margin: 0;
    }
    .sidebar-menu-config-group-items {
        display: none;
        padding: 4px;
        border-top: 1px solid var(--menu-border);
    }
    .sidebar-menu-config-group.is-expanded .sidebar-menu-config-group-items {
        display: block;
    }
    .sidebar-menu-config-row {
        display: flex;
        align-items: center;
        gap: 9px;
        width: 100%;
        min-height: 48px;
        min-width: 0;
        padding: 7px 8px;
        border-radius: 8px;
        overflow: hidden;
    }
    .sidebar-menu-config-row:hover {
        background: color-mix(in srgb, var(--primary) 12%, #ffffff00);
    }
    /* Estados de Sortable (vuedraggable), mismo criterio que visibleColumns.vue */
    .sidebar-menu-config-row--ghost {
        opacity: .4;
        background: color-mix(in srgb, var(--menu-primary) 12%, transparent) !important;
    }
    .sidebar-menu-config-row--chosen {
        background: var(--menu-hover);
    }
    .sidebar-menu-config-drag-handle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 24px;
        width: 24px;
        height: 28px;
        border-radius: 6px;
        cursor: grab;
        touch-action: none;
        transition: background-color .12s ease;
    }
    .sidebar-menu-config-drag-handle:hover {
        background: var(--menu-surface-muted);
    }
    .sidebar-menu-config-drag-handle:active {
        cursor: grabbing;
    }
    .sidebar-menu-config-row.is-disabled .sidebar-menu-config-drag-handle {
        cursor: not-allowed;
        opacity: .35;
    }
    .sidebar-menu-config-row-copy {
        flex: 1;
        width: 0;
        min-width: 0;
    }
    .sidebar-menu-config-row-name,
    .sidebar-menu-config-row-path {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .sidebar-menu-config-row-name {
        font-weight: 600;
        font-size: 12px;
    }
    .sidebar-menu-config-row-path {
        margin-top: -6px;
        font-size: 10px;
    }
    .sidebar-menu-config-row-actions {
        display: flex;
        align-items: center;
        flex: 0 0 auto;
        gap: 4px;
        white-space: nowrap;
    }
    .sidebar-menu-config-row-actions button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 28px;
        width: 28px;
        height: 28px;
        padding: 0;
        border: 0;
        border-radius: 6px;
        color: var(--menu-text-muted);
        background: var(--menu-surface-muted);
    }
    .sidebar-menu-config-row-actions button:hover:not(:disabled) {
        color: #fff;
        background: var(--primary);
    }
    .sidebar-menu-config-row-actions button:disabled {
        opacity: .35;
    }
    .sidebar-menu-config-empty {
        padding: 28px 12px;
        color: var(--menu-text-muted);
        text-align: center;
        font-size: 12px;
    }
    .sidebar-menu-config-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    @media (max-width: 767px) {
        .sidebar-menu-config-el-dialog .el-dialog__body { height: min(660px, 78vh); }
        .sidebar-menu-config-toolbar { align-items: stretch; flex-direction: column; }
        .sidebar-menu-config-search { width: 100%; }
        .sidebar-menu-config-content { grid-template-columns: 1fr; overflow-y: auto; }
        .sidebar-menu-config-section { min-height: 220px; }
        .sidebar-menu-config-list { min-height: 0; max-height: 260px; }
    }
</style>
