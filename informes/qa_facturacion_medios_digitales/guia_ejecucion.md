# Guía de ejecución QA de Pro9: facturación en Medios digitales

**Edición:** 12 de octubre de 2026. **Zona de registro:** America/Caracas (UTC−04:00).
**Modalidad requerida por el usuario:** Medios digitales (`digital`).
**Ambiente de referencia:** DEMO (`demo`), por el alcance actual del transporte HKA. DEMO es una elección de esta guía, no una confirmación del ambiente de un tenant existente.
**Versión contractual:** `87dc6506cf8d94c6e2d3cad1fb512b5b62e5e572`. **Catálogo:** 1121 casos en 35 grupos; 720 IDs conservados +401 nuevos.
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
| Retenciones IVA/ISLR recibidas | Relaciones recibidas de Factura 01, un activo por impuesto/factura | Previsión, aplicación local validada y outbox HKA independientes; emisión neta DEMO bloqueada | Perfil vigente, soporte privado, UUID, referencia/control, fórmula, estados, reversión y aislamiento |
| Regímenes especiales | Factura 01/0101 con special_tax_regime_id1..7 | Comprobantes web digital/DEMO, VES/USD, contado real completo; propia o por terceros | Matriz 28, IVA intacto, descuentos/cargos, snapshot, UUID de edición, envío explícito y ticket |
| Perfiles fiscales SPE | Versiones de empresa y comprador, RIF separado de cédula | Perfil fechado, facultades IVA/ISLR independientes y decisión IGTF | Naturaleza/firma/clase pública, notificación, vigencia/revocación, permisos y snapshots |

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
| P-G | UND, gravado `10`, precio final 116.00, base 100.00, IVA 16.00, stock 100 | Compra/venta de una unidad total 116.00 |
| P-E | UND, exento `20`, precio 50.00, stock 100 | Total 50.00 por unidad e IVA 0 |
| P-S | SERV, gravado, precio final 116.00 | Sin movimiento físico de inventario |
| P-V | Padre variable no vendible y dos hijos con atributos/stock distintos | ID hijo correcto y su stock |
| P-PRES | Presentación caja con equivalencia 6 UND | Venta 2 cajas equivale 12 UND si esa regla rige el fixture |
| P-PACK | Pack con componentes y regla de inventario anotada | No duplicar movimientos |
| P-LOTE | Lots enabled; L 1 stock 2, L 2 stock 5 | Venta 3 repartida 2+1 |
| P-SERIAL | S-A/S-B asignados al artículo | Un serial no vendido dos veces |
| P-ZERO/P-RESTRICT | Sin stock y restringido para venta | Política vigente de cada restricción |
| R1/R2 | `40.12345678` y `50.00000000` VES por USD | Tasas sintéticas, no cotizaciones BCV reales |
| IGTF-QA | Deshabilitado inicialmente; tasa operativa 3 % en fixture de prueba | No es afirmación de tasa legal |
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
|1 UND gravado precio final 116 al 16 %|Base 100; IVA 16; total 116|
|2 UND gravadas precio final 116|Base 200; IVA 32; total 232|
|1 UND exenta 50|Base exenta 50; IVA 0; total 50|
|Gravado 116 + exento 50|Base gravada 100; exenta 50; IVA 16; total 166|
|Descuento línea 00 sobre base 100 al 10 %|Descuento 10; base 90; IVA 14.40; total 104.40|
|Descuento 10 sin ajuste de base según tipo actual|Base IVA 100; IVA 16; total 106|
|Cargo línea 10 % sobre bruto 100 según cálculo actual|Cargo 10; base IVA 100; IVA 16; total 126|
|Pago USD 10 a tasa 40.12345678 sobre factura VES|Principal aplicado 401.23 VES; recibido 10 USD|
|Pago 401.23 VES a misma tasa sobre factura USD|Principal aplicado 10.00 USD; recibido 401.23 VES|
|Venta 116 VES−pago 30−retención IVA aplicada 12−fondo 10|Saldo 64 VES; venta 116; recepción efectiva 30. Si IVA sólo prevista/pendiente: saldo 76|
|IVA retenido 75 % de IVA 16|Comprobante 12; saldo 104 si no hay otras aplicaciones|
|ISLR base 100, 3 %, sustraendo 1|Retención 2; no ingreso de caja|
|IGTF QA 3 % de principal USD 20 posterior|Impuesto 0.60 USD; recepción 20.60 USD una vez; nota 08 local sin productos/IVA|

