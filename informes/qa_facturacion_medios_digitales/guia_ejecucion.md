# Guía de ejecución QA de Pro9: facturación en Medios digitales

**Edición:** 11 de octubre de 2026. **Zona de registro:** America/Caracas (UTC−04:00).
**Modalidad requerida por el usuario:** Medios digitales (`digital`).
**Ambiente de referencia:** DEMO (`demo`), por el alcance actual del transporte HKA. DEMO es una elección de esta guía, no una confirmación del ambiente de un tenant existente.
**Estado de este documento:** especificación de pruebas. No es un informe de pruebas ejecutadas.

## 1. Mandato para el agente ejecutor

Ejecuta esta guía y el documento de casos completos sobre la versión identificada de Pro9. Tu trabajo comprende el registro comercial, su resultado fiscal, cobros, stock, distribución, reportes y permisos. Debes probar cada tipo vigente, cada opción disponible y las restricciones de los flujos no habilitados. No sustituyas una matriz completa por una muestra sin documentar expresamente la cobertura que falta.

Cada prueba que ejecutes debe terminar con **Aprobó** o **Reprobó**, con resultado observado y evidencia. Si no puedes ejecutarla, usa **Bloqueada** con el impedimento concreto. **No aplica** sólo procede por exclusión de alcance sustentada, no por falta de tiempo, credenciales, permisos, módulo habilitado o conocimiento de la herramienta. Las pruebas aún no iniciadas permanecen **Pendiente**. Nunca conviertas una lectura de código, una evidencia histórica o un fixture en una prueba funcional aprobada.

Al finalizar entrega un informe autónomo, bien estructurado y detallado usando la plantilla suministrada. Incluye cada caso ejecutado, sus resultados, defectos, evidencias y límites. El usuario debe poder conocer qué pasó y qué falta sin leer los mensajes del chat ni ejecutar herramientas adicionales.

Las pruebas de Medios digitales se ejecutan con empresa `digital`, documentos con modalidad digital y ambiente DEMO. Máquina fiscal y Forma libre están excluidas de las pruebas positivas. No cambies un tenant operativo a otra modalidad para ejecutar esta guía. El caso negativo de producción usa un fixture separado y proveedor simulado, sin emisión real.

## 2. Archivos y orden de lectura

1. Lee esta guía para conocer el alcance, reglas, datos y oráculos.
2. Lee [casos_prueba.md](casos_prueba.md). Cada ID tiene precondiciones, pasos, resultado esperado, verificación posterior y campos de ejecución.
3. Abre [el registro editable de resultados](../../outputs/qa_facturacion_medios_digitales/registro_resultados.xlsx). Conserva una copia por corrida. Sus filas iniciales son todas Pendiente.
4. Usa [inventario_opciones.md](inventario_opciones.md) para demostrar la cobertura de las opciones efectivas del despliegue.
5. Usa [plantilla_informe_final.md](plantilla_informe_final.md) como informe final obligatorio.
6. Consulta [fuentes_y_trazabilidad.md](fuentes_y_trazabilidad.md) cuando necesites resolver un contrato, identificar el archivo que lo implementa o detectar una especificación antigua.

Los documentos Markdown son editables y versionables. El libro de resultados sirve para filtrar y registrar la corrida; las especificaciones completas están en el documento de casos. No borres filas para mejorar porcentajes ni reutilices una fila para otra combinación de parámetros.

El libro contiene **Resumen**, **Resultados**, **Especificaciones** y **Variantes**. Resultados tiene una fila por caso base o QA-EXT adicional; Variantes tiene una fila por subcaso con su ID padre. Sus conteos se presentan por separado. No agregues las variantes como nuevas filas de casos padre. Para un retest, conserva una copia de la corrida anterior o una bitácora y actualiza la fila vigente del mismo ID con la nueva iteración; no dupliques fallos históricos dentro de los conteos activos.

El resumen considera hasta 5000 filas por tabla de ejecución. Al añadir casos, duplica una fila con sus controles, amplía la tabla y conserva el ID; al superar 5000 amplía los rangos del resumen. Si añades un grupo nuevo, añade también su fila al resumen por grupo. Verifica conteos después de cada ampliación. Las variantes heredan la especificación y fuentes del caso padre, pero conservan su propio resultado, evidencia y fecha.

## 3. Tipos de documentos y capacidad real

