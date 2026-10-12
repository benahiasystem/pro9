---
name: mantener-sujetos-pasivos-especiales-pro9
description: Mantener los perfiles fiscales versionados SPE de emisor y comprador en Pro9, RIF separado de cédula, vigencia, facultades IVA/ISLR y evaluación del cobro IGTF en Web, POS y Garage. Usar al modificar SpecialTaxpayerPolicy, FiscalProfileService, fiscal-profiles, el editor fiscal o sus snapshots y propuestas; emisión y distribución HKA tienen habilidades propias.
---

# Sujetos Pasivos Especiales de Pro9

## Alcance y fuentes

Contrato implementado el 12 de octubre de 2026 para instalaciones nuevas. Leer [datos, endpoints y consumidores](references/contratos.md) al modificar perfiles, persistencia o formularios. El [informe SPE/HKA](../../../informes/facturas_sujetos_pasivos_especiales_hka.md) conserva fuentes normativas, resultados y límites; sus conteos históricos no sustituyen las pruebas del cambio actual.

SPE, naturaleza jurídica, firma personal, actividad comercial, condición IVA y facultades IVA/ISLR son hechos independientes. El perfil tributario no es un perfil de numeración ni una reserva/asignación HKA. No recrear el módulo retirado de secuencias ni convertir tenants existentes para introducir este contrato.

## Identidad, versiones y fechas

- Usar `SpecialTaxpayerPolicy` para validar/evaluar y `FiscalProfileService` para versiones. No inferir SPE de la letra del RIF, naturaleza pública, tasa IGTF o bandera comercial de agente.
- Mantener `fiscal_profile.rif` completo y separado de `persons.number`. Validar naturaleza/prefijo, actividad comercial del emisor y correspondencia del RIF de empresa con `companies.number`. No sobrescribir cédula ni catálogo de identidad, ni afirmar consulta/inscripción SENIAT.
- SPE exige notificación expresa, referencia y fecha coherente. Resolver la última versión por vigencia y después ID; evaluar fin y revocación en la fecha del documento/cobro. La revocación SPE no borra facultades públicas o ISLR independientes.
- Guardar una versión nueva con actor/fecha bajo bloqueo empresa → entidad y autorización administrativa tenant. Rechazar `fiscal_profile` en escrituras genéricas de empresa/persona; no añadirlo a asignación masiva ni editar versiones utilizadas.
- Congelar perfiles y `version_id` de emisor/comprador en factura, cobro y comprobante recibido. Un cambio posterior no recalcula históricos ni payloads preparados. La edición comercial autorizada obtiene el perfil del comprador a la fecha de emisión original.
- Sin tabla de versiones, la consulta devuelve perfil vacío y el editor no habilita escritura; no sembrar una condición supuesta. ND de exportación limpia el perfil doméstico y conserva su identificación externa.

## Cobros, IGTF y crédito

- Aplicar [persistencia fiscal](../mantener-persistencia-fiscal-venezuela-pro9/SKILL.md) y [moneda](../migrar-venezuela-moneda/SKILL.md): separar moneda documental/recibida, principal recibido `original_amount`, aplicado `payment`, impuesto recibido `tax_amount` y tasa VES/USD exacta de ocho decimales.
- La clasificación implementada es `subject` para emisor SPE vigente + recepción USD + `financial_intermediation=none`. Cobro USD de SPE exige intermediación explícita. Moneda de la factura, método de pago o SPE del comprador no la sustituyen. Rechazar clasificación publicada contradictoria; el impuesto sujeto necesita además habilitación/tasa positiva del tenant.
- `exempt` exige motivo, fundamento y administrador; congelar fundamento/actor. Una propuesta informativa no reemplaza la revalidación del cobro bajo bloqueo.
- Repetir `operation_key` devuelve el cobro con su contexto original. Cambiar importe, fecha, tasa, medio, intermediación o fundamento con esa clave produce error.
- Compartir `igtf_fields.vue` entre Web, POS, Garage y cobros posteriores; descartar propuestas asincrónicas obsoletas y conservar UUID/conversiones del borrador.
- Crédito elimina el cobro sugerido. Reabrir Pagos o recuperar el borrador tras recargar conserva sólo abonos introducidos, sin añadir/distribuir el total de factura. Pasar `paymentConditionId` a los diálogos y conservar condición/importes en almacenamiento del borrador. Saldo pendiente no equivale a caja ni justifica una `formaPago` ficticia HKA.

