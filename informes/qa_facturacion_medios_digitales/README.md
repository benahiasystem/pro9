# QA de facturación Pro9 en Medios digitales

Guía detallada para que otro agente ejecute QA. Referencia: 11 de octubre de 2026, commit `230830dd74de2e679d9ba5463da2ee9207baee11`.

**Modalidad:** Medios digitales. **Ambiente de referencia:** DEMO, según la integración HKA actual.
**Catálogo inicial:** 720 casos especificados en 30 grupos. Todos tienen estado **Pendiente**. No son resultados de pruebas ejecutadas.

1. [Guía de ejecución](guia_ejecucion.md): alcance, instrucciones obligatorias, fixtures, oráculos, capas de evidencia, secuencia y criterios de cierre.
2. [Casos y especificaciones detalladas](casos_prueba.md): pasos y aceptación de cada caso, más los campos de resultado individual.
3. [Registro editable de resultados](../../outputs/qa_facturacion_medios_digitales/registro_resultados.xlsx): estados, resultados, evidencias, defectos y resumen automático; especificaciones también incluidas por ID.
4. [Inventario de opciones](inventario_opciones.md): ampliar cobertura a todos los controles y valores del despliegue sin omisiones.
5. [Plantilla del informe final](plantilla_informe_final.md): reporte detallado obligatorio de cada prueba, métricas, defectos, conciliación, bloqueos y retest.
6. [Fuentes y trazabilidad](fuentes_y_trazabilidad.md): políticas/rutas/código, hashes y suites existentes como apoyo.

El agente debe indicar **Aprobó** o **Reprobó** en cada prueba ejecutada y justificar Bloqueada/No aplica. Debe distinguir resultado comercial, estado de emisión HKA, PDF y entrega de correo, y entregar un informe final incluso si la conclusión es Inconclusa. Las matrices y opciones adicionales se expanden en subcasos con su propio resultado.

## Instrucción lista para entregar al agente

> Ejecuta la guía de QA de Pro9 en `informes/qa_facturacion_medios_digitales/guia_ejecucion.md`, con modalidad Medios digitales y ambiente DEMO conforme a la capacidad actual. Lee los casos, inventaría todas las opciones y valores del despliegue, ejecuta las matrices y crea casos adicionales cuando falte cobertura. Para cada prueba y variante registra Aprobó o Reprobó con resultado observado, evidencia, documento/operación, ejecutor y fecha Caracas. Si no puedes ejecutarla, registra Bloqueada con su causa; No aplica exige exclusión sustentada. No conviertas pruebas simuladas o evidencia histórica en aceptación real HKA. Respeta permisos, fixtures QA, destinatarios controlados, estado fiscal, oráculos monetarios y la restricción de compilación del proyecto. Al finalizar entrega el registro actualizado, evidencia saneada y un informe autónomo completo según la plantilla, con conteos reconciliados, defectos reproducibles, conciliación de dinero/stock, límites y conclusión precisa. No declares aprobado el sistema con casos críticos faltantes o fallidos.

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
