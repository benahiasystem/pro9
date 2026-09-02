---
name: gestionar-clientes-venezuela
description: Adaptar, corregir y validar el alta y edición de clientes venezolanos en Pro9. Usar al trabajar con PersonRequest, PersonController, persons/form.vue, RIF, cédula, documento Extranjero, nacionalidad, country_id, nationality_id, direcciones Estado/Municipio/Parroquia, website, observation o errores SQL al guardar personas.
---

# Gestionar clientes de Venezuela

## Contrato funcional

- Tratar a todo cliente como domiciliado en Venezuela: persistir `country_id = VE`.
- Persistir `nationality_id = VE` y ocultar Nacionalidad para cualquier documento excepto `Extranjero`.
- Usar el tipo de documento `4` como `Extranjero`; mostrar y exigir una nacionalidad distinta de VE sólo en ese caso.
- Usar `6` para RIF con una letra `V`, `E`, `J`, `G` o `P` seguida de nueve dígitos.
- Usar `1` para Cédula de Identidad venezolana con seis a ocho dígitos.
- Mostrar la jerarquía heredada como Estado / Municipio / Parroquia.
- Conservar `website` y `observation` como campos opcionales de `persons`; asegurar que existan en instalaciones limpias y tenants históricos.
- Tratar una colección de direcciones ausente como `[]`; no ejecutar `count()` sobre `null`.
- Descartar filas vacías o inválidas de `addresses` antes de validar o persistir; el formulario las usa como borradores y no deben impedir guardar un cliente válido.

## Flujo de implementación

1. Cambiar el formulario real `resources/js/views/tenant/persons/form.vue`, no sólo formularios antiguos de customers.
2. Mantener Nacionalidad fuera del DOM para clientes no extranjeros; no limitarse a ocultarla con estilos.
3. Normalizar en `PersonRequest::prepareForValidation` para proteger el sistema de bundles o payloads antiguos que aún envíen PE.
4. Validar en backend que la nacionalidad extranjera exista y sea distinta de VE.
5. Procesar `location_id` para VE en `PersonController`; esperar exactamente Estado, Municipio y Parroquia en la dirección principal y las secundarias.
6. Persistir `department_id`, `province_id` y `district_id` desde `location_id`; limpiar esos campos cuando no exista una jerarquía válida.
7. Mantener la estructura final de documentos de identidad en la migración consolidada de `cat_identity_document_types`; poblar Cédula, Extranjero y RIF mediante `TenantMigrationDataSeeder`.
8. Conservar `website` y `observation`, incluidos sus comentarios MySQL, en la migración consolidada de `persons`.
9. Verificar el DDL consolidado contra un tenant fuente y ejecutar rollback completo antes de usarlo para instalaciones nuevas.
10. Compilar el frontend antes de validar en el hostname del tenant.

## Validación

- Reproducir primero el fallo y revisar el error SQL; considerar `country_id = PE` un payload histórico y `Unknown column observation/website` una desalineación de esquema.
- Ejecutar pruebas unitarias de normalización de clientes venezolanos, extranjeros y proveedores.
- Crear un cliente con RIF desde el navegador y verificar el mensaje de éxito, la fila en el listado y los valores VE/VE en base.
- Elegir `Extranjero`, confirmar que aparece Nacionalidad y que el servidor rechaza el formulario vacío con un error legible.
- Editar una dirección y confirmar que se guardan Estado/Municipio/Parroquia junto con Sitio Web y Observaciones, incluso cuando sean nulos.
- Comprobar las columnas en todos los tenants y ejecutar un guardado transaccional reversible cuando no haya navegador autenticado.
- Descartar o identificar claramente los registros de prueba.

<!-- ######## INICIO VALIDACIÓN DOCUMENTAL EN TENANT HISTÓRICO ######## -->

En tenants existentes, ejecutar el puente `tenant:migrate-venezuela {uuid}` y confirmar directamente en `cat_identity_document_types` los valores activos `Cédula de Identidad (V)`, `Extranjero` y `RIF (V/E/J/G/P)`. La fuente Vue correcta no basta si el catálogo persistido continúa en DNI/CE/RUC.

<!-- ######## FIN VALIDACIÓN DOCUMENTAL EN TENANT HISTÓRICO ######## -->
