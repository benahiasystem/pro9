var __defProp = Object.defineProperty;
var __defProps = Object.defineProperties;
var __getOwnPropDescs = Object.getOwnPropertyDescriptors;
var __getOwnPropSymbols = Object.getOwnPropertySymbols;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __propIsEnum = Object.prototype.propertyIsEnumerable;
var __defNormalProp = (obj, key, value) => key in obj ? __defProp(obj, key, { enumerable: true, configurable: true, writable: true, value }) : obj[key] = value;
var __spreadValues = (a, b) => {
  for (var prop in b || (b = {}))
    if (__hasOwnProp.call(b, prop))
      __defNormalProp(a, prop, b[prop]);
  if (__getOwnPropSymbols)
    for (var prop of __getOwnPropSymbols(b)) {
      if (__propIsEnum.call(b, prop))
        __defNormalProp(a, prop, b[prop]);
    }
  return a;
};
var __spreadProps = (a, b) => __defProps(a, __getOwnPropDescs(b));
import { p as provideApi, u as useUserSession, a as useCompanySession } from "./index.4a46f1be.js";
import { d as defineStore, u as useStorage, r as ref, a as computed } from "./vendor.0611facb.js";
const useProductSession = defineStore("productSession", () => {
  const defaultProductsData = [];
  const products = useStorage("products", defaultProductsData);
  const stockMap = ref({});
  const defaultCategoriesData = [
    {
      id: 0,
      name: "Todos",
      selected: true
    }
  ];
  const categories = useStorage("categories", defaultCategoriesData);
  function setProducts(data) {
    products.value = data;
  }
  function setCategories(data) {
    categories.value = data;
    categories.value.unshift({
      id: 0,
      name: "Todos",
      selected: true
    });
  }
  const getProducts = () => {
    return products.value;
  };
  function updateProductsStock(stockData) {
    stockData.forEach((stockItem) => {
      stockMap.value[stockItem.item_id] = parseFloat(stockItem.available);
    });
  }
  function getAvailable(itemId) {
    return stockMap.value[itemId];
  }
  return {
    products,
    stockMap,
    setProducts,
    categories,
    setCategories,
    getProducts,
    updateProductsStock,
    getAvailable
  };
});
const STATUS_NEW = {
  description: "Nuevo",
  id: 0
};
const COMMAND_BAR = {
  code: "bar",
  name: "Bar"
};
const COMMAND_KITCHEN = {
  code: "kitchen",
  name: "Cocina"
};
const MESA_DISPONIBLE = "available";
const MESA_NO_DISPONIBLE = "notavailable";
const MESA_SHAPES_SQUARE = "CUADRADO";
const MESA_SHAPES_CIRCLE = "C\xCDRCULO";
const MESA_SHAPES = [MESA_SHAPES_SQUARE, MESA_SHAPES_CIRCLE];
const DEFAULT_ENVIRONMENT = "Ambiente 1";
const useMesaSession = defineStore("mesaSession", () => {
  const defaultData = [];
  const defaultEnvironments = [
    {
      name: DEFAULT_ENVIRONMENT,
      active: true,
      tablesQuantity: 5,
      is_delivery: false,
      is_takeaway: false
    }
  ];
  const deliveryCounter = useStorage("deliveryTakeawayCounter", 0);
  const closingMesaIds = ref([]);
  function startClosingMesa(id) {
    if (!id)
      return;
    if (!closingMesaIds.value.includes(id)) {
      closingMesaIds.value.push(id);
    }
  }
  function finishClosingMesa(id) {
    if (!id)
      return;
    closingMesaIds.value = closingMesaIds.value.filter((x) => x !== id);
  }
  function isMesaClosing(id) {
    return closingMesaIds.value.includes(id);
  }
  function setDataMesas(data) {
    setMesas(data);
  }
  const environments = useStorage("environmentsMesa", defaultEnvironments);
  function setEnvironmentsMesas(envs) {
    if (!envs || envs.length === 0)
      return;
    envs.forEach((env, index) => {
      if (!environments.value[index]) {
        environments.value[index] = {
          name: env.name || `Ambiente ${index + 1}`,
          active: !!env.active,
          tablesQuantity: Number(env.tablesQuantity) || 0,
          is_delivery: !!env.is_delivery,
          is_takeaway: !!env.is_takeaway
        };
      } else {
        environments.value[index].active = !!env.active;
        environments.value[index].tablesQuantity = Number(env.tablesQuantity) || 0;
        environments.value[index].name = env.name || environments.value[index].name;
        environments.value[index].is_delivery = !!env.is_delivery;
        environments.value[index].is_takeaway = !!env.is_takeaway;
      }
    });
    if (envs.length < environments.value.length) {
      environments.value = environments.value.slice(0, envs.length);
    }
  }
  const mesas = useStorage("mesas", defaultData);
  const mesaSelectedDefaultData = {
    id: 0,
    status: "undefined",
    products: [],
    total: 0,
    personas: 0,
    label: "undefined",
    shape: "undefined",
    environment: DEFAULT_ENVIRONMENT,
    waiter: "",
    comentarios: "",
    quantityOrders: 0,
    open: false,
    close: false,
    is_venta_por_consumo: false,
    order_status: "pending"
  };
  const mesaSelectedRef = ref(mesaSelectedDefaultData);
  function setMesas(data) {
    mesas.value = [];
    mesas.value = data;
  }
  const getMesas = computed(() => mesas.value);
  const getMesas2 = () => {
    return mesas.value;
  };
  async function setAvailableMesa() {
    const index = mesas.value.findIndex((x) => x.id == mesaSelectedRef.value.id);
    mesas.value[index].status = MESA_DISPONIBLE;
    mesas.value[index].products = [];
    mesas.value[index].personas = 0;
    mesas.value[index].cliente = "";
    mesas.value[index].comentarios = "";
    mesas.value[index].waiter = "";
    mesas.value[index].open = false;
    mesas.value[index].close = true;
    mesas.value[index].divisionAccounts = [];
    mesas.value[index].is_venta_por_consumo = false;
    mesas.value[index].order_status = "pending";
    await MesaService.saveMesa(mesas.value[index]);
  }
  async function setServerMesa(id, served) {
    const index = mesas.value.findIndex((mesa) => mesa.id == id);
    if (served) {
      mesas.value[index].order_status = "served";
    } else {
      mesas.value[index].order_status = "pending";
    }
    await MesaService.saveMesa(mesas.value[index]);
  }
  async function setShippedMesa(id) {
    const index = mesas.value.findIndex((mesa) => mesa.id == id);
    if (index !== -1) {
      mesas.value[index].order_status = "shipped";
      await MesaService.saveMesa(mesas.value[index]);
    }
  }
  async function setDeliveredMesa(id) {
    const index = mesas.value.findIndex((mesa) => mesa.id == id);
    if (index !== -1) {
      mesas.value[index].order_status = "delivered";
      await MesaService.saveMesa(mesas.value[index]);
    }
  }
  async function setPreCuentaMesa(id, precuenta) {
    const index = mesas.value.findIndex((mesa) => mesa.id == id);
    if (precuenta) {
      mesas.value[index].order_status = "precuenta";
    }
    await MesaService.saveMesa(mesas.value[index]);
  }
  function setProductsMesaOpened(id, products) {
    const index = mesas.value.findIndex((x) => x.id == id);
    mesas.value[index].products = products;
  }
  function setFormLabelMesa(id, label, shape, environment) {
    const index = mesas.value.findIndex((x) => x.id == id);
    mesas.value[index].label = label;
    mesas.value[index].shape = shape;
    mesas.value[index].environment = environment;
  }
  function getMesasNotAvailable() {
    return mesas.value.filter((element) => element.status === MESA_NO_DISPONIBLE);
  }
  function setMesaSelected(id, customer = null) {
    if (id === 0) {
      mesaSelectedRef.value = __spreadValues({}, mesaSelectedDefaultData);
      return;
    }
    const mesa = mesas.value.find((x) => x.id == id);
    if (mesa) {
      mesaSelectedRef.value = mesa;
      if (customer)
        mesaSelectedRef.value.cliente = customer;
      console.log("Mesa seleccionada:", mesaSelectedRef.value);
      console.log("Comentarios:", mesaSelectedRef.value.comentarios);
    } else {
      mesaSelectedRef.value = __spreadValues({}, mesaSelectedDefaultData);
    }
  }
  function getMesaSelected() {
    return mesaSelectedRef.value;
  }
  async function openMesa(mesa) {
    const index = mesas.value.findIndex((x) => x.id == mesa.id);
    if (index >= 0) {
      mesas.value[index].status = MESA_NO_DISPONIBLE;
      mesas.value[index].personas = mesa.personas;
      mesas.value[index].cliente = mesa.cliente;
      mesas.value[index].waiter = mesa.waiter;
      mesas.value[index].comentarios = mesa.comentarios;
      mesas.value[index].open = true;
      mesas.value[index].close = false;
      mesas.value[index].is_venta_por_consumo = false;
      await MesaService.saveMesa(mesas.value[index]);
    }
  }
  async function calculaTotalBag() {
    let total = 0;
    let totalDiscount = 0;
    mesaSelectedRef.value.products.forEach((x) => {
      var _a;
      const lineTotal = Number(x.price) * x.quantity;
      const discount = (_a = x.discount) != null ? _a : 0;
      const discountAmount = discount > 0 ? x.discountType === "amount" ? Math.min(discount, lineTotal) : lineTotal * (discount / 100) : 0;
      totalDiscount += discountAmount;
      total += lineTotal - discountAmount;
    });
    mesaSelectedRef.value.total = total;
    mesaSelectedRef.value.totalDiscount = totalDiscount;
    await MesaService.saveMesa(mesaSelectedRef.value);
  }
  function addProduct(item) {
    const duplicateIndex = mesaSelectedRef.value.products.findIndex((x) => {
      if (item.modifiersSignature) {
        return x.id == item.id && x.modifiersSignature === item.modifiersSignature;
      } else {
        return x.id == item.id && !x.modifiersSignature;
      }
    });
    if (duplicateIndex >= 0) {
      mesaSelectedRef.value.products[duplicateIndex].quantity += 1;
      mesaSelectedRef.value.products[duplicateIndex].quantity_pending += 1;
    } else {
      const add = __spreadProps(__spreadValues({}, item), { quantity: 1, quantity_pending: 1 });
      mesaSelectedRef.value.products.push(add);
    }
    calculaTotalBag();
  }
  function removeProduct(index) {
    mesaSelectedRef.value.products.splice(index, 1);
    calculaTotalBag();
  }
  function changeQuantityToProduct(itemId, operation) {
    const itemIndex = mesaSelectedRef.value.products.findIndex((x) => x.id == itemId);
    if (itemIndex >= 0) {
      if (operation) {
        mesaSelectedRef.value.products[itemIndex].quantity += 1;
        mesaSelectedRef.value.products[itemIndex].quantity_pending += 1;
      } else {
        if (mesaSelectedRef.value.products[itemIndex].quantity > 1) {
          mesaSelectedRef.value.products[itemIndex].quantity -= 1;
          mesaSelectedRef.value.products[itemIndex].quantity_pending -= 1;
        }
      }
    }
    calculaTotalBag();
  }
  async function addNoteToProduct(itemId, note) {
    const itemIndex = mesaSelectedRef.value.products.findIndex((x) => x.id == itemId);
    if (itemIndex >= 0) {
      mesaSelectedRef.value.products[itemIndex].note = note;
    }
    await MesaService.saveMesa(mesaSelectedRef.value);
  }
  async function updateProductDiscount(itemId, value, type) {
    const itemIndex = mesaSelectedRef.value.products.findIndex((x) => x.id == itemId);
    if (itemIndex >= 0) {
      mesaSelectedRef.value.products[itemIndex].discount = value;
      mesaSelectedRef.value.products[itemIndex].discountType = type;
    }
    await calculaTotalBag();
  }
  function getProductsMesa() {
    return mesaSelectedRef.value.products;
  }
  function updateMesa(data) {
    const index = mesas.value.findIndex((x) => x.id == data.id);
    if (index !== -1) {
      mesas.value[index] = data;
      mesas.value = [...mesas.value];
      console.log("updateMesa session - forzado reactivity para localStorage");
    }
  }
  const mesaSelected = computed(() => mesaSelectedRef.value);
  function getNextDeliveryLabel() {
    const counter = deliveryCounter.value;
    return counter.toString().padStart(3, "0");
  }
  function incrementDeliveryCounter() {
    deliveryCounter.value += 1;
  }
  function resetDeliveryCounter() {
    deliveryCounter.value = 0;
  }
  const hasDeliveryEnvironment = computed(() => {
    return environments.value.some((env) => env.active && env.is_delivery);
  });
  const hasTakeawayEnvironment = computed(() => {
    return environments.value.some((env) => env.active && env.is_takeaway);
  });
  return {
    mesas,
    environments,
    setMesas,
    setAvailableMesa,
    setShippedMesa,
    setDeliveredMesa,
    setProductsMesaOpened,
    setFormLabelMesa,
    getMesasNotAvailable,
    setMesaSelected,
    getMesaSelected,
    getMesas,
    getMesas2,
    mesaSelected,
    openMesa,
    addProduct,
    removeProduct,
    addNoteToProduct,
    getProductsMesa,
    changeQuantityToProduct,
    setDataMesas,
    setEnvironmentsMesas,
    updateMesa,
    calculaTotalBag,
    updateProductDiscount,
    setServerMesa,
    setPreCuentaMesa,
    getNextDeliveryLabel,
    incrementDeliveryCounter,
    resetDeliveryCounter,
    hasDeliveryEnvironment,
    hasTakeawayEnvironment,
    closingMesaIds,
    startClosingMesa,
    finishClosingMesa,
    isMesaClosing
  };
});
const syncMesa = async (id) => {
  const mesaSession = useMesaSession();
  const { data } = await provideApi().get(`/restaurant/table/${id}`);
  mesaSession.updateMesa(data.table);
};
const saveMesa = async (payload) => {
  try {
    const { data } = await provideApi().post(`/restaurant/table/${payload.id}`, payload);
    return data;
  } catch (err) {
    const error = { message: "No se pudo guardar los datos", success: false };
    console.log(error);
  }
};
const syncMesasAndEnvironments = async () => {
  const mesaSession = useMesaSession();
  const { data } = await provideApi().get("/restaurant/tablesAndEnv");
  const { environments, tables } = data;
  mesaSession.setEnvironmentsMesas(environments);
  mesaSession.setDataMesas(tables);
};
const changeTablePedido = async (tableid_origin, tableid_destination) => {
  try {
    const { data } = await provideApi().post("restaurant/order/change-table", {
      tableid_origin,
      tableid_destination
    });
    return data;
  } catch (err) {
    console.error("Error al cambiar mesa:", err);
    return { success: false, message: "Error al cambiar la mesa" };
  }
};
const crearGrupoMesas = async (mesaPrincipalId) => {
  try {
    const { data } = await provideApi().post("/restaurant/tables/group/create", {
      main_table_id: mesaPrincipalId
    });
    console.log("Grupo creado:", data.group);
    return data.group;
  } catch (err) {
    console.error("Error creando grupo de mesas:", err);
    throw err;
  }
};
const agregarMesaAGrupo = async (groupId, mesaId) => {
  try {
    await provideApi().post("/restaurant/tables/group/add", {
      group_id: groupId,
      table_id: mesaId
    });
    console.log(`Mesa ${mesaId} agregada al grupo ${groupId}`);
  } catch (err) {
    console.error("Error agregando mesa al grupo:", err);
    throw err;
  }
};
const separarMesaDeGrupo = async (mesaId) => {
  try {
    await provideApi().post("/restaurant/tables/group/remove", {
      table_id: mesaId
    });
    console.log(`Mesa ${mesaId} separada del grupo`);
  } catch (err) {
    console.error("Error separando mesa del grupo:", err);
    throw err;
  }
};
const disolverGrupo = async (groupId) => {
  try {
    await provideApi().post("/restaurant/tables/group/disband", {
      group_id: groupId
    });
    console.log(`Grupo ${groupId} disuelto`);
  } catch (err) {
    console.error(" Error disolviendo grupo:", err);
    throw err;
  }
};
const recalcularTotalGrupo = async (groupId) => {
  try {
    const { data } = await provideApi().post("/restaurant/tables/group/recalculate", {
      group_id: groupId
    });
    console.log(`Total del grupo ${groupId}: ${data.total}`);
    return data.total;
  } catch (err) {
    console.error("Error recalculando total del grupo:", err);
    throw err;
  }
};
const toggleMesaActive = async (mesaId) => {
  var _a, _b;
  try {
    const { data } = await provideApi().post("/restaurant/table/toggle-active", {
      table_id: mesaId
    });
    console.log(`${data.message}`);
    return data;
  } catch (err) {
    console.error("Error al cambiar estado de mesa:", err);
    if ((_b = (_a = err.response) == null ? void 0 : _a.data) == null ? void 0 : _b.message) {
      throw new Error(err.response.data.message);
    }
    throw new Error("Error al cambiar estado de la mesa");
  }
};
const createMesa = async (payload) => {
  try {
    const { data } = await provideApi().post("/restaurant/table", payload);
    console.log("Mesa creada:", data);
    return data.data;
  } catch (err) {
    console.error("Error creando mesa:", err);
    throw err;
  }
};
const cambiarAmbienteMesa = async (mesaId, nuevoAmbiente) => {
  var _a, _b;
  try {
    const { data } = await provideApi().post("/restaurant/table/cambiar-ambiente", {
      table_id: mesaId,
      nuevo_ambiente: nuevoAmbiente
    });
    console.log(`Mesa ${mesaId} movida a ${nuevoAmbiente}`);
    return data;
  } catch (err) {
    console.error("Error al cambiar ambiente de mesa:", err);
    if ((_b = (_a = err.response) == null ? void 0 : _a.data) == null ? void 0 : _b.message) {
      throw new Error(err.response.data.message);
    }
    throw new Error("Error al cambiar ambiente de la mesa");
  }
};
const restaurarAmbienteMesa = async (mesaId) => {
  var _a, _b;
  try {
    const { data } = await provideApi().post("/restaurant/table/restaurar-ambiente", {
      table_id: mesaId
    });
    console.log(`Mesa ${mesaId} restaurada a su ambiente original`);
    return data;
  } catch (err) {
    console.error("Error al restaurar ambiente de mesa:", err);
    if ((_b = (_a = err.response) == null ? void 0 : _a.data) == null ? void 0 : _b.message) {
      throw new Error(err.response.data.message);
    }
    throw new Error("Error al restaurar ambiente de la mesa");
  }
};
const MesaService = {
  syncMesa,
  saveMesa,
  createMesa,
  syncMesasAndEnvironments,
  crearGrupoMesas,
  agregarMesaAGrupo,
  separarMesaDeGrupo,
  disolverGrupo,
  recalcularTotalGrupo,
  toggleMesaActive,
  changeTablePedido,
  cambiarAmbienteMesa,
  restaurarAmbienteMesa
};
const userSession = useUserSession();
const companySession = useCompanySession();
const productSession = useProductSession();
const saveDataCompany = async () => {
  const { data } = await provideApi().get("/company");
  companySession.setCompany(data.company);
  companySession.setEstablishments(data.establishments);
  companySession.setSeries(data.series);
  companySession.setCustomers(data.customers);
  companySession.savePaymentMethods(data.payment_method_types);
  companySession.savePaymentDestinations(data.payment_destinations);
};
const saveDataProducts = async () => {
  const { data } = await provideApi().get("/restaurant/items");
  const products = data.data;
  const productsStorage = [];
  products.forEach((element) => {
    const item = {
      internalId: element.internal_id,
      barcode: element.barcode,
      id: element.id,
      name: element.description,
      imageUrl: element.image_url,
      price: Number(element.price),
      stock: Number(element.stock),
      currencyTypeSymbol: element.currency_type_symbol,
      quantity: 0,
      categoryId: element.category_id,
      itemCode: element.item_code,
      unitTypeId: element.unit_type_id,
      statusBar: STATUS_NEW.id,
      statusKitchen: STATUS_NEW.id,
      restaurant_favorite: element.restaurant_favorite ? Boolean(element.restaurant_favorite) : false,
      sale_affectation_igv_type_id: element.sale_affectation_igv_type_id,
      quantity_send: 0,
      quantity_pending: 0,
      purchase_unit_price: Number(element.purchase_unit_price),
      area_print: element.area_print,
      has_supplies: element.has_supplies,
      has_sets: element.has_sets,
      items_sets: element.items_sets || [],
      modifiers: element.modifiers || [],
      restaurant_stock: Number(element.restaurant_stock) || 0
    };
    productsStorage.push(item);
  });
  productSession.setProducts(productsStorage);
};
const saveDataCategories = async () => {
  const { data } = await provideApi().get("/restaurant/categories");
  const catagories = data.data;
  const categoriessStorage = catagories.map((row) => {
    return __spreadProps(__spreadValues({}, row), { selected: false });
  });
  productSession.setCategories(categoriessStorage);
};
const saveDataConfiguration = async () => {
  const { data } = await provideApi().get("/restaurant/configurations");
  const configurations = data.data;
  companySession.setConfiguration(configurations);
  if (configurations.printer_name_comanda != null) {
    userSession.setPrinterNameCommand(configurations.printer_name_comanda);
  }
  if (configurations.printer_name_precuenta != null) {
    userSession.setPrinterNamePreOrder(configurations.printer_name_precuenta);
  }
  if (configurations.printer_name_documents != null) {
    userSession.setPrinterNameDocument(configurations.printer_name_documents);
  }
};
const saveWaiters = async () => {
  const { data } = await provideApi().get("/restaurant/waiters");
  companySession.setWaiters(data.data);
};
const getNotes = async () => {
  try {
    const { data } = await provideApi().get("/restaurant/notes");
    return data.records;
  } catch (err) {
    console.error(err);
    return [];
  }
};
const syncData = async () => {
  await saveDataCompany();
  await saveDataProducts();
  await saveDataCategories();
  await saveDataConfiguration();
  await saveWaiters();
  await MesaService.syncMesasAndEnvironments();
};
const syncInitialData = async () => {
  const { data } = await provideApi().get("/restaurant/initial-data");
  if (data.email)
    userSession.setEmail(data.email);
  if (data.name)
    userSession.setName(data.name);
  if (data.establishment_id)
    userSession.setEstablishmentId(data.establishment_id);
  if (data.restaurant_role_code) {
    userSession.setRole(data.restaurant_role_code);
  }
  if (data.sellerId) {
    userSession.setSellerId(data.sellerId);
    userSession.setSellerName(data.name);
  }
  if (data.permission_edit_item_prices !== void 0) {
    userSession.setPermissionEditItemPrices(data.permission_edit_item_prices);
  }
  if (data.company) {
    userSession.setUrlLogo(data.company.url_logo);
    userSession.setLogoBase64(data.company.logo_base64);
  }
  userSession.setIsBlockedPin(0);
  await syncData();
};
const MasterService = {
  syncData,
  syncInitialData,
  saveDataProducts,
  getNotes
};
var masterService = /* @__PURE__ */ Object.freeze({
  __proto__: null,
  [Symbol.toStringTag]: "Module",
  MasterService
});
export { COMMAND_BAR as C, DEFAULT_ENVIRONMENT as D, MasterService as M, STATUS_NEW as S, useProductSession as a, MesaService as b, COMMAND_KITCHEN as c, MESA_NO_DISPONIBLE as d, MESA_DISPONIBLE as e, MESA_SHAPES as f, MESA_SHAPES_SQUARE as g, MESA_SHAPES_CIRCLE as h, masterService as m, useMesaSession as u };