| Documento o categoría | Identidad interna vigente | Alcance en Medios digitales | Qué se debe comprobar |
|---|---|---|---|
| Factura ordinaria interna | `01`, operación `0101` | Registro comercial y emisión HKA DEMO del guardado común web/POS/API; otros escritores deben verificarse | Contado, crédito, cuotas disponibles, bienes/servicios, IVA/exento, pagos, edición, fiscal, PDF y distribución |
| Exportación de bienes FOB | `01`, operación `0201` | Sólo Comprobantes web digital DEMO | ND, EXP, bienes, IVA 0 %, INCOTERM, VES/USD y restricciones |
| Exportación de bienes CIF | `01`, operación `0202` | Sólo Comprobantes web digital DEMO | Mismos controles, ejecución propia de CIF |
| Exportación de bienes EXW | `01`, operación `0203` | Sólo Comprobantes web digital DEMO | Mismos controles, ejecución propia de EXW |
| Venta por cuenta de terceros | `01`, `0101`, transacción fiscal `99` determinada por servidor | Sólo Comprobantes web digital DEMO, contado completamente cobrado | Titular único distinto de emisor/comprador, snapshot, cálculo/adaptación VES/USD y restricciones |
| Nota de crédito | `07` | Guardado comercial y preparación local HKA; emisión remota actual no acreditada ni habilitada por el transporte de facturas | Cuatro motivos activos, ajustes/devoluciones, referencias, moneda, saldo, stock y preparación |
| Nota de débito comercial | `08` | Guardado comercial y preparación local | Tres motivos manuales activos, importes/referencia y efecto comercial |
| Nota de débito IGTF posterior | `08`, motivo `IGTF`, transacción HKA `98` | Generación local asociada al cobro y preparación; sin artículos ni IVA | Principal/impuesto/recibo derivado, caja, idempotencia y reversión |
| Nota de venta | `80`; `nv` únicamente donde el servicio técnico mantiene ese alias | Documento comercial propio, aunque su tenant sea digital | Creación, cobros, PDF, edición/duplicación/anulación según flujo y conversión a Factura |
| Factura de anticipo y deducción | Factura `01` y relaciones comerciales de anticipo | Opciones existentes a validar comercialmente; la aceptación/adaptación HKA debe demostrarse por separado | Registro y saldo del anticipo, aplicación parcial/múltiple, exceso, concurrencia y no duplicación |
| Orden de entrega | `09`, persistencia `dispatch` | Relación/conversión comercial; no presumir emisión HKA de guías | Vínculos, almacén/sucursal de origen, transporte y stock |
| Retenciones IVA/ISLR recibidas | Relaciones recibidas de la Factura, no un nuevo tipo de Factura | Aplicación comercial/fiscal conservada | Comprobantes reales de QA, agente, fórmula, adjunto, saldo e independencia de caja |

**No confundir catálogo con capacidad:** `FE`, `20`, `ISLR`, `ARCV` y otros tipos pueden existir en catálogos/series. Su presencia no habilita emisión en el procesamiento común de Facturas y notas. La exportación implementada usa Factura `01`; no `FE`. La Boleta `03` está retirada y debe rechazarse en cada canal nuevo.

La traducción del adaptador es Pro9 `01 → HKA 01`, Pro9 `07 → HKA 02`, Pro9 `08 → HKA 03`. Los motivos de notas tienen sus propios códigos. HKA `07` no es Nota de crédito Pro9. Conserva los códigos internos en base de datos y no uses coincidencias de códigos de distintos catálogos para deducir comportamiento.

Motivos iniciales de NC: `01 Anulación total de factura`, `07 Devolución parcial de mercancía`, `04 Descuento o rebajas concedidas`, `09 Corrección de precios o cálculos`. Motivos de ND manual: `02 Ajustes por incremento de precios`, `01 Intereses de mora o financiamiento`, `03 Gastos de despacho, fletes, seguros o embalaje`. `IGTF` tiene un flujo derivado del cobro; no se trata como nota manual libre.

### 3.1. Límites que deben quedar visibles en el resultado

- Producción digital no está habilitada en el transporte actual. No pruebes emisión real de producción para intentar ampliar ese alcance.
- NC/ND preparadas no son aceptadas remotamente por esa sola preparación. La integración de facturas no demuestra homologación de notas.
- Exportaciones: sólo bienes y Comprobantes web DEMO, operaciones FOB/CIF/EXW; no servicios exportados, 0200 genérico, notas de exportación, otros canales, terceros o regímenes especiales combinados.
- La evidencia actual de exportación HKA acredita contado. Crédito sin cobros ha sido rechazado por falta de formas de pago. Debe reportarse como límite conocido; no fabricar un cobro para convertirlo en éxito.
- Terceros: sólo venta interna y contado real completo. Crédito, notas vinculadas y demás canales fuera de esta etapa. El IGTF posterior dependiente de una nota se bloquea para exportación/terceros; el inicial conserva su flujo vigente.
- Cobro en moneda propia/mixto está implementado por contrato API; la interfaz actual no contiene todos esos selectores. No describas una opción API como botón de pantalla.
- La emisión HKA y su correo/PDF tienen estados separados. Guardado local, preparación, aceptación fiscal, disponibilidad PDF, solicitud de correo y entrega efectiva son resultados distintos.
- No existe anulación remota HKA integrada ni distribución por orden consolidada. Anulación local no acredita cancelación del registro remoto.
- No hay XML/CDR/SUNAT/PSE, firma fiscal SOAP/PFX, Boletas, ISC, detracciones ni impuesto a bolsas en el flujo vigente. QZ de impresión y QR comercial no son esos contratos retirados.

