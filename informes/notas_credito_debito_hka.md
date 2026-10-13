# Notas de crédito y débito HKA en Pro9

Fecha: 13/10/2026. Alcance: instalaciones nuevas; ventas internas VES/USD, propias, por cuenta de terceros y con los siete regímenes especiales. Exportaciones y producción quedan fuera. No se convirtió ni reconstruyó ningún tenant existente.

## Resultado y límites fiscales

Se reutiliza **Comprobantes → Acciones → Nota** para guardar los siete motivos ordinarios, aplicar sus efectos comerciales y coordinar emisión, distribución, ajustes y devoluciones. Las operaciones fiscales sólo se ejecutan fuera del commit exterior. Un rechazo o timeout del proveedor conserva los efectos comerciales guardados; un fallo comercial revierte todo y no transmite.

La corrección de emisión/anulación del 13/10/2026 supera el rechazo inicial1015: HKA acepta `formasPago`99 con descripción explícita de crédito comercial para NC y saldos pendientes de ND. Estas declaraciones fiscales no crean cobros ni reembolsos. La matriz consolidada cubre104 combinaciones previstas, más ND cobradas y seis abonos entre monedas. Las36 NC rechazadas previamente describen la implementación anterior, sin esas declaraciones.

`config/hka_notes.php` habilita `demo_non_cash_notes_verified` y `demo_annulment_verified` únicamente en DEMO, según la evidencia consolidada. Las ND al contado requieren cobros reales completos; las NC conservan la devolución de dinero como operación separada después de confirmar. Producción, exportación, anulación de facturas y circuito especializado IGTF siguen fuera.

Ver [aceptación consolidada de emisión y anulación](evidencias/notas_hka_emision_anulacion_2026_10_13.json). Los resultados históricos permanecen como trazabilidad, sin atribuir aceptación a las pruebas antiguas.

## Referencias

