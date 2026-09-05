# Sprint: corrección y ajustes al sistema pro9

**Proyecto:** Pro9  
**Estado:** Pendiente de ejecución  
**Alcance:** adaptación funcional, fiscal y operativa para Venezuela  
**Historias:** 19

## Objetivo

Corregir los flujos reportados por el negocio y consolidar la operación venezolana en clientes, facturación, reportes, hotel, VendeYa, pedidos, órdenes de entrega, retenciones, libros fiscales, IGTF, inventario, configuración y Kardex.

## Historias del sprint

| ID | Resultado esperado | Criterio de aceptación principal |
|---|---|---|
| PRO9-01 | Corregir el guardado de clientes. | Cliente con RIF/cédula, dirección, sitio web y observación se guarda, lista y edita sin error SQL. |
| PRO9-02 | Restaurar Tipo de operación en Factura después de Serie. | Aparece, se valida y persiste en frontend/backend. |
| PRO9-03 | Eliminar `R.U.C.` y `ruc` de filtros de reportes. | Se muestra terminología venezolana: RIF/cédula según corresponda. |
| PRO9-04 | Eliminar `DNI/RUC` del módulo Hotel. | Hotel muestra identificación venezolana consistente. |
| PRO9-05 | Simplificar productos de servicio a habitación. | No aparecen “Agregar Descuentos/Cargos/Atributos especiales” ni “Impuesto a la bolsa plástica”; se conservan históricos. |
| PRO9-06 | Establecer IVA general en 16%. | Frontend, backend, configuración, catálogos, cálculos y base de datos usan 16% para nuevas operaciones gravadas; base 100 produce IVA 16 y total 116. |
| PRO9-07 | Ajustar VendeYa. | Se elimina selección rápida de billetes y emisión nueva de Boletas; se conserva monto manual, vuelto y Factura/Nota de venta. |
| PRO9-08 | Mostrar conversión USD en reportes. | Hover sobre bolívares muestra una ventana flotante con USD, tasa BCV aplicada y fecha. |
| PRO9-09 | Corregir “Generar comprobante” desde Pedidos. | Se ofrecen Factura y Nota de venta; no Boleta. Históricas consultables. |
| PRO9-10 | Renombrar Guía de Remisión como **Orden de entrega**. | Incluye emisor con RIF, correlativo propio, leyenda fiscal obligatoria, vínculo con factura, receptor con RIF/dirección y cantidades/descripciones sin precios obligatorios. |
| PRO9-11 | Ajustar comprobantes de retención de IVA. | Estructura y datos conforme a la providencia adoptada por el proyecto, sin afirmar transmisión electrónica oficial. |
| PRO9-12 | Crear libros de compras y ventas. | Columnas estrictas, documentadas y validadas contra la normativa IVA aplicable. |
| PRO9-13 | Eliminar Guías de remisión transportistas. | Se retiran menú, creación y rutas activas; se preserva consulta histórica cuando aplique. |
| PRO9-14 | Implementar deducción de IGTF en ventas en dólares. | Sólo aplica cuando corresponda a una venta USD, queda trazable y se prueba con el documento adjunto. |
| PRO9-15 | Configurar retenciones de IVA e ISLR en Compras. | Compras permite registrar, calcular, consultar e imprimir ambos comprobantes con validación backend. |
| PRO9-16 | Corregir fecha de emisión del traslado en Kardex. | Usa la fecha real de emisión del traslado. |
| PRO9-17 | Mostrar “Nota de traslado” en Kardex. | Aparece en listado, filtros y detalle; incluye prueba automatizada y funcional. |
| PRO9-18 | Permitir eliminar documentos de prueba en Configuración avanzada. | Eliminación con confirmación, permisos y limpieza referencial; prueba de resultado. |
| PRO9-19 | Venezolanizar cálculos y terminología de Kardex. | Entradas, salidas, traslados y existencias cuadran con inventario y usan terminología venezolana. |

## Orden y dependencias

1. **Base:** PRO9-01, PRO9-03, PRO9-04, PRO9-06 y PRO9-19.
2. **Ventas y canales:** PRO9-02, PRO9-05, PRO9-07 y PRO9-09.
3. **Documentos y fiscalidad:** PRO9-10, PRO9-11, PRO9-12, PRO9-13, PRO9-14 y PRO9-15.
4. **Inventario y administración:** PRO9-16, PRO9-17 y PRO9-18.
5. **Reportes y regresión integral:** PRO9-08.

El IVA 16% debe centralizarse en `config/venezuela.php` y `App\\Support\\Venezuela\\Localization`, conservando los identificadores internos heredados `igv` cuando sean contratos de esquema/API. La especificación está en `.codex/skills/migrar-iva-venezuela/SKILL.md`.

## Insumo IGTF

- [NUEVO_IMPUESTO_IGTF.pdf](/home/benahia/benahia/pro/implementaciones%20con%20HKA/NUEVO_IMPUESTO_IGTF.pdf)

## Definición de terminado

- Fuentes frontend/backend corregidas; no se editan bundles compilados manualmente.
- Migraciones incrementales, idempotentes y reversibles cuando haya cambios de datos.
- Se preservan documentos históricos para consulta y auditoría.
- Se ejecutan pruebas unitarias/integración, sintaxis PHP, `git diff --check`, build frontend y validación funcional en navegador.
- Se documentan fórmula IVA, tasa BCV, reglas IGTF, columnas de libros y limitaciones normativas.
