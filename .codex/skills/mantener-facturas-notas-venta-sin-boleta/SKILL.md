---
name: mantener-facturas-notas-venta-sin-boleta
description: Mantener en Pro9 las ventas con Factura y Nota de venta para instalaciones nuevas, sin soporte de Boletas. Usar al cambiar tipos de comprobante, series, POS, pedidos, Hotel, ecommerce, restaurante, WhatsApp, servicio técnico, conversiones, importaciones o configuración de documentos.
---

# Mantener Facturas y Notas de venta sin Boleta

## Objetivo

Aplicar un único contrato funcional en todos los canales de venta: Facturas (`01`) y Notas de venta (`80` o el alias técnico vigente `nv`). La instalación nueva no tiene Boletas ni datos anteriores que convertir. Conservar la auditoría de las operaciones actuales.

## Procedimiento

1. Leer [references/contrato.md](references/contrato.md) antes de modificar un flujo de emisión, conversión o series.
2. Identificar si el código crea documentos nuevos o solamente consulta documentos existentes.
3. En creación, validar el tipo en el servidor antes de persistir o ejecutar efectos laterales. Reutilizar `App\Services\SalesDocumentTypePolicy`; no confiar únicamente en filtros de interfaz.
4. Validar además el cliente con `App\Services\SalesCustomerIdentityPolicy`: su tipo debe existir con `active = 1` en `cat_identity_document_types`. Aplicar la barrera antes de persistir o crear clientes implícitamente por API.
5. Restringir cada selector y endpoint al subconjunto permitido por su flujo. Usar `01` y `80` en ventas; usar `01` y `nv` únicamente donde el servicio técnico mantenga ese alias.
6. Retirar ramas exclusivas de Boletas en lectura, listado, PDF, envío y reportes; conservar las funciones compartidas de Facturas, notas de crédito/débito y Notas de venta. No cambiar bases reales.
7. Retirar series `BB`, `BC` y `BD` y sus resolutores históricos. Las series de venta iniciales son `FF`, `FC`, `FD` y `NV`; también se conservan las series internas vigentes de almacén.
8. Encerrar los cambios funcionales con los marcadores de la tarjeta correspondiente indicados en el contrato.
9. Si se cambia Vue o JavaScript empaquetado, seguir el skill `frontend-build`; no editar `public/build/` a mano.
10. Ejecutar las pruebas unitarias del contrato, análisis de sintaxis y búsquedas de regresión antes de entregar.
11. Para una Nota de venta creada desde Hotel (`source_module=HOTEL`), el resumen de productos de Hotel no muestra IVA como total separado; conserva Subtotal y Total. Esta es una regla de presentación exclusiva de Hotel y no autoriza cambiar los cálculos, los datos persistidos ni otros canales de venta.

## Reglas de aceptación

- Una petición manipulada con `document_type_id=03` falla también en el servidor.
- Ningún flujo nuevo propone `03`, `BB`, `BC` o `BD`.
- Facturas y Notas de venta siguen creándose desde todos los canales que las soportan.
- Los ocho tipos canónicos `0`, `1`, `6`, `7`, `E`, `C`, `G` y `R` nacen activos y pueden emitir `01`, `80`/`nv`, `07` y `08`; una identidad desactivada posteriormente se rechaza en backend en todos los canales aunque la petición sea manipulada.
- No existen restricciones por combinación identidad/comprobante ni por monto. La tabla tenant conserva la autoridad sobre una desactivación posterior.
- No quedan rutas o ramas que sólo sirvan para Boletas antiguas o sus resúmenes fiscales. Los resúmenes comerciales de caja conservan su función.
- Los textos activos no prometen emisión de Boletas.
- El resumen de una Nota de venta de Hotel no muestra una fila o importe separado de IVA.
- Las pruebas verifican la política central, las series nuevas y los puntos de integración críticos.

## Separación de flujos

- `assertNewFiscalDocumentAllowed` valida el flujo de Facturas y notas de crédito/débito: `01`, `07`, `08`. No admitir `80`, `09` ni `20` en ese procesamiento; sus módulos tienen persistencia propia.
- `PRIMARY_DOCUMENT_TYPE_IDS` permite `01` y `80` al seleccionar una venta. El servicio técnico conserva `nv` como contrato actual.
- Probar rechazo de `03`, tipos desconocidos y tipos de otros módulos, además de Factura, notas y Nota de venta válidas. La ausencia de Boletas no permite restringir todos los módulos a un único tipo.
- `DocumentType::getCurrentRelatiomClass` resuelve `Document` para 01/07/08 y `SaleNote` para 80; rechaza códigos retirados/desconocidos en vez de asumir Factura. `ModelTenant::INVOICE_DOCUMENTS_IDS` contiene sólo 01. Los selectores de reportes no incluyen Boleta.
- Los datos móviles y captions del bot usan la descripción del tipo documental real, sin una alternativa implícita de Boleta para notas. Ejecutar `CurrentDocumentResolutionTest` además de la política central.
- Mi Tienda no consulta ni configura series de Boleta: retirar `series_document_bt` y `series_document_bt_id` de controlador, modelo, store, formularios y creación de tabla. Su botón Procesar depende del establecimiento y de la serie de Factura; no exigir una serie retirada. El texto de la importación describe Factura, que es el documento que genera ese flujo.
- La configuración no contiene `default_document_type_03`. El selector por defecto ofrece Factura y Nota de venta; `default_document_type_80=true` elige Nota de venta y `false` elige Factura. Venta rápida no debe inferir una Boleta según el documento de identidad del cliente.
- `FiscalEmissionSchemaTest` comprueba que la instalación nueva no crea `configuration_mi_tienda_pe.series_document_bt_id`, además de verificar seeding, integridad referencial, rollback y repetición.
- Las plantillas PDF de anticipos muestran Factura sin una alternativa de Boleta. Se eliminan los mapas locales de tipos documentales e identidad no utilizados en las plantillas de notas; éstas ya presentan los datos y descripciones actuales del documento/cliente.
- Ejecutar `CurrentPdfTemplateContractTest`: recorre todas las plantillas PDF, verifica ausencia de Boletas, ramas de tipo 03, hash, leyendas peruanas, ISC y bolsas, y analiza el PHP producido por Blade. Complementar con `CurrentPdfRenderingTest` y revisión visual del PDF; la compilación de Blade no acredita por sí sola la presentación.

## Entradas de Facturas y notas

- `DocumentInput` y `DocumentUpdateInput` validan 01/07/08 antes de consultar la configuración del tenant. Las Facturas y sus notas usan directamente `group_id=01`; no inferir un grupo alternativo de Boleta.
- Una nota sólo puede afectar una Factura (01), tanto si la referencia se aporta en `data_affected_document` como si se obtiene de un documento local. Resolver el documento local con `findOrFail` y conservar su identificador mediante asignación normal, sin variables variables.
- Ejecutar `CurrentNoteInputTest` para comprobar notas de crédito/débito externas, rechazo de referencias de otros tipos y rechazo temprano de Boletas en creación/edición. Esta prueba no sustituye la verificación de persistencia de notas sobre documentos locales en una base temporal.

- `CurrentLocalDocumentReferenceTest` complementa las referencias externas con consultas reales en memoria para notas locales de crédito/débito, documentos inexistentes y referencias no admitidas.
- Retirar el selector comentado de Boleta del carrito de restaurante. Los mensajes de restricción NRUS en las validaciones API/Web deben concordar con `nrusDocumentTypeIds()` (sólo Nota de venta); no anunciar series de Boleta que ya no existen.
