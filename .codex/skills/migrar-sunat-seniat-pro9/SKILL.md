---
name: migrar-sunat-seniat-pro9
description: Mantener y extender en Pro9 la migración de referencias visibles SUNAT/RENIEC y del campo fiscal item_code al contexto venezolano, sin fingir una integración SENIAT ni romper contratos fiscales internos. Usar al tocar productos, packs, identidad, estados fiscales, tipo de cambio, guías o configuración relacionada.
---

# Mantener el contrato SUNAT/SENIAT de Pro9

## Resultado funcional

- No mostrar el campo fiscal `item_code` en formularios, tablas ni selectores de columnas de productos, packs, Ecommerce, Producción, DIGEMID o POS.
- Conservar `item_code` en base de datos, requests, API, importación y registros históricos. Debe seguir siendo opcional y, cuando se informe, conservar su validación compatible.
- Usar `Buscar` en acciones de consulta de identidad. No exponer SUNAT/RENIEC ni rotular SENIAT si el proveedor real no corresponde.
- Mostrar `Tipo de cambio del día` sin atribuir BCV mientras `ServiceData::exchange()` siga consumiendo ApiPeru. Sólo mencionar BCV después de migrar y probar la fuente backend efectiva.
- Usar textos fiscales neutrales cuando la autoridad no sea necesaria para comprender la acción.
- Conservar el tipo de documento de identidad `0`: su etiqueta contractual es `Doc.sin.rif`. No eliminarlo, no reasignar sus registros a otro tipo y no volver a mostrar `Doc.trib.no.dom.sin.ruc`.

## Integraciones que se preservan

La ruta `dispatches/sendSunat`, `DispatchController::sendDispatchToSunat`, ApiPeru, SOAP/WSDL, `SUNAT_ALTERNATE_SERVER`, nombres `sunat_*` persistidos y el reporte Kardex 13.1 siguen siendo contratos activos o formatos reales. No renombrarlos a SENIAT ni retirarlos mediante una limpieza textual.

`app/CoreFacturalo/WS-BK` es un respaldo histórico eliminado y no debe recrearse. Esto no autoriza borrar `app/CoreFacturalo/WS`, `modules/ApiPeruDev` ni sus consumidores vigentes.

## Marcadores

Delimitar cada unidad modificada con el par que corresponda al contrato original:

```text
########## INICIO CAMBIO NELSON: RETIRO CÓDIGO SUNAT
######### FIN CAMBIO NELSON: RETIRO CÓDIGO SUNAT

########## INICIO CAMBIO NELSON: RETIRO PALABRA SUNAT
######### FIN CAMBIO NELSON: RETIRO PALABRA SUNAT

########## INICIO CAMBIO SUNAT A SENIAT
######### FIN CAMBIO SUNAT A SENIAT
```

Usar comentarios válidos del lenguaje y rodear el bloque mínimo. No insertar marcadores en JSON, bundles, mapas, manifiestos ni archivos generados.

## Verificación obligatoria

1. Ejecutar `SunatSeniatMigrationContractTest` y las pruebas Venezuela/ItemImport relacionadas.
2. Confirmar que ninguna fuente alcanzada vuelve a enlazar o configurar visualmente `item_code`.
3. Confirmar que los tooltips migrados no mencionan SUNAT ni BCV mientras la fuente siga siendo ApiPeru.
4. Confirmar que las consultas de identidad migradas muestran `Buscar` y conservan sus endpoints compatibles.
5. Confirmar que el seeder mantiene el ID `0` con la descripción `Doc.sin.rif` y que una migración incremental renombra ese mismo registro en tenants existentes.
6. Ejecutar `git diff --check`, `phpunit` relevante y `npm run build`.
7. Auditar las coincidencias SUNAT/SENIAT restantes: deben corresponder a integración activa, compatibilidad persistida, formato real, documentación histórica o marcador.
8. No incorporar artefactos generados al diff salvo que la política vigente del repositorio lo exija.
