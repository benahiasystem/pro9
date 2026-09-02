import _sfc_main$1 from "./AppLayout.5dbeb306.js";
import { b as defineComponent, a5 as useRoute, x as resolveComponent, f as openBlock, v as createBlock, B as withCtx, w as createVNode, T as Transition, s as resolveDynamicComponent, Z as unref } from "./vendor.4fd38f72.js";
import "./VButton.74b292a0.js";
import "./plugin-vue_export-helper.5a098b48.js";
import "./VIconButton.4c00b77a.js";
import "./index.42a48c3f.js";
import "./IsotipoMozoOficial.a1a7fcdc.js";
import "./VModal.794ccc5c.js";
import "./VControl.c5bb8a1f.js";
import "./VField.29c5f7a2.js";
import "./masterService.9853fa55.js";
import "./VDropdown.3ac27351.js";
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
