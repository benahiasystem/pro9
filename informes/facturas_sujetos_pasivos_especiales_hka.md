# Perfiles SPE, cobros y retenciones — Pro9 / HKA

Fecha de verificación: 12 de octubre de 2026. Implementación destinada al esquema inicial de instalaciones nuevas. No se reconstruyeron tenants existentes ni se alteraron documentos históricos de tenants existentes. Cambios locales sin commit ni despliegue.

## Resultado y alcance acreditado

Pro9 conserva perfiles fiscales versionados de emisor y comprador, separa el RIF fiscal de la cédula y evalúa SPE, capacidad de retención y cobro IGTF por fecha. Web, POS y Garage comparten la persistencia y los campos de cobro. Las retenciones recibidas requieren comprobante y soporte privado; aplican saldo sin crear efectivo. El IGTF de un cobro posterior genera una nota exclusiva vinculada al cobro y puede enviarse después del commit cuando la factura tiene control HKA confirmado.

La verificación local cubre emisores comerciales PN, firma personal y PJ SPE, compradores PN, firma personal, PJ y seis clases de entes públicos. **La aceptación real DEMO acredita únicamente facturas al contado con el emisor jurídico FORMAL disponible y compradores sintéticos SPE.** No acredita homologación, producción, emisores SPE reales ni aceptación remota de notas IGTF.

| Verificación real DEMO | Resultado |
|---|---|
| 9 compradores × Web/POS/Garage × VES/USD × contado/crédito | 108 documentos iniciales por el servicio común; 54 adicionales con emisor local alineado |
| Contado, emisor FORMAL, líneas exentas | 54 confirmadas con identidad del emisor alineada, controles `00-00000136` a `00-00000189` |
| Crédito sin cobro ni forma de pago ficticia | 54 rechazadas por HKA, código 203 / validación 1015 |
| JSON descargado y reconciliado con identidad, tipo, moneda, totales y leyenda enviada | 54 conformes |
| A4/A5 originales y ticket local: control, firma PDF y hashes | 162 archivos comprobados |
| QR A4/A5/ticket actual decodificado contra la consulta de su emisión | 162 conformes; se normalizó sólo `/` vacío para comparar URLs |
| Leyenda SPE en ticket local regenerado | 54 comprobadas |
| Cambio de perfil desde navegador | Nueva versión registrada; 108 snapshots anteriores conservados |

La primera matriz heredó la identidad local ficticia del tenant de origen, mientras HKA imprimió la empresa autenticada. Sus 54 controles 82–135 se conservan como evidencia parcial de compradores, moneda y transporte; no acreditan correspondencia del emisor. Se alinearon únicamente el nombre/RIF del tenant desechable y su perfil FORMAL, manteniendo SPE deshabilitado, y se repitieron los 54 casos al contado sin alterar originales anteriores. La matriz alineada es la que acredita la correspondencia de identidad local/PDF remoto. Los rechazos de crédito originales conservan su diagnóstico; no se fabricó un pago para conseguir aceptación.

Las entradas Web/POS/Garage de esa matriz se ejecutaron desde el servicio comercial común con `source_module` correspondiente. No representan 108 operaciones manuales de navegador. Las comprobaciones visuales por canal se describen aparte.

## Contrato fiscal y decisiones

- SPE exige calificación y notificación expresa, referencia y fecha; su vigencia y revocación se evalúan en la fecha del documento o del cobro. No se deduce de la letra del RIF ni de ser comprador público.
- Persona natural, firma personal, persona jurídica y naturaleza pública se registran independientemente de IVA ordinario/formal/no sujeto, SPE y facultades IVA/ISLR. Un emisor necesita actividad comercial; un ente configurado sólo como agente/comprador no queda habilitado para facturar por esa condición.
- La facultad general de retención IVA bajo SNAT/2025/000054 no se concede a PN sin firma personal. Los entes públicos sujetos a su normativa específica requieren fundamento y revisión documentada del ámbito de la operación. La condición SPE no concede automáticamente facultad ISLR.
- La propuesta IVA usa 75 %; 100 % exige causal identificada y evidencia. Se validan RIF del agente, fecha, número de comprobante de catorce dígitos, control/número de factura, base sobre el IVA, fórmula, saldo y acumulados. ISLR exige concepto vigente, fundamento de aplicación y evidencia; el concepto salarial no se usa para ventas propias. No se infiere una tasa ISLR de etiquetas históricas del catálogo.
- El cobro IGTF se evalúa sobre el principal efectivamente recibido en USD por un emisor SPE vigente, sin institución financiera. La intermediación es explícita. No se determina por la moneda documental o por la condición del comprador. Una excepción requiere motivo, fundamento y autorización administrativa.
- Una venta a crédito conserva saldo y vencimientos; no inventa dinero recibido. Sólo un abono real puede originar IGTF. Los recibos derivados de la nota liquidan su impuesto sin duplicar caja.

