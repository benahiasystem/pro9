---
name: mantener-unidades-medida-venezuela
description: Mantener el catálogo venezolano de unidades de medida de Pro9 y el contrato UND/SERV. Usar al tocar cat_unit_types, unit_type_id, productos, servicios, inventario, importaciones, documentos, POS, reportes, PDFs o integraciones que intercambien unidades.
---

# Mantener unidades de medida venezolanas

## Contrato

- La unidad predeterminada de productos es `UND` y la unidad funcional de servicios es `SERV`.
- `NIU` y `ZZ` son códigos heredados retirados: no sembrarlos, generarlos, aceptarlos, convertirlos ni conservarlos por compatibilidad.
- Validar toda unidad recibida contra un registro activo de `tenant.cat_unit_types` antes de persistirla.
- Mantener `UND` y `SERV` activos y protegerlos contra edición de su contrato, desactivación y eliminación.
- No tratar cualquier código distinto de `SERV` como válido por sí solo: primero debe pertenecer al catálogo activo.
- Este proyecto adapta instalaciones nuevas. Modificar el estado inicial directamente, sin migraciones incrementales ni backfills para tenants históricos.
- `cat_unit_types.hka_code` es `varchar(3) NOT NULL` y contiene la equivalencia UNECE Rec. 20 de referencia. El catálogo está cerrado: bloquear creación, edición y eliminación con HTTP 409 y `UNIT_CATALOG_CLOSED`; permitir consultas y cambio de estado salvo la desactivación de `UND` y `SERV`.

## Catálogo inicial exacto

Cada fila usa el mismo valor en `id` y `symbol`, con `active = 1`.

| Código | Descripción | Código | Descripción |
|---|---|---|---|
| BOL | Bolsa | BOT | Botella |
| BTO | Bulto | CAJ | Caja |
| CM | Centímetro | DIA | Día |
| DOC | Docena | GAL | Galón |
| GR | Gramo | HR | Hora |
| JGO | Juego | KG | Kilogramo |
| KM | Kilómetro | LB | Libra |
| LT | Litro | M | Metro |
| M2 | Metro cuadrado | M3 | Metro cúbico |
| MG | Miligramo | ML | Mililitro |
| MM | Milímetro | PAR | Par |
| PQT | Paquete | PULG | Pulgada |
| SAC | Saco | SERV | Servicio |
| TON | Tonelada | UND | Unidad |

| Pro9 | HKA | Pro9 | HKA | Pro9 | HKA | Pro9 | HKA |
|---|---|---|---|---|---|---|---|
| BOL | XBG | BOT | XBO | BTO | XBE | CAJ | XBX |
| CM | CMT | DIA | DAY | DOC | DZN | GAL | GLL |
| GR | GRM | HR | HUR | JGO | SET | KG | KGM |
| KM | KMT | LB | LBR | LT | LTR | M | MTR |
| M2 | MTK | M3 | MTQ | MG | MGM | ML | MLT |
| MM | MMT | PAR | PR | PQT | XPK | PULG | INH |
| SAC | XSA | SERV | E48 | TON | TNE | UND | C62 |

Los códigos de envases del Excel Rec. 20 usan el prefijo `X` de su nota 2. Se interpreta `BTO` como *bundle* (`XBE`), `GAL` como galón estadounidense (`GLL`) y `TON` como tonelada métrica (`TNE`). `PR` tiene dos caracteres; confirmar su aceptación con HKA antes de emitir. Esta equivalencia no activa el envío de unidades a HKA.

## Consumidores

1. Actualizar conjuntamente el seeder tenant, datos mock, constantes del dominio y validadores web/API/móvil.
2. Auditar valores predeterminados y discriminadores de servicio en productos, inventario, POS, compras, documentos, ecommerce, restaurante, hotel, suscripciones, producción, reportes, PDFs, WhatsApp y facturación masiva.
3. En importaciones, validar contra el catálogo activo y mantener `public/formats/items.xlsx` con `UND` como ejemplo. Aplicar `migrate-product-import-excel-format` cuando corresponda.
4. Si cambia Vue o JavaScript empaquetado, aplicar `frontend-build`: no editar `public/build` ni compilar por iniciativa propia.
5. Ignorar coincidencias no semánticas, como el token horario `ZZ` de Moment y archivos vendorizados o compilados.

## Verificación

- Probar las 28 filas exactas, `id = symbol`, `hka_code` obligatorio y conforme a la tabla, todas activas y ausencia de `NIU`/`ZZ` en el estado inicial.
- Probar producto `UND` y servicio `SERV` en creación, búsquedas, inventario, POS, documentos y reportes.
- Probar que formularios, API, móvil, presentaciones e importación aceptan códigos activos y rechazan códigos heredados, inactivos o inexistentes.
- Verificar tenant temporal, claves foráneas, rollback, reinstalación, plantilla XLSX, lint PHP, suites específicas, suite unitaria y `git diff --check`.
