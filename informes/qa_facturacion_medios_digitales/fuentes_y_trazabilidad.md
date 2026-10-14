# Fuentes y trazabilidad de la especificación

Referencia actual: `76690125` y cambios locales de órdenes, 14 de octubre de 2026. Los SHA-256 actuales están en la sección de ampliación del 14 de octubre. Las tablas anteriores conservan la referencia histórica `87dc6506cf8d94c6e2d3cad1fb512b5b62e5e572`, del 12 de octubre, para trazabilidad.

Las expectativas proceden del contrato actual de Venezuela, las políticas de producto y los puntos de implementación locales. Esta especificación no evalúa normativa externa. La lectura de código y los informes históricos sirven para diseñar pruebas; no acreditan su ejecución en esta corrida.

## Cómo resolver contradicciones

Algunos informes acumulan historia: un párrafo antiguo puede decir que PDF/HKA/exportación/edición no estaban integrados. Consulta primero las políticas y habilidades vigentes y las ampliaciones posteriores enlazadas. Ejemplos: preparado ya no bloquea siempre edición; Estado HKA pasó a Estado; PDF HKA y exportación web se incorporaron después. Si implementación y contrato actual difieren, conserva la diferencia como defecto o aclaración pendiente. No cambies esperado para que el caso pase ni afirmes una capacidad por una fila de catálogo.

Cada grupo referencia sus fuentes. Los hashes siguientes permiten detectar cambios después de redactar la guía. Los archivos de pruebas se enumeran como apoyo; ejecución de fuente/método, HTTP, navegador y Real DEMO son capas diferentes.

