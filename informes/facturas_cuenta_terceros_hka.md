# Facturas por cuenta de terceros — Pro9 / HKA DEMO

Implementación y validación realizadas el 11 de octubre de 2026 (America/Caracas), para instalaciones nuevas. La aceptación incluye emisión real; guardar la clasificación del cliente por sí solo no constituye la implementación.

## Alcance operativo

Comprobantes web, Factura `01`, venta interna `0101`, Medios digitales y DEMO, un titular por factura, bienes y servicios, VES/USD y Contado completamente pagado con cobros reales. La factura conserva numeración, inventario, caja, IVA, descuentos, cargos e IGTF locales. No se habilitan producción, API/POS/Garage, crédito, exportación con terceros, varios titulares, notas vinculadas, liquidaciones, comisiones ni inventario por propietario.

## Clientes y esquema inicial

El titular se registra en Clientes, con **Es tercero** en **Avanzado → Clasificación**, independiente del tipo de cliente y del agente de retención. Sigue disponible como comprador normal.

`persons` incorpora `is_third_party` booleano inicialmente falso, `third_party_rif` nullable de 10 caracteres canónicos con índice único tenant y `third_party_activity` nullable de 100 caracteres. Al marcarlo se exigen RIF completo, actividad, razón social, dirección y domicilio venezolano. La cédula habitual permanece en `number`. Se normaliza el formato del RIF; no se declara consulta ni verificación ante SENIAT. Desmarcar o inhabilitar conserva los datos y bloquea nuevas selecciones.

`document_fiscal_data.third_party_person_id` referencia al cliente con FK `ON DELETE RESTRICT`; `third_party` conserva el snapshot fiscal. Sólo se modificaron las migraciones iniciales consolidadas de ambas tablas y la de claves foráneas. No se convirtió ningún tenant existente. La instalación temporal contiene 339 tablas de aplicación.

Se comparó el esquema completo con el DDL de la rama antes del cambio: las únicas diferencias son las cuatro columnas anteriores, tres índices (RIF único, búsqueda de terceros habilitados y referencia al titular) y una FK restrictiva. No se retiraron tablas, columnas, índices ni relaciones. Se conservaron motor, collation, defaults y comentarios; se excluyeron contadores de datos y posiciones de columnas desplazadas por las adiciones.

## Contrato HKA

