# Notas de crédito y débito HKA en Pro9

Fecha: 13/10/2026. Alcance: instalaciones nuevas; ventas internas VES/USD, propias, por cuenta de terceros y con los siete regímenes especiales. Exportaciones y producción quedan fuera. No se convirtió ni reconstruyó ningún tenant existente.

## Resultado y límites fiscales

Se reutiliza **Comprobantes → Acciones → Nota** para guardar los siete motivos ordinarios, aplicar sus efectos comerciales y coordinar emisión, distribución, ajustes y devoluciones. Las operaciones fiscales sólo se ejecutan fuera del commit exterior. Un rechazo o timeout del proveedor conserva los efectos comerciales guardados; un fallo comercial revierte todo y no transmite.

La aceptación real DEMO difiere de lo que permite declarar Swagger: **34 ND completamente cobradas fueron confirmadas**, consultadas y descargadas como originales A4 y conversiones A5. La matriz abarca los tres motivos ordinarios en VES/USD y el incremento de precios para cada régimen, con y sin tercero. **36 NC fueron rechazadas con203/1015**, incluidos los cuatro motivos en VES/USD y ajustes de precio con cada combinación régimen/tercero. La respuesta exige al menos una forma de pago, sin ofrecer en el contrato verificado una forma legítima para esta NC sin cobro. Las ND sin cobros también recibieron ese rechazo.

`config/hka_notes.php` mantiene `demo_credit_without_payment_verified=false`. `HkaNoteCapability` publica diagnóstico y bloquea preparar/enviar NC y su devolución de dinero hasta acreditar un contrato válido sin pagos ficticios. La ND pendiente espera cobros reales completos y luego la acción Enviar HKA. Los probes/dobles usan el ámbito privado `hka_notes.contract_probe`; no habilita capacidades de negocio ni acredita homologación.

La disminución IGTF se conserva como ajuste fiscal independiente bloqueado, ligado a impuesto y cobro originales. No se usa98 como NC:98 corresponde al cargo especializado ND. El cobro posterior de una ND ordinaria conserva su cargo98 y referencia a la factura; combinaciones con tercero/régimen conservan un pendiente específico hasta contrato y aceptación verificables. No hay evidencia SPE apta para declarar aceptado ese circuito. Estos pendientes bloquean el reembolso tributario dependiente.

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

Los mapeos NC permanecen **previstos, sin aceptación real acreditada** por el bloqueo1015. No se cambian códigos para superar un rechazo.

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

No se ejecutó build ni se editaron bundles: [frontend-build](../.codex/skills/frontend-build/SKILL.md) establece «No ejecutar comandos de build por iniciativa propia: la compilación la ejecuta el usuario». Los cambios observados en `public/build` provienen del proceso externo del usuario. La aceptación visual del formulario en una instalación nueva con sus assets actualizados queda pendiente; compilación de plantillas y dobles no acreditan ese recorrido de navegador.

## Verificación

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
