import _sfc_main$1 from "./AppLayout.51e68d7d.js";
import { b as defineComponent, a5 as useRoute, x as resolveComponent, f as openBlock, v as createBlock, B as withCtx, w as createVNode, T as Transition, s as resolveDynamicComponent, Z as unref } from "./vendor.e5526731.js";
import "./VButton.e2c6ff64.js";
import "./plugin-vue_export-helper.5a098b48.js";
import "./VIconButton.f30fb8fa.js";
import "./index.b16d6934.js";
import "./IsotipoMozoOficial.006c1e5c.js";
import "./VModal.c5087f3a.js";
import "./VControl.f084cf9c.js";
import "./VField.2d5f60f9.js";
import "./masterService.bda2fa25.js";
import "./VDropdown.e88bb20d.js";
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
