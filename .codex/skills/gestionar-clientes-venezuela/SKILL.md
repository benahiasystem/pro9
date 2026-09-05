---
name: gestionar-clientes-venezuela
description: Mantener el alta, edición, catálogo, persistencia y presentación de documentos de identidad de clientes venezolanos en Pro9. Usar al tocar persons, PersonRequest, PersonController, formularios o selectores de clientes, RIF/cédula/pasaporte, cat_identity_document_types, documentos, reportes, PDFs, APIs o seeders tenant.
---

# Gestionar clientes de Venezuela

## Catálogo inmutable de documentos de identidad

`cat_identity_document_types` debe contener exactamente estos registros y conservar este orden contractual:

| Orden | id | active | description | Prefijo del número |
|---:|---|---:|---|---|
| 1 | `0` | `1` | `Doc.sin.rif` | Sin prefijo |
| 2 | `1` | `1` | `Venezolano` | `V` |
| 3 | `6` | `1` | `Juridico` | `J` |
| 4 | `7` | `1` | `Pasaporte` | `P` |
| 5 | `E` | `0` | `Extranjero` | `E` |
| 6 | `C` | `0` | `Comuna` | `C` |
| 7 | `G` | `0` | `Gubernamental` | `G` |
| 8 | `R` | `0` | `Firma Personal` | `R` |

- No agregar, quitar, renombrar, reordenar ni cambiar `active` en estos registros durante otras modificaciones.
- Mantener la misma lista y orden en `database/seeders/data/tenant_initial_data.php`.
- Considerar `active` un dato contractual heredado, no una regla de visibilidad: los siete tipos con letra deben estar disponibles para registrar clientes aunque `E`, `C`, `G` y `R` tengan `active = 0`.
- Toda migración de tenants existentes debe terminar con esos ocho registros exactos, remapear referencias antiguas antes de eliminar filas obsoletas y preservar las claves foráneas.

## Selección y persistencia

- El tipo de documento debe mostrarse siempre sólo con su descripción: `Venezolano`, `Extranjero`, `Pasaporte`, `Juridico`, `Comuna`, `Gubernamental`, `Firma Personal` o `Doc.sin.rif`.
- No concatenar la letra del documento al nombre del tipo en selectores, tablas, formularios, reportes, PDFs ni respuestas de presentación. Son incorrectas etiquetas como `Venezolano V`, `Juridico J` o `Pasaporte P`.
- Guardar en `persons.identity_document_type_id` el `id` del registro elegido, no la letra visible salvo cuando ambos coinciden. Por tanto: Venezolano=`1`, Extranjero=`E`, Pasaporte=`7`, Juridico=`6`, Comuna=`C`, Gubernamental=`G`, Firma Personal=`R` y Doc.sin.rif=`0`.
- En el alta y la edición de clientes, aceptar y guardar en `persons.number` únicamente dígitos ASCII (`0-9`). Rechazar letras, espacios, signos, guiones y cualquier otro carácter especial tanto en `PersonRequest` como en el formulario Vue; no eliminar ni normalizar silenciosamente esos caracteres antes de validar.
- Mantener los prefijos `V-`, `J-`, `P-`, `E-`, `C-`, `G-` y `R-` fuera del campo editable. Añadirlos únicamente al presentar el documento mediante el formateador centralizado.
- Validar en backend que `identity_document_type_id` pertenezca al catálogo contractual; no confiar sólo en el selector Vue.

## Presentación global

- Separar la etiqueta del tipo y el formato del número: la descripción visible no lleva letra, mientras que el número presentado sí conserva el prefijo contractual cuando corresponde.
- En todo el sistema donde se presente el documento del cliente, usar `<letra>-<persons.number>`.
- Ejemplos obligatorios: Venezolano `V-persons.number`, Extranjero `E-persons.number`, Pasaporte `P-persons.number`, Juridico `J-persons.number`, Comuna `C-persons.number`, Gubernamental `G-persons.number` y Firma Personal `R-persons.number`.
- Aplicar el formato en listados, buscadores, selecciones, comprobantes, PDFs, reportes, módulos y respuestas de presentación. Mantener además el número crudo cuando una integración, consulta o validación necesite `persons.number` sin prefijo.
- Centralizar la relación id/letra y el formateo; no inferir la letra desde la descripción ni duplicar mapas divergentes en componentes.
- No anteponer letra ni guion a `Doc.sin.rif`.

## Reglas del formulario de clientes

- Trabajar en el formulario real `resources/js/views/tenant/persons/form.vue` y en cualquier otro alta de cliente que comparta el catálogo.
- Tratar a todo cliente como domiciliado en Venezuela y persistir `country_id = VE`.
- Mostrar y exigir una nacionalidad distinta de `VE` sólo cuando `identity_document_type_id = E`; para los demás documentos persistir `nationality_id = VE` y mantener Nacionalidad fuera del DOM.
- Mostrar Estado / Municipio / Parroquia para la jerarquía territorial heredada, excepto en clientes con documento Extranjero (`E`) o Pasaporte (`7`).
- Al crear o editar un cliente Extranjero (`E`) o con Pasaporte (`7`), no exigir ni persistir Estado, Municipio o Parroquia. Ocultar esos controles y limpiar de forma autoritativa en backend `location_id`, `department_id`, `province_id` y `district_id`, tanto en `persons` como en cada fila de `addresses`, para que valores antiguos o manipulados queden vacíos o nulos.
- Conservar `website` y `observation` como campos opcionales.
- Tratar `addresses` ausente como `[]` y descartar filas vacías o inválidas antes de validar o persistir.

## Verificación obligatoria

- Comprobar por prueba automatizada la lista completa, valores, orden, descripciones y banderas `active` tanto en la fuente central como en `tenant_initial_data.php`.
- Probar el alta y la edición para cada selección con un valor compuesto sólo por dígitos. Verificar que letras y caracteres especiales —por ejemplo `J-123456789`, `123.456` o `123 456`— sean rechazados y que `123456789` se guarde sin cambios y se presente como `J-123456789` cuando el tipo sea `6`.
- Probar en alta y edición que Extranjero (`E`) y Pasaporte (`7`) aceptan una dirección sin jerarquía territorial y que cualquier `location_id`, `department_id`, `province_id` o `district_id` recibido queda vacío o nulo en la persona y sus direcciones.
- Probar que los siete tipos aparecen en el selector con descripciones limpias, sin concatenar su letra, y que el número formateado conserva el prefijo correspondiente.
- En Facturación, admitir para Facturas todos los tipos del catálogo venezolano. El filtro y la validación no pueden restringirse a Juridico; el cliente debe seguir visible, seleccionable y facturable con sus datos completos.
- Probar el formato global en los recursos centrales de clientes y en las plantillas/documentos de presentación; no considerar suficiente una prueba que sólo cubra el formulario.
- Verificar tenants nuevos y existentes. En un tenant histórico, ejecutar `tenant:migrate-venezuela {uuid}` o la migración tenant correspondiente y consultar directamente `cat_identity_document_types`.
- Compilar el frontend mediante la skill `frontend-build` antes de validar el flujo en navegador.
- Al generar una Factura desde una o varias Notas de venta, conservar y resolver el cliente de origen en Facturación aunque su tipo de identidad no sea `Juridico`. El filtro ordinario de Facturas no puede reemplazar ni vaciar ese cliente precargado; debe seguir visible, seleccionable y facturable con sus datos completos.
