# Migración de SAB a Tenancy 2.5

La rama `codex/tenancy-2` parte de `c745cafb`. El commit de código `e0401ff5` instala `tenancy/tenancy` v2.5.0 y retira `hyn/multi-tenant`. El commit `9d7d61de` ajusta la aceptación HTTP al alcance de documentos VES. Se verificó Composer con PHP 8.2, dependencias de desarrollo y una instalación limpia sin ellas.

SAB conserva las tablas `websites` y `hostnames`, los dominios, las conexiones `system` y `tenant`, los roles PostgreSQL, el almacenamiento por empresa y el identificador de cola `website_id`. Tenancy 2 aplica la conexión al cambiar el contexto. Los servicios de SAB siguen ejecutando el aprovisionamiento, la recuperación y las semillas. El registro de rutas por dominio se comprobó con PHP-FPM. El middleware rechaza una petición que intenta pasar por las rutas ya registradas para otro dominio.

## Preproducción

El proyecto Docker `sab-tenancy2-preprod` quedó ejecutando Tenancy 2.5.0 en su red interna. La configuración y las evidencias están en `storage/app/tenancy2-preproduction` del worktree.

- Las empresas Alfa y Beta conservaron sus bases creadas por Hyn. Cada API aceptó su propio token y rechazó el de la otra empresa. Un dominio desconocido devolvió 404. Redis y el disco asignaron valores distintos a una misma clave según el tenant.
- Se dejaron dos trabajos en cola con Hyn. El worker de Tenancy 2 los consumió y habría fallado la verificación si hubiera escrito en la base equivocada. Las pruebas PostgreSQL dirigidas también cubrieron el worker, la compensación de una instalación fallida y la restauración desde un volcado.
- Las facturas sintéticas de ambas versiones conservaron VES 116 de total, VES 16 de IVA y VES 116 de cobro. La reversión a Hyn conservó ambas facturas, sus cobros y el acceso al PDF producido por Tenancy 2. Al redeplegar Tenancy 2 se verificó de nuevo HTTP y el worker, y se aprovisionó una tercera empresa cuyo acceso HTTP devolvió 200.

## Batería fiscal

La corrida completa se intentó sobre `9d7d61de`:

```bash
php scripts/test_postgresql.php --skip-build --reuse-reference --without-accounting --ves-documents-only
```

Terminó en la comprobación de insumos con código 2. Faltan `casts_exactos_efectivos.json` y `referencia_rendimiento_multitenant_mysql.json`. Nueve archivos del informe histórico de casts tenían huellas distintas a las de la rama base, por lo que se descartó como evidencia vigente. La opción VES no difiere las pruebas de rendimiento por sí sola.

Los módulos `Account` y `Accounting` siguen activos. El arnés excluye sus pruebas propias y casos compartidos identificados. Conserva cobros USD sobre documentos VES. El descubrimiento preliminar guardó 153 coincidencias de Contabilidad y 157 de moneda en `storage/app/tenancy-build/exclusiones-descubiertas.json`. Algunos casos coinciden en ambas listas. Hubo cuatro entradas de error durante ese descubrimiento, por lo que las cifras son diagnósticas.

La selección dirigida ejecuta la aceptación HTTP, la matriz de notas y la factura VES con cobro mixto a tasa BCV. El perfil omite dos recorridos internos de la aceptación HTTP que creaban un servicio técnico y una nota en USD. Comprueba al final que no haya documentos USD en las siete tablas documentales incluidas en ese recorrido. El comparativo utiliza el mismo ajuste de prueba sobre el runtime Hyn de la rama base.

Ambas ejecuciones dirigidas pasaron los mismos tres casos. Hyn acumuló **1.763 aserciones** y Tenancy 2 **1.757 aserciones**, sin fallos, errores ni omisiones en esa selección. Los JUnit y la comparación están en `storage/app/tenancy-build/junit`.
