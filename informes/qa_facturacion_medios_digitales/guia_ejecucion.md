# Guía de ejecución QA de Pro 9: facturación en Medios digitales

**Edición:** 12 de octubre de 2026. **Zona de registro:** America/Caracas (UTC−04:00).
**Modalidad requerida por el usuario:** Medios digitales (`digital`).
**Ambiente de referencia:** DEMO (`demo`), por el alcance actual del transporte HKA. DEMO es una elección de esta guía, no una confirmación del ambiente de un tenant existente.
**Versión contractual:** `76690125` + cambios locales de órdenes. **Catálogo:** 1128 casos en 42 grupos; las equivalencias conservan la trazabilidad de IDs agrupados.
**Estado de este documento:** especificación de pruebas. No es un informe de pruebas ejecutadas.

## 1. Mandato para el agente ejecutor

Ejecuta esta guía y el documento de casos completos sobre la versión identificada de Pro 9. Tu trabajo comprende el registro comercial, su resultado fiscal, cobros, stock, distribución, reportes y permisos. Debes probar cada tipo vigente, cada opción disponible y las restricciones de los flujos no habilitados. No sustituyas una matriz completa por una muestra sin documentar expresamente la cobertura que falta.

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
| Nota de crédito | `07` | Creación, emisión, distribución y anulación ordinaria doméstica DEMO; exige factura original confirmed/control coincidente | Cuatro motivos activos, ajustes/devoluciones, referencias, moneda, saldo, stock y preparación |
| Nota de débito comercial | `08` | Creación, emisión, distribución y anulación ordinaria doméstica DEMO; deuda/cobros propios | Tres motivos manuales activos, importes/referencia y efecto comercial |
| Nota de débito IGTF posterior | `08`, motivo `IGTF`, transacción HKA `98` | Generación local asociada al cobro y preparación; sin artículos ni IVA | Principal/impuesto/recibo derivado, caja, idempotencia y reversión |
| Nota de venta | `80`; `nv` únicamente donde el servicio técnico mantiene ese alias | Documento comercial propio, aunque su tenant sea digital | Creación, cobros, PDF, edición/duplicación/anulación según flujo y conversión a Factura |
| Factura de anticipo y deducción | Factura `01` y relaciones comerciales de anticipo | Opciones existentes a validar comercialmente; la aceptación/adaptación HKA debe demostrarse por separado | Registro y saldo del anticipo, aplicación parcial/múltiple, exceso, concurrencia y no duplicación |
| Orden de entrega | `09`, persistencia `dispatch` | Registro propio, emisión HKA04/indicador1 después del commit, consulta, PDF original A4 y relaciones; con ampliación de esquema | Vínculos, almacén/sucursal de origen, transporte y stock |
| Retenciones IVA/ISLR recibidas | Relaciones recibidas de Factura 01, un activo por impuesto/factura | Previsión, aplicación local validada y outbox HKA independientes; emisión neta DEMO bloqueada | Perfil vigente, soporte privado, UUID, referencia/control, fórmula, estados, reversión y aislamiento |
| Regímenes especiales | Factura 01/0101 con special_tax_regime_id 1..7 | Comprobantes web digital/DEMO, VES/USD, contado real completo; propia o por terceros | Matriz 28, IVA intacto, descuentos/cargos, snapshot, UUID de edición, envío explícito y ticket |
| Perfiles fiscales SPE | Versiones de empresa y comprador, RIF separado de cédula | Perfil fechado, facultades IVA/ISLR independientes y decisión IGTF | Naturaleza/firma/clase pública, notificación, vigencia/revocación, permisos y snapshots |

**No confundir catálogo con capacidad:** `FE`, `20`, `ISLR`, `ARCV` y otros tipos pueden existir en catálogos/series. Su presencia no habilita emisión en el procesamiento común de Facturas y notas. La exportación implementada usa Factura `01`; no `FE`. La Boleta `03` está retirada y debe rechazarse en cada canal nuevo.

La traducción del adaptador es Pro 9 `01 → HKA 01`, Pro 9 `07 → HKA 02`, Pro 9 `08 → HKA 03`. Los motivos de notas tienen sus propios códigos. HKA `07` no es Nota de crédito Pro 9. Conserva los códigos internos en base de datos y no uses coincidencias de códigos de distintos catálogos para deducir comportamiento.

Motivos iniciales de NC: `01 Anulación total de factura`, `07 Devolución parcial de mercancía`, `04 Descuento o rebajas concedidas`, `09 Corrección de precios o cálculos`. Motivos de ND manual: `02 Ajustes por incremento de precios`, `01 Intereses de mora o financiamiento`, `03 Gastos de despacho, fletes, seguros o embalaje`. `IGTF` tiene un flujo derivado del cobro; no se trata como nota manual libre.

### 3.1. Límites que deben quedar visibles en el resultado

- Producción digital no está habilitada en el transporte actual. No pruebes emisión real de producción para intentar ampliar ese alcance.
- NC/ND preparadas no son aceptadas remotamente por esa sola preparación. La integración de facturas no demuestra homologación de notas.
- Exportaciones: sólo bienes y Comprobantes web DEMO, operaciones FOB/CIF/EXW; no servicios exportados, 0200 genérico, notas de exportación, otros canales, terceros o regímenes especiales combinados.
- La evidencia actual de exportación HKA acredita contado. Crédito sin cobros ha sido rechazado por falta de formas de pago. Debe reportarse como límite conocido; no fabricar un cobro para convertirlo en éxito.
- Terceros: sólo venta interna y contado real completo. Crédito de la factura y demás canales fuera de esta etapa. NC/ND domésticas vinculadas sí están habilitadas desde factura confirmada, con tercero/régimen heredados. El IGTF posterior dependiente de una nota se bloquea para exportación/terceros; el inicial conserva su flujo vigente.
- Cobro en moneda propia/mixto está implementado por contrato API; la interfaz actual no contiene todos esos selectores. No describas una opción API como botón de pantalla.
- La emisión HKA y su correo/PDF tienen estados separados. Guardado local, preparación, aceptación fiscal, disponibilidad PDF, solicitud de correo y entrega efectiva son resultados distintos.
- Las notas ordinarias admiten anulación HKA DEMO auditada. Facturas y órdenes no admiten anulación remota positiva. Las órdenes tienen original A4 privado; correo HKA y anulación remota de órdenes quedan fuera.
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

Un oráculo es el valor esperado calculado independientemente. Calcula con `Decimal`, aritmética racional o precisión equivalente, sin copiar el total devuelto por Pro 9 como si fuera el esperado. Conserva una hoja/JSON de cálculo por caso monetario. La tasa expresa VES por USD: USD→VES multiplica, VES→USD divide; se redondea el importe final, no la tasa.

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

- Ocho identidades domésticas × Factura/Nota de venta × doce canales inventariados: 192 comprobaciones; añadir ocho identidades × NC/ND web: 16 comprobaciones. Total: 26 casos agrupados con 208 comprobaciones de elegibilidad comercial, separados de aceptación remota.
- Factura ordinaria web: dos monedas×tres com posiciones IVA×tres condiciones de pago:18 casos. NC/ND: dos tipos×dos monedas×tres com posiciones IVA:12 casos.
- Exportación: FOB/CIF/EXW×VES/USD, seis ejecuciones comerciales/fiscales designadas; contado real y restricciones por canal/identidad/tipo/servicio.
- Terceros: VES/USD, comprador/titular/emisor, descuento/cargo/IGTF inicial y completo contado; todos los rechazos descritos.
- Regímenes: siete valores×VES/USD×propia/terceros =28 casos individuales QA-RGE-001 a 028; completar edición/cálculos/restricciones y todos los demás casos RGE.
- SPE: naturalezas y firma personal, seis clases públicas, estados IVA, cinco fundamentos 100 %, facultades independientes, versiones/vigencia/revocación; matriz IGTF dos perfiles emisor×dos monedas recibidas×tres intermediaciones =12 casos individuales.
- IVA/ISLR: propuesta, pending/applied/invalid/reversed, HKAquery/apply/remove, soporte/permisos/idempotencia y recuperación por impuesto. Los 85 conceptos ISLR iniciales no salariales se verifican como datos de un caso agrupado con evidencia por código.
- Coexistencia: cada impuesto solo y ambos×13 entradas/canales =39 casos individuales; abonos, borradores, conversiones, carreras, reportes e aislamiento.
- Estados fiscales×acciones: editar/preparar/enviar/consultar/PDF/correo; ejecutar cada variante del caso y su permiso.
- Roles×tenant/sucursal×rutas sensibles: visitante, vendedor autorizado, sin permiso, otra sucursal y otro tenant; administrador legítimo como control positivo.
- Todos los valores efectivos de medio/destino de pago, descuentos/cargos por nivel, atributos, formatos, plantillas y reportes. Los valores se expanden en subcasos/QA-EXT, no se aprueban por muestra.

La matriz de canal es una hipótesis de cobertura basada en el contrato transversal. Si el canal concreto no ofrece un tipo/documento, verifica su código/ruta vigente y registra la contradicción o exclusión justificada. No construir una opción inexistente ni marcar el caso como aprobado por no verlo. Módulo existente deshabilitado sigue sin probar: Bloqueada hasta habilitación QA o exclusión formal.

### 8.1. Configuraciones y bordes que pueden cambiar el resultado

Registrar en inventario: documento por defecto, mostrar80/58, autoimpresión/autocorreo, edición permitida, listas/búsquedas de productos, precio editable, descripción PDF, descuentos y cargos, stock/lotes/seriales, ventas restringidas por tipo, sistema de puntos, consignado/itinerante, girosHotel/TAP/transporte, límites del plan, crédito vencido, módulos offline/importación y roles. Probar activado y desactivado cuando existan ambas ramas en el flujo. No inventar flags sólo por una etiqueta antigua.

El valor 58MM puede existir para documentos comerciales. La descarga digital protegida permite `a 4|a 5|ticket` (80MM); no inferir soporte digital 58 a partir de preferencias compartidas. Anticipos conservan controles comerciales; opciones heredadas de afectación retiradas deben reportarse si permanecen activas.

## 9. Secuencia sugerida y dependencias

| Fase | Grupos principales | Producto verificable |
|---|---|---|
|0|ENT, CFG, NUM, SPE, inventario|Entorno, fixtures, permisos, assets y capacidades|
|1|CLI, ITM, CAL, FAC, COM|Venta ordinaria, oráculos, impuesto/stock/pagos|
|2|EXP, TER, RGE, PRE, NOT|Categorías especiales, anticipos y notas locales|
|3|PAG, IGT, RET, RIVA, RISLR, INT, EDI|Cobros/aplicaciones/reversión/edición y concurrencia|
|4|HKA|Preparación, commit, fallos, recuperación y aceptación DEMO designada|
|5|PDF, COR, LIS|Distribución, estados y todas las entradas/acciones|
|6|NVE, CAN, MAT, NEG, API, IMP|Conversiones, módulos, matrices y contratos HTTP|
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
|Real DEMO|Documento de la corrida, identidad/UUID, resultado atribuible, control, consulta y original PDF; contacto QA si se prueba correo|