Referencias de revisión: [SNAT/2023/00005: calificación SPE](https://tributos.ivecofi.net/informacion/legislacion/providencias/pa-2023-5), [SNAT/2025/000054: retenciones IVA](https://tributos.ivecofi.net/informacion/legislacion/providencias/pa-2025-54), [Gaceta Oficial 43.171 de 16/07/2025](https://www.marambio-hlb.com/wp-content/uploads/2025/07/PROVIDENCIA-000054-Ret.-IVA.-G.O.-43.171-16-07-2025.pdf) y [SNAT/2022/000013: percepción IGTF](https://tributos.ivecofi.net/informacion/legislacion/providencias/pa-2022-13). La configuración conserva las referencias y evidencia de cada caso; estas reglas no sustituyen la revisión del comprobante y su operación concreta.

## Persistencia, seguridad y compatibilidad

`SpecialTaxpayerPolicy` valida y evalúa los hechos. `FiscalProfileService` guarda versiones inmutables con actor/fecha y resuelve la vigente por fecha; un cambio posterior no reescribe snapshots. Los endpoints autenticados permiten consulta/propuesta y restringen escritura a administradores. Los formularios genéricos de empresa/persona rechazan una escritura fiscal directa para evitar eludir ese control.

El esquema inicial añade `fiscal_profile` en empresas/personas, `fiscal_context` en pagos/retenciones y la tabla `fiscal_profile_versions`, con índices y referencias restrictivas a empresa/persona/actor. Son 340 tablas y 341 archivos de migración tenant. Se verificó creación, datos iniciales, claves foráneas, rollback y repetición en bases temporales. No se añadió una conversión automática de instalaciones existentes. Donde falta la tabla de versiones, la consulta devuelve un perfil vacío y la escritura fiscal queda deshabilitada.

Pagos y retenciones se revalidan bajo bloqueo empresa/documento. Las claves de operación mantienen idempotencia; repetir un cobro conserva su contexto original aunque cambie el perfil. La reversión de un cobro con nota fiscal preparada o confirmada se bloquea en su totalidad. El soporte de retención pertenece al almacenamiento privado del tenant y a la factura; no se aceptan rutas ajenas ni archivos inexistentes.

La nota IGTF mantiene Pro9 `08` → HKA `03`, transacción `98`, referencia/control de la factura, fecha y tasa del cobro, perfiles congelados y modalidad/ambiente de la factura. No contiene líneas, IVA ni movimientos de inventario. El envío ocurre fuera de la transacción comercial. Si falta confirmar la factura, la nota queda sin solicitar y se retoma al confirmar su padre. Se conservan las protecciones de UUID, respuestas tardías, consulta y recuperación; no se habilita por extensión toda nota de débito/crédito.

Exportación ND conserva su identificación externa y no hereda el RIF doméstico. Terceros, regímenes y exportaciones mantienen sus restricciones específicas para notas y cobros posteriores. Producción y cancelación remota continúan fuera del alcance.

## Adaptación y presentación HKA

El contrato publicado no aporta un campo SPE dedicado. La condición se envía mediante `infoAdicional`, con `campo` «Condición fiscal Emisor/Comprador» y `valor` «Sujeto Pasivo Especial», sólo cuando el snapshot acredita vigencia. No se reutiliza `regimenEspTributacion`, `tipoProveedor` ni se inventan banderas remotas. El comprador utiliza su RIF fiscal completo; su cédula comercial se conserva aparte. La identidad del emisor sigue vinculada a la autenticación HKA y al snapshot local.

La prueba real encontró que la factura doméstica USD necesitaba principales/líneas VES y `totalesOtraMoneda` USD. Se amplió el adaptador monetario ya usado por terceros/regímenes, incluyendo IGTF doméstico y notas 98, sin cambiar dinero local ni recalcular payloads preparados. Se verificaron regresiones de terceros, regímenes y exportación.

**Limitaciones observadas del proveedor:** el JSON descargado conserva `InfoAdicional` SPE, pero los originales A4/A5 no imprimen esa leyenda. El PDF también duplica el prefijo de identidad cuando recibe tipo y RIF completo. Los originales se preservaron intactos. Pro9 añade el RIF y la condición congelada a su representación local/ticket; esto no demuestra una corrección del PDF remoto.

## Pruebas y evidencia

| Capa | Resultado |
|---|---|
| PHP Unit completa | 712 tests, 15.555 assertions, 19 omitidos; únicamente dos fallos existentes de `QuotationStorefrontCorrelativeTest` (`COT-12` frente a `COT-00000012`) |
| JavaScript, después de corregir Crédito POS/Garage y reapertura de Pagos | 141 tests aprobados |
| Políticas, payloads HKA y PDF, con regresiones de terceros/regímenes/exportación | 124 tests / 1.504 assertions aprobadas en la ejecución final dirigida |
| Representación PDF dirigida | 28 tests / 754 assertions aprobadas |
| MySQL temporal | Los diez casos de `FiscalEmissionSchemaTest` cubiertos entre ejecución completa y repeticiones dirigidas; los fallos atribuibles corregidos y repetidos |
| Matriz comercial simulada | 144 combinaciones: 108 de compradores y 36 de emisores comerciales |
| PHP lint | 43 archivos nuevos/modificados comprobados |
| Diff | `git diff --check` conforme |

La matriz MySQL comprueba persistencia común, contado/crédito, VES/USD, IVA/IGTF, saldo, congelación, rollback, administración y fallos de transporte. El caso de cobros comprueba IVA 75/100, adjunto privado, duplicados, RIF/control/base incorrectos, límites, intermediación, VES, excepciones, revocación, idempotencia, nota exclusiva, descarga y recuperación diferida. Sus respuestas HTTP son simuladas y no se presentan como aceptación real.

En navegador se verificaron el editor de perfil y su historial, Web con comprador SPE y moneda recibida distinta, y el cambio a crédito. En POS se detectó y corrigió un manejador ausente del selector de Crédito. En POS/Garage se comprobó también que reabrir Pagos no propone el total en una venta sin cobro, que el importe visible se limpia y se recupera desde los cobros reales tras recargar y que los abonos reales se conservan sin redistribuirlos al total. Se añadieron pruebas de regresión y se verificaron los assets del watcher en el navegador. La compilación fue realizada por el watcher del usuario; no se ejecutaron comandos de build ni se editaron bundles desde el agente.

El caso MySQL final de cobros pasó 54 assertions, incluida la conservación de modalidad/ambiente de la factura al cambiar la configuración actual, con envío diferido y recuperación posterior.

Archivo privado: `/home/benahia/.codex/private-evidence/spe-hka-2026-10-12/`. La subcarpeta `aligned/` contiene la matriz con identidad del emisor reconciliada; la raíz conserva la matriz inicial como evidencia parcial.

La evidencia privada contiene `matrix.json`, respuestas DEMO sin autenticación, JSON enviados/descargados, A4/A5 originales, tickets, hashes, conciliaciones y capturas de UI. No se incluyen RIF reales, credenciales, JWT ni originales privados en fixtures o en este informe. Los compradores de QA son ficticios; sus notificaciones son referencias sintéticas y no certificaciones del SENIAT.

## Pendientes externos y entrega

Se requiere una cuenta/empresa DEMO habilitada por HKA como emisor SPE PN, firma personal o PJ para acreditar emisión real con IGTF inicial y notas de cobros posteriores. La cuenta inspeccionada es jurídica FORMAL y no permite probar honestamente ese escenario. Se requiere además confirmar el contrato para crédito sin cobros y la impresión de la leyenda/RIF en PDF. Se preparó una solicitud revisable en `solicitud_hka_emisores_spe_demo.md`; no se envió correo ni mensaje al proveedor.

La instalación QA, sus registros de cliente/hostname/website, sus archivos y dos bases de tests huérfanas se retiraron después de conservar la evidencia. Se verificó que no quedan bases ni hostname/website de QA. El retiro local no elimina los registros ya emitidos en el portal DEMO HKA. Las pruebas pendientes externas se mantienen como pendientes, sin controles ficticios ni reclasificación de la empresa remota.
