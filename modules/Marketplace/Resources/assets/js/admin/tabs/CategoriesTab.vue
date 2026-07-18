<template>
    <div>
        <div class="alert alert-info mb-3" role="alert">
            <strong>La taxonomía se construye sola con lo que envían las tiendas.</strong>
            No hace falta crear categorías. Solo puedes cambiar el nombre visible y ocultarlas:
            sus productos siguen publicados bajo «Otros».
        </div>

        <el-table :data="records" v-loading="loading" empty-text="Aún no hay categorías">
            <el-table-column label="Nombre" min-width="240">
                <template slot-scope="scope">
                    <el-input v-if="editing === scope.row.id" v-model="draft" :maxlength="120"
                              @keyup.enter.native="save(scope.row)"/>
                    <span v-else>{{ scope.row.name }}</span>
                </template>
            </el-table-column>

            <el-table-column label="Slug" min-width="200">
                <template slot-scope="scope">
                    <code class="text-muted">{{ scope.row.slug }}</code>
                </template>
            </el-table-column>

            <el-table-column label="Productos" prop="items_count" width="110" align="center"/>

            <el-table-column label="Visible" width="100" align="center">
                <template slot-scope="scope">
                    <el-switch :value="scope.row.is_visible" @change="toggle(scope.row, $event)"/>
                </template>
            </el-table-column>

            <el-table-column label="Acciones" width="180" align="right">
                <template slot-scope="scope">
                    <template v-if="editing === scope.row.id">
                        <el-button type="primary" @click="save(scope.row)">Guardar</el-button>
                        <el-button @click="editing = null">Cancelar</el-button>
                    </template>
                    <el-button v-else type="text" @click="edit(scope.row)">
                        <i class="el-icon-edit"></i> Renombrar
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
    </div>
</template>

<script>
export default {
    data() {
        return { loading: false, records: [], editing: null, draft: '' }
    },

    created() {
        this.load()
    },

    methods: {
        load() {
            this.loading = true
            this.$http.get('/marketplace/admin/categories')
                .then(({ data }) => { this.records = data.data })
                .finally(() => { this.loading = false })
        },

        edit(row) {
            this.editing = row.id
            this.draft = row.name
        },

        // Solo cambia el nombre visible. El slug es la llave con la que el sync
        // resuelve categorías, por eso no se puede editar: cambiarlo crearía
        // una categoría nueva en la siguiente sincronización.
        save(row) {
            if (!this.draft.trim()) {
                this.$message.error('El nombre es obligatorio.')
                return
            }

            this.$http.patch(`/marketplace/admin/categories/${row.id}`, { name: this.draft })
                .then(({ data }) => {
                    this.$message.success(data.message)
                    this.editing = null
                    this.load()
                })
                .catch(this.onError)
        },

        toggle(row, value) {
            this.$http.patch(`/marketplace/admin/categories/${row.id}`, { is_visible: value })
                .then(({ data }) => { this.$message.success(data.message); this.load() })
                .catch(this.onError)
        },

        onError(error) {
            const message = error.response && error.response.data && error.response.data.message
            this.$message.error(typeof message === 'object' ? Object.values(message)[0][0] : (message || 'Ocurrió un error.'))
        },
    },
}
</script>
