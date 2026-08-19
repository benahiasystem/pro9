<template>
  <div>
    <div class="form-body">
      <div class="row">
        <!-- Yape -->
        <div class="col-md-6" v-if="gateway_availability.yape">
          <div class="form-group form-modern mb-3">
            <div class="gateway-switch__control">
              <el-switch
                v-model="form.enable_yape"
                :active-value="1"
                :inactive-value="0"
                :disabled="loading_submit"
              ></el-switch>
              <span class="gateway-switch__brand gateway-switch__brand--wide">
                <img :src="gatewayLogos.yape" alt="Yape" class="gateway-switch__logo">
              </span>
              <label class="mb-0 gateway-switch__label">Habilitar pago con Yape</label>
            </div>
            <small class="d-block text-muted gateway-switch__hint">
              Muestra la opción &ldquo;Pagar con YAPE&rdquo; en el checkout.
            </small>
          </div>
        </div>

        <div class="col-md-6">
          <div class="form-group form-modern mb-3">
            <div class="gateway-switch__control">
              <el-switch
                v-model="form.enable_transfer"
                :active-value="1"
                :inactive-value="0"
                :disabled="loading_submit"
              ></el-switch>
              <span class="gateway-switch__brand gateway-switch__brand--square">
                <!-- <img :src="gatewayLogos.transfer" alt="Transferencia bancaria" class="gateway-switch__logo"> -->
                 <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building-bank"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 21l18 0" /><path d="M3 10l18 0" /><path d="M5 6l7 -3l7 3" /><path d="M4 10l0 11" /><path d="M20 10l0 11" /><path d="M8 14l0 3" /><path d="M12 14l0 3" /><path d="M16 14l0 3" /></svg>
              </span>
              <label class="mb-0 gateway-switch__label">Habilitar transferencia bancaria</label>
            </div>
            <small class="d-block text-muted gateway-switch__hint">
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
                :disabled="loading_submit"
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
            <div class="gateway-switch__control">
              <el-switch
                v-model="form.enable_izipay"
                :active-value="1"
                :inactive-value="0"
                :disabled="loading_submit"
                @change="val => onExclusiveGatewayToggle('izipay', val)"
              ></el-switch>
              <span class="gateway-switch__brand gateway-switch__brand--wide">
                <img :src="gatewayLogos.izipay" alt="Izipay" class="gateway-switch__logo">
              </span>
              <label class="mb-0 gateway-switch__label">Habilitar Izipay en Ecommerce</label>
            </div>
          </div>

          <div v-if="form.enable_izipay === 1" class="payment-gateway-panel">
            <div class="form-group mb-3">
              <label class="mb-2" style="font-weight: 600;">Título del método</label>
              <el-input
                v-model="form.title_izipay"
                placeholder="Ej: Pago con Tarjeta (Izipay)"
                :disabled="loading_submit"
              ></el-input>
            </div>
            <div class="form-group mb-0">
              <label class="mb-2" style="font-weight: 600;">Descripción</label>
              <el-input
                type="textarea"
                :rows="2"
                v-model="form.description_izipay"
                placeholder="Texto explicativo para el checkout..."
                :disabled="loading_submit"
              ></el-input>
            </div>
          </div>
        </div>

        <!-- Mercado Pago -->
        <div class="col-md-6" v-if="gateway_availability.mercadopago">
          <div class="form-group form-modern mb-3">
            <div class="gateway-switch__control">
              <el-switch
                v-model="form.enable_mp"
                :active-value="1"
                :inactive-value="0"
                :disabled="loading_submit"
              ></el-switch>
              <span class="gateway-switch__brand gateway-switch__brand--wide">
                <img :src="gatewayLogos.mercadopago" alt="Mercado Pago" class="gateway-switch__logo">
              </span>
              <label class="mb-0 gateway-switch__label">Habilitar Mercado Pago en Ecommerce</label>
            </div>
          </div>

          <div v-if="form.enable_mp === 1" class="payment-gateway-panel">
            <div class="form-group mb-3">
              <label class="mb-2" style="font-weight: 600;">Título del método</label>
              <el-input
                v-model="form.title_mp"
                placeholder="Ej: Paga con Mercado Pago"
                :disabled="loading_submit"
              ></el-input>
            </div>
            <div class="form-group mb-0">
              <label class="mb-2" style="font-weight: 600;">Descripción</label>
              <el-input
                type="textarea"
                :rows="2"
                v-model="form.description_mp"
                placeholder="Texto explicativo para el checkout..."
                :disabled="loading_submit"
              ></el-input>
            </div>
          </div>
        </div>

        <!-- Culqi -->
        <div class="col-md-6" v-if="gateway_availability.culqi">
          <div class="form-group form-modern mb-3">
            <div class="gateway-switch__control">
              <el-switch
                v-model="form.enable_culqi"
                :active-value="1"
                :inactive-value="0"
                :disabled="loading_submit"
                @change="val => onExclusiveGatewayToggle('culqi', val)"
              ></el-switch>
              <span class="gateway-switch__brand gateway-switch__brand--wide">
                <img :src="gatewayLogos.culqi" alt="Culqi" class="gateway-switch__logo">
              </span>
              <label class="mb-0 gateway-switch__label">Habilitar Culqi en Ecommerce</label>
            </div>
          </div>

          <div v-if="form.enable_culqi === 1" class="payment-gateway-panel">
            <div class="form-group mb-3">
              <label class="mb-2" style="font-weight: 600;">Título del método</label>
              <el-input
                v-model="form.title_culqi"
                placeholder="Ej: Pago con Tarjeta"
                :disabled="loading_submit"
              ></el-input>
            </div>
            <div class="form-group mb-0">
              <label class="mb-2" style="font-weight: 600;">Descripción</label>
              <el-input
                type="textarea"
                :rows="2"
                v-model="form.description_culqi"
                placeholder="Texto explicativo para el checkout..."
                :disabled="loading_submit"
              ></el-input>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="form-group form-modern mb-3">
            <div class="gateway-switch__control">
              <el-switch
                v-model="form.enable_cash"
                :active-value="1"
                :inactive-value="0"
                :disabled="loading_submit"
              ></el-switch>
              <span class="gateway-switch__brand gateway-switch__brand--square">
                <!-- <img :src="gatewayLogos.cash" alt="Pago contra entrega" class="gateway-switch__logo"> -->
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-truck-delivery"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M7 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5" /><path d="M3 9l4 0" /></svg>
              </span>
              <label class="mb-0 gateway-switch__label">Habilitar pago contra entrega</label>
            </div>
            <small class="d-block text-muted gateway-switch__hint">
              Muestra la opción &ldquo;Pago contra entrega&rdquo; en el checkout.
            </small>
          </div>

          <div v-if="form.enable_cash === 1" class="payment-gateway-panel">
            <div class="form-group mb-3">
              <label class="control-label" style="font-weight: 600;">Título</label>
              <el-input
                v-model="form.cash_title"
                placeholder="Ej: Pago contra entrega"
                :disabled="loading_submit"
              ></el-input>
            </div>
            <div class="form-group mb-3">
              <label class="control-label" style="font-weight: 600;">Descripción</label>
              <el-input
                type="textarea"
                :rows="3"
                v-model="form.cash_description"
                placeholder="Instrucciones para el cliente..."
                :disabled="loading_submit"
              ></el-input>
            </div>
            <div class="form-group mb-0">
              <el-checkbox
                v-model="form.cash_pickup_only"
                :true-label="1"
                :false-label="0"
                :disabled="loading_submit"
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
      <div class="form-actions text-end mt-4">
        <el-button type="primary" :loading="loading_submit" @click="submit">Guardar</el-button>
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
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 1rem;
}

