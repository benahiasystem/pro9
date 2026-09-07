import { b as brandName, _ as __vitePreload } from "./index.de978ae1.js";
import { ao as defineAsyncComponent, r as ref, a as computed } from "./vendor.3c361039.js";
const NavbarLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.bc78f6dd.js"), true ? ["assets/AppLayout.bc78f6dd.js","assets/AppLayout.a694544e.css","assets/VButton.03b0164c.js","assets/VButton.4bd674d0.css","assets/vendor.3c361039.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.53531b66.js","assets/IsotipoMozoOficial.a038b280.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.392df83f.js","assets/VControl.c40603f4.js","assets/VControl.66ed690d.css","assets/VField.06ac9a93.js","assets/VModal.634efbbb.js","assets/VModal.d8de09e0.css","assets/index.de978ae1.js","assets/index.94ca4d67.css","assets/VDropdown.76cd0ffc.js","assets/VDropdown.6c1d270c.css","assets/VIcon.85ce7ff3.js","assets/masterService.f5028f32.js","assets/realtime.17f1e397.js"] : void 0));
const NavbarDropdownLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.bc78f6dd.js"), true ? ["assets/AppLayout.bc78f6dd.js","assets/AppLayout.a694544e.css","assets/VButton.03b0164c.js","assets/VButton.4bd674d0.css","assets/vendor.3c361039.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.53531b66.js","assets/IsotipoMozoOficial.a038b280.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.392df83f.js","assets/VControl.c40603f4.js","assets/VControl.66ed690d.css","assets/VField.06ac9a93.js","assets/VModal.634efbbb.js","assets/VModal.d8de09e0.css","assets/index.de978ae1.js","assets/index.94ca4d67.css","assets/VDropdown.76cd0ffc.js","assets/VDropdown.6c1d270c.css","assets/VIcon.85ce7ff3.js","assets/masterService.f5028f32.js","assets/realtime.17f1e397.js"] : void 0));
const NavbarSearchLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.bc78f6dd.js"), true ? ["assets/AppLayout.bc78f6dd.js","assets/AppLayout.a694544e.css","assets/VButton.03b0164c.js","assets/VButton.4bd674d0.css","assets/vendor.3c361039.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.53531b66.js","assets/IsotipoMozoOficial.a038b280.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.392df83f.js","assets/VControl.c40603f4.js","assets/VControl.66ed690d.css","assets/VField.06ac9a93.js","assets/VModal.634efbbb.js","assets/VModal.d8de09e0.css","assets/index.de978ae1.js","assets/index.94ca4d67.css","assets/VDropdown.76cd0ffc.js","assets/VDropdown.6c1d270c.css","assets/VIcon.85ce7ff3.js","assets/masterService.f5028f32.js","assets/realtime.17f1e397.js"] : void 0));
const layoutsComponents = {
  "navbar-default": NavbarLayout,
  "navbar-fade": NavbarLayout,
  "navbar-colored": NavbarLayout,
  "navbar-dropdown": NavbarDropdownLayout,
  "navbar-dropdown-colored": NavbarDropdownLayout,
  "navbar-clean": NavbarSearchLayout,
  "navbar-clean-center": NavbarSearchLayout,
  "navbar-clean-fade": NavbarSearchLayout
};
const navbarLayoutId = ref("navbar-default");
computed(() => {
  return layoutsComponents[navbarLayoutId.value] || NavbarLayout;
});
computed(() => {
  switch (navbarLayoutId.value) {
    case "navbar-fade":
    case "navbar-clean-fade":
      return "fade";
    case "navbar-colored":
    case "navbar-dropdown-colored":
      return "colored";
    case "navbar-clean-center":
      return "center";
    default:
      return "default";
  }
});
const pageTitle = computed(() => brandName.value);
export { pageTitle as p };
