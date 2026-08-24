---
name: mantener-catalogos-fiscales-venezuela
description: Mantener en Pro9 los nombres y la visibilidad de catálogos fiscales venezolanos, preservando códigos y referencias históricas. Usar al tocar tipos documentales, afectaciones IVA, descuentos/cargos, leyendas, motivos de traslado, percepciones, atributos UBL o medios de pago heredados de SUNAT.
---

# Mantener catálogos fiscales venezolanos

## Contrato

- Tratar los IDs fiscales como contratos estables: cambiar nombres o visibilidad, nunca reasignar códigos.
- Separar opciones para crear de relaciones históricas. Una fila oculta debe continuar resolviendo documentos existentes.
- Mantener `03` y sus series para consulta, PDF y auditoría, pero no ofrecer nuevas Boletas; aplicar también `mantener-facturas-notas-venta-sin-boleta` cuando intervenga un flujo de emisión.
- Mostrar `01 = FACTURA DE VENTA`, `07 = NOTA DE CRÉDITO`, `08 = NOTA DE DÉBITO`, `09 = GUÍA DE DESPACHO REMITENTE`, `20 = COMPROBANTE DE RETENCIÓN`, `31 = GUÍA DE DESPACHO TRANSPORTISTA` y `40 = COMPROBANTE DE PERCEPCIÓN`.
- Ofrecer únicamente `10 = Gravado` y `20 = Exento` en nuevas selecciones de afectación. Conservar los demás IDs para históricos y aplicar `migrar-iva-venezuela` para tasa, cálculos y nombres internos `igv`.
- Mantener activos los descuentos por ítem `00` y `01` con descripciones IVA. No reproducir estados históricos intermedios que los retiraban.
- Ocultar catálogos peruanos específicos mediante `active`, scopes o filtros; no borrar filas ni alterar integraciones técnicas SUNAT/UBL que sigan siendo contratos reales.
- Ocultar los paneles de atributos UBL adicionales mediante una capacidad central de Venezuela; no comentar bloques grandes de Vue.

## Datos

1. Actualizar `database/seeders/data/tenant_initial_data.php` para tenants nuevos.
2. Crear una migración tenant incremental para instalaciones existentes; no editar migraciones estructurales consolidadas ni migraciones históricas ejecutadas.
3. Hacer `up()` idempotente por ID y limitar `down()` a valores reconocibles introducidos por la migración.
4. Antes de cualquier eliminación excepcional, medir referencias y detenerse si existen. La implementación normal no debe borrar registros.
5. Invalidar cachés o adaptar proveedores de opciones cuando los catálogos no se consulten directamente.

## Presentación

- Usar `%IVA` y `Tipo %IVA` en los consumidores equivalentes, conservando nombres técnicos heredados.
- Actualizar literales visibles activos de Factura de venta y Guía de despacho. Mantener textos técnicos en XML, WSDL, endpoints, validadores externos y formatos peruanos cuando describan contratos reales.
- No editar `public/build`; si cambia Vue o JavaScript, aplicar `frontend-build` e informar que el usuario debe compilar.

## Marcadores y pruebas

- Delimitar cada hunk con `########## INICIO CAMBIO CATÁLOGOS DE NOMBRES` y `######### FIN CAMBIO CATÁLOGOS DE NOMBRES`, usando comentarios válidos.
- Probar nombres finales, filtros de creación, lectura histórica de `03`, visibilidad de códigos peruanos, descuentos `00/01`, afectaciones `10/20`, paneles UBL y reversión segura.
- Ejecutar las pruebas de contrato de catálogos, IVA, ventas sin Boleta, datos tenant y SUNAT/SENIAT, además de `git diff --check` y lint PHP.
