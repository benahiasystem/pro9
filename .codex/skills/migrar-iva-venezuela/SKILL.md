---
name: migrar-iva-venezuela
description: Mantener y verificar el IVA venezolano para instalaciones nuevas de Pro9. Usar al cambiar tasa, cálculos fiscales, afectaciones, Store, POS, ecommerce, documentos, compras, reportes, PDFs o importaciones.
---

# Mantener el IVA venezolano en Pro9

## Contrato

- Mostrar `IVA`, `%IVA`, `Tipo %IVA`, `Incluye IVA`, `Total IVA` o `T.IVA` según el espacio disponible.
- Conservar los nombres internos `igv`, `has_igv`, `purchase_has_igv`, `total_igv`, `percentage_igv` y `affectation_igv_type_id`; son contratos vigentes de esquema, API y componentes.
- Usar `config/venezuela.php` y `App\Support\Venezuela\Localization` como fuente de verdad: tasa `0.16`, porcentaje `16` y multiplicador `1.16`.
- Los flujos gravados preparan el porcentaje y calculan `total_igv` con la tasa de `Localization` antes de guardar: por defecto 16 %; una tasa configurada debe prevalecer. Los modelos conservan esos campos para persistir los valores.
- Ofrecer en nuevas selecciones únicamente `10 = Gravado` y `20 = Exento`.
- No conservar códigos de afectación retirados, remapeos de instalaciones anteriores ni cálculos de ISC/bolsas. El esquema y los datos iniciales utilizan el contrato vigente.
- Mantener exportaciones o integraciones externas con encabezados contractuales IGV cuando el consumidor los exija literalmente; documentar cada excepción.
- No presentar esta personalización como certificación normativa ni como integración oficial con SENIAT.

## Cambios de datos

1. Verificar el catálogo y los consumidores actuales.
2. Modificar el consolidado y los datos iniciales, sin migraciones incrementales ni bases reales.
3. Sembrar únicamente `10` y `20`, activos, no gratuitos y no exportación.
4. Validar las entradas contra esos códigos; no traducir silenciosamente códigos retirados.
5. Verificar todas las claves foráneas del seeding. El servicio PENALIDAD asociado al motivo retirado `13` no se siembra ni se expone por un endpoint especial.

## Implementación

1. Leer la tarjeta y el informe canónico de Pro6 completo; usar los parches como evidencia, nunca con `git apply`, `git am` o `cherry-pick`.
2. Auditar literales visibles y consumidores de la tasa en Store, POS, ecommerce, ítems, documentos, compras, suscripciones, restaurante, importaciones, PDFs y reportes.
3. Obtener la tasa backend mediante `Localization::taxRate()`, `taxPercentage()` o `taxMultiplier()`; evitar nuevos `0.16`, `16` o `1.16` dispersos.
4. Hacer que el frontend cargue la tasa desde `/store/get_igv`; un fallback de arranque debe ser `0.16` y no puede prevalecer sobre la respuesta del backend.
5. Mantener la estructura vigente: `calculateRowItem` prepara el porcentaje y los importadores/controladores existentes usan `Localization`; no crear traits de guardado ni una segunda capa fiscal paralela.
6. Cambiar sólo presentación cuando el requisito sea terminológico. No renombrar identificadores internos ni alterar booleanos o payloads.
7. No editar bundles, mapas, minificados, manifests, vendor ni dependencias manualmente. Aplicar `frontend-build`: verificar fuentes sin ejecutar compilaciones por iniciativa propia.
8. Delimitar cada unidad modificada con comentarios válidos que contengan exactamente `########## INICIO CAMBIO IGV A IVA`/`######### FIN CAMBIO IGV A IVA` para textos y `########## INICIO CAMBIO AFECTACIÓN IVA`/`######### FIN CAMBIO AFECTACIÓN IVA` para tasa o catálogo.

## Verificación

- Probar una operación gravada: base `100`, IVA `16`, total `116`.
- Probar una operación exenta: base y total iguales, IVA `0`.
- Verificar que el payload gravado preparado para persistencia contiene porcentaje `16`, que los modelos de operación conservan `percentage_igv`/`total_igv` como campos guardables y que no quedan tasas activas `18`, `0.18` o `1.18` en los flujos corregidos.
- Confirmar que el catálogo devuelve sólo 10/20 y que entradas con códigos retirados se rechazan.
- Verificar instalación nueva, integridad referencial, rollback y repetición del esquema/datos iniciales.
- Buscar variantes visibles `Incluye Igv`, `Tiene Igv`, `Afectación Igv`, `Total IGV`, `T.IGV`, `IGV:` y clasificarlas.
- Ejecutar `VenezuelaIvaContractTest`, `VendeyaDocumentPayloadNormalizerTest`, las pruebas Venezuela pertinentes y lint PHP. Las pruebas se ejecutan en bases temporales o memoria, nunca sobre tenants reales.
- Auditar el diff para marcadores balanceados. Dejar la compilación a cargo del usuario conforme a `frontend-build`.

## Retirada de compatibilidad anterior

- El catálogo inicial contiene exactamente `10 = Gravado` y `20 = Exento`; comprobar también las afectaciones de compra y venta de los productos semilla. No ejecutar la migración retirada `2026_08_18_000331_configure_venezuela_iva.php`.
- `VendeyaDocumentPayloadNormalizer` admite VES/USD y rechaza monedas retiradas o ausentes. Rechaza afectaciones distintas de 10/20; calcula líneas gravadas y exentas y suma sus importes usando la tasa configurada. No conserva una rama de cálculo para afectaciones inafectas retiradas.
- En el pago del estacionamiento hay un único resumen de IVA con porcentaje dinámico. Se retira la duplicación que existía para el impuesto a bolsas, conservando subtotal, descuento, total y monto del IVA.
- Las plantillas UBL de emisión están retiradas; las pruebas no deben exigir XML SUNAT. Conservar la validación sintáctica de las plantillas PDF y reportes vigentes.
- Verificar Vende Ya con base 100/IVA 16/total 116, mezcla de gravado y exento en USD, una tasa configurada distinta de la predeterminada y rechazo explícito de PEN/VED y afectaciones retiradas.
- `resolveSelectableAffectationType` resuelve sólo el ID solicitado 10/20 cuando existe en el catálogo recibido; devuelve null para códigos desconocidos o ausentes. Nunca elegir otro tratamiento como fallback. Los formularios de Factura, cotización y Nota de venta muestran un error antes de añadir una línea inválida.
- `calculateRowItem` rechaza afectaciones fuera de 10/20 y no mantiene ramas de inafectas ni gratuidad de catálogos retirados. Ejecutar `node --test tests/js/fiscal-row.test.cjs`: carga la fuente en memoria, sin build, y comprueba gravado, exento, tasa configurable, VES/USD, descuento sobre base y rechazo de códigos retirados.
- El selector de ítems de documentos no conserva `presetItemId`/`hasPresetItem` para el servicio de penalidad retirado ni `isCreditNoteAndType03`/`isNoteErrorDescription` para permitir precio cero en el motivo retirado 03. Mantener precio unitario mayor a cero y la edición normal de una línea existente.
