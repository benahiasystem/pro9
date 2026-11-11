# Retirada de la excepción administrativa de IGTF

Implementación del 09/11/2026 solicitada por el usuario. Se retiraron la opción, columnas, motivo, fundamento, acciones y permisos propios de la excepción de los componentes de Pro9. Se conserva un evaluador fiscal por cobro. El 10/11/2026 se retiró la maqueta de paneles independientes y se recuperó la distribución anterior de la factura, manteniendo esta retirada y la simplificación de formularios de cobro.

## Contrato vigente

- Nuevas recepciones: clasificación automática `subject` o `not_applicable`. `RetiredIgtfException` rechaza `exempt` y motivo/fundamento no vacíos con validación 422, incluidos administradores.
- Campos antiguos vacíos/nulos se toleran. La evaluación de perfiles ya no publica `can_authorize_exemption`; las decisiones nuevas no generan fundamento ni actor de autorización.
- Se cubren evaluación, cotización, vista previa, guardado común, transformadores web/API y captura móvil. Los transformadores conservan contexto antiguo para que el guardado pueda verificar un reintento real o rechazar su uso nuevo; no autorizan excepciones.
- El navegador retira los datos antiguos de borradores no registrados, invalida cotizaciones anteriores y reevalúa sin cambiar sus importes, moneda, tasa, fecha ni UUID. Los recibos registrados permanecen intactos.
- Se conservan columnas y snapshots históricos. `RecordedPaymentAmounts` se utiliza después de leer el recibo real bajo los bloqueos existentes: mantiene principal aplicado, impuesto y snapshot originales, incluidos ajustes de centavos y efectivo con vuelto. No toma una bandera del cliente como acreditación del recibo.
- Reintentos con datos distintos se rechazan. Reutilización y reversión siguen sus reglas existentes. No se realizaron migraciones ni depuración de datos. Las categorías fiscales de productos mantienen su función.

## Verificación inicial — 09/11/2026

- **301 pruebas JavaScript correctas**, incluyendo formularios compartidos, factura, POS/Garage, conversiones, crédito, cuotas, pagos mixtos, borradores antiguos y respuestas tardías.
- **109 pruebas PHP correctas, 943 aserciones**, de política fiscal, importes, cotización, vista previa, transformadores, documentos/PDF y guardas de aritmética exacta.
- **MySQL temporal:** se verificaron los ocho escenarios seleccionados de intermediación automática, SPE, retenciones, perfiles, rechazo de la función retirada e históricos. La matriz de rechazo cubre administrador/vendedor, cobro posterior, alta web, transformación API, cotización y vista previa; no quedan documentos, cobros, impuestos, Kardex ni movimientos parciales.
- El fixture antiguo de conversión de nota de venta no incluía el Kardex global requerido por la validación actual de inventario. Se completó mediante las operaciones de inventario existentes y su prueba pasó al repetirse. No se modificó la política de inventario.
- El caso histórico actualizado se repitió con USD recibido, VES aplicado y ajuste de centavos: conserva todos los atributos del recibo tras reintentos, cambios actuales de perfil/tasa/método y vista previa; rechaza cambios de contexto y permite la reversión. La reutilización no añade IGTF ni movimientos.
- Se compilaron las seis plantillas/scripts Vue modificados; lint PHP de 22 archivos y `git diff --check` correctos.

Comandos principales:

```sh
node --test tests/js/fiscal-payment-ui.test.cjs tests/js/igtf-settings-ui.test.cjs tests/js/payment-classification.test.cjs tests/js/quotation-conversion-receipts.test.cjs tests/js/invoice-cash-total.test.cjs tests/js/invoice-payment-validation.test.cjs tests/js/default-receipt-methods.test.cjs tests/js/sale-note-invoice-submit.test.cjs tests/js/payment-edit-preservation.test.cjs tests/js/pos-hka-email.test.cjs tests/js/order-invoice-receipts.test.cjs tests/js/dispatch-invoice-receipts.test.cjs tests/js/subscription-invoice-receipts.test.cjs tests/js/technical-service-invoice-receipts.test.cjs

DB_HOST=127.0.0.1 vendor/bin/phpunit --filter 'SpecialTaxpayerPolicyTest|PaymentQuoteTest|HkaPayloadBuilderTest|FiscalApiPaymentTransformTest|InvoicePaymentPreviewCurrencyTest|MethodCurrencyTest|CurrentPdfRenderingTest|IgtfExceptionRetirementTest|ExactAuditRegistryTest|ExactGuardProofTest' tests/Unit

PRO9_FISCAL_MYSQL_TESTS=1 DB_HOST=127.0.0.1 vendor/bin/phpunit --filter 'test_(retired_igtf|automatic_intermediation|spe_)' tests/Unit/FiscalEmissionSchemaTest.php
```

