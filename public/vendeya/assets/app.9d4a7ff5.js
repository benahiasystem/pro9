import _sfc_main$1 from "./AppLayout.6cc926a9.js";
import { b as defineComponent, a5 as useRoute, x as resolveComponent, f as openBlock, v as createBlock, B as withCtx, w as createVNode, T as Transition, s as resolveDynamicComponent, Z as unref } from "./vendor.d8ffc872.js";
import "./VButton.610c0188.js";
import "./plugin-vue_export-helper.5a098b48.js";
import "./VIconButton.66516309.js";
import "./index.f88a26e5.js";
import "./IsotipoMozoOficial.680f3456.js";
import "./VModal.7def1e55.js";
import "./VControl.78b98364.js";
import "./VField.4fa7381e.js";
import "./VDropdown.fafac732.js";
import "./masterService.1f94f618.js";
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
