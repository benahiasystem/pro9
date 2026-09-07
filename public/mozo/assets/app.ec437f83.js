import _sfc_main$1 from "./AppLayout.bc78f6dd.js";
import { b as defineComponent, a5 as useRoute, x as resolveComponent, f as openBlock, v as createBlock, B as withCtx, w as createVNode, T as Transition, s as resolveDynamicComponent, Z as unref } from "./vendor.3c361039.js";
import "./VButton.03b0164c.js";
import "./plugin-vue_export-helper.5a098b48.js";
import "./VIconButton.53531b66.js";
import "./IsotipoMozoOficial.a038b280.js";
import "./VAvatar.392df83f.js";
import "./VControl.c40603f4.js";
import "./VField.06ac9a93.js";
import "./VModal.634efbbb.js";
import "./index.de978ae1.js";
import "./VDropdown.76cd0ffc.js";
import "./VIcon.85ce7ff3.js";
import "./masterService.f5028f32.js";
import "./realtime.17f1e397.js";
import "./navbarLayoutState.4a72f367.js";
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
