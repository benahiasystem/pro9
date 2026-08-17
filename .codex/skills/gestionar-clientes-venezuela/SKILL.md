---
name: gestionar-clientes-venezuela
description: Adaptar, corregir y validar el alta y edición de clientes venezolanos en Pro9. Usar con PersonRequest, PersonController, persons/form.vue, RIF, cédula, Extranjero, nacionalidad, direcciones Estado/Municipio/Parroquia, website u observation.
---

# Gestionar clientes de Venezuela

## Contrato funcional

- Persistir `country_id = VE` para clientes.
- Persistir `nationality_id = VE` y ocultar Nacionalidad salvo para documento Extranjero (`4`).
- Exigir una nacionalidad distinta de VE a extranjeros.
- Validar RIF (`6`) como V/E/J/G/P y nueve dígitos; validar Cédula (`1`) con seis a ocho dígitos.
- Mostrar Estado / Municipio / Parroquia y persistir sus tres identificadores.
- Conservar `website` y `observation` opcionales.
- Tratar direcciones ausentes como `[]`.
- Delimitar cada cambio de código con comentarios `########### INICIO` y `########### FIN`.

## Validación

Ejecutar `PersonRequestVenezuelaTest`, lint, build frontend y pruebas de alta/edición. Comprobar RIF, cédula, extranjero, proveedor, direcciones, Sitio Web y Observaciones.
