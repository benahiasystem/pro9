<!-- filepath: c:\Aplicaciones\laragon\sites\buho\pro9dev001\modules\ExtraServices\Resources\assets\js\views\components\apidocs-usage.vue -->
<template>
  <div>
    <div class="mb-3">
      <strong>Uso del servicio ApiDocs</strong>
    </div>

    <div class="table-responsive">
      <el-table
        :data="paginatedRows"
        border
        stripe
        style="width: 100%;"
        empty-text="No hay registros disponibles"
      >
        <el-table-column prop="client_id" label="ID Cliente" width="110" />
        <el-table-column prop="client_name" label="Cliente" min-width="180" />
        <el-table-column prop="hostname" label="Hostname" min-width="240" />
        <el-table-column prop="quantity" label="Cantidad" width="120" />
        <el-table-column prop="month" label="Mes" width="120" />
      </el-table>
    </div>

    <div class="d-flex justify-content-end mt-3" v-if="rows.length > pageSize">
      <el-pagination
        background
        layout="prev, pager, next"
        :current-page="currentPage"
        :page-size="pageSize"
        :total="rows.length"
        @current-change="handlePageChange"
      />
    </div>

    <div v-if="!loading && rows.length === 0" class="text-muted mt-3">
      No hay registros disponibles.
    </div>
  </div>
</template>

<script>
import { Table, TableColumn, Pagination } from 'element-ui';

export default {
  name: 'ApidocsUsage',
  components: {
    'el-table': Table,
    'el-table-column': TableColumn,
    'el-pagination': Pagination,
  },
  data() {
    return {
      loading: false,
      rows: [],
      currentPage: 1,
      pageSize: 10,
      resource: '/extra-services/apidocs/usage/records',
    };
  },
  async created() {
    await this.getData();
  },
  computed: {
    paginatedRows() {
      const start = (this.currentPage - 1) * this.pageSize;
      return this.rows.slice(start, start + this.pageSize);
    },
  },
  methods: {
    async getData() {
      this.loading = true;
      try {
        const response = await this.$http.get(this.resource);
        this.rows = response?.data?.data || [];
        this.currentPage = 1;
      } catch (error) {
        console.error('Error fetching apidocs usage:', error);
        this.rows = [];
        this.currentPage = 1;
      } finally {
        this.loading = false;
      }
    },
    handlePageChange(page) {
      this.currentPage = page;
    },
  },
};
</script>