Un caso de rechazo esperado **Aprobó** si rechaza correctamente y conserva la integridad. Ejemplo: “exportación por API bloqueada” aprueba cuando el servidor la rechaza antes de efectos. La **Factura rechazada por HKA** es el estado del documento, no automáticamente el estado del caso de prueba.

## 4. Preparación del entorno y datos

### 4.1. Ficha de corrida obligatoria

Registra: ID `QA-DIG-AAAA-MM-DD-NN`, ejecutor, fecha/hora Caracas, URL QA, commit y rama, cambios locales, versión de PHP/Laravel/navegador, resolución, hash del manifest/asset servido, modalidad/ambiente, conexión tenant/central y estado del proveedor. No incluyas secretos. Identifica si usas **doble**, **HTTP integral con doble**, **navegador con doble** o **Real DEMO**.

Identifica QA-A y QA-B. Ambos deben ser entornos de prueba separados. Usa S1/S2 con almacenes propios; U-ADMIN, U-VENTA-S1, U-VENTA-S2, U-SINPERMISO y U-INTEGRADOR según capacidades actuales. Para aislamiento, crea IDs locales coincidentes con datos diferentes. Evita pruebas que muten conexiones o documentos operativos ajenos a la corrida.

La caja debe estar abierta para la venta que la exija. Registra saldos iniciales por moneda/destino. Crea una cuenta bancaria QA. Verifica permisos de crear/revertir cobros, editar, anular, administrar series, importar y configurar fiscal. Las rutas y valores se obtienen del despliegue actual; no asumas que un nombre de menú antiguo sigue vigente.

### 4.2. Fixtures reproducibles

| Fixture | Preparación mínima | Control independiente |
|---|---|---|
| C-0/C-1/C-6/C-7/C-E/C-C/C-G/C-R | Un cliente doméstico activo por tipo; sólo dígitos en número; contactos QA | Elegibilidad y presentación de cada identidad |
| C-ND | País extranjero, tipo de identidad externa real de fixture, número literal, dirección extranjera | Exclusivo exportación web soportada, tipo externo HKA |
| C-INACTIVO | Tipo temporalmente inactivo sólo en copia QA | Rechazo de selector/backend/conversión |
| T-1 | Cliente VE, Es tercero, RIF complementario válido y único, actividad, nombre/dirección/contacto QA | Titular separado de comprador y emisor |
| P-G | UND, gravado `10`, precio final116.00, base100.00, IVA16.00, stock100 | Compra/venta de una unidad total116.00 |
| P-E | UND, exento `20`, precio50.00, stock100 | Total50.00 por unidad e IVA0 |
| P-S | SERV, gravado, precio final116.00 | Sin movimiento físico de inventario |
| P-V | Padre variable no vendible y dos hijos con atributos/stock distintos | ID hijo correcto y su stock |
| P-PRES | Presentación caja con equivalencia6UND | Venta2cajas equivale12UND si esa regla rige el fixture |
| P-PACK | Pack con componentes y regla de inventario anotada | No duplicar movimientos |
| P-LOTE | Lots enabled; L1 stock2, L2 stock5 | Venta3 repartida2+1 |
| P-SERIAL | S-A/S-B asignados al artículo | Un serial no vendido dos veces |
| P-ZERO/P-RESTRICT | Sin stock y restringido para venta | Política vigente de cada restricción |
| R1/R2 | `40.12345678` y `50.00000000` VES por USD | Tasas sintéticas, no cotizaciones BCV reales |
| IGTF-QA | Deshabilitado inicialmente; tasa operativa3 % en fixture de prueba | No es afirmación de tasa legal |
| F-ESTADO | Documento por estado fiscal con UUID/control/antigüedad conocidos | Nunca usar control simulado para acreditar Real DEMO |

Los fixtures de precio de la matriz se expresan en la moneda elegida para simplificar el oráculo. Para probar conversión del precio del catálogo usa el caso específico, sin sustituir sus datos por esos precios nominales.

Crear clientes para la matriz no prueba por sí mismo la validación fiscal remota de todas las identidades. La elegibilidad comercial permite los ocho tipos domésticos activos. Si falta equivalencia HKA de uno o el proveedor lo rechaza, registra el resultado fiscal exacto por separado y reporta la discrepancia contra la capacidad prometida; no cambies el cliente a Jurídico para ocultarla.

### 4.3. Assets y compilación