## Revisión visual histórica — 09/11/2026

Las capturas siguientes acreditan la retirada de controles en la maqueta entonces vigente; su distribución fue revertida el 10/11/2026 y no representa la presentación actual.

Se revisó un borrador sin generar ventas en una pestaña de QA, conservando la pestaña original `353854166`. El viewport temporal se restableció. El watcher existente actualizó los assets; no se ejecutó un build manual.

La revisión de escritorio a 1782 px mostró los paneles alineados y únicamente las columnas Cobro, Clasificación IGTF e IGTF. No hay controles ni textos de autorización, motivo o fundamento. En móvil a 390 px se conserva el orden resumen/fiscal/pagos y el desplazamiento horizontal de las tablas.

La captura cargó `app-983e696d.js` y `app-a9bcc413.css`, coincidentes con el manifiesto durante la comprobación.

- [Escritorio](evidencias/sin_excepcion_igtf_escritorio_2026_11_09.jpg)
- [Móvil](evidencias/sin_excepcion_igtf_movil_2026_11_09.jpg)
- [Mediciones y assets observados](evidencias/sin_excepcion_igtf_2026_11_09.json)

## Reversión de presentación y verificación — 10/11/2026

Por decisión del usuario se retiraron únicamente la maqueta de cobro y el traslado independiente del importe recibido a una columna. Se recuperaron el contenedor original de totales, la condición de pago y el resumen textual. Permanecen la intermediación y moneda automáticas, la tasa de cabecera para cobros nuevos y todos los rechazos y compatibilidades históricas de la retirada de excepciones. Los cambios anteriores a este chat y los de otros chats se conservaron; HEAD y el índice de Git permanecen intactos.

Se eliminaron el componente, auxiliar, pruebas y evidencias exclusivos de la maqueta. Las pruebas de limpieza de borradores antiguos y de respuestas pendientes tras destruir un controlador se conservaron en `fiscal-payment-ui.test.cjs`. El formulario mantiene una instancia fiscal por cobro y presenta los errores, importes recibidos y datos de conversión dentro de la distribución anterior.

- Se verificaron **292 casos JavaScript** de los 14 archivos indicados arriba. La primera ejecución detectó una aserción que aún esperaba los dos componentes alternativos de la columna retirada; se restauró la expectativa de uno y pasaron los 39 casos del archivo afectado.
- Regresiones PHP: **109 pruebas y 943 aserciones correctas**. Lint correcto en 31 archivos PHP; plantillas y scripts Vue de factura y campos fiscales válidos.
- Persistencia en MySQL temporal: **9 pruebas y 1.717 aserciones correctas**. Cubren intermediación automática, contradicciones, perfiles SPE en Web/POS/Garage, crédito, comisiones bancarias, rechazo de excepciones nuevas, rollback y conservación de históricos en reintentos, vista previa, reutilización y reversión. Se ejecutó el filtro anterior ampliado con `bank_commission_uses_automatic`.
- `git diff --check` correcto; la comprobación contra la copia inicial conserva los archivos anteriores y de otros chats, así como HEAD y el índice.
- El watcher existente generó `app-1f7de19d.js`; se comprobó que el bundle ya no incluye la maqueta, la columna retirada ni los controles redundantes o de excepción. No se lanzó una compilación ni se editaron assets manualmente.
- La revisión visual de escritorio y móvil quedó pendiente: la pestaña de QA de `/documents/create` redirigió al inicio de sesión de BBC. Se conservaron las pestañas existentes y no se generaron ventas.

La copia recuperable de archivos y parches y los logs de esta verificación están fuera del repositorio, en `/home/benahia/.codex/backups/pro9-reversion-cobros-20261110T153725Z`.
