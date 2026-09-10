---
name: migrar-venezuela-geopolitica
description: Reemplazar catálogos y referencias geográficas de Perú por Venezuela. Usar al trabajar con country_id, countries, departments, provinces, districts, estados, municipios, parroquias, etiquetas del cascader, caché territorial, zona horaria, direcciones o datos semilla de ubicación en Pro9.
---

<!-- ######## INICIO SKILL GEOPOLITICO VENEZUELA -->
# Migrar geopolítica de Venezuela

## Flujo

1. Auditar el esquema y contar referencias existentes antes de modificar catálogos.
2. Conservar los nombres internos `departments`, `provinces` y `districts`; mostrar Estado, Municipio y Parroquia en interfaz.
3. Conservar la estructura efectiva en migraciones consolidadas por tabla y restaurar las filas territoriales mediante `TenantMigrationDataSeeder`.
4. Definir directamente VE y la ubicación inicial venezolana en los datos semilla. No transformar direcciones de instalaciones anteriores.
5. Cargar sólo la jerarquía venezolana; no crear registros peruanos para borrarlos después.
6. Codificar los identificadores dentro del ancho heredado: estados con 2 dígitos, municipios con 4 y parroquias con 6.
7. Mostrar sólo la descripción de la Parroquia; conservar el identificador de seis dígitos únicamente como `value`, sin anteponerlo a la etiqueta.
8. Aplicar la misma etiqueta limpia en clientes, suscripciones y cualquier cascader duplicado.
9. Filtrar y cachear el árbol por tenant y país con una clave versionada, por ejemplo `locations:v2:{tenant}:{country}`. Evitar una clave global compartida y cambiar la versión cuando cambie la forma de las opciones.
10. Construir el árbol exclusivamente mediante consultas a base de datos y no leer archivos territoriales en runtime.
11. Resolver descripciones normalizando mayúsculas, espacios y acentos; exigir una coincidencia única dentro del padre y lanzar error ante inexistencia o ambigüedad.
12. Configurar `America/Caracas` y usar `000619` como ubigeo inicial de importación.
13. No conservar `ExistingTenantMigrator` ni comandos de conversión. Verificar únicamente instalaciones nuevas en bases temporales.

## Contrato de datos

- Esperar 25 estados, 335 municipios y 1138 parroquias.
- Usar Miranda/Chacao/Chacao como ubicación inicial: `14`, `0229`, `000619`.
- No dejar registros geográficos PE en la base después de la migración.
- Resolver nombres junto con su jerarquía; detectar ambigüedades en vez de elegir el primer resultado.
- Conservar el catálogo internacional de países sólo cuando sea necesario para nacionalidades; la migración territorial final debe eliminar PE del estado efectivo del tenant.
- Verificar que `TenantMigrationDataSeeder` cargue `database/seeders/data/venezuela_geopolitical_data.php` con los conteos esperados.
- Mantener migraciones consolidadas independientes para `countries`, `departments`, `provinces` y `districts`, con sus claves foráneas en la migración final.

## Validación

- Probar conteos, relaciones padre-hijo, ubicación inicial e idempotencia.
- Confirmar que no queden referencias PE, defaults PE, códigos geográficos peruanos ni archivos de datos territoriales leídos por la aplicación.
- Confirmar que una opción como Macarapana se renderice `MACARAPANA`, nunca `000781 - MACARAPANA`.
- Probar resolución jerárquica Estado/Municipio/Parroquia con nombres acentuados y casos ambiguos.
- Confirmar aislamiento y renovación de caché entre tenants.
- Ejecutar migraciones y pruebas dentro del contenedor PHP del proyecto.
- Verificar el estado inicial: 239 países sin PE, 25/335/1138, default `VE` en `origin_addresses` y referencias iniciales `14/0229/000619`.
<!-- ######## FIN SKILL GEOPOLITICO VENEZUELA -->
