# Migración de SAB a PostgreSQL 17.11

Informe resumido del cierre registrado el 19 de noviembre de 2026 en el worktree `codex/postgresql-supabase-17-11`. Reúne resultados históricos de ese worktree. La preparación de este informe se limitó a cotejar y condensar los archivos de origen.

## Qué cambió

- **Infraestructura y aislamiento.** Imagen Supabase PostgreSQL 17.11 fijada por digest, PHP 8.2 con `pdo_pgsql`, conexiones separadas para la base central y las empresas, y aprovisionamiento recuperable con permisos por empresa.
- **Esquema y compatibilidad.** Adaptación de migraciones, catálogos, consultas SQL, secuencias y reglas de fechas. La verificación documentada contrastó tipos, valores predeterminados y nulabilidad con una referencia temporal de MySQL.
- **Operación comercial.** Conservación de importes y cantidades exactos en facturas, conversiones, cobros, impuestos, anticipos, inventario y documentos impresos. La aceptación de facturas cubrió VES con cobros VES/USD y tasa BCV simulada.
- **Instalación y recuperación.** Instalación desde cero y restauración entre clústeres independientes. El ensayo recuperó 48 tablas centrales, 352 tablas por empresa para dos empresas, archivos, permisos y secuencias.

## Qué quedó verificado

| Control | Resultado registrado |
| --- | --- |
| PHPUnit en PostgreSQL 17.11 temporal | 2.657 pruebas y 88.590 aserciones. El JUnit registra cero fallos, errores u omisiones en las pruebas ejecutadas. |
| JavaScript y navegador | 1.206 pruebas JavaScript y 223 comprobaciones de facturación en Chrome aprobadas. |
| Rendimiento HTTP y reportes | 12 comparaciones de p95 aprobadas. |
| Auditorías estrictas de facturación | La batería terminó con código 1 por siete referencias desactualizadas en el registro de auditoría PHP. Tras actualizarlas, las auditorías PHP y JavaScript de facturación quedaron con cero pendientes y cero diagnósticos dentro de ese alcance. |
| Revalidación dirigida posterior | 242 pruebas y 24.488 aserciones aprobadas según el registro de cierre. Se realizaron en otra instancia temporal y se reportan por separado. |
| Instalación y restauración | Ambos sondeos registraron salida 0 y limpieza de recursos temporales. |

El cierre deja fuera los adjuntos de Contabilidad y las facturas emitidas en USD. La medición del rendimiento del POS quedó aplazada. Las auditorías globales de exactitud conservan hallazgos fuera de facturación.

## Evidencias

- [Extracto JSON verificable](evidencias.json) con códigos de salida, huellas de fuentes, resultados de auditoría y manifiestos de instalación y recuperación. Incluye rutas y SHA-256 de los originales para cotejo en el worktree.
- [JUnit comprimido](phpunit.xml.gz) con el detalle íntegro de PHPUnit de la segunda batería. Su huella, una vez descomprimido, corresponde a la del XML original en el extracto JSON.
- El alcance y las cifras de la revalidación dirigida se documentaron en `informes/postgresql_17/pendientes_verificacion.md` del worktree de origen. Su manifiesto registra salida 0 y la huella de la revisión, y el recuento de esa tanda procede del informe de cierre.