La habilidad [frontend-build](../../.codex/skills/frontend-build/SKILL.md) indica: **“No ejecutar comandos de build por iniciativa propia: la compilación la ejecuta el usuario.”** El agente QA debe verificar el manifest y los assets servidos del watcher/despliegue. No ejecutar `npm run build/dev/watch`, Vite, Yarn ni pnpm build, ni editar `public/build/`, salvo que el usuario haya pedido explícitamente ese comando.

Si fuentes y assets no coinciden, sigue con las pruebas independientes de backend y registra las visuales afectadas como Bloqueadas hasta disponer del paquete correspondiente. Una validación sintáctica de Vue no acredita el clic del paquete servido.

### 4.4. Proveedor, correo, mensajería e impresión

Configurar/autenticar HKA no transmite ni asigna controles. Usa dobles para timeouts, respuestas incompletas, errores, carreras y límites. En Real DEMO usa credenciales DEMO configuradas, productos/datos de QA, comprador/contacto controlado y numeración disponible. No uses credenciales de producción. Cada envío real debe quedar identificado por la corrida.

La guía no autoriza mensajes a destinatarios históricos o personas ajenas a QA. Correo, WhatsApp, pasarelas y webhooks se prueban con receptores QA/sandbox o dobles. Si no hay receptor apropiado o autorización de envío, continúa con simulación y registra el caso real como Bloqueado. No presentes una solicitud aceptada por HKA como correo recibido sin evidencia de entrega atribuible.

Usa impresora QA o doble de órdenes de impresión. Un PDF generado no acredita salida física QZ. No borres aceptaciones remotas mediante limpieza local: la purga de DEMO sólo elimina datos locales y requiere su propio contrato de administrador/confirmación `ELIMINAR`. Prueba esa herramienta al final sobre tenant desechable exclusivo, conservando primero evidencias.

## 5. Oráculos monetarios, fiscales y comerciales

Un oráculo es el valor esperado calculado independientemente. Calcula con `Decimal`, aritmética racional o precisión equivalente, sin copiar el total devuelto por Pro9 como si fuera el esperado. Conserva una hoja/JSON de cálculo por caso monetario. La tasa expresa VES por USD: USD→VES multiplica, VES→USD divide; se redondea el importe final, no la tasa.

### 5.1. Ejemplos base

| Operación | Resultado independiente esperado |
|---|---|
|1UND gravado precio final116 al16 %|Base100; IVA16; total116|
|2UND gravadas precio final116|Base200; IVA32; total232|
|1UND exenta50|Base exenta50; IVA0; total50|
|Gravado116 + exento50|Base gravada100; exenta50; IVA16; total166|
|Descuento línea00 sobre base100 al10 %|Descuento10; base90; IVA14.40; total104.40|
|Descuento10 sin ajuste de base según tipo actual|Base IVA100; IVA16; total106|
|Cargo línea10 % sobre bruto100 según cálculo actual|Cargo10; base IVA100; IVA16; total126|
|Pago USD10 a tasa40.12345678 sobre facturaVES|Principal aplicado401.23VES; recibido10USD|
|Pago401.23VES a misma tasa sobre facturaUSD|Principal aplicado10.00USD; recibido401.23VES|
|Venta116VES−pago30−retención12−fondo10|Saldo64VES; venta116; recepción efectiva30|
|IVA retenido75 % de IVA16|Comprobante12; saldo104 si no hay otras aplicaciones|
|ISLR base100,3 %,sustraendo1|Retención2; no ingreso de caja|
|IGTF QA3 % de principalUSD20 posterior|Impuesto0.60USD; recepción20.60USD una vez; nota08 local sin productos/IVA|

Porcentajes y cantidades del fixture son datos técnicos de prueba. No inferir perfil tributario del cliente ni tasa legal. El sistema por defecto usa IVA16 % y permite la configuración vigente; variantes8/31 sólo se prueban en entornos aislados configurados si corresponde al catálogo soportado.

### 5.2. Significado de los pagos

- `payment`: principal neto aplicado en moneda documental.
- `original_amount`: principal neto recibido en la moneda del pago, sin vuelto ni IGTF.
- `tax_amount`: IGTF recibido en moneda del pago.
- `receipt_parent_id`: recibo derivado que liquida impuesto; no representa otra recepción de efectivo.
- `operation_key`: UUID de la operación de cobro, con idempotencia del pago. No prueba por sí mismo la idempotencia de creación de una factura.
- `received_retentions` y `guarantee_fund`: aplicaciones que reducen saldo sin generar caja/banco ni alterar venta.

Cada comprobación monetaria compara línea, documento, aplicación, caja/banco y reporte por su propia moneda. No sumes VES y USD nominalmente. Los recibos derivados y pagos revertidos no cuentan como nuevos ingresos activos. En vuelto, entrega120 para total116 produce neto116/vuelto4, no venta120.

