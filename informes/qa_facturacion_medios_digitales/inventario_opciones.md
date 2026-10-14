# Inventario obligatorio de opciones de facturación digital

Referencia: 14 de octubre de 2026, `76690125` y cambios locales de órdenes con hashes en fuentes; 1702 casos planificados. Este archivo es una plantilla de ejecución. Las rutas son puntos de entrada del código actual; registrar la ubicación real del despliegue. Todas las filas comienzan **Por inventariar**. No constituyen opciones ya ejecutadas.

## Procedimiento de inventario

Recorrer cada pantalla como administrador y como vendedor, en desktop y menú móvil. Abrir pestañas, menús, filtros, selectores, personalizadores y diálogos. Registrar TODOS los valores activos, condiciones de visibilidad, permiso y endpoint del control. Comparar con catálogo/feature flags actuales y escritor del documento. Vincular cada opción a un ID o crear QA-EXT con especificación completa.

Una fila agregada como “pagos” no cubre efectivo, tarjeta, transferencia, crédito u otros valores efectivos. Expande una fila por valor y rama. Probar cada medio/destino activo en su canal real; un código 09 de un catálogo no tiene el mismo significado en otro. Si una opción es inaccesible por configuración, habilitar el fixture QA o registrar Bloqueada/exclusión sustentada. No llamarla No aplica sólo por estar oculta.

| Pantalla o flujo | Opciones a inventariar y expandir | Grupos base | Estado inicial |
|---|---|---|---|
| Configuración fiscal digital | Modalidad, ambiente, proveedor, autorización, usuario/clave ocultos, verificar/conectar, guardar/borrar secreto, auditoría |CFG/SEG|Por inventariar|
| Configuración de ventas | Documento por defecto, IVA, crédito vencido, permisos edición/precios/descuentos/cobros, límites del plan |CFG/FAC/CAL/EDI|Por inventariar|
| Configuración de impresión/distribución | Formatos/plantillas/tamaños, mostrar 80/58, QZ, autoimpresión, autocorreo, WhatsApp, pie de ventas/logo |PDF/COR|Por inventariar|
| Sucursal y Numeración y emisión | Todos/Básico/Avanzado/Interno/Dedicado/Contingencia, Nuevo/Guardar/Cancelar, serie vacía/código/inicio/popup, grupos/vínculos |NUM|Por inventariar|
| Encabezado nuevo comprobante | Tipo, serie, operación, Por cuenta de terceros, Registrar/Editar tercero, régimen vacío y los siete regímenes activos |FAC/EXP/TER/RGE|Por inventariar|
| Datos generales | Cliente buscar/crear/editar, dirección, emisión/vencimiento, moneda/tasa, consignado, itinerante, vendedor/placa |CLI/PAG/PRE|Por inventariar|
| Personalizar datos generales | Mover, fijar, anchos, guardar/cancelar/restablecer, información adicional, barra fijada |CLI|Por inventariar|
| Agregar producto/servicio | Todas las búsquedas, barcode, padre/variaciones/atributos/stock, almacén, unidad/presentación/precio, lote/serial, IVA, descripción |ITM/CAL|Por inventariar|
| Tabla de líneas | Editar/eliminar, cantidad/precio/total, descripción pack, lotes faltantes, detalles, líneas restringidas |ITM/CAL|Por inventariar|
| Descuentos/cargos/atributos | Cada código activo por línea/global, porcentaje/monto si disponible, agregar/quitar, permisos y distribución |CAL/ITM|Por inventariar|
| Anticipos y opciones adicionales | Registrar anticipo, deducir, afectación, referencias, importes, puntos, datos Hotel/transporte/TAP |PRE|Por inventariar|
| Pagos iniciales | Contado/Crédito/Cuotas, fechas/calendario, cada medio y destino, referencia, vuelto, IGTF clasificación, intermediación none/ financial_institution, excepción admin con motivo/fundamento |FAC/PAG/IGT/COM|Por inventariar|
| Botones del formulario | Generar, generar/enviar si existe, guardar/actualizar, vista previa, cancelar, nuevo/listado y atajos realmente activos |FAC/EDI/PDF|Por inventariar|
| Comprobante generado y Opciones | Estado fiscal, Enviar HKA, imprimir/descargar formatos, correo/enviar de nuevo/rastreo, WhatsApp, cerrar/listado/nuevo |HKA/PDF/COR/LIS|Por inventariar|
| Listado comprobantes | Filtros/orden/paginación/KPI/totales, columnas, estado/popup, campos personalizados, selección/masivas, acciones y menú móvil |LIS|Por inventariar|
| Detalle/drawer | Cabecera estado/control, Consultar comprobante, PDF, editar, pagos, referencias/origen, alerta de bloqueo |LIS/HKA/EDI|Por inventariar|
| Pagos posteriores | Nuevo, cada medio/destino, fecha/importe/referencia/adjunto, IGTF, reversión y razón, saldos/recibos derivados |PAG/IGT|Por inventariar|
| Retenciones recibidas | IVA/ISLR separados; propuesta/previsión, neto explícito, datos del comprobante, soporte privado, registrar/consultar/retry/reverse, historial y estados local/HKA |RET/RIVA/RISLR/INT|Por inventariar|
| Fondos de garantía | Vía de creación/aplicación disponible, base/%/importe, presentación, permisos |RET|Por inventariar|
| Nota de crédito/débito | Cada motivo activo, descripción, referencia, líneas/IVA, formato disponible, guardar/PDF/correo comercial |NOT/MAT/COM|Por inventariar|
| Nota de venta | Crear/editar/duplicar/anular, pagos, formatos/correo, lote/serie, selección/conversión individual/múltiple |NVE|Por inventariar|
| POS | Categorías/favoritos/barcode/variaciones, stock, descuento autorizado, moneda, suspender/recuperar, todos los pagos, visor y Opciones |CAN/ITM/PDF/COR|Por inventariar|
| Vende Ya/Garage y venta rápida | Cliente/serie/tipo, recargas, pagos simples/múltiples, descuento, moneda, vuelto, resultado y Opciones |CAN/NUM/PAG|Por inventariar|
| Hotel/restaurante/servicio técnico | Todos los cierres/conversiones/tipos realmente ofrecidos y campos por giro |CAN/NVE/PRE|Por inventariar|
| Ecommerce/Store/pedidos/consignación | Estados que generan venta, tipos, descuentos/precio/envío/cupón/pago en sandbox, vínculos y deduplicación |CAN/NVE/SEG|Por inventariar|
| WhatsApp Bot, móvil/API y Offline | Contratos de creación/consulta/estado/documento/PDF, reintentos, sincronización; mensajes controlados |CAN/API/MAT|Por inventariar|
| Suscripciones y otras fuentes | Modos/ciclos que generan Factura o NV, estados, cobros y vínculos |CAN/NVE|Por inventariar|
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


