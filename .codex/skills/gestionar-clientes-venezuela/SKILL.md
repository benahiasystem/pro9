---
name: gestionar-clientes-venezuela
description: Mantener el alta, edición, catálogo, persistencia y presentación de documentos de identidad de clientes venezolanos en Pro9. Usar al tocar persons, PersonRequest, PersonController, formularios o selectores de clientes, RIF/cédula/pasaporte, cat_identity_document_types, documentos, reportes, PDFs, APIs o seeders tenant.
---

# Gestionar clientes de Venezuela

## Catálogo inmutable de documentos de identidad

`cat_identity_document_types` debe contener exactamente estos nueve registros y conservar este orden contractual. `external_document_examples` es `null` salvo en la fila `ND`:

| Orden | id | active | description | Prefijo del número |
|---:|---|---:|---|---|
| 1 | `0` | `1` | `Doc.sin.rif` | Sin prefijo |
| 2 | `1` | `1` | `Venezolano` | `V` |
| 3 | `6` | `1` | `Juridico` | `J` |
| 4 | `7` | `1` | `Pasaporte` | `P` |
| 5 | `E` | `1` | `Extranjero` | `E` |
| 6 | `C` | `1` | `Comuna` | `C` |
| 7 | `G` | `1` | `Gubernamental` | `G` |
| 8 | `R` | `1` | `Firma Personal` | `R` |
| 9 | `ND` | `1` | `No Domiciliado` | Sin prefijo; referencia HKA `RUT, NIT` |

- No agregar, quitar, renombrar, reordenar ni cambiar `active` en estos registros durante otras modificaciones. `ND` es un ID local de referencia, no un código HKA.
- Mantener la misma lista y orden en `database/seeders/data/tenant_initial_data.php`.
- Los ocho tipos canónicos nacen con `active = 1` y permanecen disponibles tanto para mantener clientes como para emitir ventas. `ND` nace activo y pertenece a `IdentityDocument::TYPES` y al alta/edición de clientes; se selecciona en ventas únicamente para exportación de bienes desde Comprobantes web DEMO. `RUT, NIT` son ejemplos externos, no un código único. La tabla sigue siendo autoritativa para emisión: si un tipo operativo se desactiva directamente, debe dejar de emitirse sin requerir cambios de código.
- Sembrar directamente las nueve filas. No usar `IdentityDocumentCatalogMigrator` ni transformar números o tipos de instalaciones anteriores; la validación vigente sigue siendo obligatoria.

## Selección y persistencia

- El tipo de documento debe mostrarse siempre sólo con su descripción: `Venezolano`, `Extranjero`, `Pasaporte`, `Juridico`, `Comuna`, `Gubernamental`, `Firma Personal` o `Doc.sin.rif`.
- No concatenar la letra del documento al nombre del tipo en selectores, tablas, formularios, reportes, PDFs ni respuestas de presentación. Son incorrectas etiquetas como `Venezolano V`, `Juridico J` o `Pasaporte P`.
- Guardar en `persons.identity_document_type_id` el `id` del registro elegido, no la letra visible salvo cuando ambos coinciden. Por tanto: Venezolano=`1`, Extranjero=`E`, Pasaporte=`7`, Juridico=`6`, Comuna=`C`, Gubernamental=`G`, Firma Personal=`R` y Doc.sin.rif=`0`.
- Mantener `IdentityDocumentType::$keyType = 'string'`: Eloquent no debe convertir IDs como E/C/G/R/ND a enteros al precargar la relación de identidad. Verificar los nueve IDs y la búsqueda de cliente por ID con contexto de exportación mediante el enrutador Laravel.
- En el alta y la edición de clientes domiciliados (todos los tipos salvo `ND`), aceptar y guardar en `persons.number` únicamente dígitos ASCII (`0-9`). Rechazar letras, espacios, signos, guiones y cualquier otro carácter especial tanto en `PersonRequest` como en el formulario Vue; no eliminar ni normalizar silenciosamente esos caracteres antes de validar.
- Mantener los prefijos `V-`, `J-`, `P-`, `E-`, `C-`, `G-` y `R-` fuera del campo editable. Añadirlos únicamente al presentar el documento mediante el formateador centralizado.
- Validar en backend que `identity_document_type_id` pertenezca al catálogo contractual; no confiar sólo en el selector Vue.
- Para `ND`, exigir país distinto de VE y dirección. Guardar `external_identity_type` (2–4 letras ASCII mayúsculas, distinto de ND: NIT/RUT/EIN), número de 1–45 caracteres ASCII alfanuméricos, punto o guion, empezando por alfanumérico. Conservar el número literalmente, sin prefijo ni normalización. La unicidad incluye tipo de persona, ND, país, tipo externo y número; el índice compuesto impide duplicados concurrentes. Enviar a HKA el tipo externo, nunca ND. No admitir ND como proveedor.

