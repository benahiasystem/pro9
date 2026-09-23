---
name: distribuir-documentos-hka
description: Preparar envío y rastreo de correo, descarga de archivos y agrupación de documentos mediante la API de imprenta digital HKA. Usar para Correo/Enviar, Correo/Rastreo, Correo/EnviaOrden, Correo/RastreoOrden o DescargaArchivo.
---

# Distribución de documentos HKA

Leer [referencia HKA, secciones 3, 4.3, 6 y 10–12](../../../informes/imprenta_digital_hka_api.md), [operación fiscal local](../mantener-operacion-local-fiscal-pro9/SKILL.md) y [seguridad Pro9](../mantener-seguridad-pro9/SKILL.md).

- Resolver la identidad desde el documento y su reserva persistidos. Para `tipoDocumento` externo usar `01→01`, `07→02`, `08→03`, `09→04` según el significado; los códigos HKA no reemplazan los internos ni afectan numeración. Verificar autorización de consulta/descarga dentro del tenant.
- `Correo/Enviar` envía el documento, `Correo/Rastreo` consulta cada correo y `Correo/EnviaOrden`/`RastreoOrden` hacen lo propio con una orden consolidada. Swagger define `rastreos[]` con `messageId`, `correo`, `status`, `fecha`; `orden` admite hasta 20 caracteres de un conjunto restringido. `DescargaArchivo` acepta `tipoArchivo` PDF/XML/JSON y responde `archivo` string: no inferir que es Base64 sin probarlo. Solicitar XML HKA no restaura el flujo XML fiscal peruano de Pro9.
- Separar estado de emisión, generación/descarga del PDF y entrega del correo; un éxito de una operación no demuestra éxito de las otras. Evitar duplicar envíos en reintentos inciertos y no divulgar datos del comprador a otro tenant.
