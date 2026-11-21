# Migración de SAB a Tenancy 2.5

La rama `codex/tenancy-2` parte de `c745cafb`. El commit de código `e0401ff5` instala `tenancy/tenancy` v2.5.0 y retira `hyn/multi-tenant`. El commit `9d7d61de` ajusta la aceptación HTTP al alcance de documentos VES. Se verificó Composer con PHP 8.2, dependencias de desarrollo y una instalación limpia sin ellas.

SAB conserva las tablas `websites` y `hostnames`, los dominios, las conexiones `system` y `tenant`, los roles PostgreSQL, el almacenamiento por empresa y el identificador de cola `website_id`. Tenancy 2 aplica la conexión al cambiar el contexto. Los servicios de SAB siguen ejecutando el aprovisionamiento, la recuperación y las semillas. El registro de rutas por dominio se comprobó con PHP-FPM. El middleware rechaza una petición que intenta pasar por las rutas ya registradas para otro dominio.

## Preproducción

El proyecto Docker `sab-tenancy2-preprod` quedó ejecutando Tenancy 2.5.0 en su red interna. La configuración y las evidencias están en `storage/app/tenancy2-preproduction` del worktree.

- Las empresas Alfa y Beta conservaron sus bases creadas por Hyn. Cada API aceptó su propio token y rechazó el de la otra empresa. Un dominio desconocido devolvió 404. Redis y el disco asignaron valores distintos a una misma clave según el tenant.
- Se dejaron dos trabajos en cola con Hyn. El worker de Tenancy 2 los consumió y habría fallado la verificación si hubiera escrito en la base equivocada. Las pruebas PostgreSQL dirigidas también cubrieron el worker, la compensación de una instalación fallida y la restauración desde un volcado.
- Las facturas sintéticas de ambas versiones conservaron VES 116 de total, VES 16 de IVA y VES 116 de cobro. La reversión a Hyn conservó ambas facturas, sus cobros y el acceso al PDF producido por Tenancy 2. Al redeplegar Tenancy 2 se verificó de nuevo HTTP y el worker, y se aprovisionó una tercera empresa cuyo acceso HTTP devolvió 200.

## Batería fiscal

Ejecutar en el worktree de `codex/tenancy-2` con PostgreSQL 17.11 temporal y Tenancy 2.5:

```bash
php scripts/test_postgresql.php --skip-build --without-accounting --ves-documents-only
```

El arnés utiliza tres insumos versionados. `casts_exactos_efectivos.json` conserva el inventario de campos y valida los casts efectivos de la rama. `esquema.json` contiene el contrato de tablas y columnas normalizado para PostgreSQL. `referencia_rendimiento_postgresql.json` compara HTTP, reportes y POS contra las mediciones del commit `9d7d61de`, que ya usa Tenancy 2. La opción VES incluye las pruebas de rendimiento.

Los módulos `Account` y `Accounting` siguen activos. El arnés excluye sus pruebas propias y casos compartidos identificados. Conserva cobros USD sobre documentos VES, incluido el cobro mixto. El reporte JSON lista las exclusiones por identificador, y el arnés verifica la cobertura comparando el inventario con los JUnit.

La selección dirigida cubrió la aceptación HTTP, la matriz de notas y la factura VES con cobro mixto a tasa BCV. El perfil omite dos recorridos internos de la aceptación HTTP que creaban un servicio técnico y una nota en USD. Comprueba al final que no haya documentos USD en las siete tablas documentales incluidas en ese recorrido.

Tenancy 2 pasó los tres casos con **1.757 aserciones**. El JUnit de esa selección está en `storage/app/tenancy-build/junit`.