Defecto obligatorio cuando Reprobó: `BUG-QA-NNN`, título, severidad, prioridad, casos afectados, versión/entorno, precondiciones, pasos de reproducción, esperado/observado, evidencia y efecto sobre venta/stock/dinero/fiscal/permisos. Incluye frecuencia y workaround si se conoce. Separa error de producto, bloqueo de entorno y limitación conocida; no conviertas una falta de credenciales en defecto de cálculo.

Severidad propuesta para el informe: Crítica (cruce tenant, duplicación fiscal/financiera, corrupción grave), Alta (flujo principal bloqueado o cálculo/saldo incorrecto), Media (opción funcional incorrecta con alternativa) y Baja (presentación sin pérdida funcional). La prioridad P0/P1/P2 del caso fija orden; severidad del defecto se determina por impacto observado.

Retest usa mismo ID y nueva iteración/commit. Guarda la fila/bitácora anterior con Reprobó. Si una variante falla, padre Reprobó; si ninguna falla pero faltan variantes, padre Pendiente/Bloqueada según causa. Aprobó exige todas las variantes aplicables aprobadas y con evidencia.

## 11. Pruebas existentes como apoyo

Antes de ejecutar Laravel con DB, verifica `tests/CreatesApplication.php`, phpunit.xml y conexiones central/tenant, colas, correo y red. El arranque de aplicación no debe caer en bases operativas. My SQL de la suite fiscal crea/elimina bases temporales con prefijo `pro 9_fiscal_test_`; no sustituirlo por un tenant real ni ejecutar `migrate:fresh` sobre datos existentes.

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

Suites por área (verificar nombres en el inventario de fuentes): SalesDocumentTypePolicy/SalesCustomerIdentityPolicy/identidad; FiscalEmissionSettings/Schema; FiscalLineCalculation/FiscalApiPaymentTransform; DocumentEditPolicy/DocumentUpdateRequest; ExportInvoice/ThirdPartyInvoice; HkaPayloadBuilder/Transport/Response/Mail/Pdf/A5Text/A5Layout; CurrentPdfRendering/TemplateContract; FiscalIdentity/DocumentNumberDisplay/DocumentFileName; DocumentFiscalAuthorization/ListColumns; TestDocumentsDeletionBehavior/Contract. Los tests que buscan texto en fuente se etiquetan “contrato fuente”; no prueban permisos HTTP integrales o clics de UI.

Ejecuta la suite necesaria para el caso y conserva resultados. No marques cientos de casos funcionales Aprobó porque una suite general pasó. El inventario de tests apoya trazabilidad, no sustituye ejecución por flujo.

## 12. Criterios de cierre y métricas del informe

SeaT el total planificado incluyendo extensiones/subcasos según el nivel de conteo declarado, A aprobadas, R reprobadas, B bloqueadas, N no aplicables y P pendientes. Debe cumplirse **T=A+R+B+N+P**. Ejecutadas=A+R. Aplicables=T−N. Cobertura de ejecución=(A+R)/(T−N). Tasa de aprobación=A/(A+R). Si denominador 0, “No calculable”, no 100 %.

No mezcles conteo de casos padre con subcasos. Presenta ambos niveles cuando existan variantes. Una prueba bloqueada es cobertura faltante, no prueba aprobada. Documenta cada exclusiónN y su evidencia. Añadir opciones/casos cambiaT; no mantengas porcentajes de una lista antigua.

Conclusiones posibles:

- **Aprobado en el alcance ejecutado:** aplicables ejecutados, matrices/opciones trazables, sin P0 fallido/bloqueado/pendiente, sin defectos críticos/altos abiertos, dinero/stock/identidad fiscal conciliados y aceptación DEMO que el alcance requiere acreditada.
- **Reprobado:** existe fallo que incumple un requisito de facturación. Enumerar defectos/impactos y no ocultar aprobaciones parciales.
- **Inconcluso:** faltan pruebas aplicables o evidencia esencial por entorno, módulos, permisos, receptor QA, assets o proveedor. Enumerar exactamente faltantes; no llamarlo aprobado.

Separar conclusiones por registro comercial, emisión fiscal DEMO, notas ordinarias y anulación, órdenes y relaciones, distribución y UI/API/seguridad. No certificar producción ni homologación completa desde DEMO. No usar límites conocidos como permiso para omitir casos de regresión de esos rechazos.

El informe final debe entregarse incluso cuando la corrida sea inconclusa. Completa la plantilla, enlaza el registro actualizado, casos adicionales, evidencia y bitácora de retest. Explica qué decisión permite tomar y qué falta probar.


## 13. Instrucciones detalladas de las funcionalidades nuevas

### 13.1. Fixtures y operaciones de preparación

Primero inventaría los perfiles existentes sin modificarlos. En tenant QA crea versiones desde los editores de empresa y cliente con U-ADMIN: usa vigencia 01/10/2026, notificación 30/09/2026, referencia QA-SPE y fundamento de facultad ISLR separado QA-ISLR. Para oráculos usa emisor ordinary no SPE excepto los casos IGTF/SPE; comprador agente IVA elegible bajo su fundamento y agente ISLR con base independiente. Las facultades no se completan automáticamente por ser SPE.

El RIF de perfil exige formato `V/J/E/G/C/R-########-#`, con prefijo concordante: natural V/E/R, jurídica J/C y pública G/J. No afirmar validación de dígito verificador ni inscripción SENIAT. La cédula/número doméstico conserva su campo propio. Firma personal sólo natural; clase pública sólo pública y obligatoria allí. Las seis clases son `national`, `state`, `municipal`, `autonomous_institute`, `decentralized_non_business`, `decentralized_business`. Para públicas específicas verificar fundamento/evidencia y contexto de la operación (`public_operation_scope=subject` y `public_scope_reference`), sin asumir facultad general SPE.

SPE exige notificación y fecha≤inicio de vigencia. Seleccionar versión por effective_from ≤fecha y desempate ID; fin de vigencia inclusive. Revocación deja SPE inactivo desde esa fecha, sin borrar fundamento público o ISLR independientes. Documento usa fecha de emisión; pago y comprobante sus propias fechas. Conservar IDs de versión y snapshots al crear historial nuevo; edición autorizada de comprador sigue usando fecha fiscal original. El perfil emisor debe coincidir con RIF empresa y actividad comercial=true. Sólo admin escribe versiones; endpoints generales no son una vía alternativa de escritura.

Rutas web de perfil: GET/POST `fiscal-profiles/{kind}/{id}` y POST `fiscal-profiles/evaluate`, con kind company/customer. Confirmar URL exacta y permisos contra rutas actuales; no suponer exposición API si no existe. Retenciones usan los contratos compartidos `documents/retention`: catálogo GET/concepts, propuesta POST/proposal, upload POST/upload-draft o/upload, consulta GET/{document}, registro POST raíz, adjunto GET/{id}/attachment, query POST/{id}/query-hka, retry POST/{id}/retry-hka, reverse POST/{id}/reverse. En API usar prefijo/autenticación real y rutas vigentes. Registro lleva tax_kind apropiado; acciones sobre un ID resuelven el impuesto del registro, no reinterpretan una bandera enviada por cliente.

Todo voucher requiere soporte PDF/JPG/PNG privado≤5 MB. Subir por interfaz/endpoint autorizado; anotar hash, token UUID, tenant, usuario y fecha de expiración 24 h. Registrar/consumir token una sola vez; descargar sólo con autorización y respuesta privada/no-store. Probar 1 MB y frontera 5 MB exacta en variantes individuales, además >5 MB, MIME adversario, token ajeno/consumido/vencido y rollback. La copia conserva el temporal cuando una transacción exterior falla; limpieza concurrente no puede eliminar soporte asociado a un comprobante consumido.

### 13.2. Regímenes: ejecutar la matriz y las ramas

QA-RGE-001 a 004 agrupan por moneda y titular los siete valores del selector, con evidencia por valor: Zonas económicas especiales, Zona franca de Paraguaná, Zona libre de Paraguaná, Puerto libre Santa Elena de Uairén, Zona Libre de Mérida, Puerto libre Estado Nueva Esparta y Dutty Free (nombre contractual exacto). No corregir la etiqueta del catálogo durante la corrida.

Para cada combinación abrir Comprobantes web, seleccionar Factura 01/0101, moneda VES/USD y categoría propia/terceros, elegir régimen y comprobar que no cambia IVA de productos. Registrar titular único distinto de comprador/emisor cuando sea terceros. Usar bienes y servicio con total 282 nominal (base gravada 200, IVA 32, exenta 50) y cobro real completo 282. Con tasa 50, fiscal USD→VES total 14100, IVA 1600, exenta 2500; el adaptador convierte una sola vez incluso cuando hay terceros+régimen. Datos locales no se reescriben.

Comprobar snapshot autoritativo ID/descripcion; HKA lleva descripción en regimenEspTributacion, nunca un ID como texto. Probar descuento sobre base, cargo según código, descuento global/distribución y IGTF inicial con emisor SPE. Agregar/cambiar/retirar régimen al editar sin control renueva UUID según política, conserva serie/número/moneda/categoría/pagos y saldo 0; requiere nuevo envío explícito. No guardar y luego asumir envío automático.

Para la factura con régimen, crédito, pago incompleto, exportación, tipos distintos 01, itinerante/conditional_data, transacción forzada y canales no habilitados se rechazan antes de efectos. Terceros+régimen se permite en factura web 0101/DEMO con cobro real completo. Las notas ordinarias domésticas sobre factura confirmada se admiten y heredan el régimen; sus restricciones se prueban por separado. Correo/PDF/ticket/listado se prueban con veredicto de presentación propio. A4 original HKA puede omitir régimen aunque el JSON lo incluya: conservar original por hash y reportar límite; A5/correo heredan esa omisión, ticket local debe identificarlo.

### 13.3. Propuesta, deuda y aplicación: cálculo obligatorio

La interfaz gestiona IVA e ISLR únicamente desde la acción **Retención** del listado `/documents`, sobre una factura guardada. Los formularios de creación, pagos y confirmación de Facturas, POS/Garage, Hotel, pedidos, servicio técnico y suscripciones no ofrecen previsiones ni carga de comprobantes. Los borradores y solicitudes UI eliminan los campos antiguos de retenciones sin modificar cobros, artículos ni tasas. El objetivo y la sugerencia de cobro de creación usan el total liquidable menos cobros reales, sin deducir previsiones. Los servicios y API conservan sus contratos independientes de propuesta y registro; probarlos como capas separadas.

