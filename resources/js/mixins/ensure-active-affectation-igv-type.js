/**
 * Fallback de tipo de afectación para los diálogos donde se agrega un producto.
 *
 * Los diálogos reciben los tipos de afectación filtrados por activos
 * (AffectationIgvType::whereActive()). Si el producto tiene uno desactivado no está
 * en la lista y la fila queda sin affectation_igv_type: se activa automáticamente y
 * se agrega a las listas del diálogo.
 *
 * Usa form.affectation_igv_type_id y las listas affectation_igv_types /
 * all_affectation_igv_types. Si el diálogo tiene operation_types y operationTypeId,
 * en exportación no hace nada (ahí se fuerza la afectación 40).
 *
 *   changeItem():   this.ensureActiveAffectationIgvType()
 *   clickAddItem(): if (!(await this.ensureActiveAffectationIgvType())) return false
 */
export const ensureActiveAffectationIgvType = {
    data() {
        return {
            activating_affectation_igv_type: null,
        }
    },
    methods: {
        // all_affectation_igv_types solo cuando el diálogo la usa (algunos la declaran vacía)
        getLoadedAffectationIgvTypes() {
            if (Array.isArray(this.all_affectation_igv_types) && this.all_affectation_igv_types.length) {
                return this.all_affectation_igv_types
            }

            return Array.isArray(this.affectation_igv_types) ? this.affectation_igv_types : []
        },
        getOperationTypeExportation() {
            if (!Array.isArray(this.operation_types) || !this.operationTypeId) return null

            const operation_type = this.operation_types.find(row => row.id === this.operationTypeId)

            return operation_type ? Boolean(Number(operation_type.exportation)) : null
        },
        hasInactiveAffectationIgvType() {
            const id = this.form ? this.form.affectation_igv_type_id : null
            const affectation_igv_types = this.getLoadedAffectationIgvTypes()

            if (!id || !affectation_igv_types.length) return false
            if (this.getOperationTypeExportation()) return false

            return !affectation_igv_types.some(row => row.id === id)
        },
        ensureActiveAffectationIgvType() {
            if (!this.hasInactiveAffectationIgvType()) return Promise.resolve(true)

            // Una sola activación en curso aunque la pidan changeItem y clickAddItem a la vez
            if (!this.activating_affectation_igv_type) {
                this.activating_affectation_igv_type = this.activateAffectationIgvType(this.form.affectation_igv_type_id)
                    .finally(() => {
                        this.activating_affectation_igv_type = null
                    })
            }

            return this.activating_affectation_igv_type
        },
        async activateAffectationIgvType(id) {
            try {
                const { data } = await this.$http.get(`/item-affectations-igv/active/${id}/1`)
                if (!data.success) throw new Error(data.message)
                if (!data.data) throw new Error('no se encontró en el catálogo')

                this.addAffectationIgvTypeToLists(data.data)
                this.$message.info(`Se activó el tipo de afectación "${data.data.description}"`)

                return true
            } catch (error) {
                this.$message.error(`No se pudo activar el tipo de afectación ${id}: ${error.message}`)
                return false
            }
        },
        addAffectationIgvTypeToLists(affectation) {
            const uses_all_list = Array.isArray(this.all_affectation_igv_types) && this.all_affectation_igv_types.length > 0
            const exportation = this.getOperationTypeExportation()
            const matches_operation = exportation === null || Boolean(Number(affectation.exportation)) === exportation

            // Algunos diálogos comparten el mismo arreglo en ambas listas: se evita duplicar
            if (uses_all_list && !this.all_affectation_igv_types.some(row => row.id === affectation.id)) {
                this.all_affectation_igv_types.push(affectation)
            }

            if (Array.isArray(this.affectation_igv_types) && matches_operation
                && !this.affectation_igv_types.some(row => row.id === affectation.id)) {
                this.affectation_igv_types.push(affectation)
            }
        },
    },
}
