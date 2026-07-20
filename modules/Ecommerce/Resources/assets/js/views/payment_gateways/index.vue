<template>
  <div>
    <div class="form-body">
      <div class="row">
        <!-- Yape -->
        <div class="col-md-6" v-if="gateway_availability.yape">
          <div class="form-group form-modern mb-3">
            <el-switch
              v-model="form.enable_yape"
              :active-value="1"
              :inactive-value="0"
              :disabled="saving.yape"
              @change="val => saveAuto('yape', val)"
            ></el-switch>
            <label class="ms-2 mb-0">Habilitar pago con Yape</label>
            <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">
              Muestra la opción &ldquo;Pagar con YAPE&rdquo; en el checkout.
            </small>
          </div>
        </div>

        <div class="col-md-6">
          <div class="form-group form-modern mb-3">
            <el-switch
              v-model="form.enable_transfer"
              :active-value="1"
              :inactive-value="0"
              :disabled="saving.transfer"
              @change="val => saveAuto('transfer', val)"
            ></el-switch>
            <label class="ms-2 mb-0">Habilitar transferencia bancaria</label>
            <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">
              Muestra la opción &ldquo;Transferencia Bancaria&rdquo; en el checkout.
            </small>
          </div>

          <div v-if="form.enable_transfer === 1" class="payment-gateway-panel bank-accounts-panel">
            <label class="mb-2" style="font-weight: 600;">Cuentas Bancarias Disponibles</label>
            <small class="d-block text-muted mb-3" style="line-height: 1.5;">
              Seleccione qué cuentas bancarias se mostrarán a los clientes en el checkout público de la tienda virtual.
            </small>
            <div v-if="bank_accounts.length > 0">
              <el-checkbox-group
                v-model="form.ecommerce_bank_account_ids"
                :disabled="saving.transfer"
                @change="() => saveAuto('transfer', form.enable_transfer, { silent: true })"
              >
                <el-checkbox v-for="bank in bank_accounts" :key="bank.id" :label="bank.id" style="display: block; margin-bottom: 8px;">
                  {{ bank.description }}
                </el-checkbox>
              </el-checkbox-group>
            </div>
            <div v-else>
              <el-alert title="No se encontraron cuentas bancarias registradas en el sistema. Registre una primero." type="warning" show-icon :closable="false"></el-alert>
            </div>
          </div>
        </div>

        <!-- Izipay -->
        <div class="col-md-6" v-if="gateway_availability.izipay">
          <div class="form-group form-modern mb-3">
            <el-switch
              v-model="form.enable_izipay"
              :active-value="1"
              :inactive-value="0"
              :disabled="saving.izipay"
              @change="val => onExclusiveGatewayToggle('izipay', val)"
            ></el-switch>
            <label class="ms-2 mb-0">Habilitar Izipay en Ecommerce</label>
          </div>

          <div v-if="form.enable_izipay === 1" class="payment-gateway-panel">
            <div class="form-group mb-3">
              <label class="mb-2" style="font-weight: 600;">Título del método</label>
              <el-input
                v-model="form.title_izipay"
                placeholder="Ej: Pago con Tarjeta (Izipay)"
                :disabled="saving.izipay"
                @blur="() => saveAuto('izipay', form.enable_izipay, { silent: true })"
              ></el-input>
            </div>
            <div class="form-group mb-0">
              <label class="mb-2" style="font-weight: 600;">Descripción</label>
              <el-input
                type="textarea"
                :rows="2"
                v-model="form.description_izipay"
                placeholder="Texto explicativo para el checkout..."
                :disabled="saving.izipay"
                @blur="() => saveAuto('izipay', form.enable_izipay, { silent: true })"
              ></el-input>
            </div>
          </div>
        </div>

        <!-- Mercado Pago -->
        <div class="col-md-6" v-if="gateway_availability.mercadopago">
          <div class="form-group form-modern mb-3">
            <el-switch
              v-model="form.enable_mp"
              :active-value="1"
              :inactive-value="0"
              :disabled="saving.mercadopago"
              @change="val => saveAuto('mercadopago', val)"
            ></el-switch>
            <label class="ms-2 mb-0">Habilitar Mercado Pago en Ecommerce</label>
          </div>

          <div v-if="form.enable_mp === 1" class="payment-gateway-panel">
            <div class="form-group mb-3">
              <label class="mb-2" style="font-weight: 600;">Título del método</label>
              <el-input
                v-model="form.title_mp"
                placeholder="Ej: Paga con Mercado Pago"
                :disabled="saving.mercadopago"
                @blur="() => saveAuto('mercadopago', form.enable_mp, { silent: true })"
              ></el-input>
            </div>
            <div class="form-group mb-0">
              <label class="mb-2" style="font-weight: 600;">Descripción</label>
              <el-input
                type="textarea"
                :rows="2"
                v-model="form.description_mp"
                placeholder="Texto explicativo para el checkout..."
                :disabled="saving.mercadopago"
                @blur="() => saveAuto('mercadopago', form.enable_mp, { silent: true })"
              ></el-input>
            </div>
          </div>
        </div>

        <!-- Culqi -->
        <div class="col-md-6" v-if="gateway_availability.culqi">
          <div class="form-group form-modern mb-3">
            <el-switch
              v-model="form.enable_culqi"
              :active-value="1"
              :inactive-value="0"
              :disabled="saving.culqi"
              @change="val => onExclusiveGatewayToggle('culqi', val)"
            ></el-switch>
            <label class="ms-2 mb-0">Habilitar Culqi en Ecommerce</label>
          </div>

          <div v-if="form.enable_culqi === 1" class="payment-gateway-panel">
            <div class="form-group mb-3">
              <label class="mb-2" style="font-weight: 600;">Título del método</label>
              <el-input
                v-model="form.title_culqi"
                placeholder="Ej: Pago con Tarjeta"
                :disabled="saving.culqi"
                @blur="() => saveAuto('culqi', form.enable_culqi, { silent: true })"
              ></el-input>
            </div>
            <div class="form-group mb-0">
              <label class="mb-2" style="font-weight: 600;">Descripción</label>
              <el-input
                type="textarea"
                :rows="2"
                v-model="form.description_culqi"
                placeholder="Texto explicativo para el checkout..."
                :disabled="saving.culqi"
                @blur="() => saveAuto('culqi', form.enable_culqi, { silent: true })"
              ></el-input>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="form-group form-modern mb-3">
            <el-switch
              v-model="form.enable_cash"
              :active-value="1"
              :inactive-value="0"
              :disabled="saving.cash"
              @change="val => saveAuto('cash', val)"
            ></el-switch>
            <label class="ms-2 mb-0">Habilitar pago contra entrega</label>
            <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">
              Muestra la opción &ldquo;Pago contra entrega&rdquo; en el checkout.
            </small>
          </div>

          <div v-if="form.enable_cash === 1" class="payment-gateway-panel">
            <div class="form-group mb-3">
              <label class="mb-2" style="font-weight: 600;">Título</label>
              <el-input
                v-model="form.cash_title"
                placeholder="Ej: Pago contra entrega"
                :disabled="saving.cash"
                @blur="() => saveAuto('cash', form.enable_cash, { silent: true })"
              ></el-input>
            </div>
            <div class="form-group mb-3">
              <label class="mb-2" style="font-weight: 600;">Descripción</label>
              <el-input
                type="textarea"
                :rows="3"
                v-model="form.cash_description"
                placeholder="Instrucciones para el cliente..."
                :disabled="saving.cash"
                @blur="() => saveAuto('cash', form.enable_cash, { silent: true })"
              ></el-input>
            </div>
            <div class="form-group mb-0">
              <el-checkbox
                v-model="form.cash_pickup_only"
                :true-label="1"
                :false-label="0"
                :disabled="saving.cash"
                @change="() => saveAuto('cash', form.enable_cash, { silent: true })"
              >Solo aplicar en recojo en tienda</el-checkbox>
              <small class="d-block text-muted mt-1" style="line-height: 1.5;">
                Si está marcado, este método de pago no aparecerá si el cliente elige envío a domicilio.
              </small>
            </div>
          </div>
        </div>

        <div class="col-md-12 mt-4">
          <el-alert title="Configuración de Credenciales" show-icon type="info" class="mb-4" :closable="false">
            Las credenciales de Izipay, Mercado Pago y Culqi se obtienen automáticamente desde la configuración central (Empresa > Configuración de pagos).
            Aquí puedes habilitar cuáles mostrar en la tienda virtual y personalizar su título y descripción.
          </el-alert>
          <el-alert
            v-if="!hasAvailableGateways"
            title="No hay pasarelas de pago disponibles. Actívelas y complete sus credenciales en Empresa > Configuración de pagos."
            show-icon
            type="warning"
            class="mb-4"
            :closable="false"
          ></el-alert>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.payment-gateway-panel {
  animation: fadeIn 0.3s;
  background: #f9f9f9;
  padding: 15px;
  border-radius: 8px;
  border: 1px solid #eee;
  margin-bottom: 1rem;
}