Porcentajes y cantidades del fixture son datos técnicos de prueba. Resolver perfiles fiscales versionados por fecha; no deducirlos de moneda, método de pago, cédula o nombre del cliente ni interpretar tasas de fixtures como tasas legales. El sistema por defecto usa IVA 16 % y permite la configuración vigente; variantes 8/31 sólo se prueban en entornos aislados configurados si corresponde al catálogo soportado.

### 5.2. Significado de los pagos

- `payment`: principal neto aplicado en moneda documental.
- `original_amount`: principal neto recibido en la moneda del pago, sin vuelto ni IGTF.
- `tax_amount`: IGTF recibido en moneda del pago.
- `receipt_parent_id`: recibo derivado que liquida impuesto; no representa otra recepción de efectivo.
- `operation_key`: UUID de la operación de cobro, con idempotencia del pago. No prueba por sí mismo la idempotencia de creación de una factura.
- `received_retentions`: sólo importe realmente aplicado reduce saldo; previsión, pending_validation, invalid y reversed no lo reducen. Una reversal_pending de una aplicada conserva efecto hasta acreditar ausencia remota. `guarantee_fund` conserva su contrato propio; tampoco genera caja/banco ni altera venta.

Cada comprobación monetaria compara línea, documento, aplicación, caja/banco y reporte por su propia moneda. No sumes VES y USD nominalmente. Los recibos derivados y pagos revertidos no cuentan como nuevos ingresos activos. En vuelto, entrega 120 para total 116 produce neto 116/vuelto 4, no venta 120.

### 5.3. Tolerancias y comparación

Identificadores, UUID, tipos, series, tenant/sucursal, moneda, número de registros y estados se comparan exactamente. La representación visible del número agrega hasta ocho dígitos, pero el payload/consulta HKA usa los valores originales del snapshot. Serie vacía no se convierte en `Sin serie`, `SIN_SERIE` ni `__without_series__` fiscal.

Importes finales se comparan a la precisión contractual de dos decimales; tasas a ocho decimales como cadena. Documenta redondeo por línea frente a global. Las tolerancias 0.005/0.01 que ciertos servicios usan para saldo/fórmula no son una autorización general para descuadres. Una diferencia sólo se acepta si deriva del algoritmo explícito probado y se registra el cálculo.

Los bytes de A4 original HKA deben ser iguales por hash. A5 es transformación legítima: compara número de páginas, tamaño 210×148 mm, contenido, proporciones de glifos/logo y QR. El ticket 80 MM se valida con QR decodificado igual a `consulta_url`; no basta ver un cuadrado impreso.

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

**Detalle de UUID al editar:** el código actual conserva UUID de una factura ordinaria que ya estaba `not_requested`, porque todavía no hay payload preparado; para terceros o regímenes especiales renueva también ese UUID, incluso al retirar un régimen de una factura que lo tenía. Al invalidar `prepared/rejected/uncertain` editable se renueva. No exigir un cambio indiscriminado de UUID por cualquier edición ni mezclar UUID de pago con UUID fiscal.

Recuperación de `pending` de emisión: comprobar frontera 40 s del intento según implementación actual. Reenvío: mínimo 30 s desde intento y ausencia inequívoca acreditada, no sólo timeout/vacío/código 203. Correo tiene recuperación propia de 50 s. Retenciones IVA/ISLR tienen protección de 90 s para reclamo de pending y retiro que sigue a una aplicación pending/uncertain; sus incertidumbres y backoff tienen reglas propias (sección 13.5). Simula reloj; no dejes bloqueada la conversación ni provoques timeouts reales para demostrar esas fronteras.

La actualización de UUID también se aplica al agregar/cambiar/retirar régimen especial en una edición permitida. Terceros+régimen es ahora combinación positiva sólo desde web, contado real completo y operación 0101. No convertir esa categoría a exportación ni habilitar notas por esa combinación.

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

