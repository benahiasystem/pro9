# QA de facturación Pro 9 en Medios digitales

Guía detallada para que otro agente ejecute QA. Referencia: 14 de octubre de 2026, commit `76690125` y cambios locales de órdenes con SHA-256 en fuentes.

**Modalidad:** Medios digitales. **Ambiente de referencia:** DEMO, según la integración HKA actual.
**Catálogo inicial:** 1702 casos especificados en 41 grupos (1121 anteriores conservados y 581 nuevos). Todos tienen estado **Pendiente**. No son resultados de pruebas ejecutadas.

1. [Guía de ejecución](guia_ejecucion.md): alcance, instrucciones obligatorias, fixtures, oráculos, capas de evidencia, secuencia y criterios de cierre.
2. [Casos y especificaciones detalladas](casos_prueba.md): pasos y aceptación de cada caso, más los campos de resultado individual.
3. [Registro editable de resultados](../../outputs/qa_facturacion_medios_digitales/registro_resultados.xlsx): estados, resultados, evidencias, defectos y resumen automático; especificaciones también incluidas por ID.
4. [Inventario de opciones](inventario_opciones.md): ampliar cobertura a todos los controles y valores del despliegue sin omisiones.
5. [Plantilla del informe final](plantilla_informe_final.md): reporte detallado obligatorio de cada prueba, métricas, defectos, conciliación, bloqueos y retest.
6. [Fuentes y trazabilidad](fuentes_y_trazabilidad.md): políticas/rutas/código, hashes y suites existentes como apoyo.

El agente debe indicar **Aprobó** o **Reprobó** en cada prueba ejecutada y justificar Bloqueada/No aplica. Debe distinguir resultado comercial, estado de emisión HKA, PDF y entrega de correo, y entregar un informe final incluso si la conclusión es Inconclusa. Las matrices y opciones adicionales se expanden en subcasos con su propio resultado.

## Ampliación del 12 de octubre de 2026

Se actualizaron las expectativas de 37 casos anteriores y se agregaron cinco grupos: **RGE** (59), **SPE** (64), **RIVA** (63), **RISLR** (135) e **INT** (80). Incluyen 28 combinaciones individuales de régimen/moneda/titular, las seis clases públicas, todos los fundamentos IVA 100, los 85 conceptos ISLR no salariales iniciales y la coexistencia con conservación de abonos por canal.

**Contratos de cierre:** un comprobante activo por impuesto/factura; previsión no reduce deuda; aplicación digital exige confirmación/control y referencia coincidente; IGTF usa perfil vigente del emisor, moneda recibida e intermediación. Ambas capacidades DEMO de cobro neto siguen deshabilitadas. La documentación distingue registro comercial, aplicación local, sincronización HKA y presentación. No completar pagos ficticios ni modificar originales HKA para esconder omisiones de régimen/SPE.

## Instrucción lista para entregar al agente

> Ejecuta la guía de QA de Pro 9 en `informes/qa_facturacion_medios_digitales/guia_ejecucion.md`, con modalidad Medios digitales y ambiente DEMO conforme a la capacidad actual. La referencia actual es 76690125 más cambios locales de órdenes con hashes en fuentes; conserva los 1702 IDs del catálogo, incluidos los 1121 anteriores y los 720 originales. Lee los casos, inventaría todas las opciones y valores del despliegue, ejecuta las matrices y crea casos adicionales cuando falte cobertura. Para cada prueba y variante registra Aprobó o Reprobó con resultado observado, evidencia, documento/operación, ejecutor y fecha Caracas. Si no puedes ejecutarla, registra Bloqueada con su causa; No aplica exige exclusión sustentada. No conviertas pruebas simuladas o evidencia histórica en aceptación real HKA ni en ejecución de esta nueva corrida. Prueba regímenes, SPE, IVA, ISLR, coexistencia, NC/ND, devoluciones/ajustes, anulación de notas, órdenes de entrega, inventario y relaciones; documenta cada una de las cuatro capas y las capacidades netas bloqueadas con can_send =false. Conserva abonos al reabrir Pagos/recuperar borradores y aplica la ventana propia de retenciones de 90 segundos conforme a sus estados. Excluye las notas de entrega esGuiaDespacho=0; prueba únicamente órdenes 09→HKA04/indicador1. No ejecutes generadores históricos sobre BBC ni migraciones o compilaciones como preparación. Respeta permisos, fixtures QA, destinatarios controlados, estado fiscal, oráculos monetarios y la restricción de compilación del proyecto. Al finalizar entrega el registro actualizado, evidencia saneada y un informe autónomo completo según la plantilla, con conteos reconciliados, defectos reproducibles, conciliación de dinero/stock, límites y conclusión precisa. No declares aprobado el sistema con casos críticos faltantes o fallidos.