### 5.3. Tolerancias y comparación

Identificadores, UUID, tipos, series, tenant/sucursal, moneda, número de registros y estados se comparan exactamente. La representación visible del número agrega hasta ocho dígitos, pero el payload/consulta HKA usa los valores originales del snapshot. Serie vacía no se convierte en `Sin serie`, `SIN_SERIE` ni `__without_series__` fiscal.

Importes finales se comparan a la precisión contractual de dos decimales; tasas a ocho decimales como cadena. Documenta redondeo por línea frente a global. Las tolerancias0.005/0.01 que ciertos servicios usan para saldo/fórmula no son una autorización general para descuadres. Una diferencia sólo se acepta si deriva del algoritmo explícito probado y se registra el cálculo.

Los bytes de A4 original HKA deben ser iguales por hash. A5 es transformación legítima: compara número de páginas, tamaño210×148mm, contenido, proporciones de glifos/logo y QR. El ticket80MM se valida con QR decodificado igual a `consulta_url`; no basta ver un cuadrado impreso.

## 6. Máquina de estados y secuencias que debes probar

| Estado emisión | Qué significa | Verificación crítica |
|---|---|---|
|not_requested|Aún sin solicitud fiscal|Guardado local y requisitos de preparación/envío|
|prepared|Payload local congelado|No hay aceptación ni HTTP por prepare; UUID/payload estables|
|pending|Intento reclamado/en curso|Se persiste antes de HTTP, sin doble envío|
|confirmed|Éxito de negocio atribuible con control válido|Control/identidad íntegros, no degradar por consulta fallida|
|rejected|Rechazo definitivo interpretado|Venta local/pagos/stock íntegros, diagnóstico saneado|
|uncertain|Posible transmisión sin resultado conciliado|Consultar por la misma transacción; no reenviar a ciegas|
|cancelled|Operación cancelada según estado conservado|No afirmar anulación remota integrada|

La venta se guarda dentro de la transacción tenant. El envío elegible se inicia después del commit exterior. Si falla comercio, rollback y cero HKA. Si falla HKA después de commit, la venta continúa guardada con el resultado fiscal correspondiente.

Se permite editar Factura vigente sin control en `not_requested/prepared/rejected`, y en `uncertain` sólo con ausencia acreditada (`retry_allowed`). `pending/confirmed/cancelled`, control existente y estados comerciales no vigentes bloquean edición. Edición comercial conserva cobros, identidad, emisor y moneda; inventario se ajusta por diferencia. Invalidar preparación crea un nuevo UUID y exige envío explícito. Consulta/reintento conserva UUID; no genera otro por una incertidumbre.

**Detalle de UUID al editar:** el código actual conserva UUID de una factura ordinaria que ya estaba `not_requested`, porque todavía no hay payload preparado; para terceros renueva también ese UUID. Al invalidar `prepared/rejected/uncertain` editable se renueva. No exigir un cambio indiscriminado de UUID por cualquier edición ni mezclar UUID de pago con UUID fiscal.

Recuperación de `pending` de emisión: comprobar frontera40s del intento según implementación actual. Reenvío: mínimo30s desde intento y ausencia inequívoca acreditada, no sólo timeout/vacío/código203. Correo tiene recuperación propia de50s. Simula reloj; no dejes bloqueada la conversación ni provoques timeouts reales para demostrar esas fronteras.

## 7. Procedimiento detallado por caso

1. **Selecciona el ID y la variante.** Lee objetivo, grupo/fuentes, capa, prioridad, fixtures, pasos y resultado esperado. Si el caso reúne varias entradas, formatos, campos o valores, abre subcasos `ID-V01`, `V02`, etc. Cada subcaso tiene un resultado propio. Nunca marques el padre Aprobó si falta una variante.
2. **Resuelve las precondiciones.** Verifica tenant digital/demo, usuario/permisos, caja, catálogo, serie/grupo, cliente, stock y estado fiscal. Registra IDs concretos. Si falta algo, registra Bloqueada y sigue con casos independientes.
3. **Captura la línea base.** Documento/conteos, numeración, pagos, aplicaciones, stock/lotes/seriales, caja/banco, emisión/UUID/control, archivos y llamadas al proveedor que correspondan. Captura sólo lecturas necesarias, con contexto tenant claro.
4. **Calcula el esperado antes de actuar.** Define base/IVA/impuesto/descuento/cargo/total/saldo y diferencias de stock/caja de la variante. Para seguridad define “cero efectos antes de rechazo”.
5. **Ejecuta los pasos específicos.** Usa la acción real de UI cuando la capa sea navegador. Conserva request/response saneados. Para HTTP integral usa autenticación y middleware reales con proveedor doble; llamar un método PHP no sustituye esa capa.
6. **Verifica resultado inmediato y posterior.** Lee la respuesta de creación, detalle/listado, base en lectura, caja/bancos/Kardex, relaciones/origen, PDF/correo y reportes según caso. No exijas evidencia ajena a una prueba de puro render o lectura, pero sí todos los efectos que el caso puede modificar.
7. **Recarga y reabre.** Confirma persistencia y estado correcto. Probar sólo un toast antes de recargar es insuficiente para declarar persistencia. Reintenta únicamente según política de idempotencia/conciliación del flujo.
8. **Asigna veredicto.** Aprobó si se cumplieron todos los criterios y variantes; Reprobó si hay diferencia real con el esperado y evidencia reproducible. Guarda defecto para el fallo. No cambia un requisito para acomodar lo observado.
9. **Completa el registro.** ID/subcaso, estado, resultado observado, números esperados/obtenidos, documento/UUID/intento, evidencia, defecto/bloqueo, ejecutor, fecha/hora y corrida/iteración. Conserva datos originales al hacer retest.
10. **Preserva evidencia y estado útil.** No borrar pruebas antes de capturarlas. Restaurar cambios de catálogos/configuración sólo en fixtures QA y anotar restauración. Ante fallo comercial con resultado desconocido, consultar antes de repetir creación.

