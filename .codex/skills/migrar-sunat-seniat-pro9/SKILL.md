---
name: migrar-sunat-seniat-pro9
description: Mantener y extender en Pro9 la migración de referencias visibles SUNAT/RENIEC y del campo fiscal item_code al contexto venezolano, sin fingir una integración SENIAT ni romper contratos fiscales internos. Usar al tocar productos, packs, identidad, estados fiscales, tipo de cambio, guías o configuración relacionada.
---

# Mantener el contrato SUNAT/SENIAT de Pro9

## Resultado funcional

- No mostrar el campo fiscal `item_code` en formularios, tablas ni selectores de columnas de productos, packs, Ecommerce, Producción, DIGEMID o POS.
- Conservar `item_code` opcional en los contratos actuales de base de datos, requests, API e importación mientras sus consumidores vigentes lo necesiten. No justificar campos únicamente por registros históricos inexistentes.
- Usar `Buscar` en acciones de consulta de identidad. No exponer SUNAT/RENIEC ni rotular SENIAT si el proveedor real no corresponde.
- Mostrar `Tipo de cambio del día` sin atribuir BCV mientras `ServiceData::exchange()` siga consumiendo ApiPeru. Sólo mencionar BCV después de migrar y probar la fuente backend efectiva.
- Usar textos fiscales neutrales cuando la autoridad no sea necesaria para comprender la acción.
- Conservar el tipo de documento de identidad `0`: su etiqueta contractual es `Doc.sin.rif`. No eliminarlo, no reasignar sus registros a otro tipo y no volver a mostrar `Doc.trib.no.dom.sin.ruc`.

## Integraciones que se preservan

La emisión peruana SOAP/PFX y las rutas `dispatches/sendSunat`/`sendDispatchToSunat` están retiradas. ApiPeru y los formatos comerciales que mantengan consumidores requieren revisión independiente: no renombrarlos a SENIAT ni retirarlos por coincidencia textual. Aplicar las skills de modalidad fiscal y operación local para la retirada de transporte.

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
5. Confirmar que el seeder crea directamente el ID `0` con la descripción `Doc.sin.rif`, sin migración incremental ni remapeos de datos anteriores.
6. Ejecutar `git diff --check` y `phpunit` relevante. Aplicar `frontend-build`: no compilar por iniciativa propia.
7. Auditar las coincidencias SUNAT/SENIAT restantes: deben corresponder a consumidores vigentes, formatos reales, documentación o marcadores; no mantener compatibilidad con datos anteriores.
8. No incorporar artefactos generados al diff salvo que la política vigente del repositorio lo exija.

## Pruebas de instalación nueva

Las pruebas de etiquetas recorren únicamente componentes vigentes. No restaurar `dispatches/Carrier/Form.vue`, el formulario de regularización de resúmenes, su ayuda ni la migración de renombrado del documento 0: pertenecen a funcionalidad retirada. Verificar el catálogo inicial directamente y mantener las comprobaciones de `Buscar`, tasa de cambio sin atribución falsa y ausencia de rutas de transmisión fiscal.

En `technical-services/form.vue`, mantener los comentarios HTML bien delimitados alrededor de moneda y anticipo. Los delimitadores mal escritos incluían parcialmente el bloque de tipo de cambio y producían cierres de pestañas inválidos. Verificar la plantilla con vue-template-compiler en memoria; no generar un build por iniciativa propia.
