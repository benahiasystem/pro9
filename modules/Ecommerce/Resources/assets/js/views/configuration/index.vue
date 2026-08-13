<template>
  <div class="col-12 pt-2 pt-md-0">
    <el-tabs type="border-card" tab-position="left" class="el-tab-ecommerce-config">
      <el-tab-pane label="Información">
        <div>
          <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
              <div class="row">
                <div class="col-12 mb-3">
                  <h4 class="mb-0"><strong>Información de Contacto</strong></h4>
                </div>
                <div class="col-md-6">
                  <div class="form-group" :class="{'has-danger': errors.information_contact_email}">
                    <label class="control-label">Email</label>
                    <el-input v-model="form.information_contact_email"></el-input>
                    <small
                      class="form-control-feedback"
                      v-if="errors.information_contact_email"
                      v-text="errors.information_contact_email[0]"
                    ></small>
                  </div>
                </div>
                <!-- <div class="col-md-6">
                  <div class="form-group" :class="{'has-danger': errors.information_contact_name}">
                    <label class="control-label">
                      Nombre
                      <span class="text-danger">*</span>
                    </label>
                    <el-input v-model="form.information_contact_name"></el-input>
                    <small
                      class="form-control-feedback"
                      v-if="errors.information_contact_name"
                      v-text="errors.information_contact_name[0]"
                    ></small>
                  </div>
                </div> -->
                <div class="col-md-6">
                  <div class="form-group" :class="{'has-danger': errors.information_contact_phone}">
                    <label class="control-label">
                      Teléfono
                      <span class="text-danger">*</span>
                    </label>
                    <el-input v-model="form.information_contact_phone"></el-input>
                    <small
                      class="form-control-feedback"
                      v-if="errors.information_contact_phone"
                      v-text="errors.information_contact_phone[0]"
                    ></small>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group" :class="{'has-danger': errors.information_contact_address}">
                    <label class="control-label">
                      Horario de atención
                    </label>
                    <el-input v-model="form.information_contact_address"></el-input>
                    <small
                      class="form-control-feedback"
                      v-if="errors.information_contact_address"
                      v-text="errors.information_contact_address[0]"
                    ></small>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group" :class="{'has-danger': errors.phone_whatsapp}">
                    <label class="control-label">
                      Whatsapp
                    </label>
                    <el-input v-model="form.phone_whatsapp"></el-input>
                    <small
                      class="form-control-feedback"
                      v-if="errors.phone_whatsapp"
                      v-text="errors.phone_whatsapp[0]"
                    ></small>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group editor-resizable">
                      <label class="control-label">Términos y Condiciones</label>
                      <vue-ckeditor
                        :editors="editors"
                        type="classic"
                        :config="editorConfig"
                        v-model="form.terms_conditions"
                      />
                      <div class="resize-handle" @mousedown="startResize"></div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group editor-resizable">
                      <label class="control-label">Política de Privacidad</label>
                      <vue-ckeditor
                        :editors="editors"
                        type="classic"
                        :config="editorConfig"
                        v-model="form.privacy_policy"
                      />
                      <div class="resize-handle" @mousedown="startResize"></div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group editor-resizable">
                      <label class="control-label">Sobre Nosotros</label>
                      <vue-ckeditor
                        :editors="editors"
                        type="classic"
                        :config="editorConfig"
                        v-model="form.about_us"
                      />
                      <div class="resize-handle" @mousedown="startResize"></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="form-actions text-end float-end pt-2">
              <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
            </div>
          </form>
        </div>
      </el-tab-pane>
      <el-tab-pane label="Apariencia">
        <form autocomplete="off" @submit.prevent="submitColor">
          <div class="form-body">
            <div class="row">
              <div class="col-12 mb-3">
                <h4 class="mb-0"><strong>Apariencia de la Tienda</strong></h4>
              </div>
              <div class="col-md-6">
                  <div class="form-group form-modern mb-3">
                    <el-switch v-model="form.full_width_banner" :active-value="1" :inactive-value="0"></el-switch>
                    <label class="ms-2 mb-0">Activar ancho completo del banner</label>
                    <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">Las imágenes del carrusel ocuparán el 100% del ancho de la pantalla.
                      Aseguresé que sus imágenes tenga la proporción 5:2
                    </small>
                  </div>
                <div class="form-group form-modern mb-3">
                  <el-switch v-model="form.show_description" :active-value="1" :inactive-value="0"></el-switch>
                  <label class="ms-2 mb-0">Mostrar descripción del producto</label>
                  <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">Muestra el nombre adicional o descripción corta debajo del título del producto</small>
                </div>
                <div class="form-group form-modern mb-3">
                  <el-switch v-model="form.show_stock" :active-value="1" :inactive-value="0"></el-switch>
                  <label class="ms-2 mb-0">Mostrar stock disponible</label>
                  <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">Muestra la cantidad disponible en inventario de cada producto</small>
                </div>
                <div class="form-group form-modern mb-3">
                  <el-switch v-model="form.only_available_products" :active-value="1" :inactive-value="0"></el-switch>
                  <label class="ms-2 mb-0">Ocultar productos sin stock</label>
                  <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">Los productos agotados no aparecerán en el catálogo de la tienda</small>
                </div>
                <div class="form-group form-modern mb-3">
                  <label class="control-label">Productos por página</label>
                  <small class="d-block text-muted mb-2" style="line-height: 1.5;">
                    Cantidad de productos que se mostrarán en cada página de la lista.
                  </small>
                  <el-radio-group class="btn-pagination" v-model="form.products_per_page">
                    <el-radio-button
                      v-for="option in products_per_page_options"
                      :key="option"
                      :label="option"
                    >{{ option }}</el-radio-button>
                  </el-radio-group>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group form-modern">
                  <label>Color Principal de la Tienda</label>
                  <div class="d-flex align-items-center mt-1">
                    <input
                      type="color"
                      v-model="form.color_ecommerce"
                      class="form-control form-control-color"
                      style="width: 80px; height: 40px; padding: 2px; cursor: pointer; border: 1px solid #dcdfe6;"
                    >
                    <span class="ms-2 text-muted" style="font-family: monospace;">{{ form.color_ecommerce }}</span>
                  </div>
                </div>
                <div class="form-group form-modern mb-3">
                  <label>Tema del encabezado</label>
                  <!-- <div class="mt-1">
                    <el-radio-group v-model="form.header_theme">
                      <el-radio-button label="light">Claro</el-radio-button>
                      <el-radio-button label="dark">Oscuro</el-radio-button>
                    </el-radio-group>
                  </div> -->
                  <div class="theme-header">
                    <button type="button" class="theme-light" :class="{ active: form.header_theme === 'light' }" @click="form.header_theme = 'light'">
                      <div class="hdr">
                        <div class="d-flex align-items-center">
                          <span class="logo"></span>
                          <span class="name ms-2">Mi Tienda</span>
                        </div>
                        <span v-if="form.header_theme === 'light'" class="check-select">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 12l5 5l10 -10" /></svg>
                        </span>
                        <span v-else class="cart-light">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M15 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17h-11v-14h-2" /><path d="M6 5l14 1l-1 7h-13" /></svg>
                        </span>
                      </div>
                      <div class="body-strip">
                        <span></span>
                        <span></span>
                        <span></span>
                      </div>
                      <div class="cap text-start ps-2">Claro</div>
                    </button>
                  
                    <button type="button" class="theme-dark" :class="{ active: form.header_theme === 'dark' }" @click="form.header_theme = 'dark'">
                      <div class="hdr" :style="{ backgroundColor: form.color_ecommerce }">
                        <div class="d-flex align-items-center">
                          <span class="logo"></span>
                          <span class="name ms-2">Mi Tienda</span>
                        </div>
                        <span v-if="form.header_theme === 'dark'" class="check-select">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 12l5 5l10 -10" /></svg>
                        </span>
                        <span v-else class="cart-dark">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-shopping-cart"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M15 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 17h-11v-14h-2" /><path d="M6 5l14 1l-1 7h-13" /></svg>
                        </span>
                      </div>
                      <div class="body-strip">
                        <span></span>
                        <span></span>
                        <span></span>
                      </div>
                      <div class="cap text-start ps-2">Oscuro</div>
                    </button>
                  </div>
                  <small class="d-block text-muted mt-1" style="line-height: 1.5;">
                    El tema <strong>Claro</strong> muestra el encabezado en blanco. El tema <strong>Oscuro</strong> usa el color principal de la tienda como fondo del encabezado.
                  </small>
                </div>
                <div class="form-group">
                  <label>Logo</label>
                  <div class="d-flex align-items-start gap-3">
                    <div>
                      <p class="mb-3 text-muted" style="font-size: 0.875rem;">
                        Para subir o cambiar el logo, ve a
                        <strong>Configuración y más &rsaquo; Configuraciones globales &rsaquo; Empresa</strong>.
                        Desde allí podrás cargar el logo en modo claro y modo oscuro.
                      </p>
                      <a href="/companies/create" class="el-button--primary btn btn-sm w-100">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-external-link"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" /><path d="M11 13l9 -9" /><path d="M15 4h5v5" /></svg>
                        Ir a configuración de empresa
                      </a>
                    </div>
                  </div>
                </div>
              </div>


            </div>
          </div>
          <div class="form-actions text-end float-end pt-2">
            <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
          </div>
        </form>
      </el-tab-pane>
      <el-tab-pane label="Banners">
        <div class="pt-2">
          <!-- Banners / spots de la tienda (antes vista independiente de promociones) -->
          <banner-settings-manager></banner-settings-manager>
        </div>
      </el-tab-pane>
      <el-tab-pane label="Social Proof">
        <div class="d-flex align-items-center justify-content-between mb-4 mt-2">
          <div>
            <h4 class="mb-0"><strong>Campaña de Social Proof</strong></h4>
            <small class="text-muted">Una sola campaña global: afecta a todos los productos de la tienda.</small>
          </div>
          <el-button
            v-if="campaigns.length === 0"
            type="primary"
            icon="el-icon-plus"
            @click.prevent="openCampaignDialog(null)"
          >Crear Campaña</el-button>
        </div>

        <el-table :data="campaigns" border stripe v-loading="campaigns_loading" style="width: 100%">
          <el-table-column prop="title" label="Título" min-width="150"></el-table-column>
          <el-table-column label="Descuento" width="160">
            <template slot-scope="scope">
              <span v-if="scope.row.sp_discount_price">
                <el-tag type="danger" size="mini">{{ scope.row.discount_type === 'percentage' ? scope.row.discount_value + '%' : 'S/. ' + scope.row.discount_value }}</el-tag>
              </span>
              <span v-else class="text-muted">—</span>
            </template>
          </el-table-column>
          <el-table-column label="Cronómetro" width="120">
            <template slot-scope="scope">
              <el-tag type="warning" size="mini" v-if="scope.row.sp_countdown">Activo</el-tag>
              <span v-else class="text-muted">—</span>
            </template>
          </el-table-column>
          <el-table-column label="Alcance" min-width="160">
            <template>
              <el-tag type="success" size="mini">Todos los productos</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="Estado" width="100">
            <template slot-scope="scope">
              <el-switch :value="!!scope.row.status" @change="toggleCampaignStatus(scope.row)"></el-switch>
            </template>
          </el-table-column>
          <el-table-column label="Acciones" width="130" align="center">
            <template slot-scope="scope">
              <el-button size="mini" icon="el-icon-edit" @click.prevent="openCampaignDialog(scope.row)">Editar</el-button>
              <el-button size="mini" type="danger" icon="el-icon-delete" @click.prevent="deleteCampaign(scope.row.id)"></el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="mt-5 pt-3 border-top">
          <h5 class="mb-2"><strong>Sellos de autoridad (Trust Badges)</strong></h5>
          <small class="text-muted d-block mb-3">Se muestran en la ficha del producto y en el checkout.</small>
          <div class="form-group mb-3">
            <el-switch v-model="trustBadgesEnabled" active-text="Mostrar sellos de confianza"></el-switch>
          </div>
          <div v-for="(badge, idx) in trustBadges" :key="'tb-'+idx" class="row mb-2 align-items-center">
            <div class="col-md-3">
              <el-select v-model="badge.icon" class="w-100" placeholder="Ícono">
                <el-option label="Escudo" value="shield"></el-option>
                <el-option label="Devolución" value="refresh"></el-option>
                <el-option label="Envío" value="truck"></el-option>
                <el-option label="Candado" value="lock"></el-option>
                <el-option label="Check" value="check"></el-option>
              </el-select>
            </div>
            <div class="col-md-7">
              <el-input v-model="badge.text" maxlength="60" placeholder="Texto del sello"></el-input>
            </div>
            <div class="col-md-2">
              <el-button type="danger" icon="el-icon-delete" circle @click.prevent="trustBadges.splice(idx, 1)"></el-button>
            </div>
          </div>
          <div class="d-flex gap-2 mt-2">
            <el-button size="mini" icon="el-icon-plus" @click.prevent="trustBadges.push({ icon: 'shield', text: '' })">Agregar sello</el-button>
            <el-button type="primary" size="mini" :loading="trust_badges_saving" @click.prevent="saveTrustBadges">Guardar sellos</el-button>
          </div>
        </div>

        <el-dialog :title="campaignForm.id ? 'Editar Campaña' : 'Nueva Campaña'" :visible.sync="campaignDialogVisible" width="780px" @close="resetCampaignForm">
          <div class="row">
            <div class="col-md-12 form-group">
              <label><strong>Nombre de la Campaña</strong></label>
              <el-input v-model="campaignForm.title" placeholder="Ej: Cyber Wow, Liquidación de Stock..."></el-input>
            </div>

            <div class="col-12 mt-3 mb-2"><h6 class="text-muted"><strong>Módulos de Social Proof a Activar</strong></h6></div>
            <div class="col-md-4 form-group"><el-switch v-model="campaignForm.sp_countdown" active-text="Cuenta Regresiva"></el-switch></div>
            <div class="col-md-4 form-group"><el-switch v-model="campaignForm.sp_discount_price" active-text="Precio Tachado"></el-switch></div>
            <div class="col-md-4 form-group"><el-switch v-model="campaignForm.sp_purchase_count" active-text="Ventas Simuladas"></el-switch></div>
            <div class="col-md-4 form-group mt-2"><el-switch v-model="campaignForm.sp_views_count" active-text="Personas Viendo"></el-switch></div>
            <div class="col-md-4 form-group mt-2"><el-switch v-model="campaignForm.sp_stock_alert" active-text="Alerta de Stock"></el-switch></div>
            <div class="col-md-4 form-group mt-2"><el-switch v-model="campaignForm.sp_rating" active-text="Estrellas Rating"></el-switch></div>

            <template v-if="campaignForm.sp_discount_price || campaignForm.sp_countdown">
              <div class="col-12 mt-3 mb-1">
                <h6 class="text-muted"><strong>Configuración de Precio Tachado</strong></h6>
                <small class="text-muted d-block mb-2">
                  El precio del producto en catálogo es el que se cobra. Este valor solo arma el “precio anterior” tachado
                  (ej.: producto a S/ 100 con 50% → muestra S/ 150 tachado y S/ 100 de oferta).
                </small>
              </div>
              <div class="col-md-4 form-group">
                <label>Tipo</label>
                <el-select v-model="campaignForm.discount_type" class="w-100">
                  <el-option label="Porcentaje (%)" value="percentage"></el-option>
                  <el-option label="Monto Fijo (S/.)" value="fixed"></el-option>
                </el-select>
              </div>
              <div class="col-md-4 form-group">
                <label>Valor a sumar al precio (tachado)</label>
                <el-input-number v-model="campaignForm.discount_value" :precision="2" :step="1" :min="0" class="w-100"></el-input-number>
                <small class="text-muted">% o monto que se agrega sobre el precio de catálogo para el tachado.</small>
              </div>
              <div class="col-md-4 form-group" v-if="campaignForm.sp_countdown">
                <label>Fin de la Campaña (Cronómetro)</label>
                <el-date-picker
                  v-model="campaignForm.end_date"
                  type="datetime"
                  placeholder="Fecha y hora límite"
                  value-format="yyyy-MM-dd HH:mm:ss"
                  format="dd/MM/yyyy HH:mm"
                  default-time="23:59:00"
                  class="w-100"
                ></el-date-picker>
                <small class="text-muted">Al llegar a esta hora, la campaña suma +1 día sola (misma hora) y el contador sigue.</small>
              </div>
            </template>

            <template v-if="campaignForm.sp_stock_alert">
              <div class="col-12 mt-3 mb-1"><h6 class="text-muted"><strong>Alerta de Stock Bajo</strong></h6></div>
              <div class="col-md-4 form-group">
                <label>Umbral (unidades)</label>
                <el-input-number v-model="campaignForm.sp_stock_threshold" :step="1" :min="1" class="w-100"></el-input-number>
              </div>
            </template>

            <template v-if="campaignForm.sp_views_count">
              <div class="col-12 mt-3 mb-1"><h6 class="text-muted"><strong>Personas Viendo (Simulado)</strong></h6></div>
              <div class="col-md-6 form-group">
                <label>Mínimo</label>
                <el-input-number v-model="campaignForm.sp_views_min" :step="1" :min="1" class="w-100"></el-input-number>
              </div>
              <div class="col-md-6 form-group">
                <label>Máximo</label>
                <el-input-number v-model="campaignForm.sp_views_max" :step="1" :min="2" class="w-100"></el-input-number>
              </div>
            </template>

            <template v-if="campaignForm.sp_purchase_count">
              <div class="col-12 mt-3 mb-1"><h6 class="text-muted"><strong>Ventas Simuladas (últimos 7 días)</strong></h6></div>
              <div class="col-md-6 form-group">
                <label>Mínimo</label>
                <el-input-number v-model="campaignForm.sp_purchase_min" :step="1" :min="1" class="w-100"></el-input-number>
              </div>
              <div class="col-md-6 form-group">
                <label>Máximo</label>
                <el-input-number v-model="campaignForm.sp_purchase_max" :step="1" :min="2" class="w-100"></el-input-number>
              </div>
            </template>

            <div class="col-md-4 form-group mt-3">
              <el-switch v-model="campaignForm.status" active-text="Campaña Activa" inactive-text="Pausada"></el-switch>
            </div>
            <div class="col-12">
              <small class="text-muted">Esta campaña es global: se aplica a todos los productos de la tienda.</small>
            </div>
          </div>

          <span slot="footer">
            <el-button @click="campaignDialogVisible = false">Cancelar</el-button>
            <el-button type="primary" :loading="campaigns_loading" @click.prevent="saveCampaign">Guardar Campaña</el-button>
          </span>
        </el-dialog>
      </el-tab-pane>
      <el-tab-pane label="Enlaces">
        <ConfigurationLinks />
      </el-tab-pane>
      <el-tab-pane label="Pasarelas de pago">
        <div>
          <div class="mb-3">
            <h4 class="mb-0"><strong>Pasarelas de Pago</strong></h4>
          </div>
          <PaymentGateways />
        </div>
      </el-tab-pane>
      <el-tab-pane label="Cupones de descuento">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h4 class="mb-0"><strong>Cupones de Descuento</strong></h4>
            <button class="btn btn-custom btn-sm" type="button" @click.prevent="$refs.digitalCoupon.clickNew()">
              <i class="fa fa-plus-circle"></i> Nuevo Cupón
            </button>
          </div>
          <DigitalCoupon ref="digitalCoupon" />
        </div>
      </el-tab-pane>
      <el-tab-pane label="Zonas de delivery">
        <div>
          <div class="mb-3">
            <h4 class="mb-2 d-flex align-items-center justify-content-between">
              <strong>Zonas de Delivery</strong>
              <el-button type="primary" plain class="btn btn-sm" @click.prevent="$refs.deliveryZones.clickNew()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                Nuevo
              </el-button>
            </h4>
          </div>
          <DeliveryZones ref="deliveryZones" />
          <div class="mb-3">
            <h4 class="mb-2"><strong>Sucursales de Recojo</strong></h4>
          </div>
          <div class="form-group form-modern mb-3">
            <el-switch v-model="form.enable_store_pickup" :active-value="1" :inactive-value="0" @change="submit"></el-switch>
            <label class="ms-2 mb-0">Habilitar recojo en tienda</label>
            <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">
              Permite a los clientes elegir recoger su pedido en una sucursal en lugar de recibirlo por delivery.
            </small>
          </div>
          <!-- Sucursales de recojo: solo visible cuando el recojo en tienda está activo -->
          <div v-if="form.enable_store_pickup == 1" class="mt-4">
            <PickupBranches />
          </div>
        </div>
      </el-tab-pane>
      <el-tab-pane label="Otras configuraciones">
        <form autocomplete="off" @submit.prevent="submit">
          <div class="form-body">
            <div class="row">
              <div class="col-12 mb-3">
                <h4 class="mb-0"><strong>Otras Configuraciones</strong></h4>
              </div>
              <div class="col-md-6">
                <div class="form-group form-modern mb-3">
                  <el-switch v-model="form.enable_electronic_documents" :active-value="1" :inactive-value="0"></el-switch>
                  <label class="ms-2 mb-0">Habilitar documentos electrónicos</label>
                  <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">
                    Cuando está desactivado, se genera automáticamente una nota de venta.
                  </small>
                </div>
              </div>

              <div class="col-12 mt-4 mb-3">
                <h4 class="mb-0"><strong>Configuración de Cotizaciones</strong></h4>
                <small class="d-block text-muted mt-1" style="line-height: 1.5;">
                  Define cómo funciona el cotizador en la tienda virtual.
                </small>
              </div>

              <div class="col-12">
                <div class="row align-items-start">
                  <div class="col-md-6">
                    <div class="form-group form-modern mb-3">
                      <el-switch v-model="form.quotation_enabled" :active-value="1" :inactive-value="0"></el-switch>
                      <label class="ms-2 mb-0">Activar cotizaciones</label>
                      <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">
                        Habilita o deshabilita la función de cotizaciones en toda la tienda.
                      </small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group form-modern mb-3" :class="{'has-danger': errors.quotation_success_message}">
                      <label class="control-label">Mensaje de respuesta automático</label>
                      <small class="d-block text-muted mb-2" style="line-height: 1.5;">
                        Texto que verá el cliente después de enviar su solicitud de cotización.
                      </small>
                      <el-input
                        type="textarea"
                        :rows="3"
                        v-model="form.quotation_success_message"
                        :disabled="form.quotation_enabled != 1"
                        placeholder="Ej: Gracias por tu solicitud. Nos pondremos en contacto contigo a la brevedad."
                        maxlength="2000"
                        show-word-limit
                      ></el-input>
                      <small
                        class="form-control-feedback"
                        v-if="errors.quotation_success_message"
                        v-text="errors.quotation_success_message[0]"
                      ></small>
                    </div>
                  </div>
                </div>

                <div class="row align-items-start">
                  <div class="col-md-6">
                    <div class="form-group form-modern mb-3">
                      <label class="control-label">Modo de operación</label>
                      <small class="d-block text-muted mb-2" style="line-height: 1.5;">
                        Elige si el cliente solo puede cotizar o también puede comprar.
                      </small>
                      <el-radio-group
                        v-model="form.quotation_mode"
                        :disabled="form.quotation_enabled != 1"
                        size="small"
                        @change="onQuotationModeChange"
                      >
                        <el-radio-button label="quote_and_sell">Cotizar y vender</el-radio-button>
                        <el-radio-button label="quote_only">Solo cotizar</el-radio-button>
                      </el-radio-group>
                      <small class="d-block text-muted mt-2" style="line-height: 1.5;">
                        Valor actual:
                        <strong v-if="form.quotation_mode === 'quote_and_sell'">Cotizar y vender</strong>
                        <strong v-else-if="form.quotation_mode === 'quote_only'">Solo cotizar</strong>
                        <strong v-else>No definido</strong>
                      </small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group form-modern mb-3" :class="{'has-danger': errors.quotation_validity_days}">
                      <label class="control-label">Vigencia de la cotización (días)</label>
                      <small class="d-block text-muted mb-2" style="line-height: 1.5;">
                        Se asigna automáticamente. El cliente no puede modificarla.
                      </small>
                      <el-input-number
                        v-model="form.quotation_validity_days"
                        :min="1"
                        :max="90"
                        :disabled="form.quotation_enabled != 1"
                        controls-position="right"
                      ></el-input-number>
                      <small
                        class="form-control-feedback d-block"
                        v-if="errors.quotation_validity_days"
                        v-text="errors.quotation_validity_days[0]"
                      ></small>
                    </div>
                  </div>
                </div>

                <div class="row align-items-start">
                  <div class="col-md-6">
                    <div class="form-group form-modern mb-3">
                      <el-switch
                        v-model="form.quotation_show_prices"
                        :active-value="1"
                        :inactive-value="0"
                        :disabled="form.quotation_enabled != 1"
                        @change="onQuotationShowPricesChange"
                      ></el-switch>
                      <label class="ms-2 mb-0">Mostrar precios en el cotizador</label>
                      <small class="d-block text-muted ms-5" style="padding: 0 !important; line-height: 1.5;">
                        Solo se puede ocultar precios en «Solo cotizar». En «Cotizar y vender» los precios siempre deben verse porque el cliente puede comprar.
                      </small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group form-modern mb-3" :class="{'has-danger': errors.quotation_terms}">
                      <label class="control-label">Condiciones comerciales</label>
                      <small class="d-block text-muted mb-2" style="line-height: 1.5;">
                        Texto visible para el cliente al solicitar la cotización (además de la vigencia).
                      </small>
                      <el-input
                        type="textarea"
                        :rows="3"
                        v-model="form.quotation_terms"
                        :disabled="form.quotation_enabled != 1"
                        placeholder="Ej: Precios sujetos a stock. La cotización no reserva inventario."
                        maxlength="5000"
                        show-word-limit
                      ></el-input>
                      <small
                        class="form-control-feedback"
                        v-if="errors.quotation_terms"
                        v-text="errors.quotation_terms[0]"
                      ></small>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>
          <div class="form-actions text-end float-end pt-2">
            <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
          </div>
        </form>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>
