import _sfc_main$1 from "./AppLayout.644482c4.js";
import { b as defineComponent, a5 as useRoute, x as resolveComponent, f as openBlock, v as createBlock, B as withCtx, w as createVNode, T as Transition, s as resolveDynamicComponent, Z as unref } from "./vendor.f54a8f4c.js";
import "./VButton.2be3e296.js";
import "./plugin-vue_export-helper.5a098b48.js";
import "./VIconButton.043495e6.js";
import "./IsotipoMozoOficial.6b66971c.js";
import "./VAvatar.8a46791f.js";
import "./VControl.5ab3a926.js";
import "./VField.04345baa.js";
import "./VModal.95874e8d.js";
import "./index.9ad3bf19.js";
import "./VDropdown.612abd01.js";
import "./VIcon.3f8eb681.js";
import "./masterService.18c6d5eb.js";
import "./realtime.ae8fa929.js";
import "./navbarLayoutState.e9420b5f.js";
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
