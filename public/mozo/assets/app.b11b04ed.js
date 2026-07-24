import _sfc_main$1 from "./AppLayout.a7a5ca1c.js";
import { b as defineComponent, a5 as useRoute, x as resolveComponent, f as openBlock, v as createBlock, B as withCtx, w as createVNode, T as Transition, s as resolveDynamicComponent, Z as unref } from "./vendor.dca42141.js";
import "./VButton.2bd31a3c.js";
import "./plugin-vue_export-helper.5a098b48.js";
import "./VIconButton.03fee79f.js";
import "./IsotipoMozoOficial.aa231484.js";
import "./VAvatar.4eca5934.js";
import "./VControl.ab20f615.js";
import "./VField.547aede3.js";
import "./VModal.fa3cd151.js";
import "./index.8c6daf4a.js";
import "./VDropdown.30a2a102.js";
import "./VIcon.394dd7c3.js";
import "./masterService.282e9ea7.js";
import "./navbarLayoutState.af10f214.js";
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
