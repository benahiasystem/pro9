---
name: migrar-iva-venezuela
description: Migrar, mantener y verificar el contrato de IVA venezolano en Pro9. Usar al cambiar etiquetas IGV/IVA, tasa general, cálculos fiscales, tipos de afectación, catálogos, Store, POS, ecommerce, documentos, compras, reportes, PDFs, importaciones o tenants históricos.
---

# Mantener el IVA venezolano en Pro9

## Contrato

- Mostrar `IVA`, `%IVA`, `Tipo %IVA`, `Incluye IVA`, `Total IVA` o `T.IVA` según el espacio disponible.
- Conservar los nombres internos heredados `igv`, `has_igv`, `purchase_has_igv`, `total_igv`, `percentage_igv` y `affectation_igv_type_id`; son contratos de esquema, XML, API y componentes.
- Usar `config/venezuela.php` y `App\Support\Venezuela\Localization` como fuente de verdad: tasa `0.16`, porcentaje `16` y multiplicador `1.16`.
- Los flujos gravados deben preparar `percentage_igv = 16` y calcular `total_igv` con la tasa `0.16` antes de guardar; los modelos conservan los campos heredados actuales para persistir esos valores.
- Ofrecer en nuevas selecciones únicamente `10 = Gravado` y `20 = Exento`.
- Conservar otros códigos de afectación para leer documentos históricos. No borrarlos ni convertir referencias existentes.
- Mantener exportaciones o integraciones externas con encabezados contractuales IGV cuando el consumidor los exija literalmente; documentar cada excepción.
- No presentar esta personalización histórica como certificación normativa ni como integración oficial con SENIAT.

## Cambios de datos

1. Medir referencias existentes antes de modificar catálogos fiscales.
2. Crear una migración tenant incremental e idempotente; no editar una migración histórica ya ejecutada.
3. Asegurar `10` y `20` activos, no gratuitos y no exportación, sin desactivar ni borrar filas históricas referenciadas.
4. Restringir la consulta de opciones nuevas en la capa central del modelo; las relaciones de documentos históricos deben seguir resolviendo cualquier ID existente.
5. Ejecutar el puente de migración del tenant histórico después de un respaldo recuperable.

## Implementación

1. Leer la tarjeta y el informe canónico de Pro6 completo; usar los parches como evidencia, nunca con `git apply`, `git am` o `cherry-pick`.
2. Auditar literales visibles y consumidores de la tasa en Store, POS, ecommerce, ítems, documentos, compras, suscripciones, restaurante, importaciones, PDFs y reportes.
3. Obtener la tasa backend mediante `Localization::taxRate()`, `taxPercentage()` o `taxMultiplier()`; evitar nuevos `0.16`, `16` o `1.16` dispersos.
4. Hacer que el frontend cargue la tasa desde `/store/get_igv`; un fallback de arranque debe ser `0.16` y no puede prevalecer sobre la respuesta del backend.
5. Mantener la estructura vigente: `calculateRowItem` prepara el porcentaje y los importadores/controladores existentes usan `Localization`; no crear traits de guardado ni una segunda capa fiscal paralela.
6. Cambiar sólo presentación cuando el requisito sea terminológico. No renombrar identificadores internos ni alterar booleanos o payloads.
7. No editar bundles, mapas, minificados, manifests, vendor ni dependencias manualmente. Validar mediante el build de fuentes y conservar la salida sólo cuando el repositorio la versiona.
8. Delimitar cada unidad modificada con comentarios válidos que contengan exactamente `########## INICIO CAMBIO IGV A IVA`/`######### FIN CAMBIO IGV A IVA` para textos y `########## INICIO CAMBIO AFECTACIÓN IVA`/`######### FIN CAMBIO AFECTACIÓN IVA` para tasa o catálogo.

## Verificación

- Probar una operación gravada: base `100`, IVA `16`, total `116`.
- Probar una operación exenta: base y total iguales, IVA `0`.
- Verificar que el payload gravado preparado para persistencia contiene porcentaje `16`, que los modelos de operación conservan `percentage_igv`/`total_igv` como campos guardables y que no quedan tasas activas `18`, `0.18` o `1.18` en los flujos corregidos.
- Confirmar que catálogo nuevo devuelve sólo 10/20 y que relaciones históricas con otros IDs continúan legibles.
- Verificar migración idempotente y un `down()` que sólo revierta valores reconocibles y seguros.
- Buscar variantes visibles `Incluye Igv`, `Tiene Igv`, `Afectación Igv`, `Total IGV`, `T.IGV`, `IGV:` y clasificarlas.
- Ejecutar `VenezuelaIvaContractTest`, las pruebas Venezuela existentes, lint PHP y build frontend.
- Auditar el diff para marcadores balanceados; si `public/build` está versionado, confirmar que sus hashes y manifest provienen exclusivamente de `npm run build`.
