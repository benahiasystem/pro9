---
name: migrate-product-import-excel-format
description: Mantener, migrar, validar previamente y verificar el flujo Laravel de importación masiva de ítems basado en `public/formats/items.xlsx`. Usar al cambiar columnas o reglas de tipos de base de datos del Excel de productos, validar antes de `ItemsImport`, generar o descargar automáticamente reportes XLSX de errores, revalidar reportes parcialmente corregidos o probar el controlador, las rutas, el diálogo Vue, los catálogos del tenant, el almacén y el paquete de producción de Vite.
---

# Mantener la importación Excel de ítems

Conservar la lógica existente de persistencia de ítems y ejecutar una validación completa antes de que cualquier fila llegue a `ItemsImport`.

## Lectura obligatoria

Leer completamente [references/contrato-validacion-previa.md](references/contrato-validacion-previa.md) antes de modificar este flujo. Allí están los contratos exactos de columnas, tipos de base de datos, respuestas HTTP, reporte de errores, seguridad, revalidación parcial, descarga automática y pruebas.

## Mapa de implementación

Usar estos archivos como fuente de verdad en `Pro9`:

| Responsabilidad | Archivo |
|---|---|
| Plantilla descargable | `public/formats/items.xlsx` |
| Mapeo de persistencia existente; conservar intacto | `app/Imports/ItemsImport.php` |
| Validación previa HTTP, transacción, respuesta de error y descarga | `app/Http/Controllers/Tenant/ItemController.php` |
| Carga y descarga automática del archivo binario | `resources/js/views/tenant/items/import.vue` |
| Ruta POST de importación y ruta GET autenticada de descarga | `routes/web.php` |
| Contrato posicional y de base de datos | `app/Support/ItemImport/ItemImportContract.php` |
| Validación de todo el libro | `app/Support/ItemImport/ItemImportWorkbookValidator.php` |
| Catálogos activos e identificadores internos existentes | `app/Support/ItemImport/ItemImportCatalogs.php` |
| Resultado de validación | `app/Support/ItemImport/ItemImportValidationResult.php` |
| Generación del reporte y almacenamiento privado | `app/Support/ItemImport/ItemImportValidationWorkbook.php` |
| Contratos automatizados | `tests/Unit/ItemImport*.php` |

## Flujo de trabajo

1. Leer la tarea de migración y revisar `git status --short --branch`. Preservar los cambios preexistentes y no aplicar commits históricos de forma mecánica.
2. Inspeccionar la plantilla, el importador, el diálogo, el controlador, las rutas, el esquema efectivo de `items` y los catálogos activos del tenant. Tratar los índices del importador y el esquema efectivo de base de datos como autoridad.
3. Mantener el almacén fuera del libro. Enviar `warehouse_id` desde el diálogo y validar que exista en `tenant.warehouses`.
4. Conservar las 20 posiciones principales y la URL de imagen opcional en la posición 20. No desplazar posiciones existentes.
5. Validar la petición y el XLSX completo antes de construir o invocar `ItemsImport`. Si existe algún error, importar cero filas.
6. Crear el reporte a partir del libro cargado, quitar las marcas antiguas del validador, neutralizar fórmulas, aplicar únicamente los errores actuales, almacenarlo de forma privada por usuario autenticado y UUID, y devolver una URL relativa del mismo origen.
7. Hacer que el cliente Vue solicite inmediatamente esa URL como un archivo binario autenticado no vacío, obtenga el nombre del adjunto, lo descargue mediante una URL de objeto y comunique explícitamente cualquier fallo de descarga.
8. Cuando la validación sea satisfactoria, invocar el `ItemsImport` intacto dentro de una transacción de la base de datos del tenant y conservar sus datos de respuesta.
9. Compilar la aplicación Vite cuando el diseño del tenant sirva recursos de producción. Confirmar que `public/build/manifest.json` apunte a un paquete que contenga el comportamiento de descarga. No editar manualmente archivos generados.
10. Ejecutar pruebas específicas y completas, validar sintaxis PHP, validar este skill, inspeccionar el XLSX generado y ejecutar `git diff --check` antes de entregar.