Una prueba negativa Aprobó sólo si también comprobaste ausencia de efectos indebidos. Un 403 sin medir que no autenticó HKA, no guardó archivos ni mutó saldos puede ser evidencia incompleta. Una prueba de aislamiento requiere al menos dos tenants o dos sucursales realmente distintas.

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
- Regímenes: siete valores×VES/USD×propia/terceros =28 casos individuales QA-RGE-001 a028; completar edición/cálculos/restricciones y todos los demás casosRGE.
- SPE: naturalezas y firma personal, seis clases públicas, estadosIVA, cinco fundamentos100 %, facultades independientes, versiones/vigencia/revocación; matrizIGTF dos perfiles emisor×dos monedas recibidas×tres intermediaciones =12 casos individuales.
- IVA/ISLR: propuesta, pending/applied/invalid/reversed, HKAquery/apply/remove, soporte/permisos/idempotencia y recuperación por impuesto. Todos los 85 conceptosISLR iniciales no salariales tienen ID propio.
- Coexistencia: cada impuesto solo y ambos×13 entradas/canales =39 casos individuales; abonos, borradores, conversiones, carreras, reportes e aislamiento.
- Estados fiscales×acciones: editar/preparar/enviar/consultar/PDF/correo; ejecutar cada variante del caso y su permiso.
- Roles×tenant/sucursal×rutas sensibles: visitante, vendedor autorizado, sin permiso, otra sucursal y otro tenant; administrador legítimo como control positivo.
- Todos los valores efectivos de medio/destino de pago, descuentos/cargos por nivel, atributos, formatos, plantillas y reportes. Los valores se expanden en subcasos/QA-EXT, no se aprueban por muestra.

La matriz de canal es una hipótesis de cobertura basada en el contrato transversal. Si el canal concreto no ofrece un tipo/documento, verifica su código/ruta vigente y registra la contradicción o exclusión justificada. No construir una opción inexistente ni marcar el caso como aprobado por no verlo. Módulo existente deshabilitado sigue sin probar: Bloqueada hasta habilitación QA o exclusión formal.

### 8.1. Configuraciones y bordes que pueden cambiar el resultado

Registrar en inventario: documento por defecto, mostrar80/58, autoimpresión/autocorreo, edición permitida, listas/búsquedas de productos, precio editable, descripciónPDF, descuentos y cargos, stock/lotes/seriales, ventas restringidas por tipo, sistema de puntos, consignado/itinerante, girosHotel/TAP/transporte, límites del plan, crédito vencido, módulos offline/importación y roles. Probar activado y desactivado cuando existan ambas ramas en el flujo. No inventar flags sólo por una etiqueta antigua.

El valor58MM puede existir para documentos comerciales. La descarga digital protegida permite `a 4|a 5|ticket` (80MM); no inferir soporte digital58 a partir de preferencias compartidas. Anticipos conservan controles comerciales; opciones heredadas de afectación retiradas deben reportarse si permanecen activas.

## 9. Secuencia sugerida y dependencias

| Fase | Grupos principales | Producto verificable |
|---|---|---|
|0|ENT, CFG, NUM, SPE, inventario|Entorno, fixtures, permisos, assets y capacidades|
|1|CLI, ITM, CAL, FAC, COM|Venta ordinaria, oráculos, impuesto/stock/pagos|
|2|EXP, TER, RGE, PRE, NOT|Categorías especiales, anticipos y notas locales|
|3|PAG, IGT, RET, RIVA, RISLR, INT, EDI|Cobros/aplicaciones/reversión/edición y concurrencia|
|4|HKA|Preparación, commit, fallos, recuperación y aceptación DEMO designada|
|5|PDF, COR, LIS|Distribución, estados y todas las entradas/acciones|
|6|NVE, CAN, MAT, NEG, API, IMP|Conversiones, módulos, matrices y contratosHTTP|
|7|FIN, SEG, ROB|Reportes, conciliación, permisos, estabilidad y retest|
|8|ANU y limpieza del tenant desechable|Anulación local/herramientas sin borrar evidencia previamente|
|9|Inventario e informe final|Conteos reconciliados, límites y conclusión|

