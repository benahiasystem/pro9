import { b as brandName, _ as __vitePreload } from "./index.9ad3bf19.js";
import { ao as defineAsyncComponent, r as ref, a as computed } from "./vendor.f54a8f4c.js";
const NavbarLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.644482c4.js"), true ? ["assets/AppLayout.644482c4.js","assets/AppLayout.803edda9.css","assets/VButton.2be3e296.js","assets/VButton.e28c104e.css","assets/vendor.f54a8f4c.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.043495e6.js","assets/IsotipoMozoOficial.6b66971c.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.8a46791f.js","assets/VControl.5ab3a926.js","assets/VControl.243637c8.css","assets/VField.04345baa.js","assets/VModal.95874e8d.js","assets/VModal.d8de09e0.css","assets/index.9ad3bf19.js","assets/index.da2595e0.css","assets/VDropdown.612abd01.js","assets/VDropdown.0f83e5f1.css","assets/VIcon.3f8eb681.js","assets/masterService.18c6d5eb.js","assets/realtime.ae8fa929.js"] : void 0));
const NavbarDropdownLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.644482c4.js"), true ? ["assets/AppLayout.644482c4.js","assets/AppLayout.803edda9.css","assets/VButton.2be3e296.js","assets/VButton.e28c104e.css","assets/vendor.f54a8f4c.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.043495e6.js","assets/IsotipoMozoOficial.6b66971c.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.8a46791f.js","assets/VControl.5ab3a926.js","assets/VControl.243637c8.css","assets/VField.04345baa.js","assets/VModal.95874e8d.js","assets/VModal.d8de09e0.css","assets/index.9ad3bf19.js","assets/index.da2595e0.css","assets/VDropdown.612abd01.js","assets/VDropdown.0f83e5f1.css","assets/VIcon.3f8eb681.js","assets/masterService.18c6d5eb.js","assets/realtime.ae8fa929.js"] : void 0));
const NavbarSearchLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.644482c4.js"), true ? ["assets/AppLayout.644482c4.js","assets/AppLayout.803edda9.css","assets/VButton.2be3e296.js","assets/VButton.e28c104e.css","assets/vendor.f54a8f4c.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.043495e6.js","assets/IsotipoMozoOficial.6b66971c.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.8a46791f.js","assets/VControl.5ab3a926.js","assets/VControl.243637c8.css","assets/VField.04345baa.js","assets/VModal.95874e8d.js","assets/VModal.d8de09e0.css","assets/index.9ad3bf19.js","assets/index.da2595e0.css","assets/VDropdown.612abd01.js","assets/VDropdown.0f83e5f1.css","assets/VIcon.3f8eb681.js","assets/masterService.18c6d5eb.js","assets/realtime.ae8fa929.js"] : void 0));
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
