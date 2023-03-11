import Utmbar from "./components/fields/Utmbar.vue";

panel.plugin('bnomei/utm', {
  fields: {
    'utmbar': Utmbar,
  },
  icons: {
    "utm-bars": '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><g fill="currentColor" class="nc-icon-wrapper"><rect width="6" height="30" x="12" y="14" data-color="color-2" rx="1" ry="1"/><rect width="6" height="20" x="21" y="24" rx="1" ry="1"/><rect width="6" height="30" x="30" y="14" data-color="color-2" rx="1" ry="1"/><rect width="6" height="20" x="39" y="24" rx="1" ry="1"/><rect width="6" height="40" x="3" y="4" rx="1" ry="1"/></g></svg>',
  },
});
