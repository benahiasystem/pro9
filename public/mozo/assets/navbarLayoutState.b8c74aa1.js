import { b as brandName, _ as __vitePreload } from "./index.4a46f1be.js";
import { ao as defineAsyncComponent, r as ref, a as computed } from "./vendor.0611facb.js";
const NavbarLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.55b8b567.js"), true ? ["assets/AppLayout.55b8b567.js","assets/AppLayout.8401e9d1.css","assets/VButton.fbae0555.js","assets/VButton.e28c104e.css","assets/vendor.0611facb.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.856daf8c.js","assets/IsotipoMozoOficial.b3dc484a.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.916bc669.js","assets/VControl.3ac22e58.js","assets/VControl.243637c8.css","assets/VField.0b883ad8.js","assets/VModal.d2e0a5eb.js","assets/VModal.d8de09e0.css","assets/index.4a46f1be.js","assets/index.0c806c15.css","assets/VDropdown.d0ee03b8.js","assets/VDropdown.0f83e5f1.css","assets/VIcon.90bdc252.js","assets/masterService.c3eb1ff6.js","assets/realtime.f5941764.js"] : void 0));
const NavbarDropdownLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.55b8b567.js"), true ? ["assets/AppLayout.55b8b567.js","assets/AppLayout.8401e9d1.css","assets/VButton.fbae0555.js","assets/VButton.e28c104e.css","assets/vendor.0611facb.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.856daf8c.js","assets/IsotipoMozoOficial.b3dc484a.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.916bc669.js","assets/VControl.3ac22e58.js","assets/VControl.243637c8.css","assets/VField.0b883ad8.js","assets/VModal.d2e0a5eb.js","assets/VModal.d8de09e0.css","assets/index.4a46f1be.js","assets/index.0c806c15.css","assets/VDropdown.d0ee03b8.js","assets/VDropdown.0f83e5f1.css","assets/VIcon.90bdc252.js","assets/masterService.c3eb1ff6.js","assets/realtime.f5941764.js"] : void 0));
const NavbarSearchLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.55b8b567.js"), true ? ["assets/AppLayout.55b8b567.js","assets/AppLayout.8401e9d1.css","assets/VButton.fbae0555.js","assets/VButton.e28c104e.css","assets/vendor.0611facb.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.856daf8c.js","assets/IsotipoMozoOficial.b3dc484a.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.916bc669.js","assets/VControl.3ac22e58.js","assets/VControl.243637c8.css","assets/VField.0b883ad8.js","assets/VModal.d2e0a5eb.js","assets/VModal.d8de09e0.css","assets/index.4a46f1be.js","assets/index.0c806c15.css","assets/VDropdown.d0ee03b8.js","assets/VDropdown.0f83e5f1.css","assets/VIcon.90bdc252.js","assets/masterService.c3eb1ff6.js","assets/realtime.f5941764.js"] : void 0));
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
