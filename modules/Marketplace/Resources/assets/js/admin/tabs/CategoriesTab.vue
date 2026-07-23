<template>
    <div>
        <div class="alert alert-info mb-3" role="alert">
            <strong>La taxonomía se construye sola con lo que envían las tiendas.</strong>
            No hace falta crear categorías. Solo puedes cambiar el nombre visible y ocultarlas:
            sus productos siguen publicados bajo «Otros».
        </div>

        <div class="table-responsive" v-loading="loading">
            <table class="table">
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Slug</th>
                    <th class="text-center">Productos</th>
                    <th class="text-center">Visible</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="row in records" :key="row.id">
                    <td>
                        <el-input v-if="editing === row.id" v-model="draft" :maxlength="120"
                                  @keyup.enter.native="save(row)"/>
                        <span v-else>{{ row.name }}</span>
                    </td>
                    <td><code class="text-muted">{{ row.slug }}</code></td>
                    <td class="text-center">{{ row.items_count }}</td>
                    <td class="text-center">
                        <el-switch :value="row.is_visible" @change="toggle(row, $event)"/>
                    </td>
                    <td class="text-end">
                        <template v-if="editing === row.id">
                            <el-button type="primary" @click="save(row)">Guardar</el-button>
                            <el-button @click="editing = null">Cancelar</el-button>
                        </template>
                        <el-button v-else @click="edit(row)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" /><path d="M16 5l3 3" /></svg>
                            Renombrar
                        </el-button>
                    </td>
                </tr>

                <tr v-if="!records.length && !loading">
                    <td colspan="5" class="text-center text-muted">Aún no hay categorías</td>
                </tr>
                </tbody>
            </table>
        </div>
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
