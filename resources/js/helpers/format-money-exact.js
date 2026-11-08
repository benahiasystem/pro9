import { exactAmount } from './exchange-rate-math'

export function formatMoneyExact(value) {
    const amount = exactAmount(value ?? 0).final(2)
    const [whole, fraction] = amount.split('.')
    return `${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${fraction}`
}
