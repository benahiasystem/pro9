import _sfc_main$1 from "./AppLayout.79625695.js";
import { b as defineComponent, a5 as useRoute, x as resolveComponent, f as openBlock, v as createBlock, B as withCtx, w as createVNode, T as Transition, s as resolveDynamicComponent, Z as unref } from "./vendor.73f133b9.js";
import "./VButton.0d870fba.js";
import "./plugin-vue_export-helper.5a098b48.js";
import "./VIconButton.8ad05465.js";
import "./index.3bb13d9e.js";
import "./IsotipoMozoOficial.521b98ca.js";
import "./VModal.faedfed7.js";
import "./VControl.8f7a9833.js";
import "./VField.cf44fb41.js";
import "./VDropdown.00cd1170.js";
import "./masterService.fa09b494.js";
var block0 = {};
const _sfc_main = /* @__PURE__ */ defineComponent({
  setup(__props) {
    const route = useRoute();
    return (_ctx, _cache) => {
      const _component_RouterView = resolveComponent("RouterView");
      const _component_AppLayout = _sfc_main$1;
      return openBlock(), createBlock(_component_AppLayout, null, {
        default: withCtx(() => [
          createVNode(_component_RouterView, null, {
            default: withCtx(({ Component }) => [
              createVNode(Transition, {
                name: "fade-fast",
                mode: "out-in"
              }, {
                default: withCtx(() => [
                  (openBlock(), createBlock(resolveDynamicComponent(Component), {
                    key: unref(route).fullPath
                  }))
                ]),
                _: 2
              }, 1024)
            ]),
            _: 1
          })
        ]),
        _: 1
      });
    };
  }
});
if (typeof block0 === "function")
  block0(_sfc_main);
export { _sfc_main as default };