Prioriza P0 para detectar corrupción, duplicados, aislamiento, dinero o fiscal. Luego completa P1/P2. Un smoke exitoso no reduce alcance. Si falla P0, conserva evidencia, identifica el impacto y sigue con pruebas independientes que no agraven el mismo estado. Marca casos dependientes Bloqueados con el ID del defecto que impide continuar.

## 10. Evidencia, defectos y retest

Crea una carpeta `evidencias/<corrida>/<caso>/<variante>/` con archivos nombrados por propósito: `antes.json`, `request_saneado.json`, `response_saneado.json`, `des pues.json`, `pantalla.png`, `documento.pdf`, `qr.txt`, `calculo_independiente.json`, `log_saneado.txt`. En cada registro escribe rutas recuperables, no “ver captura” sin ruta.

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

Antes de ejecutar Laravel conDB, verifica `tests/CreatesApplication.php`, phpunit.xml y conexiones central/tenant, colas, correo y red. El arranque de aplicación no debe caer en bases operativas. MySQL de la suite fiscal crea/elimina bases temporales con prefijo `pro 9_fiscal_test_`; no sustituirlo por un tenant real ni ejecutar `migrate:fresh` sobre datos existentes.

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


## 13. Instrucciones detalladas de las funcionalidades nuevas

### 13.1. Fixtures y operaciones de preparación

Primero inventaría los perfiles existentes sin modificarlos. En tenant QA crea versiones desde los editores de empresa y cliente con U-ADMIN: usa vigencia 01/10/2026, notificación 30/09/2026, referencia QA-SPE y fundamento de facultad ISLR separado QA-ISLR. Para oráculos usa emisor ordinary no SPE excepto los casos IGTF/SPE; comprador agente IVA elegible bajo su fundamento y agente ISLR con base independiente. Las facultades no se completan automáticamente por ser SPE.

El RIF de perfil exige formato `V/J/E/G/C/R-########-#`, con prefijo concordante: natural V/E/R, jurídica J/C y pública G/J. No afirmar validación de dígito verificador ni inscripción SENIAT. La cédula/número doméstico conserva su campo propio. Firma personal sólo natural; clase pública sólo pública y obligatoria allí. Las seis clases son `national`, `state`, `municipal`, `autonomous_institute`, `decentralized_non_business`, `decentralized_business`. Para públicas específicas verificar fundamento/evidencia y contexto de la operación (`public_operation_scope=subject` y `public_scope_reference`), sin asumir facultad general SPE.

SPE exige notificación y fecha≤inicio de vigencia. Seleccionar versión por effective_from ≤fecha y desempate ID; fin de vigencia inclusive. Revocación deja SPE inactivo desde esa fecha, sin borrar fundamento público o ISLR independientes. Documento usa fecha de emisión; pago y comprobante sus propias fechas. Conservar IDs de versión y snapshots al crear historial nuevo; edición autorizada de comprador sigue usando fecha fiscal original. El perfil emisor debe coincidir con RIF empresa y actividad comercial=true. Sólo admin escribe versiones; endpoints generales no son una vía alternativa de escritura.

Rutas web de perfil: GET/POST `fiscal-profiles/{kind}/{id}` y POST `fiscal-profiles/evaluate`, con kind company/customer. Confirmar URL exacta y permisos contra rutas actuales; no suponer exposición API si no existe. Retenciones usan los contratos compartidos `documents/retention`: catálogo GET/concepts, propuesta POST/proposal, upload POST/upload-draft o/upload, consulta GET/{document}, registro POST raíz, adjunto GET/{id}/attachment, query POST/{id}/query-hka, retry POST/{id}/retry-hka, reverse POST/{id}/reverse. En API usar prefijo/autenticación real y rutas vigentes. Registro lleva tax_kind apropiado; acciones sobre un ID resuelven el impuesto del registro, no reinterpretan una bandera enviada por cliente.

Todo voucher requiere soporte PDF/JPG/PNG privado≤5 MB. Subir por interfaz/endpoint autorizado; anotar hash, token UUID, tenant, usuario y fecha de expiración 24 h. Registrar/consumir token una sola vez; descargar sólo con autorización y respuesta privada/no-store. Probar 1 MB y frontera 5 MB exacta en variantes individuales, además >5 MB, MIME adversario, token ajeno/consumido/vencido y rollback. La copia conserva el temporal cuando una transacción exterior falla; limpieza concurrente no puede eliminar soporte asociado a un comprobante consumido.