| Momento en factura de crédito de 116 VES | Caja acumulada | IVA aplicado | ISLR aplicado | Deuda real / referencia de cobro |
|---|---:|---:|---:|---:|
| Sin pagos; propuestas API IVA 12 e ISLR 3 |0|0|0|116; las propuestas no reducen saldo ni sugieren 101 en la UI de creación|
| Pago real manual 101; sin comprobantes aplicados |101|0|0|15|
| IVA validada 12; ISLR pendiente |101|12|0|3|
| Ambas aplicadas 12+3 |101|12|3|0|
| Revertir ISLR con ausencia acreditada |101|12|0|3|
| Revertir ambas con ausencia acreditada |101|0|0|15; no crear otro cobro automáticamente|

Las propuestas del servicio son informativas y no aplican retenciones ni registran efectivo. En la creación de una factura 116 con abonos reales 30+20, el restante sugerido es 66, no 51 aunque un borrador antiguo contenga previsiones 12+3. Al recuperar o reabrir se conservan abonos, moneda, tasa, fecha y UUID, se eliminan los campos antiguos de retenciones de la solicitud UI y no se redistribuyen cobros. En el gestor del listado, probar respuestas de propuesta fuera de orden y cambios de documento o impuesto sin que una respuesta antigua sobrescriba la selección actual. Registrar por separado las pruebas HTTP/API y no asumir que un contrato de servicio sea un control de creación visible.

Un comprobante activo por impuesto/factura significa pending_validation o applied, incluso si applied está en reversal_pending. IVA+ISLR pueden coexistir; no sumar varios IVA o varios ISLR activos. invalid/reversed quedan en historial y permiten reemplazo con nuevo UUID conforme unicidad. Registrar exige UUID, soporte, fechas/importes/perfil. Factura digital exige confirmed+control y referencia coincidente para activar; sin ello queda pending_validation / applied_amount 0. Mismatch o validación tardía fallida deja invalid 0, con rollback/savepoint antes de preservar diagnóstico.

IVA 75 %: base comprobante es IVA 16, importe 12; base imponible 100 es otro campo. IVA 100 % exige fundamento y evidencia: undisclosed_iva,invoice_formalities,portal_100,unregistered_rif,precious_metals; público específico no admite precious_metals como ese fundamento. Probarlos por separado. IVA parcial una alícuota puede tener base IVA 8/importe 6/base imponible 50; en alícuotas mixtas sólo IVA completo sin desglose parcial. Con base 100 al 16%+base 100 al 8%:IVA 24, total 224, retención 75 %=18 y base imponible 200; parcial IVA 12 sin desglose se rechaza. Conservar cap fiscal VES congelado y tasa del comprobante; HKA convierte directamente comprobante→moneda fiscal, sin tasa hoy ni conversión doble.

ISLR: un concepto no salarial existente de tres dígitos, 001 excluido. Declarar concept_basis y evidence_reference no blancos≤255, porcentaje>0≤100, base positiva, sustraendo≥0, número no vacío≤50 y fecha emisión..hoy. Fórmula max(0, base×porcentaje/100−sustraendo); importe aplicado debe ser positivo y exacto tras precisión contractual. Base 100, 3 %, sustraendo 1 da 2, no 3. El catálogo no es motor de tasas; sus percentage_label históricos no completan cálculo. Cada concepto 002..086 tiene resultado y evidencia individual dentro del caso agrupado QA-RISLR-036, usando datos técnicos sintéticos para selector y cálculo; no se certifica la validez tributaria de una operación real desde una tasa QA.

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

IVA usa /api/AplicarRetencion; ausencia exitosa específica: HTTP 2 xx, codigo 203, mensaje exacto «Retención no encontrada», validaciones única «No se ha aplicado retención previamente por lo que no se puede consultar nada». También codigo 200/sin validaciones con infoRetencion null explícito, o identidad coincidente y totalesRetencion null, según adaptador. Un 203 genérico, JSON faltante, timeout o identidad contradictoria no acredita ausencia. GET/DELETE reciben JSON de identidad congelada, tipoDocumento 01/serie/número/control originales, sin número visible rellenado.

ISLR usa /api/AplicarRetencionISLR y outbox propia. Ausencia específica: HTTP 2 xx, codigo 204, mensaje exacto «No se ha registrado aún ninguna Retención de ISLR a este documento», validaciones vacías y sin infoRetencion ISLR registrada; también respuesta 200 sin validaciones con infoRetencion ISLR null explícito. 203 IVA no es ausencia ISLR. Payload detallesRetencion ISLR incluye numeroCompRetencion, fecha Emision CR, impuestoRetenido, rifAgenteRetencion, porcentaje y omite codigoOperacion. GET compara sólo siete campos:serie, tipoDocumento, numeroDocumento, numeroControl, numeroCompRetencion, fecha Emision CR, impuestoRetenido. No afirmar RIF, porcentaje, concepto o sustraendo remotamente verificados si GET no los retorna.

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

Completar las secciones específicas de regímenes, SPE, IVA, ISLR y coexistencia en la plantilla. Enumerar todos los IDs/variantes y conclusiones de las cuatro capas. Incluir matriz 28 y los 85 conceptos ISLR con resultado y evidencia por valor dentro del caso agrupado, perfil/versiones por fecha, saldo antes/después, cash por moneda y operaciones remotas por impuesto. Registrar bloqueo can_send y flags efectivos, los límites del original y la evidencia simulada/histórica en anexo separado. Todas las pruebas nuevas empiezan Pendiente; asignar Aprobó/Reprobó sólo tras ejecución atribuida y documentada. Entregar informe incluso Inconcluso, registro Excel actualizado, cálculos, evidencias y defectos/retest.

## 15. Ejecución de notas y órdenes: ampliación del 14 de octubre

### 15.1. Alcance, versión y preparación segura

Leer primero los contratos actuales de [notas](../notas_credito_debito_hka.md) y [órdenes](../ordenes_entrega_hka.md). Referencia confirmada 76690125 y archivos locales con hashes en fuentes. Registrar HEAD, diff, hash de assets realmente cargados y flags efectivos al comenzar; si varía un contrato, documentar diferencia y actualizar el esperado con fundamento antes de ejecutar. Las restricciones de notas de etapas anteriores quedan reemplazadas por la habilitación doméstica actual. La aceptación histórica no es resultado de esta corrida.

No realizar migraciones, resets, generadores sobre BBC ni compilaciones para seguir esta guía. El responsable del entorno proporciona fixtures y esquema QA apropiados; si falta, registrar Bloqueada. Los casos de concurrencia, fallos, reloj y flags de stock necesitan bases temporales propias aisladas y proveedor simulado; las pruebas fiscales positivas requieren evidencia Real DEMO propia. No tocar los motivos de stock en BBC: históricamente los cinco motivos tenían discount_stock=0. Una prueba con descuento activado necesita otro fixture configurado expresamente, nunca modificar el catálogo vivo para simular cobertura.

La nota de entrega HKA con indicador 0 está excluida: no crear selector, payload o pruebas positivas de ese subtipo. Toda orden de esta ampliación usa Pro 9 09/HKA04/esGuiaDespacho1. NC y ND son ajustes ordinarios distintos de notas de entrega y permanecen incluidas.

### 15.2. Fixtures concretos y condiciones comunes

| Fixture | Datos y finalidad |
|---|---|
| QA-A / QA-B | Tenants aislados, modalidad digital, ambiente demo, RIF del emisor y conexión propios. Nunca copiar JWT, contraseña o datos privados a evidencia. |
| S1/W1 y S2/W2 | Dos sucursales con almacén propio. G1 disponible 10 UND enW1 y 20 UND enW2, excepto cuando el caso declara otra línea base. Dirección válida por sucursal y origen adicional propio. |
| Usuarios | UADMIN, UVENTA S1 con módulo, USINPERMISO sin create_payment/delete_payment; otro usuario para caja ajena. Validar ACL reales antes de probar. |
| C1/C2/T1 | Clientes locales sintéticos activos, identidad fiscal válida y direcciones propias. T1 habilitado como titular. Emisor FORMAL vigente para matrices sin IGTF; casos sujetos requieren perfil SPE/moneda recibida/intermediación explícitos. |
| Factura F del caso | 01/0101 interna vigente, confirmed con control real coherente en documento/emisión; dos G1 UND, base neta 100 cada unidad, IVA16%, total 232; monedaVES/USD según ID. Pagada 232 con recibo real, sin fondos/retenciones/IGTF, salvo escenario específico. Cada caso tiene factura independiente. |
| Tasas QA | Factura USD50.00000000; NC usa tasa original; ND51.00000000 propia. Los casos de precisión usan tasas explícitas 8 dec; no son cotizaciones oficiales. |
| G1/GKG/SERV/PACK | G1 bien UND; GKG bien fraccionario; SERV sólo cargo ND y nunca devolución física 07; pack con snapshot 2G1+3G2 para pruebas NC, excluido de órdenes individuales. IVA y unidad activos, equivalencia HKA preservada. |
| Lotes/seriales | L1/L2 por producto/almacén, saldos propios; SN1/SN2 disponibles y trazables. Registrar sus estados y derechos originales antes de cada devolución/conversión. |
| Transporte | T1 transportista local autorizado, D1 conductor con identidad/licencia/contacto, V1 vehículo con marca/modelo/placa/TUC; registros del tenant. Públicos 01 exigen transportista; privado 02 conductor/vehículo; opcionales secundarios inventariados individualmente. |
| Órdenes | Motivos04/21/22/23/24 activos y textos exactos del catálogo, KG/TON; fecha emisión 14 oct y traslado 15 oct 2026. Motivo 04 destino otra sucursal/almacén propio; resto dirección del cliente. Motivo 21 descripción de reparación. Flagsstock/valorización según ID, precio VES116 bruto=100 neto+16IVA. |
| Finanzas | Caja abierta propia por usuario/sucursal/moneda, bancos activos S1VES/S1USD; método de pago activo no is_credit; UUID de cada recibo/egreso. Soporte de retención privado válido/token autorizado y comprobante fiscal real en pruebas de aplicación. |
| Fallos controlados | Doble HTTP con contador y transactionLevel, respuestas200/203/timeout/incompleta, reloj para límites30/40/50/90/backoff; dos procesos coordinados para races y respuestas tardías. Nunca fabricar el control de una operación Real DEMO. |

Si un caso sobre devolución no enumera otro fixture, usar F232 pagada, NC07 confirmada 116 que retornó 1 UND, sin ajustes ni reservas; destino caja abierta o banco compatible. Para anulación usar nota ordinaria confirmada sin cobros/devoluciones/ajustes activos y con bienes devueltos aún disponibles. Para ODI usar órdenes propias del caso, cantidades efectivamente ya trasladadas documentadas por Kardex; no atribuir una salida física a un simple estado visual.