## Política de identidad en ventas

- Para Factura (`01`), Nota de venta (`80` o `nv`) y notas de crédito/débito (`07`/`08`), obtener siempre los tipos elegibles desde `cat_identity_document_types.active = 1`; no mantener listas locales como fuente de verdad.
- Aplicar `App\Services\SalesCustomerIdentityPolicy` como barrera backend antes de persistir o ejecutar efectos laterales, incluso cuando el cliente llegue precargado, por API, importación, ecommerce, restaurante, POS, Hotel, pedidos, tienda o servicio técnico.
- Filtrar buscadores y selectores de ventas con el mismo criterio y contexto: ventas internas excluyen ND; exportaciones 0201/0202/0203 aceptan exclusivamente ND. Un activo no habilita exportación desde POS, API ni otros módulos. El catálogo inicial muestra los ocho tipos; cualquier desactivación posterior debe excluir sólo el tipo afectado de la emisión.
- No emparejar tipos de identidad con tipos de comprobante ni con montos: todo tipo doméstico activo puede emitir cualquiera de los comprobantes de venta vigentes. No restaurar reglas como “sólo Juridico para Factura” o límites para `Doc.sin.rif`.

## Presentación global

- Separar la etiqueta del tipo y el formato del número: la descripción visible no lleva letra, mientras que el número presentado sí conserva el prefijo contractual cuando corresponde.
- En todo el sistema donde se presente el documento del cliente domiciliado, usar `<letra>-<persons.number>`. Para ND mostrar el tipo externo por separado y el número literalmente, sin añadir ND ni prefijos.
- Ejemplos obligatorios: Venezolano `V-persons.number`, Extranjero `E-persons.number`, Pasaporte `P-persons.number`, Juridico `J-persons.number`, Comuna `C-persons.number`, Gubernamental `G-persons.number` y Firma Personal `R-persons.number`.
- Aplicar el formato en listados, buscadores, selecciones, comprobantes, PDFs, reportes, módulos y respuestas de presentación. Mantener además el número crudo cuando una integración, consulta o validación necesite `persons.number` sin prefijo.
- Centralizar la relación id/letra y el formateo; no inferir la letra desde la descripción ni duplicar mapas divergentes en componentes.
- No anteponer letra ni guion a `Doc.sin.rif`.

## Reglas del formulario de clientes

- Trabajar en el formulario real `resources/js/views/tenant/persons/form.vue` y en cualquier otro alta de cliente que comparta el catálogo.
- Para tipos domésticos persistir `country_id = VE`. ND conserva su país real; las direcciones secundarias también requieren países extranjeros y una dirección seleccionada debe pertenecer al comprador.
- Mostrar y exigir una nacionalidad distinta de `VE` sólo cuando `identity_document_type_id = E`; para los demás documentos domésticos persistir `nationality_id = VE`; para ND limpiar nacionalidad y territorio venezolano y mantener Nacionalidad fuera del DOM.
- Mostrar Estado / Municipio / Parroquia para la jerarquía territorial heredada, excepto en clientes con documento Extranjero (`E`), Pasaporte (`7`) o No Domiciliado (`ND`).
- Al crear o editar un cliente Extranjero (`E`) o con Pasaporte (`7`), no exigir ni persistir Estado, Municipio o Parroquia. Ocultar esos controles y limpiar de forma autoritativa en backend `location_id`, `department_id`, `province_id` y `district_id`, tanto en `persons` como en cada fila de `addresses`, para que valores antiguos o manipulados queden vacíos o nulos.
- Conservar `website` y `observation` como campos opcionales.
- Tratar `addresses` ausente como `[]` y descartar filas vacías o inválidas antes de validar o persistir.

## Verificación obligatoria