<style>
.editor-resizable {
    position: relative;
}
.resize-handle {
    height: 10px;
    cursor: ns-resize;
    background: #eee;
}
.editor-resizable .ck-editor__editable {
    min-height: 150px;
    height: 200px;
}
</style>
<script>
import ConfigurationLinks from '../configuration_links/index.vue';
import PaymentGateways from '../payment_gateways/index.vue';
import DeliveryZones from '../configuration_delivery_zones/index.vue';
import 'ckeditor5/ckeditor5.css';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
import DigitalCoupon from '../configuration_digital_coupon/index.vue';
import PickupBranches from '../configuration_pickup_branches/index.vue';
import BannerSettingsManager from '@views/configurations/BannerSettingsManager.vue';
import CKEditor from 'vue-ckeditor5';
export default {
  components: {
    ConfigurationLinks,
    PaymentGateways,
    DigitalCoupon,
    DeliveryZones,
    PickupBranches,
    BannerSettingsManager,
    'vue-ckeditor': CKEditor.component
  },
  data() {
    return {
      loading_submit: false,
      resource: "ecommerce",
      errors: {},
      form: {},
      products: [],
      campaigns: [],
      campaigns_loading: false,
      campaignDialogVisible: false,
      campaignForm: {
        id: null, title: '', discount_type: 'percentage', discount_value: 0,
        start_date: null, end_date: null, sp_product_ids: [], status: true,
        sp_countdown: false, sp_discount_price: false, sp_purchase_count: false,
        sp_views_count: false, sp_stock_alert: false, sp_rating: false,
        sp_stock_threshold: 10,
        sp_views_min: 10, sp_views_max: 50, sp_purchase_min: 5, sp_purchase_max: 30
      },
      trustBadgesEnabled: true,
      trustBadges: [
        { icon: 'shield', text: 'Pago 100% Seguro' },
        { icon: 'refresh', text: 'Devolución Garantizada' },
        { icon: 'truck', text: 'Envío Rápido' },
      ],
      trust_badges_saving: false,
      soap_sends: [],
      soap_types: [],
      products_per_page_options: [8, 12, 16, 24, 32, 40],
      editors: {
          classic: ClassicEditor
      },
      editorConfig: {
          licenseKey: 'GPL',
          toolbar: [
              'heading',
              '|',
              'bold', 'italic', 'link',
              'bulletedList', 'numberedList',
              '|',
              'blockQuote',
              'undo', 'redo',
              '|',
              'sourceEditing'
          ]
      },
      isResizing: false,
    };
  },
  async created() {
    await this.$http.get(`/${this.resource}/configuration/products`).then(response => {
      this.products = response.data.products || [];
    }).catch(() => { this.products = []; });

    await this.loadCampaigns();

    await this.$http.get(`/${this.resource}/record`).then(response => {
      if (response.data !== "") {
        let data = response.data.data;

        // Cargar preferencias si existen
        let preferences = { show_description: 1, show_stock: 0, only_available_products: 0, full_width_banner: 0 };
        if (data.preferences) {
          const prefs = typeof data.preferences === 'string'
            ? JSON.parse(data.preferences)
            : data.preferences;
          preferences = prefs;
        }

        // Inicializar form con todos los datos de una vez
        this.form = {
          // campos originales
          id: data.id,
          information_contact_email: data.information_contact_email || "",
          information_contact_name: data.information_contact_name || null,
          information_contact_phone: data.information_contact_phone || null,
          information_contact_address: data.information_contact_address || null,
          phone_whatsapp: data.phone_whatsapp || null,
          // campos de color y preferencias
          color_ecommerce: data.color_ecommerce,
          show_description: parseInt(preferences.show_description) || 0,
          show_stock: parseInt(preferences.show_stock) || 0,
          only_available_products: parseInt(preferences.only_available_products) || 0,
          full_width_banner: parseInt(preferences.full_width_banner) || 0,
          header_theme: preferences.header_theme || 'light',
          products_per_page: parseInt(preferences.products_per_page) || 16,
          // campos de páginas personalizadas
          terms_conditions: data.terms_conditions || '',
          privacy_policy: data.privacy_policy || '',
          about_us: data.about_us || '',
          // configuración de documentos electrónicos y recojo en tienda
          enable_electronic_documents: data.enable_electronic_documents ? 1 : 0,
          enable_store_pickup: data.enable_store_pickup ? 1 : 0,
          // configuración de cotizaciones
          quotation_enabled: data.quotation_enabled ? 1 : 0,
          quotation_mode: data.quotation_mode || 'quote_and_sell',
          quotation_show_prices: (data.quotation_mode || 'quote_and_sell') === 'quote_and_sell'
            ? 1
            : ((data.quotation_show_prices === true || data.quotation_show_prices === 1 || data.quotation_show_prices === '1') ? 1 : 0),
          quotation_success_message: data.quotation_success_message || '',
          quotation_validity_days: parseInt(data.quotation_validity_days) || 7,
          quotation_terms: data.quotation_terms || '',
        };

        this.trustBadgesEnabled = preferences.trust_badges_enabled !== 0 && preferences.trust_badges_enabled !== false;
        if (Array.isArray(preferences.trust_badges) && preferences.trust_badges.length) {
          this.trustBadges = preferences.trust_badges.map(b => ({
            icon: b.icon || 'shield',
            text: b.text || '',
          }));
        }
      } else {
        this.initForm();
      }
    });
  },
  methods: {
    getProductName(id) {
      const found = this.products.find(p => p.id == id);
      return found ? found.description : ('#' + id);
    },
    defaultCampaignForm() {
      return {
        id: null, title: '', discount_type: 'percentage', discount_value: 0,
        start_date: null, end_date: null, sp_product_ids: [], status: true,
        sp_countdown: false, sp_discount_price: false, sp_purchase_count: false,
        sp_views_count: false, sp_stock_alert: false, sp_rating: false,
        sp_stock_threshold: 10,
        sp_views_min: 10, sp_views_max: 50, sp_purchase_min: 5, sp_purchase_max: 30
      };
    },
    loadCampaigns() {
      this.campaigns_loading = true;
      return this.$http.get(`/${this.resource}/campaigns/records`)
        .then(response => { this.campaigns = response.data.records || []; })
        .catch(() => { this.campaigns = []; })
        .finally(() => { this.campaigns_loading = false; });
    },
    formatCampaignDate(value) {
      if (!value) return null;
      // Ya viene como yyyy-MM-dd HH:mm:ss desde la API (hora local).
      if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value)) {
        return value;
      }
      const d = new Date(value);
      if (Number.isNaN(d.getTime())) return null;
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    },
    openCampaignDialog(campaign) {
      if (!campaign && this.campaigns.length > 0) {
        this.$message.warning('Solo puedes tener una campaña. Edita la existente.');
        return;
      }
      if (campaign) {
        this.campaignForm = {
          id: campaign.id,
          title: campaign.title,
          discount_type: campaign.discount_type || 'percentage',
          discount_value: parseFloat(campaign.discount_value) || 0,
          start_date: this.formatCampaignDate(campaign.start_date),
          end_date: this.formatCampaignDate(campaign.end_date),
          sp_product_ids: [],
          status: !!campaign.status,
          sp_countdown: !!campaign.sp_countdown,
          sp_discount_price: !!campaign.sp_discount_price,
          sp_purchase_count: !!campaign.sp_purchase_count,
          sp_views_count: !!campaign.sp_views_count,
          sp_stock_alert: !!campaign.sp_stock_alert,
          sp_rating: !!campaign.sp_rating,
          sp_stock_threshold: parseInt(campaign.sp_stock_threshold, 10) || 10,
          sp_views_min: parseInt(campaign.sp_views_min) || 10,
          sp_views_max: parseInt(campaign.sp_views_max) || 50,
          sp_purchase_min: parseInt(campaign.sp_purchase_min) || 5,
          sp_purchase_max: parseInt(campaign.sp_purchase_max) || 30,
        };
      } else {
        this.resetCampaignForm();
      }
      this.campaignDialogVisible = true;
    },
    resetCampaignForm() {
      this.campaignForm = this.defaultCampaignForm();
    },
    saveCampaign() {
      if (!this.campaignForm.title) {
        this.$message.warning('Ingresa un nombre para la campaña');
        return;
      }
      this.campaigns_loading = true;
      this.$http.post(`/${this.resource}/campaigns`, this.campaignForm)
        .then(response => {
          if (response.data.success) {
            this.$message.success(response.data.message);
            this.campaignDialogVisible = false;
            this.loadCampaigns();
          } else {
            this.$message.error(response.data.message || 'No se pudo guardar');
          }
        })
        .catch(() => this.$message.error('Error al guardar la campaña'))
        .finally(() => { this.campaigns_loading = false; });
    },
    deleteCampaign(id) {
      this.$confirm('¿Eliminar esta campaña?', 'Confirmar', { type: 'warning' })
        .then(() => this.$http.delete(`/${this.resource}/campaigns/${id}`))
        .then(response => {
          if (response && response.data && response.data.success) {
            this.$message.success(response.data.message);
            this.loadCampaigns();
          }
        })
        .catch(() => {});
    },
    toggleCampaignStatus(campaign) {
      this.$http.get(`/${this.resource}/campaigns/${campaign.id}/status`)
        .then(response => {
          if (response.data.success) {
            campaign.status = !campaign.status;
            this.$message.success(response.data.message);
          }
        })
        .catch(() => this.$message.error('No se pudo actualizar el estado'));
    },
    saveTrustBadges() {
      this.trust_badges_saving = true;
      const payload = {
        id: this.form.id,
        preferences: {
          show_description: this.form.show_description,
          show_stock: this.form.show_stock,
          only_available_products: this.form.only_available_products,
          full_width_banner: this.form.full_width_banner,
          header_theme: this.form.header_theme,
          products_per_page: this.form.products_per_page,
          trust_badges_enabled: this.trustBadgesEnabled ? 1 : 0,
          trust_badges: this.trustBadges.filter(b => (b.text || '').trim() !== ''),
        }
      };
      this.$http.post(`/${this.resource}/configuration_color`, payload)
        .then(response => {
          if (response.data.success) {
            this.$message.success('Sellos de confianza guardados');
          } else {
            this.$message.error(response.data.message || 'No se pudo guardar');
          }
        })
        .catch(() => this.$message.error('Error al guardar sellos'))
        .finally(() => { this.trust_badges_saving = false; });
    },
    onQuotationModeChange(mode) {
      if (mode === 'quote_and_sell') {
        this.form.quotation_show_prices = 1;
      }
    },
    onQuotationShowPricesChange(value) {
      if (this.form.quotation_mode === 'quote_and_sell' && value == 0) {
        this.$nextTick(() => {
          this.form.quotation_show_prices = 1;
        });
        this.$message.error('No es posible activar esta función en este modo');
      }
    },
    startResize(e) {
      this.isResizing = true

      const editor = e.target.previousElementSibling.querySelector('.ck-editor__editable')

      const onMouseMove = (event) => {
        if (!this.isResizing) return
        editor.style.height = event.clientY - editor.getBoundingClientRect().top + 'px'
      }

      const onMouseUp = () => {
        this.isResizing = false
        document.removeEventListener('mousemove', onMouseMove)
        document.removeEventListener('mouseup', onMouseUp)
      }

      document.addEventListener('mousemove', onMouseMove)
      document.addEventListener('mouseup', onMouseUp)
    },
    initForm() {
      this.errors = {};
      this.form = {
        id: null,
        information_contact_email: "",
        information_contact_name: null,
        information_contact_phone: null,
        information_contact_address: null,
        phone_whatsapp: null,
        color_ecommerce: null,
        show_description: 1,
        show_stock: 0,
        only_available_products: 0,
        full_width_banner: 0,
        header_theme: 'light',
        products_per_page: 16,
        terms_conditions: '',
        privacy_policy: '',
        about_us: '',
        enable_electronic_documents: 0,
        enable_store_pickup: 0,
        quotation_enabled: 0,
        quotation_mode: 'quote_and_sell',
        quotation_show_prices: 1,
        quotation_success_message: '',
        quotation_validity_days: 7,
        quotation_terms: '',
      };
    },
    submit() {
      this.loading_submit = true;
      // Copiar el form y empaquetar los switches en 'preferences'
      const payload = { ...this.form };
      payload.preferences = {
        show_description: this.form.show_description,
        show_stock: this.form.show_stock,
        only_available_products: this.form.only_available_products,
        full_width_banner: this.form.full_width_banner,
        header_theme: this.form.header_theme,
        products_per_page: this.form.products_per_page
      };
      // Eliminar los switches planos para evitar duplicidad
      delete payload.show_description;
      delete payload.show_stock;
      delete payload.only_available_products;
      delete payload.full_width_banner;

      // Normaliza modo y flag de precios (0/1) para persistencia fiable.
      payload.quotation_mode = this.form.quotation_mode === 'quote_only'
        ? 'quote_only'
        : 'quote_and_sell';
      payload.quotation_enabled = this.form.quotation_enabled == 1 ? 1 : 0;
      // En híbrido no se permite ocultar precios.
      if (payload.quotation_mode === 'quote_and_sell') {
        this.form.quotation_show_prices = 1;
        payload.quotation_show_prices = 1;
      } else {
        payload.quotation_show_prices = this.form.quotation_show_prices == 1 ? 1 : 0;
      }

      this.$http
        .post(`/${this.resource}/configuration`, payload)
        .then(response => {
          if (response.data.success) {
            if (response.data.quotation_mode) {
              this.form.quotation_mode = response.data.quotation_mode;
            }
            if (typeof response.data.quotation_show_prices !== 'undefined') {
              this.form.quotation_show_prices = response.data.quotation_show_prices ? 1 : 0;
            }
            this.$message.success(response.data.message);
          } else {
            this.$message.error(response.data.message);
          }
        })
        .catch(error => {
          if (error.response && error.response.status === 422) {
            this.errors = error.response.data.errors || error.response.data;
            this.$message.error('Revise los datos del formulario');
          } else {
            console.log(error);
            this.$message.error('No se pudo guardar la configuración');
          }
        })
        .then(() => {
          this.loading_submit = false;
        });
    },
    submitColor() {
      this.loading_submit = true;
      // La pestaña Apariencia guarda solo color + preferencias por su propia ruta,
      // sin pasar por la validación de datos de contacto de /ecommerce/configuration.
      const payload = {
        id: this.form.id,
        color_ecommerce: this.form.color_ecommerce,
        show_description: this.form.show_description,
        show_stock: this.form.show_stock,
        only_available_products: this.form.only_available_products,
        full_width_banner: this.form.full_width_banner,
        header_theme: this.form.header_theme,
        products_per_page: this.form.products_per_page
      };
      this.$http
        .post(`/${this.resource}/configuration_color`, payload)
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
    }
  }
};
</script>
