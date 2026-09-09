<template>
    <div class="pos-table">
        <!-- Barra de ayuda + configuración de columnas -->
        <div class="pos-table__toolbar">
            <div class="pos-table__hints">
                <span class="pos-key">B</span>
                <span class="pos-table__hint-text">buscar</span>
                <span class="pos-key">↑</span><span class="pos-key">↓</span>
                <span class="pos-table__hint-text">navegar</span>
                <span class="pos-key">Enter</span>
                <span class="pos-table__hint-text">agregar</span>
                <span class="pos-key">Esc</span>
                <span class="pos-table__hint-text">limpiar búsqueda</span>

            </div>

            <div class="pos-table__toolbar-actions">
                <button
                    type="button"
                    class="pos-table__toolbar-btn"
                    title="Ver todos los atajos de teclado (F8)"
                    @click="showShortcuts = true"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M2 6m0 2a2 2 0 0 1 2 -2h16a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-16a2 2 0 0 1 -2 -2z" /><path d="M6 10l0 .01" /><path d="M10 10l0 .01" /><path d="M14 10l0 .01" /><path d="M18 10l0 .01" /><path d="M6 14l0 .01" /><path d="M18 14l0 .01" /><path d="M10 14l4 .01" /></svg>
                    Atajos
                </button>

                <el-popover placement="bottom-end" width="210" trigger="click">
                    <div class="pos-table__cols">
                        <p class="pos-table__cols-title">Columnas visibles</p>
                        <el-checkbox v-model="cols.codigo" @change="saveCols">Código</el-checkbox>
                        <el-checkbox v-model="cols.marca" @change="saveCols">Marca</el-checkbox>
                        <el-checkbox v-model="cols.precio" @change="saveCols">Precio</el-checkbox>
                        <el-checkbox v-model="cols.unidad" @change="saveCols">Unidad</el-checkbox>
                        <el-checkbox v-model="cols.pack" @change="saveCols">Pack</el-checkbox>
                        <el-checkbox v-model="cols.stock" @change="saveCols">Stock</el-checkbox>
                        <el-checkbox v-model="cols.lista_precios" @change="saveCols">Lista precios</el-checkbox>
                        <el-checkbox v-model="cols.historial" @change="saveCols">Historial ventas</el-checkbox>
                    </div>
                    <button
                        slot="reference"
                        type="button"
                        class="pos-table__toolbar-btn"
                        title="Elegir qué columnas mostrar"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /></svg>
                        Columnas
                    </button>
                </el-popover>
            </div>
        </div>

        <el-table
            ref="singleTable"
            :data="records"
            highlight-current-row
            :header-cell-class-name="headerFont"
            :row-class-name="stampIndex"
            @current-change="handleCurrentChange"
            @cell-mouse-enter="onRowHover"
            @row-click="onRowClick"
            style="width: 100%"
        >
            <el-table-column type="index" width="50"> </el-table-column>
            <el-table-column property="description" label="Nombre" min-width="180">
            </el-table-column>
            <el-table-column v-if="cols.codigo" property="internal_id" label="Código" width="120">
            </el-table-column>
            <el-table-column v-if="cols.marca" property="brand" label="Marca" width="120">
            </el-table-column>
            <el-table-column v-if="cols.precio" label="Precio" width="110" align="right">
                <template slot-scope="{ row }">
                    {{ row.currency_type_symbol }} {{ formatPrice(row.sale_unit_price) }}
                </template>
            </el-table-column>
            <el-table-column v-if="cols.unidad" property="unit_type_id" label="Unidad" width="90">
            </el-table-column>

            <el-table-column v-if="cols.pack" label="Pack" width="140">
                <template slot-scope="{ row }">
                    <div v-if="packList(row).length" class="pos-table__pack">
                        <span v-for="(set_name, i) in packList(row)" :key="i">{{ set_name }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column v-if="cols.stock" label="Stock">
                <template slot-scope="{ row, $index }">
                    <div v-if="config.product_only_location == true">
                        {{ row.stock }}
                    </div>
                    <div v-else>
                        <template
                            v-if="
                                typeUser == 'seller' && row.unit_type_id != 'ZZ'
                            "
                            >{{ row.stock }}</template
                        >
                        <template
                            v-else-if="
                                typeUser != 'seller' && row.unit_type_id != 'ZZ'
                            "
                        >
                            <button
                                type="button"
                                class="pos-table__icon-btn"
                                title="Ver stock por almacén (F4)"
                                @click.prevent="clickWarehouseDetail(row)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" /><path d="M12 12l8 -4.5" /><path d="M12 12l0 9" /><path d="M12 12l-8 -4.5" /><path d="M16 5.25l-8 4.5" /></svg>
                            </button>
                            <span
                                v-if="$index === currentIndex"
                                class="pos-key pos-key--hint"
                            >F4</span>
                        </template>
                    </div>
                </template>
            </el-table-column>

            <el-table-column v-if="cols.lista_precios" label="Lista precios" min-width="150">
                <template slot-scope="{ row, $index }">
                    <div v-if="priceOptions(row).length > 0" class="pos-price-chips">
                        <button
                            v-for="(opt, i) in priceOptions(row).slice(0, 2)"
                            :key="i"
                            type="button"
                            class="pos-price-chip"
                            :title="opt.label + ': agregar al carrito con precio ' + formatPrice(opt.price) + ' (' + opt.unit_type_id + ')'"
                            @click.stop="pickPrice(opt, $index)"
                        >
                            <span class="pos-price-chip__label">{{ shortLabel(opt) }}</span>
                            {{ formatPrice(opt.price) }}
                        </button>
                        <button
                            v-if="priceOptions(row).length > 2"
                            type="button"
                            class="pos-price-chip pos-price-chip--more"
                            :title="'Ver los ' + priceOptions(row).length + ' precios (F2)'"
                            @click.stop="openPricePick(row, $index)"
                        >
                            +{{ priceOptions(row).length - 2 }}
                        </button>
                    </div>
                    <span v-else class="pos-table__muted">Sin lista</span>
                </template>
            </el-table-column>


            <el-table-column v-if="cols.historial" label="Historial" width="110">
                <template slot-scope="{ row, $index }">
                    <button
                        type="button"
                        class="pos-table__icon-btn"
                        title="Historial de ventas del producto (F9)"
                        @click.stop="clickHistorySales(row.item_id)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 8l0 4l2 2" /><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5" /></svg>
                    </button>
                    <span
                        v-if="$index === currentIndex"
                        class="pos-key pos-key--hint"
                    >F9</span>
                </template>
            </el-table-column>

        </el-table>

        <!-- Atajos de teclado -->
        <el-dialog
            title="Atajos de teclado"
            :visible.sync="showShortcuts"
            width="500px"
            append-to-body
            :close-on-press-escape="false"
            custom-class="pos-shortcuts"
        >
            <ul class="pos-shortcuts__list">
                <li>
                    <span><span class="pos-key">↑</span> <span class="pos-key">↓</span></span>
                    <span>Moverse por la lista de productos</span>
                </li>
                <li>
                    <span><span class="pos-key">Enter</span></span>
                    <span>Agregar el producto resaltado al carrito</span>
                </li>
                <li>
                    <span><span class="pos-key">Esc</span></span>
                    <span>Cerrar la ventana abierta (historial, stock, precios); si no hay ninguna, limpia la búsqueda</span>
                </li>
                <li>
                    <span><span class="pos-key">Ctrl</span> <span class="pos-key">B</span></span>
                    <span>Ir al buscador de productos desde cualquier campo</span>
                </li>
                <li>
                    <span><span class="pos-key">Click</span></span>
                    <span>Un click sobre la fila también agrega el producto</span>
                </li>
                <li>
                    <span><span class="pos-key">F1</span></span>
                    <span>Activar/desactivar búsqueda por código de barras</span>
                </li>
                <li>
                    <span><span class="pos-key">F2</span></span>
                    <span>Abrir los precios del producto resaltado</span>
                </li>
                <li>
                    <span><span class="pos-key">1</span><span class="pos-key">…</span><span class="pos-key">9</span></span>
                    <span>Con los precios abiertos, agrega con ese precio</span>
                </li>
                <li>
                    <span><span class="pos-key">F4</span></span>
                    <span>Ver stock por almacén del producto resaltado</span>
                </li>
                <li>
                    <span><span class="pos-key">F9</span></span>
                    <span>Ver historial de ventas del producto resaltado</span>
                </li>
                <li>
                    <span><span class="pos-key">F8</span></span>
                    <span>Mostrar esta ayuda</span>
                </li>
            </ul>
            <p class="pos-shortcuts__note">
                Escribe en el buscador y usa las flechas sin soltar el teclado:
                el primer resultado queda resaltado y con
                <span class="pos-key">Enter</span> lo agregas directamente.
            </p>
        </el-dialog>

        <!-- Selector rápido de precios (F2 o chip "+N") -->
        <el-dialog
            :title="pricePick.row ? 'Precios · ' + pricePick.row.description : 'Precios'"
            :visible.sync="pricePick.visible"
            width="400px"
            append-to-body
            :close-on-press-escape="false"
            custom-class="pos-shortcuts pos-price-pick"
        >
            <ul class="pos-price-pick__list">
                <li v-for="(opt, i) in pricePick.options" :key="i">
                    <button
                        type="button"
                        class="pos-price-pick__opt"
                        @click="pickFromDialog(i)"
                    >
                        <span class="pos-key">{{ i < 9 ? i + 1 : "·" }}</span>
                        <span class="pos-price-pick__info">
                            <span class="pos-price-pick__label">{{ opt.label }}</span>
                            <span class="pos-price-pick__meta">
                                {{ opt.unit_type_id }}<template v-if="opt.description"> · {{ opt.description }}</template><template v-if="Number(opt.quantity_unit) > 1"> · x{{ opt.quantity_unit }}</template>
                            </span>
                        </span>
                        <span class="pos-price-pick__price">
                            {{ pricePick.row ? pricePick.row.currency_type_symbol : "" }} {{ formatPrice(opt.price) }}
                        </span>
                    </button>
                </li>
            </ul>
            <p class="pos-shortcuts__note">
                Presiona el número del precio (o haz click) para agregar el
                producto al carrito con ese precio.
            </p>
        </el-dialog>

        <item-unit-types-table
            :showDialog.sync="showDialogItemUnitTypes"
            :itemUnitTypes="itemUnitTypes"
        >
        </item-unit-types-table>
    </div>
</template>

<script>
import ItemUnitTypesTable from "./item_unit_types_table.vue";

const COLS_STORAGE_KEY = "pos_table_columns_v1";

export default {
    components: { ItemUnitTypesTable },
    props: {
        typeUser: String,
        records: {
            type: Array,
            default: [],
            required: false
        },
        visibleTagsCustomer: {
            type: Boolean,
            default: false
        },
        searchFromBarcode: {
            type: Boolean,
            default: false
        },
        originIsGarage: {
            type: Boolean,
            required: false,
            default: false
        },
    },
    data() {
        return {
            currentIndex: 0,
            showDialogItemUnitTypes: false,
            showShortcuts: false,
            currentRow: null,
            itemUnitTypes: [],
            // Selector rápido de precios (F2 / chip "+N")
            pricePick: {
                visible: false,
                row: null,
                index: null,
                options: []
            },
            config: {},
            // Visibilidad de columnas: configurable por negocio (localStorage)
            cols: {
                codigo: true,
                marca: true,
                precio: true,
                unidad: false,
                pack: true,
                stock: true,
                lista_precios: true,
                historial: true
            }
        };
    },
    watch: {
        // Al cambiar los resultados de búsqueda, el primer producto queda
        // resaltado: escribir + Enter agrega el primer resultado sin tocar
        // las flechas.
        records() {
            this.currentIndex = 0;
            this.$nextTick(() => {
                if (this.records.length > 0) {
                    this.setCurrent(this.records[0]);
                }
            });
        }
    },
    mounted() {
        // Un solo listener por e.key (keyCode como respaldo): más fiable que
        // depender solo de keyCode, que está deprecado.
        this._onKeyup = (e) => this.handleKeyup(e);
        this._onKeydown = (e) => this.handleKeydown(e);
        window.addEventListener("keyup", this._onKeyup);
        window.addEventListener("keydown", this._onKeydown);
    },
    beforeDestroy() {
        window.removeEventListener("keyup", this._onKeyup);
        window.removeEventListener("keydown", this._onKeydown);
    },
    created() {
        this.loadCols();

        this.$http.get(`/configurations/record`).then(response => {
            this.config = response.data.data;
        });

        this.events()

    },
    methods: {
        /**
         * Precio con la cantidad de decimales configurada por el negocio
         * (Configuración avanzada -> "Cantidad de decimales para los precios",
         * campo decimal_quantity, aplica a todo el sistema). Sin esto se veían
         * "S/ 30" y "S/ 20.0000" mezclados: la BD guarda texto con 4 decimales
         * y las listas de precios reasignan números sin formato.
         */
        formatPrice(value) {
            const n = parseFloat(value);
            if (isNaN(n)) return value;

            const decimals = parseInt(this.config.decimal_quantity, 10);
            return n.toFixed(isNaN(decimals) ? 2 : decimals);
        },
        handleKeydown(e) {
            const key = e.key || "";

            // Ctrl+B / Cmd+B en keydown: el modificador se lee bien aquí.
            // La tecla B sola no debe interferir al escribir (ej. "baby").
            if ((key === "b" || key === "B") && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                return this.handleFocusSearch();
            }
        },
        handleKeyup(e) {
            const key = e.key || "";
            const code = e.keyCode || 0;

            // Con el selector de precios abierto, los dígitos eligen el precio
            if (this.pricePick.visible) {
                if (/^[1-9]$/.test(key)) {
                    return this.pickFromDialog(parseInt(key, 10) - 1);
                }
                if (key === "Escape" || code === 27) {
                    this.pricePick.visible = false;
                }
                return;
            }

            if (key === "ArrowDown" || code === 40) return this.handle40();
            if (key === "ArrowUp" || code === 38) return this.handle38();
            if (key === "Enter" || code === 13) return this.handle13();
            if (key === "Escape" || code === 27) return this.handle27();
            if (key === "F8" || code === 119) return this.handle119();
            if (key === "F2" || code === 113) return this.handleF2();
            if (key === "F4" || code === 115) return this.handleF4();
            if (key === "F9" || code === 120) return this.handleF9();
        },
        /**
         * F4: stock por almacén del producto resaltado (si su fila muestra
         * el botón: no aplica a vendedores ni a unidades ZZ).
         */
        handleF4() {
            if (this.keyboardBlocked()) return;

            const row = this.currentRow || this.records[0];
            if (!row || !this.canOpenStock(row)) return;

            this.$emit("clickWarehouseDetail", row);
        },
        canOpenStock(row) {
            if (this.config.product_only_location == true) return false;

            return this.typeUser != "seller" && row.unit_type_id != "ZZ";
        },
        /**
         * F9: historial de ventas del producto resaltado.
         */
        handleF9() {
            if (this.keyboardBlocked()) return;

            const row = this.currentRow || this.records[0];
            if (!row) return;

            this.$emit("clickHistorySales", row.item_id);
        },
        /**
         * F2: precios del producto resaltado. Si el negocio usa el diálogo
         * de listas por configuración (select_available_price_list) se
         * conserva ese flujo; si no, se abre el selector rápido numerado.
         */
        handleF2() {
            if (this.config.select_available_price_list) {
                return this.openTableListPrices113();
            }

            if (this.keyboardBlocked()) return;

            const row = this.currentRow || this.records[0];
            if (!row) return;

            const index = typeof row.index === "number" ? row.index : this.currentIndex;
            this.openPricePick(row, index);
        },
        events(){

            this.$eventHub.$on("selectedItemUnitTypeTable", (unit_type) => {
                this.setPriceItem(unit_type)
            })

        },
        /**
         * Cargar/guardar la configuración de columnas del negocio.
         */
        loadCols() {
            try {
                const saved = JSON.parse(localStorage.getItem(COLS_STORAGE_KEY));
                if (saved && typeof saved === "object") {
                    this.cols = { ...this.cols, ...saved };
                }
            } catch (e) {
                // configuración corrupta: se mantienen los valores por defecto
            }
        },
        saveCols() {
            localStorage.setItem(COLS_STORAGE_KEY, JSON.stringify(this.cols));
            // el-table necesita recalcular el layout al mostrar/ocultar columnas
            this.$nextTick(() => {
                if (this.$refs.singleTable) this.$refs.singleTable.doLayout();
            });
        },
        /**
         * true cuando el foco está en un campo distinto del buscador de
         * productos (cantidades del carrito, cliente, importes...): ahí las
         * teclas de la tabla no deben actuar.
         */
        keyboardBlocked() {
            const el = document.activeElement;
            if (!el) return false;

            const tag = el.tagName;
            if (tag !== "INPUT" && tag !== "TEXTAREA" && tag !== "SELECT") {
                return false;
            }

            return (el.getAttribute("placeholder") || "") !== "Buscar productos";
        },
        setPriceItem(price, index) {

            const item = this.records[index];
            if (item && item.item_unit_types && Array.isArray(item.item_unit_types)) {
                item.item_unit_types.forEach(iut => {
                    if (iut && iut.prices && Array.isArray(iut.prices)) {
                        iut.prices.forEach(p => {
                            if (p && p.selected) {
                                this.$set(p, 'selected', false);
                            }
                        });
                    }
                });
            }

            if (price) {
                this.$set(price, 'selected', true);
                this.addItemFromPriceSelected(item)
            }

            this.records[index].sale_unit_price = price.price;
            this.records[index].unit_type_id = price.unit_type_id;
            this.$message.success("Precio seleccionado");

        },
        async addItemFromPriceSelected(item)
        {
            if(this.originIsGarage && this.config.price_selected_add_product)
            {
                await this.handle13()
                item.apply_price_selected_add_product = true
            }
        },
        openTableListPrices113(){

            if(this.config.select_available_price_list){

                if (this.records.length == 1) {
                    // console.log(this.records[0].description)

                    if(this.records[0].unit_type.length > 0){

                        this.itemUnitTypes = this.records[0].unit_type
                        this.showDialogItemUnitTypes = true

                    }


                } else {

                    if (this.currentRow) {

                        if(this.currentRow.unit_type.length > 0){

                            this.itemUnitTypes = this.currentRow.unit_type
                            this.showDialogItemUnitTypes = true
                            // console.log(this.currentRow.description)

                        }

                    }
                }
            }

        },
        /**
         * Precios disponibles de la fila, aplanados (una entrada por precio > 0).
         */
        priceOptions(row) {
            const out = [];
            const iuts = row && row.item_unit_types;
            if (!Array.isArray(iuts)) return out;

            iuts.forEach(iut => {
                if (!iut || !Array.isArray(iut.prices)) return;
                iut.prices.forEach(p => {
                    if (p && !isNaN(Number(p.price)) && Number(p.price) > 0) {
                        out.push({
                            price: p.price,
                            // Nombre de la lista de precios a la que pertenece
                            label: p.label || "Precio",
                            position: p.position || 0,
                            unit_type_id: p.unit_type_id || iut.unit_type_id,
                            description: iut.description,
                            quantity_unit: p.quantity_unit || iut.quantity_unit,
                            ref: p
                        });
                    }
                });
            });

            // Orden estable: por posición de la etiqueta y luego por unidad
            return out.sort((a, b) =>
                (a.position - b.position) ||
                String(a.unit_type_id).localeCompare(String(b.unit_type_id))
            );
        },
        /**
         * Nombre corto de la etiqueta para los chips ("Precio mayorista" -> "Mayorista").
         */
        shortLabel(option) {
            return String(option.label || "").replace(/^precio\s+/i, "") || "Precio";
        },
        /**
         * Un solo paso: aplica el precio elegido y agrega el producto al
         * carrito (antes había que marcar el precio y luego dar Enter/click).
         */
        pickPrice(opt, index) {
            const item = this.records[index];
            if (!item) return;

            (item.item_unit_types || []).forEach(iut => {
                (iut.prices || []).forEach(p => {
                    if (p && p.selected) this.$set(p, "selected", false);
                });
            });
            this.$set(opt.ref, "selected", true);

            item.sale_unit_price = opt.price;
            item.unit_type_id = opt.unit_type_id;

            this.$emit("clickAddItem", item);
        },
        openPricePick(row, index) {
            const options = this.priceOptions(row);
            if (!options.length) return;

            this.pricePick.row = row;
            this.pricePick.index = index;
            this.pricePick.options = options;
            this.pricePick.visible = true;
        },
        pickFromDialog(i) {
            const opt = this.pricePick.options[i];
            if (!opt) return;

            this.pickPrice(opt, this.pricePick.index);
            this.pricePick.visible = false;
        },
        /**
         * Nombres de los productos del pack (sets puede venir anidado).
         */
        packList(row) {
            const sets = row && row.sets;
            if (!Array.isArray(sets)) return [];
            return typeof sets.flat === "function" ? sets.flat() : sets;
        },
        priceOptionsCount(item) {
            let count = 0;
            if (!item) return count;
            const iuts = item.item_unit_types;
            if (!iuts || !Array.isArray(iuts)) return count;

            iuts.forEach(iut => {
                if (!iut || !Array.isArray(iut.prices)) return;
                iut.prices.forEach(p => {
                    if (p && !isNaN(Number(p.price)) && Number(p.price) > 0) {
                        count++;
                    }
                });
            });

            return count;
        },
        handle13() {
            if(this.searchFromBarcode) return

            if (this.visibleTagsCustomer || this.keyboardBlocked()) {
                return false;
            }

            if (this.records.length == 1) {
                this.$emit("clickAddItem", this.records[0]);
            } else if (this.currentRow) {
                this.$emit("clickAddItem", this.currentRow);
            } else if (this.records.length > 0) {
                this.$emit("clickAddItem", this.records[0]);
            }
        },
        handle27() {
            if (this.showShortcuts) {
                this.showShortcuts = false;
                return;
            }

            // Con un modal abierto (historial, stock, precios...), Esc lo
            // cierra el propio modal: aquí no se toca la búsqueda.
            const modalAbierto = [...document.querySelectorAll(".el-dialog__wrapper")]
                .some(w => getComputedStyle(w).display !== "none");
            if (modalAbierto) return;

            if (this.keyboardBlocked()) return;
            this.$emit("escape");
        },
        /**
         * Ctrl+B / Cmd+B: enfocar el buscador de productos (desde cualquier
         * campo; Esc también vuelve al buscador pero además limpia).
         */
        handleFocusSearch() {
            const input = [...document.querySelectorAll(
                'input[placeholder="Buscar productos"]'
            )].find(i => i.offsetParent !== null);

            if (input) {
                input.focus();
                input.select();
            }
        },
        handle119() {
            this.showShortcuts = !this.showShortcuts;
        },
        handle40() {
            if(this.searchFromBarcode) return

            if (this.visibleTagsCustomer || this.keyboardBlocked()) {
                return;
            }
            this.currentIndex += 1;

            if (this.records[this.currentIndex]) {
                this.setCurrent(this.records[this.currentIndex]);
            } else {
                this.currentIndex = 0;
                this.setCurrent(this.records[0]);
            }

            this.scrollSelectedIntoView();
        },
        handle38() {
            if (this.visibleTagsCustomer || this.keyboardBlocked()) {
                return;
            }

            if (this.currentIndex == 0) {
                return;
            }
            this.currentIndex -= 1;
            this.setCurrent(this.records[this.currentIndex]);

            this.scrollSelectedIntoView();
        },
        setCurrent(row) {
            this.$refs.singleTable.setCurrentRow(row);
        },
        scrollSelectedIntoView() {
            this.$nextTick(() => {
                if (!this.$refs.singleTable) return;
                // Sólo las filas directas de la tabla: dentro de las celdas hay
                // tablas anidadas (popover de lista de precios) con sus propios <tr>
                const rows = this.$refs.singleTable.$el.querySelectorAll(
                    ".el-table__body > tbody > tr"
                );
                const row = rows[this.currentIndex];
                if (row && row.scrollIntoView) {
                    row.scrollIntoView({ block: "nearest" });
                }
            });
        },
        /**
         * Única fuente de la selección: cualquier cambio (flechas, hover,
         * click) pasa por aquí y sincroniza el índice del teclado, de modo
         * que lo resaltado es siempre lo que agrega Enter.
         */
        handleCurrentChange(val) {
            this.currentRow = val;
            if (val && typeof val.index === "number") {
                this.currentIndex = val.index;
            }
        },
        /**
         * El hover mueve la selección real (no un segundo resaltado): así el
         * mouse y el teclado nunca apuntan a filas distintas.
         */
        onRowHover(row) {
            // Evitar re-renders innecesarios cuando el mouse sigue en la misma fila
            if (row && row !== this.currentRow) this.setCurrent(row);
        },
        /**
         * Click sobre la fila agrega el producto (igual que en la vista de
         * tarjetas). Los botones internos (stock, precios, historial)
         * conservan su acción propia.
         */
        onRowClick(row, column, event) {
            if (
                event &&
                event.target &&
                event.target.closest("button, a, .el-popover")
            ) {
                return;
            }
            this.$emit("clickAddItem", row);
        },
        clickWarehouseDetail(id) {
            this.$emit("clickWarehouseDetail", id);
        },
        clickHistorySales(id) {
            this.$emit("clickHistorySales", id);
        },
        clickHistoryPurchases(id) {
            this.$emit("clickHistoryPurchases", id);
        },
        reset() {
            this.currentIndex = 0;
            this.setCurrent(this.records[this.currentIndex]);
        },
        stampIndex({ row, rowIndex }) {
            row.index = rowIndex;
            return "font-weight-semibold";
        },
        headerFont(){
            return 'font-weight-semibold';
        }

    }
};
</script>

<style></style>
