<template>
  <div>
    <header class="page-header">
      <h2 class="text-white">
        <a href="/dashboard">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="icon icon-tabler icons-tabler-filled icon-tabler-apps"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 3h-4a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2z" /><path d="M9 13h-4a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2z" /><path d="M19 13h-4a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2z" /><path d="M17 3a1 1 0 0 1 .993 .883l.007 .117v2h2a1 1 0 0 1 .117 1.993l-.117 .007h-2v2a1 1 0 0 1 -1.993 .117l-.007 -.117v-2h-2a1 1 0 0 1 -.117 -1.993l.117 -.007h2v-2a1 1 0 0 1 1 -1z" /></svg>
        </a>
      </h2>
      <ol class="breadcrumbs">
        <li class="active"><span class="text-white">Servicios Extras</span></li>
      </ol>
    </header>
     <div class="card mb-0">
      <div class="card">
        <div class="card-header bg-info bg-info-customer-admin">
          <h3 class="my-0">Servicio ApiDocs</h3>
        </div>
        <div class="card-body">
          <div class="card mb-0" v-loading="loading" element-loading-background="rgba(255, 255, 255, 0.7)">
            <div class="row">
              <div class="col-12 form-group mb-4">
                <label>Habilitar servicio extra para el sistema</label>
                <Tooltip style="margin-left: 5px;" content="Habilitar el servicio extra para el sistema, esto permitirá que los clientes puedan acceder a la documentación de la API.">
                  <i class="fa fa-info-circle"></i>
                </Tooltip>
                <el-switch style="margin-left: 10px;" v-model="form.isActiveApidocs" @change="setData"></el-switch>
              </div>
              <div class="col-12 form-group mb-4" v-if="form.isActiveApidocs">
                Ventana de configuracion
              </div>
              <div class="col-12 form-group mb-4" v-if="!form.isActiveApidocs">
                Ventana de ventas
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import { Tooltip } from 'element-ui';


export default {
  name: 'ExtraServicesIndex',
  components: {
    Tooltip
  },
  data() {
    return {
      resource: '/extra-services',
      loading: false,
      form: {
        isActiveApidocs: false,
      },
      errors: {},
    };
  },
  async created() {
    await this.getData();
  },
  methods: {
    async getData() {
      this.loading = true;
      try {
        const response = await this.$http.get(this.resource + '/record');
        this.form = response.data.data;
      } catch (error) {
          console.error('Error fetching data:', error);
      } finally {
        this.loading = false;
      }
    },
    async setData() {
      this.loading = true;
      try {
        const response = await this.$http.post(this.resource + '/store', this.form);
        if (response.data.success) {
          this.$message({
            message: 'Configuración guardada exitosamente',
              type: 'success'
            });
          }
          await this.getData();
        } catch (error) {
          console.error('Error saving data:', error);
          this.$message({
            message: 'Error al guardar la configuración',
            type: 'error'
          });
          await this.getData();
        } finally {
          this.loading = false;
        }
    },
  },
};
</script>