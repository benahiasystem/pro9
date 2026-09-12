# Contrato de validación previa para importar ítems

## Contenido

- [Orden de ejecución](#orden-de-ejecución)
- [Estructura del libro](#estructura-del-libro)
- [Contrato de filas y base de datos](#contrato-de-filas-y-base-de-datos)
- [Instantánea de catálogos](#instantánea-de-catálogos)
- [Reporte de errores](#reporte-de-errores)
- [Corrección parcial](#corrección-parcial)
- [Respuesta HTTP y descarga](#respuesta-http-y-descarga)
- [Contrato del frontend](#contrato-del-frontend)
- [Marcadores del código fuente](#marcadores-del-código-fuente)
- [Contrato de pruebas](#contrato-de-pruebas)

## Orden de ejecución

1. Validar la petición HTTP:
   - `warehouse_id`: obligatorio, numérico, mínimo 1 y existente en `tenant.warehouses,id`.
   - `file`: archivo obligatorio, extensión o MIME XLSX y máximo 10 MiB.
2. Cargar el archivo como XLSX dejando las fórmulas visibles para el lector.
3. Validar el libro completo y recopilar todos los errores por celda.
4. Si existe al menos un error, no construir ni invocar `ItemsImport`; generar y devolver el reporte de errores.
5. Si no existen errores, ejecutar el `ItemsImport` existente dentro de `DB::connection('tenant')->transaction(...)` y devolver sus datos originales.
6. Registrar las excepciones de generación del reporte y de importación posterior a la validación junto con el identificador del usuario autenticado.

## Estructura del libro

- Exigir exactamente una hoja.
- Exigir al menos una fila de datos no vacía después de la fila 1.
- Exigir estos encabezados en orden exacto en los índices `0..19`:

| Índice | Encabezado |
|---:|---|
| 0 | `Nombre` |
| 1 | `Código Interno` |
| 2 | `Modelo` |
| 3 | `Código` |
| 4 | `Código Tipo de Unidad` |
| 5 | `Código Tipo de Moneda` |
| 6 | `Precio Unitario Venta` |
| 7 | `Codigo Tipo de Afectación del Iva Venta` |
| 8 | `Tiene Iva` |
| 9 | `Precio Unitario Compra` |
| 10 | `Codigo Tipo de Afectación del Iva Compra` |
| 11 | `Stock` |
| 12 | `Stock Mínimo` |
| 13 | `Categoria` |
| 14 | `Marca` |
| 15 | `Descripcion` |
| 16 | `Nombre secundario` |
| 17 | `Código lote` |
| 18 | `Fec. Vencimiento` |
| 19 | `Cód barras` |

- Permitir el índice `20` para la URL de imagen del importador actual. Si alguna fila contiene datos en esa columna, exigir `URL Imagen` en U1. Si la columna no se utiliza, puede omitirse. No aceptar imágenes sin encabezado por compatibilidad anterior.
- Rechazar cualquier encabezado o celda de datos poblada después del índice `20`.
- Rechazar celdas con fórmula aunque su valor calculado parezca válido.

## Contrato de filas y base de datos

| Índice | Campo importado | Validación |
|---:|---|---|
| 0 | `description` | texto escalar obligatorio, máximo 600 caracteres |
| 1 | `internal_id` | texto escalar anulable, máximo 30; único dentro del libro; determina actualización o creación |
| 2 | `model` | texto escalar anulable, máximo 100 |
| 3 | `item_code` | código SUNAT anulable de exactamente 8 dígitos numéricos, conforme al importador actual de Pro9 |
| 4 | `unit_type_id` | identificador obligatorio, presente y activo en el catálogo de unidades del tenant; usar `UND` para productos y `SERV` para servicios; rechazar `NIU` y `ZZ` |
| 5 | `currency_type_id` | identificador obligatorio, presente y activo en el catálogo de monedas del tenant; Venezuela usa `VES`, no `VED` |
| 6 | `sale_unit_price` | `decimal(16,6)` obligatorio y estrictamente mayor que cero |
| 7 | `sale_affectation_igv_type_id` | identificador activo de afectación de IVA obligatorio |
| 8 | entrada de `has_igv` | `SI` o `NO` obligatorio, sin distinguir mayúsculas y minúsculas |
| 9 | `purchase_unit_price` | `decimal(16,6)` anulable, mínimo cero; el importador convierte un valor vacío en cero |
| 10 | `purchase_affectation_igv_type_id` | identificador activo de afectación de IVA obligatorio porque la columna efectiva de base de datos no admite nulos |
| 11 | `stock` | `decimal(16,4)` obligatorio, mínimo cero; estrictamente mayor que cero si `internal_id` ya existe, porque una actualización crea un movimiento de inventario |
| 12 | `stock_min` | `decimal(12,2)` obligatorio, mínimo cero |
| 13 | nombre de categoría | texto escalar anulable, máximo 255 |
| 14 | nombre de marca | texto escalar anulable, máximo 255 |
| 15 | `name` | texto escalar anulable, máximo 1000 en el esquema efectivo de Pro9 |
| 16 | `second_name` | texto escalar anulable, máximo 600 |
| 17 | código de lote | texto escalar anulable, máximo 255 |
| 18 | `date_of_due` | serial positivo de fecha de Excel anulable; obligatorio si existe código de lote; rechazar fechas textuales |
| 19 | `barcode` | texto escalar anulable, máximo 150 |
| 20 | URL de imagen | URL válida anulable, máximo 2048 y esquema únicamente HTTP o HTTPS |

Aceptar valores numéricos solo cuando quepan en la precisión y escala efectivas y usen punto como separador decimal. Rechazar booleanos y valores no escalares cuando se espere texto o número. Ignorar filas totalmente vacías. Agrupar mensajes duplicados para una misma coordenada de fila y columna.

## Instantánea de catálogos

Leer solamente identificadores activos de `cat_unit_types`, `cat_currency_types` y `cat_affectation_igv_types` del tenant. Leer todos los valores no vacíos de `items.internal_id` para detectar actualizaciones y códigos repetidos. Comparar identificadores como cadenas sin espacios exteriores.

Para Venezuela, `UND` y `SERV` son los códigos canónicos. `NIU` y `ZZ` no forman parte del catálogo activo y deben producir un error de validación, sin conversión automática.

## Reporte de errores

- Generar el reporte a partir del libro exacto que cargó el usuario.
- Preservar hojas, valores, dimensiones, formato ordinario y comentarios ajenos al validador.
- Convertir cada celda con fórmula a texto literal antes de guardar para impedir que el reporte ejecute fórmulas cargadas.
- Para cada coordenada inválida actual:
  - aplicar relleno sólido `FFFFA6A6`;
  - sustituir o crear un comentario cuyo autor sea `Benahia`;
  - comenzar el comentario con `A corregir:\n`;
  - enumerar todos los mensajes agrupados como `- mensaje`;
  - usar un cuadro legible de 300 por 150 píxeles.
- Guardar en el disco privado local bajo `item-import-validation/{authenticated_user_id}/{uuid}.xlsx`.

## Corrección parcial

Los editores de hojas de cálculo pueden reescribir el autor y los saltos de línea de los comentarios. Antes de pintar el resultado más reciente:

1. Normalizar CRLF y CR a LF e ignorar un posible BOM UTF-8 al inicio del texto del comentario.
2. Considerar propiedad del validador un comentario cuyo texto normalizado comience exactamente con `A corregir:\n- `; no depender del autor.
3. Eliminar ese comentario del validador.
4. Quitar su relleno solamente si es sólido y exactamente `FFFFA6A6`.
5. Aplicar únicamente las coordenadas de error de la validación más reciente.

Esto garantiza que un valor corregido, como `VED` cambiado al identificador activo `VES`, pierda su marca anterior. Si otro valor continúa inválido, solo esa celda pendiente debe aparecer en el siguiente reporte. Preservar comentarios sin la firma del validador y rellenos ajenos al validador.

## Respuesta HTTP y descarga

Ante un fallo de validación, devolver:

```json
{
  "success": false,
  "validation_failed": true,
  "message": "Se encontraron errores en el archivo. Corríjalos antes de importar.",
  "validation_url": "/items/import/validation/{uuid}",
  "errors_count": 1,
  "rows_count": 1
}
```

Devolver `validation_url: null` solamente si falla la creación del reporte y mostrar ese fallo expresamente en el cliente. Generar una URL relativa para conservar el host y el esquema del tenant actual.

`errors_count` cuenta coordenadas inválidas agrupadas, no la cantidad de mensajes individuales. `rows_count` cuenta las filas de datos no vacías evaluadas después del encabezado. Una importación satisfactoria conserva `success: true`, el mensaje traducido de carga satisfactoria y `data` del importador existente. Una excepción de importación posterior a la validación devuelve `success: false` con su mensaje, se registra y revierte todos los cambios mediante la transacción del tenant.

La ruta GET autenticada debe:

- restringir `{token}` a un UUID;
- reconstruir la ruta con el identificador del usuario autenticado actual;
- devolver 404 para un token inválido, un token perteneciente a otro usuario o un reporte inexistente;
- transmitir `VALIDACION_ITEMS_{uuid}.xlsx` como adjunto;
- eliminar el archivo privado después de enviarlo mediante `deleteFileAfterSend(true)`.

## Contrato del frontend

- Aceptar la selección de un solo archivo `.xlsx` y exigir almacén y archivo antes de enviar.
- Mantener activo el estado de procesamiento hasta que terminen las funciones de respuesta satisfactoria o error de la carga.
- Tras una importación satisfactoria, mostrar éxito, recargar datos y tablas, limpiar el archivo y cerrar el diálogo.
- Ante un fallo de validación con URL, solicitar inmediatamente `validation_url` mediante GET autenticado y `responseType: 'blob'`.
- Rechazar un archivo binario vacío.
- Leer `Content-Disposition` cuando esté disponible y usar `VALIDACION_ITEMS.xlsx` como alternativa.
- Crear una URL de objeto, activar un enlace temporal con atributo `download`, retirar el enlace y revocar la URL de objeto después del clic.
- Mostrar éxito solo después de obtener el reporte y mostrar un mensaje específico cuando falle la generación o la descarga.

## Marcadores del código fuente

Envolver los cambios PHP y JavaScript/Vue pertenecientes a esta tarea entre comentarios que contengan:

```text
########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
```

Usar la sintaxis de comentario válida para cada lenguaje. No añadir estos marcadores a binarios XLSX, JSON estricto, manifiestos de Vite ni paquetes generados.

## Contrato de pruebas

Mantener cobertura automatizada para:

- encabezados principales y opcionales exactos;
- imagen con encabezado vacío rechazada en U1 y el mismo libro aceptado al añadir `URL Imagen`;
- encabezados cambiados o desplazados y columnas adicionales pobladas;
- longitud, tipo, precisión, escala, nulabilidad y mínimo de cada valor importado conforme a la base de datos;
- pertenencia a catálogos activos y rechazo de `VED` en favor de `VES`;
- serial de fecha Excel válido y dependencia entre lote y fecha;
- identificadores internos duplicados y stock positivo al actualizar;
- libros dañados, vacíos, con varias hojas o fórmulas;
- orden validación-antes-de-importación y contrato de transacción del tenant;
- relleno rojo, comentarios agrupados y neutralización de fórmulas;
- corrección parcial en dos pasadas después de que Office reescriba autor y saltos de línea;
- eliminación de marcas antiguas ya corregidas y conservación de comentarios y rellenos ajenos;
- URL relativa del reporte y comportamiento autenticado de archivo binario y URL de objeto en el frontend;
- nombre del adjunto, MIME XLSX, bytes exactos no vacíos, firma ZIP, asociación al usuario y eliminación después de enviar;
- manifiesto y paquete de Vite de producción con el comportamiento de descarga.
- ejecutar siempre el contrato de ruta UUID; verificar el bundle por separado cuando haya manifiesto, dejando la omisión explícita si todavía no fue compilado. Aplicar `frontend-build` sin compilar por iniciativa propia.

Ejecutar las pruebas específicas `ItemImport` y toda la suite unitaria. Inspeccionar un XLSX generado tanto estructural como visualmente. Una prueba limitada al código fuente no basta para validar el recorrido por Office ni el comportamiento del recurso de producción.
