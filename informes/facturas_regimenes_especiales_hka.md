# Facturas con regímenes especiales — Pro9 / HKA DEMO

Implementado y verificado el 12 de octubre de 2026, America/Caracas. Checkout local `codex/imprenta-digital-HKA`; sin commits ni despliegue.

## Alcance y operación

Comprobantes web, Factura interna `01/0101`, Medios digitales y DEMO, bienes y servicios, VES/USD, Contado completamente cobrado con pagos reales y saldo cero. Se admiten venta propia y un titular tercero. El régimen se selecciona opcionalmente por factura y conserva el IVA elegido por producto; no aplica tasas ni exenciones automáticas. Producción, crédito, exportación combinada, POS/Garage/API y notas vinculadas permanecen bloqueados.

El formulario ofrece **Régimen especial de tributación** y **Sin régimen especial**. Carga únicamente opciones activas del tenant; una edición muestra también la descripción congelada. Si una opción histórica queda inactiva, se identifica como no seleccionable y su snapshot sigue visible. El listado, detalle y ticket muestran el régimen registrado.

## Contrato y persistencia

Fuentes: manual HKA V02, páginas 14 y 37, y [Swagger DEMO](https://demoemisionv2.thefactoryhka.com.ve/swagger/v1/swagger.json). El 12 de octubre el Swagger coincidió byte a byte con el contrato versionado: SHA-256 `f2ddb07c239b04319df1fc9b1e6b4db137f89b51f063fa4989fd1bc836d10f04`.

`POST /api/Emision`, raíz `documentoElectronico`: tipoDocumento `01`, tipoTransaccion `01` propia / `99` por terceros, tipoDeVenta `Interna`, tipoDePago `Contado`. `regimenEspTributacion` contiene la descripción exacta, hasta 55 caracteres, y se omite sin régimen. No se envían IDs locales. El titular conserva `encabezado.tercero`, separado del comprador y del emisor autenticado.

SpecialTaxRegimePolicy valida el ID y resuelve el catálogo activo bajo bloqueo tenant, antes de efectos comerciales. Rechaza descripciones y catalog_snapshot publicados por el navegador. Se reutilizan `document_fiscal_data.special_tax_regime_id`, su FK y `catalog_snapshot`; no hay nuevas tablas, columnas, migraciones ni conversiones de tenants existentes.

HkaDomesticInvoicePolicy comparte el alcance y el cobro completo con terceros antes del commit y al construir el payload. HkaDomesticMoney adapta exclusivamente una copia, una sola vez aunque coincidan tercero y régimen. HkaThirdPartyMoney queda como delegador compatible. La copia concilia bases, descuentos globales/distribuidos, cargos no gravados, IVA, IGTF y centavos; USD se envía con principales/líneas VES y totalesOtraMoneda USD. Los importes locales, inventario, caja y pagos se conservan.

## Edición, notas y emisión

Antes del registro remoto se puede añadir, cambiar o quitar el régimen. Se conservan categoría propia/terceros, número, serie, moneda y cobros. El saldo debe permanecer cero incluso al quitar el régimen. Toda edición fiscal renueva UUID y snapshot también en not_requested y descarta payload anterior; queda trazabilidad saneada. Guardar exige después **Enviar HKA** explícito.

Se reutilizan los bloqueos empresa → documento → emisión, afterCommit, conciliación y control confirmado. Rollback no transmite; rechazo o timeout conserva la venta. Pendientes, incertidumbre no conciliada y confirmaciones bloquean edición; las respuestas tardías no modifican otra operación. Cambios posteriores de catálogo o Clientes no reescriben históricos. Las notas afectadas y el IGTF posterior que requiera una nota se rechazan antes de sus efectos comerciales.

## Aceptación real DEMO

Instalación nueva desechable con compradores, titulares y artículos sintéticos; correo automático desactivado, cero intentos de correo. Los 28 casos mínimos fueron confirmados por HKA. Cada uno incluye bien gravado, servicio gravado y servicio exento; los regímenes impares añaden descuentos por línea/globales y cargos por línea/globales, y en USD incorporan IGTF inicial. Los titulares alternan J/V.

Los valores de la tabla son **número Pro9 / control HKA**.

| Régimen | Propia VES | Terceros VES | Propia USD | Terceros USD |
|---|---|---|---|---|
| Zonas económicas especiales | 3100 / 00-00000052 | 3101 / 00-00000053 | 3102 / 00-00000054 | 3103 / 00-00000055 |
| Zona franca de Paraguaná | 3104 / 00-00000056 | 3105 / 00-00000057 | 3106 / 00-00000058 | 3107 / 00-00000059 |
| Zona libre de Paraguaná | 3108 / 00-00000060 | 3109 / 00-00000061 | 3110 / 00-00000062 | 3111 / 00-00000063 |
| Puerto libre Santa Elena de Uairén | 3112 / 00-00000064 | 3113 / 00-00000065 | 3114 / 00-00000066 | 3115 / 00-00000067 |
| Zona Libre de Mérida | 3116 / 00-00000068 | 3117 / 00-00000069 | 3118 / 00-00000070 | 3119 / 00-00000071 |
| Puerto libre Estado Nueva Esparta | 3120 / 00-00000072 | 3121 / 00-00000073 | 3122 / 00-00000074 | 3123 / 00-00000075 |
| Dutty Free | 3124 / 00-00000076 | 3125 / 00-00000077 | 3126 / 00-00000078 | 3127 / 00-00000079 |

Adicionalmente, el navegador creó la factura 3128 (Dutty Free, titular V, VES, 116,00), control `00-00000080`. La factura 3129 empezó sin transmisión; se cambió Dutty Free → Zona franca de Paraguaná → Sin régimen → Zona Libre de Mérida, conservando los mismos pagos, número y saldo cero y renovando UUID en cada guardado. Sólo **Enviar HKA** produjo el control `00-00000081`. Resultado final: 30 facturas confirmadas, sin ventas pendientes ni correo enviado.

## Originales HKA y limitación de presentación

Los 28 archivos JSON fiscales descargados de HKA conservaron exactamente el régimen enviado. Se revisaron 84 páginas: A4 original, A5 convertido y ticket por caso. Comprador y, cuando corresponde, titular aparecen en los originales; los 28 tickets muestran el régimen y sus QR decodificados coinciden exactamente con la URL conservada.

**El proveedor no imprime actualmente el régimen en sus originales A4**, pese a conservarlo en el JSON fiscal remoto. A5 y el correo HKA heredan esa omisión. Se documentó y se conservaron los originales, sin insertar texto, modificar sus bytes ni reemitir facturas confirmadas. La recepción y conservación fiscal en DEMO están acreditadas; la impresión del régimen en el original HKA queda limitada por su plantilla. La aceptación no acredita producción ni homologación.

Pro9 conserva A4, A5 y correo actuales; el ticket incorpora la parcial del snapshot y reserva 16 mm adicionales. Su resumen aplica los importes locales también para venta propia con régimen. Las tres descargas A4/A5/80MM fueron comprobadas con clics de navegador y el asset final `app-0d3d7497.js`.

## Verificación

- PHP focalizado: 179 pruebas y 1.680 aserciones aprobadas de contrato, siete nombres, monedas, importes, manipulación, cobros, aislamiento de opciones tenant, autorización, edición, respuestas HKA, PDF y correo. SpecialTaxRegimeTest volvió a pasar al final: 33 pruebas y 425 aserciones.
- JavaScript: 62 pruebas aprobadas de regímenes, terceros, exportaciones, cobros, edición y diálogos HKA. Se compiló la plantilla Vue y se verificó selección/limpieza, conservación de IVA y rechazo de crédito antes de HTTP.
- MySQL desechable: cuatro pruebas y 998 aserciones de regímenes, terceros, exportación y emisión/commit; tres pruebas y 1.759 aserciones de instalación/seeding/rollback/repetición y pagos/IGTF/retenciones concurrentes; prueba final de regímenes con 481 aserciones. Incluye edición/envío simultáneo, congelación y rechazo/timeout con conservación de venta.
- Suite general: 701 pruebas y 15.489 aserciones, 16 omitidas, con tres fallos ajenos a este cambio: dos expectativas previas COT-12 / COT-00000012 de QuotationStorefrontCorrelativeTest y el ejemplo HTTP de Dusk por ausencia de multifacturalo_dusk.
- Sintaxis PHP de 18 archivos correcta; git diff --check sin errores. npm run build autorizado finalizó con los avisos existentes de Sass, fuentes y tamaño de chunks. Había un watcher del usuario activo, que se conservó.
- Chrome: siete opciones activas, combinación con tercero, IVA gravado intacto, rechazo de crédito, vista previa 80MM, creación real, listado/detalle, descargas, reapertura y edición con envío explícito.

## Evidencia y limpieza

[Matriz saneada](evidencias/regimenes_hka_demo_2026_10_12.json), [formulario](evidencias/regimenes_formulario_2026_10_12.png), [creación confirmada](evidencias/regimenes_web_confirmada_2026_10_12.png), [edición confirmada](evidencias/regimenes_edicion_confirmada_2026_10_12.png) y [detalle con régimen](evidencias/regimenes_detalle_2026_10_12.png). Los originales, JSON remotos y datos privados se archivan fuera del repositorio; la evidencia pública no contiene credenciales, JWT ni URLs de consulta/QR.

Se retiraron la instalación desechable, sus registros centrales, base, usuario MySQL, almacenamiento, cachés de resolución y autenticación HKA y pestaña. Las tres descargas del navegador se archivaron fuera del repositorio y se retiraron de Descargas. Los originales y archivos privados están en `/home/benahia/.codex/private-evidence/regimenes-hka-2026-10-12`, con directorio privado. No quedan bases pro9_fiscal_test de esta ejecución. Las bases de las pruebas MySQL se eliminan al terminar; se corrigió tearDown para revertir transacciones pendientes antes de DROP, evitando bloqueo de metadatos al fallar una prueba. Se conservaron informes/qa_facturacion_medios_digitales/, outputs/ y las instalaciones existentes del usuario.