.bank-accounts-panel {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}
</style>

<script>
const GATEWAY_TOGGLE_FIELDS = {
  yape: 'enable_yape',
  transfer: 'enable_transfer',
  izipay: 'enable_izipay',
  mercadopago: 'enable_mp',
  culqi: 'enable_culqi',
  cash: 'enable_cash',
};

const IZIPAY_CULQI_EXCLUSIVITY_MESSAGE = 'No puedes activar Izipay y Culqi simultáneamente';

export default {
  data() {
    return {
      resource: "ecommerce",
      errors: {},
      form: {},
      bank_accounts: [],
      gateway_availability: {
        yape: false,
        mercadopago: false,
        culqi: false,
        izipay: false,
      },
      saving: {
        yape: false,
        transfer: false,
        izipay: false,
        mercadopago: false,
        culqi: false,
        cash: false,
      },
    };
  },
  computed: {
    hasAvailableGateways() {
      return Object.values(this.gateway_availability).some(Boolean);
    },
  },
  async created() {
    await this.initForm();
    await this.loadRecord();
  },
  methods: {
    async loadRecord() {
      const response = await this.$http.get(`/${this.resource}/record`);
      if (response.data !== "") {
        this.applyServerState(response.data);
      }
    },
    applyServerState(payload) {
      const data = payload.data || {};
      const prefs = data.preferences || {};

      this.gateway_availability = payload.gateway_availability || this.gateway_availability;
      this.bank_accounts = payload.bank_accounts || [];

      this.form.id = data.id;
      this.form.enable_yape = (this.gateway_availability.yape && data.enable_yape) ? 1 : 0;
      this.form.enable_transfer = data.enable_transfer ? 1 : 0;
      this.form.ecommerce_bank_account_ids = prefs.ecommerce_bank_account_ids || [];
      this.form.enable_cash = prefs.enable_cash ? parseInt(prefs.enable_cash) : 0;
      this.form.cash_title = prefs.cash_title || 'Pago contra entrega';
      this.form.cash_description = prefs.cash_description || '';
      this.form.cash_pickup_only = prefs.cash_pickup_only ? 1 : 0;

      this.form.enable_izipay = (this.gateway_availability.izipay && prefs.enable_izipay) ? parseInt(prefs.enable_izipay) : 0;
      this.form.title_izipay = prefs.title_izipay || 'Pago con Izipay';
      this.form.description_izipay = prefs.description_izipay || '';

      this.form.enable_mp = (this.gateway_availability.mercadopago && prefs.enable_mp) ? parseInt(prefs.enable_mp) : 0;
      this.form.title_mp = prefs.title_mp || 'Mercado Pago';
      this.form.description_mp = prefs.description_mp || '';

      this.form.enable_culqi = (this.gateway_availability.culqi && prefs.enable_culqi) ? parseInt(prefs.enable_culqi) : 0;
      this.form.title_culqi = prefs.title_culqi || 'Pago con Tarjeta (Culqi)';
      this.form.description_culqi = prefs.description_culqi || '';
    },
    initForm() {
      this.errors = {};
      this.form = {
        id: null,
        enable_yape: 0,
        enable_transfer: 0,
        enable_cash: 0,
        cash_title: 'Pago contra entrega',
        cash_description: '',
        cash_pickup_only: 0,
        ecommerce_bank_account_ids: [],
        enable_izipay: 0,
        title_izipay: 'Pago con Izipay',
        description_izipay: '',
        enable_mp: 0,
        title_mp: 'Mercado Pago',
        description_mp: '',
        enable_culqi: 0,
        title_culqi: 'Pago con Tarjeta (Culqi)',
        description_culqi: '',
      };
    },
    revertToggle(gateway, previousValue) {
      const field = GATEWAY_TOGGLE_FIELDS[gateway];
      if (field) {
        this.$set(this.form, field, previousValue);
      }
    },
    hasExclusiveGatewayConflict(gateway, newValue) {
      if (newValue !== 1) {
        return false;
      }

      if (gateway === 'izipay') {
        return this.form.enable_culqi === 1;
      }

      if (gateway === 'culqi') {
        return this.form.enable_izipay === 1;
      }

      return false;
    },
    showExclusiveGatewayToast() {
      this.$message({
        message: IZIPAY_CULQI_EXCLUSIVITY_MESSAGE,
        type: 'error',
        duration: 5000,
        showClose: true,
      });
    },
    onExclusiveGatewayToggle(gateway, newValue) {
      if (this.hasExclusiveGatewayConflict(gateway, newValue)) {
        this.$nextTick(() => this.revertToggle(gateway, 0));
        this.showExclusiveGatewayToast();
        return;
      }

      this.saveAuto(gateway, newValue);
    },
    isExclusiveGatewayError(error) {
      if (!error.response || error.response.status !== 422) {
        return false;
      }

      const data = error.response.data || {};
      const errors = data.errors || {};
      const messages = [
        data.message,
        ...(errors.enable_izipay || []),
        ...(errors.enable_culqi || []),
      ].filter(Boolean);

      return messages.some(message => message.includes('Izipay y Culqi'));
    },
    getErrorMessage(error) {
      const data = error.response && error.response.data ? error.response.data : {};
      if (data.message) {
        return data.message;
      }
      const errors = data.errors || {};
      const firstKey = Object.keys(errors)[0];
      if (firstKey && errors[firstKey] && errors[firstKey][0]) {
        return errors[firstKey][0];
      }
      return 'No se pudo guardar la configuración';
    },
    async saveAuto(gateway, newValue, options = {}) {
      const { silent = false } = options;
      const field = GATEWAY_TOGGLE_FIELDS[gateway];
      const previousValue = field && newValue !== null && newValue !== undefined
        ? (newValue === 1 ? 0 : 1)
        : null;

      if (this.saving[gateway]) {
        return;
      }

      this.saving[gateway] = true;

      try {
        const response = await this.$http.post(`/${this.resource}/configuration_culqui`, this.form);

        if (response.data.success) {
          this.applyServerState(response.data);
          if (!silent) {
            this.$message.success(response.data.message || 'Configuración actualizada');
          }
        } else {
          const message = response.data.message || 'No se pudo guardar la configuración';

          if (previousValue !== null && this.isExclusiveGatewayMessage(message)) {
            this.$nextTick(() => this.revertToggle(gateway, previousValue));
            this.showExclusiveGatewayToast();
          } else {
            if (previousValue !== null) {
              this.$nextTick(() => this.revertToggle(gateway, previousValue));
            }
            this.$message.error(message);
          }
        }
      } catch (error) {
        if (this.isExclusiveGatewayError(error)) {
          if (previousValue !== null) {
            this.$nextTick(() => this.revertToggle(gateway, previousValue));
          }
          this.showExclusiveGatewayToast();
        } else {
          if (previousValue !== null) {
            this.$nextTick(() => this.revertToggle(gateway, previousValue));
          }
          this.$message.error(this.getErrorMessage(error));
        }
      } finally {
        this.saving[gateway] = false;
      }
    },
    isExclusiveGatewayMessage(message) {
      return typeof message === 'string' && message.includes('Izipay y Culqi');
    },
  }
};
</script>
