import { b as defineComponent, e as useHead, f as openBlock, g as createElementBlock } from "./vendor.47bbcd9c.js";
import { p as pageTitle } from "./sidebarLayoutState.ce1f1dd6.js";
const _hoisted_1 = { class: "page-content-inner" };
const _sfc_main = /* @__PURE__ */ defineComponent({
  setup(__props) {
    pageTitle.value = "POS";
    useHead({
      title: "Main POS"
    });
    return (_ctx, _cache) => {
      return openBlock(), createElementBlock("div", _hoisted_1);
    };
  }
});
export { _sfc_main as default };