### 13.2. Regímenes: ejecutar la matriz y las ramas

QA-RGE-001 a 028 cubren individualmente los siete valores del selector: Zonas económicas especiales, Zona franca de Paraguaná, Zona libre de Paraguaná, Puerto libre Santa Elena de Uairén, Zona Libre de Mérida, Puerto libre Estado Nueva Esparta y Dutty Free (nombre contractual exacto). No corregir la etiqueta del catálogo durante la corrida.

Para cada combinación abrir Comprobantes web, seleccionar Factura 01/0101, moneda VES/USD y categoría propia/terceros, elegir régimen y comprobar que no cambia IVA de productos. Registrar titular único distinto de comprador/emisor cuando sea terceros. Usar bienes y servicio con total 282 nominal (base gravada 200, IVA 32, exenta 50) y cobro real completo 282. Con tasa 50, fiscal USD→VES total 14100, IVA 1600, exenta 2500; el adaptador convierte una sola vez incluso cuando hay terceros+régimen. Datos locales no se reescriben.

Comprobar snapshot autoritativo ID/descripcion; HKA lleva descripción en regimenEspTributacion, nunca un ID como texto. Probar descuento sobre base, cargo según código, descuento global/distribución y IGTF inicial con emisor SPE. Agregar/cambiar/retirar régimen al editar sin control renueva UUID según política, conserva serie/número/moneda/categoría/pagos y saldo 0; requiere nuevo envío explícito. No guardar y luego asumir envío automático.

Crédito, pago incompleto, exportación, tipos distintos 01, itinerante/conditional_data, transacción forzada, notas y otros canales deben rechazar antes de efectos. La excepción nueva es terceros+régimen, permitida sólo desde web 0101/DEMO/contado real completo. Correo/PDF/ticket/listado se prueban con veredicto de presentación propio. A4 original HKA puede omitir régimen aunque el JSON lo incluya: conservar original por hash y reportar límite; A5/correo heredan esa omisión, ticket local debe identificarlo.

### 13.3. Propuesta, deuda y aplicación: cálculo obligatorio

| Momento en el fixture 116 VES | Caja acumulada | IVA aplicado | ISLR aplicado | Deuda real | Cobro sugerido restante |
|---|---:|---:|---:|---:|---:|
| Sin pagos, previsiones IVA 12/ISLR 3 |0|0|0|116|101|
| Pago real 101, aún previsiones/pendientes |101|0|0|15|0 con previsiones, sin declarar deuda liquidada|
| IVA validada 12, ISLR pendiente |101|12|0|3|0, todavía falta ISLR válida|
| Ambas aplicadas 12+3 |101|12|3|0|0|
| Revertir ISLR con ausencia acreditada |101|12|0|3|Sugerencia sólo informativa si previsión restaurada|
| Revertir ambos con ausencia acreditada |101|0|0|15|No hacer otro cobro automático|

Una previsión es informativa y el servidor la recalcula según cliente/fecha/líneas/contexto. No resta saldo. Reabrir Pagos y recuperar borradores conserva sólo abonos realmente ingresados, con moneda, tasa, fecha y UUID. No añadir el total completo ni repartir abonos existentes entre filas. Con 30+20 abonados: deuda 66; previsiones 12+3 sugieren remanente 51 sólo por acción explícita. Si se cobra 51 realmente, efectivo 101 y deuda 15 hasta aplicaciones válidas. Probar respuestas asincrónicas fuera de orden al cambiar cliente, fecha o cantidades; la propuesta vieja no puede sobrescribir la actual.

Un comprobante activo por impuesto/factura significa pending_validation o applied, incluso si applied está en reversal_pending. IVA+ISLR pueden coexistir; no sumar varios IVA o varios ISLR activos. invalid/reversed quedan en historial y permiten reemplazo con nuevo UUID conforme unicidad. Registrar exige UUID, soporte, fechas/importes/perfil. Factura digital exige confirmed+control y referencia coincidente para activar; sin ello queda pending_validation / applied_amount 0. Mismatch o validación tardía fallida deja invalid 0, con rollback/savepoint antes de preservar diagnóstico.

