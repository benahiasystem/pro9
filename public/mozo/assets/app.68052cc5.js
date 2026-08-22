import _sfc_main$1 from "./AppLayout.a1b351cc.js";
import { b as defineComponent, a5 as useRoute, x as resolveComponent, f as openBlock, v as createBlock, B as withCtx, w as createVNode, T as Transition, s as resolveDynamicComponent, Z as unref } from "./vendor.0611facb.js";
import "./VButton.fbae0555.js";
import "./plugin-vue_export-helper.5a098b48.js";
import "./VIconButton.856daf8c.js";
import "./IsotipoMozoOficial.b3dc484a.js";
import "./VAvatar.916bc669.js";
import "./VControl.3ac22e58.js";
import "./VField.0b883ad8.js";
import "./VModal.d2e0a5eb.js";
import "./index.5f23ec3b.js";
import "./VDropdown.d0ee03b8.js";
import "./VIcon.90bdc252.js";
import "./masterService.3daa761b.js";
import "./realtime.05a535a8.js";
import "./navbarLayoutState.f4be4684.js";
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