Fuentes: [manual HKA V02, Tercero y catálogo de transacciones](../../implementaciones%20con%20HKA/manuales/%5BIT-21-01-01%5D%5BV02%5DREFERENCIA%20TECNICA%20API-26.pdf) y [Swagger publicado](https://demoemisionv2.thefactoryhka.com.ve/swagger/v1/swagger.json). La respuesta consultada coincidió byte a byte con `app/Services/Fiscal/contracts/hka-ve-v1.json`, SHA-256 `f2ddb07c239b04319df1fc9b1e6b4db137f89b51f063fa4989fd1bc836d10f04`.

Se utiliza `POST /api/Emision`, raíz `documentoElectronico`, `tipoDocumento=01`, `tipoTransaccion=99`, `tipoDeVenta=Interna`, `tipoDePago=Contado`. El 99 designa la transacción; no se utiliza EmisionDNF. La cuenta tenant autentica al emisor, el adquirente permanece en `comprador` y el titular va en `encabezado.tercero`. Se omite `otrosTercero`.

El nodo tercero contiene únicamente `tipoIdentificacion`, `numeroIdentificacion`, `razonSocial`, `direccion`, `tipo` (actividad comercial) y, si existe, `correo[]`. Los cinco primeros son obligatorios. Límites conservadores: 3, 12, 100, 255 y 100 caracteres; 50 por correo. El RIF se presenta como letra, ocho dígitos y verificador. No se envían ID local, país ni clasificación.

El servidor recibe `third_party_id`, resuelve una ficha habilitada del tenant y determina el snapshot/transacción; rechaza snapshots publicados por el cliente y canales/combinaciones fuera del alcance antes de efectos comerciales. El constructor HKA usa exclusivamente datos congelados y valida el contrato versionado.

## Importes y edición

El adaptador `HkaThirdPartyMoney` opera sobre una copia. Distribuye las bases finales y el IVA conservados entre las líneas, incluyendo descuentos globales y los ya distribuidos. Los cargos que Pro9 no incluye en la base IVA se presentan como un servicio E de cargos no gravados; así se conserva el IVA local y no se duplica el cargo. No se modifican artículos comerciales, inventario ni cobros.

USD conserva moneda/importes locales y cobros, mientras HKA recibe principales/líneas VES y `totalesOtraMoneda` USD. Se concilian líneas, bases, impuestos y total en centavos con aritmética exacta; los residuos máximos de dos centavos quedan en la copia fiscal. El IGTF se declara tanto en sus totales como en `impuestosSubtotal`, requisito comprobado en DEMO. El recibo derivado no duplica `formasPago`.

El envío permanece después del commit, con UUID, conciliación y control confirmado. Un rechazo conserva la venta; un rollback no transmite. La edición previa al registro remoto mantiene la categoría propia/terceros, número, pagos y saldo cero; actualiza el titular desde su ficha y renueva el UUID, incluso cuando todavía estaba sin solicitar. Guardar una edición exige **Enviar HKA** explícito. Notas vinculadas e IGTF posterior que requiera una nota permanecen bloqueados.

La ficha de tercero usa un evento de guardado propio, para que registrar o editar al titular no sustituya ni reinicialice al comprador. No se añade al tercero como destinatario automático.

## Aceptación real

Datos de compradores y titulares de prueba, correo automático desactivado. Los cuatro casos mínimos incluyen un bien gravado y un servicio gravado.

| Caso | Número Pro9 | Control HKA | Total local | Resultado |
|---|---:|---|---:|---|
| Titular J / VES | 2032 | 00-00000044 | 261,29 VES | Confirmado |
| Titular J / USD | 2033 | 00-00000045 | 261,29 USD | Confirmado |
| Titular V / VES | 2034 | 00-00000046 | 261,29 VES | Confirmado |
| Titular V / USD | 2035 | 00-00000047 | 261,29 USD | Confirmado |
| Creación web / VES | 2038 | 00-00000048 | 100,00 VES | Confirmado |
| Descuentos, cargos y exento / VES | 2036 | 00-00000049 | 237,45 VES | Confirmado |
| Descuentos, cargos, exento e IGTF / USD | 2037 | 00-00000050 | 244,57 USD | Confirmado |
| Edición J → V y envío explícito / VES | 2039 | 00-00000051 | 261,29 VES | Confirmado |

Los rechazos iniciales de las combinaciones ampliadas permitieron corregir la distribución de bases y la declaración IGTF. Se editaron las operaciones rechazadas con UUID nuevo; las confirmadas conservaron payload, control y PDF original. No se reemitieron documentos confirmados.

Se descargaron y revisaron los originales A4, su conversión A5 y el ticket 80MM. Los PDF HKA incluyen un bloque **Datos de Tercero** separado de **Datos de Receptor**. El proveedor antepone nuevamente la letra al RIF completo, mostrando `J-J-…`/`V-V-…`; se documenta esa presentación de DEMO y se conservan los originales. La aceptación DEMO no acredita homologación ni producción; el emisor de sus PDF procede de la cuenta DEMO configurada.

El listado, detalle y ticket identifican la venta por terceros y muestran RIF/razón social congelados. La identidad del tercero se inserta mediante una parcial compartida para las plantillas locales; la validación visual y de importes del ticket corresponde a la plantilla predeterminada.

## Verificación

- JavaScript: 125 pruebas aprobadas de selección, respuestas tardías, disponibilidad, categoría inmutable y guardado del titular sin cambiar comprador, además de regresiones de exportación, cálculos y diálogos HKA. Las ocho pruebas del formulario volvieron a pasar después del último ajuste de presentación.
- PHP: 98 pruebas focalizadas y 1.432 aserciones aprobadas de clasificación, RIF complementario, duplicados, límites, snapshots, contrato, monedas, centavos, descuentos/cargos, IGTF, autorización, correo y PDF. La suite general ejecutó 667 pruebas y 15.058 aserciones, con 15 omitidas y dos fallos previos en `QuotationStorefrontCorrelativeTest` por `COT-12` frente a `COT-00000012`; no corresponden a esta implementación. Sintaxis correcta en los 31 archivos PHP modificados o nuevos; `git diff --check` sin errores.
- MySQL temporal: cuatro pruebas y 1.986 aserciones aprobadas de instalación, seeding, integridad global, rollback y segunda instalación; regresiones de exportación, commit/rollback/emisión y pagos concurrentes. Una prueba adicional y 105 aserciones aprobadas de terceros con FK restrictiva, desmarcado/deshabilitación, pagos conservados, UUID, IGTF y concurrencia edición/envío. Comparación completa del esquema con la rama anterior aprobada.
- Navegador Chrome: Clientes/Avanzado/Clasificación, alta J/V, reapertura, búsqueda por RIF, selección y edición del titular, creación pagada, edición previa, estado confirmado, listado/detalle y descargas A4/A5/80MM. Con el build final se comprobaron los errores de RIF y actividad vacíos, visibles en rojo y en líneas separadas; el rechazo no modifica la ficha. `npm run build` autorizado expresamente por el usuario y completado, asset `app-f3731006.js` comprobado en el navegador.
- PDF: 24 archivos revisados para las ocho facturas, con titular, RIF y comprador presentes; los ocho QR de ticket coinciden con la URL HKA guardada. El ticket ampliado conserva la igualdad `252,45 − 30,00 + 15,00 + 7,12 = 244,57 USD`.

Evidencia saneada y conteos finales: [matriz DEMO](evidencias/terceros_hka_demo_2026_10_11.json). Las capturas muestran [Clientes](evidencias/terceros_clientes_2026_10_11.png), [factura confirmada](evidencias/terceros_factura_2026_10_11.png) y [validaciones obligatorias](evidencias/terceros_validaciones_2026_10_11.png). Los originales y respuestas privadas se conservaron fuera del repositorio; no se publican credenciales, JWT, URLs firmadas ni datos reales del comprador.

Se retiraron la base, usuario MySQL, hostname, cliente y website centrales de la instalación temporal, su almacenamiento y las entradas propias de caché. Se cerró su pestaña y se archivaron fuera del repositorio las tres descargas de navegador antes de retirarlas de Descargas. La matriz registra todas las comprobaciones de limpieza. Los cambios previos del usuario en datos MOCK se conservaron.

## Evolución — 12 de octubre de 2026

Las facturas por terceros admiten ahora el régimen especial opcional dentro del mismo alcance web digital DEMO al contado completamente pagado. HkaDomesticInvoicePolicy y HkaDomesticMoney comparten las validaciones y la adaptación con ventas propias con régimen; HkaThirdPartyMoney delega por compatibilidad. La combinación se adapta una sola vez y conserva transacción 99 y titular separado. Ver [regímenes y evidencia nueva](facturas_regimenes_especiales_hka.md). Los controles y archivos históricos de este informe se conservan.

## Actualización de notas del 13/10/2026

El contrato vigente de notas domésticas, devoluciones y ajustes está en [notas de crédito y débito HKA](notas_credito_debito_hka.md). Reemplaza las restricciones históricas de notas vinculadas de esta etapa para el flujo ordinario identificado por UUID de creación. ND cobradas:34 variantes confirmadas/consultadas con PDF real; NC:36 variantes rechazadas por203/1015, con efectos comerciales guardados y capacidad fiscal bloqueada, sin pagos ficticios. Ajustes IGTF sin contrato/aceptación específica conservan pendientes. Esquema sólo para instalaciones nuevas; no se convirtió ningún tenant real.
