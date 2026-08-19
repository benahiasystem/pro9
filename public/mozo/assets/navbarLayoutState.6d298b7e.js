import { b as brandName, _ as __vitePreload } from "./index.02f4974c.js";
import { ao as defineAsyncComponent, r as ref, a as computed } from "./vendor.47bbcd9c.js";
const NavbarLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.fb6c09b1.js"), true ? ["assets/AppLayout.fb6c09b1.js","assets/AppLayout.8401e9d1.css","assets/VButton.a329028a.js","assets/VButton.e28c104e.css","assets/vendor.47bbcd9c.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.c045eea9.js","assets/IsotipoMozoOficial.9e2f6595.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.b01c944b.js","assets/VControl.4be8848d.js","assets/VControl.243637c8.css","assets/VField.d83ee54f.js","assets/VModal.d10f8864.js","assets/VModal.d8de09e0.css","assets/index.02f4974c.js","assets/index.0c806c15.css","assets/VDropdown.aa1f4c97.js","assets/VDropdown.0f83e5f1.css","assets/VIcon.51cd8055.js","assets/masterService.4870541c.js","assets/realtime.4a9fec0b.js"] : void 0));
const NavbarDropdownLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.fb6c09b1.js"), true ? ["assets/AppLayout.fb6c09b1.js","assets/AppLayout.8401e9d1.css","assets/VButton.a329028a.js","assets/VButton.e28c104e.css","assets/vendor.47bbcd9c.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.c045eea9.js","assets/IsotipoMozoOficial.9e2f6595.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.b01c944b.js","assets/VControl.4be8848d.js","assets/VControl.243637c8.css","assets/VField.d83ee54f.js","assets/VModal.d10f8864.js","assets/VModal.d8de09e0.css","assets/index.02f4974c.js","assets/index.0c806c15.css","assets/VDropdown.aa1f4c97.js","assets/VDropdown.0f83e5f1.css","assets/VIcon.51cd8055.js","assets/masterService.4870541c.js","assets/realtime.4a9fec0b.js"] : void 0));
const NavbarSearchLayout = defineAsyncComponent(() => __vitePreload(() => import("./AppLayout.fb6c09b1.js"), true ? ["assets/AppLayout.fb6c09b1.js","assets/AppLayout.8401e9d1.css","assets/VButton.a329028a.js","assets/VButton.e28c104e.css","assets/vendor.47bbcd9c.js","assets/plugin-vue_export-helper.5a098b48.js","assets/VIconButton.c045eea9.js","assets/IsotipoMozoOficial.9e2f6595.js","assets/IsotipoMozoOficial.6fad2d75.css","assets/VAvatar.b01c944b.js","assets/VControl.4be8848d.js","assets/VControl.243637c8.css","assets/VField.d83ee54f.js","assets/VModal.d10f8864.js","assets/VModal.d8de09e0.css","assets/index.02f4974c.js","assets/index.0c806c15.css","assets/VDropdown.aa1f4c97.js","assets/VDropdown.0f83e5f1.css","assets/VIcon.51cd8055.js","assets/masterService.4870541c.js","assets/realtime.4a9fec0b.js"] : void 0));
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
