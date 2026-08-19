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
import { b as defineComponent, f as openBlock, g as createElementBlock, y as renderSlot, Y as normalizeClass, d as defineStore, u as useStorage, a as computed, an as defineAsyncComponent, r as ref, q as h, x as resolveComponent } from "./vendor.73f133b9.js";
import { b as brandName, _ as __vitePreload } from "./index.dd916fd1.js";
const _sfc_main$1 = /* @__PURE__ */ defineComponent({
  props: {
    align: { type: String, required: false, default: void 0 },
    addons: { type: Boolean, required: false }
  },
  setup(__props) {
    const props = __props;
    return (_ctx, _cache) => {
      return openBlock(), createElementBlock("div", {
        class: normalizeClass(["buttons", [props.addons && "has-addons", props.align && `is-${props.align}`]])
      }, [
        renderSlot(_ctx.$slots, "default")
      ], 2);
    };
  }
});
const ALL_CATEGORY_ID = 0;
const CATEGORY_TONES = 6;
const CATEGORY_VIEW_DEFAULT = {
  mode: "list",
  colorStyle: "accent",
  size: "medium",
  autoColors: true,
  showAll: true
};
const CATEGORY_MODE_OPTIONS = [
  {
    id: "list",
    icon: "feather:list",
    name: "Lista",
    description: "Fila con scroll"
  },
  {
    id: "grid",
    icon: "feather:grid",
    name: "Cuadr\xEDcula",
    description: "Botones de color"
  }
];
const CATEGORY_COLOR_STYLE_OPTIONS = [
  {
    id: "accent",
    name: "Acento sutil",
    description: "Blanco con punto de color, combina con el dise\xF1o actual"
  },
  {
    id: "pastel",
    name: "Pasteles",
    description: "Fondos suaves con texto oscuro"
  },
  {
    id: "vivid",
    name: "Vivos",
    description: "Colores intensos, alta visibilidad t\xE1ctil"
  }
];
const CATEGORY_SIZE_OPTIONS = [
  { id: "compact", name: "Compacto" },
  { id: "medium", name: "Mediano" },
  { id: "large", name: "Grande" }
];
const CATEGORY_PILL_SIZE_CLASS = {
  compact: "is-normal",
  medium: "is-medium",
  large: "is-large"
};
const useCategoryViewSession = defineStore("categoryViewSession", () => {
  const config = useStorage("categoryView", __spreadValues({}, CATEGORY_VIEW_DEFAULT));
  config.value = __spreadValues(__spreadValues({}, CATEGORY_VIEW_DEFAULT), config.value);
  const isGrid = computed(() => config.value.mode === "grid");
  const showDots = computed(() => isGrid.value && config.value.autoColors && config.value.colorStyle === "accent");
  const containerClass = computed(() => [
    `is-cat-size-${config.value.size}`,
    `is-cat-style-${config.value.colorStyle}`
  ]);
  const pillSizeClass = computed(() => CATEGORY_PILL_SIZE_CLASS[config.value.size]);
  function toneClass(index) {
    if (!config.value.autoColors)
      return "";
    return `is-cat-tone-${index % CATEGORY_TONES + 1}`;
  }
  function set(key, value) {
    config.value[key] = value;
  }
  function reset() {
    config.value = __spreadValues({}, CATEGORY_VIEW_DEFAULT);
  }
  return {
    config,
    isGrid,
    showDots,
    containerClass,
    pillSizeClass,
    toneClass,
    set,
    reset
  };
});
const NavbarLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.2dc61bcd.js"), true ? ["assets/AppLayout.2dc61bcd.js","assets/AppLayout.86d92d54.css","assets/VButton.0d870fba.js","assets/VButton.e28c104e.css","assets/vendor.73f133b9.js","assets/plugin-vue_export-helper.5a098b48.js","assets/IsotipoMozoOficial.521b98ca.js","assets/IsotipoMozoOficial.a97af8de.css","assets/VModal.faedfed7.js","assets/VModal.d8de09e0.css","assets/VControl.8f7a9833.js","assets/VControl.243637c8.css","assets/VField.cf44fb41.js","assets/index.dd916fd1.js","assets/index.0d1fe543.css","assets/masterService.c18f9f8c.js","assets/VDropdown.d3816d35.js","assets/VDropdown.79a9bddc.css"] : void 0));
const NavbarDropdownLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.2dc61bcd.js"), true ? ["assets/AppLayout.2dc61bcd.js","assets/AppLayout.86d92d54.css","assets/VButton.0d870fba.js","assets/VButton.e28c104e.css","assets/vendor.73f133b9.js","assets/plugin-vue_export-helper.5a098b48.js","assets/IsotipoMozoOficial.521b98ca.js","assets/IsotipoMozoOficial.a97af8de.css","assets/VModal.faedfed7.js","assets/VModal.d8de09e0.css","assets/VControl.8f7a9833.js","assets/VControl.243637c8.css","assets/VField.cf44fb41.js","assets/index.dd916fd1.js","assets/index.0d1fe543.css","assets/masterService.c18f9f8c.js","assets/VDropdown.d3816d35.js","assets/VDropdown.79a9bddc.css"] : void 0));
const NavbarSearchLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.2dc61bcd.js"), true ? ["assets/AppLayout.2dc61bcd.js","assets/AppLayout.86d92d54.css","assets/VButton.0d870fba.js","assets/VButton.e28c104e.css","assets/vendor.73f133b9.js","assets/plugin-vue_export-helper.5a098b48.js","assets/IsotipoMozoOficial.521b98ca.js","assets/IsotipoMozoOficial.a97af8de.css","assets/VModal.faedfed7.js","assets/VModal.d8de09e0.css","assets/VControl.8f7a9833.js","assets/VControl.243637c8.css","assets/VField.cf44fb41.js","assets/index.dd916fd1.js","assets/index.0d1fe543.css","assets/masterService.c18f9f8c.js","assets/VDropdown.d3816d35.js","assets/VDropdown.79a9bddc.css"] : void 0));
const layoutsComponents = {
  "navbar-default": NavbarLayout,
  "navbar-fade": NavbarLayout,
  "navbar-colored": NavbarLayout,
  "navbar-dropdown": NavbarDropdownLayout,
  "navbar-dropdown-colored": NavbarDropdownLayout,
  "navbar-clean": NavbarSearchLayout,
  "navbar-clean-center": NavbarSearchLayout,
  "navbar-clean-fade": NavbarSearchLayout
};
const navbarLayoutId = ref("navbar-default");
computed(() => {
  return layoutsComponents[navbarLayoutId.value] || NavbarLayout;
});
computed(() => {
  switch (navbarLayoutId.value) {
    case "navbar-fade":
    case "navbar-clean-fade":
      return "fade";
    case "navbar-colored":
    case "navbar-dropdown-colored":
      return "colored";
    case "navbar-clean-center":
      return "center";
    default:
      return "default";
  }
});
const pageTitle = computed(() => brandName.value);
const _sfc_main = defineComponent({
  props: {
    icon: {
      type: String,
      required: true
    },
    to: {
      type: Object,
      default: void 0
    },
    href: {
      type: String,
      default: void 0
    },
    color: {
      type: String,
      default: void 0,
      validator: (value) => {
        if ([
          void 0,
          "primary",
          "info",
          "success",
          "warning",
          "danger",
          "white"
        ].indexOf(value) === -1) {
          console.warn(`VIconButton: invalid "${value}" color. Should be primary, info, success, warning, danger, white or undefined`);
          return false;
        }
        return true;
      }
    },
    dark: {
      type: String,
      default: void 0,
      validator: (value) => {
        if (!value)
          return true;
        if (["1", "2", "3", "4", "5", "6"].indexOf(value) === -1) {
          console.warn(`VIconButton: invalid "${value}" dark. Should be 1, 2, 3, 4, 5, 6 or undefined`);
          return false;
        }
        return true;
      }
    },
    circle: {
      type: Boolean,
      default: false
    },
    bold: {
      type: Boolean,
      default: false
    },
    light: {
      type: Boolean,
      default: false
    },
    raised: {
      type: Boolean,
      default: false
    },
    elevated: {
      type: Boolean,
      default: false
    },
    outlined: {
      type: Boolean,
      default: false
    },
    darkOutlined: {
      type: Boolean,
      default: false
    },
    loading: {
      type: Boolean,
      default: false
    },
    disabled: {
      type: Boolean,
      default: false
    }
  },
  setup(props, { attrs }) {
    const classes = computed(() => {
      const defaultClasses = (attrs == null ? void 0 : attrs.class) || [];
      return [
        ...defaultClasses,
        props.disabled && "is-disabled",
        props.circle && "is-circle",
        props.bold && "is-bold",
        props.outlined && "is-outlined",
        props.raised && "is-raised",
        props.dark && `is-dark-bg-${props.dark}`,
        props.darkOutlined && "is-dark-outlined",
        props.elevated && "is-elevated",
        props.loading && "is-loading",
        props.color && `is-${props.color}`,
        props.light && "is-light"
      ];
    });
    const isIconify = computed(() => props.icon && props.icon.indexOf(":") !== -1);
    return () => {
      let icon;
      if (isIconify.value) {
        icon = h("i", {
          "aria-hidden": true,
          class: "iconify",
          "data-icon": props.icon
        });
      } else {
        icon = h("i", { "aria-hidden": true, class: props.icon });
      }
      const iconWrapper = h("span", { class: "icon" }, icon);
      if (props.to) {
        return h(resolveComponent("RouterLink"), __spreadProps(__spreadValues({}, attrs), {
          to: props.to,
          class: ["button", ...classes.value]
        }), iconWrapper);
      } else if (props.href) {
        return h("a", __spreadProps(__spreadValues({}, attrs), {
          href: props.href,
          class: classes.value
        }), iconWrapper);
      }
      return h("button", __spreadProps(__spreadValues({
        type: "button"
      }, attrs), {
        disabled: props.disabled,
        class: ["button", ...classes.value]
      }), iconWrapper);
    };
  }
});
export { ALL_CATEGORY_ID as A, CATEGORY_TONES as C, _sfc_main$1 as _, CATEGORY_MODE_OPTIONS as a, CATEGORY_COLOR_STYLE_OPTIONS as b, CATEGORY_SIZE_OPTIONS as c, _sfc_main as d, pageTitle as p, useCategoryViewSession as u };
