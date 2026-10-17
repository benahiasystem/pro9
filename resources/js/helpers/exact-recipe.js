// ######## INICIO CALCULOS EXACTOS PRO9 ########
import {exactAmount, exactValue, exactSnapshot, exactSum} from './exchange-rate-math'

export function recipeEffectiveCost(row) {
    return exactValue(row, 'cost').times(exactAmount(1).plus(exactValue(row, 'waste_percentage').dividedBy(100)))
}
export function recipeLineCost(row) {
    return recipeEffectiveCost(row).times(exactValue(row, 'quantity'))
}
export function recipeTotals(rows) {
    const production_cost = exactSum(rows.map(recipeLineCost))
    const suggested_price = production_cost.times('1.20')
    return {production_cost: production_cost.decimal(), suggested_price: suggested_price.decimal(),
        calculation_snapshot: exactSnapshot({production_cost, suggested_price})}
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