- Comprobar por prueba automatizada la lista completa, valores, orden, descripciones y banderas `active` tanto en la fuente central como en `tenant_initial_data.php`.
- Probar el alta y la edición para cada selección con un valor compuesto sólo por dígitos. Verificar que letras y caracteres especiales —por ejemplo `J-123456789`, `123.456` o `123 456`— sean rechazados y que `123456789` se guarde sin cambios y se presente como `J-123456789` cuando el tipo sea `6`.
- Probar en alta y edición que Extranjero (`E`) y Pasaporte (`7`) aceptan una dirección sin jerarquía territorial y que cualquier `location_id`, `department_id`, `province_id` o `district_id` recibido queda vacío o nulo en la persona y sus direcciones.
- Probar que los ocho tipos aparecen al mantener clientes, con descripciones limpias y sin concatenar su letra, y que el número formateado conserva el prefijo correspondiente.
- En todos los módulos de ventas, probar que inicialmente aparecen y se aceptan los ocho tipos con `active = 1`; verificar además que cambiar una bandera a `0` modifica la elegibilidad sin cambiar código.
- Probar la matriz completa de `0`, `1`, `6`, `7`, `E`, `C`, `G` y `R` contra Factura, Nota de venta y notas de crédito/débito. El filtro y la validación no pueden restringir Factura a Juridico ni condicionar `Doc.sin.rif` por monto.
- Probar el formato global en los recursos centrales de clientes y en las plantillas/documentos de presentación; no considerar suficiente una prueba que sólo cubra el formulario.
- Verificar el catálogo y todas sus referencias en una instalación nueva temporal, sin modificar bases reales.
- Seguir la skill `frontend-build` para cambios en fuentes Vue/JavaScript; no compilar salvo petición explícita del usuario.
- Al generar una Factura desde una o varias Notas de venta, conservar y resolver el cliente de origen si su tipo de identidad continúa activo. Un tipo inactivo debe bloquear la emisión también en conversiones y clientes precargados.

## Exportación de bienes

El contrato y las restricciones se describen en [facturas de exportación HKA](../../../informes/facturas_exportacion_hka.md). Ejecutar `ExportInvoiceTest`, `SalesCustomerIdentityPolicyTest` y `tests/js/export-invoice-ui.test.cjs`. No convertir ni actualizar tenants existentes para añadir los campos.

## Clientes titulares de ventas por terceros

Mantener Es tercero en Avanzado → Clasificación, independiente de tipo de cliente y agente de retención. `persons` conserva is_third_party, third_party_rif y third_party_activity; no crear otro catálogo. El RIF complementario no sustituye la cédula. Exigir formato completo único tenant, actividad, domicilio VE, nombre de hasta 100 y dirección de hasta 255; correo opcional válido de hasta 50. No afirmar verificación SENIAT. Desmarcar o inhabilitar conserva datos/historial y bloquea nuevas selecciones; el cliente sigue disponible como comprador.

En el formulario reutilizado por el titular, usar selection_target=third_party y el evento saved. No disparar reloadDataPersons ni initInputPerson: seleccionan o reinicializan al comprador. La FK del snapshot impide borrar un titular utilizado. Consultar [facturas por cuenta de terceros](../../../informes/facturas_cuenta_terceros_hka.md) y ejecutar ThirdPartyInvoiceTest, PersonRequestVenezuelaTest y la prueba JS de terceros.

## Perfil fiscal SPE separado de la identidad comercial

Aplicar [perfiles SPE](../mantener-sujetos-pasivos-especiales-pro9/SKILL.md) al tocar `persons.fiscal_profile`, `FiscalProfileService`, el editor fiscal o snapshots. `persons.number` conserva los dígitos de cédula/documento y su presentación canónica; `fiscal_profile.rif` es un RIF fiscal adicional completo, separado y validado contra naturaleza jurídica. Firma personal del perfil, SPE, IVA y facultades IVA/ISLR no se deducen de la fila del catálogo de identidad ni de banderas comerciales.

El editor compartido de empresa/cliente guarda versiones sólo para administrador mediante `/fiscal-profiles/customer/{id}` y muestra historial. El POST genérico de persona rechaza `fiscal_profile`; no hacerlo mass fillable. Al emitir, conservar la versión vigente por fecha; un cambio posterior no sustituye snapshots. HKA toma el RIF fiscal del snapshot doméstico, mientras ND limpia ese perfil y usa su tipo/número externos. Mostrar RIF adicional identificado como fiscal sin cambiar el formato de cédula ni afirmar consulta SENIAT. Verificar política, historial/autorización y congelación en `test_spe_*`.