.bank-accounts-panel {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

.gateway-switch__control {
  display: flex;
  align-items: center;
  flex-wrap: nowrap;
  gap: 10px;
  min-height: 30px;
}

.gateway-switch__brand {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  height: 30px;
  padding: 0;
  border: 1px solid #eee;
  border-radius: 6px;
  background: #fff;
  overflow: hidden;
  box-sizing: border-box;
}

.gateway-switch__brand--wide {
  width: 88px;
  min-width: 80px;
  height: 30px;
  padding: 0;
  border: 1px solid #eee;
  background: #fff;
  border-radius: 6px;
}

.gateway-switch__brand--square {
  width: 36px;
  height: 30px;
  padding: 3px;
  border: 1px solid #eee;
  background: #fff;
}

.gateway-switch__logo {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
  object-position: center;
}

.gateway-switch__brand--wide .gateway-switch__logo {
  max-height: none;
  border-radius: 6px;
}

.gateway-switch__brand--square .gateway-switch__logo {
  width: 24px;
  height: 24px;
}

.gateway-switch__label {
  flex: 1;
  min-width: 0;
  font-weight: 600;
  line-height: 1.4;
}

.gateway-switch__hint {
  margin-top: 8px;
  margin-left: 0;
  padding-left: 50px;
  padding-right: 0 !important;
  line-height: 1.5;
}
</style>

<script>
const IZIPAY_CULQI_EXCLUSIVITY_MESSAGE = 'No puedes activar Izipay y Culqi simultáneamente';

const GATEWAY_LOGO_BASE = '/porto-ecommerce/assets/images/payment-gateways';

export default {
  data() {
    return {
      resource: "ecommerce",
      errors: {},
      form: {},
      loading_submit: false,
      bank_accounts: [],
      gatewayLogos: {
        yape: `${GATEWAY_LOGO_BASE}/yape.svg`,
        transfer: `${GATEWAY_LOGO_BASE}/bank-transfer.svg`,
        izipay: `${GATEWAY_LOGO_BASE}/izipay-official.svg?v=5`,
        mercadopago: `${GATEWAY_LOGO_BASE}/mercado-pago.svg`,
        culqi: `${GATEWAY_LOGO_BASE}/culqi.svg?v=2`,
        cash: `${GATEWAY_LOGO_BASE}/cash-delivery.svg`,
      },
      gateway_availability: {
        yape: false,
        mercadopago: false,
        culqi: false,
        izipay: false,
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
      this.bank_accounts = payload.bank_accounts !== undefined ? payload.bank_accounts : this.bank_accounts;

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
    /**
     * Exclusividad Izipay/Culqi solo en estado local; no persiste hasta Guardar.
     */
    onExclusiveGatewayToggle(gateway, newValue) {
      if (!this.hasExclusiveGatewayConflict(gateway, newValue)) {
        return;
      }

      if (gateway === 'izipay') {
        this.form.enable_culqi = 0;
      } else if (gateway === 'culqi') {
        this.form.enable_izipay = 0;
      }

      this.$message({
        message: 'Se deshabilitó la pasarela en conflicto automáticamente.',
        type: 'warning',
        duration: 3000,
      });
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
    async submit() {
      if (this.loading_submit) return;

      this.loading_submit = true;
      try {
        const response = await this.$http.post(`/${this.resource}/configuration_culqui`, this.form);

        if (response.data.success) {
          this.applyServerState({
            ...response.data,
            bank_accounts: this.bank_accounts,
          });
          this.$message.success('Configuración guardada correctamente');
        } else {
          this.$message.error(response.data.message || 'No se pudo guardar la configuración');
        }
      } catch (error) {
        if (this.isExclusiveGatewayError(error)) {
          this.showExclusiveGatewayToast();
        } else {
          this.$message.error(this.getErrorMessage(error));
        }
      } finally {
        this.loading_submit = false;
      }
    },
  }
};
</script>