| Grupo | Base contractual / punto de implementación | Casos |
|---|---|---|
|ENT|[routes/web.php](../../routes/web.php), [routes/api.php](../../routes/api.php), `modules/*/Routes` (patrón o punto de referencia; resolver en la versión de la corrida), [resources/views/tenant/documents/form.blade.php](../../resources/views/tenant/documents/form.blade.php)|QA-ENT-001 a QA-ENT-012|
|CFG|[app/Services/FiscalEmissionSettings.php](../../app/Services/FiscalEmissionSettings.php), [app/Services/Fiscal/HkaAuthentication.php](../../app/Services/Fiscal/HkaAuthentication.php), [.codex/skills/configurar-conexion-hka-pro 9/SKILL.md](../../.codex/skills/configurar-conexion-hka-pro9/SKILL.md)|QA-CFG-001 a QA-CFG-017|
|NUM|[app/Services/SeriesNumbering.php](../../app/Services/SeriesNumbering.php), [app/Services/SeriesAdministration.php](../../app/Services/SeriesAdministration.php), [app/Services/SeriesResolver.php](../../app/Services/SeriesResolver.php), [app/Services/Fiscal/FiscalIdentity.php](../../app/Services/Fiscal/FiscalIdentity.php), [.codex/skills/mantener-numeracion-fiscal-venezuela-pro 9/SKILL.md](../../.codex/skills/mantener-numeracion-fiscal-venezuela-pro9/SKILL.md)|QA-NUM-001 a QA-NUM-021|
|CLI|[app/Services/SalesCustomerIdentityPolicy.php](../../app/Services/SalesCustomerIdentityPolicy.php), [resources/js/views/tenant/persons/form.vue](../../resources/js/views/tenant/persons/form.vue), [resources/js/views/tenant/documents/_document_form_fields_catalog.js](../../resources/js/views/tenant/documents/_document_form_fields_catalog.js), [.codex/skills/gestionar-clientes-venezuela/SKILL.md](../../.codex/skills/gestionar-clientes-venezuela/SKILL.md)|QA-CLI-001 a QA-CLI-018|
|ITM|[resources/js/views/tenant/documents/partials/item.vue](../../resources/js/views/tenant/documents/partials/item.vue), [app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [.codex/skills/mantener-flujo-productos-pro 9/SKILL.md](../../.codex/skills/mantener-flujo-productos-pro9/SKILL.md)|QA-ITM-001 a QA-ITM-025|
|CAL|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [config/venezuela.php](../../config/venezuela.php), [tests/Unit/FiscalLineCalculationTest.php](../../tests/Unit/FiscalLineCalculationTest.php)|QA-CAL-001 a QA-CAL-019|
|FAC|[resources/js/views/tenant/documents/invoice_generate.vue](../../resources/js/views/tenant/documents/invoice_generate.vue), [app/CoreFacturalo/Facturalo.php](../../app/CoreFacturalo/Facturalo.php), [app/Http/Controllers/Tenant/DocumentController.php](../../app/Http/Controllers/Tenant/DocumentController.php)|QA-FAC-001 a QA-FAC-016|
|EXP|[app/Services/ExportInvoicePolicy.php](../../app/Services/ExportInvoicePolicy.php), [informes/facturas_exportacion_hka.md](../../informes/facturas_exportacion_hka.md), [tests/Unit/ExportInvoiceTest.php](../../tests/Unit/ExportInvoiceTest.php)|QA-EXP-001 a QA-EXP-018|
|TER|[app/Services/ThirdPartyInvoicePolicy.php](../../app/Services/ThirdPartyInvoicePolicy.php), [app/Services/Fiscal/HkaThirdPartyMoney.php](../../app/Services/Fiscal/HkaThirdPartyMoney.php), [informes/facturas_cuenta_terceros_hka.md](../../informes/facturas_cuenta_terceros_hka.md)|QA-TER-001 a QA-TER-015|
|NOT|[resources/js/views/tenant/documents/note.vue](../../resources/js/views/tenant/documents/note.vue), [app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [app/Services/Fiscal/HkaPayloadBuilder.php](../../app/Services/Fiscal/HkaPayloadBuilder.php), [database/seeders/data/tenant_initial_data.php](../../database/seeders/data/tenant_initial_data.php)|QA-NOT-001 a QA-NOT-019|
|PAG|[app/Services/Fiscal/FiscalAmounts.php](../../app/Services/Fiscal/FiscalAmounts.php), [app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [app/Http/Controllers/Tenant/DocumentPaymentController.php](../../app/Http/Controllers/Tenant/DocumentPaymentController.php), [tests/Unit/FiscalApiPaymentTransformTest.php](../../tests/Unit/FiscalApiPaymentTransformTest.php)|QA-PAG-001 a QA-PAG-022|
|IGT|[app/Services/Fiscal/FiscalAmounts.php](../../app/Services/Fiscal/FiscalAmounts.php), [app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [resources/js/views/tenant/documents/partials/igtf_fields.vue](../../resources/js/views/tenant/documents/partials/igtf_fields.vue)|QA-IGT-001 a QA-IGT-014|
|RET|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [app/Http/Controllers/Tenant/DocumentFiscalController.php](../../app/Http/Controllers/Tenant/DocumentFiscalController.php), [resources/js/views/tenant/documents/partials/retention.vue](../../resources/js/views/tenant/documents/partials/retention.vue)|QA-RET-001 a QA-RET-019|
|EDI|[app/Services/Fiscal/DocumentEditPolicy.php](../../app/Services/Fiscal/DocumentEditPolicy.php), [app/Services/Fiscal/DocumentEditSettlements.php](../../app/Services/Fiscal/DocumentEditSettlements.php), [app/Http/Requests/Tenant/DocumentUpdateRequest.php](../../app/Http/Requests/Tenant/DocumentUpdateRequest.php), [informes/edicion_facturas_antes_hka.md](../../informes/edicion_facturas_antes_hka.md)|QA-EDI-001 a QA-EDI-020|
|HKA|[app/Services/Fiscal/HkaEmissionPreparation.php](../../app/Services/Fiscal/HkaEmissionPreparation.php), [app/Services/Fiscal/HkaEmission.php](../../app/Services/Fiscal/HkaEmission.php), [app/Services/Fiscal/HkaResponse.php](../../app/Services/Fiscal/HkaResponse.php), [app/Services/Fiscal/HkaTransport.php](../../app/Services/Fiscal/HkaTransport.php)|QA-HKA-001 a QA-HKA-025|
|PDF|[app/Services/Fiscal/HkaPdf.php](../../app/Services/Fiscal/HkaPdf.php), [app/Services/Fiscal/HkaPdfStore.php](../../app/Services/Fiscal/HkaPdfStore.php), [app/Services/Fiscal/HkaA5Pdf.php](../../app/Services/Fiscal/HkaA5Pdf.php), [app/Services/Fiscal/HkaTicketPdf.php](../../app/Services/Fiscal/HkaTicketPdf.php), [.codex/skills/distribuir-documentos-hka/SKILL.md](../../.codex/skills/distribuir-documentos-hka/SKILL.md)|QA-PDF-001 a QA-PDF-025|
|COR|[app/Services/Fiscal/HkaMail.php](../../app/Services/Fiscal/HkaMail.php), [resources/js/mixins/document-email.js](../../resources/js/mixins/document-email.js), [resources/js/views/tenant/documents/partials/options.vue](../../resources/js/views/tenant/documents/partials/options.vue), [resources/js/views/tenant/pos/partials/options.vue](../../resources/js/views/tenant/pos/partials/options.vue)|QA-COR-001 a QA-COR-019|
|LIS|[resources/js/views/tenant/documents/index.vue](../../resources/js/views/tenant/documents/index.vue), [resources/js/views/tenant/documents/partials/detail-drawer.vue](../../resources/js/views/tenant/documents/partials/detail-drawer.vue), [resources/js/helpers/document-status.js](../../resources/js/helpers/document-status.js), [.codex/skills/mantener-operacion-local-fiscal-pro 9/SKILL.md](../../.codex/skills/mantener-operacion-local-fiscal-pro9/SKILL.md)|QA-LIS-001 a QA-LIS-016|
|NVE|[resources/js/views/tenant/sale_notes](../../resources/js/views/tenant/sale_notes), [modules/Sale/Http/Controllers/GenerateDocumentController.php](../../modules/Sale/Http/Controllers/GenerateDocumentController.php), [tests/js/sale-note-invoice-submit.test.cjs](../../tests/js/sale-note-invoice-submit.test.cjs), [.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|QA-NVE-001 a QA-NVE-014|
|CAN|[routes/web.php](../../routes/web.php), [routes/api.php](../../routes/api.php), [modules/Hotel/Routes/web.php](../../modules/Hotel/Routes/web.php), [modules/Restaurant/Routes](../../modules/Restaurant/Routes), [modules/Ecommerce/Routes](../../modules/Ecommerce/Routes), [modules/Store/Routes](../../modules/Store/Routes), [modules/WhatsAppBot/Services/Tools/CreateDocumentTool.php](../../modules/WhatsAppBot/Services/Tools/CreateDocumentTool.php), [.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|QA-CAN-001 a QA-CAN-040|
|API|[routes/api.php](../../routes/api.php), [app/Http/Controllers/Tenant/Api/DocumentController.php](../../app/Http/Controllers/Tenant/Api/DocumentController.php), [app/CoreFacturalo/Requests/Api/Transform/DocumentTransform.php](../../app/CoreFacturalo/Requests/Api/Transform/DocumentTransform.php)|QA-API-001 a QA-API-008|
|IMP|[app/Http/Controllers/Tenant/DocumentController.php](../../app/Http/Controllers/Tenant/DocumentController.php), [app/Services/MassiveInvoiceService.php](../../app/Services/MassiveInvoiceService.php), [app/Http/Controllers/System/MassiveInvoiceController.php](../../app/Http/Controllers/System/MassiveInvoiceController.php), [public/formats/documents_second_format.xlsx](../../public/formats/documents_second_format.xlsx), [public/formats/formato_facturas_masivas.xlsx](../../public/formats/formato_facturas_masivas.xlsx)|QA-IMP-001 a QA-IMP-012|
|ANU|[app/Http/Controllers/Tenant/VoidedController.php](../../app/Http/Controllers/Tenant/VoidedController.php), [app/Http/Controllers/Tenant/DocumentController.php](../../app/Http/Controllers/Tenant/DocumentController.php), [tests/Unit/TestDocumentsDeletionBehaviorTest.php](../../tests/Unit/TestDocumentsDeletionBehaviorTest.php), [tests/Unit/TestDocumentsDeletionContractTest.php](../../tests/Unit/TestDocumentsDeletionContractTest.php)|QA-ANU-001 a QA-ANU-009|
|FIN|[modules/Finance/Routes/web.php](../../modules/Finance/Routes/web.php), [modules/Report/Routes/web.php](../../modules/Report/Routes/web.php), [modules/Dashboard](../../modules/Dashboard), [resources/views/pdf/partials/document_fiscal_totals.blade.php](../../resources/views/pdf/partials/document_fiscal_totals.blade.php)|QA-FIN-001 a QA-FIN-010|
|SEG|[app/Http/Controllers/Tenant/DocumentFiscalController.php](../../app/Http/Controllers/Tenant/DocumentFiscalController.php), [app/Http/Controllers/Tenant/DocumentPdfController.php](../../app/Http/Controllers/Tenant/DocumentPdfController.php), [.codex/skills/mantener-seguridad-pro 9/SKILL.md](../../.codex/skills/mantener-seguridad-pro9/SKILL.md), [informes/auditoria_seguridad_pro9.md](../../informes/auditoria_seguridad_pro9.md)|QA-SEG-001 a QA-SEG-012|
|ROB|[tests/Unit/FiscalEmissionSchemaTest.php](../../tests/Unit/FiscalEmissionSchemaTest.php), [tests/Support/fiscal_payment_concurrency_worker.php](../../tests/Support/fiscal_payment_concurrency_worker.php), [tests/js](../../tests/js), `flujo completo de facturación` (patrón o punto de referencia; resolver en la versión de la corrida)|QA-ROB-001 a QA-ROB-006|
|MAT|[app/Services/SalesCustomerIdentityPolicy.php](../../app/Services/SalesCustomerIdentityPolicy.php), [.codex/skills/gestionar-clientes-venezuela/SKILL.md](../../.codex/skills/gestionar-clientes-venezuela/SKILL.md), [.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|QA-MAT-001 a QA-MAT-208|
|NEG|[app/Services/SalesDocumentTypePolicy.php](../../app/Services/SalesDocumentTypePolicy.php), [app/Services/ExportInvoicePolicy.php](../../app/Services/ExportInvoicePolicy.php), [app/Services/ThirdPartyInvoicePolicy.php](../../app/Services/ThirdPartyInvoicePolicy.php), [.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|QA-NEG-001 a QA-NEG-019|
|PRE|[app/CoreFacturalo/Facturalo.php](../../app/CoreFacturalo/Facturalo.php), [app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [resources/js/views/tenant/documents/invoice_generate.vue](../../resources/js/views/tenant/documents/invoice_generate.vue), [modules/Document/Routes/web.php](../../modules/Document/Routes/web.php)|QA-PRE-001 a QA-PRE-012|
|COM|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [resources/js/views/tenant/documents/invoice_generate.vue](../../resources/js/views/tenant/documents/invoice_generate.vue), [config/venezuela.php](../../config/venezuela.php)|QA-COM-001 a QA-COM-030|

|RGE|[app/Services/SpecialTaxRegimePolicy.php](../../app/Services/SpecialTaxRegimePolicy.php), [app/Services/Fiscal/HkaDomesticInvoicePolicy.php](../../app/Services/Fiscal/HkaDomesticInvoicePolicy.php), [app/Services/Fiscal/HkaDomesticMoney.php](../../app/Services/Fiscal/HkaDomesticMoney.php), [app/Services/Fiscal/HkaPayloadBuilder.php](../../app/Services/Fiscal/HkaPayloadBuilder.php), [tests/Unit/SpecialTaxRegimeTest.php](../../tests/Unit/SpecialTaxRegimeTest.php), [tests/js/special-tax-regime-ui.test.cjs](../../tests/js/special-tax-regime-ui.test.cjs), [informes/facturas_regimenes_especiales_hka.md](../../informes/facturas_regimenes_especiales_hka.md)|QA-RGE-001 a QA-RGE-059|
|SPE|[app/Services/Fiscal/SpecialTaxpayerPolicy.php](../../app/Services/Fiscal/SpecialTaxpayerPolicy.php), [app/Services/Fiscal/FiscalProfileService.php](../../app/Services/Fiscal/FiscalProfileService.php), [app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [app/Http/Controllers/Tenant/FiscalProfileController.php](../../app/Http/Controllers/Tenant/FiscalProfileController.php), [tests/Unit/SpecialTaxpayerPolicyTest.php](../../tests/Unit/SpecialTaxpayerPolicyTest.php), [tests/Support/SpeProfiles.php](../../tests/Support/SpeProfiles.php), [tests/Support/SpePersistenceCases.php](../../tests/Support/SpePersistenceCases.php), [informes/facturas_sujetos_pasivos_especiales_hka.md](../../informes/facturas_sujetos_pasivos_especiales_hka.md)|QA-SPE-001 a QA-SPE-064|
|RIVA|[app/Services/Fiscal/ReceivedRetentionService.php](../../app/Services/Fiscal/ReceivedRetentionService.php), [app/Http/Controllers/Tenant/IvaRetentionController.php](../../app/Http/Controllers/Tenant/IvaRetentionController.php), [app/Services/Fiscal/HkaReceivedRetention.php](../../app/Services/Fiscal/HkaReceivedRetention.php), [routes/web.php](../../routes/web.php), [routes/api.php](../../routes/api.php), [app/Services/Fiscal/IvaRetentionService.php](../../app/Services/Fiscal/IvaRetentionService.php), [app/Services/Fiscal/HkaIvaRetention.php](../../app/Services/Fiscal/HkaIvaRetention.php), [app/Services/Fiscal/HkaIvaRetentionPayload.php](../../app/Services/Fiscal/HkaIvaRetentionPayload.php), [app/Services/Fiscal/HkaIvaRetentionCapability.php](../../app/Services/Fiscal/HkaIvaRetentionCapability.php), [tests/Unit/HkaIvaRetentionPayloadTest.php](../../tests/Unit/HkaIvaRetentionPayloadTest.php), [tests/Unit/IvaRetentionHttpContractTest.php](../../tests/Unit/IvaRetentionHttpContractTest.php), [tests/Support/IvaRetentionPersistenceCases.php](../../tests/Support/IvaRetentionPersistenceCases.php), [tests/js/iva-retention-ui.test.cjs](../../tests/js/iva-retention-ui.test.cjs), [informes/retenciones_iva_recibidas_hka.md](../../informes/retenciones_iva_recibidas_hka.md)|QA-RIVA-001 a QA-RIVA-063|
|RISLR|[app/Services/Fiscal/ReceivedRetentionService.php](../../app/Services/Fiscal/ReceivedRetentionService.php), [app/Http/Controllers/Tenant/IvaRetentionController.php](../../app/Http/Controllers/Tenant/IvaRetentionController.php), [app/Services/Fiscal/HkaReceivedRetention.php](../../app/Services/Fiscal/HkaReceivedRetention.php), [routes/web.php](../../routes/web.php), [routes/api.php](../../routes/api.php), [app/Services/Fiscal/IslrRetentionService.php](../../app/Services/Fiscal/IslrRetentionService.php), [app/Services/Fiscal/HkaIslrRetention.php](../../app/Services/Fiscal/HkaIslrRetention.php), [app/Services/Fiscal/HkaIslrRetentionPayload.php](../../app/Services/Fiscal/HkaIslrRetentionPayload.php), [app/Services/Fiscal/HkaIslrRetentionCapability.php](../../app/Services/Fiscal/HkaIslrRetentionCapability.php), [database/seeders/data/tenant_initial_data.php](../../database/seeders/data/tenant_initial_data.php), [tests/Unit/HkaIslrRetentionTest.php](../../tests/Unit/HkaIslrRetentionTest.php), [tests/Support/IslrRetentionPersistenceCases.php](../../tests/Support/IslrRetentionPersistenceCases.php), [informes/retenciones_islr_recibidas_hka.md](../../informes/retenciones_islr_recibidas_hka.md)|QA-RISLR-001 a QA-RISLR-135|
|INT|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [app/Services/Fiscal/ReceivedRetentionService.php](../../app/Services/Fiscal/ReceivedRetentionService.php), [app/Services/Fiscal/HkaIvaRetentionCapability.php](../../app/Services/Fiscal/HkaIvaRetentionCapability.php), [app/Services/Fiscal/HkaIslrRetentionCapability.php](../../app/Services/Fiscal/HkaIslrRetentionCapability.php), [resources/js/mixins/iva-retention.js](../../resources/js/mixins/iva-retention.js), [resources/js/views/tenant/documents/partials/payments.vue](../../resources/js/views/tenant/documents/partials/payments.vue), [resources/js/views/tenant/pos/partials/payment.vue](../../resources/js/views/tenant/pos/partials/payment.vue), [tests/js/fiscal-payment-ui.test.cjs](../../tests/js/fiscal-payment-ui.test.cjs), [tests/Support/IvaRetentionPersistenceCases.php](../../tests/Support/IvaRetentionPersistenceCases.php), [tests/Support/IslrRetentionPersistenceCases.php](../../tests/Support/IslrRetentionPersistenceCases.php), [routes/web.php](../../routes/web.php), [routes/api.php](../../routes/api.php)|QA-INT-001 a QA-INT-080|

## Hashes de archivos concretos de referencia

| Grupo | Archivo | SHA-256 |
|---|---|---|
|ENT|[routes/web.php](../../routes/web.php)|`6c500843fe5901f1e0aed5f4374bbab21baa4db61710ee5cfc375d91a322aff1`|
|ENT|[routes/api.php](../../routes/api.php)|`8b96ee2432356bdb235712f144fe691a602b386d27ce4fddf87307d45b121718`|
|ENT|[resources/views/tenant/documents/form.blade.php](../../resources/views/tenant/documents/form.blade.php)|`056f80f86f33366ee5cdc9244202745e3a9b4351eaad1a56a2196287256747e2`|
|CFG|[app/Services/FiscalEmissionSettings.php](../../app/Services/FiscalEmissionSettings.php)|`ab8d1147c6f8583ab538cda26b5c461425f02ff8fb1850fd2f89bc3ecb41193c`|
|CFG|[app/Services/Fiscal/HkaAuthentication.php](../../app/Services/Fiscal/HkaAuthentication.php)|`540b09ab781f2d6fdb8735bcde280608dd632e71e8429b4d46a94b3820cb69e0`|
|CFG|[.codex/skills/configurar-conexion-hka-pro 9/SKILL.md](../../.codex/skills/configurar-conexion-hka-pro9/SKILL.md)|`b67a78e0c17e68af9146973590de59bccd742229d6fdb13559b9910c23732844`|
|NUM|[app/Services/SeriesNumbering.php](../../app/Services/SeriesNumbering.php)|`08e7847a5e82edb3c075318a87c81ade18000a6dde7b01ff16e615076ea544ae`|
|NUM|[app/Services/SeriesAdministration.php](../../app/Services/SeriesAdministration.php)|`0ab98ebb8cce26ab7d92317a9f3590f15e755895a52e3252c5f2b21eaf140e42`|
|NUM|[app/Services/SeriesResolver.php](../../app/Services/SeriesResolver.php)|`f1dd2363b6890bc05aead66f2e785c6abc3c08190b4d98fe5ea2ba9b20c0f240`|
|NUM|[app/Services/Fiscal/FiscalIdentity.php](../../app/Services/Fiscal/FiscalIdentity.php)|`f4279648d961e6696beffbe7e961184fde12397e300c17bfb5f407f52ec4c8e9`|
|NUM|[.codex/skills/mantener-numeracion-fiscal-venezuela-pro 9/SKILL.md](../../.codex/skills/mantener-numeracion-fiscal-venezuela-pro9/SKILL.md)|`e822ac1ab23417275ff75c485bfb535389ac0392a013e07a2ea8b09ceb1ae2ff`|
|CLI|[app/Services/SalesCustomerIdentityPolicy.php](../../app/Services/SalesCustomerIdentityPolicy.php)|`6cd9704d7536bb9a4c4e0a7b55899f2e346cf07a494f5371990535fa255321c9`|
|CLI|[resources/js/views/tenant/persons/form.vue](../../resources/js/views/tenant/persons/form.vue)|`1e1fd47ef1c33030314c25b83f7437f7a29d94c8f6ede92e628178206ae719c8`|
|CLI|[resources/js/views/tenant/documents/_document_form_fields_catalog.js](../../resources/js/views/tenant/documents/_document_form_fields_catalog.js)|`4f17e555df74e296470dfda38668eceda8412508432c504c3a90abac634d0929`|
|CLI|[.codex/skills/gestionar-clientes-venezuela/SKILL.md](../../.codex/skills/gestionar-clientes-venezuela/SKILL.md)|`15bfdf3e21ed27c5dabbd5480b228a312ee73511474116d7a69cf0df9bcb7b77`|
|ITM|[resources/js/views/tenant/documents/partials/item.vue](../../resources/js/views/tenant/documents/partials/item.vue)|`5f375a86dd39a5ac35748065df599476c85796fb2efda93a306c748c63bd321a`|
|ITM|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`be6a8820f96a059343cd765803016d930910e6e480e7d92b90b63eef6bb1413a`|
|ITM|[.codex/skills/mantener-flujo-productos-pro 9/SKILL.md](../../.codex/skills/mantener-flujo-productos-pro9/SKILL.md)|`45516ef1e4873a92a9b2ba00525095c69be76a43c2e1132e97ce8fd49fbc2f4a`|
|CAL|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`be6a8820f96a059343cd765803016d930910e6e480e7d92b90b63eef6bb1413a`|
|CAL|[config/venezuela.php](../../config/venezuela.php)|`787a3a2cdb0b7eff3efff7762e78f818f3e049ca6fd38d2a422e0e1ce2d99e0d`|
|CAL|[tests/Unit/FiscalLineCalculationTest.php](../../tests/Unit/FiscalLineCalculationTest.php)|`67b93742ddf0812b0e89e81f0eea7544782bbffd580f7be6b1d0c06c292106c6`|
|FAC|[resources/js/views/tenant/documents/invoice_generate.vue](../../resources/js/views/tenant/documents/invoice_generate.vue)|`3e8f79e1e1d7197e6b39a148f0cb44c2f3a5916632f820bdc539d9744c127d19`|
|FAC|[app/CoreFacturalo/Facturalo.php](../../app/CoreFacturalo/Facturalo.php)|`4db60bcd803130e248ce367483d26bb91c1b6a377c04f4d61aa964dbf98e9753`|
|FAC|[app/Http/Controllers/Tenant/DocumentController.php](../../app/Http/Controllers/Tenant/DocumentController.php)|`488950347d3d5206eb819d512384ed0925460cbef31eddcc5ffea3adb52fdf61`|
|EXP|[app/Services/ExportInvoicePolicy.php](../../app/Services/ExportInvoicePolicy.php)|`42135c3e441da619a0758595ad7e7225f6d431f476aa3a93a852b21c0b1cbe92`|
|EXP|[informes/facturas_exportacion_hka.md](../../informes/facturas_exportacion_hka.md)|`6b8cf2083ede3cec8b1d10e66345a219d2f4a7e8f5a25aea300925764385ffa7`|
|EXP|[tests/Unit/ExportInvoiceTest.php](../../tests/Unit/ExportInvoiceTest.php)|`f90b608053b1509fab7f18d8b6190d98d0df67074065f7e1aaed801106c99fab`|
|TER|[app/Services/ThirdPartyInvoicePolicy.php](../../app/Services/ThirdPartyInvoicePolicy.php)|`eabd94949b996530d03f148a18a69c0c6253717c235621503560666219814386`|
|TER|[app/Services/Fiscal/HkaThirdPartyMoney.php](../../app/Services/Fiscal/HkaThirdPartyMoney.php)|`6abb1f5920a5d64e0d754db62553b1585c8c25befec4d29acfd8fc2b8e047fcd`|
|TER|[informes/facturas_cuenta_terceros_hka.md](../../informes/facturas_cuenta_terceros_hka.md)|`a7da24d7e68d8083c23a2fb3c9fceab2f947cc0ad96fdb9a916dd73f88479700`|
|NOT|[resources/js/views/tenant/documents/note.vue](../../resources/js/views/tenant/documents/note.vue)|`4eaa224a7f3fdd58b6e0f36d420926674532d505cc78585dd9fe08094175b937`|
|NOT|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`be6a8820f96a059343cd765803016d930910e6e480e7d92b90b63eef6bb1413a`|
|NOT|[app/Services/Fiscal/HkaPayloadBuilder.php](../../app/Services/Fiscal/HkaPayloadBuilder.php)|`a0d89df10d1df35ee19f672f319713971b92b53b6c43e92c675824e84e80e7cb`|
|NOT|[database/seeders/data/tenant_initial_data.php](../../database/seeders/data/tenant_initial_data.php)|`128a187ae7a57e259854e64160dbab0d2e4e2d06d107427c2dd5d8de39afc450`|
|PAG|[app/Services/Fiscal/FiscalAmounts.php](../../app/Services/Fiscal/FiscalAmounts.php)|`7494070df30982c79a09bb7051ad13cbccc2f12ffa50a9453d6506baaf4795b9`|
|PAG|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`be6a8820f96a059343cd765803016d930910e6e480e7d92b90b63eef6bb1413a`|
|PAG|[app/Http/Controllers/Tenant/DocumentPaymentController.php](../../app/Http/Controllers/Tenant/DocumentPaymentController.php)|`fe1f556e8769e69470bd26c308224256d9fa2f54d88a2143cd1d52aa4cd9e1ce`|
|PAG|[tests/Unit/FiscalApiPaymentTransformTest.php](../../tests/Unit/FiscalApiPaymentTransformTest.php)|`97e8b993a37305d402a2f55be02c77b63dca559053a2f4d8aa254e8dc0488a62`|
|IGT|[app/Services/Fiscal/FiscalAmounts.php](../../app/Services/Fiscal/FiscalAmounts.php)|`7494070df30982c79a09bb7051ad13cbccc2f12ffa50a9453d6506baaf4795b9`|
|IGT|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`be6a8820f96a059343cd765803016d930910e6e480e7d92b90b63eef6bb1413a`|
|IGT|[resources/js/views/tenant/documents/partials/igtf_fields.vue](../../resources/js/views/tenant/documents/partials/igtf_fields.vue)|`98c5799515f9abeabd3875977c11fa64f267e55c290eaf77bbfac66cef2760a2`|
|RET|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`be6a8820f96a059343cd765803016d930910e6e480e7d92b90b63eef6bb1413a`|
|RET|[app/Http/Controllers/Tenant/DocumentFiscalController.php](../../app/Http/Controllers/Tenant/DocumentFiscalController.php)|`9f0014cd5dd617c59c7017e33ed300374f4a891f50d788cdb731c09bc632079f`|
|RET|[resources/js/views/tenant/documents/partials/retention.vue](../../resources/js/views/tenant/documents/partials/retention.vue)|`831d94b0109e303afbf96a25257709ccb0554e77e803bec8ea345f97ca7d4ad3`|
|EDI|[app/Services/Fiscal/DocumentEditPolicy.php](../../app/Services/Fiscal/DocumentEditPolicy.php)|`37d86de8c6befc881e30df1af322a34e3fa747dcc6aacd1b425b3c5519af7c15`|
|EDI|[app/Services/Fiscal/DocumentEditSettlements.php](../../app/Services/Fiscal/DocumentEditSettlements.php)|`ebcb3e36f356396af2a0bce72e7826ae30f68692a281822c3d0e2545207b7296`|
|EDI|[app/Http/Requests/Tenant/DocumentUpdateRequest.php](../../app/Http/Requests/Tenant/DocumentUpdateRequest.php)|`da9aaf514026d40b6a0979b2c01ce1887b1465672323324972888a7d62683908`|
|EDI|[informes/edicion_facturas_antes_hka.md](../../informes/edicion_facturas_antes_hka.md)|`3c3cb0806881edd6e6264d248f024ba152c3d8cf12cc221cc013b42feecc3032`|
|HKA|[app/Services/Fiscal/HkaEmissionPreparation.php](../../app/Services/Fiscal/HkaEmissionPreparation.php)|`d665da33e4c78e26b53f3955f70cda35c134050101a0033e493bd82028cedd51`|
|HKA|[app/Services/Fiscal/HkaEmission.php](../../app/Services/Fiscal/HkaEmission.php)|`5fa0f7444714804b114c2e86b00be1a53fe8ffe08a48d8765f925df871ea92f3`|
|HKA|[app/Services/Fiscal/HkaResponse.php](../../app/Services/Fiscal/HkaResponse.php)|`b5d3055c2cc900191af10599c44421e32a7ce5fbedd0bdf4da1195ac83bdc1cc`|
|HKA|[app/Services/Fiscal/HkaTransport.php](../../app/Services/Fiscal/HkaTransport.php)|`3d244cc528e88b7cbb6c226e52c7b1a5163fcb81e46fccb3591b62a34c07abdc`|
|PDF|[app/Services/Fiscal/HkaPdf.php](../../app/Services/Fiscal/HkaPdf.php)|`f37d8f8b5d62a77580fbc6ca63b41bc0de3f3a25ef3a65d2590eb58765bdc048`|
|PDF|[app/Services/Fiscal/HkaPdfStore.php](../../app/Services/Fiscal/HkaPdfStore.php)|`b679292264601bba37d4ff5c3389e223c9f36886725959e422b259abab0ee2dc`|
|PDF|[app/Services/Fiscal/HkaA5Pdf.php](../../app/Services/Fiscal/HkaA5Pdf.php)|`2c6608d2693da4ab69d286a6e00f3aafc44a13c3fa1b76ccf7807f4f183dd28d`|
|PDF|[app/Services/Fiscal/HkaTicketPdf.php](../../app/Services/Fiscal/HkaTicketPdf.php)|`b773b732555c23edde021c50184c62fe666fa5ec67715a9f5774b37dd54515b2`|
|PDF|[.codex/skills/distribuir-documentos-hka/SKILL.md](../../.codex/skills/distribuir-documentos-hka/SKILL.md)|`d63a96f40d2463354e999d056ddcf4e7c7140045db442c5d4dfb0416973e7977`|
|COR|[app/Services/Fiscal/HkaMail.php](../../app/Services/Fiscal/HkaMail.php)|`29608273ad91d34ad1ff34fe0a6c4735475957c03ef91bcb56bfc4db1d399ce7`|
|COR|[resources/js/mixins/document-email.js](../../resources/js/mixins/document-email.js)|`e3f4e186f1457cdcbe184ce44e8fa2c5682e1ee1a5d0de1c2032d141696d2630`|
|COR|[resources/js/views/tenant/documents/partials/options.vue](../../resources/js/views/tenant/documents/partials/options.vue)|`d21e197dad421934b14c39608b03e95a55ce56584b2242a8c8237c0297062112`|
|COR|[resources/js/views/tenant/pos/partials/options.vue](../../resources/js/views/tenant/pos/partials/options.vue)|`068f7b3f1bf26eefc48319c460c0b966a56b6d51477769218c845baa47e0f74e`|
|LIS|[resources/js/views/tenant/documents/index.vue](../../resources/js/views/tenant/documents/index.vue)|`795de4cb9b0f2a7bc7e080e2361e56e846147bcc2cf280f7be40c00fe610dd59`|
|LIS|[resources/js/views/tenant/documents/partials/detail-drawer.vue](../../resources/js/views/tenant/documents/partials/detail-drawer.vue)|`d4cba19882774ea917b49da6915f3f14e4cf31b4141eca0be44b42f1746cf4e7`|
|LIS|[resources/js/helpers/document-status.js](../../resources/js/helpers/document-status.js)|`f9022f66f12dfcda6078d83e841c5cb0941d4c1b52800f034dada61b7de131f0`|
|LIS|[.codex/skills/mantener-operacion-local-fiscal-pro 9/SKILL.md](../../.codex/skills/mantener-operacion-local-fiscal-pro9/SKILL.md)|`cb81164261355578adbf86ea4d20e6862264c9a1d8b6a5219f636f6af13c8364`|
|NVE|[modules/Sale/Http/Controllers/GenerateDocumentController.php](../../modules/Sale/Http/Controllers/GenerateDocumentController.php)|`7700efd883db0ffda3f27a9ed6ba653718652afc4d7c84add523b905d5fd53ae`|
|NVE|[tests/js/sale-note-invoice-submit.test.cjs](../../tests/js/sale-note-invoice-submit.test.cjs)|`ad659da9f200a1c045641b7d476405e2ffdd1f8a9eb569706b300f3769d8056a`|
|NVE|[.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|`488a5cfb60a8676ed0e7661434518d9048328bc6e5336aace4b035ada6631794`|
|CAN|[routes/web.php](../../routes/web.php)|`6c500843fe5901f1e0aed5f4374bbab21baa4db61710ee5cfc375d91a322aff1`|
|CAN|[routes/api.php](../../routes/api.php)|`8b96ee2432356bdb235712f144fe691a602b386d27ce4fddf87307d45b121718`|
|CAN|[modules/Hotel/Routes/web.php](../../modules/Hotel/Routes/web.php)|`2192126768d47111867cc4f98e1f69582c43c660835c7f906f2701e937e9abbe`|
|CAN|[modules/WhatsAppBot/Services/Tools/CreateDocumentTool.php](../../modules/WhatsAppBot/Services/Tools/CreateDocumentTool.php)|`d1cd8e5159a7d9452a3ce6692c6dbff2104968cd6f9810b64e0c30328d7d320a`|
|CAN|[.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|`488a5cfb60a8676ed0e7661434518d9048328bc6e5336aace4b035ada6631794`|
|API|[routes/api.php](../../routes/api.php)|`8b96ee2432356bdb235712f144fe691a602b386d27ce4fddf87307d45b121718`|
|API|[app/Http/Controllers/Tenant/Api/DocumentController.php](../../app/Http/Controllers/Tenant/Api/DocumentController.php)|`5c93d4957eafb8e141f1948f3831c90a3ab7f8364a75aa6c1395abf5369ef5e6`|
|API|[app/CoreFacturalo/Requests/Api/Transform/DocumentTransform.php](../../app/CoreFacturalo/Requests/Api/Transform/DocumentTransform.php)|`341e57605642e5d5186ef6c9716ea4f1c20f24731c41ae69fcf4b0ee4bb7fe9e`|
|IMP|[app/Http/Controllers/Tenant/DocumentController.php](../../app/Http/Controllers/Tenant/DocumentController.php)|`488950347d3d5206eb819d512384ed0925460cbef31eddcc5ffea3adb52fdf61`|
|IMP|[app/Services/MassiveInvoiceService.php](../../app/Services/MassiveInvoiceService.php)|`2b6e4a8894b4bb1ab78d8d3a568e9b3243d93dcd74543cc16d614879083240b2`|
|IMP|[app/Http/Controllers/System/MassiveInvoiceController.php](../../app/Http/Controllers/System/MassiveInvoiceController.php)|`9f0a57960e999444adf45ad98e6ebe185874b77a2a6c1a8003f6842a9b02e980`|
|IMP|[public/formats/documents_second_format.xlsx](../../public/formats/documents_second_format.xlsx)|`010235cf2ff12b0803b57398133ff7323673c102aff1a5e10803357d8157d14f`|
|IMP|[public/formats/formato_facturas_masivas.xlsx](../../public/formats/formato_facturas_masivas.xlsx)|`af6e28e99aeff806ca2de0ee8bc951032523d44c494cd0cf2c01523686b0810f`|
|ANU|[app/Http/Controllers/Tenant/VoidedController.php](../../app/Http/Controllers/Tenant/VoidedController.php)|`673de48eb661575999e7cddd769bb92ef3a80a74edc3be543f3e271ff30116c9`|
|ANU|[app/Http/Controllers/Tenant/DocumentController.php](../../app/Http/Controllers/Tenant/DocumentController.php)|`488950347d3d5206eb819d512384ed0925460cbef31eddcc5ffea3adb52fdf61`|
|ANU|[tests/Unit/TestDocumentsDeletionBehaviorTest.php](../../tests/Unit/TestDocumentsDeletionBehaviorTest.php)|`65e35be9c2b1d0891d26cc5e79c2de270fff15f0aba051292577e8fdbea5fedb`|
|ANU|[tests/Unit/TestDocumentsDeletionContractTest.php](../../tests/Unit/TestDocumentsDeletionContractTest.php)|`9db687f029ffad5ded10309eaf4980d2b6d68d550bcdd3bf00133484cc4d57ce`|
|FIN|[modules/Finance/Routes/web.php](../../modules/Finance/Routes/web.php)|`6b3470701857d9f5f03ab2215431d0038b6420d0c0d93b608e23243ae02eae12`|
|FIN|[modules/Report/Routes/web.php](../../modules/Report/Routes/web.php)|`4dfedeed0252b9e5ab5e7a1a6828223168d04c03d20768b1b56551e92a25cb6d`|
|FIN|[resources/views/pdf/partials/document_fiscal_totals.blade.php](../../resources/views/pdf/partials/document_fiscal_totals.blade.php)|`9debe42ff952fe5f57f7218ca250bb486e8d5d53105c5bd4051c9bb9dfdc9eff`|
|SEG|[app/Http/Controllers/Tenant/DocumentFiscalController.php](../../app/Http/Controllers/Tenant/DocumentFiscalController.php)|`9f0014cd5dd617c59c7017e33ed300374f4a891f50d788cdb731c09bc632079f`|
|SEG|[app/Http/Controllers/Tenant/DocumentPdfController.php](../../app/Http/Controllers/Tenant/DocumentPdfController.php)|`608468a7ad87f58d50fcc38a5ecfb90561221ef542a3d2ec43c11f09d0b7dc15`|
|SEG|[.codex/skills/mantener-seguridad-pro 9/SKILL.md](../../.codex/skills/mantener-seguridad-pro9/SKILL.md)|`6265c84680a75c28fa3b0f85ed33596a30644c483737f28b48ed73cd03065aae`|
|SEG|[informes/auditoria_seguridad_pro9.md](../../informes/auditoria_seguridad_pro9.md)|`29e7785eea0c4ee7883a37bfc7726d1d7adffec487256836e702569394f7c2b1`|
|ROB|[tests/Unit/FiscalEmissionSchemaTest.php](../../tests/Unit/FiscalEmissionSchemaTest.php)|`d55e241d1443a20eec0e335252a4d9b6396c8ca58130c5580cab65211045dcc9`|
|ROB|[tests/Support/fiscal_payment_concurrency_worker.php](../../tests/Support/fiscal_payment_concurrency_worker.php)|`136d547eaabe369293c9ab605e22fb95243f9b9bff562ce8d13d134deffe4d9d`|
|MAT|[app/Services/SalesCustomerIdentityPolicy.php](../../app/Services/SalesCustomerIdentityPolicy.php)|`6cd9704d7536bb9a4c4e0a7b55899f2e346cf07a494f5371990535fa255321c9`|
|MAT|[.codex/skills/gestionar-clientes-venezuela/SKILL.md](../../.codex/skills/gestionar-clientes-venezuela/SKILL.md)|`15bfdf3e21ed27c5dabbd5480b228a312ee73511474116d7a69cf0df9bcb7b77`|
|MAT|[.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|`488a5cfb60a8676ed0e7661434518d9048328bc6e5336aace4b035ada6631794`|
|NEG|[app/Services/SalesDocumentTypePolicy.php](../../app/Services/SalesDocumentTypePolicy.php)|`f1ae1733bcb9d4baa8d7137c05d6d5f56dd21f35dd64e7b80998c3f5ea9aad85`|
|NEG|[app/Services/ExportInvoicePolicy.php](../../app/Services/ExportInvoicePolicy.php)|`42135c3e441da619a0758595ad7e7225f6d431f476aa3a93a852b21c0b1cbe92`|
|NEG|[app/Services/ThirdPartyInvoicePolicy.php](../../app/Services/ThirdPartyInvoicePolicy.php)|`eabd94949b996530d03f148a18a69c0c6253717c235621503560666219814386`|
|NEG|[.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|`488a5cfb60a8676ed0e7661434518d9048328bc6e5336aace4b035ada6631794`|
|PRE|[app/CoreFacturalo/Facturalo.php](../../app/CoreFacturalo/Facturalo.php)|`4db60bcd803130e248ce367483d26bb91c1b6a377c04f4d61aa964dbf98e9753`|
|PRE|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`be6a8820f96a059343cd765803016d930910e6e480e7d92b90b63eef6bb1413a`|
|PRE|[resources/js/views/tenant/documents/invoice_generate.vue](../../resources/js/views/tenant/documents/invoice_generate.vue)|`3e8f79e1e1d7197e6b39a148f0cb44c2f3a5916632f820bdc539d9744c127d19`|
|PRE|[modules/Document/Routes/web.php](../../modules/Document/Routes/web.php)|`516b0b9a41d97cf80ba7c845ad26a82853bedbc172bdbe25caeae8dcbbc46f78`|
|COM|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`be6a8820f96a059343cd765803016d930910e6e480e7d92b90b63eef6bb1413a`|
|COM|[resources/js/views/tenant/documents/invoice_generate.vue](../../resources/js/views/tenant/documents/invoice_generate.vue)|`3e8f79e1e1d7197e6b39a148f0cb44c2f3a5916632f820bdc539d9744c127d19`|
|COM|[config/venezuela.php](../../config/venezuela.php)|`787a3a2cdb0b7eff3efff7762e78f818f3e049ca6fd38d2a422e0e1ce2d99e0d`|

|RGE|[app/Services/SpecialTaxRegimePolicy.php](../../app/Services/SpecialTaxRegimePolicy.php)|`9c48ca2248016e08f827197a45570871902ae69cfed3ae56c6384ed4862388d7`|
|RGE|[app/Services/Fiscal/HkaDomesticInvoicePolicy.php](../../app/Services/Fiscal/HkaDomesticInvoicePolicy.php)|`8b89de0ad04f9b406efeef59660a3169f1381d8de8e95dfc72835f5b7635fc5b`|
|RGE|[app/Services/Fiscal/HkaDomesticMoney.php](../../app/Services/Fiscal/HkaDomesticMoney.php)|`735793d78e9a91ab25b64299dba392ed383601f46f1c8249b5592348842beefd`|
|RGE|[app/Services/Fiscal/HkaPayloadBuilder.php](../../app/Services/Fiscal/HkaPayloadBuilder.php)|`a0d89df10d1df35ee19f672f319713971b92b53b6c43e92c675824e84e80e7cb`|
|RGE|[tests/Unit/SpecialTaxRegimeTest.php](../../tests/Unit/SpecialTaxRegimeTest.php)|`25c4c9668e052eb048e1ef5d206aff2cb217f6ea0745021d5540d8865b817a5a`|
|RGE|[tests/js/special-tax-regime-ui.test.cjs](../../tests/js/special-tax-regime-ui.test.cjs)|`67fea3fc9ad2dde3d38e2fcbeb7dd2ff8bf02d86c4d2965e0a44ff7e068ed67e`|
|RGE|[informes/facturas_regimenes_especiales_hka.md](../../informes/facturas_regimenes_especiales_hka.md)|`5a73a5e9005f3226db5965b838f4d4220e41c941058628136ea1550eb5dd98b4`|
|RGE|[.codex/skills/emitir-facturas-notas-hka/SKILL.md](../../.codex/skills/emitir-facturas-notas-hka/SKILL.md)|`2c388eebb2fc01f7afabb43f1e0251defff84aa31a15531e265b7a44ecaeb2a5`|
|SPE|[app/Services/Fiscal/SpecialTaxpayerPolicy.php](../../app/Services/Fiscal/SpecialTaxpayerPolicy.php)|`578aae5cd453d966d7eb4ab95cfeb6406bab1cbf0b8cc17171d26356641e5e0c`|
|SPE|[app/Services/Fiscal/FiscalProfileService.php](../../app/Services/Fiscal/FiscalProfileService.php)|`68d5d4c33e6ec7e9d00ca1a1afe5171e3b5181139c371a2e744a3d0c6f3bb743`|
|SPE|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`be6a8820f96a059343cd765803016d930910e6e480e7d92b90b63eef6bb1413a`|
|SPE|[app/Http/Controllers/Tenant/FiscalProfileController.php](../../app/Http/Controllers/Tenant/FiscalProfileController.php)|`6faaa25a935929b99b57a38e577339a9637415e3ded8f1f749494c665d017be6`|
|SPE|[tests/Unit/SpecialTaxpayerPolicyTest.php](../../tests/Unit/SpecialTaxpayerPolicyTest.php)|`72d3f550b935c1ee5ef782a3110ec50ec006efd102a4395763c30a509cc8b560`|
|SPE|[tests/Support/SpeProfiles.php](../../tests/Support/SpeProfiles.php)|`12ccfa7f5669745d083b5304df1b1ceb51c8ee4a8e9aebad24ee1d2e5b8ed0a1`|
|SPE|[tests/Support/SpePersistenceCases.php](../../tests/Support/SpePersistenceCases.php)|`f6b97a3649b90fad8966e3b1390770229f286e06f42866f6a68d2f08d4b430eb`|
|SPE|[informes/facturas_sujetos_pasivos_especiales_hka.md](../../informes/facturas_sujetos_pasivos_especiales_hka.md)|`17d70a81f11bdf1271c0e14deb310833a812dc72aeaa0b5ccea4471a5936fdaf`|
|SPE|[.codex/skills/mantener-sujetos-pasivos-especiales-pro 9/SKILL.md](../../.codex/skills/mantener-sujetos-pasivos-especiales-pro9/SKILL.md)|`509e4d744d9b4a665f6c3253b96c20f373de0b98e9768eaa2ab6ce3759c80623`|
|RIVA|[app/Services/Fiscal/ReceivedRetentionService.php](../../app/Services/Fiscal/ReceivedRetentionService.php)|`8accbf2c5352d3053c52167b32a293e9c49bd4af4fb02f5c4a3fac34ff3d2565`|
|RIVA|[app/Http/Controllers/Tenant/IvaRetentionController.php](../../app/Http/Controllers/Tenant/IvaRetentionController.php)|`a24c9597354dfbdb61b7b4a6f6f15f2f5300db5de792e613c3604e5fbbe0b2d5`|
|RIVA|[app/Services/Fiscal/HkaReceivedRetention.php](../../app/Services/Fiscal/HkaReceivedRetention.php)|`d5011bdf7737e3a60772dcd72eff859e2701af7103d220d242c05e07ed8e90be`|
|RIVA|[routes/web.php](../../routes/web.php)|`6c500843fe5901f1e0aed5f4374bbab21baa4db61710ee5cfc375d91a322aff1`|
|RIVA|[routes/api.php](../../routes/api.php)|`8b96ee2432356bdb235712f144fe691a602b386d27ce4fddf87307d45b121718`|
|RIVA|[app/Services/Fiscal/IvaRetentionService.php](../../app/Services/Fiscal/IvaRetentionService.php)|`afad61848050ad27377bea38313bf92b7409977c7da28ed18560036adb5d6257`|
|RIVA|[app/Services/Fiscal/HkaIvaRetention.php](../../app/Services/Fiscal/HkaIvaRetention.php)|`1371bdfb25ec0f701eeefb78aa92178d8402aeda478224d6b4d6b65a4092640e`|
|RIVA|[app/Services/Fiscal/HkaIvaRetentionPayload.php](../../app/Services/Fiscal/HkaIvaRetentionPayload.php)|`75992422b66ffc9f402d16a3363d16be0f9a3661df93933b42a86024cf562175`|
|RIVA|[app/Services/Fiscal/HkaIvaRetentionCapability.php](../../app/Services/Fiscal/HkaIvaRetentionCapability.php)|`8a1321e75e73877084ec4a4a539135dac40224b683ccb41a4e568e88e061fe3e`|
|RIVA|[tests/Unit/HkaIvaRetentionPayloadTest.php](../../tests/Unit/HkaIvaRetentionPayloadTest.php)|`68f149c3553d8e3946f9b9728e16941d67f301b4a87d073c3d5ed232d7cd6718`|
|RIVA|[tests/Unit/IvaRetentionHttpContractTest.php](../../tests/Unit/IvaRetentionHttpContractTest.php)|`72851d25d64a5ce45f12cb7e4d548e521751a230da377a12fa9d938799aa602a`|
|RIVA|[tests/Support/IvaRetentionPersistenceCases.php](../../tests/Support/IvaRetentionPersistenceCases.php)|`d35d929b64a8fc7e1c2db6e7c76f016b4b5edf3c15044c6cabffe345ca2d7847`|
|RIVA|[tests/js/iva-retention-ui.test.cjs](../../tests/js/iva-retention-ui.test.cjs)|`7a8036f4eeb9b7a925901d9ed30c7c815ff110466e09670a009d232ef754f8e2`|
|RIVA|[informes/retenciones_iva_recibidas_hka.md](../../informes/retenciones_iva_recibidas_hka.md)|`e8986849a9143dfb1eedc03083ee6686ca4fbb430e3f83f7bdb3ff311b8bb1fc`|
|RIVA|[.codex/skills/mantener-retenciones-iva-recibidas-pro 9/SKILL.md](../../.codex/skills/mantener-retenciones-iva-recibidas-pro9/SKILL.md)|`d12028b8c85c95bf20d48267b987718d885d622e9f61120530cb1899762e72b4`|
|RIVA|[config/iva_retention.php](../../config/iva_retention.php)|`7e3943c8d0fe1f1cc4e97cf7cc989a5755329e3c89cbed2c778842e8699188d8`|
|RISLR|[app/Services/Fiscal/ReceivedRetentionService.php](../../app/Services/Fiscal/ReceivedRetentionService.php)|`8accbf2c5352d3053c52167b32a293e9c49bd4af4fb02f5c4a3fac34ff3d2565`|
|RISLR|[app/Http/Controllers/Tenant/IvaRetentionController.php](../../app/Http/Controllers/Tenant/IvaRetentionController.php)|`a24c9597354dfbdb61b7b4a6f6f15f2f5300db5de792e613c3604e5fbbe0b2d5`|
|RISLR|[app/Services/Fiscal/HkaReceivedRetention.php](../../app/Services/Fiscal/HkaReceivedRetention.php)|`d5011bdf7737e3a60772dcd72eff859e2701af7103d220d242c05e07ed8e90be`|
|RISLR|[routes/web.php](../../routes/web.php)|`6c500843fe5901f1e0aed5f4374bbab21baa4db61710ee5cfc375d91a322aff1`|
|RISLR|[routes/api.php](../../routes/api.php)|`8b96ee2432356bdb235712f144fe691a602b386d27ce4fddf87307d45b121718`|
|RISLR|[app/Services/Fiscal/IslrRetentionService.php](../../app/Services/Fiscal/IslrRetentionService.php)|`9eb5b4078b7e9f8b6e606598907c5df9f19b888e25906f3015caab504218c582`|
|RISLR|[app/Services/Fiscal/HkaIslrRetention.php](../../app/Services/Fiscal/HkaIslrRetention.php)|`bde9f23a7bc0e348290bc77f16c4782f8b55ccfe2e448e274c3bb27e6701c4b0`|
|RISLR|[app/Services/Fiscal/HkaIslrRetentionPayload.php](../../app/Services/Fiscal/HkaIslrRetentionPayload.php)|`34b141b70d6f386c1887368610ea9b8db97bb6a2b0ebefc73eb44c04e78c7d4f`|
|RISLR|[app/Services/Fiscal/HkaIslrRetentionCapability.php](../../app/Services/Fiscal/HkaIslrRetentionCapability.php)|`14f177bb72bd7e279ffe23b00ac11ce29bfadebad68fd3d04cd831f9d280cd2e`|
|RISLR|[database/seeders/data/tenant_initial_data.php](../../database/seeders/data/tenant_initial_data.php)|`128a187ae7a57e259854e64160dbab0d2e4e2d06d107427c2dd5d8de39afc450`|
|RISLR|[tests/Unit/HkaIslrRetentionTest.php](../../tests/Unit/HkaIslrRetentionTest.php)|`410c620f26415d92e5f6eb472d71e19c048fbcd0bf0927a5f39cb5ac35db09f5`|
|RISLR|[tests/Support/IslrRetentionPersistenceCases.php](../../tests/Support/IslrRetentionPersistenceCases.php)|`c0d7f50e10f8fb0bf333743bfae673416e997a027b6330c9ec898d8f22a473fa`|
|RISLR|[informes/retenciones_islr_recibidas_hka.md](../../informes/retenciones_islr_recibidas_hka.md)|`6cfa5880d1f380129fe97268957bc34b515fa2c72c9f09f63b1bc781e23595c1`|
|RISLR|[.codex/skills/mantener-retenciones-islr-recibidas-pro 9/SKILL.md](../../.codex/skills/mantener-retenciones-islr-recibidas-pro9/SKILL.md)|`4ebbca572b8fd7cb5fcffb14e4f9623f3f5966975b96bf6235a85b27e021a80b`|
|RISLR|[config/islr_retention.php](../../config/islr_retention.php)|`bd9c630058e7c06a3d8c9ba90fc99b18e6df59760b4445247278de9d1f0b825d`|
|INT|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`be6a8820f96a059343cd765803016d930910e6e480e7d92b90b63eef6bb1413a`|
|INT|[app/Services/Fiscal/ReceivedRetentionService.php](../../app/Services/Fiscal/ReceivedRetentionService.php)|`8accbf2c5352d3053c52167b32a293e9c49bd4af4fb02f5c4a3fac34ff3d2565`|
|INT|[app/Services/Fiscal/HkaIvaRetentionCapability.php](../../app/Services/Fiscal/HkaIvaRetentionCapability.php)|`8a1321e75e73877084ec4a4a539135dac40224b683ccb41a4e568e88e061fe3e`|
|INT|[app/Services/Fiscal/HkaIslrRetentionCapability.php](../../app/Services/Fiscal/HkaIslrRetentionCapability.php)|`14f177bb72bd7e279ffe23b00ac11ce29bfadebad68fd3d04cd831f9d280cd2e`|
|INT|[resources/js/mixins/iva-retention.js](../../resources/js/mixins/iva-retention.js)|`a3f2891ec79340b0cfd18c88207feb67b66f76608ea8e080124e8c4ea42a503a`|
|INT|[resources/js/views/tenant/documents/partials/payments.vue](../../resources/js/views/tenant/documents/partials/payments.vue)|`3c766f2c87bf682b35bea751ce3e4183641bfe0f49a995404485e3042e05d005`|
|INT|[resources/js/views/tenant/pos/partials/payment.vue](../../resources/js/views/tenant/pos/partials/payment.vue)|`a1a8efe314b4073c5c42f1107d100fdca052a980fe55e048586b3f35431657ad`|
|INT|[tests/js/fiscal-payment-ui.test.cjs](../../tests/js/fiscal-payment-ui.test.cjs)|`e99401088b662cb861a52b190522ae80974b1a25c4c924ffa716267b50ef3f85`|
|INT|[tests/Support/IvaRetentionPersistenceCases.php](../../tests/Support/IvaRetentionPersistenceCases.php)|`d35d929b64a8fc7e1c2db6e7c76f016b4b5edf3c15044c6cabffe345ca2d7847`|
|INT|[tests/Support/IslrRetentionPersistenceCases.php](../../tests/Support/IslrRetentionPersistenceCases.php)|`c0d7f50e10f8fb0bf333743bfae673416e997a027b6330c9ec898d8f22a473fa`|
|INT|[routes/web.php](../../routes/web.php)|`6c500843fe5901f1e0aed5f4374bbab21baa4db61710ee5cfc375d91a322aff1`|
|INT|[routes/api.php](../../routes/api.php)|`8b96ee2432356bdb235712f144fe691a602b386d27ce4fddf87307d45b121718`|
|INT|[.codex/skills/mantener-persistencia-fiscal-venezuela-pro 9/SKILL.md](../../.codex/skills/mantener-persistencia-fiscal-venezuela-pro9/SKILL.md)|`1b2fe35c485fc789825c08692f2d1deeee734223e5225025281cd0a28354ac3f`|

## Suites relacionadas existentes al redactar

No se han ejecutado para esta entrega. Verificar aislamiento y alcance real de cada suite antes de usarlas.

- [tests/Unit/CurrentDocumentResolutionTest.php](../../tests/Unit/CurrentDocumentResolutionTest.php)
- [tests/Unit/CurrentLocalDocumentReferenceTest.php](../../tests/Unit/CurrentLocalDocumentReferenceTest.php)
- [tests/Unit/CurrentPdfRenderingTest.php](../../tests/Unit/CurrentPdfRenderingTest.php)
- [tests/Unit/CurrentPdfTemplateContractTest.php](../../tests/Unit/CurrentPdfTemplateContractTest.php)
- [tests/Unit/DocumentEditPolicyTest.php](../../tests/Unit/DocumentEditPolicyTest.php)
- [tests/Unit/DocumentFileNameTest.php](../../tests/Unit/DocumentFileNameTest.php)
- [tests/Unit/DocumentFiscalAuthorizationTest.php](../../tests/Unit/DocumentFiscalAuthorizationTest.php)
- [tests/Unit/DocumentListColumnsTest.php](../../tests/Unit/DocumentListColumnsTest.php)
- [tests/Unit/DocumentNumberDisplayTest.php](../../tests/Unit/DocumentNumberDisplayTest.php)
- [tests/Unit/DocumentUpdateRequestTest.php](../../tests/Unit/DocumentUpdateRequestTest.php)
- [tests/Unit/ExportInvoiceTest.php](../../tests/Unit/ExportInvoiceTest.php)
- [tests/Unit/FiscalApiPaymentTransformTest.php](../../tests/Unit/FiscalApiPaymentTransformTest.php)
- [tests/Unit/FiscalControlNumberTest.php](../../tests/Unit/FiscalControlNumberTest.php)
- [tests/Unit/FiscalEmissionSchemaTest.php](../../tests/Unit/FiscalEmissionSchemaTest.php)
- [tests/Unit/FiscalEmissionSettingsTest.php](../../tests/Unit/FiscalEmissionSettingsTest.php)
- [tests/Unit/FiscalIdentityTest.php](../../tests/Unit/FiscalIdentityTest.php)
- [tests/Unit/FiscalLineCalculationTest.php](../../tests/Unit/FiscalLineCalculationTest.php)
- [tests/Unit/FiscalPdfDataTest.php](../../tests/Unit/FiscalPdfDataTest.php)
- [tests/Unit/HkaA5LayoutTest.php](../../tests/Unit/HkaA5LayoutTest.php)
- [tests/Unit/HkaA5TextTest.php](../../tests/Unit/HkaA5TextTest.php)
- [tests/Unit/HkaIvaRateCatalogContractTest.php](../../tests/Unit/HkaIvaRateCatalogContractTest.php)
- [tests/Unit/HkaMailTest.php](../../tests/Unit/HkaMailTest.php)
- [tests/Unit/HkaPayloadBuilderTest.php](../../tests/Unit/HkaPayloadBuilderTest.php)
- [tests/Unit/HkaPdfTest.php](../../tests/Unit/HkaPdfTest.php)
- [tests/Unit/HkaResponseTest.php](../../tests/Unit/HkaResponseTest.php)
- [tests/Unit/HkaTransportTest.php](../../tests/Unit/HkaTransportTest.php)
- [tests/Unit/HotelVenezuelanIdentificationContractTest.php](../../tests/Unit/HotelVenezuelanIdentificationContractTest.php)
- [tests/Unit/LocalFiscalDocumentPolicyTest.php](../../tests/Unit/LocalFiscalDocumentPolicyTest.php)
- [tests/Unit/PersonRequestVenezuelaTest.php](../../tests/Unit/PersonRequestVenezuelaTest.php)
- [tests/Unit/SaleNotesInvoiceCustomerContractTest.php](../../tests/Unit/SaleNotesInvoiceCustomerContractTest.php)
- [tests/Unit/SalesCustomerIdentityPolicyTest.php](../../tests/Unit/SalesCustomerIdentityPolicyTest.php)
- [tests/Unit/SalesDocumentTypePolicyTest.php](../../tests/Unit/SalesDocumentTypePolicyTest.php)
- [tests/Unit/SalesIdentityDocumentEmissionContractTest.php](../../tests/Unit/SalesIdentityDocumentEmissionContractTest.php)
- [tests/Unit/SalesWithoutReceiptSourceContractTest.php](../../tests/Unit/SalesWithoutReceiptSourceContractTest.php)
- [tests/Unit/SystemDashboardSalesTotalTest.php](../../tests/Unit/SystemDashboardSalesTotalTest.php)
- [tests/Unit/TestDocumentsDeletionBehaviorTest.php](../../tests/Unit/TestDocumentsDeletionBehaviorTest.php)
- [tests/Unit/TestDocumentsDeletionContractTest.php](../../tests/Unit/TestDocumentsDeletionContractTest.php)
- [tests/Unit/ThirdPartyInvoiceTest.php](../../tests/Unit/ThirdPartyInvoiceTest.php)
- [tests/Unit/VendeyaDocumentPayloadNormalizerTest.php](../../tests/Unit/VendeyaDocumentPayloadNormalizerTest.php)
- [tests/Unit/VenezuelaCurrencyTest.php](../../tests/Unit/VenezuelaCurrencyTest.php)
- [tests/Unit/VenezuelaGeopoliticalContractTest.php](../../tests/Unit/VenezuelaGeopoliticalContractTest.php)
- [tests/Unit/VenezuelaIdentityDocumentContractTest.php](../../tests/Unit/VenezuelaIdentityDocumentContractTest.php)
- [tests/Unit/VenezuelaInitialCatalogContractTest.php](../../tests/Unit/VenezuelaInitialCatalogContractTest.php)
- [tests/Unit/VenezuelaIvaContractTest.php](../../tests/Unit/VenezuelaIvaContractTest.php)
- [tests/Unit/VenezuelaLocalizationTest.php](../../tests/Unit/VenezuelaLocalizationTest.php)
- [tests/Unit/VenezuelaPersonLocationTest.php](../../tests/Unit/VenezuelaPersonLocationTest.php)
- [tests/Unit/VenezuelaPhoneLocalizationTest.php](../../tests/Unit/VenezuelaPhoneLocalizationTest.php)
- [tests/Unit/VenezuelaRifPresentationContractTest.php](../../tests/Unit/VenezuelaRifPresentationContractTest.php)
- [tests/Unit/VenezuelaSourceContractTest.php](../../tests/Unit/VenezuelaSourceContractTest.php)
- [tests/Unit/VenezuelaUnitTypeContractTest.php](../../tests/Unit/VenezuelaUnitTypeContractTest.php)
- [tests/js/digital-invoice-preview.test.cjs](../../tests/js/digital-invoice-preview.test.cjs)
- [tests/js/document-edit-ui.test.cjs](../../tests/js/document-edit-ui.test.cjs)
- [tests/js/document-number-display.test.cjs](../../tests/js/document-number-display.test.cjs)
- [tests/js/empty-series-display.test.cjs](../../tests/js/empty-series-display.test.cjs)
- [tests/js/export-invoice-ui.test.cjs](../../tests/js/export-invoice-ui.test.cjs)
- [tests/js/fiscal-identity-filter.test.cjs](../../tests/js/fiscal-identity-filter.test.cjs)
- [tests/js/fiscal-payment-ui.test.cjs](../../tests/js/fiscal-payment-ui.test.cjs)
- [tests/js/fiscal-row.test.cjs](../../tests/js/fiscal-row.test.cjs)
- [tests/js/garage-series-selection.test.cjs](../../tests/js/garage-series-selection.test.cjs)
- [tests/js/hka-emission-ui.test.cjs](../../tests/js/hka-emission-ui.test.cjs)
- [tests/js/hka-mail-ui.test.cjs](../../tests/js/hka-mail-ui.test.cjs)
- [tests/js/hka-pdf-ui.test.cjs](../../tests/js/hka-pdf-ui.test.cjs)
- [tests/js/pos-hka-email.test.cjs](../../tests/js/pos-hka-email.test.cjs)
- [tests/js/sale-note-invoice-submit.test.cjs](../../tests/js/sale-note-invoice-submit.test.cjs)
- [tests/js/series-configuration.test.cjs](../../tests/js/series-configuration.test.cjs)
- [tests/js/third-party-invoice-ui.test.cjs](../../tests/js/third-party-invoice-ui.test.cjs)

## Auditoría de alcance de esta preparación

Se prepararon documentos de QA, datos de especificación y un libro editable. No se crearon clientes/ventas en la aplicación, no se emitió HKA, no se envió correo/WhatsApp, no se modificó lógica de facturación, bases ni assets y no se certificó producción. El estado inicial de todas las pruebas es Pendiente.

## Cambios del 12 de octubre de 2026 y reglas de precedencia

| Commit | Funcionalidad cubierta | Grupos/correcciones |
|---|---|---|
|067 d 8 fda|Regímenes especiales y combinación con terceros|RGE; TER 008 y edición UUID|
|5 ce 9 b 802|Perfiles fiscales SPE y clasificación fechada|SPE; IGT/PAG/EDI 014|
|3 acaec 8 a|IVA recibida, soporte privado y outbox|RIVA; RET 001/002/004..015/018/019|
|87 dc 6506|ISLR recibida y protocolo propio; coexistencia/abonos|RISLR/INT; RET 003/004/009/010|

Fuente base anterior 230830 dd conservada como historia de los 720 IDs. Referencia histórica 87 dc 6506; para notas/órdenes prevalece ampliación14oct/76690125 más fuentes locales con hashes. Sustituir múltiples comprobantes activos por uno activo por impuesto/factura; permitir coexistencia IVA+ISLR. Previsión no es aplicación, y confirmación/control/referencia son prerequisitos digitales. En IGTF resolver perfil vigente del emisor/moneda recibida/intermediación; excepción administrativa con motivo/fundamento. Terceros+régimen ahora positivo en alcance web contado completo; no ampliar a otros canales. Notas ordinarias domésticas vinculadas ahora habilitadas por contrato del 13 de octubre; ver ampliación del 14 de octubre.

Informes de regímenes/SPE/IVA/ISLR contienen resultados históricos del 12/10 y límites de proveedor. Conservar separados en el informe; no asignar Aprobó a casos nuevos por esas evidencias. El emisor HKA DEMO disponible FORMAL no homologa emisor SPE; JSON incluye datos que PDF original puede omitir. Capacidades IVA/ISLR netas false, incluida ISLR pese aceptación histórica parcial VES. Ausencias 203 IVA y 204 ISLR tienen contratos distintos; siete campos verificables ISLR no incluyen concepto/sustraendo/RIF/porcentaje.

Verificación histórica del 12 de octubre: IDs únicos y 720 originales conservados, especificaciones por ID en Markdown/Excel, 35 grupos, estados Pendiente/inputs vacíos, fórmulas y validaciones del registro ampliadas, enlaces/hash y previews de las cuatro hojas. No se ejecutó una corrida QA de la aplicación para elaborar la documentación; esa obligación corresponde al agente ejecutor con registro y reporte final.

## Ampliación del 14 de octubre de 2026: referencia actual

HEAD observado: `76690125acdf1377b0f93f434507a38dd8d7c292`. Base confirmada hasta `76690125`; cambios de notas `f37b664e` y `76690125`. Órdenes tienen modificaciones locales/no confirmadas, identificadas abajo. Los hashes son del contenido exacto leído al preparar esta entrega; cambios posteriores exigen revalidar contrato.

| Grupo | Casos | Fuentes principales |
|---|---|---|
|NHD|QA-NHD-001 a QA-NHD-362|[app/Services/Fiscal/FiscalNote.php](../../app/Services/Fiscal/FiscalNote.php), [app/Services/Fiscal/HkaNotePayments.php](../../app/Services/Fiscal/HkaNotePayments.php), [app/Services/Fiscal/HkaNotePolicy.php](../../app/Services/Fiscal/HkaNotePolicy.php), [app/Services/Fiscal/FiscalNoteInventory.php](../../app/Services/Fiscal/FiscalNoteInventory.php)|
|NRF|QA-NRF-001 a QA-NRF-038|[app/Services/Fiscal/NoteRefund.php](../../app/Services/Fiscal/NoteRefund.php), [app/Services/Fiscal/NoteAdjustment.php](../../app/Services/Fiscal/NoteAdjustment.php), [app/Services/Fiscal/FiscalNoteBalance.php](../../app/Services/Fiscal/FiscalNoteBalance.php)|
|NAN|QA-NAN-001 a QA-NAN-032|[app/Services/Fiscal/NoteAnnulment.php](../../app/Services/Fiscal/NoteAnnulment.php)|
|ODE|QA-ODE-001 a QA-ODE-072|[app/Services/Fiscal/DeliveryOrderInput.php](../../app/Services/Fiscal/DeliveryOrderInput.php), [app/Services/Fiscal/DeliveryOrderPersistence.php](../../app/Services/Fiscal/DeliveryOrderPersistence.php), [app/Services/Fiscal/HkaDispatchEmission.php](../../app/Services/Fiscal/HkaDispatchEmission.php), [app/Services/Fiscal/HkaDispatchPayloadBuilder.php](../../app/Services/Fiscal/HkaDispatchPayloadBuilder.php), [app/Services/Fiscal/HkaDispatchPdf.php](../../app/Services/Fiscal/HkaDispatchPdf.php)|
|ODI|QA-ODI-001 a QA-ODI-045|[app/Services/Fiscal/DeliveryOrderInventory.php](../../app/Services/Fiscal/DeliveryOrderInventory.php), [app/Services/Fiscal/DeliveryOrderInvoiceInventory.php](../../app/Services/Fiscal/DeliveryOrderInvoiceInventory.php), [app/Services/Fiscal/HkaDispatchRelations.php](../../app/Services/Fiscal/HkaDispatchRelations.php)|
|NRI|QA-NRI-001 a QA-NRI-032|[app/Services/Fiscal/FiscalNoteBalance.php](../../app/Services/Fiscal/FiscalNoteBalance.php)|

| Archivo de referencia | SHA-256 actual | Estado al documentar |
|---|---|---|
|[informes/notas_credito_debito_hka.md](../../informes/notas_credito_debito_hka.md)|`4db68c2f1c4c40be0f8a056e1b793f794b8d878e3a4d21572ff0bda202bd3b8d`|Confirmado en HEAD|
|[informes/ordenes_entrega_hka.md](../../informes/ordenes_entrega_hka.md)|`e9e2fe61a0e67374e7d4a2db088d939451922c8bbf7c376d2d6cbdf2d7a5f711`|??|
|[config/hka_notes.php](../../config/hka_notes.php)|`1c4bfa379affc0b3e2108708c3ad33b0bea6ae9df772e91b7da3764c8e0de089`|Confirmado en HEAD|
|[routes/web.php](../../routes/web.php)|`c8f3d8589cca66b4760267df06503baaa6edd0a80139cc21f32a60b6803d2325`| M|
|[routes/api.php](../../routes/api.php)|`8b96ee2432356bdb235712f144fe691a602b386d27ce4fddf87307d45b121718`|Confirmado en HEAD|
|[app/Services/Fiscal/contracts/hka-ve-v1.json](../../app/Services/Fiscal/contracts/hka-ve-v1.json)|`f2ddb07c239b04319df1fc9b1e6b4db137f89b51f063fa4989fd1bc836d10f04`|Confirmado en HEAD|
|[app/Services/Fiscal/FiscalNote.php](../../app/Services/Fiscal/FiscalNote.php)|`28f975354badd2b4753f22e06dc50a9e72e465409f71c7516e2ef11361946ba5`|Confirmado en HEAD|
|[app/Services/Fiscal/FiscalNoteBalance.php](../../app/Services/Fiscal/FiscalNoteBalance.php)|`b7b63ab9ff6d219d9fe706bf893193a319cf6d29e0678ecc6a7de26f0cd27738`|Confirmado en HEAD|
|[app/Services/Fiscal/FiscalNoteInventory.php](../../app/Services/Fiscal/FiscalNoteInventory.php)|`07325ea3a57b23a29a87a46aa9c3cca1d7e172f331fb0e45095bfc139f6faa67`|Confirmado en HEAD|
|[app/Services/Fiscal/HkaNotePayments.php](../../app/Services/Fiscal/HkaNotePayments.php)|`29dfa7611ea28af3ca203732b4492040f876bedd137c1c6741d60704f49000f1`|Confirmado en HEAD|
|[app/Services/Fiscal/HkaNoteCapability.php](../../app/Services/Fiscal/HkaNoteCapability.php)|`ad16b9d75ed1114fb522d34a55b7f1112dc0648780080fde8d96a6537e940523`|Confirmado en HEAD|
|[app/Services/Fiscal/HkaNotePolicy.php](../../app/Services/Fiscal/HkaNotePolicy.php)|`908607512275d0458be4223f00e41e650cc5f6d4b753c1d432931b718a0bd9a9`|Confirmado en HEAD|
|[app/Services/Fiscal/NoteRefund.php](../../app/Services/Fiscal/NoteRefund.php)|`d41f36174cc604b03e459235a6d04c6c251ab9b02dd5f07af04a7f5671cf2655`|Confirmado en HEAD|
|[app/Services/Fiscal/NoteAdjustment.php](../../app/Services/Fiscal/NoteAdjustment.php)|`c915fda62b0e0c1ccf1991a718e4f8e8f2899a5a4ef60e70ebdef37704cf9985`|Confirmado en HEAD|
|[app/Services/Fiscal/NoteAnnulment.php](../../app/Services/Fiscal/NoteAnnulment.php)|`55e023747f1e4f35dc5d03c1efa65ba37cf61300cdb1561b82d058f2a7fe70fb`|Confirmado en HEAD|
|[app/Services/Fiscal/DeliveryOrderInput.php](../../app/Services/Fiscal/DeliveryOrderInput.php)|`9f5a781003ffc3b0205e065292fd7ed3eb5823d1c898000f4a79498fd0ffeb3c`|??|
|[app/Services/Fiscal/DeliveryOrderPersistence.php](../../app/Services/Fiscal/DeliveryOrderPersistence.php)|`e7ba913e26a010142205a5432e415c839856107ba721619dd7b52123b4656f09`|??|
|[app/Services/Fiscal/DeliveryOrderInventory.php](../../app/Services/Fiscal/DeliveryOrderInventory.php)|`219b9e7a63f14a5ca4b9703429608bcc30ca770ea078be17b81bbc78534dcef2`|??|
|[app/Services/Fiscal/DeliveryOrderInvoiceInventory.php](../../app/Services/Fiscal/DeliveryOrderInvoiceInventory.php)|`e3fee63f32684d05c1ada555592d17a374a2608527b65d42d8b267fbdb32cf59`|??|
|[app/Services/Fiscal/HkaDispatchEmission.php](../../app/Services/Fiscal/HkaDispatchEmission.php)|`0cb24865a3612bb1b1d2c1b744fd2436d4a21189e4f74c8fcb21135c5fa3ae8f`|??|
|[app/Services/Fiscal/HkaDispatchPayloadBuilder.php](../../app/Services/Fiscal/HkaDispatchPayloadBuilder.php)|`a8d41cfd8a0d6e24f3692372b0f74a6c35c7824415266bf319a9f52731dc2633`|??|
|[app/Services/Fiscal/HkaDispatchRelations.php](../../app/Services/Fiscal/HkaDispatchRelations.php)|`bdae78e4b027d6283537d6ec38f5a165c44964d1dbf2bdac7c6a763ccf657565`|??|
|[app/Services/Fiscal/HkaDispatchPdf.php](../../app/Services/Fiscal/HkaDispatchPdf.php)|`a1812766391d899e8ff7e2a0ccf01ec657b9f53d50f144829068a5f8d918e5f0`|??|
|[app/Models/Tenant/Dispatch.php](../../app/Models/Tenant/Dispatch.php)|`963dc10a4826b2aa00cbf6758edaf8634a42f9eff5ff66ac98cc1dd9c715633b`| M|
|[app/Models/Tenant/DispatchEmission.php](../../app/Models/Tenant/DispatchEmission.php)|`96f243acdd314550909189de1b69cf9cab192724ce0aae3e43f0aff461d7bde9`|??|
|[app/Http/Controllers/Tenant/DispatchFiscalController.php](../../app/Http/Controllers/Tenant/DispatchFiscalController.php)|`e9442dc041d6be291af2fd1a9d4443e25fe283db4565208ee5c5ace622445bf1`|??|
|[database/migrations/tenant/2026_08_17_000342_create_dispatch_emissions_table.php](../../database/migrations/tenant/2026_08_17_000342_create_dispatch_emissions_table.php)|`db7e6492f8191e300da6c496ed96aac57694f4601b7cfa9d941dd603014d261b`|??|
|[resources/js/views/tenant/dispatches/create.vue](../../resources/js/views/tenant/dispatches/create.vue)|`45573716032a513715a4d5c8e222ea6312a31c9f31314ccb037c38e8511c2a71`| M|
|[resources/js/views/tenant/dispatches/partials/fiscal-status.vue](../../resources/js/views/tenant/dispatches/partials/fiscal-status.vue)|`1ebfa3175a66188df854e28b88f610093c0e163737b015027005646481f54b4f`|??|
|[resources/js/views/tenant/documents/invoice.vue](../../resources/js/views/tenant/documents/invoice.vue)|`c387dfc74f500b03b1e92601e90c0cd8a7087d046eee6e5e53763c67026ab35d`| M|
|[tests/Support/NotePersistenceCases.php](../../tests/Support/NotePersistenceCases.php)|`e81bee6a61f23ff16c2152edec23b9bc3f28f561fc47b99dd117cedf40de2408`|Confirmado en HEAD|
|[tests/Support/DispatchPersistenceCases.php](../../tests/Support/DispatchPersistenceCases.php)|`91a1a32585125f8b7bc56a62753c513c0301571c4b16fcb10da092c392eb4d90`|??|
|[tests/js/dispatch-hka-ui.test.cjs](../../tests/js/dispatch-hka-ui.test.cjs)|`bc9d5280ab566df8e818e6872a09c0686f728815ea472167b62f23391c46b089`|??|

### Precedencia y divergencias concretas

Las prohibiciones antiguas de notas de terceros/regímenes y emisión ordinaria quedan reemplazadas por el contrato del 13 de octubre; no reemplazan restricciones de crédito de factura, exportación, producción, canales ni IGTF98. Config hka_notes habilita capacidades notas DEMO, no capacidades netoIVA/ISLR.

El informe de órdenes describe la ausencia exacta 404 observada. HkaDispatchRelations::matches también devuelve ausencia para envolvente 200 válida/sinvalidaciones con lista relacionados que no contiene destino. ODI prueba ambas ramas, identifica doble frente Real DEMO y conserva incertidumbre para 404 no contractual/JSONincompleto. No ocultar diferencia de alcance entre informe histórico e implementación.

La guía asume flagsstock/valorización declarados por caso en entorno aislado; BBC histórico conservó cinco motivos sin descuento. No ejecutar prepare_delivery_order_demo.php ni probes como preparación de esta documentación. Suites y manifiestos históricos no son veredictos de esta nueva corrida.

### Evidencia histórica separada de la corrida

- [Matriz consolidada notas](../evidencias/notas_hka_emision_anulacion_2026_10_13.json):120escenarios/128operaciones históricos; no poblar estados nuevos con ellos.
- [Informe notas](../notas_credito_debito_hka.md):mapeos, corrección 99, anulación, referencias a pruebas y límites gráficos del original.
- [Manifiesto órdenes BBC](../evidencias/ordenes_entrega_bbc_manifest.json) y [resultados históricos](../evidencias/ordenes_entrega_resultados.json):23órdenes/5relaciones, no ejecución actual.
- [Informe órdenes](../ordenes_entrega_hka.md):esquema local/aditivo, contratos, cantidad/stock y originales; fuentes susceptibles de cambios locales.

### Auditoría de preparación actual

Sólo se actualizaron seis documentos QA y el libro existente. No se ejecutaron pruebas de aplicación, emisiones/anulaciones/correos, migraciones, generadores BBC ni builds; los cambios de aplicación existentes se preservaron. Los resultados nuevos son Pendiente, con campos de ejecución vacíos; las verificaciones de esta entrega sólo acreditan integridad de documentación/Excel.

Verificación de la entrega del 14 de octubre: **1702 IDs únicos en 41 grupos**, con los **1121 anteriores conservados** y **581 nuevos Pendiente**. Se actualizaron expectativas o controles de 29 casos anteriores. Los IDs y especificaciones nuevos/corregidos coinciden entre Markdown y Excel; resultados, evidencias y variantes anteriores permanecen intactos. Se verificaron enlaces locales y hashes de fuentes actuales, cuatro hojas en el mismo orden, filtros, tablas, validaciones, paneles congelados y ausencia de errores de fórmula. La prueba temporal de Aprobó/Reprobó actualizó las métricas de casos y variantes por separado y se restauró antes de exportar. Se revisaron visualmente las cuatro hojas; no se ejecutó QA de la aplicación.
