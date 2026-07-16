/**
 * Validación de lotes (origen) para ítems de venta/documentos.
 * Misma invariante que lots_group.vue y DocumentRequest.
 */

/**
 * @param {Object} row - fila de form.items
 * @returns {boolean}
 */
export function itemRequiresLot(row) {
    if (!row || !row.item) return false;
    return !!(row.item.lots_enabled || row.item.has_lot || row.lots_enabled);
}

/**
 * @param {*} idLoteSelected
 * @returns {boolean}
 */
export function isEmptyLotSelection(idLoteSelected) {
    if (idLoteSelected === null || idLoteSelected === undefined || idLoteSelected === '' || idLoteSelected === false) {
        return true;
    }
    if (Array.isArray(idLoteSelected) && idLoteSelected.length === 0) {
        return true;
    }
    return false;
}

/**
 * Resuelve el lote ya elegido desde distintas formas de persistencia:
 * - row.IdLoteSelected (nivel ítem del documento/cotización)
 * - row.item.IdLoteSelected (JSON embebido)
 * - row.item.lots_group con compromise_quantity > 0
 *
 * @param {Object} row
 * @returns {*|null}
 */
export function resolveIdLoteSelected(row) {
    if (!row) return null;

    if (!isEmptyLotSelection(row.IdLoteSelected)) {
        return row.IdLoteSelected;
    }

    const item = row.item || {};
    if (!isEmptyLotSelection(item.IdLoteSelected)) {
        return item.IdLoteSelected;
    }

    const lotsGroup = item.lots_group;
    if (Array.isArray(lotsGroup) && lotsGroup.length > 0) {
        const selected = lotsGroup
            .filter(lot => parseFloat(lot.compromise_quantity || 0) > 0)
            .map(lot => ({
                id: lot.id,
                code: lot.code,
                compromise_quantity: lot.compromise_quantity,
                date_of_due: lot.date_of_due,
            }));
        if (selected.length > 0) {
            return selected;
        }
    }

    return null;
}

/**
 * Hidrata row.IdLoteSelected (y item.IdLoteSelected) desde cualquier fuente disponible.
 * Mutación in-place; devolver la misma fila para encadenar en map().
 *
 * @param {Object} row
 * @returns {Object}
 */
export function hydrateItemLots(row) {
    if (!row) return row;

    const resolved = resolveIdLoteSelected(row);
    if (isEmptyLotSelection(resolved)) {
        return row;
    }

    row.IdLoteSelected = resolved;
    if (row.item && typeof row.item === 'object') {
        row.item.IdLoteSelected = resolved;
    }

    return row;
}

/**
 * Suma compromise_quantity de IdLoteSelected (array).
 * Si es id único (legacy), se considera "completo" solo a nivel de presencia.
 *
 * @param {*} idLoteSelected
 * @returns {number|null} null si formato legacy (id único)
 */
export function sumLotCompromiseQuantity(idLoteSelected) {
    if (!Array.isArray(idLoteSelected)) {
        return null;
    }
    return idLoteSelected.reduce((accum, lot) => {
        const qty = parseFloat(
            (lot && (lot.compromise_quantity !== undefined ? lot.compromise_quantity : lot.compromiseQuantity)) || 0
        );
        return accum + (isNaN(qty) ? 0 : qty);
    }, 0);
}

/**
 * @param {Object} row
 * @returns {{ valid: boolean, message?: string }}
 */
export function validateItemLots(row) {
    if (!itemRequiresLot(row)) {
        return { valid: true };
    }

    const description =
        (row.item && (row.item.description || row.item.name)) ||
        'sin nombre';
    const quantity = Math.round(parseFloat(row.quantity || 0) * 10000) / 10000;
    const idLoteSelected = resolveIdLoteSelected(row);

    if (isEmptyLotSelection(idLoteSelected)) {
        return {
            valid: false,
            message: `El producto [${description}] requiere asignación de lotes válida. Por favor, corrígelo antes de continuar.`,
        };
    }

    // Legacy: un solo id cubre toda la cantidad
    if (!Array.isArray(idLoteSelected)) {
        return { valid: true };
    }

    const sum = Math.round(sumLotCompromiseQuantity(idLoteSelected) * 10000) / 10000;
    if (sum !== quantity) {
        return {
            valid: false,
            message: `El producto [${description}] requiere asignación de lotes válida. Por favor, corrígelo antes de continuar.`,
        };
    }

    return { valid: true };
}

/**
 * @param {Array} items
 * @returns {{ valid: boolean, message?: string, index?: number }}
 */
export function validateItemsLots(items) {
    if (!Array.isArray(items)) {
        return { valid: true };
    }

    for (let i = 0; i < items.length; i++) {
        const result = validateItemLots(items[i]);
        if (!result.valid) {
            return { ...result, index: i };
        }
    }

    return { valid: true };
}

/**
 * ¿Mostrar botón "Asignar Lote" en la fila?
 * @param {Object} row
 * @returns {boolean}
 */
export function rowNeedsLotAssignment(row) {
    if (!itemRequiresLot(row)) return false;
    return !validateItemLots(row).valid;
}