## Inventario específico de la ampliación

Cada valor/rama siguiente debe tener fila OP propia y caso/variante/veredicto individual. No aprobar todo un selector con un clic de apertura.

| Control/flujo | Valores y ramas obligatorias | Grupos | Estado |
|---|---|---|---|
| Régimen especial web | Vacío; 1 Zonas económicas especiales; 2 Zona franca de Paraguaná; 3 Zona libre de Paraguaná; 4 Puerto libre Santa Elena de Uairén; 5 Zona Libre de Mérida; 6 Puerto libre Estado Nueva Esparta; 7 Dutty Free; VES/USD; propia/terceros |RGE|Por inventariar|
| Editor/versiones de perfil | Empresa/cliente; naturaleza natural/legal/public; firma sí/no; actividad sí/no; RIF distinto de cédula; crear/cancelar/historial/fechas/notificación/revocación; can_edit por rol |SPE|Por inventariar|
| Clases públicas | national, state, municipal,autonomous_institute,decentralized_non_business,decentralized_business |SPE|Por inventariar|
| Estado/facultades del perfil | iva_status ordinary/formal/not_subject; iva_agent_basis none/SNAT/2025/000054/SNAT/2013/0029; islr_agent true/false y fundamento propio; exclusión/contexto público |SPE/RIVA/RISLR|Por inventariar|
| IVA 100 % | undisclosed_iva,invoice_formalities,portal_100,unregistered_rif,precious_metals; evidencia y restricción pública particular |SPE/RIVA|Por inventariar|
| Pago IGTF | Moneda documental/recibida; intermediación omitida/none/ financial_institution; not_applicable/subject; exempt sólo admin+motivo+ exception_basis |SPE/IGT/PAG|Por inventariar|
| Previsión IVA | Activar/desactivar; propuesta elegibilidad/porcentaje/base IVA/base imponible; actualización cliente/fecha/líneas; sugerir cobro neto sólo explícito |RIVA/INT|Por inventariar|
| Previsión ISLR | Activar/desactivar; base_mode full_net /declared, concepto 002..086 (85 valores iniciales), porcentaje/sustraendo declarados, fundamento/evidencia |RISLR/INT|Por inventariar|
| Registro/soporte recibido | Tax kind IVA/ISLR; número/fecha/referencia/control/RIF; moneda VES/USD/tasa 8 dec; PDF/JPG/PNG≤5 MB; draft token 24 h/consumo/descarga privada |RIVA/RISLR|Por inventariar|
| Estado/comando de retención | pending_validation /applied/invalid/reversed; reversal_pending; outbox waiting/pending/confirmed/uncertain/rejected/conflict/removed/cancelled; query/retry/reverse por permiso/UUID actual |RIVA/RISLR/INT|Por inventariar|
| Pagos/borradores | Web/POS normal/rápido/múltiples/Garage rápido/múltiples, Hotel/Order/servicio técnico/Suscription/FullSuscription/restaurante/API; crédito/abonos/conservación/reabrir/recuperar y XHR obsoleta |INT|Por inventariar|
| Bloqueo de capacidad DEMO | configiva_retention. demo_net_collection_verified =false y configislr_retention. demo_net_collection_verified =false; moneda/condición/coexistencia, diagnóstico/ can_send false; no activar en real sin verificación |RIVA/RISLR/INT|Por inventariar|
| Presentación fiscal | Snapshots, detalle, listado, ticket local, original A4 HKA, A5/correo; régimen/leyendas SPE presentes/omitidas; hash original sin alteración |RGE/SPE/INT/PDF|Por inventariar|

