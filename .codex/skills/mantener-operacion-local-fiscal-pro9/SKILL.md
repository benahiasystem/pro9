---
name: mantener-operacion-local-fiscal-pro9
description: Mantener en Pro9 la operación comercial local sin generación, almacenamiento, descarga o transmisión de XML/CDR/SUNAT/PSE, sin presentación activa de ISC, detracciones ni controles de impuesto a bolsas plásticas, preservando PDF, datos históricos y contratos internos. Usar al tocar Facturalo, documentos, guías, correo, descargas, items, configuración, reportes, PDF, rutas fiscales o componentes relacionados.
---

# Mantener operación local fiscal de Pro9

## Objetivo

Sostener conjuntamente los contratos de SCRUM-19, SCRUM-22, SCRUM-41, SCRUM-53 y SCRUM-54. Antes de cambiarlos, leer [references/traceability.md](references/traceability.md).

## Política central

- Usar `App\Services\LocalFiscalDocumentPolicy` como única fuente para activar la emisión local y controlar la visibilidad de ISC y detracciones.
- Registrar los documentos con estado local `REGISTERED` y respuesta `LOCAL_REGISTERED`.
- Nunca representar un registro local como enviado, aceptado por SUNAT/SENIAT/PSE ni acompañado de XML, hash o CDR.
- Bloquear XML/CDR en los productores, en `StorageDocument`, en descargas y en adjuntos de correo. Mantener la generación, descarga, impresión y envío por correo del PDF.
- Desregistrar las rutas de envío, validación, consulta de CDR/ticket y regularización fiscal. No basta con ocultar botones si la ruta sigue activa.

## Compatibilidad histórica

- Conservar columnas, modelos, relaciones, casts, recursos API y cálculos de ISC, detracción e impuesto a bolsas cuando puedan existir en registros históricos.
- Ocultar controles interactivos y presentación activa; no borrar datos existentes ni falsear totales persistidos.
- Mantener los nombres internos SUNAT/PSE que sigan siendo contratos técnicos históricos. No renombrarlos a SENIAT sin una integración real.
- Mantener series, correlativos, items, inventario, pagos, notas, PDF y correo comercial.

## Cambios de interfaz y reportes

- En listas de documentos, percepciones, retenciones, liquidaciones y contingencias mostrar PDF, pero no XML/CDR ni reenvío fiscal.
- Eliminar completamente el reporte exclusivo de detracciones: menú, rutas, controlador, recurso, vista y componente.
- En formularios de items, documentos y configuración retirar controles de ISC y detracción.
- En reportes y plantillas PDF gobernar cualquier presentación de ISC con `LocalFiscalDocumentPolicy::showIsc()`.
- Retirar todos los controles `v-model` de `has_plastic_bag_taxes`, conservando el campo en el estado y payload histórico.
- En Hotel, el modal **Agregar Producto o Servicio** no debe mostrar la sección **Agregar Descuentos/Cargos/Atributos especiales**. Ocultarla solo para ese flujo y conservar los datos y payloads históricos de los ítems existentes.

## Marcadores y verificación

- Delimitar cambios con los marcadores exactos documentados en la referencia.
- Ejecutar `tests/Unit/LocalFiscalDocumentPolicyTest.php` y `tests/Unit/JiraInProgressMigrationContractTest.php` junto con las pruebas de contratos Venezuela afectadas.
- Ejecutar validación de sintaxis PHP y `git diff --check`.
- Si se modifican Vue o JavaScript, aplicar `frontend-build` antes de compilar. No ejecutar una compilación por iniciativa propia cuando ese skill la prohíba; informar que el bundle queda pendiente.