Una prueba negativa Aprobó sólo si también comprobaste ausencia de efectos indebidos. Un403 sin medir que no autenticó HKA, no guardó archivos ni mutó saldos puede ser evidencia incompleta. Una prueba de aislamiento requiere al menos dos tenants o dos sucursales realmente distintas.

### 7.1. Resultado mínimo escrito

```text
Caso: QA-PAG-001 / variante V01 / corrida QA-DIG-AAAA-MM-DD-01
Estado: Aprobó [o Reprobó]
Capa: navegador con HTTP integral y proveedor simulado
Tenant / sucursal / usuario: identificadores QA
Documento / pago / UUID: identificadores de la prueba
Esperado: total116VES; pago30VES; saldo86VES; recepción30VES una vez
Observado: valores leídos de respuesta, persistencia y caja después de recargar
Evidencia: ruta relativa recuperable de captura, respuesta saneada y lectura antes/después
Defecto: ninguno, o BUG-QA-NNN con reproducción e impacto
Agente / fecha y hora Caracas / iteración: completar
```

Si sólo se ejecutó el servicio con doble, escribir esa capa. No etiquetar navegador o Real DEMO. El ejemplo de veredicto no constituye una ejecución realizada.

## 8. Cobertura de todas las opciones y matrices

El catálogo inicial no puede anticipar valores futuros, configuraciones privadas o módulos del despliegue. Por eso el **inventario de opciones es una puerta de cierre obligatoria**. Recorrer cada pantalla como administrador y vendedor; abrir todos los desplegables, pestañas, diálogos y menús móvil/desktop, condiciones de pago, motivos, series, unidades/presentaciones, descuentos/cargos/atributos, formatos, canales y reportes. Registrar opción, valores, condición, ruta y caso.

Genera `QA-EXT-0001` y siguientes para cualquier opción no cubierta, con la misma especificación y campos de estado. Añade casos sin renumerar IDs existentes. Una opción oculta por feature flag requiere prueba en fixture habilitado o bloqueo/exclusión sustentados. Probar el botón que abre un selector no cubre todos los valores del selector.

Matrices obligatorias:

- Ocho identidades domésticas × Factura/Nota de venta × doce canales inventariados:192 casos; añadir ocho identidades×NC/ND web:16 casos. Total 208 de elegibilidad comercial, separados de aceptación remota.
- Factura ordinaria web: dos monedas×tres composicionesIVA×tres condiciones de pago:18 casos. NC/ND: dos tipos×dos monedas×tres composicionesIVA:12 casos.
- Exportación: FOB/CIF/EXW×VES/USD, seis ejecuciones comerciales/fiscales designadas; contado real y restricciones por canal/identidad/tipo/servicio.
- Terceros: VES/USD, comprador/titular/emisor, descuento/cargo/IGTF inicial y completo contado; todos los rechazos descritos.
- Estados fiscales×acciones: editar/preparar/enviar/consultar/PDF/correo; ejecutar cada variante del caso y su permiso.
- Roles×tenant/sucursal×rutas sensibles: visitante, vendedor autorizado, sin permiso, otra sucursal y otro tenant; administrador legítimo como control positivo.
- Todos los valores efectivos de medio/destino de pago, descuentos/cargos por nivel, atributos, formatos, plantillas y reportes. Los valores se expanden en subcasos/QA-EXT, no se aprueban por muestra.

