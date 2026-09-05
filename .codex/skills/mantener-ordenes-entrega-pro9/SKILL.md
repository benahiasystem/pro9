---
name: mantener-ordenes-entrega-pro9
description: Mantener en Pro9 el módulo y la presentación de Órdenes de entrega, antes denominado Guía de Remisión, preservando rutas, tablas, códigos y contratos internos de dispatch. Usar al tocar despachos, transportistas, reportes, PDF, correos, catálogos o conversiones a comprobantes.
---

# Mantener Órdenes de entrega en Pro9

## Contrato de presentación

- Mostrar `Orden de entrega` en singular y `Órdenes de entrega` en plural.
- Aplicar la denominación a menús, títulos, botones, formularios, mensajes, correos, reportes, exportaciones y PDF.
- Nombrar el documento del código `31` como `Orden de entrega del transportista` cuando sea necesario distinguirlo.
- No presentar al usuario las denominaciones históricas `Guía de Remisión`, `G.R. Remitente` o `G.R. Transportista`.

## Contrato de catálogos y datos

- Conservar los códigos documentales existentes: `09`, `31`, `71` y `72`.
- Asignar estas descripciones: `09` = `ORDEN DE ENTREGA`; `31` = `ORDEN DE ENTREGA DEL TRANSPORTISTA`; `71` = `Orden de entrega complementaria`; `72` = `Orden de entrega del transportista complementaria`.
- Actualizar tanto semillas y migraciones base como tenants existentes mediante una migración nueva.
- Conservar documentos históricos y sus relaciones; el cambio es de nomenclatura, no de identidad documental.

## Compatibilidad técnica

- Preservar nombres internos como `dispatch`, `dispatches`, `dispatch_carrier`, tablas, modelos, claves foráneas, rutas, permisos y campos de API.
- Preservar el valor técnico del módulo `guia` y el alias de búsqueda `guia` por compatibilidad.
- No reescribir catálogos oficiales, códigos de error ni endpoints históricos contenidos en `app/CoreFacturalo/WS`; no son textos de presentación del módulo.
- No confundir las órdenes de entrega con guías documentarias, archivos adjuntos de compra u otros conceptos ajenos al módulo de despachos.

## Verificación obligatoria

1. Buscar variantes visibles de la denominación anterior fuera de las excepciones técnicas.
2. Probar que los códigos `09`, `31`, `71` y `72` conservan su identidad y muestran las nuevas descripciones.
3. Verificar menú, listado, creación, mensajes, correo, reportes y PDF.
4. Ejecutar las pruebas de contrato relacionadas y compilar los assets cuando cambien archivos Vue o JavaScript.
