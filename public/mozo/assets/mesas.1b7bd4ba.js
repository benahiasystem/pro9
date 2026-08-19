var __defProp = Object.defineProperty;
var __defProps = Object.defineProperties;
var __getOwnPropDescs = Object.getOwnPropertyDescriptors;
var __getOwnPropSymbols = Object.getOwnPropertySymbols;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __propIsEnum = Object.prototype.propertyIsEnumerable;
var __defNormalProp = (obj, key, value) => key in obj ? __defProp(obj, key, { enumerable: true, configurable: true, writable: true, value }) : obj[key] = value;
var __spreadValues = (a, b) => {
  for (var prop in b || (b = {}))
    if (__hasOwnProp.call(b, prop))
      __defNormalProp(a, prop, b[prop]);
  if (__getOwnPropSymbols)
    for (var prop of __getOwnPropSymbols(b)) {
      if (__propIsEnum.call(b, prop))
        __defNormalProp(a, prop, b[prop]);
    }
  return a;
};
var __spreadProps = (a, b) => __defProps(a, __getOwnPropDescs(b));
import { _ as _sfc_main$9 } from "./LockedScreen.605d5b59.js";
import { _ as _sfc_main$4, a as _sfc_main$6, b as _sfc_main$8 } from "./MesaRestaurantPos.0998b49b.js";
import { a as _sfc_main$5 } from "./ProductModifiersDialog.5547ded0.js";
import { _ as _sfc_main$3, a as __unplugin_components_4 } from "./VButton.a329028a.js";
import { b as defineComponent, a as computed, r as ref, f as openBlock, g as createElementBlock, F as Fragment, A as renderList, X as createBaseVNode, z as toDisplayString, Y as normalizeClass, Z as unref, I as withModifiers, C as createCommentVNode, D as createTextVNode, v as createBlock, B as withCtx, w as createVNode, aa as normalizeStyle, ad as Teleport, G as pushScopeId, H as popScopeId, _ as createStaticVNode, N as Notyf, t as reactive, L as watch, o as onMounted, a6 as withDirectives, ar as vShow, a7 as vModelText, av as vModelSelect, e as useHead } from "./vendor.47bbcd9c.js";
import { u as useMesaSession, d as MESA_NO_DISPONIBLE, e as MESA_DISPONIBLE, h as MESA_SHAPES_CIRCLE, D as DEFAULT_ENVIRONMENT, b as MesaService } from "./masterService.4870541c.js";
import { _ as _export_sfc } from "./plugin-vue_export-helper.5a098b48.js";
import { _ as __unplugin_components_5 } from "./VPlaceloadWrap.7a2b9b98.js";
import { _ as __unplugin_components_1$1 } from "./VControl.4be8848d.js";
import { _ as _sfc_main$7 } from "./VField.d83ee54f.js";
import { a as useCompanySession, u as useUserSession } from "./index.02f4974c.js";
import { i as initRealtime } from "./realtime.4a9fec0b.js";
import { p as pageTitle } from "./sidebarLayoutState.ce1f1dd6.js";
import "./VModal.d10f8864.js";
import "./VIcon.51cd8055.js";
import "./CategoriesNavBar.a7a81745.js";
import "./VAvatar.b01c944b.js";
import "./VIconButton.c045eea9.js";
import "./VLoader.cc16436b.js";
var MesaEnvironment_vue_vue_type_style_index_0_scoped_true_lang = "";
var MesaEnvironment_vue_vue_type_style_index_1_lang = "";
var MesaEnvironment_vue_vue_type_style_index_2_scoped_true_lang = "";
const _withScopeId$1 = (n) => (pushScopeId("data-v-6a4ef99a"), n = n(), popScopeId(), n);
const _hoisted_1$2 = { class: "container" };
const _hoisted_2$1 = ["onClick"];
const _hoisted_3$1 = { class: "mesa-label" };
const _hoisted_4$1 = ["title", "disabled", "onClick"];
const _hoisted_5$1 = {
  key: 0,
  xmlns: "http://www.w3.org/2000/svg",
  width: "16",
  height: "16",
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": "2",
  "stroke-linecap": "round",
  "stroke-linejoin": "round",
  class: "icon-tabler icons-tabler-outline icon-tabler-check"
};
const _hoisted_6$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("path", {
  stroke: "none",
  d: "M0 0h24v24H0z",
  fill: "none"
}, null, -1));
const _hoisted_7$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("path", { d: "M5 12l5 5l10 -10" }, null, -1));
const _hoisted_8$1 = [
  _hoisted_6$1,
  _hoisted_7$1
];
const _hoisted_9$1 = {
  key: 1,
  xmlns: "http://www.w3.org/2000/svg",
  width: "16",
  height: "16",
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": "2",
  "stroke-linecap": "round",
  "stroke-linejoin": "round",
  class: "icon-tabler icons-tabler-outline icon-tabler-ban"
};
const _hoisted_10$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("path", {
  stroke: "none",
  d: "M0 0h24v24H0z",
  fill: "none"
}, null, -1));
const _hoisted_11$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("path", { d: "M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" }, null, -1));
const _hoisted_12$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("path", { d: "M5.7 5.7l12.6 12.6" }, null, -1));
const _hoisted_13$1 = [
  _hoisted_10$1,
  _hoisted_11$1,
  _hoisted_12$1
];
const _hoisted_14$1 = ["onClick"];
const _hoisted_15$1 = /* @__PURE__ */ createStaticVNode('<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-tabler icons-tabler-outline icon-tabler-arrows-left-right" data-v-6a4ef99a><path stroke="none" d="M0 0h24v24H0z" fill="none" data-v-6a4ef99a></path><path d="M21 17l-18 0" data-v-6a4ef99a></path><path d="M6 10l-3 -3l3 -3" data-v-6a4ef99a></path><path d="M3 7l18 0" data-v-6a4ef99a></path><path d="M18 20l3 -3l-3 -3" data-v-6a4ef99a></path></svg>', 1);
const _hoisted_16$1 = [
  _hoisted_15$1
];
const _hoisted_17$1 = ["onClick"];
const _hoisted_18$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("i", { class: "fa-solid fa-rotate-left" }, null, -1));
const _hoisted_19$1 = [
  _hoisted_18$1
];
const _hoisted_20$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("div", {
  class: "mesa-closing-spinner",
  "aria-hidden": "true"
}, null, -1));
const _hoisted_21$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("div", { class: "mesa-closing-text" }, "Cerrando mesa...", -1));
const _hoisted_22$1 = [
  _hoisted_20$1,
  _hoisted_21$1
];
const _hoisted_23$1 = { class: "item-left" };
const _hoisted_24$1 = { class: "item-details" };
const _hoisted_25$1 = { class: "item-customer" };
const _hoisted_26$1 = { class: "item-meta" };
const _hoisted_27$1 = {
  key: 0,
  class: "meta-info"
};
const _hoisted_28$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("i", { class: "fa-solid fa-phone" }, null, -1));
const _hoisted_29 = {
  key: 1,
  class: "meta-info"
};
const _hoisted_30 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("i", { class: "fa-solid fa-location-dot" }, null, -1));
const _hoisted_31 = {
  key: 2,
  class: "meta-info"
};
const _hoisted_32$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("i", { class: "fa-solid fa-note-sticky" }, null, -1));
const _hoisted_33$1 = {
  key: 3,
  class: "meta-info"
};
const _hoisted_34$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("i", { class: "fa-solid fa-clock" }, null, -1));
const _hoisted_35$1 = {
  key: 4,
  class: "meta-info"
};
const _hoisted_36$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("i", { class: "fa-solid fa-receipt" }, null, -1));
const _hoisted_37$1 = { class: "item-right" };
const _hoisted_38$1 = {
  key: 0,
  class: "item-amount"
};
const _hoisted_39$1 = /* @__PURE__ */ createTextVNode(" En camino ");
const _hoisted_40$1 = /* @__PURE__ */ createTextVNode(" Despachado ");
const _hoisted_41$1 = /* @__PURE__ */ createTextVNode(" Ver Detalles ");
const _hoisted_42$1 = { key: 0 };
const _hoisted_43$1 = { key: 1 };
const _hoisted_44$1 = /* @__PURE__ */ createTextVNode(" Cerrar ");
const _hoisted_45$1 = ["onClick"];
const _hoisted_46$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("div", {
  class: "mesa-closing-spinner",
  "aria-hidden": "true"
}, null, -1));
const _hoisted_47$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("div", { class: "mesa-closing-text" }, "Cerrando mesa...", -1));
const _hoisted_48$1 = [
  _hoisted_46$1,
  _hoisted_47$1
];
const _hoisted_49$1 = {
  key: 1,
  class: "mesa-info-item mesa-time-opening"
};
const _hoisted_50$1 = /* @__PURE__ */ createStaticVNode('<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-tabler icons-tabler-outline icon-tabler-clock-hour-4" data-v-6a4ef99a><path stroke="none" d="M0 0h24v24H0z" fill="none" data-v-6a4ef99a></path><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" data-v-6a4ef99a></path><path d="M12 12l3 2" data-v-6a4ef99a></path><path d="M12 7v5" data-v-6a4ef99a></path></svg>', 1);
const _hoisted_51$1 = {
  key: 2,
  class: "check-icon"
};
const _hoisted_52$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("i", { class: "fa-solid fa-check" }, null, -1));
const _hoisted_53$1 = [
  _hoisted_52$1
];
const _hoisted_54$1 = {
  key: 3,
  class: "star-icon"
};
const _hoisted_55$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("i", { class: "fa-solid fa-star" }, null, -1));
const _hoisted_56$1 = [
  _hoisted_55$1
];
const _hoisted_57$1 = ["onClick"];
const _hoisted_58$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("i", { class: "fa-solid fa-ellipsis-vertical" }, null, -1));
const _hoisted_59$1 = [
  _hoisted_58$1
];
const _hoisted_60$1 = ["title"];
const _hoisted_61$1 = {
  key: 0,
  class: "mesa-fuera-servicio"
};
const _hoisted_62$1 = /* @__PURE__ */ createTextVNode(" Fuera de");
const _hoisted_63$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("br", null, null, -1));
const _hoisted_64$1 = /* @__PURE__ */ createTextVNode("servicio ");
const _hoisted_65$1 = [
  _hoisted_62$1,
  _hoisted_63$1,
  _hoisted_64$1
];
const _hoisted_66$1 = {
  key: 1,
  class: "mesas-unidas-burbujas"
};
const _hoisted_67$1 = ["onClick"];
const _hoisted_68$1 = {
  key: 2,
  class: "mesa-info-item is-justify-content-center",
  style: { "margin-top": "-8px" }
};
const _hoisted_69$1 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("svg", {
  xmlns: "http://www.w3.org/2000/svg",
  width: "15",
  height: "15",
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": "2",
  "stroke-linecap": "round",
  "stroke-linejoin": "round",
  class: "icon-tabler icons-tabler-outline icon-tabler-user",
  style: { "margin-top": "-2px" }
}, [
  /* @__PURE__ */ createBaseVNode("path", {
    stroke: "none",
    d: "M0 0h24v24H0z",
    fill: "none"
  }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" })
], -1));
const _hoisted_70$1 = { class: "mesa-info-text" };
const _hoisted_71$1 = {
  key: 3,
  class: "is-flex is-flex-direction-row is-align-items-center is-justify-content-space-between bottom-meta-row"
};
const _hoisted_72$1 = {
  key: 0,
  class: "mesa-info-item"
};
const _hoisted_73 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("svg", {
  xmlns: "http://www.w3.org/2000/svg",
  width: "15",
  height: "15",
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": "2",
  "stroke-linecap": "round",
  "stroke-linejoin": "round",
  class: "icon-tabler icons-tabler-outline icon-tabler-tools-kitchen-2",
  style: { "margin-top": "-2px" }
}, [
  /* @__PURE__ */ createBaseVNode("path", {
    stroke: "none",
    d: "M0 0h24v24H0z",
    fill: "none"
  }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M19 3v12h-5c-.023 -3.681 .184 -7.406 5 -12zm0 12v6h-1v-3m-10 -14v17m-3 -17v3a3 3 0 1 0 6 0v-3" })
], -1));
const _hoisted_74 = {
  key: 1,
  class: "mesa-info-item"
};
const _hoisted_75 = /* @__PURE__ */ createStaticVNode('<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-tabler icons-tabler-outline icon-tabler-users" style="margin-top:-2px;" data-v-6a4ef99a><path stroke="none" d="M0 0h24v24H0z" fill="none" data-v-6a4ef99a></path><path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" data-v-6a4ef99a></path><path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" data-v-6a4ef99a></path><path d="M16 3.13a4 4 0 0 1 0 7.75" data-v-6a4ef99a></path><path d="M21 21v-2a4 4 0 0 0 -3 -3.85" data-v-6a4ef99a></path></svg>', 1);
const _hoisted_76 = {
  key: 0,
  class: "mesa-info-item",
  style: { "width": "50%", "justify-content": "flex-end" }
};
const _hoisted_77 = { class: "dropdown-content" };
const _hoisted_78 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("svg", {
  xmlns: "http://www.w3.org/2000/svg",
  width: "20",
  height: "20",
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": "1.2",
  "stroke-linecap": "round",
  "stroke-linejoin": "round",
  class: "icon-tabler icons-tabler-outline icon-tabler-circles-relation"
}, [
  /* @__PURE__ */ createBaseVNode("path", {
    stroke: "none",
    d: "M0 0h24v24H0z",
    fill: "none"
  }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M9.183 6.117a6 6 0 1 0 4.511 3.986" }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M14.813 17.883a6 6 0 1 0 -4.496 -3.954" })
], -1));
const _hoisted_79 = /* @__PURE__ */ createTextVNode(" Unir mesas ");
const _hoisted_80 = [
  _hoisted_78,
  _hoisted_79
];
const _hoisted_81 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("svg", {
  xmlns: "http://www.w3.org/2000/svg",
  width: "20",
  height: "20",
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": "1.2",
  "stroke-linecap": "round",
  "stroke-linejoin": "round",
  class: "icon-tabler icons-tabler-outline icon-tabler-plus"
}, [
  /* @__PURE__ */ createBaseVNode("path", {
    stroke: "none",
    d: "M0 0h24v24H0z",
    fill: "none"
  }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M12 5l0 14" }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M5 12l14 0" })
], -1));
const _hoisted_82 = /* @__PURE__ */ createTextVNode(" Agregar m\xE1s mesas ");
const _hoisted_83 = [
  _hoisted_81,
  _hoisted_82
];
const _hoisted_84 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("svg", {
  xmlns: "http://www.w3.org/2000/svg",
  width: "20",
  height: "20",
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": "1.2",
  "stroke-linecap": "round",
  "stroke-linejoin": "round",
  class: "icon-tabler icons-tabler-outline icon-tabler-scissors"
}, [
  /* @__PURE__ */ createBaseVNode("path", {
    stroke: "none",
    d: "M0 0h24v24H0z",
    fill: "none"
  }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M3 7a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M3 17a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M8.6 8.6l10.4 10.4" }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M8.6 15.4l10.4 -10.4" })
], -1));
const _hoisted_85 = /* @__PURE__ */ createTextVNode(" Separar todas las mesas ");
const _hoisted_86 = [
  _hoisted_84,
  _hoisted_85
];
const _hoisted_87 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("svg", {
  xmlns: "http://www.w3.org/2000/svg",
  width: "20",
  height: "20",
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": "1.2",
  "stroke-linecap": "round",
  "stroke-linejoin": "round",
  class: "icon-tabler icons-tabler-outline icon-tabler-arrow-narrow-right"
}, [
  /* @__PURE__ */ createBaseVNode("path", {
    stroke: "none",
    d: "M0 0h24v24H0z",
    fill: "none"
  }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M5 12l14 0" }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M15 16l4 -4" }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M15 8l4 4" })
], -1));
const _hoisted_88 = /* @__PURE__ */ createTextVNode(" Mover pedido ");
const _hoisted_89 = [
  _hoisted_87,
  _hoisted_88
];
const _hoisted_90 = /* @__PURE__ */ _withScopeId$1(() => /* @__PURE__ */ createBaseVNode("svg", {
  xmlns: "http://www.w3.org/2000/svg",
  width: "20",
  height: "20",
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  "stroke-width": "1.2",
  "stroke-linecap": "round",
  "stroke-linejoin": "round",
  class: "icon-tabler icons-tabler-outline icon-tabler-edit"
}, [
  /* @__PURE__ */ createBaseVNode("path", {
    stroke: "none",
    d: "M0 0h24v24H0z",
    fill: "none"
  }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" }),
  /* @__PURE__ */ createBaseVNode("path", { d: "M16 5l3 3" })
], -1));
const _hoisted_91 = /* @__PURE__ */ createTextVNode(" Editar mesa ");
const _hoisted_92 = [
  _hoisted_90,
  _hoisted_91
];
const _sfc_main$2 = /* @__PURE__ */ defineComponent({
  props: {
    mesas: { type: Array, required: true, default: () => [] },
    todasLasMesas: { type: Array, required: true, default: () => [] },
    selected: { type: null, required: true },
    modeEdit: { type: Boolean, required: true, default: false },
    modoUnion: { type: Boolean, required: false, default: false },
    mesaPrincipalUnion: { type: null, required: false, default: () => null },
    mesasSeleccionadas: { type: Set, required: false, default: () => new Set() },
    mostrarConfig: { type: Object, required: false, default: () => ({
      mozo: true,
      tiempo: true,
      pedidos: true,
      monto: false,
      personas: false,
      cliente: false
    }) },
    viewList: { type: Boolean, required: false },
    isDelivery: { type: Boolean, required: false },
    isTakeaway: { type: Boolean, required: false }
  },
  emits: ["select", "select-details", "edit", "separar", "separar-especifica", "iniciar-union", "agregar-mesa", "toggle-active", "mover-pedido", "shipped", "delivered", "payment", "close-table", "mover-ambiente", "restaurar-ambiente", "details"],
  setup(__props, { emit }) {
    const props = __props;
    const mesasSession = useMesaSession();
    const mesaSelected = computed(() => mesasSession.mesaSelected);
    const isClosingMesa = (mesaId) => mesasSession.isMesaClosing(mesaId);
    const requestCloseMesa = (mesa) => {
      mesasSession.startClosingMesa(mesa.id);
      emit("close-table", mesa);
    };
    console.log("MESAS EN ESTE AMBIENTE:", props.mesas.map((m) => ({
      id: m.id,
      label: m.label,
      original_environment: m.original_environment
    })));
    const menuAbierto = ref(null);
    const menuPosition = ref({ top: 0, left: 0 });
    const mesaDelMenu = ref(null);
    const toggleMenu = (mesa, event) => {
      event.stopPropagation();
      if (props.modoUnion)
        return;
      if (menuAbierto.value === mesa.id) {
        menuAbierto.value = null;
        mesaDelMenu.value = null;
        return;
      }
      const target = event.currentTarget;
      const rect = target.getBoundingClientRect();
      menuPosition.value = {
        top: rect.bottom + 4,
        left: rect.right - 200
      };
      menuAbierto.value = mesa.id;
      mesaDelMenu.value = mesa;
    };
    const cerrarMenu = () => {
      menuAbierto.value = null;
      mesaDelMenu.value = null;
    };
    const select = (item) => {
      if (menuAbierto.value !== null) {
        cerrarMenu();
        return;
      }
      if (item.is_active === false) {
        return;
      }
      emit("select", item);
    };
    const selectDetails = (item, event) => {
      event.stopPropagation();
      if (item.is_active === false) {
        return;
      }
      emit("select-details", item);
    };
    const ordersServed = (products) => {
      return (products == null ? void 0 : products.some((prod) => prod.statusBar === 4 || prod.statusKitchen === 4)) || false;
    };
    const getMesasDelGrupo = (mesa) => {
      if (!mesa.group_id)
        return [];
      return props.todasLasMesas.filter((m) => m.group_id === mesa.group_id && m.id !== mesa.id);
    };
    const toggleActiveIcon = (mesa, event) => {
      event.stopPropagation();
      if (mesa.status === MESA_NO_DISPONIBLE) {
        return;
      }
      emit("toggle-active", mesa.id);
    };
    const edit = (item) => {
      cerrarMenu();
      emit("edit", item);
    };
    const moverPedido = (mesa) => {
      cerrarMenu();
      emit("mover-pedido", mesa);
    };
    const ordersPending = (products) => {
      let pending = false;
      for (let i = 0; i < products.length; i++) {
        if (products[i].quantity_pending > 0) {
          pending = false;
          break;
        } else {
          pending = true;
        }
      }
      return pending;
    };
    const getMesaLabel = (mesa) => {
      const mesasGrupo = getMesasDelGrupo(mesa);
      if (mesasGrupo.length === 0)
        return mesa.label;
      const labelsSecundarias = mesasGrupo.map((m) => m.label);
      return `${mesa.label} + ${labelsSecundarias.join(" + ")}`;
    };
    const tieneMesasUnidas = (mesaId) => {
      const mesa = props.mesas.find((m) => m.id === mesaId);
      return (mesa == null ? void 0 : mesa.group_id) && (mesa == null ? void 0 : mesa.is_main_table) && getMesasDelGrupo(mesa).length > 0;
    };
    const getMesaClasses = (item) => {
      return {
        notavailableandprecuenta: item.status == MESA_NO_DISPONIBLE && ordersPending(item.products) && item.order_status == "precuenta",
        notavailableandorderserved: item.status === MESA_NO_DISPONIBLE && ordersServed(item.products),
        notavailableandorderpending: item.status == MESA_NO_DISPONIBLE && (!ordersPending(item.products) || item.order_status == "pending"),
        notavailable: item.status == MESA_NO_DISPONIBLE,
        available: item.status == MESA_DISPONIBLE,
        inactive: item.is_active === false,
        selected: mesaSelected.value && mesaSelected.value.id == item.id
      };
    };
    const getMesaListClasses = (item) => {
      const isShipped = item.order_status === "shipped";
      const isDelivered = item.order_status === "delivered";
      const isServed = item.order_status === "served";
      const allProductsReady = ordersPending(item.products);
      const isActuallyServed = isServed && allProductsReady;
      return {
        "is-shipped": isShipped,
        "is-delivered": isDelivered,
        notavailableandorderserved: isActuallyServed,
        notavailableandorderpending: !isShipped && !isDelivered && !isActuallyServed
      };
    };
    const handleMoveAmbiente = (mesa, event) => {
      event.stopPropagation();
      if (mesa.status === MESA_NO_DISPONIBLE) {
        return;
      }
      emit("mover-ambiente", mesa);
    };
    const esMesaMovida = (mesa) => {
      return mesa.original_environment !== null;
    };
    const hayMasDeUnAmbiente = computed(() => {
      const ambientes = new Set(props.todasLasMesas.filter((mesa) => {
        var _a;
        const env = ((_a = mesa.environment) == null ? void 0 : _a.toLowerCase()) || "";
        return !env.includes("delivery") && !env.includes("para llevar") && !env.includes("parallevar");
      }).map((mesa) => mesa.environment).filter(Boolean));
      return ambientes.size > 1;
    });
    const formatTimeOpening = (value) => {
      if (!value)
        return "";
      const normalized = value.toLowerCase().replace(/\s+/g, " ").trim();
      const hoursMatch = normalized.match(/(\d+)\s*h(?:s)?/);
      const minutesMatch = normalized.match(/(\d+)\s*m(?:in)?/);
      const hours = hoursMatch == null ? void 0 : hoursMatch[1];
      const minutes = minutesMatch == null ? void 0 : minutesMatch[1];
      if (hours && minutes)
        return `${hours}h ${minutes}m`;
      if (hours)
        return `${hours}h`;
      if (minutes)
        return `${minutes}m`;
      return value;
    };
    const isMesaAbierta = (item) => {
      return item.status === MESA_NO_DISPONIBLE;
    };
    const shouldShowPedidos = (item) => {
      var _a;
      return !!((_a = props.mostrarConfig) == null ? void 0 : _a.pedidos) && isMesaAbierta(item) && item.quantityOrders > 0 && item.is_active !== false;
    };
    const shouldShowPersonas = (item) => {
      var _a;
      return !!((_a = props.mostrarConfig) == null ? void 0 : _a.personas) && isMesaAbierta(item) && !!item.personas && item.is_active !== false;
    };
    const shouldShowMonto = (item) => {
      var _a;
      return !!((_a = props.mostrarConfig) == null ? void 0 : _a.monto) && isMesaAbierta(item) && !!item.total && item.is_active !== false;
    };
    const shouldShowMetaRow = (item) => {
      return shouldShowPedidos(item) || shouldShowPersonas(item) || shouldShowMonto(item);
    };
    return (_ctx, _cache) => {
      const _component_VButton = _sfc_main$3;
      return openBlock(), createElementBlock("div", _hoisted_1$2, [
        __props.modeEdit ? (openBlock(true), createElementBlock(Fragment, { key: 0 }, renderList(props.mesas, (item) => {
          return openBlock(), createElementBlock("div", {
            key: item.id + "M",
            class: "mesa mesa-parent-edit"
          }, [
            createBaseVNode("div", {
              class: normalizeClass(["mesa-child edit", {
                circle: item.shape == unref(MESA_SHAPES_CIRCLE),
                "mesa-movida": esMesaMovida(item)
              }]),
              onClick: ($event) => edit(item)
            }, [
              createBaseVNode("div", _hoisted_3$1, toDisplayString(item.label), 1),
              createBaseVNode("button", {
                class: normalizeClass(["button-toggle-active p-0", {
                  "is-inactive": item.is_active === false,
                  "is-disabled": item.status === unref(MESA_NO_DISPONIBLE)
                }]),
                title: item.status === unref(MESA_NO_DISPONIBLE) ? "No se puede desactivar una mesa ocupada" : item.is_active ? "Poner fuera de servicio" : "Activar mesa",
                disabled: item.status === unref(MESA_NO_DISPONIBLE),
                onClick: withModifiers(($event) => toggleActiveIcon(item, $event), ["stop"])
              }, [
                item.is_active ? (openBlock(), createElementBlock("svg", _hoisted_5$1, _hoisted_8$1)) : (openBlock(), createElementBlock("svg", _hoisted_9$1, _hoisted_13$1))
              ], 10, _hoisted_4$1),
              item.status === unref(MESA_DISPONIBLE) && !item.group_id && unref(hayMasDeUnAmbiente) ? (openBlock(), createElementBlock("button", {
                key: 0,
                class: "button-move-ambiente",
                title: "Mover a otro ambiente",
                onClick: withModifiers(($event) => handleMoveAmbiente(item, $event), ["stop"])
              }, _hoisted_16$1, 8, _hoisted_14$1)) : createCommentVNode("", true),
              item.status === unref(MESA_DISPONIBLE) && item.original_environment && !item.group_id ? (openBlock(), createElementBlock("button", {
                key: 1,
                class: "button-restore-ambiente",
                title: "Restaurar a ambiente original",
                onClick: withModifiers(($event) => _ctx.$emit("restaurar-ambiente", item.id), ["stop"])
              }, _hoisted_19$1, 8, _hoisted_17$1)) : createCommentVNode("", true)
            ], 10, _hoisted_2$1)
          ]);
        }), 128)) : (openBlock(true), createElementBlock(Fragment, { key: 1 }, renderList(props.mesas, (item) => {
          var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k;
          return openBlock(), createElementBlock("div", {
            key: item.id,
            class: normalizeClass(["mesa", { "view-list p-1": __props.viewList }])
          }, [
            __props.viewList ? (openBlock(), createElementBlock("div", {
              key: 0,
              class: normalizeClass(["mesa-list-item p-1", [getMesaListClasses(item), { "is-closing": isClosingMesa(item.id) }]])
            }, [
              isClosingMesa(item.id) ? (openBlock(), createElementBlock("div", {
                key: 0,
                class: "mesa-closing-overlay mesa-closing-overlay--list",
                onClick: _cache[0] || (_cache[0] = withModifiers(() => {
                }, ["stop"]))
              }, _hoisted_22$1)) : createCommentVNode("", true),
              createBaseVNode("div", _hoisted_23$1, [
                createBaseVNode("div", {
                  class: normalizeClass(["item-label-badge", getMesaListClasses(item)])
                }, toDisplayString(item.label), 3),
                createBaseVNode("div", _hoisted_24$1, [
                  createBaseVNode("div", _hoisted_25$1, toDisplayString(item.cliente ? item.cliente : "Sin cliente"), 1),
                  createBaseVNode("div", _hoisted_26$1, [
                    ((_a = item.delivery) == null ? void 0 : _a.phone) ? (openBlock(), createElementBlock("span", _hoisted_27$1, [
                      _hoisted_28$1,
                      createTextVNode(" " + toDisplayString(item.delivery.phone), 1)
                    ])) : createCommentVNode("", true),
                    ((_b = item.delivery) == null ? void 0 : _b.address) ? (openBlock(), createElementBlock("span", _hoisted_29, [
                      _hoisted_30,
                      createTextVNode(" " + toDisplayString(item.delivery.address), 1)
                    ])) : createCommentVNode("", true),
                    ((_c = item.delivery) == null ? void 0 : _c.reference) ? (openBlock(), createElementBlock("span", _hoisted_31, [
                      _hoisted_32$1,
                      createTextVNode(" " + toDisplayString(item.delivery.reference), 1)
                    ])) : createCommentVNode("", true),
                    item.timeOpening ? (openBlock(), createElementBlock("span", _hoisted_33$1, [
                      _hoisted_34$1,
                      createTextVNode(" " + toDisplayString(formatTimeOpening(item.timeOpening)), 1)
                    ])) : createCommentVNode("", true),
                    item.quantityOrders ? (openBlock(), createElementBlock("span", _hoisted_35$1, [
                      _hoisted_36$1,
                      createTextVNode(" " + toDisplayString(item.quantityOrders), 1)
                    ])) : createCommentVNode("", true)
                  ])
                ])
              ]),
              createBaseVNode("div", _hoisted_37$1, [
                item.total ? (openBlock(), createElementBlock("div", _hoisted_38$1, " S/ " + toDisplayString(item.total.toFixed(2)), 1)) : createCommentVNode("", true),
                createBaseVNode("div", {
                  class: "item-actions ml-3",
                  onClick: _cache[1] || (_cache[1] = withModifiers(() => {
                  }, ["stop"]))
                }, [
                  props.isDelivery && item.order_status === "served" ? (openBlock(), createBlock(_component_VButton, {
                    key: 0,
                    class: "is-link",
                    onClick: ($event) => emit("shipped", item)
                  }, {
                    default: withCtx(() => [
                      _hoisted_39$1
                    ]),
                    _: 2
                  }, 1032, ["onClick"])) : createCommentVNode("", true),
                  props.isDelivery && item.order_status === "shipped" || props.isTakeaway && item.order_status === "served" ? (openBlock(), createBlock(_component_VButton, {
                    key: 1,
                    color: "success",
                    onClick: ($event) => emit("delivered", item)
                  }, {
                    default: withCtx(() => [
                      _hoisted_40$1
                    ]),
                    _: 2
                  }, 1032, ["onClick"])) : createCommentVNode("", true),
                  createVNode(_component_VButton, {
                    class: "is-info",
                    onClick: ($event) => emit("details", item)
                  }, {
                    default: withCtx(() => [
                      _hoisted_41$1
                    ]),
                    _: 2
                  }, 1032, ["onClick"]),
                  createVNode(_component_VButton, {
                    color: item.is_paid ? "white" : "danger",
                    disabled: item.is_paid,
                    onClick: ($event) => emit("payment", item)
                  }, {
                    default: withCtx(() => [
                      !item.is_paid ? (openBlock(), createElementBlock("span", _hoisted_42$1, "Pagar")) : (openBlock(), createElementBlock("span", _hoisted_43$1, "Pagado"))
                    ]),
                    _: 2
                  }, 1032, ["color", "disabled", "onClick"]),
                  item.is_paid && item.order_status === "delivered" ? (openBlock(), createBlock(_component_VButton, {
                    key: 2,
                    color: "success",
                    onClick: ($event) => requestCloseMesa(item)
                  }, {
                    default: withCtx(() => [
                      _hoisted_44$1
                    ]),
                    _: 2
                  }, 1032, ["onClick"])) : createCommentVNode("", true)
                ])
              ])
            ], 2)) : (openBlock(), createElementBlock("div", {
              key: 1,
              class: normalizeClass(["mesa-child", __spreadProps(__spreadValues({}, getMesaClasses(item)), {
                "has-union": tieneMesasUnidas(item.id),
                "mesa-movida": esMesaMovida(item),
                seleccionada: (_d = __props.mesasSeleccionadas) == null ? void 0 : _d.has(item.id),
                "principal-union": ((_e = __props.mesaPrincipalUnion) == null ? void 0 : _e.id) === item.id,
                "is-closing": isClosingMesa(item.id),
                "modo-seleccion": __props.modoUnion && !item.group_id && ((_f = __props.mesaPrincipalUnion) == null ? void 0 : _f.id) !== item.id && item.is_active !== false,
                circle: item.shape == unref(MESA_SHAPES_CIRCLE)
              })]),
              onClick: ($event) => select(item)
            }, [
              isClosingMesa(item.id) ? (openBlock(), createElementBlock("div", {
                key: 0,
                class: "mesa-closing-overlay",
                onClick: _cache[2] || (_cache[2] = withModifiers(() => {
                }, ["stop"]))
              }, _hoisted_48$1)) : createCommentVNode("", true),
              ((_g = __props.mostrarConfig) == null ? void 0 : _g.tiempo) && isMesaAbierta(item) && item.timeOpening && item.is_active !== false ? (openBlock(), createElementBlock("div", _hoisted_49$1, [
                _hoisted_50$1,
                createTextVNode(" " + toDisplayString(formatTimeOpening(item.timeOpening)), 1)
              ])) : createCommentVNode("", true),
              __props.modoUnion && ((_h = __props.mesasSeleccionadas) == null ? void 0 : _h.has(item.id)) ? (openBlock(), createElementBlock("div", _hoisted_51$1, _hoisted_53$1)) : createCommentVNode("", true),
              __props.modoUnion && ((_i = __props.mesaPrincipalUnion) == null ? void 0 : _i.id) === item.id ? (openBlock(), createElementBlock("div", _hoisted_54$1, _hoisted_56$1)) : createCommentVNode("", true),
              !__props.modeEdit && !__props.modoUnion && item.is_active !== false ? (openBlock(), createElementBlock("button", {
                key: 4,
                class: "button-menu",
                onClick: withModifiers(($event) => toggleMenu(item, $event), ["stop"])
              }, _hoisted_59$1, 8, _hoisted_57$1)) : createCommentVNode("", true),
              createBaseVNode("div", {
                class: normalizeClass(["mesa-content", shouldShowMetaRow(item) ? "mt-auto" : ""])
              }, [
                createBaseVNode("div", {
                  class: "mesa-label",
                  title: getMesaLabel(item)
                }, toDisplayString(getMesaLabel(item)), 9, _hoisted_60$1),
                item.is_active === false ? (openBlock(), createElementBlock("div", _hoisted_61$1, _hoisted_65$1)) : createCommentVNode("", true),
                tieneMesasUnidas(item.id) && item.is_active !== false ? (openBlock(), createElementBlock("div", _hoisted_66$1, [
                  (openBlock(true), createElementBlock(Fragment, null, renderList(getMesasDelGrupo(item), (mesaUnida) => {
                    return openBlock(), createElementBlock("span", {
                      key: mesaUnida.id,
                      class: "burbuja-mesa",
                      onClick: ($event) => selectDetails(mesaUnida, $event)
                    }, toDisplayString(mesaUnida.label), 9, _hoisted_67$1);
                  }), 128))
                ])) : createCommentVNode("", true),
                ((_j = __props.mostrarConfig) == null ? void 0 : _j.mozo) && isMesaAbierta(item) && item.waiter && item.is_active !== false ? (openBlock(), createElementBlock("div", _hoisted_68$1, [
                  _hoisted_69$1,
                  createBaseVNode("span", _hoisted_70$1, toDisplayString(item.waiter), 1)
                ])) : createCommentVNode("", true),
                shouldShowMetaRow(item) ? (openBlock(), createElementBlock("div", _hoisted_71$1, [
                  createBaseVNode("div", {
                    class: "is-flex is-justify-content-space-between",
                    style: normalizeStyle({
                      width: shouldShowMonto(item) ? "50%" : "100%"
                    })
                  }, [
                    shouldShowPedidos(item) ? (openBlock(), createElementBlock("div", _hoisted_72$1, [
                      _hoisted_73,
                      createTextVNode(" " + toDisplayString(item.quantityOrders), 1)
                    ])) : createCommentVNode("", true),
                    shouldShowPersonas(item) ? (openBlock(), createElementBlock("div", _hoisted_74, [
                      _hoisted_75,
                      createTextVNode(" " + toDisplayString(item.personas), 1)
                    ])) : createCommentVNode("", true)
                  ], 4),
                  shouldShowMonto(item) ? (openBlock(), createElementBlock("div", _hoisted_76, " S/ " + toDisplayString((_k = item.total) == null ? void 0 : _k.toFixed(2)), 1)) : createCommentVNode("", true)
                ])) : createCommentVNode("", true)
              ], 2)
            ], 10, _hoisted_45$1))
          ], 2);
        }), 128)),
        (openBlock(), createBlock(Teleport, { to: "body" }, [
          menuAbierto.value !== null ? (openBlock(), createElementBlock("div", {
            key: 0,
            class: "menu-overlay",
            onClick: cerrarMenu
          })) : createCommentVNode("", true)
        ])),
        (openBlock(), createBlock(Teleport, { to: "body" }, [
          menuAbierto.value !== null && mesaDelMenu.value ? (openBlock(), createElementBlock("div", {
            key: 0,
            class: "dropdown-menu-fixed",
            style: normalizeStyle({
              top: menuPosition.value.top + "px",
              left: menuPosition.value.left + "px"
            }),
            onClick: _cache[8] || (_cache[8] = withModifiers(() => {
            }, ["stop"]))
          }, [
            createBaseVNode("div", _hoisted_77, [
              !mesaDelMenu.value.group_id ? (openBlock(), createElementBlock("a", {
                key: 0,
                class: "dropdown-item",
                onClick: _cache[3] || (_cache[3] = ($event) => (emit("iniciar-union", mesaDelMenu.value), cerrarMenu()))
              }, _hoisted_80)) : createCommentVNode("", true),
              tieneMesasUnidas(mesaDelMenu.value.id) ? (openBlock(), createElementBlock("a", {
                key: 1,
                class: "dropdown-item",
                onClick: _cache[4] || (_cache[4] = ($event) => (emit("agregar-mesa", mesaDelMenu.value), cerrarMenu()))
              }, _hoisted_83)) : createCommentVNode("", true),
              tieneMesasUnidas(mesaDelMenu.value.id) ? (openBlock(), createElementBlock("a", {
                key: 2,
                class: "dropdown-item",
                onClick: _cache[5] || (_cache[5] = ($event) => (emit("separar", mesaDelMenu.value.id), cerrarMenu()))
              }, _hoisted_86)) : createCommentVNode("", true),
              mesaDelMenu.value.status === unref(MESA_NO_DISPONIBLE) ? (openBlock(), createElementBlock("a", {
                key: 3,
                class: "dropdown-item",
                onClick: _cache[6] || (_cache[6] = ($event) => (moverPedido(mesaDelMenu.value), cerrarMenu()))
              }, _hoisted_89)) : createCommentVNode("", true),
              createBaseVNode("a", {
                class: "dropdown-item",
                onClick: _cache[7] || (_cache[7] = ($event) => (edit(mesaDelMenu.value), cerrarMenu()))
              }, _hoisted_92)
            ])
          ], 4)) : createCommentVNode("", true)
        ]))
      ]);
    };
  }
});
var __unplugin_components_3 = /* @__PURE__ */ _export_sfc(_sfc_main$2, [["__scopeId", "data-v-6a4ef99a"]]);
var MesaRestaurant_vue_vue_type_style_index_0_lang = "";
var MesaRestaurant_vue_vue_type_style_index_1_scoped_true_lang = "";
const _withScopeId = (n) => (pushScopeId("data-v-241f3b28"), n = n(), popScopeId(), n);
const _hoisted_1$1 = {
  key: 0,
  class: "ecommerce-dashboard ecommerce-dashboard-v1"
};
const _hoisted_2 = { class: "columns is-multiline" };
const _hoisted_3 = { class: "column is-9" };
const _hoisted_4 = { class: "stat-widget line-stats-widget is-straight" };
const _hoisted_5 = { class: "mesa-top-bar mb-3" };
const _hoisted_6 = {
  key: 0,
  class: "filtros-mesas"
};
const _hoisted_7 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("span", { class: "filtros-label" }, "Mostrar Mesas:", -1));
const _hoisted_8 = {
  key: 0,
  class: "fa-solid fa-check"
};
const _hoisted_9 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("span", { class: "checkbox-text" }, "Disponibles", -1));
const _hoisted_10 = {
  key: 0,
  class: "fa-solid fa-check"
};
const _hoisted_11 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("span", { class: "checkbox-text" }, "Ocupadas", -1));
const _hoisted_12 = {
  key: 0,
  class: "fa-solid fa-check"
};
const _hoisted_13 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("span", { class: "checkbox-text" }, "Fuera de servicio", -1));
const _hoisted_14 = {
  key: 1,
  class: "visualizacion-mesas"
};
const _hoisted_15 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("span", { class: "filtros-label" }, "Mostrar en mesas:", -1));
const _hoisted_16 = ["onClick"];
const _hoisted_17 = {
  key: 0,
  class: "fa-solid fa-check"
};
const _hoisted_18 = { class: "checkbox-text" };
const _hoisted_19 = ["innerHTML"];
const _hoisted_20 = { class: "mesa-buttons" };
const _hoisted_21 = /* @__PURE__ */ createTextVNode(" Editar mesas ");
const _hoisted_22 = /* @__PURE__ */ createTextVNode(" Finalizar ");
const _hoisted_23 = { class: "mesa-controls-container mt-3" };
const _hoisted_24 = { class: "mesa-buttons" };
const _hoisted_25 = /* @__PURE__ */ createTextVNode(" Editar mesas ");
const _hoisted_26 = /* @__PURE__ */ createTextVNode(" Finalizar ");
const _hoisted_27 = {
  key: 0,
  class: "mesa-legend-edit"
};
const _hoisted_28 = /* @__PURE__ */ createStaticVNode('<div class="legend-item" data-v-241f3b28><span class="legend-icon-edit disponible" data-v-241f3b28><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-tabler icons-tabler-outline icon-tabler-check" data-v-241f3b28><path stroke="none" d="M0 0h24v24H0z" fill="none" data-v-241f3b28></path><path d="M5 12l5 5l10 -10" data-v-241f3b28></path></svg></span><span class="legend-text" data-v-241f3b28>Disponible</span></div><div class="legend-item" data-v-241f3b28><span class="legend-icon-edit ocupada" data-v-241f3b28><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-tabler icons-tabler-outline icon-tabler-check" data-v-241f3b28><path stroke="none" d="M0 0h24v24H0z" fill="none" data-v-241f3b28></path><path d="M5 12l5 5l10 -10" data-v-241f3b28></path></svg></span><span class="legend-text" data-v-241f3b28>Ocupada (no se puede desactivar)</span></div><div class="legend-item" data-v-241f3b28><span class="legend-icon-edit fuera-servicio" data-v-241f3b28><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-tabler icons-tabler-outline icon-tabler-ban" data-v-241f3b28><path stroke="none" d="M0 0h24v24H0z" fill="none" data-v-241f3b28></path><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" data-v-241f3b28></path><path d="M5.7 5.7l12.6 12.6" data-v-241f3b28></path></svg></span><span class="legend-text" data-v-241f3b28>Fuera de servicio</span></div><div class="legend-item" data-v-241f3b28><span class="legend-indicator moved" data-v-241f3b28></span><span class="legend-text" data-v-241f3b28>Mesa reubicada</span></div>', 4);
const _hoisted_32 = [
  _hoisted_28
];
const _hoisted_33 = { class: "column is-3" };
const _hoisted_34 = {
  key: 0,
  class: "stat-widget area-stats-widget is-straight"
};
const _hoisted_35 = { class: "fieldset-heading pb-4" };
const _hoisted_36 = { style: { "font-size": "13px" } };
const _hoisted_37 = {
  key: 0,
  class: "notification is-secondary mb-2 p-3"
};
const _hoisted_38 = { class: "container-text" };
const _hoisted_39 = { class: "" };
const _hoisted_40 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("br", null, null, -1));
const _hoisted_41 = { style: { "font-size": "13px" } };
const _hoisted_42 = {
  class: "is-flex is-justify-content-space-between mt-2",
  style: { "gap": "10px" }
};
const _hoisted_43 = /* @__PURE__ */ createTextVNode(" Cancelar ");
const _hoisted_44 = {
  key: 1,
  class: "column is-12 mb-3 px-0"
};
const _hoisted_45 = { class: "box p-3 joined-tables-container" };
const _hoisted_46 = { style: { "display": "flex", "align-items": "center", "justify-content": "space-between", "margin-bottom": "8px" } };
const _hoisted_47 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("label", { style: { "font-weight": "600", "font-size": "13px", "color": "#48c774" } }, [
  /* @__PURE__ */ createBaseVNode("i", { class: "fa-solid fa-link" }),
  /* @__PURE__ */ createTextVNode(" Mesas unidas ")
], -1));
const _hoisted_48 = /* @__PURE__ */ createTextVNode(" Separar todas ");
const _hoisted_49 = { style: { "display": "flex", "flex-direction": "column", "gap": "6px" } };
const _hoisted_50 = { style: { "font-size": "13px", "font-weight": "500" } };
const _hoisted_51 = ["onClick"];
const _hoisted_52 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("i", { class: "fa-solid fa-xmark" }, null, -1));
const _hoisted_53 = [
  _hoisted_52
];
const _hoisted_54 = { class: "columns is-multiline" };
const _hoisted_55 = { class: "column is-12" };
const _hoisted_56 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("label", null, "Personas en la mesa", -1));
const _hoisted_57 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("div", { class: "form-icon" }, [
  /* @__PURE__ */ createBaseVNode("svg", {
    xmlns: "http://www.w3.org/2000/svg",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    "stroke-width": "2",
    "stroke-linecap": "round",
    "stroke-linejoin": "round",
    class: "icon icon-tabler icons-tabler-outline icon-tabler-hash"
  }, [
    /* @__PURE__ */ createBaseVNode("path", {
      stroke: "none",
      d: "M0 0h24v24H0z",
      fill: "none"
    }),
    /* @__PURE__ */ createBaseVNode("path", { d: "M5 9l14 0" }),
    /* @__PURE__ */ createBaseVNode("path", { d: "M5 15l14 0" }),
    /* @__PURE__ */ createBaseVNode("path", { d: "M11 4l-4 16" }),
    /* @__PURE__ */ createBaseVNode("path", { d: "M17 4l-4 16" })
  ])
], -1));
const _hoisted_58 = { class: "column is-12" };
const _hoisted_59 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("label", null, "Cliente", -1));
const _hoisted_60 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("div", { class: "form-icon" }, [
  /* @__PURE__ */ createBaseVNode("svg", {
    xmlns: "http://www.w3.org/2000/svg",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    "stroke-width": "2",
    "stroke-linecap": "round",
    "stroke-linejoin": "round",
    class: "icon icon-tabler icons-tabler-outline icon-tabler-user"
  }, [
    /* @__PURE__ */ createBaseVNode("path", {
      stroke: "none",
      d: "M0 0h24v24H0z",
      fill: "none"
    }),
    /* @__PURE__ */ createBaseVNode("path", { d: "M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" }),
    /* @__PURE__ */ createBaseVNode("path", { d: "M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" })
  ])
], -1));
const _hoisted_61 = { class: "column is-12" };
const _hoisted_62 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("label", null, "Mozo", -1));
const _hoisted_63 = {
  class: "select",
  placeholder: "Seleccione mozo"
};
const _hoisted_64 = ["value"];
const _hoisted_65 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("div", { class: "form-icon" }, [
  /* @__PURE__ */ createBaseVNode("svg", {
    xmlns: "http://www.w3.org/2000/svg",
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    "stroke-width": "2",
    "stroke-linecap": "round",
    "stroke-linejoin": "round",
    class: "icon icon-tabler icons-tabler-outline icon-tabler-user-check"
  }, [
    /* @__PURE__ */ createBaseVNode("path", {
      stroke: "none",
      d: "M0 0h24v24H0z",
      fill: "none"
    }),
    /* @__PURE__ */ createBaseVNode("path", { d: "M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" }),
    /* @__PURE__ */ createBaseVNode("path", { d: "M6 21v-2a4 4 0 0 1 4 -4h4" }),
    /* @__PURE__ */ createBaseVNode("path", { d: "M15 19l2 2l4 -4" })
  ])
], -1));
const _hoisted_66 = { class: "column is-12" };
const _hoisted_67 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("label", null, "Comentarios", -1));
const _hoisted_68 = { class: "column is-12" };
const _hoisted_69 = /* @__PURE__ */ createTextVNode(" Abrir mesa ");
const _hoisted_70 = {
  key: 1,
  class: "stat-widget area-stats-widget is-straight"
};
const _hoisted_71 = /* @__PURE__ */ _withScopeId(() => /* @__PURE__ */ createBaseVNode("h3", null, "SELECCIONE UNA MESA", -1));
const _hoisted_72 = [
  _hoisted_71
];
const _sfc_main$1 = /* @__PURE__ */ defineComponent({
  setup(__props) {
    new Notyf();
    const mesasSession = reactive(useMesaSession());
    const companySession = reactive(useCompanySession());
    const mesaSelected = ref(mesasSession.mesaSelected);
    let mesas = ref([]);
    const userSession = useUserSession();
    const userRole = userSession.getRole();
    let viewPos = ref(false);
    let canUpdate = ref(false);
    let openModalEditMesa = ref(false);
    let loadingRefresh = ref(false);
    const environments = mesasSession.environments;
    const environment_1 = environments[0];
    const environment_2 = environments[1];
    const environment_3 = environments[2];
    const environment_4 = environments[3];
    let stateFormMesa = reactive({
      id: 0,
      label: "",
      shape: "",
      environment: DEFAULT_ENVIRONMENT
    });
    let modeEditMesas = ref(false);
    const customerDefault = companySession.getCustomerDefault();
    const selectMesa = (item) => {
      if (item.status == MESA_DISPONIBLE) {
        mesasSession.setMesaSelected(item.id, customerDefault.apellidos_y_nombres_o_razon_social);
        canUpdate.value = true;
      } else {
        mesasSession.setMesaSelected(item.id);
        viewPos.value = true;
      }
    };
    const openMesa = () => {
      if (mesaSelected.value) {
        mesasSession.openMesa(mesaSelected.value);
        viewPos.value = true;
      }
    };
    const viewMesas = () => {
      viewPos.value = false;
    };
    const editMesaForm = (item) => {
      const { id, label, shape, environment } = item;
      stateFormMesa.id = id;
      stateFormMesa.label = label;
      stateFormMesa.shape = shape;
      stateFormMesa.environment = environment;
      openModalEditMesa.value = true;
    };
    const editMesas = () => {
      modeEditMesas.value = true;
    };
    const finishEditMesas = () => {
      modeEditMesas.value = false;
      syncMesasEnviroments();
    };
    const setMesas = () => {
      mesas.value = mesasSession.mesas;
    };
    const closeFormMesa = () => {
      openModalEditMesa.value = false;
    };
    const syncMesasEnviroments = async () => {
      await MesaService.syncMesasAndEnvironments();
      setMesas();
    };
    watch(() => [...mesasSession.mesas], () => {
      if (!modeEditMesas.value)
        setMesas();
    });
    onMounted(() => {
      setMesas();
      syncMesasEnviroments();
      initRealtime();
    });
    return (_ctx, _cache) => {
      const _component_MesaForm = _sfc_main$4;
      const _component_CashDialog = _sfc_main$5;
      const _component_VButton = _sfc_main$3;
      const _component_MesaEnvironment = __unplugin_components_3;
      const _component_VTabs = _sfc_main$6;
      const _component_VPlaceload = __unplugin_components_4;
      const _component_VPlaceloadWrap = __unplugin_components_5;
      const _component_VControl = __unplugin_components_1$1;
      const _component_VField = _sfc_main$7;
      const _component_MesaRestaurantPos = _sfc_main$8;
      return openBlock(), createElementBlock(Fragment, null, [
        createVNode(_component_MesaForm, {
          open: unref(openModalEditMesa),
          model: unref(stateFormMesa),
          onClose: closeFormMesa
        }, null, 8, ["open", "model"]),
        createVNode(_component_CashDialog),
        !unref(viewPos) ? (openBlock(), createElementBlock("div", _hoisted_1$1, [
          createBaseVNode("div", _hoisted_2, [
            createBaseVNode("div", _hoisted_3, [
              createBaseVNode("div", _hoisted_4, [
                createBaseVNode("div", _hoisted_5, [
                  !unref(modeEditMesas) ? (openBlock(), createElementBlock("div", _hoisted_6, [
                    _hoisted_7,
                    !_ctx.isDeliveryOrTakeaway ? (openBlock(), createElementBlock("label", {
                      key: 0,
                      class: "checkbox-inline",
                      onClick: _cache[0] || (_cache[0] = ($event) => _ctx.toggleFiltro("disponibles"))
                    }, [
                      createBaseVNode("span", {
                        class: normalizeClass(["checkbox-box", { "is-checked": _ctx.filtros.disponibles }])
                      }, [
                        _ctx.filtros.disponibles ? (openBlock(), createElementBlock("i", _hoisted_8)) : createCommentVNode("", true)
                      ], 2),
                      _hoisted_9
                    ])) : createCommentVNode("", true),
                    !_ctx.isDeliveryOrTakeaway ? (openBlock(), createElementBlock("label", {
                      key: 1,
                      class: "checkbox-inline",
                      onClick: _cache[1] || (_cache[1] = ($event) => _ctx.toggleFiltro("ocupadas"))
                    }, [
                      createBaseVNode("span", {
                        class: normalizeClass(["checkbox-box", { "is-checked": _ctx.filtros.ocupadas }])
                      }, [
                        _ctx.filtros.ocupadas ? (openBlock(), createElementBlock("i", _hoisted_10)) : createCommentVNode("", true)
                      ], 2),
                      _hoisted_11
                    ])) : createCommentVNode("", true),
                    !_ctx.isDeliveryOrTakeaway ? (openBlock(), createElementBlock("label", {
                      key: 2,
                      class: "checkbox-inline",
                      onClick: _cache[2] || (_cache[2] = ($event) => _ctx.toggleFiltro("fueraServicio"))
                    }, [
                      createBaseVNode("span", {
                        class: normalizeClass(["checkbox-box", { "is-checked": _ctx.filtros.fueraServicio }])
                      }, [
                        _ctx.filtros.fueraServicio ? (openBlock(), createElementBlock("i", _hoisted_12)) : createCommentVNode("", true)
                      ], 2),
                      _hoisted_13
                    ])) : createCommentVNode("", true)
                  ])) : createCommentVNode("", true),
                  unref(modeEditMesas) ? (openBlock(), createElementBlock("div", _hoisted_14, [
                    _hoisted_15,
                    (openBlock(true), createElementBlock(Fragment, null, renderList(_ctx.opcionesVisualizacion, (opcion, key) => {
                      return withDirectives((openBlock(), createElementBlock("label", {
                        key,
                        class: "checkbox-inline",
                        onClick: ($event) => _ctx.toggleVisualizacion(key)
                      }, [
                        createBaseVNode("span", {
                          class: normalizeClass(["checkbox-box", { "is-checked": opcion.activo }])
                        }, [
                          opcion.activo ? (openBlock(), createElementBlock("i", _hoisted_17)) : createCommentVNode("", true)
                        ], 2),
                        createBaseVNode("span", _hoisted_18, [
                          _ctx.isSvgIcon(opcion.icono) ? (openBlock(), createElementBlock("span", {
                            key: 0,
                            class: "checkbox-icon",
                            innerHTML: opcion.icono
                          }, null, 8, _hoisted_19)) : (openBlock(), createElementBlock("i", {
                            key: 1,
                            class: normalizeClass(["fa-solid", opcion.icono]),
                            "aria-hidden": "true"
                          }, null, 2)),
                          createTextVNode(" " + toDisplayString(opcion.label), 1)
                        ])
                      ], 8, _hoisted_16)), [
                        [vShow, key !== "cliente"]
                      ]);
                    }), 128))
                  ])) : createCommentVNode("", true),
                  createBaseVNode("div", _hoisted_20, [
                    !unref(modeEditMesas) && unref(userRole) != "MOZO" ? (openBlock(), createBlock(_component_VButton, {
                      key: 0,
                      color: "primary",
                      raised: "",
                      disabled: _ctx.isDeliveryOrTakeaway,
                      onClick: editMesas
                    }, {
                      default: withCtx(() => [
                        _hoisted_21
                      ]),
                      _: 1
                    }, 8, ["disabled"])) : createCommentVNode("", true),
                    unref(modeEditMesas) && unref(userRole) != "MOZO" ? (openBlock(), createBlock(_component_VButton, {
                      key: 1,
                      class: "btn-secondary",
                      raised: "",
                      onClick: finishEditMesas
                    }, {
                      default: withCtx(() => [
                        _hoisted_22
                      ]),
                      _: 1
                    })) : createCommentVNode("", true)
                  ])
                ]),
                createVNode(_component_VTabs, {
                  selected: unref(environments)[0].name,
                  tabs: unref(environments).filter((item) => item.active === true).map((item) => {
                    return { label: item.name, value: item.name };
                  })
                }, {
                  tab: withCtx(({ activeValue }) => [
                    activeValue === unref(environment_1).name ? (openBlock(), createBlock(_component_MesaEnvironment, {
                      key: 0,
                      mesas: unref(mesas).filter((x) => x.environment == unref(environment_1).name),
                      selected: mesaSelected.value,
                      "mode-edit": unref(modeEditMesas),
                      onSelect: selectMesa,
                      onEdit: editMesaForm
                    }, null, 8, ["mesas", "selected", "mode-edit"])) : activeValue === unref(environment_2).name ? (openBlock(), createBlock(_component_MesaEnvironment, {
                      key: 1,
                      mesas: unref(mesas).filter((x) => x.environment == unref(environment_2).name),
                      selected: mesaSelected.value,
                      "mode-edit": unref(modeEditMesas),
                      onSelect: selectMesa,
                      onEdit: editMesaForm
                    }, null, 8, ["mesas", "selected", "mode-edit"])) : activeValue === unref(environment_3).name ? (openBlock(), createBlock(_component_MesaEnvironment, {
                      key: 2,
                      mesas: unref(mesas).filter((x) => x.environment == unref(environment_3).name),
                      selected: mesaSelected.value,
                      "mode-edit": unref(modeEditMesas),
                      onSelect: selectMesa,
                      onEdit: editMesaForm
                    }, null, 8, ["mesas", "selected", "mode-edit"])) : activeValue === unref(environment_4).name ? (openBlock(), createBlock(_component_MesaEnvironment, {
                      key: 3,
                      mesas: unref(mesas).filter((x) => x.environment == unref(environment_4).name),
                      selected: mesaSelected.value,
                      "mode-edit": unref(modeEditMesas),
                      onSelect: selectMesa,
                      onEdit: editMesaForm
                    }, null, 8, ["mesas", "selected", "mode-edit"])) : createCommentVNode("", true)
                  ]),
                  _: 1
                }, 8, ["selected", "tabs"]),
                unref(loadingRefresh) ? (openBlock(), createBlock(_component_VPlaceloadWrap, { key: 0 }, {
                  default: withCtx(() => [
                    createVNode(_component_VPlaceload, {
                      height: "25px",
                      width: "20%",
                      class: "mx-2"
                    })
                  ]),
                  _: 1
                })) : createCommentVNode("", true),
                createBaseVNode("div", _hoisted_23, [
                  createBaseVNode("div", _hoisted_24, [
                    !unref(modeEditMesas) && unref(userRole) != "MOZO" ? (openBlock(), createBlock(_component_VButton, {
                      key: 0,
                      onClick: editMesas
                    }, {
                      default: withCtx(() => [
                        _hoisted_25
                      ]),
                      _: 1
                    })) : createCommentVNode("", true),
                    unref(modeEditMesas) && unref(userRole) != "MOZO" ? (openBlock(), createBlock(_component_VButton, {
                      key: 1,
                      onClick: finishEditMesas
                    }, {
                      default: withCtx(() => [
                        _hoisted_26
                      ]),
                      _: 1
                    })) : createCommentVNode("", true)
                  ]),
                  unref(modeEditMesas) ? (openBlock(), createElementBlock("div", _hoisted_27, _hoisted_32)) : createCommentVNode("", true)
                ])
              ])
            ]),
            createBaseVNode("div", _hoisted_33, [
              mesaSelected.value && mesaSelected.value.id !== 0 ? (openBlock(), createElementBlock("div", _hoisted_34, [
                createBaseVNode("div", _hoisted_35, [
                  createBaseVNode("h4", null, [
                    createTextVNode(" MESA " + toDisplayString(mesaSelected.value.label) + " ", 1),
                    createBaseVNode("span", _hoisted_36, toDisplayString(mesaSelected.value.environment), 1)
                  ])
                ]),
                _ctx.modoSeleccionUnion && _ctx.mesaPrincipalParaUnion ? (openBlock(), createElementBlock("div", _hoisted_37, [
                  createBaseVNode("div", null, [
                    createBaseVNode("div", _hoisted_38, [
                      createBaseVNode("strong", _hoisted_39, [
                        createBaseVNode("i", {
                          class: normalizeClass(_ctx.grupoExistenteParaAgregar ? "fa-solid fa-plus" : "fa-solid fa-link")
                        }, null, 2),
                        createTextVNode(" " + toDisplayString(_ctx.grupoExistenteParaAgregar ? " Agregando mesas" : " Uniendo Mesa") + " " + toDisplayString(_ctx.mesaPrincipalParaUnion.label), 1)
                      ]),
                      _hoisted_40,
                      createBaseVNode("span", _hoisted_41, " Selecciona las mesas " + toDisplayString(_ctx.grupoExistenteParaAgregar ? "adicionales" : "secundarias") + " (" + toDisplayString(_ctx.mesasSecundariasSeleccionadas.size) + " seleccionadas) ", 1)
                    ]),
                    createBaseVNode("div", _hoisted_42, [
                      createVNode(_component_VButton, {
                        class: "is-fullwidth",
                        color: "primary",
                        disabled: _ctx.mesasSecundariasSeleccionadas.size === 0,
                        onClick: _ctx.confirmarUnion
                      }, {
                        default: withCtx(() => [
                          createTextVNode(toDisplayString(_ctx.grupoExistenteParaAgregar ? "Agregar mesas" : "Confirmar"), 1)
                        ]),
                        _: 1
                      }, 8, ["disabled", "onClick"]),
                      createVNode(_component_VButton, {
                        class: "is-fullwidth",
                        color: "danger",
                        onClick: _ctx.cancelarUnion
                      }, {
                        default: withCtx(() => [
                          _hoisted_43
                        ]),
                        _: 1
                      }, 8, ["onClick"])
                    ])
                  ])
                ])) : createCommentVNode("", true),
                _ctx.getMesasUnidasCompletas(mesaSelected.value.id).length > 0 ? (openBlock(), createElementBlock("div", _hoisted_44, [
                  createBaseVNode("div", _hoisted_45, [
                    createBaseVNode("div", _hoisted_46, [
                      _hoisted_47,
                      createVNode(_component_VButton, {
                        color: "warning",
                        style: { "padding": "4px 8px", "font-size": "11px" },
                        onClick: _cache[3] || (_cache[3] = ($event) => _ctx.handleSepararMesa(mesaSelected.value.id))
                      }, {
                        default: withCtx(() => [
                          _hoisted_48
                        ]),
                        _: 1
                      })
                    ]),
                    createBaseVNode("div", _hoisted_49, [
                      (openBlock(true), createElementBlock(Fragment, null, renderList(_ctx.getMesasUnidasCompletas(mesaSelected.value.id), (mesaUnida) => {
                        return openBlock(), createElementBlock("div", {
                          key: mesaUnida.id,
                          style: { "display": "flex", "align-items": "center", "justify-content": "space-between", "padding": "6px 8px", "background": "white", "border-radius": "4px", "border": "1px solid #dee2e6" }
                        }, [
                          createBaseVNode("span", _hoisted_50, " Mesa " + toDisplayString(mesaUnida.label), 1),
                          createBaseVNode("button", {
                            style: { "background": "none", "border": "none", "color": "#f14668", "cursor": "pointer", "padding": "2px 6px", "font-size": "18px", "line-height": "1" },
                            title: "Separar esta mesa",
                            onClick: ($event) => _ctx.separarMesaEspecifica(mesaUnida.id)
                          }, _hoisted_53, 8, _hoisted_51)
                        ]);
                      }), 128))
                    ])
                  ])
                ])) : createCommentVNode("", true),
                createBaseVNode("div", _hoisted_54, [
                  createBaseVNode("div", _hoisted_55, [
                    createVNode(_component_VField, null, {
                      default: withCtx(() => [
                        _hoisted_56,
                        createVNode(_component_VControl, { icon: "feather:hash" }, {
                          default: withCtx(() => [
                            withDirectives(createBaseVNode("input", {
                              "onUpdate:modelValue": _cache[4] || (_cache[4] = ($event) => mesaSelected.value.personas = $event),
                              type: "number",
                              class: "input",
                              min: "0",
                              step: "1",
                              placeholder: "",
                              autocomplete: "given-name",
                              onKeydown: _cache[5] || (_cache[5] = ($event) => {
                                ["-", "e", "E"].includes($event.key) && $event.preventDefault();
                              })
                            }, null, 544), [
                              [
                                vModelText,
                                mesaSelected.value.personas,
                                void 0,
                                { number: true }
                              ]
                            ]),
                            _hoisted_57
                          ]),
                          _: 1
                        })
                      ]),
                      _: 1
                    })
                  ]),
                  createBaseVNode("div", _hoisted_58, [
                    createVNode(_component_VField, null, {
                      default: withCtx(() => [
                        _hoisted_59,
                        createVNode(_component_VControl, { icon: "feather:user" }, {
                          default: withCtx(() => [
                            withDirectives(createBaseVNode("input", {
                              "onUpdate:modelValue": _cache[6] || (_cache[6] = ($event) => mesaSelected.value.cliente = $event),
                              type: "text",
                              class: "input",
                              placeholder: "",
                              autocomplete: "family-name"
                            }, null, 512), [
                              [vModelText, mesaSelected.value.cliente]
                            ]),
                            _hoisted_60
                          ]),
                          _: 1
                        })
                      ]),
                      _: 1
                    })
                  ]),
                  createBaseVNode("div", _hoisted_61, [
                    createVNode(_component_VField, null, {
                      default: withCtx(() => [
                        _hoisted_62,
                        createVNode(_component_VControl, { icon: "feather:user-check" }, {
                          default: withCtx(() => [
                            createBaseVNode("div", _hoisted_63, [
                              withDirectives(createBaseVNode("select", {
                                "onUpdate:modelValue": _cache[7] || (_cache[7] = ($event) => mesaSelected.value.waiter = $event)
                              }, [
                                (openBlock(true), createElementBlock(Fragment, null, renderList(unref(companySession).waiters, (item, index) => {
                                  return openBlock(), createElementBlock("option", {
                                    key: index + "Wa",
                                    value: `${item.name}`
                                  }, toDisplayString(`${item.name}`), 9, _hoisted_64);
                                }), 128))
                              ], 512), [
                                [vModelSelect, mesaSelected.value.waiter]
                              ])
                            ]),
                            _hoisted_65
                          ]),
                          _: 1
                        })
                      ]),
                      _: 1
                    })
                  ]),
                  createBaseVNode("div", _hoisted_66, [
                    createVNode(_component_VField, null, {
                      default: withCtx(() => [
                        _hoisted_67,
                        createVNode(_component_VControl, null, {
                          default: withCtx(() => [
                            withDirectives(createBaseVNode("textarea", {
                              "onUpdate:modelValue": _cache[8] || (_cache[8] = ($event) => mesaSelected.value.comentarios = $event),
                              class: "textarea",
                              rows: "4",
                              placeholder: "",
                              autocomplete: "off",
                              autocapitalize: "off",
                              spellcheck: "true"
                            }, null, 512), [
                              [vModelText, mesaSelected.value.comentarios]
                            ])
                          ]),
                          _: 1
                        })
                      ]),
                      _: 1
                    })
                  ]),
                  createBaseVNode("div", _hoisted_68, [
                    createVNode(_component_VButton, {
                      color: "primary",
                      raised: "",
                      class: "is-fullwidth",
                      onClick: openMesa
                    }, {
                      default: withCtx(() => [
                        _hoisted_69
                      ]),
                      _: 1
                    })
                  ])
                ])
              ])) : createCommentVNode("", true),
              mesaSelected.value.id == 0 ? (openBlock(), createElementBlock("div", _hoisted_70, _hoisted_72)) : createCommentVNode("", true)
            ])
          ])
        ])) : createCommentVNode("", true),
        unref(viewPos) ? (openBlock(), createBlock(_component_MesaRestaurantPos, {
          key: 1,
          onViewMesas: viewMesas
        })) : createCommentVNode("", true)
      ], 64);
    };
  }
});
var __unplugin_components_1 = /* @__PURE__ */ _export_sfc(_sfc_main$1, [["__scopeId", "data-v-241f3b28"]]);
const _hoisted_1 = { class: "page-content-inner" };
const _sfc_main = /* @__PURE__ */ defineComponent({
  setup(__props) {
    pageTitle.value = "Mesas";
    useHead({
      title: "Main Mesas"
    });
    return (_ctx, _cache) => {
      const _component_LockedScreen = _sfc_main$9;
      const _component_MesaRestaurant = __unplugin_components_1;
      return openBlock(), createElementBlock("div", _hoisted_1, [
        createVNode(_component_LockedScreen),
        createVNode(_component_MesaRestaurant)
      ]);
    };
  }
});
export { _sfc_main as default };