Si el tenant tiene más conceptos/opciones que el catálogo inicial, agregar QA-EXT con datos/fundamento/pasos/oráculo, manteniendo IDs previos. Inventariar cada medio, destino, descuento, cargo, reporte/formato por canal; los grupos nuevos no sustituyen las matrices antiguas. Confirmar controles de versión realmente cargados en el navegador antes de ejecutar.

## Ampliación: notas y órdenes (14/10/2026)

| Pantalla / opción | Valores y controles a inventariar | Casos |
|---|---|---|
|Nota|Tipo07/08; siete motivos y activos; factura/control; remanentes/partidas; series vacías/autorizadas; descripción; tercero/régimen heredados; tasa NC/ND; lotes/seriales originales|NHD|
|Pagos ND|Contado/Crédito; medios activos/no is_credit; VES/USD/tasa8dec/fecha/UUID; banco/caja por sucursal; IGTF/intermediación; abonos/borradores|NHD/NRI|
|Devoluciones|Origen real; principal/moneda/tasa/fecha/medio/destino; parcial/total/remanente; UUID/motivo/actor; reversión e historial/permisos|NRF|
|Ajustes|IVA/ISLR independientes; número/fundamento/soporte; reversión incierta/reemplazo; fondo parcial/total; reservas/IGTF bloqueado|NRF|
|Anular nota|Admin/motivo/UUID; local/remoto; dependencias; pending/uncertain/rejected/remote_confirmed/completed; consultar/completar; rutas antiguas|NAN|
|Fiscal y distribución nota|show_send/can_send/diagnóstico; cargar/consultar/reintentar; A4/A5/ticketURL; correo/rastreo; identidad en diálogos y respuestas tardías|NHD/NRI|
|Nueva orden|Motivos04/21/22/23/24; público01/privado02; flagstock/valorización; receptor/origen/destino; KG/TON/fecha; descripción reparación; bienes/unidades/300líneas|ODE|
|Transporte orden|Transportista/conductor/vehículo autorizado; has_transport_driver; placa/TUC/licencia/contacto; conductores/vehículos secundarios; catálogos tipo/origen/destino producto|ODE/ODI|
|Inventario orden|Sucursal/almacén; cantidades 4 dec; lote/serial; edición/restauración; stock congelado; derechos físicos del origen|ODI|
|Convertir/asociar|Individual/múltiple; origen cotización/pedido/NV/F; agrupación; ya trasladado/nuevo; unidad original; refs8dígitos/serie vacía; facturaGuia/relaciones y ambas direcciones|ODI|
|Listado/finanzas|Filtrar/ordenar/paginar; estados; detalle/menúmóvil; reportes/caja/bancos/cxc/morosidad/dashboard/exportación; roles/tenant/sucursal/esquema legado|NRI|

No inventariar selector ni emisión de nota de entrega indicador 0 como funcionalidad planificada. Órdenes usan 1. Para cada control realmente encontrado registrar entrada, valores permitidos, default, dependencia, endpoint, rol y caso/variante con resultado propio; una fila de inventario no es evidencia de ejecución.
