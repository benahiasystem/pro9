import {exactAmount, exactInput, exactValue} from '../helpers/exchange-rate-math'
import {calculateRowItem} from '../helpers/functions'
import PosStockStatus from '../components/PosStockStatus.vue'
let lineSequence=0

// Calculate the visible draft first. Stock remains a separate, required check.
export default {
    components:{PosStockStatus},
    data:()=>({localPosStockChecks:[]}),
    computed:{
        currentPosStockChecks() { return this.localPosStockChecks.flatMap(entry=>{
            const row=this.form.items.find(row=>row._pos_line_key === entry.key)
            return row ? [{...entry,row}] : []
        }) },
        posStockPending() { return this.currentPosStockChecks.some(entry=>entry.pending) },
        posStockError() { const entry=this.currentPosStockChecks.find(entry=>entry.inputError || entry.error);return entry?.inputError || entry?.error || '' },
        posStockRetryable() { return this.currentPosStockChecks.some(entry=>entry.error && !entry.inputError) },
        posStockBlocked() { return this.posStockPending || !!this.posStockError },
    },
    watch:{'form.items'(rows) {
        rows.forEach((row,index)=>{ if (row._pos_line_key && !this.localPosStockChecks.some(entry=>entry.key === row._pos_line_key)) this.updateLocalPosQuantity(row,index) })
    }},
    methods:{
        posStockEntry(row) {
            if (!row._pos_line_key) this.$set(row,'_pos_line_key',`${Date.now().toString(36)}-${++lineSequence}`)
            this.localPosStockChecks=this.localPosStockChecks.filter(entry=>this.form.items.some(row=>row._pos_line_key === entry.key))
            let entry=this.localPosStockChecks.find(entry=>entry.key === row._pos_line_key)
            if (!entry) { entry={key:row._pos_line_key,row,generation:0,pending:false,error:'',inputError:''};this.localPosStockChecks.push(entry) }
            return entry
        },
        updateLocalPosQuantity(row,index,quantityInput=null) {
            const entry=this.posStockEntry(row)
            const generation=++entry.generation
            entry.pending=false;entry.error='';entry.inputError=''
            let quantity,next
            try {
                quantity=exactInput(String(row.item.aux_quantity))
                if (exactAmount(quantity).compare(0) <= 0) throw new Error('Indique una cantidad mayor que cero.')
                next=calculateRowItem({...row,item:{...row.item,aux_quantity:quantity},quantity,quantity_input:quantityInput},this.form.currency_type_id,this.form.exchange_rate_sale,this.percentage_igv)
                next.unit_type_id=row.unit_type_id
            } catch (error) { entry.inputError=error.message;return }
            if (this.validatePosStockChange && !this.validatePosStockChange(row, next)) {
                row.item.aux_quantity = row.quantity
                row.total = exactValue(row, 'total').decimal()
                return
            }
            entry.row=next
            this.$set(this.form.items,index,next)
            this.calculateTotal()
            this.setFormPosLocalStorage()
            if (this.validatePosStockChange) return

            entry.pending=true
            const current=()=>entry.generation === generation && this.form.items.some(row=>row._pos_line_key === entry.key && String(row.item.aux_quantity) === quantity)
            Promise.resolve().then(()=>this.getStatusStock(next.item_id,quantity)).then(result=>{
                if (!current()) return
                entry.pending=false
                if (!result.success) { entry.error=result.message || 'La cantidad supera las existencias disponibles.';this.$message.error(entry.error) }
            }).catch(()=>{
                if (!current()) return
                entry.pending=false;entry.error='No se pudo verificar la disponibilidad. Reintente antes de generar.'
            })
        },
        updateLocalPosTotal(index) {
            const row=this.form.items[index],entry=this.posStockEntry(row)
            try {
                const amount=exactAmount(exactInput(String(row.total))),quantity=exactValue(row,'quantity')
                if (amount.compare(0) < 0 || quantity.compare(0) <= 0) throw new Error('Indique un importe y una cantidad válidos.')
                if (this.config?.condition_sale_purchase_price_to_item && amount.compare(row.purchase_unit_price ?? row.item.purchase_unit_price ?? '0') < 0) throw new Error('El importe debe ser mayor o igual al costo de compra.')
                if (row.item.calculate_quantity) {
                    const nextQuantity=amount.dividedBy(exactValue(row,'unit_price'))
                    const {n,d}=nextQuantity.fraction()
                    row.item.aux_quantity=nextQuantity.decimal()
                    return this.updateLocalPosQuantity(row,index,{amount:exactInput(n),unit_price:exactInput(d)})
                }
                const price=amount.dividedBy(quantity)
                const next=calculateRowItem({...row,price_input:{amount:price.fraction(),currency_type_id:this.form.currency_type_id,includes_iva:true}},this.form.currency_type_id,this.form.exchange_rate_sale,this.percentage_igv)
                next.item={...row.item,unit_price:price.decimal(),sale_unit_price:row.item.has_igv ? price.decimal() : price.dividedBy(exactAmount('1').plus(this.percentage_igv)).decimal()}
                next.unit_type_id=row.unit_type_id;entry.row=next;entry.inputError=''
                this.$set(this.form.items,index,next);this.calculateTotal();this.setFormPosLocalStorage()
            } catch (error) { entry.inputError=error.message }
        },
        retryPosStock() {
            this.currentPosStockChecks.filter(entry=>entry.error && !entry.inputError).forEach(entry=>this.updateLocalPosQuantity(entry.row,this.form.items.indexOf(entry.row)))
        },
    },
}