## Retenciones y nota exclusiva

- Seguir [el contrato de comprobantes recibidos](references/contratos.md#retenciones-recibidas). Una propuesta no aplica saldo: sólo un comprobante real validado con soporte privado lo hace.
- PN sin firma personal no obtiene facultad general IVA por SPE. Entes públicos bajo norma específica requieren evidencia y ámbito de operación documentado. ISLR se acredita separadamente. Proveedor formal/no sujeto o exclusión acreditada impiden propuesta IVA.
- El 75 % se aplica al IVA; 100 exige causal/evidencia. No deducir conceptos/tasas ISLR de SPE ni de etiquetas históricas.
- IGTF posterior genera `08` local, motivo `IGTF`, transacción HKA `98`, sin líneas/IVA/inventario; hereda modalidad/ambiente de factura y fecha/tasa/perfiles del cobro. Seguir [emisión](../emitir-facturas-notas-hka/SKILL.md) y [distribución](../distribuir-documentos-hka/SKILL.md) para envío limitado y recuperación. Nota preparada/confirmada bloquea la reversión completa; no habilitar otras notas o cancelación remota por extensión.

## HKA y evidencia

Enviar SPE desde snapshot mediante `infoAdicional`, sin reutilizar `regimenEspTributacion`, `tipoProveedor` o banderas inventadas. Constructor puro y payloads preparados inmutables; adaptación doméstica USD/IGTF una sola vez, conservando dinero local y referencia monetaria original de factura afectada.

La cuenta DEMO comprobada es jurídica FORMAL: acredita compradores sintéticos SPE y contado VES/USD, no emisores SPE reales ni notas IGTF aceptadas. Crédito sin cobros fue rechazado 203/1015. JSON conserva la leyenda SPE, pero A4/A5 la omiten y duplican el prefijo de identidad del comprador. Preservar originales y esos límites. Sin habilitación/contrato HKA, documentar el pendiente y detener reintentos sin cambios; el [borrador de solicitud](../../../informes/solicitud_hka_emisores_spe_demo.md) no autoriza su envío.

Para QA real autorizada, comprobar identidad/clasificación de cuenta autenticada frente a emisor local y PDF remoto antes de emitir la matriz. Copiar credenciales no acredita correspondencia. Una discrepancia conserva evidencia parcial; corregir el tenant desechable sólo afecta documentos nuevos, sin relabelar una cuenta FORMAL como SPE. Archivar originales/respuestas saneadas/hashes/conciliación JSON-PDF-QR de forma privada y retirar únicamente instalaciones/bases propias de QA; no borrar registros remotos ni incluir RIF reales, JWT o credenciales en fixtures.

## Verificación según el cambio

- `SpecialTaxpayerPolicyTest`: fechas, naturaleza/firma, facultades públicas, 75/100, ISLR, intermediación y autorización.
- MySQL temporal: `FiscalEmissionSchemaTest` con `PRO9_FISCAL_MYSQL_TESTS=1`, casos `test_spe_*`, instalación/rollback/repetición, concurrencia y commit exterior. `SpeProfiles`/`SpePersistenceCases` usan datos sintéticos; sus HTTP no acreditan aceptación real. Usar memoria suficiente para la matriz; identificar bases propias huérfanas tras una terminación fatal antes de retirarlas.
- `HkaPayloadBuilderTest`, `ThirdPartyInvoiceTest`, `SpecialTaxRegimeTest`, `ExportInvoiceTest`: identidad, leyendas, USD/IGTF y fronteras de otros flujos.
- `tests/js/fiscal-payment-ui.test.cjs` y `pos-hka-email.test.cjs`: propuesta/conversión, eventos Crédito, reapertura/cancelación de Pagos y recuperación de abonos.
- `CurrentPdfRenderingTest`/`CurrentPdfTemplateContractTest` y revisión visual: perfil congelado/local/ticket. No confundir ticket local con original HKA.
- Seguir [frontend-build](../frontend-build/SKILL.md): compilación a cargo del usuario salvo instrucción explícita. Verificar manifiesto/asset actuales y comportamiento visible por entrada; compilar una plantilla o probar métodos no descubre necesariamente un manejador ausente o un cobro recreado al abrir Pagos.
