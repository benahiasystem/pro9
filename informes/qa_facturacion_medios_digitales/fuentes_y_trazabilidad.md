# Fuentes y trazabilidad de la especificación

Commit de referencia: `230830dd74de2e679d9ba5463da2ee9207baee11`. Fecha: 11 de octubre de 2026.

Las expectativas proceden del contrato actual de Venezuela, las políticas de producto y los puntos de implementación locales. Esta especificación no evalúa normativa externa. La lectura de código y los informes históricos sirven para diseñar pruebas; no acreditan su ejecución en esta corrida.

## Cómo resolver contradicciones

Algunos informes acumulan historia: un párrafo antiguo puede decir que PDF/HKA/exportación/edición no estaban integrados. Consulta primero las políticas y habilidades vigentes y las ampliaciones posteriores enlazadas. Ejemplos: preparado ya no bloquea siempre edición; Estado HKA pasó a Estado; PDF HKA y exportación web se incorporaron después. Si implementación y contrato actual difieren, conserva la diferencia como defecto o aclaración pendiente. No cambies esperado para que el caso pase ni afirmes una capacidad por una fila de catálogo.

Cada grupo referencia sus fuentes. Los hashes siguientes permiten detectar cambios después de redactar la guía. Los archivos de pruebas se enumeran como apoyo; ejecución de fuente/método, HTTP, navegador y Real DEMO son capas diferentes.

