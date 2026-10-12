# Inventario obligatorio de opciones de facturación digital

Este archivo es una plantilla de ejecución. Las rutas son puntos de entrada del código actual; registrar la ubicación real del despliegue. Todas las filas comienzan **Por inventariar**. No constituyen opciones ya ejecutadas.

## Procedimiento de inventario

Recorrer cada pantalla como administrador y como vendedor, en desktop y menú móvil. Abrir pestañas, menús, filtros, selectores, personalizadores y diálogos. Registrar TODOS los valores activos, condiciones de visibilidad, permiso y endpoint del control. Comparar con catálogo/feature flags actuales y escritor del documento. Vincular cada opción a un ID o crear QA-EXT con especificación completa.

Una fila agregada como “pagos” no cubre efectivo, tarjeta, transferencia, crédito u otros valores efectivos. Expande una fila por valor y rama. Probar cada medio/destino activo en su canal real; un código09 de un catálogo no tiene el mismo significado en otro. Si una opción es inaccesible por configuración, habilitar elfixtureQA o registrar Bloqueada/exclusión sustentada. No llamarla No aplica sólo por estar oculta.

| Pantalla o flujo | Opciones a inventariar y expandir | Grupos base | Estado inicial |
|---|---|---|---|
| Configuración fiscal digital | Modalidad, ambiente, proveedor, autorización, usuario/clave ocultos, verificar/conectar, guardar/borrar secreto, auditoría |CFG/SEG|Por inventariar|
| Configuración de ventas | Documento por defecto, IVA, crédito vencido, permisos edición/precios/descuentos/cobros, límites del plan |CFG/FAC/CAL/EDI|Por inventariar|
| Configuración de impresión/distribución | Formatos/plantillas/tamaños, mostrar80/58, QZ, autoimpresión, autocorreo, WhatsApp, pie de ventas/logo |PDF/COR|Por inventariar|
| Sucursal y Numeración y emisión | Todos/Básico/Avanzado/Interno/Dedicado/Contingencia, Nuevo/Guardar/Cancelar, serie vacía/código/inicio/popup, grupos/vínculos |NUM|Por inventariar|
| Encabezado nuevo comprobante | Tipo, serie, operación, Por cuenta de terceros, Registrar/Editar tercero |FAC/EXP/TER|Por inventariar|
| Datos generales | Cliente buscar/crear/editar, dirección, emisión/vencimiento, moneda/tasa, consignado, itinerante, vendedor/placa |CLI/PAG/PRE|Por inventariar|
| Personalizar datos generales | Mover, fijar, anchos, guardar/cancelar/restablecer, información adicional, barra fijada |CLI|Por inventariar|
| Agregar producto/servicio | Todas las búsquedas, barcode, padre/variaciones/atributos/stock, almacén, unidad/presentación/precio, lote/serial, IVA, descripción |ITM/CAL|Por inventariar|
| Tabla de líneas | Editar/eliminar, cantidad/precio/total, descripciónpack, lotes faltantes, detalles, líneas restringidas |ITM/CAL|Por inventariar|
| Descuentos/cargos/atributos | Cada código activo por línea/global, porcentaje/monto si disponible, agregar/quitar, permisos y distribución |CAL/ITM|Por inventariar|
| Anticipos y opciones adicionales | Registrar anticipo, deducir, afectación, referencias, importes, puntos, datosHotel/transporte/TAP |PRE|Por inventariar|
| Pagos iniciales | Contado/Crédito/Cuotas, fechas/calendario, cada medio y destino, referencia, vuelto, IGTF clasificación/motivo |FAC/PAG/IGT/COM|Por inventariar|
| Botones del formulario | Generar, generar/enviar si existe, guardar/actualizar, vista previa, cancelar, nuevo/listado y atajos realmente activos |FAC/EDI/PDF|Por inventariar|
| Comprobante generado y Opciones | Estado fiscal, Enviar HKA, imprimir/descargar formatos, correo/enviar de nuevo/rastreo, WhatsApp, cerrar/listado/nuevo |HKA/PDF/COR/LIS|Por inventariar|
| Listado comprobantes | Filtros/orden/paginación/KPI/totales, columnas, estado/popup, campos personalizados, selección/masivas, acciones y menú móvil |LIS|Por inventariar|
| Detalle/drawer | Cabeceraestado/control, Consultar comprobante, PDF, editar, pagos, referencias/origen, alerta de bloqueo |LIS/HKA/EDI|Por inventariar|
| Pagos posteriores | Nuevo, cada medio/destino, fecha/importe/referencia/adjunto, IGTF, reversión y razón, saldos/recibos derivados |PAG/IGT|Por inventariar|
| Retenciones recibidas | IVA/ISLR, conceptos efectivos, agente, número/fecha, base/%/sustraendo/importe/moneda/tasa, upload/download |RET|Por inventariar|
| Fondos de garantía | Vía de creación/aplicación disponible, base/%/importe, presentación, permisos |RET|Por inventariar|
| Nota de crédito/débito | Cada motivo activo, descripción, referencia, líneas/IVA, formato disponible, guardar/PDF/correo comercial |NOT/MAT/COM|Por inventariar|
| Nota de venta | Crear/editar/duplicar/anular, pagos, formatos/correo, lote/serie, selección/conversión individual/múltiple |NVE|Por inventariar|
| POS | Categorías/favoritos/barcode/variaciones, stock, descuento autorizado, moneda, suspender/recuperar, todos los pagos, visor y Opciones |CAN/ITM/PDF/COR|Por inventariar|
| Vende Ya/Garage y venta rápida | Cliente/serie/tipo, recargas, pagos simples/múltiples, descuento, moneda, vuelto, resultado y Opciones |CAN/NUM/PAG|Por inventariar|
| Hotel/restaurante/servicio técnico | Todos los cierres/conversiones/tipos realmente ofrecidos y campos por giro |CAN/NVE/PRE|Por inventariar|
| Ecommerce/Store/pedidos/consignación | Estados que generan venta, tipos, descuentos/precio/envío/cupón/pago en sandbox, vínculos ydeduplicación |CAN/NVE/SEG|Por inventariar|
| WhatsApp Bot, móvil/API y Offline | Contratos de creación/consulta/estado/documento/PDF, reintentos, sincronización; mensajes controlados |CAN/API/MAT|Por inventariar|
| Suscripciones y otras fuentes | Modos/ciclos que generan Factura oNV, estados, cobros yvínculos |CAN/NVE|Por inventariar|
| Cotizaciones/pedidos/órdenes de entrega | Cada forma de convertir, referencias, cliente, stock origen, serie/sucursal, transporte/dirección |NVE|Por inventariar|
| Importar documentos | Formato estándar, segundo formato, Excel, validaciones/reportes/plantillas, corregir/reimportar |IMP|Por inventariar|
| Facturación masiva superadmin | Descargar formato, cargar/procesar/registros/exportar/PDF y permisos |IMP|Por inventariar|
| Anular/recrear/eliminar | Opciones realmente habilitadas por estado/permiso, solicitud por anular, plazos, herramientas DEMO |ANU|Por inventariar|
| Caja/Finanzas/Reportes/Dashboard | Cada reporte/exportación/filtro relacionado con facturación/cobro/IVA/notas/stock y cada formato |FIN|Por inventariar|

## Registro de opciones efectivamente encontradas

| ID opción | Pantalla/ruta | Control y valor exacto | Flag/condición | Rol/canal | Endpoint/escritor | Caso/subcaso | Estado de ejecución | Evidencia |
|---|---|---|---|---|---|---|---|---|
|OP-0001|[completar]|[completar]|[completar]|[completar]|[completar]|[ID]|Pendiente|[ruta]|

Al cierre: total encontradas=[cantidad]; mapeadas=[cantidad]; ejecutadas=[cantidad]; sin ejecutar=[lista]. Exigir cobertura de opciones aplicables 100 % o declarar el informe Inconcluso con faltantes.

## Plantilla de caso adicional

```text
ID: QA-EXT-0001
Opción y pantalla/endpoint:
Prioridad / clase / capa:
Fuente del contrato y versión:
Precondiciones, tenant digital/demo y permisos:
Fixture y valores exactos:
Línea base de efectos:
Pasos numerados:
Resultado esperado por paso y al recargar:
Control monetario/inventario/fiscal si corresponde:
Variantes y sus IDs:
Estado: Pendiente / Aprobó / Reprobó / Bloqueada / No aplica
Observado / evidencia / defecto / ejecutor / fecha Caracas / iteración:
```

