
 <template>
  <div>
    <form autocomplete="off" @submit.prevent="submit">
      <div class="form-body">
        <div class="row">
          <!-- Métodos de pago alternativos -->
          <div class="col-md-6">
            <div class="form-group form-modern mb-3">
              <el-switch v-model="form.enable_yape" :active-value="1" :inactive-value="0"></el-switch>
              <label class="ms-2 mb-0">Habilitar pago con Yape</label>
              <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">
                Muestra la opción &ldquo;Pagar con YAPE&rdquo; en el checkout.
              </small>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group form-modern mb-3">
              <el-switch v-model="form.enable_transfer" :active-value="1" :inactive-value="0"></el-switch>
              <label class="ms-2 mb-0">Habilitar transferencia bancaria</label>
              <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">
                Muestra la opción &ldquo;Transferencia Bancaria&rdquo; en el checkout.
              </small>
            </div>
          </div>
          <div class="col-md-12" v-if="form.enable_transfer === 1" style="animation: fadeIn 0.3s;">
            <div class="form-group form-modern mb-3" style="background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
              <label class="mb-2" style="font-weight: 600;">Cuentas Bancarias Disponibles</label>
              <small class="d-block text-muted mb-3" style="line-height: 1.5;">
                Seleccione qué cuentas bancarias se mostrarán a los clientes en el checkout público de la tienda virtual.
              </small>
              <div v-if="bank_accounts.length > 0">
                <el-checkbox-group v-model="form.ecommerce_bank_account_ids">
                  <el-checkbox v-for="bank in bank_accounts" :key="bank.id" :label="bank.id" style="display: block; margin-bottom: 8px;">
                    @{{ bank.description }}
                  </el-checkbox>
                </el-checkbox-group>
              </div>
              <div v-else>
                <el-alert title="No se encontraron cuentas bancarias registradas en el sistema. Registre una primero." type="warning" show-icon :closable="false"></el-alert>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="form-actions text-end float-end pt-2">
        <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
      </div>
    </form>
  </div>
</template>




<script>
export default {
  data() {
    return {
      loading_submit: false,
      // headers: headers_token,
      resource: "ecommerce",
      errors: {},
      form: {},
      bank_accounts: [],
      soap_sends: [],
      soap_types: []
    };
  },
  async created() {
    await this.initForm();

    await this.$http.get(`/${this.resource}/record`).then(response => {
      if (response.data !== "") {
        let data = response.data.data;
        this.form.id = data.id;
        this.form.enable_yape = data.enable_yape ? 1 : 0;
        this.form.enable_transfer = data.enable_transfer ? 1 : 0;
        this.bank_accounts = response.data.bank_accounts || [];
        this.form.ecommerce_bank_account_ids = (data.preferences && data.preferences.ecommerce_bank_account_ids) ? data.preferences.ecommerce_bank_account_ids : [];
      }
    });
  },
  methods: {
    openCulqi() {
      window.open("https://www.culqi.com");
    },
    openPaypal() {
      window.open(
        "https://developer.paypal.com/docs/classic/paypal-payments-standard/integration-guide/buy-now-step-1/#open-the-paypal-button-creation-page"
      );
    },
    initForm() {
      this.errors = {};
      this.form = {
        id: null,
        enable_yape: 0,
        enable_transfer: 0,
        ecommerce_bank_account_ids: [],
      };
    },
    submit() {
      this.loading_submit = true;
      this.$http
        .post(`/${this.resource}/configuration_culqui`, this.form)
        .then(response => {
          if (response.data.success) {
            this.$message.success(response.data.message);
          } else {
            this.$message.error(response.data.message);
          }
        })
        .catch(error => {
          if (error.response.status === 422) {
            this.errors = error.response.data;
          } else {
            console.log(error);
          }
        })
        .then(() => {
          this.loading_submit = false;
        });
    },
    submit_paypal() {}
  }
};
</script>