| Grupo | Base contractual / punto de implementación | Casos |
|---|---|---|
|ENT|[routes/web.php](../../routes/web.php), [routes/api.php](../../routes/api.php), `modules/*/Routes` (patrón o punto de referencia; resolver en la versión de la corrida), [resources/views/tenant/documents/form.blade.php](../../resources/views/tenant/documents/form.blade.php)|QA-ENT-001 a QA-ENT-012|
|CFG|[app/Services/FiscalEmissionSettings.php](../../app/Services/FiscalEmissionSettings.php), [app/Services/Fiscal/HkaAuthentication.php](../../app/Services/Fiscal/HkaAuthentication.php), [.codex/skills/configurar-conexion-hka-pro9/SKILL.md](../../.codex/skills/configurar-conexion-hka-pro9/SKILL.md)|QA-CFG-001 a QA-CFG-017|
|NUM|[app/Services/SeriesNumbering.php](../../app/Services/SeriesNumbering.php), [app/Services/SeriesAdministration.php](../../app/Services/SeriesAdministration.php), [app/Services/SeriesResolver.php](../../app/Services/SeriesResolver.php), [app/Services/Fiscal/FiscalIdentity.php](../../app/Services/Fiscal/FiscalIdentity.php), [.codex/skills/mantener-numeracion-fiscal-venezuela-pro9/SKILL.md](../../.codex/skills/mantener-numeracion-fiscal-venezuela-pro9/SKILL.md)|QA-NUM-001 a QA-NUM-021|
|CLI|[app/Services/SalesCustomerIdentityPolicy.php](../../app/Services/SalesCustomerIdentityPolicy.php), [resources/js/views/tenant/persons/form.vue](../../resources/js/views/tenant/persons/form.vue), [resources/js/views/tenant/documents/_document_form_fields_catalog.js](../../resources/js/views/tenant/documents/_document_form_fields_catalog.js), [.codex/skills/gestionar-clientes-venezuela/SKILL.md](../../.codex/skills/gestionar-clientes-venezuela/SKILL.md)|QA-CLI-001 a QA-CLI-018|
|ITM|[resources/js/views/tenant/documents/partials/item.vue](../../resources/js/views/tenant/documents/partials/item.vue), [app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [.codex/skills/mantener-flujo-productos-pro9/SKILL.md](../../.codex/skills/mantener-flujo-productos-pro9/SKILL.md)|QA-ITM-001 a QA-ITM-025|
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
|LIS|[resources/js/views/tenant/documents/index.vue](../../resources/js/views/tenant/documents/index.vue), [resources/js/views/tenant/documents/partials/detail-drawer.vue](../../resources/js/views/tenant/documents/partials/detail-drawer.vue), [resources/js/helpers/document-status.js](../../resources/js/helpers/document-status.js), [.codex/skills/mantener-operacion-local-fiscal-pro9/SKILL.md](../../.codex/skills/mantener-operacion-local-fiscal-pro9/SKILL.md)|QA-LIS-001 a QA-LIS-016|
|NVE|[resources/js/views/tenant/sale_notes](../../resources/js/views/tenant/sale_notes), [modules/Sale/Http/Controllers/GenerateDocumentController.php](../../modules/Sale/Http/Controllers/GenerateDocumentController.php), [tests/js/sale-note-invoice-submit.test.cjs](../../tests/js/sale-note-invoice-submit.test.cjs), [.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|QA-NVE-001 a QA-NVE-014|
|CAN|[routes/web.php](../../routes/web.php), [routes/api.php](../../routes/api.php), [modules/Hotel/Routes/web.php](../../modules/Hotel/Routes/web.php), [modules/Restaurant/Routes](../../modules/Restaurant/Routes), [modules/Ecommerce/Routes](../../modules/Ecommerce/Routes), [modules/Store/Routes](../../modules/Store/Routes), [modules/WhatsAppBot/Services/Tools/CreateDocumentTool.php](../../modules/WhatsAppBot/Services/Tools/CreateDocumentTool.php), [.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|QA-CAN-001 a QA-CAN-040|
|API|[routes/api.php](../../routes/api.php), [app/Http/Controllers/Tenant/Api/DocumentController.php](../../app/Http/Controllers/Tenant/Api/DocumentController.php), [app/CoreFacturalo/Requests/Api/Transform/DocumentTransform.php](../../app/CoreFacturalo/Requests/Api/Transform/DocumentTransform.php)|QA-API-001 a QA-API-008|
|IMP|[app/Http/Controllers/Tenant/DocumentController.php](../../app/Http/Controllers/Tenant/DocumentController.php), [app/Services/MassiveInvoiceService.php](../../app/Services/MassiveInvoiceService.php), [app/Http/Controllers/System/MassiveInvoiceController.php](../../app/Http/Controllers/System/MassiveInvoiceController.php), [public/formats/documents_second_format.xlsx](../../public/formats/documents_second_format.xlsx), [public/formats/formato_facturas_masivas.xlsx](../../public/formats/formato_facturas_masivas.xlsx)|QA-IMP-001 a QA-IMP-012|
|ANU|[app/Http/Controllers/Tenant/VoidedController.php](../../app/Http/Controllers/Tenant/VoidedController.php), [app/Http/Controllers/Tenant/DocumentController.php](../../app/Http/Controllers/Tenant/DocumentController.php), [tests/Unit/TestDocumentsDeletionBehaviorTest.php](../../tests/Unit/TestDocumentsDeletionBehaviorTest.php), [tests/Unit/TestDocumentsDeletionContractTest.php](../../tests/Unit/TestDocumentsDeletionContractTest.php)|QA-ANU-001 a QA-ANU-009|
|FIN|[modules/Finance/Routes/web.php](../../modules/Finance/Routes/web.php), [modules/Report/Routes/web.php](../../modules/Report/Routes/web.php), [modules/Dashboard](../../modules/Dashboard), [resources/views/pdf/partials/document_fiscal_totals.blade.php](../../resources/views/pdf/partials/document_fiscal_totals.blade.php)|QA-FIN-001 a QA-FIN-010|
|SEG|[app/Http/Controllers/Tenant/DocumentFiscalController.php](../../app/Http/Controllers/Tenant/DocumentFiscalController.php), [app/Http/Controllers/Tenant/DocumentPdfController.php](../../app/Http/Controllers/Tenant/DocumentPdfController.php), [.codex/skills/mantener-seguridad-pro9/SKILL.md](../../.codex/skills/mantener-seguridad-pro9/SKILL.md), [informes/auditoria_seguridad_pro9.md](../../informes/auditoria_seguridad_pro9.md)|QA-SEG-001 a QA-SEG-012|
|ROB|[tests/Unit/FiscalEmissionSchemaTest.php](../../tests/Unit/FiscalEmissionSchemaTest.php), [tests/Support/fiscal_payment_concurrency_worker.php](../../tests/Support/fiscal_payment_concurrency_worker.php), [tests/js](../../tests/js), `flujo completo de facturación` (patrón o punto de referencia; resolver en la versión de la corrida)|QA-ROB-001 a QA-ROB-006|
|MAT|[app/Services/SalesCustomerIdentityPolicy.php](../../app/Services/SalesCustomerIdentityPolicy.php), [.codex/skills/gestionar-clientes-venezuela/SKILL.md](../../.codex/skills/gestionar-clientes-venezuela/SKILL.md), [.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|QA-MAT-001 a QA-MAT-208|
|NEG|[app/Services/SalesDocumentTypePolicy.php](../../app/Services/SalesDocumentTypePolicy.php), [app/Services/ExportInvoicePolicy.php](../../app/Services/ExportInvoicePolicy.php), [app/Services/ThirdPartyInvoicePolicy.php](../../app/Services/ThirdPartyInvoicePolicy.php), [.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|QA-NEG-001 a QA-NEG-019|
|PRE|[app/CoreFacturalo/Facturalo.php](../../app/CoreFacturalo/Facturalo.php), [app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [resources/js/views/tenant/documents/invoice_generate.vue](../../resources/js/views/tenant/documents/invoice_generate.vue), [modules/Document/Routes/web.php](../../modules/Document/Routes/web.php)|QA-PRE-001 a QA-PRE-012|
|COM|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php), [resources/js/views/tenant/documents/invoice_generate.vue](../../resources/js/views/tenant/documents/invoice_generate.vue), [config/venezuela.php](../../config/venezuela.php)|QA-COM-001 a QA-COM-030|

## Hashes de archivos concretos de referencia

| Grupo | Archivo | SHA-256 |
|---|---|---|
|ENT|[routes/web.php](../../routes/web.php)|`a238475552689544533941428eba7eceeaa5f789a3f5752a2e0dadb01ce7bcb3`|
|ENT|[routes/api.php](../../routes/api.php)|`868db4a841f3486b956000d699ed6f0ff4c264abaec0665eaac20ea5167912e0`|
|ENT|[resources/views/tenant/documents/form.blade.php](../../resources/views/tenant/documents/form.blade.php)|`056f80f86f33366ee5cdc9244202745e3a9b4351eaad1a56a2196287256747e2`|
|CFG|[app/Services/FiscalEmissionSettings.php](../../app/Services/FiscalEmissionSettings.php)|`ab8d1147c6f8583ab538cda26b5c461425f02ff8fb1850fd2f89bc3ecb41193c`|
|CFG|[app/Services/Fiscal/HkaAuthentication.php](../../app/Services/Fiscal/HkaAuthentication.php)|`540b09ab781f2d6fdb8735bcde280608dd632e71e8429b4d46a94b3820cb69e0`|
|CFG|[.codex/skills/configurar-conexion-hka-pro9/SKILL.md](../../.codex/skills/configurar-conexion-hka-pro9/SKILL.md)|`b67a78e0c17e68af9146973590de59bccd742229d6fdb13559b9910c23732844`|
|NUM|[app/Services/SeriesNumbering.php](../../app/Services/SeriesNumbering.php)|`08e7847a5e82edb3c075318a87c81ade18000a6dde7b01ff16e615076ea544ae`|
|NUM|[app/Services/SeriesAdministration.php](../../app/Services/SeriesAdministration.php)|`0ab98ebb8cce26ab7d92317a9f3590f15e755895a52e3252c5f2b21eaf140e42`|
|NUM|[app/Services/SeriesResolver.php](../../app/Services/SeriesResolver.php)|`f1dd2363b6890bc05aead66f2e785c6abc3c08190b4d98fe5ea2ba9b20c0f240`|
|NUM|[app/Services/Fiscal/FiscalIdentity.php](../../app/Services/Fiscal/FiscalIdentity.php)|`f4279648d961e6696beffbe7e961184fde12397e300c17bfb5f407f52ec4c8e9`|
|NUM|[.codex/skills/mantener-numeracion-fiscal-venezuela-pro9/SKILL.md](../../.codex/skills/mantener-numeracion-fiscal-venezuela-pro9/SKILL.md)|`e822ac1ab23417275ff75c485bfb535389ac0392a013e07a2ea8b09ceb1ae2ff`|
|CLI|[app/Services/SalesCustomerIdentityPolicy.php](../../app/Services/SalesCustomerIdentityPolicy.php)|`6cd9704d7536bb9a4c4e0a7b55899f2e346cf07a494f5371990535fa255321c9`|
|CLI|[resources/js/views/tenant/persons/form.vue](../../resources/js/views/tenant/persons/form.vue)|`6eb77517f7d9e3fe9ba6a54bad3c4a14c366c27364b8f609ea3050453e489aee`|
|CLI|[resources/js/views/tenant/documents/_document_form_fields_catalog.js](../../resources/js/views/tenant/documents/_document_form_fields_catalog.js)|`4f17e555df74e296470dfda38668eceda8412508432c504c3a90abac634d0929`|
|CLI|[.codex/skills/gestionar-clientes-venezuela/SKILL.md](../../.codex/skills/gestionar-clientes-venezuela/SKILL.md)|`2b69027827096360cee4e6836de03931ecb1eaf1270705c9521adf8abc62957f`|
|ITM|[resources/js/views/tenant/documents/partials/item.vue](../../resources/js/views/tenant/documents/partials/item.vue)|`5f375a86dd39a5ac35748065df599476c85796fb2efda93a306c748c63bd321a`|
|ITM|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`01d3aad60b1b43a40a63a8d118fec2ac16964bc4ec92c76e82989adc3c1276e9`|
|ITM|[.codex/skills/mantener-flujo-productos-pro9/SKILL.md](../../.codex/skills/mantener-flujo-productos-pro9/SKILL.md)|`45516ef1e4873a92a9b2ba00525095c69be76a43c2e1132e97ce8fd49fbc2f4a`|
|CAL|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`01d3aad60b1b43a40a63a8d118fec2ac16964bc4ec92c76e82989adc3c1276e9`|
|CAL|[config/venezuela.php](../../config/venezuela.php)|`787a3a2cdb0b7eff3efff7762e78f818f3e049ca6fd38d2a422e0e1ce2d99e0d`|
|CAL|[tests/Unit/FiscalLineCalculationTest.php](../../tests/Unit/FiscalLineCalculationTest.php)|`67b93742ddf0812b0e89e81f0eea7544782bbffd580f7be6b1d0c06c292106c6`|
|FAC|[resources/js/views/tenant/documents/invoice_generate.vue](../../resources/js/views/tenant/documents/invoice_generate.vue)|`3c0beb0b954fc2bc5d3166787c75c0cf169e39d27d1eeffb60eb66371076750b`|
|FAC|[app/CoreFacturalo/Facturalo.php](../../app/CoreFacturalo/Facturalo.php)|`35c1e8dc046fb4942d028b2fc2c7366d759270ad196f2d9540d772302436301b`|
|FAC|[app/Http/Controllers/Tenant/DocumentController.php](../../app/Http/Controllers/Tenant/DocumentController.php)|`ee30b45f162b2a262955e426dab21237167dc06e20f8c0e51bbb95d4dba8cb41`|
|EXP|[app/Services/ExportInvoicePolicy.php](../../app/Services/ExportInvoicePolicy.php)|`42135c3e441da619a0758595ad7e7225f6d431f476aa3a93a852b21c0b1cbe92`|
|EXP|[informes/facturas_exportacion_hka.md](../../informes/facturas_exportacion_hka.md)|`6b8cf2083ede3cec8b1d10e66345a219d2f4a7e8f5a25aea300925764385ffa7`|
|EXP|[tests/Unit/ExportInvoiceTest.php](../../tests/Unit/ExportInvoiceTest.php)|`f90b608053b1509fab7f18d8b6190d98d0df67074065f7e1aaed801106c99fab`|
|TER|[app/Services/ThirdPartyInvoicePolicy.php](../../app/Services/ThirdPartyInvoicePolicy.php)|`1ca2bec3e335ed5e343b00e10dd2da7cd86d9c6e8b66173c6712b53fed8c8f24`|
|TER|[app/Services/Fiscal/HkaThirdPartyMoney.php](../../app/Services/Fiscal/HkaThirdPartyMoney.php)|`3fb4434939e50288732a4dc29f80927b89ac52b3e9281a2e46c446d0970fb44a`|
|TER|[informes/facturas_cuenta_terceros_hka.md](../../informes/facturas_cuenta_terceros_hka.md)|`0664283ef0e628ccbe8a4e0dc92eac43e341d342ea0fd7cc1625b0cb8a299b3f`|
|NOT|[resources/js/views/tenant/documents/note.vue](../../resources/js/views/tenant/documents/note.vue)|`4eaa224a7f3fdd58b6e0f36d420926674532d505cc78585dd9fe08094175b937`|
|NOT|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`01d3aad60b1b43a40a63a8d118fec2ac16964bc4ec92c76e82989adc3c1276e9`|
|NOT|[app/Services/Fiscal/HkaPayloadBuilder.php](../../app/Services/Fiscal/HkaPayloadBuilder.php)|`629de0f55ba2f0a865e3441e5be319bedbd8d69d8e59e5bb8b368839934bc889`|
|NOT|[database/seeders/data/tenant_initial_data.php](../../database/seeders/data/tenant_initial_data.php)|`128a187ae7a57e259854e64160dbab0d2e4e2d06d107427c2dd5d8de39afc450`|
|PAG|[app/Services/Fiscal/FiscalAmounts.php](../../app/Services/Fiscal/FiscalAmounts.php)|`7494070df30982c79a09bb7051ad13cbccc2f12ffa50a9453d6506baaf4795b9`|
|PAG|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`01d3aad60b1b43a40a63a8d118fec2ac16964bc4ec92c76e82989adc3c1276e9`|
|PAG|[app/Http/Controllers/Tenant/DocumentPaymentController.php](../../app/Http/Controllers/Tenant/DocumentPaymentController.php)|`77d5f3cd00b74b45deb04426337acee748bc02e0c2be7041ce1c35b65c04e21a`|
|PAG|[tests/Unit/FiscalApiPaymentTransformTest.php](../../tests/Unit/FiscalApiPaymentTransformTest.php)|`97e8b993a37305d402a2f55be02c77b63dca559053a2f4d8aa254e8dc0488a62`|
|IGT|[app/Services/Fiscal/FiscalAmounts.php](../../app/Services/Fiscal/FiscalAmounts.php)|`7494070df30982c79a09bb7051ad13cbccc2f12ffa50a9453d6506baaf4795b9`|
|IGT|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`01d3aad60b1b43a40a63a8d118fec2ac16964bc4ec92c76e82989adc3c1276e9`|
|IGT|[resources/js/views/tenant/documents/partials/igtf_fields.vue](../../resources/js/views/tenant/documents/partials/igtf_fields.vue)|`85e5d759b2f96adb56dc46837b0ff2182a5beccfb9dd654f9f3fd059f4a867d0`|
|RET|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`01d3aad60b1b43a40a63a8d118fec2ac16964bc4ec92c76e82989adc3c1276e9`|
|RET|[app/Http/Controllers/Tenant/DocumentFiscalController.php](../../app/Http/Controllers/Tenant/DocumentFiscalController.php)|`891fcb86e6ef71162e9329146363ecfef35121a921939a29af2bf24461193802`|
|RET|[resources/js/views/tenant/documents/partials/retention.vue](../../resources/js/views/tenant/documents/partials/retention.vue)|`a24313e46502f282e8a98951dd1ceb8fcbfc38143e5d1f93383c8a77385ff86b`|
|EDI|[app/Services/Fiscal/DocumentEditPolicy.php](../../app/Services/Fiscal/DocumentEditPolicy.php)|`34eec63dac0729f5381af5b06240591951aef0fc872244414f80ca7a4f3dd8ed`|
|EDI|[app/Services/Fiscal/DocumentEditSettlements.php](../../app/Services/Fiscal/DocumentEditSettlements.php)|`749fe34c58b33b733c5a6c85f1d3abb6a6fde79a8e6f89f82fb234e3cda5c3c8`|
|EDI|[app/Http/Requests/Tenant/DocumentUpdateRequest.php](../../app/Http/Requests/Tenant/DocumentUpdateRequest.php)|`874ad2b570a0bcf4d4e8d4562f6bcd082e5214d6d8b4336e526abd7fd2948d07`|
|EDI|[informes/edicion_facturas_antes_hka.md](../../informes/edicion_facturas_antes_hka.md)|`3c3cb0806881edd6e6264d248f024ba152c3d8cf12cc221cc013b42feecc3032`|
|HKA|[app/Services/Fiscal/HkaEmissionPreparation.php](../../app/Services/Fiscal/HkaEmissionPreparation.php)|`3e38b6e4a7768456ea761639be37d99c23f5ef822d27207729f2eb1f92b26c4b`|
|HKA|[app/Services/Fiscal/HkaEmission.php](../../app/Services/Fiscal/HkaEmission.php)|`0da5d839468dc544829bc991836529ce230d044c53b896b30ea14d33ba7a8327`|
|HKA|[app/Services/Fiscal/HkaResponse.php](../../app/Services/Fiscal/HkaResponse.php)|`b5d3055c2cc900191af10599c44421e32a7ce5fbedd0bdf4da1195ac83bdc1cc`|
|HKA|[app/Services/Fiscal/HkaTransport.php](../../app/Services/Fiscal/HkaTransport.php)|`b58bdaa6e3d6bb3cdda579d9a05541ecaac3a37dcb063462dddfe6554ae71699`|
|PDF|[app/Services/Fiscal/HkaPdf.php](../../app/Services/Fiscal/HkaPdf.php)|`cb7ec643af4cc22b326380f9fd65871b9946bfc39876d9ae1c0c7a16c4b19426`|
|PDF|[app/Services/Fiscal/HkaPdfStore.php](../../app/Services/Fiscal/HkaPdfStore.php)|`d9f01ead85af6b43b1c07d248fde4f5ec0fcd1356de8a9f896c11622a4e5fd9b`|
|PDF|[app/Services/Fiscal/HkaA5Pdf.php](../../app/Services/Fiscal/HkaA5Pdf.php)|`2c6608d2693da4ab69d286a6e00f3aafc44a13c3fa1b76ccf7807f4f183dd28d`|
|PDF|[app/Services/Fiscal/HkaTicketPdf.php](../../app/Services/Fiscal/HkaTicketPdf.php)|`3acdb260db3e1e15f7e7f4a01d5844c42367902e732f6d8e7f59c9767976e32f`|
|PDF|[.codex/skills/distribuir-documentos-hka/SKILL.md](../../.codex/skills/distribuir-documentos-hka/SKILL.md)|`f85ed74db045121237513f5323a5bed13fe1f88de34366c456a508cc279a7dcf`|
|COR|[app/Services/Fiscal/HkaMail.php](../../app/Services/Fiscal/HkaMail.php)|`8806ddc9981a0486941bb25cda40988407bf1dae7a397168f4371095b6ca19fd`|
|COR|[resources/js/mixins/document-email.js](../../resources/js/mixins/document-email.js)|`e3f4e186f1457cdcbe184ce44e8fa2c5682e1ee1a5d0de1c2032d141696d2630`|
|COR|[resources/js/views/tenant/documents/partials/options.vue](../../resources/js/views/tenant/documents/partials/options.vue)|`55a5ee7629a2f62a9c30131f641cfe38884696a81b5d15f06121b462650ea828`|
|COR|[resources/js/views/tenant/pos/partials/options.vue](../../resources/js/views/tenant/pos/partials/options.vue)|`d0c0190cb70837af42aeac31cf1cc09a3a75fdba04d8f03cd2f0aac18a35ac7a`|
|LIS|[resources/js/views/tenant/documents/index.vue](../../resources/js/views/tenant/documents/index.vue)|`1dcfddd0a67a4ae2d4c29b97dfef9a8e4434e8896125d0a1abbc7954de5c910d`|
|LIS|[resources/js/views/tenant/documents/partials/detail-drawer.vue](../../resources/js/views/tenant/documents/partials/detail-drawer.vue)|`3f8a7777e9cba014dcd6916122b30c149489d4c3b8172c0f66275071c0dcfcc0`|
|LIS|[resources/js/helpers/document-status.js](../../resources/js/helpers/document-status.js)|`f9022f66f12dfcda6078d83e841c5cb0941d4c1b52800f034dada61b7de131f0`|
|LIS|[.codex/skills/mantener-operacion-local-fiscal-pro9/SKILL.md](../../.codex/skills/mantener-operacion-local-fiscal-pro9/SKILL.md)|`0f5625ef8006182aaaa0d5ce8b01ec5593600c97b3fc4b7c7b67042458fcd935`|
|NVE|[modules/Sale/Http/Controllers/GenerateDocumentController.php](../../modules/Sale/Http/Controllers/GenerateDocumentController.php)|`7700efd883db0ffda3f27a9ed6ba653718652afc4d7c84add523b905d5fd53ae`|
|NVE|[tests/js/sale-note-invoice-submit.test.cjs](../../tests/js/sale-note-invoice-submit.test.cjs)|`ad659da9f200a1c045641b7d476405e2ffdd1f8a9eb569706b300f3769d8056a`|
|NVE|[.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|`488a5cfb60a8676ed0e7661434518d9048328bc6e5336aace4b035ada6631794`|
|CAN|[routes/web.php](../../routes/web.php)|`a238475552689544533941428eba7eceeaa5f789a3f5752a2e0dadb01ce7bcb3`|
|CAN|[routes/api.php](../../routes/api.php)|`868db4a841f3486b956000d699ed6f0ff4c264abaec0665eaac20ea5167912e0`|
|CAN|[modules/Hotel/Routes/web.php](../../modules/Hotel/Routes/web.php)|`2192126768d47111867cc4f98e1f69582c43c660835c7f906f2701e937e9abbe`|
|CAN|[modules/WhatsAppBot/Services/Tools/CreateDocumentTool.php](../../modules/WhatsAppBot/Services/Tools/CreateDocumentTool.php)|`d1cd8e5159a7d9452a3ce6692c6dbff2104968cd6f9810b64e0c30328d7d320a`|
|CAN|[.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|`488a5cfb60a8676ed0e7661434518d9048328bc6e5336aace4b035ada6631794`|
|API|[routes/api.php](../../routes/api.php)|`868db4a841f3486b956000d699ed6f0ff4c264abaec0665eaac20ea5167912e0`|
|API|[app/Http/Controllers/Tenant/Api/DocumentController.php](../../app/Http/Controllers/Tenant/Api/DocumentController.php)|`fc71424ec693f9da1ce8d3d9b02f05c798863f42f049520ccbcddde78d9be1a2`|
|API|[app/CoreFacturalo/Requests/Api/Transform/DocumentTransform.php](../../app/CoreFacturalo/Requests/Api/Transform/DocumentTransform.php)|`a51e716ed21581aa2021759a1ce5367f2291102a22b10f1948e448daf3e8e80f`|
|IMP|[app/Http/Controllers/Tenant/DocumentController.php](../../app/Http/Controllers/Tenant/DocumentController.php)|`ee30b45f162b2a262955e426dab21237167dc06e20f8c0e51bbb95d4dba8cb41`|
|IMP|[app/Services/MassiveInvoiceService.php](../../app/Services/MassiveInvoiceService.php)|`2b6e4a8894b4bb1ab78d8d3a568e9b3243d93dcd74543cc16d614879083240b2`|
|IMP|[app/Http/Controllers/System/MassiveInvoiceController.php](../../app/Http/Controllers/System/MassiveInvoiceController.php)|`9f0a57960e999444adf45ad98e6ebe185874b77a2a6c1a8003f6842a9b02e980`|
|IMP|[public/formats/documents_second_format.xlsx](../../public/formats/documents_second_format.xlsx)|`010235cf2ff12b0803b57398133ff7323673c102aff1a5e10803357d8157d14f`|
|IMP|[public/formats/formato_facturas_masivas.xlsx](../../public/formats/formato_facturas_masivas.xlsx)|`af6e28e99aeff806ca2de0ee8bc951032523d44c494cd0cf2c01523686b0810f`|
|ANU|[app/Http/Controllers/Tenant/VoidedController.php](../../app/Http/Controllers/Tenant/VoidedController.php)|`673de48eb661575999e7cddd769bb92ef3a80a74edc3be543f3e271ff30116c9`|
|ANU|[app/Http/Controllers/Tenant/DocumentController.php](../../app/Http/Controllers/Tenant/DocumentController.php)|`ee30b45f162b2a262955e426dab21237167dc06e20f8c0e51bbb95d4dba8cb41`|
|ANU|[tests/Unit/TestDocumentsDeletionBehaviorTest.php](../../tests/Unit/TestDocumentsDeletionBehaviorTest.php)|`65e35be9c2b1d0891d26cc5e79c2de270fff15f0aba051292577e8fdbea5fedb`|
|ANU|[tests/Unit/TestDocumentsDeletionContractTest.php](../../tests/Unit/TestDocumentsDeletionContractTest.php)|`9db687f029ffad5ded10309eaf4980d2b6d68d550bcdd3bf00133484cc4d57ce`|
|FIN|[modules/Finance/Routes/web.php](../../modules/Finance/Routes/web.php)|`6b3470701857d9f5f03ab2215431d0038b6420d0c0d93b608e23243ae02eae12`|
|FIN|[modules/Report/Routes/web.php](../../modules/Report/Routes/web.php)|`4dfedeed0252b9e5ab5e7a1a6828223168d04c03d20768b1b56551e92a25cb6d`|
|FIN|[resources/views/pdf/partials/document_fiscal_totals.blade.php](../../resources/views/pdf/partials/document_fiscal_totals.blade.php)|`1ca513ef6fe1dec9de3384e77b0914b7a300b08bf5f8d393b1b7938c104e98d9`|
|SEG|[app/Http/Controllers/Tenant/DocumentFiscalController.php](../../app/Http/Controllers/Tenant/DocumentFiscalController.php)|`891fcb86e6ef71162e9329146363ecfef35121a921939a29af2bf24461193802`|
|SEG|[app/Http/Controllers/Tenant/DocumentPdfController.php](../../app/Http/Controllers/Tenant/DocumentPdfController.php)|`608468a7ad87f58d50fcc38a5ecfb90561221ef542a3d2ec43c11f09d0b7dc15`|
|SEG|[.codex/skills/mantener-seguridad-pro9/SKILL.md](../../.codex/skills/mantener-seguridad-pro9/SKILL.md)|`6fbde65640f4c991dc0d6e98989b6ede9e3d869662d5ebd1ccfd40d866762e34`|
|SEG|[informes/auditoria_seguridad_pro9.md](../../informes/auditoria_seguridad_pro9.md)|`29e7785eea0c4ee7883a37bfc7726d1d7adffec487256836e702569394f7c2b1`|
|ROB|[tests/Unit/FiscalEmissionSchemaTest.php](../../tests/Unit/FiscalEmissionSchemaTest.php)|`5b9b24a5e53a7390ac5a1658273662528b07f71bd02463c52910f3bf32b4c15d`|
|ROB|[tests/Support/fiscal_payment_concurrency_worker.php](../../tests/Support/fiscal_payment_concurrency_worker.php)|`6606c19387267a366dbace0e60417b57bb56ebeed20a2d6998b0cde11125aea0`|
|MAT|[app/Services/SalesCustomerIdentityPolicy.php](../../app/Services/SalesCustomerIdentityPolicy.php)|`6cd9704d7536bb9a4c4e0a7b55899f2e346cf07a494f5371990535fa255321c9`|
|MAT|[.codex/skills/gestionar-clientes-venezuela/SKILL.md](../../.codex/skills/gestionar-clientes-venezuela/SKILL.md)|`2b69027827096360cee4e6836de03931ecb1eaf1270705c9521adf8abc62957f`|
|MAT|[.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|`488a5cfb60a8676ed0e7661434518d9048328bc6e5336aace4b035ada6631794`|
|NEG|[app/Services/SalesDocumentTypePolicy.php](../../app/Services/SalesDocumentTypePolicy.php)|`f1ae1733bcb9d4baa8d7137c05d6d5f56dd21f35dd64e7b80998c3f5ea9aad85`|
|NEG|[app/Services/ExportInvoicePolicy.php](../../app/Services/ExportInvoicePolicy.php)|`42135c3e441da619a0758595ad7e7225f6d431f476aa3a93a852b21c0b1cbe92`|
|NEG|[app/Services/ThirdPartyInvoicePolicy.php](../../app/Services/ThirdPartyInvoicePolicy.php)|`1ca2bec3e335ed5e343b00e10dd2da7cd86d9c6e8b66173c6712b53fed8c8f24`|
|NEG|[.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md](../../.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md)|`488a5cfb60a8676ed0e7661434518d9048328bc6e5336aace4b035ada6631794`|
|PRE|[app/CoreFacturalo/Facturalo.php](../../app/CoreFacturalo/Facturalo.php)|`35c1e8dc046fb4942d028b2fc2c7366d759270ad196f2d9540d772302436301b`|
|PRE|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`01d3aad60b1b43a40a63a8d118fec2ac16964bc4ec92c76e82989adc3c1276e9`|
|PRE|[resources/js/views/tenant/documents/invoice_generate.vue](../../resources/js/views/tenant/documents/invoice_generate.vue)|`3c0beb0b954fc2bc5d3166787c75c0cf169e39d27d1eeffb60eb66371076750b`|
|PRE|[modules/Document/Routes/web.php](../../modules/Document/Routes/web.php)|`516b0b9a41d97cf80ba7c845ad26a82853bedbc172bdbe25caeae8dcbbc46f78`|
|COM|[app/Services/Fiscal/FiscalDocumentPersistence.php](../../app/Services/Fiscal/FiscalDocumentPersistence.php)|`01d3aad60b1b43a40a63a8d118fec2ac16964bc4ec92c76e82989adc3c1276e9`|
|COM|[resources/js/views/tenant/documents/invoice_generate.vue](../../resources/js/views/tenant/documents/invoice_generate.vue)|`3c0beb0b954fc2bc5d3166787c75c0cf169e39d27d1eeffb60eb66371076750b`|
|COM|[config/venezuela.php](../../config/venezuela.php)|`787a3a2cdb0b7eff3efff7762e78f818f3e049ca6fd38d2a422e0e1ce2d99e0d`|

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
