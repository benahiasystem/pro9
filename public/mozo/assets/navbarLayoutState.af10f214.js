import { b as brandName, _ as __vitePreload } from "./index.8c6daf4a.js";
import { ap as defineAsyncComponent, r as ref, a as computed } from "./vendor.dca42141.js";
const NavbarLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.a7a5ca1c.js"), true ? ["assets/AppLayout.a7a5ca1c.js","assets/AppLayout.8401e9d1.css","assets/VButton.2bd31a3c.js","assets/VButton.e28c104e.css","assets/vendor.dca42141.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.03fee79f.js","assets/IsotipoMozoOficial.aa231484.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.4eca5934.js","assets/VControl.ab20f615.js","assets/VControl.243637c8.css","assets/VField.547aede3.js","assets/VModal.fa3cd151.js","assets/VModal.d8de09e0.css","assets/index.8c6daf4a.js","assets/index.89180d3a.css","assets/VDropdown.30a2a102.js","assets/VDropdown.0f83e5f1.css","assets/VIcon.394dd7c3.js","assets/masterService.282e9ea7.js"] : void 0));
const NavbarDropdownLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.a7a5ca1c.js"), true ? ["assets/AppLayout.a7a5ca1c.js","assets/AppLayout.8401e9d1.css","assets/VButton.2bd31a3c.js","assets/VButton.e28c104e.css","assets/vendor.dca42141.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.03fee79f.js","assets/IsotipoMozoOficial.aa231484.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.4eca5934.js","assets/VControl.ab20f615.js","assets/VControl.243637c8.css","assets/VField.547aede3.js","assets/VModal.fa3cd151.js","assets/VModal.d8de09e0.css","assets/index.8c6daf4a.js","assets/index.89180d3a.css","assets/VDropdown.30a2a102.js","assets/VDropdown.0f83e5f1.css","assets/VIcon.394dd7c3.js","assets/masterService.282e9ea7.js"] : void 0));
const NavbarSearchLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.a7a5ca1c.js"), true ? ["assets/AppLayout.a7a5ca1c.js","assets/AppLayout.8401e9d1.css","assets/VButton.2bd31a3c.js","assets/VButton.e28c104e.css","assets/vendor.dca42141.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.03fee79f.js","assets/IsotipoMozoOficial.aa231484.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.4eca5934.js","assets/VControl.ab20f615.js","assets/VControl.243637c8.css","assets/VField.547aede3.js","assets/VModal.fa3cd151.js","assets/VModal.d8de09e0.css","assets/index.8c6daf4a.js","assets/index.89180d3a.css","assets/VDropdown.30a2a102.js","assets/VDropdown.0f83e5f1.css","assets/VIcon.394dd7c3.js","assets/masterService.282e9ea7.js"] : void 0));
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