La matriz de canal es una hipótesis de cobertura basada en el contrato transversal. Si el canal concreto no ofrece un tipo/documento, verifica su código/ruta vigente y registra la contradicción o exclusión justificada. No construir una opción inexistente ni marcar el caso como aprobado por no verlo. Módulo existente deshabilitado sigue sin probar: Bloqueada hasta habilitación QA o exclusión formal.

### 8.1. Configuraciones y bordes que pueden cambiar el resultado

Registrar en inventario: documento por defecto, mostrar80/58, autoimpresión/autocorreo, edición permitida, listas/búsquedas de productos, precio editable, descripciónPDF, descuentos y cargos, stock/lotes/seriales, ventas restringidas por tipo, sistema de puntos, consignado/itinerante, girosHotel/TAP/transporte, límites del plan, crédito vencido, módulos offline/importación y roles. Probar activado y desactivado cuando existan ambas ramas en el flujo. No inventar flags sólo por una etiqueta antigua.

El valor58MM puede existir para documentos comerciales. La descarga digital protegida permite `a4|a5|ticket` (80MM); no inferir soporte digital58 a partir de preferencias compartidas. Anticipos conservan controles comerciales; opciones heredadas de afectación retiradas deben reportarse si permanecen activas.

## 9. Secuencia sugerida y dependencias

| Fase | Grupos principales | Producto verificable |
|---|---|---|
|0|ENT, CFG, NUM, inventario|Entorno, fixtures, permisos, assets y capacidades|
|1|CLI, ITM, CAL, FAC, COM|Venta ordinaria, oráculos, impuesto/stock/pagos|
|2|EXP, TER, PRE, NOT|Categorías especiales, anticipos y notas locales|
|3|PAG, IGT, RET, EDI|Cobros/aplicaciones/reversión/edición y concurrencia|
|4|HKA|Preparación, commit, fallos, recuperación y aceptación DEMO designada|
|5|PDF, COR, LIS|Distribución, estados y todas las entradas/acciones|
|6|NVE, CAN, MAT, NEG, API, IMP|Conversiones, módulos, matrices y contratosHTTP|
|7|FIN, SEG, ROB|Reportes, conciliación, permisos, estabilidad y retest|
|8|ANU y limpieza del tenant desechable|Anulación local/herramientas sin borrar evidencia previamente|
|9|Inventario e informe final|Conteos reconciliados, límites y conclusión|

Prioriza P0 para detectar corrupción, duplicados, aislamiento, dinero o fiscal. Luego completa P1/P2. Un smoke exitoso no reduce alcance. Si falla P0, conserva evidencia, identifica el impacto y sigue con pruebas independientes que no agraven el mismo estado. Marca casos dependientes Bloqueados con el ID del defecto que impide continuar.

## 10. Evidencia, defectos y retest

Crea una carpeta `evidencias/<corrida>/<caso>/<variante>/` con archivos nombrados por propósito: `antes.json`, `request_saneado.json`, `response_saneado.json`, `despues.json`, `pantalla.png`, `documento.pdf`, `qr.txt`, `calculo_independiente.json`, `log_saneado.txt`. En cada registro escribe rutas recuperables, no “ver captura” sin ruta.

Evidencia mínima por capa:

| Capa | Evidencia necesaria |
|---|---|
|Análisis fuente|Commit, ruta, contrato/test leído y conclusión limitada a esa lectura|
|Unidad/contrato|Comando real, entorno aislado, salida, resultado y limitación de cobertura|
|HTTP integral con doble|Auth/middleware reales, request/response, persistencia, contador del proveedor y lecturas antes/después|
|Navegador|Capturas del clic/resultado, assets versión, request correlacionado y verificación posterior pertinente|
|Concurrencia|Dos procesos/sesiones, barrera temporal, ambos resultados, conteos, unicidad y montos finales|
|PDF/render|Archivo/hashes, tamaños/páginas, texto extraído, render de todas las páginas y QR decodificado cuando corresponda|
|Real DEMO|Documento de la corrida, identidad/UUID, resultado atribuible, control, consulta y originalPDF; contactoQA si se prueba correo|

Defecto obligatorio cuando Reprobó: `BUG-QA-NNN`, título, severidad, prioridad, casos afectados, versión/entorno, precondiciones, pasos de reproducción, esperado/observado, evidencia y efecto sobre venta/stock/dinero/fiscal/permisos. Incluye frecuencia y workaround si se conoce. Separa error de producto, bloqueo de entorno y limitación conocida; no conviertas una falta de credenciales en defecto de cálculo.

Severidad propuesta para el informe: Crítica (cruce tenant, duplicación fiscal/financiera, corrupción grave), Alta (flujo principal bloqueado o cálculo/saldo incorrecto), Media (opción funcional incorrecta con alternativa) y Baja (presentación sin pérdida funcional). La prioridad P0/P1/P2 del caso fija orden; severidad del defecto se determina por impacto observado.

