import _sfc_main$1 from "./AppLayout.e5e5a9ce.js";
import { b as defineComponent, a5 as useRoute, x as resolveComponent, f as openBlock, v as createBlock, B as withCtx, w as createVNode, T as Transition, s as resolveDynamicComponent, Z as unref } from "./vendor.b09305a7.js";
import "./VButton.fc4cbeca.js";
import "./plugin-vue_export-helper.5a098b48.js";
import "./VIconButton.18510a53.js";
import "./index.15d44122.js";
import "./IsotipoMozoOficial.6fcf915b.js";
import "./VModal.ff5b02d6.js";
import "./VControl.bc7d1930.js";
import "./VField.6fe855fb.js";
import "./VDropdown.6720ff68.js";
import "./masterService.30f5ebd8.js";
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