### 15.3. Matrices y oráculos de notas

NHD agrupa las pruebas positivas NC01,07,04,09 y ND02,01,03 × VES/USD × propia/terceros; los regímenes null/1..7 son datos dentro de cada caso positivo. QA-NHD-225 concentra el rechazo común de contado y pagos iniciales en escenarios representativos. Consultar la tabla de equivalencias para los IDs anteriores. Cada combinación usa factura, nota y UUID propios; los ocho regímenes se registran como datos y evidencia del caso agrupado. El formulario se abre desde Comprobantes → Acciones → Nota; no crear notas libres sin factura afectada. Confirmar serie del tipo/sucursal, motivo activo, descripción y partidas originales. Verificar los nombres de regímenes contra snapshot, sin cambiar sus textos para superar rechazos.

NC01 cancela el importe comercial restante de la factura, no su emisión fiscal. Selección automática por derechos pendientes; NC07 devuelve sólo bienes originales seleccionados; NC04/09 acreditan diferencia monetaria. ND02 registra incremento por partida; ND01/03 cargo SERV con IVA autorizado. Sólo NC01/07 retornan inventario y lotes/seriales en almacén original; usar composición y presentación históricas incluso si el catálogo cambió.

| Nota | Base/IVA/total del fixture | Transacción HKA | Stock y dinero |
|---|---|---|---|
| NC01 |200/32/232|03|Retorna 2 UND; cash sin cambio hasta refund separado|
| NC07 |100/16/116|04|Retorna 1 UND; cash sin cambio|
| NC04/09 |10/1.60/11.60|04|Stock 0; cash 0|
| ND02/01/03 |10/1.60/11.60|02|Stock 0; deuda/cobros propios|

NC USD convierte copia fiscal usando tasa original 50: NC01=11600 VES, NC07=5800 VES, NC monetaria=580 VES. ND USD11.60×51=591.60 VES. La referencia conserva total232USD/tasa50/control del padre, nunca tasa 51 de la ND. Retener importes locales en moneda original. Calcular bases/IVA por grupos congelados y redondeo acumulado; sumar todos los créditos hasta agotar derechos y cotejar centavos con la factura original.

Las NC no tienen condición Crédito 02 ni pagos propios. HkaNotePayments declara 99 crédito comercial por el importe NC, sin crear cobros ni egresos. NDCrédito usa 99 por pendiente y conserva los abonos reales con fecha/moneda/tasa/UUID; Las ND ordinarias nuevas se crean a crédito sin cobros iniciales; los abonos se registran después. Las ND al contado preexistentes mantienen su política de cobro completo. No utilizar is_credit como entrada de caja. Si redondeos de abonos convertidos dejan un centavo, sólo el complemento fiscal 99 se ajusta; nunca recibos, tasas o saldo local. La nueva capacidad de notas no activa demo_net_collection_verified de IVA/ISLR.

### 15.4. Devoluciones, ajustes y saldo

Oráculo de factura: **F.total − NC activas − pagos activos − retenciones aplicadas − fondo ajustado + devoluciones principales activas**. ND conserva deuda propia, NC saldo cobrable 0, ND anulada saldo 0 en modelo y SQL. La aritmética de F puede ser negativa tras NC sobre factura pagada; no ocultarla con clamp 0 inventado. Ejemplo F232/pago232/NC116: saldo−116; refund 58→−58; refundtotal 116→0. Refund no altera ni borra el cobro original.

Registrar devolución sólo desde NC digital DEMO confirmed/controlcoincidente, vinculada a cobro real activo. Capturar principal, moneda recibida/devuelta, tasa 8 dec, fecha, medio, caja/banco, motivo, UUID/actor y hash. Límites por factura/NC/origen/reembolsos previos y reservas de impuestos/fondo. Ejemplo origen 30 impide devolver 30.01 aunque factura tenga otro pago 202. Dos devoluciones 80 compiten por NC116: sólo una 80 puede aplicarse. Reversión conserva historial y elimina el egreso activo una sola vez.

Para IVA/ISLR solicitar ajuste, conciliar reversión por el outbox específico y registrar nuevo comprobante real con soporte privado, referencia fiscal, fundamento y UUID. Durante uncertain la aplicación anterior sigue restando saldo; no sustituir con cálculo sin comprobante. IVA remanente 8 al 75%=6; ISLR base 50 al 3% con sustraendo 0.50=1.00. Facultades y fundamentos son independientes. Un activo por impuesto/factura, con historial. Fondo 10−reducción 5=5, sin cash. IGTF exige ajuste fiscal independiente confirmado; no usar ND98 como crédito negativo ni habilitar contrato faltante. Reservar su importe/tasa original mientras corresponda.

### 15.5. Anulación de la propia nota

Admin con motivo y UUID solicita flujo auditado. Distinguir NC01 por anulación comercial de factura de la anulación de esa NC. Prevalidar dependencias: pagos ND, refunds NC, ajustes activos bloquean; nunca se revierten automáticamente. Prevalidar almacén/composición/lotes/seriales; serial vendido nuevamente impide transmitir.

| Etapa | Comprobación obligatoria |
|---|---|
| Local sin transmisión |No control ni intentos y estado elegible, o ausencia acreditada con retry_allowed; no asumir que rejected/uncertain significa ausencia|
| pending/uncertain |Comercial 13 mantiene crédito y cantidades reservadas; identidad congelada y sin otroAnular ciego|
| rejected |Conservar efectos; diagnóstico y nueva acción según contrato, sin marcar completed|
| Conciliación |Listado paginado cotejaRIF/serie/control;201 vacío no prueba ausencia. EstadoDocumento200/Anulada exige UUID/tipo/número/control exactos|
| remote_confirmed |HKA canceló; reversión comercial aún pendiente si carrera impide stock. Emisióncancelled/comercial13; preservar intención|
| completed |Efectos locales revertidos una sola vez y comercial 11; ND sin deuda, NC retorno retirado exacto|

Completar reversión comercial hace sólo operación local, sin segundo Anular. Conservar control/número/payload/PDF/journal/historia y no reutilizar números. Verificar antiguas rutas de void/delete para que no omitan el flujo. HKA fuera de transacciones y bloqueos empresa→factura→nota→emisión en reclamaciones cortas. Cambios de contexto y respuestas tardías no aplican a otra operación.

### 15.6. Órdenes, inventario y relaciones

ODE001–040 corresponde a cinco motivos04/21/22/23/24 × transportepúblico01/privado02 × stock0/1 × valorización0/1, siempre indicador 1. Guardar desde módulo Órdenes de entrega. El servidor resuelve sucursal, cliente, direcciones, conductor/vehículo/transportista, bienes/unidades y catálogos permitidos; no aceptar snapshots arbitrarios. Peso positivo KG/TON, traslado no anterior a emisión, cantidad positiva hasta 4 dec y hasta 300 líneas. Público requiere transportista; privado conductor/vehículo. Motivo 04 usa destino establecimiento autorizado y 21 descripción de servicio. Catálogos condicionales y conductores/vehículos secundarios se inventarían con variantes propias.

Stock pertenece a almacén de la sucursal de la orden. Edición elegible restaura anteriores y aplica nuevas en una TX; motivoigual mantiene flag congelado. Pending/uncertain/confirmed o relacionada a factura bloquean edición. Rechazo corregible conserva número y renueva UUID/payload sólo mediante flujo autorizado. Replay propio de lote/serial ya consumido devuelve orden existente sin tratar su propio movimiento como consumo ajeno.

Sin valorización precioUnitario 0.0000/precioItem0.00; ignorar precio oculto. Con valorización VES dos unidades 116 bruto generan precioUnitario100/precioItem200/IVA32/valorTotalItem232. **Omitir encabezado.totales en ambos**, sin pagos ni impuestos comerciales de factura. Catálogo USD sin tasa documental no se convierte automáticamente. Preservar valores de la orden aunque cambien catálogos/configuración.

Conversión a factura conserva cliente/sucursal/referenciasoriginales y agrupa productos repetidos. Si cinco unidades ya trasladadas y factura siete, sólo dos generan salida; revertir factura retorna sólo esas dos. Derivaciones de pedido/NV/factura que ya movieron stock no repiten salida. Para trazabilidad exigir cantidadcompleta/una línea por producto/unidadoriginal; rechazar parciales, packs y mezcla de nuevas/yatrasladadas con lote/serial.

La referencia local puede existir con relation waiting. Fiscalmente source04/target01 requiere ambosconfirmed/configcoherente: consulta previa→asociación si ausencia→consulta posterior, y verificar también desde factura. Si relación exacta ya está presente, adopta sin POST. Ausencia real observada: HTTP200/codigo404/mensaje"Relación no encontrada"/validación única"Relación no encontrada en nuestra base de datos.". La implementación también permite ausencia con HTTP2xx/codigo200/sinvalidaciones y relacionados[] válida sin destino; probar esa rama con doble y distinguirla del contrato histórico observado. Timeout, JSON incompleto o 404 distinto no autorizan POST. Journal response.relation independiente de emisión; guard 40 s, hash/contexto/attempt_id evitan duplicados y respuestasviejas.

### 15.7. Rutas, estados y presentación

| Operación | Ruta web / contrato |
|---|---|
| Crear nota |POST /documents con note_creation_key y affected_document_item_id; formulario Comprobantes→Acciones→Nota|
| Devoluciones |GET/POST documents/{note}/refunds; POST documents/{note}/refunds/{refund}/reverse|
| Ajustes |POST documents/{note}/adjustments y documents/{note}/adjustments/{adjustment}/replacement|
| Anulación notas |POST documents/{note}/annul, query-annulment-hka, complete-annulment|
| Notas fiscal/distribución |Reutilizan endpoints documents existentes de enviar/consultar/PDF/correo; cotejar permisos vigentes|
| Orden emisión/consulta |POST dispatches/{id}/send-hka y query-hka|
| Orden relación |POST dispatches/{id}/relate-hka administrativo|
| Original orden |GET dispatches/{external_id}/download-hka-pdf/a4 privado/autorizado|

Inventariar rutas desplegadas y no ampliar API pública por analogía. Emisión protege pending 40 s y reenvío≥30 s con ausencia acreditada; correo 50 s segúnestado; retenciones 90 s propio y backoff según next_attempt_at. No compartir un temporizador universal.