IVA 75 %: base comprobante es IVA 16, importe 12; base imponible 100 es otro campo. IVA 100 % exige fundamento y evidencia: undisclosed_iva,invoice_formalities,portal_100,unregistered_rif,precious_metals; público específico no admite precious_metals como ese fundamento. Probarlos por separado. IVA parcial una alícuota puede tener base IVA 8/importe 6/base imponible 50; en alícuotas mixtas sólo IVA completo sin desglose parcial. Con base 100 al 16%+base 100 al 8%:IVA 24, total 224, retención 75 %=18 y base imponible 200; parcial IVA 12 sin desglose se rechaza. Conservar cap fiscal VES congelado y tasa del comprobante; HKA convierte directamente comprobante→moneda fiscal, sin tasa hoy ni conversión doble.

ISLR: un concepto no salarial existente de tres dígitos, 001 excluido. Declarar concept_basis y evidence_reference no blancos≤255, porcentaje>0≤100, base positiva, sustraendo≥0, número no vacío≤50 y fecha emisión..hoy. Fórmula max(0, base×porcentaje/100−sustraendo); importe aplicado debe ser positivo y exacto tras precisión contractual. Base 100, 3 %, sustraendo 1 da 2, no 3. El catálogo no es motor de tasas; sus percentage_label históricos no completan cálculo. Cada concepto 002..086 tiene caso individual con datos técnicos sintéticos y evidencia del selector/cálculo, sin certificar validez tributaria de una operación real desde una tasa QA.

### 13.4. IGTF: decisión fechada y contexto de replay

Sujeto si y sólo si emisor SPE vigente a fecha del pago, moneda recibida USD e intermediación none. Con emisor SPE+USD es obligatorio declarar none o financial_institution; omitir se rechaza. Moneda documental, método de pago y SPE del comprador no sustituyen esos tres hechos. Intermediación financiera, recepción VES o emisor no SPE dejan not_applicable. Subject requiere configuración IGTF habilitada/tasa positiva del fixture.

La excepción exempt requiere administrador, motivo y exception_basis; snapshot conserva authorized_by. Probar falta de cada campo, rol no administrador y clasificación cliente contradictoria. Replay UUID compara contexto original completo (importe, moneda, tasa, fecha, intermediación, clasificación/fundamento); nuevo perfil no recalcula pago histórico. Con principal 100 USD, none, emisor SPE y tasa QA 3 %:impuesto 3/recepción 103. Retenciones no generan efectivo ni incrementan base de IGTF; IGTF posterior de categorías que requieran nota no soportada se bloquea transaccionalmente.

### 13.5. Conciliación HKA, ausencia y recuperación

| Operación | Ventana/control propio | Evidencia de recuperación |
|---|---|---|
| Factura pending |40 s para recuperar intento según contrato; reenvío≥30 s sólo con ausencia inequívoca |EstadoDocumento, UUID/intento/control; no sólo timeout|
| Correo |50 s en su máquina; no usar estado factura como entrega |Solicitud/rastreo/recepción QA, por intento|
| Retenciones pending |90 s de protección del intento antes de reclamo concurrente |GET del endpoint del impuesto,attempt_id y estado outbox|
| Retiro de retención luego apply pending/uncertain |90 s desde inicio de aplicación antes del retiro |No liberar aplicación local antes de ausencia remota|
| Retención uncertain/waiting ya liberada |next_attempt_at/backoff progresivo 60 s hasta 1 h; queryonly según contrato |No imponer indiscriminadamente 90 s a cada consulta; no replay automático de rejected/conflict|

Usar reloj controlado para 39/40, 49/50, 89/90/91 y vencimientos de backoff; no esperar timeouts reales. Scheduler tenant cada minuto: iva-retentions:recover e islr-retentions:recover, sin solapamiento. Verificar compañía→factura→comprobante→operación bajo bloqueo, autenticación/HTTP fuera de la transacción, contexto payload inmutable y protección de respuesta de intento viejo. Rollback exterior no dispara HTTP; fallo remoto no deshace aplicación local válida.

