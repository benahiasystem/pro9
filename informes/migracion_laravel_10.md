# Migración de SAB a Laravel 10

La rama `codex/laravel-10` parte de `migration-postgresql` en `d30a4f057ef72a38f34ab7e3a6b8486aea9d395f`. El nuevo lock instala Laravel 10.50.4 y Laravel Modules 10.0.6 sobre PHP 8.2.35. Se conservaron Hyn 5.9.1, PHPUnit 9.6 y Collision 6. PHPUnit 9 sigue siendo necesario para el reportero de la batería PostgreSQL y Collision 6 es la serie compatible con ese ejecutor.

Composer instala Culqi 2.0.4 desde la etiqueta oficial porque Packagist todavía apunta a un commit que ya no se puede descargar. El contenido de esa etiqueta coincide con la copia de la instalación que se utilizó para la línea base. Se verificaron instalaciones limpias con y sin dependencias de desarrollo.

## Resultados comparables

| Verificación | Laravel 9.52.21 | Laravel 10.50.4 |
|---|---:|---:|
| Batería dirigida de PostgreSQL, PDF, ventas y cobros, incluidas las pruebas de métodos de pago | 106 pruebas, 3 fallos previos | 106 pruebas, los mismos 3 fallos |
| Aceptación HTTP de ventas, POS y API | 1.519 aserciones, pasó | 1.523 aserciones, pasó |
| Inventario de rutas con dependencias de desarrollo | 622 | Las mismas 622 |
| Avisos de `composer audit --locked` | 79 | 77 |

La prueba de PostgreSQL confirmó que se conserva la visibilidad de columnas según los permisos de la conexión y que los valores por defecto de los timestamps siguen usando la hora de cada sentencia. También pasaron la recuperación de fechas como Carbon, el preflight CORS, HTTPS detrás del proxy y la invalidación de etiquetas con Redis real. Un worker procesó trabajos de dos tenants en el mismo proceso y limpió el contexto entre ambos.

Los tres fallos compartidos están en la semilla central, el alta de tenants y la restauración integral. Las pruebas esperan un plan que ya no está en la semilla central. La verificación fue dirigida. La batería completa conserva dependencias de evidencias ausentes de la rama base, incluido `casts_exactos_efectivos.json`. `view:cache` también falla en ambas versiones por un directorio de vistas de módulo ausente. Laravel 10 está fuera de soporte de seguridad.

## Preproducción local

Se creó el proyecto Docker `sab-laravel10-preprod`, con red interna, PostgreSQL 17.11, Redis, PHP-FPM, Nginx y un worker propios. La configuración y las evidencias locales están en `storage/app/laravel10-preproduction` del worktree. El entorno usa dos empresas ficticias y correo en memoria.

Se instaló primero Laravel 9 y después Laravel 10, conservando la misma base y el almacenamiento. En ambas versiones, el acceso central funcionó y cada token fue aceptado solo en su propio tenant. Se guardó una factura de prueba por versión con total y cobro de VES 116 e IVA de VES 16. La otra empresa conservó cero facturas. Al restaurar Laravel 9, ambas facturas y sus cobros seguían disponibles, el PDF generado en Laravel 10 se podía descargar y el worker volvió a escribir en la base correcta de cada tenant. Luego se restableció Laravel 10 y se volvieron a comprobar HTTP y el worker.

Al cambiar de artefacto, preparar un directorio nuevo de `bootstrap/cache` antes de ejecutar Artisan. La caché de Laravel 9 contiene referencias a proveedores de desarrollo y puede impedir el arranque del artefacto Laravel 10 instalado con `--no-dev`. El ensayo regeneró el descubrimiento de paquetes y la caché de configuración cada vez que cambió de versión.