- Guía local: `/home/benahia/benahia/pro/implementaciones con HKA/manuales/[IT-21-01-01][V02]REFERENCIA TECNICA API-26.pdf`.
- [Swagger oficial DEMO](https://demoemisionv2.thefactoryhka.com.ve/swagger/v1/swagger.json), contrastado con la copia local. SHA256: `f2ddb07c239b04319df1fc9b1e6b4db137f89b51f063fa4989fd1bc836d10f04`.
- [ND básicas aceptadas](evidencias/notas_hka_demo_2026_10_13_094920.json): seis casos, tres motivos por moneda.
- [ND ampliadas aceptadas](evidencias/notas_hka_demo_2026_10_13_095749.json):34 casos, confirmación/consulta/control real y tamaños/hash de PDF.
- [NC ampliadas rechazadas](evidencias/notas_hka_demo_2026_10_13_105820.json):36 casos con el diagnóstico1015 exacto.
- [Exploración previa](evidencias/notas_hka_demo_2026_10_13_094056.json): rechazos NC/ND sin cobros; seis variantes cobradas detectaron una restricción comercial local, corregida antes de las matrices aceptadas. No cuentan como aceptaciones.

La conexión del tenant configurado se leyó exclusivamente para obtener credenciales DEMO. Todos los registros comerciales de los probes se guardaron en bases MySQL temporales propias, eliminadas al finalizar; no se alteró la base del tenant origen. Se usó una clave de cifrado aleatoria por proceso en el probe final. Los documentos sintéticos remotos permanecen en DEMO. `auto_send_pdf_email=false` y `comprador.notificar=No`; no se enviaron correos a clientes. Las evidencias no contienen credenciales, JWT, payloads completos ni datos privados del tenant.

## Formulario y contrato comercial

| Tipo interno | Motivo local | Efecto | Transacción HKA | Tipo HKA |
|---|---|---|---|---|
|07|01 anulación|Importe comercial restante y cantidades pendientes|03|02|
|07|07 devolución parcial|Cantidades seleccionadas de partidas originales|04|02|
|07|04 descuentos/rebajas|Disminución monetaria por partida|04|02|
|07|09 precios/cálculos|Sólo la diferencia que disminuye|04|02|
|08|02 incremento|Sólo incremento por partida original|02|03|
|08|01 intereses|Cargo SERV con IVA autorizado|02|03|
|08|03 despacho/fletes/seguros/embalaje|Cargo SERV con IVA autorizado|02|03|

Los mapeos NC/ND fueron aceptados en DEMO con las formas de pago correspondientes. Conservar sus transacciones y diagnósticos; no cambiar códigos para superar un rechazo.

El selector de motivos corresponde al tipo actual y excluye IGTF manual. Se muestran factura/control, notas anteriores, importes acreditables y cantidades restantes. Las series se filtran por tipo/sucursal y validación/permisos comunes, admitiendo código vacío y códigos válidos sin prefijoF. La devolución parcial permite elegir seriales originales pendientes y cantidades por lote; sin selección de lotes, el servidor usa los originales pendientes en orden.

`FiscalNote` recibe UUID de creación, descripción y vínculos de partida original. Canoniza tipo, cliente/sucursal/moneda y metadatos privados; el navegador no decide snapshots, importes, IVA o clasificación. El servidor copia los originales y distribuye bases, IVA, descuentos y cargos globales mediante redondeo acumulado, de modo que no se pierdan ni dupliquen centavos. Las NC sucesivas descuentan importes y cantidades de las notas guardadas, también cuando estén pendientes fiscales.

NC USD hereda tasa/fuente/fecha originales; ND conserva su propia tasa de emisión de ocho decimales. La referencia afectada conserva moneda/tasa/importes originales. Tercero, régimen y sus catálogos se heredan desde snapshots; la transacción propia03/04/02 reemplaza99. Una factura con notas y sus notas guardadas conservan sus partidas originales y no admiten eliminación/edición comercial directa.

## Inventario, saldo y efectivo

`FiscalNoteInventory` captura composición de pack y presentación al persistir factura y conserva almacén exacto de la sucursal. Sólo NC01/07 reingresan stock. Se valida pertenencia, cantidades pendientes, lotes y seriales, incluidos cambios posteriores del producto/pack. Las notas monetarias y ND no mueven inventario. Un serial ya devuelto o un fallo de almacén revierte la transacción antes de llamar al proveedor.

Saldo común: factura menos NC, cobros activos, retenciones y fondo ajustado, más devoluciones principales activas. Cada ND tiene saldo/cobros propios, sin sumarse de nuevo a la factura. La NC tiene saldo cobrable cero. `Document::balance` y `FiscalNoteBalance` alimentan pagos, listado, cuentas por cobrar, morosidad, estado de cuenta y dashboard.

`NoteRefund` registra una salida independiente, con fecha, cobro real de origen, moneda VES/USD, importe realmente devuelto, tasa explícita de ocho decimales, medio y caja/banco. Requiere NC digital DEMO confirmada y control coincidente con su emisión. El límite respeta factura, NC, cobro y devoluciones previas; excluye conceptos no recibidos en efectivo y reserva las participaciones de retenciones/fondos mientras se corrigen. El IGTF requiere ajuste fiscal confirmado independiente. No se borran ni modifican cobros originales.

Registro/reversión llevan UUID, actor, hash y motivo; repetir la misma operación conserva un efecto, reutilizar clave con datos incompatibles produce409. Se valida permiso `create_payment` o `delete_payment`, conexión tenant, sucursal, cuenta por moneda/sucursal y caja abierta del usuario. El historial de devolución se conserva tras reversión. Finanzas, banco/caja, cierres y reportes contabilizan la salida real y excluyen registros revertidos; no convierten el valor de una NC en un egreso automático.

## Ajustes relacionados

`NoteAdjustment` muestra origen, importe, soporte/diagnóstico y estado separados de la nota. IVA/ISLR solicitan reversión con los servicios/outboxes existentes; mantienen la aplicación mientras la ausencia remota sea incierta. Sólo después de acreditarla admiten el comprobante corregido real aportado por el cliente, con referencia/soporte/UUID. La validación usa IVA/neto restante y la fórmula declarada, incluido sustraendo ISLR. `withNoteReplacement` abre un ámbito interno bajo bloqueo para coexistir con exceso real pendiente de reembolso; no fabrica pagos ni debilita los límites fiscales generales.

Fondos registran reducción parcial/total auditada, preservando el importe original y sin salida de caja. Ajustes IGTF conservan impuesto, base, tasa, recibo y referencia originales; reservan el importe para impedir duplicación. Comprenden IGTF inicial y cargos posteriores mediante ND relacionadas. Sin contrato y aceptación fiscal verificables permanecen bloqueados y conservan su diagnóstico; no anulan la nota comercial.

## Interfaces y esquema inicial

Se reutiliza `POST /documents`, con `note_creation_key` y `items[].affected_document_item_id`. La respuesta y las consultas publican DTOs saneados, sin hash, contexto de conexión ni payload privado. El formulario recibe únicamente ID, tipo y sucursal del usuario; no serializa contraseñas ni el modelo completo.

- `GET documents/{note}/refunds`: límites, fuentes, historial y ajustes.
- `POST documents/{note}/refunds`: registro idempotente de salida.
- `POST documents/{note}/refunds/{refund}/reverse`: reversión auditada.
- `POST documents/{note}/adjustments`: solicitud independiente.
- `POST documents/{note}/adjustments/{adjustment}/replacement`: comprobante corregido real.

Se mantienen los endpoints existentes de emisión/consulta/correo/PDF y de retenciones. UUID de formulario se conserva tras timeout/doble clic; al cambiar de nota se descartan respuestas tardías y claves de operaciones anteriores.

Consolidado:345 tablas de aplicación/346 archivos tenant.000318 añade vínculo restrictivo de partida e `inventory_quantity`;000323 añade UUID/hash/snapshot y unicidad por documento;000340/000341 crean devoluciones/ajustes. FKs e índices únicos se agregan en000999 y se retiran inversamente. Cantidades DECIMAL(12,4), importes(12,2), tasas(18,8). Bloqueo empresa antes de cualquier lectura consistente MySQL, después factura→nota→origen; evita que una instantánea anterior omita un UUID concurrente.

## Distribución y presentación fiscal

Correo y PDF requieren confirmación/control coherente, identidad congelada y autorización. Fallos no modifican nota, inventario ni saldo. A4 conserva bytes originales; A5 usa conversión vectorial horizontal existente. Ticket mantiene QR desde la URL fiscal verificada. En las muestras reales de ND se revisaron visualmente A4/A5: referencia, IVA y montos corresponden; el original HKA presenta un pequeño solapamiento de encabezado/primera fila y recorte de texto de tasa en el pie. No se retocó el original para ocultarlo.

No se ejecutó build ni se editaron bundles: [frontend-build](../.codex/skills/frontend-build/SKILL.md) establece «No ejecutar comandos de build por iniciativa propia: la compilación la ejecuta el usuario». Los cambios observados en `public/build` provienen del proceso externo del usuario. La comprobación visual de esta ampliación utilizó sus assets actualizados en BBC; véase la verificación final.

## Verificación de la primera implementación (histórica)

[Resumen verificable](evidencias/notas_hka_verificacion_2026_10_13.json), sin datos privados ni salidas crudas:

| Verificación | Resultado |
|---|---|
|Regresión Unit, excluyendo la clase de cotización preexistente|739 pruebas,15655 aserciones;40 omitidas por capacidades/entorno opt-in, sin fallos|
|Regresión final de HKA, autorización, edición, PDFs, notas, cálculos y esquema|145 pruebas,2612 aserciones;5 omitidas, sin fallos|
|MySQL temporal:15 casos únicos, incluidos9 de notas|3204 aserciones, todos aprobados|
|JavaScript completo|158 pruebas, todas aprobadas|
|Lint de PHP modificado/nuevo|73 archivos, sin errores|
|`git diff --check` de fuentes|Sin errores|
|DEMO ND cobradas|34 confirmaciones/consultas/A4/A5 reales|
|DEMO NC|36 rechazos203/1015 preservados|

MySQL cubre los siete motivos, VES/USD, catálogos de régimen/tercero, series vacías de dos sucursales, créditos sucesivos, devolución de stock y límites, pack mutable, presentaciones, lotes/seriales, rollback sin HTTP, cobros propios de ND, fondos/IGTF inicial y posterior, correcciones IVA/ISLR concurrentes y reversión incierta. Dos procesos verifican un solo efecto de creación/reembolso, tasa10.12345678, monedas distintas y límite de dinero concurrente. Los casos de devolución comprueban saldo SQL/modelo, rechazo de exceso, reversión idempotente, caja/banco, DTO privado y denegación desde otra sucursal. La regresión de emisión cubre commit exterior, timeout, respuestas tardías y ausencia de duplicación; los tests JavaScript cubren doble clic, respuesta perdida y cambio de nota.

Todas las bases propias temporales fueron eliminadas, incluido un destino identificado de una ejecución interrumpida por memoria; se comprobó que no quedaban esquemas `pro9_fiscal_test_*`. Los dobles cubren aceptación/rechazo/timeout y efectos; la evidencia DEMO anterior es la que acredita confirmación fiscal real. Las27 pruebas/161 aserciones de exploración inicial no acreditaban emisión de notas.

Dos fallos preexistentes de `QuotationStorefrontCorrelativeTest` esperan COT-12 mientras el modelo devuelve COT-00000012. Sus fuentes y pruebas no se modificaron por esta implementación. Se conserva el diagnóstico de la ejecución completa y se ejecuta la regresión restante excluyendo esa clase. También permanece el aviso de parámetro opcional anterior al obligatorio en `Item.php:2865`, ajeno a esta entrega.

## Corrección de envío y anulación — 13/10/2026

### Formas de pago y presentación

`HkaNotePayments` construye declaraciones99 desde el importe de la NC o el saldo pendiente de una ND a crédito. Conserva los cobros reales y excluye sus recibos IGTF derivados para no duplicar dinero. Si la conversión de un abono y su crédito deja un centavo de diferencia, expresa solamente el crédito fiscal en la moneda principal HKA por el complemento exacto; conserva recibos, importes locales y tasa original de ocho decimales. La matriz real comprobó los tres motivos ND con abonos VES/USD y monedas recibidas distintas.

El formulario ND al contado incorpora cobros explícitos con UUID, fecha, medio, destino y campos de moneda/IGTF compartidos con facturas. El servidor exige permisos de pagos, destino de la sucursal/moneda y principal completo. Un fallo revierte la creación antes de llamar a HKA. Los medios `is_credit` no crean cobros reales.

`fiscal_emission.show_send` conserva visible Enviar HKA aunque un impedimento lo deshabilite; `can_send` mantiene los permisos y las validaciones. Opciones presenta Factura/NC/ND con sus nombres y resultado fiscal. Los diagnósticos antiguos de capacidad resuelta dejan de ocultar la disponibilidad actual, sin reescribir registros.

BBC conserva sus cuatro comprobantes. La NC1000, sin cobros propios, puede solicitar emisión. La ND100 está registrada como Contado y no cobrada: muestra Enviar HKA deshabilitado hasta registrar sus cobros reales desde Pagos. No se cambió su condición ni se enviaron/anularon estas notas durante la comprobación.

### Anulación

`NoteAnnulment` usa el JSON existente de emisión, sin migraciones: solicitud congelada, UUID/hash, actor, intentos, historial, estado y aplicación comercial. Conserva HTTP, código HKA y códigos numéricos de validación; no guarda textos privados crudos. El DTO publica sólo campos saneados. Administradores acceden desde Acciones → Anular u Opciones; motivo y confirmación son obligatorios. Los POST son `documents/{note}/annul`, `query-annulment-hka` y `complete-annulment`.

Una nota sin transmisión acreditada se anula localmente. Una confirmada utiliza Anular con tipos HKA02/03 e identidad congelada. Cobros reales de ND, reembolsos de NC y ajustes relacionados activos bloquean la solicitud: no se revierten automáticamente. La prevalidación comprueba existencias, almacén, composición, lotes y seriales originales; un serial vendido nuevamente impide transmitir.

La solicitud deja el estado comercial13, manteniendo créditos y cantidades reservados. Los estados internos de anulación son pending/uncertain/rejected/remote_confirmed/completed. Sólo tras confirmación fiscal se revierte el inventario de NC físicas, se libera el crédito y se marca11. ND monetarias no mueven stock. El saldo ND anulada es cero tanto en modelo como en SQL. Consultas y respuestas tardías no aplican nuevamente estos efectos.

Ante timeout se conserva la solicitud y se concilia, sin reenviar automáticamente. DEMO devuelve201 vacío en ListadoDocumentos incluso tras anular; esto no prueba ausencia. EstadoDocumento devuelve200 con `estadoDocumento=Anulada`; su UUID, tipo, número y control permiten confirmar. La consulta paginada conserva cotejo de RIF/serie/control y utiliza esta comprobación adicional cuando no acredita el resultado.

Si HKA confirma y el stock ya no permite revertir, la emisión queda cancelled, pero el documento comercial permanece13 con `remote_confirmed`. Completar reversión comercial reintenta sólo la operación local, sin otro Anular. Empresa → factura → nota → emisión se bloquean en transacciones cortas; autenticación, mutaciones/consultas HKA y notificación de restaurante ocurren fuera. Las operaciones relacionadas incompatibles quedan bloqueadas durante la conciliación/reversión.

Las rutas antiguas de anulación y borrado no permiten omitir el flujo auditado. Se preservan controles, numeración, payloads, PDF originales, pagos y antecedentes; no se reutilizan números ni se alteran facturas confirmadas.

### Evidencia y límites

Las matrices se ejecutaron en bases propias temporales, con credenciales DEMO de BBC y datos sintéticos, sin correo. Dos ejecuciones grandes terminaron después de42 casos; se conservaron los resultados, se eliminaron sólo sus bases temporales y se completó la cobertura en lotes de hasta20. Cada ejecución usa nombre de evidencia único. Una consulta remota transitoria falló; la comprobación posterior acreditó Anulada con la misma identidad.

Se revisaron A4 original y A5 de una NC USD: identificación, referencia, IVA, totales, tasa y descripción Crédito comercial corresponden al payload. El solapamiento de la primera fila ya está en el original HKA; no se modificó para ocultarlo. Las facturas sintéticas afectadas por NC de anulación total conservan estado Enviada después de anular la NC.

Las habilidades e informes reflejan el contrato vigente. No se ejecutó build ni se editaron assets generados; los cambios de public/build corresponden al proceso externo del usuario.

### Verificación final de esta ampliación

Ver [resumen de verificación](evidencias/notas_hka_correccion_verificacion_2026_10_13.json) y [matriz fiscal consolidada](evidencias/notas_hka_emision_anulacion_2026_10_13.json).

| Verificación | Resultado |
|---|---|
|Matriz real DEMO consolidada|120 casos distintos;104 combinaciones principales completas,10 ND cobradas adicionales y6 abonos entre monedas.128 operaciones registradas, todas emitidas, anuladas y consultadas como Anulada; A4/A5 comprobados|
|Regresión PHP, suite Unit incluyendo el parser de catálogo de productos|751 pruebas,15679 aserciones,46 omitidas por capacidades/entorno opt-in; sin fallos. Excluye la clase de cotización preexistente descrita arriba|
|JavaScript completo|164 pruebas, todas aprobadas|
|MySQL temporal|Emisión después del commit, rollback sin HTTP, crédito/saldo ND, stock original y serial vendido de nuevo, dependencias, idempotencia, concurrencia, timeout/conciliación, rechazo y reversión local posterior a confirmación fiscal|
|Navegador BBC con assets actualizados|Opciones NC/ND, diagnóstico y Enviar HKA visible; habilitado NC1000 y bloqueado ND100 Contado sin cobros. Anular aparece en ambas notas. Formulario ND Contado muestra cobros explícitos VES/USD con tasa de ocho decimales y excluye medios comerciales de crédito|
|Lint PHP / revisión de diferencias|32 archivos PHP sin errores y `git diff --check` aprobado; cinco habilidades validadas|

La comprobación del formulario se hizo en una pestaña separada y se cerró sin guardar. No se pulsaron Enviar HKA, Anular ni correo en las notas existentes de BBC. Las capturas locales son `/tmp/pro9-bbc-nc-hka.jpg`, `/tmp/pro9-bbc-nd-hka.jpg` y `/tmp/pro9-bbc-nd-cash-form.jpg`. La consulta final de `information_schema` confirmó cero bases `pro9_fiscal_test_*` pendientes.
