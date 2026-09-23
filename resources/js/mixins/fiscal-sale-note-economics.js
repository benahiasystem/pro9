// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
const ids = rows => (rows || []).map(row => Number(typeof row === 'object' ? row.id : row)).sort((a, b) => a - b);
const sameSources = (left, right) => JSON.stringify(ids(left)) === JSON.stringify(ids(right));

export const fiscalSaleNoteEconomics = {
    data() { return {sourceSaleNoteEconomics: null}; },
    computed: {
        hasSourceSaleNoteEconomics() {
            return !!this.sourceSaleNoteEconomics && (this.form.sale_notes_relateds || []).length > 0
                && sameSources(this.form.sale_notes_relateds, this.sourceSaleNoteEconomics.source_ids);
        }
    },
    methods: {
        loadSaleNoteEconomics(notes, snapshot) {
            this.sourceSaleNoteEconomics = snapshot && sameSources(notes, snapshot.source_ids) ? snapshot : null;
        },
        applySaleNoteEconomics() {
            if (!this.hasSourceSaleNoteEconomics) return false;
            for (const [field, value] of Object.entries(this.sourceSaleNoteEconomics.totals)) this.$set(this.form, field, Number(value));
            for (const field of ['discounts', 'charges']) this.$set(this.form, field, JSON.parse(JSON.stringify(this.sourceSaleNoteEconomics[field] || [])));
            return true;
        }
    }
};
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