Retest usa mismoID y nueva iteración/commit. Guarda la fila/bitácora anterior con Reprobó. Si una variante falla, padre Reprobó; si ninguna falla pero faltan variantes, padre Pendiente/Bloqueada según causa. Aprobó exige todas las variantes aplicables aprobadas y con evidencia.

## 11. Pruebas existentes como apoyo

Antes de ejecutar Laravel conDB, verifica `tests/CreatesApplication.php`, phpunit.xml y conexiones central/tenant, colas, correo y red. El arranque de aplicación no debe caer en bases operativas. MySQL de la suite fiscal crea/elimina bases temporales con prefijo `pro9_fiscal_test_`; no sustituirlo por un tenant real ni ejecutar `migrate:fresh` sobre datos existentes.

Comandos de referencia, después de verificar aislamiento y dependencias; estos comandos no se han ejecutado para elaborar esta guía:

```bash
# Desde la raíz de Pro9; primero verificar los archivos/test names actuales.
vendor/bin/phpunit tests/Unit/FiscalLineCalculationTest.php
vendor/bin/phpunit tests/Unit/DocumentEditPolicyTest.php
vendor/bin/phpunit tests/Unit/HkaPayloadBuilderTest.php
vendor/bin/phpunit tests/Unit/HkaResponseTest.php
vendor/bin/phpunit tests/Unit/HkaMailTest.php
vendor/bin/phpunit tests/Unit/HkaPdfTest.php
# Exclusivamente DB temporal configurada según la suite:
PRO9_FISCAL_MYSQL_TESTS=1 vendor/bin/phpunit tests/Unit/FiscalEmissionSchemaTest.php
# Cada scriptJS concreto se ejecuta sólo si existe en la versión probada:
node --test tests/js/hka-emission-ui.test.cjs
node --test tests/js/third-party-invoice-ui.test.cjs
```

Suites por área (verificar nombres en el inventario de fuentes): SalesDocumentTypePolicy/SalesCustomerIdentityPolicy/identidad; FiscalEmissionSettings/Schema; FiscalLineCalculation/FiscalApiPaymentTransform; DocumentEditPolicy/DocumentUpdateRequest; ExportInvoice/ThirdPartyInvoice; HkaPayloadBuilder/Transport/Response/Mail/Pdf/A5Text/A5Layout; CurrentPdfRendering/TemplateContract; FiscalIdentity/DocumentNumberDisplay/DocumentFileName; DocumentFiscalAuthorization/ListColumns; TestDocumentsDeletionBehavior/Contract. Los tests que buscan texto en fuente se etiquetan “contrato fuente”; no prueban permisosHTTP integrales o clics de UI.

Ejecuta la suite necesaria para el caso y conserva resultados. No marques cientos de casos funcionales Aprobó porque una suite general pasó. El inventario de tests apoya trazabilidad, no sustituye ejecución por flujo.

## 12. Criterios de cierre y métricas del informe

SeaT el total planificado incluyendo extensiones/subcasos según el nivel de conteo declarado, A aprobadas, R reprobadas, B bloqueadas, N no aplicables y P pendientes. Debe cumplirse **T=A+R+B+N+P**. Ejecutadas=A+R. Aplicables=T−N. Cobertura de ejecución=(A+R)/(T−N). Tasa de aprobación=A/(A+R). Si denominador0, “No calculable”, no100 %.

No mezcles conteo de casos padre con subcasos. Presenta ambos niveles cuando existan variantes. Una prueba bloqueada es cobertura faltante, no prueba aprobada. Documenta cada exclusiónN y su evidencia. Añadir opciones/casos cambiaT; no mantengas porcentajes de una lista antigua.

Conclusiones posibles:

- **Aprobado en el alcance ejecutado:** aplicables ejecutados, matrices/opciones trazables, sin P0 fallido/bloqueado/pendiente, sin defectos críticos/altos abiertos, dinero/stock/identidad fiscal conciliados y aceptación DEMO que el alcance requiere acreditada.
- **Reprobado:** existe fallo que incumple un requisito de facturación. Enumerar defectos/impactos y no ocultar aprobaciones parciales.
- **Inconcluso:** faltan pruebas aplicables o evidencia esencial por entorno, módulos, permisos, receptorQA, assets o proveedor. Enumerar exactamente faltantes; no llamarlo aprobado.

Separar conclusiones por registro comercial, emisión fiscalDEMO, notas locales/preparación, distribución y UI/API/seguridad. No certificar producción ni homologación completa desde DEMO. No usar límites conocidos como permiso para omitir casos de regresión de esos rechazos.

El informe final debe entregarse incluso cuando la corrida sea inconclusa. Completa la plantilla, enlaza el registro actualizado, casos adicionales, evidencia y bitácora de retest. Explica qué decisión permite tomar y qué falta probar.