Protocolo GET antes de POST/DELETE y GET posterior. Registro remoto exacto se adopta sin POST nuevo; remoto distinto queda conflict sin sobrescribir. Una consulta de ausencia sobre apply conflict no autoriza retransmisión; coincidencia exacta sí puede confirmar. Retención posiblemente remota conserva efecto local durante reversal_pending hasta ausencia acreditada. Cuando nunca se transmitió ni observó remotamente puede revertirse localmente y cancelar intentos anteriores; respuesta tardía no la reactiva. Soporte/historial/actor/motivo permanecen.

IVA usa /api/AplicarRetencion; ausencia exitosa específica: HTTP 2xx, codigo 203, mensaje exacto «Retención no encontrada», validaciones única «No se ha aplicado retención previamente por lo que no se puede consultar nada». También codigo 200/sin validaciones con infoRetencion null explícito, o identidad coincidente y totalesRetencion null, según adaptador. Un 203 genérico, JSON faltante, timeout o identidad contradictoria no acredita ausencia. GET/DELETE reciben JSON de identidad congelada, tipoDocumento 01/serie/número/control originales, sin número visible rellenado.

ISLR usa /api/AplicarRetencionISLR y outbox propia. Ausencia específica: HTTP 2xx, codigo 204, mensaje exacto «No se ha registrado aún ninguna Retención de ISLR a este documento», validaciones vacías y sin infoRetencionISLR registrada; también respuesta 200 sin validaciones con infoRetencionISLR null explícito. 203 IVA no es ausencia ISLR. Payload detallesRetencionISLR incluye numeroCompRetencion, fechaEmisionCR, impuestoRetenido, rifAgenteRetencion, porcentaje y omite codigoOperacion. GET compara sólo siete campos:serie, tipoDocumento, numeroDocumento, numeroControl, numeroCompRetencion, fechaEmisionCR, impuestoRetenido. No afirmar RIF, porcentaje, concepto o sustraendo remotamente verificados si GET no los retorna.

### 13.6. Capacidades y límites: no sustituir evidencia

| Capacidad al commit de referencia | Estado real del contrato | Prueba obligatoria |
|---|---|---|
| IVA demo_net_collection_verified |false; neto VES/USD rechazado históricamente 1016/203 |Guardar operación local y comprobar bloqueo antes de Emision, diagnóstico y can_send =false|
| ISLR demo_net_collection_verified |false aunque hubo un caso histórico VES aceptado; USD requiere verificación propia |Mismo bloqueo por moneda/condición; no habilitar flag por esa aceptación parcial|
| IVA+ISLR netas |No verificada; ambas capacidades exigidas |Bloqueo combinado, cobro real 101/deuda 15; USD y VES tienen resultados propios|
| Original HKA régimen/SPE |Puede omitir descripciones/leyendas; identidad prefijo puede duplicarse |Comparar payload, snapshots, detalle/ticket local y hash original; no editar PDF original|
| Producción/SPE emisor/notas homologadas |Sin aceptación por la presente entrega |Registrar bloqueos/exclusiones por capacidad y capa; no inferir desde comprador SPE aceptado|

Si el caso espera bloqueo contractual, aprueba el rechazo correcto y efectos cero. La prueba positiva de emisión neta real no se declara aprobada: está bloqueada por capacidad y su diagnóstico es un resultado separado. Para probar aplicación/reversión local positiva usa fixtures con confirmación del doble y evidencia HTTP/persistencia; no controlar una factura real de saldo 0 creando retención que superaría saldo. Un control protocolario HKA de factura totalmente pagada sólo acredita POST/GET/DELETE, no la aplicación financiera local ni emisión neta completa.

## 14. Contenido obligatorio adicional del informe de corrida

Completar las secciones específicas de regímenes, SPE, IVA, ISLR y coexistencia en la plantilla. Enumerar todos los IDs/variantes y conclusiones de las cuatro capas. Incluir matriz 28 y conceptos ISLR 85 con resultado propio, perfil/versiones por fecha, saldo antes/después, cash por moneda y operaciones remotas por impuesto. Registrar bloqueo can_send y flags efectivos, los límites del original y la evidencia simulada/histórica en anexo separado. Todas las pruebas nuevas empiezan Pendiente; asignar Aprobó/Reprobó sólo tras ejecución atribuida y documentada. Entregar informe incluso Inconcluso, registro Excel actualizado, cálculos, evidencias y defectos/retest.
