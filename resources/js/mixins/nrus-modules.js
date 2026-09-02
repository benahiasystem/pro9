const BASE_MODULES = [7, 1, 2, 18, 17, 8, 12, 52, 4, 11, 14, 5, 53]

const BASE_LEVELS = [
    '1-1', '1-2', '1-5', '1-8', '1-15', '1-84',
    '2-31', '2-32', '2-33', '2-34', '2-35', '2-36', '2-37', '2-38',
    '8-39', '8-40', '8-41', '8-42', '8-43', '8-44', '8-75',
    '12-16',
    '17-23', '17-24', '17-25', '17-26', '17-27', '17-28',
    '18-29', '18-30',
    '52-3', '52-6', '52-14',
    '5-76', '5-77', '5-78',
    '11-61', '11-62',
    '14-45', '14-46',
]

const BLOCKED_MODULES = [51, 3, 9]

const TAGS = {
    base: {text: 'Recomendado', type: 'success', tooltip: 'Funciona sin problemas con NRUS: cubre la operación habitual del régimen.'},
    extra: {text: 'Compatible', type: 'info', tooltip: 'Se puede usar con NRUS: no depende de facturas, guías ni libros contables.'},
    blocked: {text: 'No Recomendado', type: 'warning', tooltip: 'Usa comprobantes u obligaciones que NRUS no tiene (guías, retención/percepción, libros contables).'},
}

export const nrusModules = {
    data() {
        return {
            nrusBlockedSelected: false,
        }
    },
    methods: {
        /**
         * @param  {Object} data nodo del árbol (módulo o nivel)
         * @return {String} base | extra | blocked
         */
        nrusStatus(data) {
            if (data.is_parent) {
                if (BLOCKED_MODULES.includes(Number(data.id))) return 'blocked'

                return BASE_MODULES.includes(Number(data.id)) ? 'base' : 'extra'
            }

            const moduleId = Number(String(data.id).split('-')[0])
            if (BLOCKED_MODULES.includes(moduleId)) return 'blocked'

            return BASE_LEVELS.includes(String(data.id)) ? 'base' : 'extra'
        },
        /**
         * Etiqueta a mostrar en el árbol, o null si el régimen no está activo.
         *
         * @param  {Object} data nodo del árbol
         * @return {Object|null}
         */
        nrusTag(data) {
            if (!this.form || !this.form.nrus) return null

            return TAGS[this.nrusStatus(data)]
        },
        refreshNrusBlocked() {
            this.nrusBlockedSelected = [this.$refs.tree, this.$refs.Apptree].some(tree => {
                return tree && tree.getCheckedNodes().some(node => this.nrusStatus(node) === 'blocked')
            })
        },
    },
}
