# Catálogo demo de productos

`iittala-catalog.json` es el set de datos que alimenta los productos modelo de los
tenants de demostración. Los datos (nombres originales, ficha técnica, EAN, precios
de lista y fotos) se tomaron de la web pública de Iittala; las descripciones y los
nombres comerciales están redactados en español y los precios se convirtieron a
soles a partir del precio de lista en GBP (`meta.exchange_rate`).

> Solo para ambientes de demo. Las fotos son material de la marca original: no se
> deben usar en producción ni en tenants de clientes reales.

## Estructura

```
meta: { source, currency_source, exchange_rate, products, images }
products[]:
  sku, internal_id ("IIT-<sku>"), item_code, barcode (EAN)
  description   -> "Nombre" del producto en la ficha
  name          -> "Descripción" larga en español
  second_name   -> nombre original de fábrica (en inglés)
  model         -> colección     line -> línea comercial
  technical_specifications, category, brand, designer
  unit_type_id, currency_type_id, sale_unit_price, purchase_unit_price,
  percentage_of_profit, has_igv, stock, stock_min
  images[]      -> URLs de la galería; la primera es la foto principal
```

## Categorías y líneas

El catálogo usa 9 categorías, y la línea es el agrupador grueso por encima de ellas:

| Línea | Categoría | Productos |
|---|---|---|
| Mesa | Cristalería | 7 |
| Mesa | Vajilla | 10 |
| Mesa | Cubertería | 5 |
| Mesa | Artículos para servir | 7 |
| Cocina | Ollas y sartenes | 5 |
| Decoración | Jarrones y maceteros | 6 |
| Decoración | Velas y candelabros | 5 |
| Decoración | Decoración e iluminación | 9 |
| Textiles | Textiles del hogar | 3 |

## Comandos

### 1. Importar el catálogo (productos nuevos)

```bash
php artisan demo:catalog-import --tenant=demo.pro71.test
```

Crea/actualiza los productos con `internal_id` `IIT-<sku>`, la marca, las
categorías, el stock por almacén (con su kardex inicial), la foto principal y la
galería de fotos adicionales en `item_images`.

Opciones: `--file=`, `--limit=N`, `--skip-images`, `--force-images`,
`--overwrite` (refresca textos y precios de los ya importados), `--dry-run`.

### 2. Completar solo las fotos de los productos existentes

```bash
php artisan demo:items-fill-images --tenant=demo.pro71.test
```

Asigna una foto a los productos que están con `imagen-no-disponible.jpg`
**sin tocar nombre, descripción, código interno, precios ni stock** — esos
productos se usan como ejemplos de listas de precios con y sin IGV. La foto se
elige por afinidad con el nombre del producto (un plato para "Plato con
servicio", una lámpara para "Habitación Cuarto simple"…) y, cuando no hay
coincidencia, se reparte sin repetir. Las variaciones heredan la foto de su
producto padre.

Opciones: `--all` (reasignar también a los que ya tienen foto), `--gallery`
(crear además la galería), `--include-catalog`, `--force-images`, `--dry-run`.
Con `-v` lista qué foto recibió cada producto.

`--tenant` acepta id, uuid o dominio, y varios separados por coma. Las imágenes
se guardan en `storage/app/public/uploads/items/` como `iittala-<sku>-<n>.jpg`
junto a sus variantes `_medium` y `_small`, y no se vuelven a descargar si ya
existen.