Notas A4 íntegro, A5 vectorial horizontal y ticket QR verificado; correo a buzón QA separado de confirmación. Orden A4 original privado, sinA5/ticket/correoHKApositivo no habilitado. El PDF comercial local de orden es distinto. Omisiones de origen en PDF04 y régimen/leyendasSPE en original se registran; no retocar ni sustituir original. PDF inválido/HTML no entra al caché; cachépor tenant/operación, headersprivado/no-store/nosniff. FalloPDF/correo no cambia confirmed ni efectos comerciales.

### 15.8. Informe y resultados obligatorios

El ejecutor debe registrar Aprobó/Reprobó para cada prueba ejecutada, con observado/evidencia/defecto/agente/fecha Caracas/iteración/documentoUUID. Bloqueada/Noaplica requieren fundamento, no equivalen a aprobado. Cada caso activo tiene una fila en Resultados. En casos agrupados, registrar el veredicto y evidencia de cada valor en el anexo del caso; sólo crear una variante con ID propio cuando se compruebe una condición distinta. Casos y variantes conservan métricas separadas. Anexar corridas históricas y dobles separadamente de Real DEMO actual. Entregar informe aun Inconcluso, con conciliación independiente de saldos/efectivo/stock/fiscal/relaciones y seis secciones nuevas de la plantilla. No aprobar sistema conP0pendiente/bloqueado/fallido o defecto crítico/alto sinresolver.

## Casos agrupados: registro de comprobaciones

Los casos agrupados de identidades, conceptos ISLR, regímenes, clases fiscales equivalentes, fundamentos y formatos de soporte requieren registrar en su evidencia cada valor ensayado, su resultado y su identificador de operación. Esas comprobaciones no crean automáticamente una fila por opción en Resultados. El caso sólo se marca Aprobó cuando todas las comprobaciones aplicables cumplen; cualquier fallo lo hace Reprobó y las no realizadas requieren justificación. Las variantes adicionales se registran por separado sólo cuando sea necesario para documentar una condición distinta. Consultar las tablas de equivalencias de IDs históricos y usar la tabla de comprobaciones agrupadas de la plantilla del informe para registrar cada veredicto.

## Controles complementarios de medios de pago

Aplican a los casos MPA indicados y forman parte de su criterio de aceptación. Registrar datos, resultado y evidencia de cada control dentro del caso; no tratar un enlace a una prueba automatizada como una ejecución de la corrida.

- **MPA-002 y MPA-003:** comprobar que la disponibilidad de un método nuevo la determina el servidor aunque el cliente envíe otro valor; los métodos sin clasificar no se activan. Editar o activar en paralelo y comprobar que la clasificación se relee bajo bloqueo. Verificar que los selectores de abonos excluyen crédito y no clasificados y que el destino bancario se puede elegir donde el formulario lo permite, sin inferir su naturaleza desde el ID.
- **MPA-004 y MPA-005:** probar conversión VES→USD además de USD→VES, tasas ausentes o no positivas, moneda de cuenta incompatible y edición de un recibo existente. El recibo histórico conserva moneda, importe, tasa y destino; una selección nueva incompatible se limpia sin modificar registros anteriores.
- **MPA-006 y MPA-007:** probar cobro mixto y vuelto imposible de expresar en céntimos de la moneda recibida. El exceso bancario no se transforma en efectivo y un recibo antiguo no financia un vuelto nuevo.
- **MPA-008, MPA-009 y MPA-016:** verificar aplicación simultánea del mismo recibo, eliminación concurrente de un recibo y conversión de varias notas; sólo una operación puede consumir el derecho y no se omite ningún abono obligatorio. Verificar rollback de servicio técnico cuando falla la creación del documento.
- **MPA-010 y MPA-020:** comparar caja, banco, listado y archivos PDF/XLSX por moneda; revisar apertura, pagos y reversión sin doble conteo. Comprobar nombres distintos de archivos y errores de exportación. Cuando el reporte exponga numeradores y denominadores exactos, cotejarlos con los importes presentados.
- **MPA-011 y MPA-019:** probar catálogo de Hotel, estado de los recibos, reintento concurrente y actualización móvil tardía. La evidencia del servidor y la de interfaz son capas distintas; no atribuir soporte al compilado de Mozo ni a clientes externos sin ejecutarlos.
- **MPA-012:** probar prevalidación de todas las filas del importador, creación automática a crédito sin efectivo ficticio, rollback cuando falla la generación de suscripción y confirmación repetida de WhatsApp con emisor simulado. Un borrador anterior que requiera nueva confirmación no debe generar cobros con precios o tasas obsoletos.
- **MPA-013 y MPA-014:** comprobar moneda propia y destino de pagos a proveedores y préstamos, preservación del pago tras editar y diferencia entre abono real y cuota. Incluir el cálculo con fondo de garantía cuando aplique; no confundir pago de egreso con cobro recibido.
- **MPA-017 y MPA-018:** comprobar errores generales e indexados sin asignarlos a una fila equivocada, apertura de Pagos con tasa inválida, unicidad de caja abierta y presencia del turno en el listado paginado.
- **MPA-021 a MPA-023:** mantener independientes los catálogos de gastos y medios, comprobar unidad de peso fraccionaria en el endpoint disponible y verificar pedido con precio sin IVA. La evidencia de un endpoint móvil no demuestra que el cliente móvil externo haya sido adaptado.

## Conciliación de numeración de NC

Complementar QA-HKA-011 y las pruebas de recuperación/anulación de notas con un doble HKA que permita controlar ambas consultas. En una NC ordinaria 07/02 digital DEMO con transmisión incierta, consultar primero por UUID; cuando se acredite ausencia, consultar EstadoDocumento por tipo, serie y número antes de permitir reenvío. Usar fixtures separados para estas respuestas:

1. **Ocupado por otra operación:** control válido e identidad coincidente, UUID remoto distinto, tanto vigente como Anulada. Esperar `numbering_conflict=true`, `status=uncertain`, diagnóstico visible y Enviar HKA deshabilitado en cliente y servidor. La serie se compara cuando HKA la incluye; su omisión no equivale automáticamente a una contradicción. No adoptar el control ajeno.
2. **Ausencia en ambas consultas:** conservar UUID, número y payload; el reenvío sólo puede habilitarse tras el umbral de 30 segundos y los demás controles de ausencia.
3. **Respuesta incompleta, identidad contradictoria o error de conexión:** bloquear el reenvío y conservar incertidumbre, sin afirmar que existe duplicidad.
4. **Recuperación posterior de la operación propia:** confirmar sólo con identidad y control coherentes y desactivar el conflicto; una falla posterior no debe borrar la evidencia anterior. Encontrar la operación propia invalida la ausencia usada para anulación local.

Registrar las dos respuestas saneadas, UUID/intento y hash de la transacción ajena; no copiar su contenido privado. Comprobar que consultas y fallos no cambian importes, inventario ni numeración. La anulación local puede usar ausencia acreditada de la operación actual aun con reenvío bloqueado, pero sigue sujeta a las dependencias de devoluciones y ajustes. Las pruebas históricas de BBC no son resultados de la nueva corrida.

Para **QA-MPA-002**, incluir explícitamente estas comprobaciones de administración: crear el código personalizado 27 clasificado `account`/VES y verificar que queda activo; repetir su creación por las rutas web y JSON y comprobar rechazo 422 sin cambiar la fila original. Editar su descripción conservando ID 27; intentar cambiar el ID a 28 debe rechazarse y editar un ID inexistente debe devolver no encontrado. En un método inicial, probar edición a efectivo USD: `cash_currency_type_id` debe sincronizarse a USD y el código HKA original debe permanecer inmutable aunque el cliente envíe otro. Verificar en solicitudes independientes la validación de moneda e IGTF opcional y los permisos de activación/eliminación. Guardar respuesta y lectura posterior de cada comprobación; no sumar una fila del catálogo de casos por cada valor equivalente.

Para **MPA-013 y MPA-014**, añadir a la evidencia estos controles del chat de origen: en una compra de USD 1,00 a crédito con abono USD 0,25, la cuota y el saldo son USD 0,75; al retirar el abono vuelven a USD 1,00. Una sugerencia automática de contado que nadie modificó se retira al cambiar a crédito, mientras un abono introducido o modificado por el usuario se conserva. Editar y volver a guardar debe preservar la cuota, el abono y un único movimiento. Comprobar que 0,10 + 0,20 = 0,30 con aritmética decimal exacta y que se rechaza un exceso real. Con varias cuotas, cambiar el método o plazo de una no debe cambiar las fechas de las demás; plazo cero conserva la fecha manual. Las liquidaciones de compras quedan fuera de este alcance.

Para **MPA-008 y MPA-012**, comprobar que la copia de cotización no hereda cobros ni anticipos y que la cadena original conserva el movimiento único. En WhatsApp, si ocurre un fallo al entregar el PDF después de guardar, informar que la factura quedó guardada; un reintento del mismo borrador no crea otra factura, pago ni movimiento. El caso debe usar emisor simulado y distinguir un fallo anterior al commit, que sí revierte los efectos.

Controles adicionales de los recorridos históricos de pagos, para ejecutar con fixtures propios:

- En las conversiones desde cotización y en ambas variantes de suscripción, comprobar que las cuotas existentes se cargan con fecha, importe y método y se crean con identidad propia en destino. Alternar contado/crédito sin borrar cuotas editadas ni abonos; al contado no se transmiten cuotas. Comprobar que no se agrega un pago que el diálogo no muestra.
- En servicio técnico, cotización, factura, nota, POS y Garage, un recibo histórico identificado por ID o por origen conserva método, destino, fecha, moneda e importe y no ofrece eliminarlo o editarlo. Si el destino ya no aparece en el catálogo activo, debe mantenerse la presentación histórica. Probar la variante sin ID con un borrador sintético y no presentarla como recorrido real del POS cuando no exista productor identificado.
- En MPA-019, verificar las respuestas HTTP 401 sin autenticación y 409 al intentar actualizar un cobro ya contabilizado; conservar pago, vínculo y snapshot. La prueba de concurrencia se ejecuta por separado del cliente móvil externo.
- En MPA-010 y MPA-020, comparar el reporte general con el filtrado de efectivo: el segundo excluye banco, y el resumen de ingresos excluye egresos. El saldo físico corresponde al turno completo. Abrir PDF y XLSX y verificar naturaleza, moneda y nombre históricos; una fracción USD 6,05 equivale a 121/20. Comprobar las hojas de fracciones cuando el formato las incluya.
- En MPA-006 y MPA-007, registrar efectivo 0,40 VES, vuelto 0,10 y aplicado 0,30; al revertir dos veces debe conservarse un solo historial y retirarse únicamente el neto de caja. Usar aritmética decimal exacta para comparar fracciones.
- En MPA-022, añadir un fixture de factura de 0,3 KG a USD 10/KG y tasa 12,5 VES/USD: la base es 37,50 VES antes de impuestos, la equivalencia HKA es KGM y la cantidad no se redondea a entero. Consultar KG y TON en el endpoint móvil disponible sin atribuir el resultado al cliente externo.

