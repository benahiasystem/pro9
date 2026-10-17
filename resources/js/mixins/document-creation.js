import {collectionTarget} from '../helpers/document-creation'

export default {
    computed: {
        collectionTarget() { return collectionTarget(this.form) },
    },
    watch: {
        collectionTarget() { if (typeof this.inputAmount === 'function') this.inputAmount() },
    },
}
