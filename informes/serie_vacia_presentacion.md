# Serie vacía en la presentación

Se retiró la etiqueta sustituta de formularios, tablas, POS/Garage, filtros de reportes, conversiones, diálogos y búsqueda pública. Element UI recibe un espacio no separable sólo como etiqueta visual para impedir que muestre el ID de la opción o `__without_series__`. La serie persistida sigue siendo cadena vacía; limpiar un filtro sigue siendo distinto de seleccionar esa opción.

`DocumentFileName` separa los nombres entregados al usuario de las claves privadas. Las descargas A4/A5 HKA y 80MM, impresión local, adjuntos comerciales y nombres dentro de ZIP omiten el marcador interno de sucursal y completan el correlativo hasta ocho dígitos. Los ZIP distinguen nombres visibles repetidos con sufijos `(2)`, conservando cada archivo. Las URLs de presentación y los alias de adjuntos usan el mismo nombre visible.

No se renombraron archivos guardados ni se alteraron `documents.filename`, numeración, permisos tenant/sucursal, controles, payloads o bytes originales HKA. Los adjuntos enviados directamente por HKA siguen siendo responsabilidad del proveedor.

## Verificación

Pruebas de nombres con serie, vacía y sucursales distintas, descargas por ruta privada, bytes, adjuntos comerciales, ZIP sin pérdidas, PDF, identidad, numeración y HKA aprobadas. Fuentes Vue compilables y pruebas de filtros/selección POS/Garage/descargas aprobadas. Verificación visual: seleccionar la opción vacía en el listado filtra sólo esos documentos; reabrir conserva la selección visual vacía y limpiar restaura documentos con serie. El watcher del usuario compiló los assets; el agente no ejecutó compilaciones.

Evidencia: `/tmp/pro9-serie-vacia-filtro.png`. Se usaron comprobantes existentes, sin emitir ventas ni enviar correos.

Resultados: 103 pruebas PHP iniciales y 63 en la verificación posterior que incluye adjuntos (con suites compartidas); 34 pruebas JavaScript. Descargas reales de la factura existente 25: `J958976786-01-00000025.pdf` y `J958976786-01-00000025-80mm.pdf`. El filtro vacío no muestra texto sustituto, ID ni placeholder después de seleccionarse.