Para completar el contraste con los recorridos anteriores del chat de pagos:

- En **MPA-016**, repetir la conversión múltiple con dos recibos USD a tasa histórica 100 y tasa documental distinta; la respuesta asíncrona de tasa actual no debe sustituir la tasa restaurada. Rechazar total o parcialmente la omisión de recibos y un abono registrado después de preparar la selección; preservar pagos y movimientos originales sin duplicar ingresos.
- En **MPA-003 y MPA-018**, al faltar una tasa válida o efectivo compatible, conservar los importes introducidos, bloquear cálculos y pago que dependan de la tasa y permitir reintentar. Ignorar una respuesta antigua que llegue después de restaurar el borrador. Al faltar efectivo de una moneda, la apertura positiva de esa moneda se rechaza atómicamente; una apertura cero no requiere ese método.
- En **MPA-014 y RET**, con factura 232 VES, abono bancario 5 USD a tasa 10 y retención IVA aplicada 24 VES, calcular 232−50−24=158 VES; al revertir la retención, saldo 182 VES. Repetir la operación no duplica ingresos y una reclasificación posterior no altera el snapshot bancario USD. Identificar el doble utilizado y mantener las capacidades netas HKA según el contrato vigente.
- En **MPA-014, PDF y reportes**, comprobar condición de crédito con cuotas y abonos juntos en A4, A5 y tickets aplicables, mostrando moneda, vencimiento, recibido y aplicado. Una muestra de ticket que ocupa una página no acredita cualquier longitud de contenido ni la paginación dinámica de otros formatos.

Más controles del historial de pagos:

- **MPA-010/020, Movimientos:** crear registros con fechas distintas y uno registrado después con fecha anterior. El acumulado del informe usa orden de registro y separa VES/USD; probar ordenación manual ascendente y descendente sobre todas las páginas, sin fechas inválidas ni cambio de importes. Un fallo de página descarta la carga parcial y muestra aviso. En exportación encolada, un fallo de generación o almacenamiento marca la bandeja `FAILED`, propaga error y permite reintento; dos exportaciones conservan archivos con nombres distintos.
- **MPA-010/014 y reportes de venta:** usar documentos de 232 VES y 77,77 USD, notas de crédito vigentes y documentos anulados/rechazados. Comparar pantalla, PDF y XLSX: totales separados por moneda, NC restada y documentos no vigentes excluidos. Un cobro reutilizado se identifica como previo; la aplicación IGTF no figura como dinero recibido otra vez. Comprobar detalle de cuotas y abonos, además de encabezados y fracciones exactas cuando estén disponibles.
- **MPA-012, suscripción clásica:** con almacenamiento temporal controlado, generar dos notas y forzar fallo del segundo PDF. El lote se revierte, se limpian archivos nuevos y se preserva o restaura un archivo previo con el mismo nombre. En éxito verificar PDF válido y ausencia de cobros ficticios. Repetir edición y edición concurrente del plan sin duplicar notas existentes. No ejecutar estas pruebas sobre archivos reales del cliente.

## Controles adicionales: reportes y distribución de cuotas del chat de pagos

Estos controles forman parte de los criterios de los casos indicados. Preparar una operación aislada para cada control, conservar el cálculo independiente y anotar su resultado y evidencia dentro del caso; la evidencia del chat es histórica y no equivale a una ejecución de esta corrida.

1. **QA-MPA-006 / QA-MPA-010 / QA-MPA-020 — recibido, vuelto y neto en reportes.** Con deuda de 232 VES y tasa 10 VES/USD, recibir en efectivo 30 USD y devolver 6,80 USD. Verificar en el historial y reporte que se distingan recibido 30 USD, vuelto 6,80 USD y aplicado 232 VES; neto físico 23,20 USD. Comparar la representación en pantalla y en los archivos disponibles con los registros financieros. Para un registro histórico preparado sin información suficiente del vuelto, no inventar el bruto ni el vuelto: documentar la representación disponible y comprobar el importe neto. Identificar este segundo fixture como legado.
2. **QA-MPA-014 — conversión inversa y reparto de centavos.** Crear una factura a crédito de 232 USD con un abono bancario de 50,10 VES a tasa 10 VES/USD. El cálculo independiente es 50,10/10 = 5,01 USD aplicados y 232−5,01 = 226,99 USD pendientes. Repartir el saldo entre dos cuotas de 113,50 y 113,49 USD; verificar que sumen exactamente 226,99, no generen un segundo ingreso y conserven fecha y tasa del cobro. Rechazar una cuota negativa sin crear registros parciales; probar por separado saldo totalmente pagado y cuota cero según el formulario y servidor vigentes.
3. **QA-MPA-005 / QA-MPA-010 / QA-MPA-020 — exportaciones contables.** Con una factura y un cobro guardado como bancario, conservar la cuenta y el snapshot; reclasificar después el mismo método como efectivo. Configurar en un tenant de prueba las cuentas contables necesarias y generar EJB y Contasis cuando estén disponibles. Abrir ambos XLSX y cotejar la cuenta y clasificación con el cobro original: EJB debe usar la cuenta bancaria configurada y Contasis no debe tratar ese cobro como efectivo por la clasificación nueva. Registrar archivos, parámetros, posiciones verificadas y cambios de catálogo; probar la descarga HTTP por separado del generador, sin deducirla de la mera apertura del archivo. La condición de contado simulada en la prueba histórica no acredita todas las condiciones contables.
4. **QA-MPA-005 / QA-MPA-014 / QA-MPA-020 — documentos y artículos.** Con un abono de 5 USD aplicado como 50 VES a tasa 10, una cuota de 182 VES y método reclasificado posteriormente, comparar el detalle en el recurso de reportes, pantalla, PDF, XLSX de documentos, artículos y estado de cuenta que estén habilitados. Comprobar nombre histórico, recibido USD, aplicado VES y separación entre abono y cuota; no sustituir los snapshots por la descripción actual. Abrir cada archivo y anotar formato y páginas; si alguna vista, endpoint o formato no está habilitado, documentar su motivo y alcance sin atribuirle el resultado de otra capa.
5. **QA-MPA-014 / QA-MPA-020 — formatos y longitud.** En los formatos de factura y nota de venta configurados para el tenant, incluir condición de crédito, cobros y cuotas, y examinar A4/A5 y tickets disponibles. Añadir un fixture con tres cobros y referencias largas para comprobar cortes, legibilidad y paginación del generador real. Enumerar los formatos efectivamente inspeccionados; un PDF renderizado, una sola muestra o una prueba de plantilla con altura fija no acredita impresión física ni todos los tamaños y longitudes.

Controles complementarios para los mismos casos:

- **QA-MPA-003 / QA-MPA-004 / QA-MPA-014:** con documento VES 100, tasa 3 VES/USD y propuesta de 33,33 USD, comparar interfaz y servidor: aplicado 99,99 VES y saldo 0,01 VES. No dar por pagado el centavo restante ni reconstruir una tasa con redondeo. En POS, pago rápido y Garage modificar monto, moneda y tasa en una fila nueva y comprobar que se actualizan aplicado y faltante; en pagos ya registrados o múltiples, el campo rápido no sobrescribe otras filas. Preparar fixture independiente para cada canal habilitado.
- **QA-MPA-005 / QA-MPA-020:** preparar un documento histórico sin importe recibido o con tasa ausente/inválida. Verificar que el documento y reporte identifican el importe aplicado en moneda documental, señalan los datos ausentes y no inventan recibido ni tasa; en XLSX los campos ausentes se dejan vacíos donde corresponda, no como cero. Contrastar con un registro moderno completo para no confundir ausencia histórica con un importe real cero.
- **QA-MPA-009 / QA-RET-001 y siguientes:** con factura fiscal confirmada y saldo limitado, sincronizar en fixtures separados un cobro frente a una retención IVA y otro frente a ISLR. La transacción posterior debe releer el saldo y no aplicar importes cuyo conjunto lo exceda; comprobar rollback y estado final. Usar dobles para las respuestas remotas, con HKA DEMO y capacidades vigentes documentadas; no se infiere que el cobro neto esté habilitado.
- **QA-MPA-008 / QA-MPA-010:** en cotización → nota de venta → factura, identificar el abono como previo, conservar su turno de caja de origen y comprobar que ninguna aplicación derivada cree ni vuelva a asociar un ingreso. En PDF de cotización A4, A5 y ticket disponibles, comprobar recibido, aplicado y tasa, además de la copia sin pagos. Comparar cada formato por separado y mantener el alcance de la evidencia.
- **QA-MPA-012 / QA-MPA-013:** comprobar el productor de tienda y, sólo mediante simulación aislada, el enlace de pago cuando falta una configuración explícita: seleccionar efectivo compatible por clasificación activa, no por ID; cuando existe configuración explícita válida, conservarla. Si el enlace usa un método bancario, su preparación no crea por sí sola un cobro de caja. En compras y préstamos, filtrar cuenta según moneda del egreso y conservar los datos históricos al editar; un pago cero se rechaza. Las liquidaciones de compras siguen fuera del alcance.
- **QA-MPA-005 / QA-MPA-010:** filtrar cuentas por cobrar incluyendo métodos personalizados, reclasificados e inactivos con movimientos históricos; esto no habilita métodos inactivos para cobros nuevos. Comparar el filtro de caja o devolución con la clasificación y turno guardados en el movimiento, y la condición de crédito con la condición comercial guardada, aunque el ID del método se haya reclasificado.
- **QA-MPA-009 / QA-MPA-015 / QA-MPA-020:** usar una cuenta con saldo inicial o movimientos y comprobar que el servidor rechaza cambiar su moneda; repetir la competencia entre edición de cuenta y cobro para asegurar que no se confirme un movimiento con moneda incompatible. En el selector de origen de traslados, contrastar el saldo con los movimientos clasificados de esa moneda, incluidos los de Hotel y las salidas, y excluir importes en otras monedas. Para un movimiento legado sin snapshot suficiente, comprobar que el reporte indique importe/saldo no disponible y lo excluya de los totales en vez de asignarle una moneda supuesta.

**Límite de enlaces y pasarelas:** el chat de origen dejó fuera de operación las pasarelas por instrucción del usuario y no resolvió el uso de enlaces con confirmación manual. Los controles de clasificación del enlace anteriores son de código/servidor en entorno aislado con dobles; no iniciar una pasarela, publicar enlaces ni registrar como exitosa una confirmación pública no verificada. Si el canal no está autorizado o no puede verificarse de forma aislada, registrar Bloqueada/No aplica y su fundamento.

### Conservación del formulario y de sugerencias