## Invariantes

- Mantener `app/Imports/ItemsImport.php` sin cambios salvo que el usuario solicite expresamente cambiar el mapeo de importación.
- Mantener las columnas `0..19` por posición y permitir la columna `20` solamente como campo opcional `URL Imagen`.
- Usar `VES`, nunca `VED`, como identificador de moneda de Venezuela.
- Validar identificadores activos de unidad, moneda y afectación de IVA contra los catálogos actuales del tenant.
- Rechazar fórmulas, archivos XLSX dañados, varias hojas, encabezados desplazados, libros vacíos, identificadores internos duplicados, columnas adicionales pobladas y valores incompatibles con precisión, escala, nulabilidad o longitud de base de datos.
- Agrupar los mensajes por celda para que cada coordenada inválida reciba un solo comentario con todos sus errores actuales.
- No importar el subconjunto válido de un libro inválido.
- Asociar cada descarga al usuario autenticado y a un UUID opaco. No exponer ni aceptar rutas del sistema de archivos.
- Eliminar el reporte privado después de transmitirlo correctamente.
- Envolver los cambios de código fuente pertenecientes a esta tarea entre comentarios válidos para el lenguaje que contengan `########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS` y `########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS`. No insertar marcadores en JSON, manifiestos ni binarios XLSX.

## Invariante de revalidación parcial

Antes de aplicar los errores actuales, eliminar solamente comentarios cuya firma normalizada comience con `A corregir:\n- `. No identificar comentarios del validador por autor, porque Excel y LibreOffice pueden sustituir `Benahia` por valores como `Autoría desconocida` y convertir los saltos de línea a CRLF.

Quitar un relleno anterior únicamente cuando sea sólido y exactamente `FFFFA6A6`. Preservar comentarios, colores, formatos, valores, dimensiones y estructura ajenos al validador. Una celda corregida no debe conservar el comentario ni el relleno rojo del validador; un libro parcialmente corregido debe contener solamente los errores pendientes devueltos por la validación más reciente.

## Verificación

Ejecutar en el repositorio objetivo y adaptar solo el nombre del contenedor PHP cuando el entorno sea distinto:

```bash
find app/Support/ItemImport tests/Unit -type f -name 'ItemImport*.php' -print0 | xargs -0 -n1 php -l
php -l app/Http/Controllers/Tenant/ItemController.php
php -l routes/web.php
docker exec pro9-php vendor/bin/phpunit tests/Unit --filter ItemImport
docker exec pro9-php vendor/bin/phpunit tests/Unit
npm run build
python3 /home/benahia/.codex/skills/.system/skill-creator/scripts/quick_validate.py .codex/skills/migrate-product-import-excel-format
git diff --check
```

Inspeccionar además un libro en dos pasadas: generar al menos dos errores, guardarlo mediante un editor compatible con Office, corregir solo un valor y volver a validarlo. Demostrar que el siguiente reporte contiene únicamente la coordenada pendiente, que la celda corregida no conserva comentario ni relleno rojo del validador y que las anotaciones del usuario permanecen intactas.

## Criterios de aceptación

- La validación siempre ocurre antes de `ItemsImport`.
- Un libro inválido no persiste cambios de ítems ni inventario.
- Un libro válido conserva el comportamiento original dentro de una transacción del tenant.
- El reporte conserva los datos cargados, marca todas y solo las celdas actualmente inválidas, adjunta todos sus mensajes vigentes y no contiene fórmulas ejecutables.
- La descarga automática realiza una segunda petición GET autenticada y recibe un adjunto XLSX no vacío.
- La corrección parcial funciona aunque Excel o LibreOffice reescriban el autor y los saltos de línea de los comentarios.
- El paquete activo de producción contiene el comportamiento implementado en el frontend.
- Pasan las pruebas específicas, la suite unitaria completa, la validación de sintaxis PHP, la inspección del libro, la validación del skill y la comprobación del diff.
