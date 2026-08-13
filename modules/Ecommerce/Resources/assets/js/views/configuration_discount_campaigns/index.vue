<template>
  <div class="discount-campaigns mt-4 pt-4 border-top">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h4 class="mb-1"><strong>Campañas de descuento</strong></h4>
          <small class="text-muted">Descuentos reales programados. Esta configuración es independiente de Social Proof.</small>
        </div>
        <el-button type="primary" icon="el-icon-plus" @click="openForm()">Nueva campaña</el-button>
      </div>

      <div class="table-responsive" v-loading="loading">
        <table class="table campaign-table">
          <thead><tr>
            <th class="text-start">Campaña</th><th class="text-start">Descuento</th>
            <th class="text-start">Inicio</th><th class="text-start">Vencimiento</th>
            <th class="text-center">Estado</th><th class="text-end">Opciones</th>
          </tr></thead>
          <tbody>
            <tr v-for="row in records" :key="row.id">
              <td class="text-start fw-bold">{{ row.name }}</td>
              <td class="text-start"><el-tag type="primary" size="small">% {{ money(row.value) }}</el-tag></td>
              <td class="text-start">{{ formatDate(row.starts_at) }}</td>
              <td class="text-start">
                <el-tag v-if="isExpired(row)" type="danger" size="small">{{ formatDate(row.expires_at) }}</el-tag>
                <span v-else>{{ formatDate(row.expires_at) }}</span>
              </td>
              <td class="text-center"><el-switch v-model="row.is_active" active-color="#13ce66" inactive-color="#ff4949" @change="changeStatus(row)" /></td>
              <td class="text-end">
                <button class="btn btn-xs btn-info btn-shad me-1 campaign-action-btn" type="button" title="Editar" @click.prevent="openForm(row)">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"/>
                    <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415"/>
                    <path d="M16 5l3 3"/>
                  </svg>
                </button>
                <button class="btn btn-xs btn-danger btn-shad campaign-action-btn" type="button" title="Eliminar" @click.prevent="remove(row)">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M4 7l16 0"/><path d="M10 11l0 6"/><path d="M14 11l0 6"/>
                    <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/>
                    <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>
                  </svg>
                </button>
              </td>
            </tr>
            <tr v-if="!loading && records.length === 0"><td colspan="6" class="text-center text-muted py-4">No hay campañas registradas.</td></tr>
          </tbody>
        </table>
      </div>
      <div v-if="records.length" class="campaign-total text-muted">Total {{ records.length }}</div>

    <el-dialog :title="form.id ? 'Editar campaña de descuento' : 'Nueva campaña de descuento'" :visible.sync="dialogVisible" width="680px">
      <div class="row">
        <div class="col-12 form-group">
          <label>Nombre de la campaña <span class="text-danger">*</span></label>
          <el-input v-model="form.name" placeholder="Ej.: Cyber Wow" />
          <small v-if="error('name')" class="text-danger">{{ error('name') }}</small>
        </div>
        <div class="col-md-6 form-group">
          <label>Tipo de descuento <span class="text-danger">*</span></label>
          <el-select v-model="form.discount_type" class="w-100" disabled>
            <el-option label="Porcentaje (%)" value="percentage" />
          </el-select>
        </div>
        <div class="col-md-6 form-group">
          <label>Valor <span class="text-danger">*</span></label>
          <el-input-number v-model="form.value" :min="0.01" :max="100" :precision="2" class="w-100" />
          <small v-if="error('value')" class="text-danger">{{ error('value') }}</small>
        </div>
        <div class="col-12 form-group">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <label class="mb-0">Productos específicos</label>
            <div>
              <el-button type="text" size="mini" @click="selectAllProducts">Seleccionar todos</el-button>
              <el-button type="text" size="mini" @click="form.product_ids = []">Limpiar</el-button>
            </div>
          </div>
          <el-select v-model="form.product_ids" multiple filterable collapse-tags class="w-100" :class="{'all-products-selected': allProductsSelected}" placeholder="Selecciona uno o más productos">
            <el-option v-for="product in products" :key="product.id" :label="product.name" :value="product.id" />
          </el-select>
          <small class="text-muted">La campaña se aplicará a cualquiera de los productos seleccionados.</small>
        </div>
        <div class="col-12 form-group">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <label class="mb-0">Categorías específicas</label>
            <div>
              <el-button type="text" size="mini" @click="selectAllCategories">Seleccionar todas</el-button>
              <el-button type="text" size="mini" @click="form.category_ids = []">Limpiar</el-button>
            </div>
          </div>
          <el-select v-model="form.category_ids" multiple filterable collapse-tags class="w-100" :class="{'all-categories-selected': allCategoriesSelected}" placeholder="Selecciona una o más categorías">
            <el-option v-for="category in categories" :key="category.id" :label="category.name" :value="category.id" />
          </el-select>
          <small class="text-muted">También se aplicará a todos los productos que pertenezcan a estas categorías.</small>
        </div>
        <div class="col-md-6 form-group d-flex align-items-end pb-2">
          <el-switch v-model="form.is_active" active-text="Campaña activa" />
        </div>
        <div class="col-md-6 form-group">
          <label>Fecha y hora de vencimiento</label>
          <el-date-picker v-model="form.expires_at" type="datetime" value-format="yyyy-MM-dd HH:mm:ss" format="dd/MM/yyyy HH:mm" :picker-options="expirationPickerOptions" class="w-100" placeholder="Vencimiento" />
          <small v-if="error('expires_at')" class="text-danger">{{ error('expires_at') }}</small>
        </div>
        <div class="col-md-6 form-group d-flex align-items-center">
          <small class="text-muted">La campaña comenzará automáticamente en el momento en que sea activada.</small>
        </div>
      </div>
      <span slot="footer">
        <el-button @click="dialogVisible = false">Cancelar</el-button>
        <el-button type="primary" :loading="saving" @click="save">Guardar campaña</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  computed: {
    allProductsSelected() {
      return this.products.length > 0 && this.form.product_ids.length === this.products.length;
    },
    allCategoriesSelected() {
      return this.categories.length > 0 && this.form.category_ids.length === this.categories.length;
    }
  },
  data() {
    return {
      records: [], products: [], categories: [], loading: false, saving: false, dialogVisible: false, errors: {}, expirationRefreshTimer: null,
      expirationPickerOptions: {
        disabledDate(date) {
          return date.getTime() < new Date().setHours(0, 0, 0, 0);
        }
      },
      form: this.emptyForm()
    };
  },
  mounted() {
    this.load();
    this.loadOptions();
    this.expirationRefreshTimer = setInterval(() => this.load(), 30000);
  },
  beforeDestroy() {
    clearInterval(this.expirationRefreshTimer);
  },
  methods: {
    selectAllProducts() {
      this.form.product_ids = this.products.map(product => product.id);
    },
    selectAllCategories() {
      this.form.category_ids = this.categories.map(category => category.id);
    },
    emptyForm() {
      return { id: null, name: '', discount_type: 'percentage', value: 10, expires_at: null, is_active: true, product_ids: [], category_ids: [] };
    },
    loadOptions() {
      this.$http.get('/ecommerce/discount-campaigns/options').then(({ data }) => {
        this.products = data.products || [];
        this.categories = data.categories || [];
      });
    },
    load() {
      this.loading = true;
      this.$http.get('/ecommerce/discount-campaigns/records')
        .then(({ data }) => { this.records = data.records || []; })
        .finally(() => { this.loading = false; });
    },
    openForm(row = null) {
      this.errors = {};
      this.form = row ? Object.assign(this.emptyForm(), JSON.parse(JSON.stringify(row))) : this.emptyForm();
      this.dialogVisible = true;
    },
    save() {
      if (!this.form.expires_at) {
        this.errors = { expires_at: ['Selecciona la fecha y hora de vencimiento.'] };
        this.$message.error(this.errors.expires_at[0]);
        return;
      }
      const expiration = new Date(String(this.form.expires_at).replace(' ', 'T'));
      if (Number.isNaN(expiration.getTime()) || expiration.getTime() <= Date.now()) {
        this.errors = { expires_at: ['La fecha de vencimiento debe ser posterior a la fecha y hora actual.'] };
        this.$message.error(this.errors.expires_at[0]);
        return;
      }
      this.saving = true;
      this.errors = {};
      this.$http.post('/ecommerce/discount-campaigns', this.form)
        .then(({ data }) => {
          this.$message.success(data.message);
          this.dialogVisible = false;
          this.load();
        })
        .catch(error => {
          this.errors = error.response && error.response.data.errors ? error.response.data.errors : {};
          this.$message.error(error.response && error.response.data.message ? error.response.data.message : 'No fue posible guardar la campaña.');
        })
        .finally(() => { this.saving = false; });
    },
    changeStatus(row) {
      this.$http.post(`/ecommerce/discount-campaigns/${row.id}/status`, { is_active: row.is_active })
        .then(({ data }) => this.$message.success(data.message))
        .catch(() => { row.is_active = !row.is_active; this.$message.error('No fue posible cambiar el estado.'); });
    },
    remove(row) {
      this.$confirm(`¿Eliminar la campaña "${row.name}"?`, 'Confirmar', { type: 'warning' })
        .then(() => this.$http.delete(`/ecommerce/discount-campaigns/${row.id}`))
        .then(({ data }) => { this.$message.success(data.message); this.load(); })
        .catch(() => {});
    },
    error(field) { return this.errors[field] ? this.errors[field][0] : null; },
    money(value) { return Number(value || 0).toFixed(2); },
    formatDate(value) { return value ? value.substring(8, 10) + '/' + value.substring(5, 7) + '/' + value.substring(0, 4) + ' ' + value.substring(11, 16) : '—'; },
    isExpired(row) { return !!row.expires_at && new Date(String(row.expires_at).replace(' ', 'T')).getTime() <= Date.now(); }
  }
};
</script>

<style scoped>
.discount-campaigns { padding-left: 10px; padding-right: 10px; }
.campaign-table { margin-bottom: 10px; }
.campaign-table th { color: #17233d; font-weight: 600; white-space: nowrap; }
.campaign-table td { vertical-align: middle; }
.campaign-total { font-size: 13px; padding-bottom: 8px; }
.campaign-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 25px;
  padding: 0;
}
.all-products-selected /deep/ .el-select__tags > span,
.all-categories-selected /deep/ .el-select__tags > span { display: none; }
.all-products-selected /deep/ .el-select__tags::before,
.all-categories-selected /deep/ .el-select__tags::before {
  display: inline-flex;
  align-items: center;
  height: 20px;
  line-height: 18px;
  padding: 0 6px;
  color: #409eff;
  background: #ecf5ff;
  border: 1px solid #409eff;
  border-radius: 10px;
  font-size: 12px;
  white-space: nowrap;
  box-sizing: border-box;
}
.all-products-selected /deep/ .el-select__tags::before { content: 'Todos los productos'; }
.all-categories-selected /deep/ .el-select__tags::before { content: 'Todas las categorías'; }
</style>