En **QA-MPA-002/003/013/014/017**, ejecutar con datos aislados: crear o editar método con código duplicado y confirmar que el error del servidor permanece visible sin cerrar el formulario; al editar, conservar la disponibilidad cargada y derivar las etiquetas de efectivo/crédito de la clasificación vigente. Al modificar líneas, cantidad, descuentos y cargos de una venta, la sugerencia automática de un cobro nuevo sigue el saldo; una edición manual, incluido cero mientras se edita, no debe ser sobreescrita por una actualización posterior. El servidor conserva sus validaciones para importes inválidos al guardar.

En nota de venta, cambiar la fecha documental no altera la fecha de cobros registrados. Forzar un rechazo y comprobar que permanecen moneda, importe recibido, tasa, filas y cuotas del borrador; tras éxito verificar el reinicio según configuración. Alternar contado/crédito con una copia enviada al servidor: conservar el borrador, enviar cuotas sólo en crédito y no duplicar abonos. En compras, validar fracciones exactas, pagos inválidos/excesivos y descuentos/cargos en la edición; conservar cuotas guardadas y proponer sólo el saldo pendiente. Documentar cada canal ejecutado y la respuesta de servidor, sin considerar una prueba de fuente de interfaz como aceptación visual.

En **QA-NHD-351**, el intento de crear ND al contado comprueba el bloqueo de condición y no demuestra por sí solo la validación del método: la creación ordinaria se rechaza antes de llegar al cobro. Para probar la exclusión del método crédito como ingreso, utilizar el circuito de abonos posterior permitido por el contrato y controles de QA-MPA-002, con una ND a crédito existente; registrar ambas respuestas por separado y no atribuir el primer rechazo al método.

Para los casos agrupados de clases SPE y fundamentos IVA, ejecutar cada valor y guardar veredicto y evidencia propios. La clase `decentralized_business` conserva QA-SPE-044 separado porque usa fundamento fiscal diferente. Los soportes PDF/JPG/PNG se comprueban una vez por formato dentro del caso de cada impuesto; IVA e ISLR mantienen circuitos separados. Ver [equivalencias de opciones fiscales](equivalencias_opciones_fiscales.md).

### Controles de volumen, consultas y caja de pagos

- **QA-MPA-004:** en una base aislada activar registro de consultas y consultar destinos con una cuenta bancaria VES y luego con dos cuentas VES/USD del mismo banco. Verificar las dos monedas, el nombre bancario y la exclusión de una cuenta inactiva. En la prueba de la capa de datos, la consulta de nombres a `banks` se mantiene en una para ambos tamaños; registrar el número de consultas y la capa medida, sin atribuirlo al tiempo de respuesta HTTP completo.
- **QA-MPA-010:** si se dispone de un entorno MySQL temporal apropiado, ejecutar la comparación de rendimiento histórica con 1.000 y 10.000 ingresos de 0,01 VES, cinco mediciones del reporte clasificado por volumen y una medición del comparador legado de pruebas. El ingreso y saldo deben ser exactamente 10 y 100 VES respectivamente y coincidir con el comparador. Ordenar las cinco mediciones; la mediana es la tercera y el máximo observado se usa como p95 de esa muestra pequeña. El umbral de esta prueba es máximo clasificado ≤ tiempo legado + máximo(20 % del tiempo legado, 100 ms); registrar tiempos, consultas, memoria, hardware y carga. Esta comprobación cubre datos PHP/MySQL, no HTTP ni render PDF. Si no existe entorno aislado adecuado, marcar este control Bloqueado con motivo y no ejecutar cargas masivas en BBC ni producción; un resultado dentro del umbral no establece una garantía general de rendimiento.
- **QA-MPA-011 / QA-MPA-018:** con usuario sin caja abierta intentar crear la nota de venta que necesita caja. Verificar rechazo con mensaje comprensible y rollback de nota, pagos y movimientos. Abrir después una caja válida en un fixture separado y comprobar el control positivo. No confundir la ausencia de caja con un error de moneda.

### Conversión por canal y tasa documental

En **QA-MPA-008/014/016/023**, preparar por separado pedido, servicio técnico y orden de entrega para convertirlos a factura cuando su canal esté disponible. Identificar pagos nuevos y recibos históricos o identificados por origen: los últimos se muestran sin permitir borrarlos o modificar moneda, importe, fecha, destino ni tasa. Los pagos nuevos conservan el importe recibido y su tasa; el borrador de pedido no registra ingreso antes de facturar. La conversión rechaza IDs históricos no verificados y no duplica el movimiento al reutilizar un cobro real.

En la conversión de orden de entrega, utilizar únicamente la orden Pro9 09 / guía HKA 04 indicador 1. Comparar el precio fuente, IVA de cada partida y precio convertido usando la tasa documental válida; rechazar una tasa ausente o no positiva. Una edición manual del importe sugerido se conserva al cambiar a crédito; una sugerencia de efectivo sin tocar no se convierte en pago real. En crédito se envían abonos y cuotas aplicables al saldo; en contado no se transmiten cuotas. Registrar capturas, solicitudes saneadas, cálculos, saldos y movimientos antes/después para cada canal ejecutado.

En **QA-MPA-011**, durante check-in de Hotel obtener tasa válida y conservar su precisión; en el cierre sumar saldos con decimales exactos y añadir mora una sola vez incluso al reintentar. El cambio de método o moneda en un producto nuevo limpia un destino incompatible sin cambiar productos o cobros ya registrados. Guardar y consultar el resultado de Hotel por separado de la API de restaurante y del cliente Mozo externo.

### Aceptación HTTP de productores de cobro

Para **QA-MPA-004/005/008/011/013**, además de la interfaz, repetir con sesión y CSRF de un usuario autorizado las rutas reales disponibles de ingresos, cotizaciones, servicio técnico, contratos, Hotel, nota de venta, factura y préstamo. Usar métodos de prueba reclasificados y cuentas de ambas monedas; anotar ruta, respuesta y lecturas antes/después por productor. Comprobar de forma independiente:

1. Para un cobro bancario USD de documento VES, rechazar cuenta VES y aceptar cuenta USD; a tasa 100, USD 1 recibido aplica 100 VES. Una moneda o método inactivo, una cuenta incompatible o un destino ausente no deja registros parciales.
2. En servicio técnico USD, probar también el sentido inverso: 100 VES a tasa 100 aplican 1 USD en cuenta VES. La consulta conserva la moneda real del servicio y el snapshot del cobro.
3. En creación y edición de contrato, conservar pago, importe recibido, tasa y snapshot tras reclasificar el método; intentar modificar un pago ya registrado y comprobar rechazo sin alterar el anterior.
4. Para factura, repetir la clave del cobro incluyendo adjunto PDF de prueba en almacenamiento tenant aislado: un pago, un movimiento y un adjunto; un reintento contradictorio no modifica el original. Para cotización, servicio técnico y nota, comprobar además su respectivo vínculo de origen y que una conversión no produzca otro ingreso.
5. En préstamos, usar moneda del egreso para filtrar la cuenta, exigir método bancario activo y rechazar cero, valores negativos, efectivo o destino omitido. En Hotel, reintentar la misma operación de producto y el cierre después del cobro sin duplicar el ingreso. Los errores no borran el formulario ni datos previamente confirmados.

Una llamada directa a un controlador, una prueba unitaria o una respuesta de un doble debe identificarse como tal y no sustituye la prueba HTTP con autenticación, la comprobación visual o la aceptación HKA DEMO. Si una ruta o canal no está habilitado en el despliegue, documentar la razón y el estado correspondiente.

En **QA-MPA-001/002**, revisar el esquema inicial y la pantalla de administración de medios: `payment_method_types.charge` no existe, no se ofrecen «Solicitar datos de tarjeta», `has_card` ni `card_brand_id` en los pagos comerciales tenant y no se exige un catálogo de marcas para registrar el cobro. Los métodos de tarjeta siguen presentes como medios clasificados y se prueban por sus reglas de cuenta/moneda; no confundir su conservación con la retirada de campos de tarjeta. Verificar el esquema en una instalación temporal nueva y la pantalla en el despliegue probado, registrando cada capa por separado.

En **QA-ODE-055**, con dos clientes y direcciones distintas, seleccionar la dirección principal disponible del primero y cambiar de cliente o motivo antes de que termine una consulta de direcciones. Una respuesta tardía no debe reinstalar la dirección del cliente anterior; un error de carga limpia opciones obsoletas, muestra el fallo y libera el indicador de carga. Probar por separado una dirección de otro cliente por HTTP y conservar evidencia por escenario. Este control corresponde a órdenes de entrega y no habilita notas de entrega con indicador 0.

### Cuentas por cobrar y registros legados

Para **QA-MPA-010/014/020** y los casos FIN correspondientes, preparar factura de 500 VES con abono activo 100, nota de crédito vigente 100, retención aplicada 75, fondo de garantía 50, ajuste de nota 20 y devolución real activa 10. Incluir cobros y devoluciones ya revertidos en fixtures distintos. El saldo esperado del conjunto de prueba es 500−100−100−75−50+20+10 = 205 VES; los importes revertidos no se vuelven a sumar ni crean movimientos. Cotejar detalle, resumen, páginas, PDF y XLSX disponibles con las mismas filas y estados, sin atribuir automáticamente un egreso a la NC.

Probar el mismo reporte con documentos de dos clientes y vendedores: el filtro debe respetar los permisos efectivos y el cliente solicitado, y excluir notas de venta convertidas según el contrato del reporte. Usar además un saldo exacto positivo de 1/10.000.000.000.000 VES cuya presentación pueda redondearse a cero; comprobar en la capa exacta que no se trate como saldado ni se descarte por el redondeo visual. Las comprobaciones de volumen se hacen sólo en base temporal.

En **QA-MPA-014**, conservar por separado un fixture legado de nota sin condición de pago: el servicio de compatibilidad utiliza la clasificación del método relacionado cuando no existe condición guardada; no debe guiarse por indicadores obsoletos contradictorios. En documentos con condición guardada, ésta prevalece aunque se reclasifique el método. Identificar expresamente el fixture legado y no extrapolarlo a nuevas instalaciones o a un movimiento con snapshot histórico completo.

En **QA-MPA-019**, además de captura móvil, comprobar el resumen de movimientos del endpoint disponible con 4.000 VES de ingreso, 10 USD de ingreso y 1/3 USD de egreso: los saldos son 4.000 VES y 29/3 USD, sin sumar monedas. El campo escalar heredado sólo se informa cuando la información es inequívocamente VES; con monedas mixtas o movimiento sin clasificar debe quedar no disponible. Registrar si la evidencia es una prueba de servicio o una respuesta HTTP; no atribuirla a un cliente móvil externo.

### Reutilización por HTTP y selección de partidas

