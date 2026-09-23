---
name: emitir-facturas-notas-hka
description: Preparar los payloads HKA de facturas y notas de crédito/débito de Pro9, con identificaciones, referencias, líneas, IVA, pagos y otras monedas. Usar al programar emisión HKA de esos documentos.
---

# Facturas y notas HKA

Leer [referencia HKA, secciones 1, 4.1–4.4, 5, 7 y 10–12](../../../informes/imprenta_digital_hka_api.md), el [Swagger DEMO](https://demoemisionv2.thefactoryhka.com.ve/swagger/index.html?urls.primaryName=Imprenta+Digital+VE) y [numeración fiscal Pro9](../mantener-numeracion-fiscal-venezuela-pro9/SKILL.md).

- **Conservar tipos y numeración internos:** Pro9 factura `01`, crédito `07`, débito `08`; HKA recibe `01`, `02`, `03` respectivamente. Traducir por tipo semántico sólo en el adaptador HKA y al interpretar sus respuestas. Jamás reemplazar `FiscalProfileService::TYPES`, secuencias, reservas, validaciones o códigos persistidos por los del proveedor. HKA `07` designa ARCV, no crédito.
- Construir desde documentos y reservas persistidos, no desde importes o identidad fiscal aportados por navegador. Conservar la referencia de factura afectada en notas y la clave de operación para reintentos. Aplicar los contratos de [factura/nota sin boleta](../mantener-facturas-notas-venta-sin-boleta/SKILL.md), [clientes](../gestionar-clientes-venezuela/SKILL.md) e [IVA](../migrar-iva-venezuela/SKILL.md).
- Mapear moneda, unidades y pagos en el borde HKA; consultar [moneda](../migrar-venezuela-moneda/SKILL.md), [unidades](../mantener-unidades-medida-venezuela/SKILL.md) y catálogos fiscales. `VED`, `VEF` o `BsD` del manual no cambian el contrato VES de Pro9. No usar la tolerancia HKA para alterar importes locales.
- Swagger publica `POST /api/Emision` con raíz `documentoElectronico` y propiedades `lowerCamelCase`; el `tipoDocumento` del esquema normal admite `01`–`06`. `facturaGuia` es lista, no objeto; `totales` incorpora recargos, OTI e IGTF. Verificar condiciones de negocio del PDF aunque el esquema deje propiedades opcionales. Probar en DEMO serie vacía, respuesta, NC y ND; los ejemplos del PDF tienen casing y límites inconsistentes. Una prueba del simulador local no acredita esta integración.