## Distribución de casos

| Grupo | Área | Cantidad |
|---|---|---:|
|ENT|Preparación, inventario y alcance|12|
|CFG|Configuración digital, autenticación y catálogos|17|
|NUM|Series, numeración y dispositivos|21|
|CLI|Clientes, identidad y datos generales|18|
|ITM|Productos, servicios, inventario y atributos|25|
|CAL|IVA, descuentos, cargos y totales|19|
|FAC|Facturas ordinarias y condiciones de pago|16|
|EXP|Facturas de exportación de bienes|18|
|TER|Facturas por cuenta de terceros|15|
|NOT|Notas de crédito y débito|19|
|PAG|Pagos, monedas, tasas y reversión|22|
|IGT|IGTF inicial y posterior|14|
|RET|Retenciones recibidas y fondos de garantía|19|
|EDI|Edición comercial y conservación de datos|20|
|HKA|Preparación, emisión, conciliación y fallos|25|
|PDF|PDF, vista previa, ticket QR e impresión|25|
|COR|Correo, rastreo y WhatsApp|19|
|LIS|Listado, detalle, búsqueda, columnas y estados|16|
|NVE|Notas de venta y conversiones comerciales|14|
|CAN|Canales de venta y generación indirecta|40|
|API|Contratos HTTP, validación y replay de creación|8|
|IMP|Importaciones y facturación masiva|12|
|ANU|Anulación local, recreación y eliminación de pruebas|9|
|FIN|Caja, cuentas por cobrar, reportes y conciliación|10|
|SEG|Autorización, aislamiento y seguridad del flujo fiscal|12|
|ROB|Recuperación, estabilidad y aceptación final|6|
|MAT|Matriz exhaustiva de identidad, comprobante y canal|208|
|NEG|Tipos retirados, canales no habilitados y catálogos ajenos|19|
|PRE|Anticipos, deducciones y opciones condicionales|12|
|COM|Matriz de moneda, IVA y condición comercial|30|
|RGE|Regímenes especiales: matriz, edición y restricciones|59|
|SPE|Perfiles fiscales SPE: versiones, facultades e IGTF|64|
|RIVA|Retenciones IVA recibidas: previsión, aplicación y conciliación|63|
|RISLR|Retenciones ISLR recibidas: conceptos, previsión y protocolo independiente|135|
|INT|Integración y regresión: coexistencia, pagos, canales y aislamiento|80|

## Ampliación del 14 de octubre de 2026

Se agregan **581 casos**, conservando los 1121 anteriores. NHD incluye 224 combinaciones de siete motivos × dos monedas × propia/terceros × sin régimen/siete regímenes, 96 ND al contado y 42 controles adicionales. ODE incluye 40 combinaciones de cinco motivos × dos transportes × descuento de stock × valorización, más 32 controles. NRF, NAN, ODI y NRI desarrollan ciclos financieros, anulación, trazabilidad y regresión. Cada combinación tiene su ID y veredicto independiente.

La creación ordinaria requiere factura doméstica vigente confirmada y control coincidente. Las notas heredan tercero/régimen; sus transacciones son 03/04/02. La declaración 99 de crédito comercial no crea pagos ni devoluciones. Las órdenes conservan Pro 9 09→HKA04/`esGuiaDespacho="1"`. **Las notas de entrega indicador 0 están excluidas**, junto con producción, anulación remota de facturas, correo/anulación HKA de órdenes y ampliación de API pública.

La preparación de esta entrega verifica documentación y libro; no ejecuta QA de la aplicación ni operaciones fiscales. La evidencia del 13 de octubre y las suites existentes son antecedentes; todos los casos nuevos están Pendiente, sin resultados atribuidos.

| Grupo nuevo | Área | Casos |
|---|---|---:|
|NHD|Notas ordinarias y emisión HKA|362|
|NRF|Devoluciones de dinero y ajustes|38|
|NAN|Anulación de notas|32|
|ODE|Órdenes de entrega: guías de despacho|72|
|ODI|Inventario, conversiones y relaciones|45|
|NRI|Integración y regresión de notas y órdenes|32|