En **QA-MPA-008/016**, preparar cotización con un cobro real de USD 1 a tasa 100, aplicado como 100 VES, y capturar los conteos de notas, facturas, pagos y movimientos. En solicitudes independientes a conversión de nota web, nota API y factura, omitir deliberadamente el cobro obligatorio. La operación debe rechazarse sin cambios en conteos ni snapshots: la ruta web de nota puede responder HTTP 200 con `success=false`, mientras las rutas API y factura responden 422 según el contrato vigente; evaluar el contenido de negocio además del código HTTP. Consultar los pagos de origen, reclasificar y desactivar después el método, y convertir enviando el recibo histórico. La nota conserva USD 1, 100 VES aplicados y origen; el movimiento original no se duplica. Un segundo intento de consumir la misma cotización se rechaza sin segunda nota. Registrar peticiones y respuestas saneadas por ruta y operación.

Cuando se verifique el rendimiento de selección de partidas de nota, usar sólo un entorno HTTP/MySQL temporal y el modo de medición expresamente configurado. Preparar por separado notas con 1, 100 y 1.000 partidas; realizar tres ejecuciones de calentamiento y 30 mediciones, comprobar el número exacto de partidas devueltas y registrar p50, p95, consultas y memoria. Si existe una implementación de referencia válida, el criterio histórico es p95 actual ≤ p95 de referencia + máximo(20 % de referencia, 100 ms). Identificar fuentes y versiones y comprobar que no cambien durante la medición. El resultado cubre esa ruta de selección, no equivale a un indicador general del POS, caja o facturación. Sin entorno aislado o referencia apropiada, registrar el control como Bloqueado o sin comparación, según corresponda; nunca generar el volumen en BBC ni en producción.

### Precisión y transacciones de cobro

En **QA-PAG-012 y QA-MPA-009**, distinguir el cálculo de cobros fiscales de factura de la validación exacta de nota de venta: para el flujo fiscal, un importe recibido `0,001` que se liquida a cero centavos se rechaza; el criterio no implica que todo decimal con más de dos posiciones sea inválido en todos los canales. En el validador exacto de nota de venta, `1,004` transmitido como texto decimal pasa la validación de formato; cero, negativos, texto no numérico, valores fuera del rango/precisión y flotantes binarios rechazados por la ruta no deben generar pagos. Verificar validación y liquidación como pasos distintos, con resultado por entrada y sin extrapolar la respuesta de un canal a otro.

Con una nota de venta de 30 VES y 30 cobros de 1 VES en base temporal, comprobar que el catálogo de métodos se consulta una vez para el lote y que una lectura del saldo informa 30 pagados sin ejecutar escrituras ni depender de un indicador de pagado obsoleto. Forzar una falla después de insertar un pago dentro de una transacción: debe quedar cero pagos del lote fallido; un reintento válido debe liquidar correctamente la nota. Si se prueba el servicio de bloqueo directamente, confirmar que el conjunto de métodos bloqueados no se use fuera de su transacción o callback; señalar esta capa como prueba técnica y no como evidencia visual.

### ND nueva: valores predeterminados y abono posterior

En **QA-NOT-005/006/007 y QA-NHD-344/346**, con factura original doméstica confirmada y control coherente, crear una ND ordinaria de 11,60 VES omitiendo condición y `payments`. El servidor debe asignar crédito `02`, conservar saldo 11,60 y no registrar cobros ni ingresos. Repetir la misma clave de creación enviando explícitamente crédito `02` y lista vacía: debe devolver la misma nota, sin otro efecto. En solicitudes independientes con otra clave, probar contado y una lista con un pago inicial: ambas se rechazan antes de guardar, sin descartar el pago silenciosamente.

Usar dos fixtures de ND separados para comparar el abono antes y después de preparar o confirmar la emisión. En el primero, registrar después de crear la nota un abono real de 3 VES y preparar su declaración: deuda 8,60 VES y declaración de crédito por el pendiente; la factura de origen no recibe el abono. En el segundo, preparar o confirmar primero y conservar el hash del payload fiscal congelado; registrar el abono posterior por la ruta autorizada, comprobar saldo 8,60 y un único movimiento de caja, y verificar que no se reescribe el payload preparado/confirmado. Distinguir en la evidencia ambos órdenes y el estado HKA; usar doble cuando corresponda y no presentar una confirmación simulada como DEMO real.

### Conversión de orden con abono y condición de crédito

En **QA-MPA-004/014, QA-NVE-012 y QA-ODI**, usar una orden de entrega de prueba y preparar una factura de 232 VES a crédito, con abono bancario de 5 USD a 10 VES/USD y cuota por 182 VES. Intentar primero cuenta VES para el medio USD: debe rechazarse sin factura, movimiento ni vínculo de referencia en la orden. Repetir con cuenta USD compatible: el saldo y la cuota son 232−(5×10)=182 VES; se conserva el vínculo orden–factura y se registra un único movimiento bancario USD. En el catálogo de la conversión comprobar que sólo se ofrezcan medios activos clasificados como `cash` o `account`; crédito es condición y no un cobro. Registrar la prueba de este entorno Medios digitales/DEMO con HKA simulado o real identificado.

La prueba histórica de persistencia de este recorrido utilizó `free_form` y proveedor simulado: sirve como referencia de la regla comercial, no acredita el resultado en Medios digitales ni emisión HKA de esta corrida. No generar ni probar una nota de entrega HKA con indicador 0.

### Crédito con cuotas en el formulario y exportación encolada

En **QA-MPA-014**, preparar una venta a crédito con total 116 VES y abono real de 1,40 USD a tasa 50 VES/USD: 70 VES aplicados y 46 VES pendientes. Introducir cuotas manuales de 12 y 34 VES, seleccionar en el formulario la opción `03` de crédito con cuotas y alternar temporalmente a contado y de vuelta sin guardar. El borrador debe conservar abono y cuotas; cambiar el plazo de la primera cuota no modifica la fecha manual de la segunda. Al guardar con esa opción, verificar que el payload normaliza la condición a `02`, conserva ambas cuotas y el recibo original, y no crea otro movimiento. Al enviar contado en un fixture distinto, se omiten cuotas del payload sin borrar las editadas del borrador. La opción visual `03` no se presenta como un registro inicial nuevo del catálogo de condiciones. Comparar nota, factura derivada y PDF/ticket disponibles; un ticket de una muestra no demuestra paginación para cantidades ilimitadas de líneas.

En **QA-MPA-020**, además del fallo de generación, simular por separado fecha inválida y fallo de almacenamiento del XLSX después de seleccionar el tenant. Verificar que la bandeja marque `FAILED`, registre el fin del intento, no publique un archivo inexistente y propague el error al trabajador; un reintento exitoso genera un archivo que se puede abrir y no modifica cobros. Ejecutar con cola y almacenamiento temporales; una prueba de servicio o un doble no demuestra que un trabajador de BBC esté activo.

En **QA-MPA-023**, comprobar creación y edición de partida de pedido con dos fixtures. Para precio 100 VES sin IVA y alícuota de prueba 16 %, la base es 100, el IVA 16 y el total 116; al reabrir ambas pantallas de partida deben conservarse precio original 100, moneda VES e indicador de IVA no incluido. Cambiar sólo un dato no monetario y guardar no puede restaurar un total anterior sobre el recalculado. En otro fixture de precio final 100 VES con IVA incluido, el cálculo independiente es base exacta 2500/29 VES e IVA 400/29 VES; sus presentaciones a centavos son 86,21 y 13,79, total 100,00. Comparar la precisión del snapshot y los totales sin exigir que la base exacta se almacene como una cantidad finita de decimales. Para una partida legada sin entrada original conservada, documentar el fallback del precio documental con IVA incluido, sin inventar una entrada histórica. Distinguir prueba de componente, controlador, navegador y posterior conversión; no generar un cobro sólo por guardar el pedido.

### Capacidad DEMO y origen del fixture de previsión

Para **QA-RIVA-059..062, QA-RISLR-131..134 y QA-INT-075..080**, preparar los snapshots con previsión mediante los contratos API/servicio autorizados de un entorno aislado. La interfaz de creación y pagos no dispone de ese editor; comprobar su ausencia por separado. Con `demo_net_collection_verified=false`, la capacidad específica detecta una previsión o comprobante pendiente/aplicado del impuesto y pagos activos inferiores al total según su comparación, publica diagnóstico y bloquea el envío neto. Comparar IVA e ISLR por separado y comprobar coexistencia; un solo flag habilitado en un doble no acredita la capacidad del otro impuesto ni una aceptación DEMO real.

Como controles de la capa de capacidad, usar el mismo total 116 y pagos activos 104: con previsión/retención esperada y flag falso hay bloqueo; sin previsión ni comprobante esperado esa capacidad específica no bloquea, pero esto no garantiza que las demás validaciones permitan enviar. Un pago completo de 116 tampoco activa ese bloqueo específico. En una prueba de servicio con snapshot sintético, comprobar la frontera implementada: `paid < total−0,005`; para total 116, 115,994 activa el bloqueo y 115,995 no lo activa por esa comparación. No extrapolar estos valores de precisión a la validez de un cobro HTTP ni alterar dinero real. Registrar motivo, `can_send`, conteo de solicitudes al proveedor y evidencia por capa; nunca agregar pagos ficticios para superar el bloqueo.

### Contexto de gestión de retenciones y configuración IGTF

En **QA-NRF-024/025 y QA-INT-014**, abrir operaciones de una nota desde el listado de comprobantes y desde un contexto que no concede `allowRetentionManagement`. Sólo el listado habilitado muestra y permite iniciar correcciones o reemplazos IVA/ISLR; fuera de ese contexto deben quedar bloqueados, sin retirar las demás operaciones permitidas, como los ajustes de fondos. Comprobar también la autorización del servidor. En el gestor de Retención de una factura guardada, iniciar por separado una propuesta y una carga de soporte, cerrar el gestor y entregar la respuesta tardía: no debe restaurar propuesta, adjunto ni datos de otro documento. Registrar petición y estado de cada escenario sin usar soportes reales.

En **QA-IGT y QA-MPA-017**, probar la carga asíncrona de la configuración IGTF con respuestas controladas: una lectura antigua no puede sobrescribir un guardado confirmado; un fallo debe mostrar diagnóstico, impedir un cobro nuevo dependiente de esa configuración y permitir reintentar, sin borrar el borrador. Al deshabilitar IGTF no se ocultan la moneda ni la intermediación ni se alteran cobros históricos y derivados; tampoco se envía un impuesto oculto de un borrador. Un recibo reutilizado no solicita una propuesta nueva ni cambia sus importes históricos. Si una comprobación fiscal termina después de cerrar o cambiar de venta, no debe enviar la venta anterior ni modificar la nueva. Diferenciar pruebas de componente, HTTP y navegador.
