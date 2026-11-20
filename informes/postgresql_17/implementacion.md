# Implementación de PostgreSQL 17.11

La implementación está en la rama `codex/postgresql-supabase-17-11`, basada en `develop`, en el worktree `/home/benahia/.codex/worktrees/pro9-postgresql-supabase-17-11/Pro9`. El 19 de noviembre de 2026 aprobaron las pruebas funcionales en PostgreSQL 17.11 temporal, las mediciones de HTTP y reportes y las auditorías estrictas de facturación. También aprobaron la instalación desde cero y la restauración integral. La batería cubrió facturas en VES con cobros mixtos a la tasa BCV simulada. Los adjuntos de Contabilidad están excluidos de las pruebas y el rendimiento del POS está aplazado. [Conservación de formatos](conservacion_formatos_20261117.md) · [Verificación y registro de cierre](pendientes_verificacion.md) · [Operación y recuperación](operacion.md) · [Trazabilidad de Pro6](referencia_pro6.md).

## Continuación verificada del 17 de noviembre

La batería `pro9-pgtest-373c97f1f71e` aprobó 2.640 pruebas funcionales y 88.677 aserciones en PostgreSQL 17.11 temporal, incluidas 188 de restauración integral. El ejecutor confirmó limpieza y código de salida 1 por las auditorías estrictas de facturación. Después se integraron la recuperación de precios y cantidades de notas, la tasa IGTF a ocho decimales y el 404 de la compra eliminada. La nueva revisión aprobó 57 pruebas y 1.752 aserciones dirigidas, incluida la aceptación HTTP, en PostgreSQL 17.11 temporal. Las auditorías estrictas posteriores siguen abiertas por revisiones pendientes. [Pruebas dirigidas](../evidencias/postgresql_17_implementacion/resultados/facturacion_origen_integrado_20261117_01.xml). El rendimiento POS permanece aplazado. [Manifiesto de batería](../evidencias/postgresql_17_implementacion/resultados/pro9-pgtest-373c97f1f71e.json). [Aplicación verificada](../evidencias/postgresql_17_implementacion/resultados/aplicacion_revision_facturacion_20261117_01.json).

Las comprobaciones posteriores corrigieron la cotización de saldos de facturas con fondos fraccionarios y el cálculo de descuentos de factura en el POS. La prueba combinada de PDF y facturación pasó 45 casos después de aislar la caché del fixture. La compilación actual pasó 1.088 pruebas JavaScript, 60 comprobaciones en Chrome y 12 pruebas dirigidas en PostgreSQL 17.11 temporal. La batería completa se repetirá sobre la revisión que apruebe las auditorías estrictas. [Detalles de verificación](pendientes_verificacion.md).

La conversión de notas de venta conserva las partidas de origen de los paquetes y recupera la suma de cantidades agrupadas desde las notas bloqueadas. La repetición `pro9-pgtest-31e36dc2a9c3` aprobó 16 pruebas y 1.369 aserciones en PostgreSQL 17.11 temporal, incluida la ruta HTTP y la suma de dos cantidades válidas cuya fracción tiene un denominador de 35 dígitos. Los formatos de reportes conservan su revisión anterior. [JUnit de conversión](../evidencias/postgresql_17_implementacion/resultados/conversion_fraccion_amplia_20261117_03.xml).

La aceptación dirigida `aceptacion_facturacion_pdf_20261117_02` aprobó 132 pruebas y 3.893 aserciones en PostgreSQL temporal; después se verificó el resumen A5 y los recursos de PDF con cuatro pruebas y 1.062 aserciones (`pdf_recursos_locales_20261117_02`). No se rediseñaron reportes. La compilación autorizada 05 aprobó; JavaScript 20 aprobó 1.076 pruebas y Chrome 18 completó 48 comprobaciones, sin peticiones comerciales. Estas tandas tienen sus propias huellas; no sustituyen la batería completa final.

La batería `pro9-pgtest-6fa227c14d19` ejecutó sus 2.627 pruebas funcionales, incluidas las 165 contables. Terminó con un error y cinco fallos funcionales. Aprobaron SQL, sintaxis, JavaScript, los tres controles de navegador y los dos controles del diff. Las auditorías estrictas siguieron abiertas. La fuente permaneció fija y se limpiaron los recursos temporales. El rendimiento POS quedó aplazado.

Las tandas dirigidas posteriores corregieron el fixture de papel A4/A5, la pérdida de fracciones en la reversión de lotes, los metadatos de los tres casts de Item y la precisión de los importes de facturas por cobrar. El sondeo `pro9-infra-8bbd9b7a2c40` aprobó instalación temporal, persistencia al recrear PostgreSQL 17.11 y arranque HTTP. Las pruebas de respaldo ampliadas validaron datos y secuencias centrales, falta de espacio y denegación de permisos. La batería completa posterior sigue siendo un criterio de cierre.

Las auditorías estrictas permanecen abiertas. El análisis 12, anterior a los últimos 23 controles individuales y dos registros retirados, conserva 2.959 pendientes PHP y 2.221 JavaScript en facturación, con 688 y 400 diagnósticos de registro. No se omiten sus resultados globales. Las nuevas pruebas AST aprobaron 136 casos y 535 aserciones y sólo materializaron pruebas estructurales individuales; no aprobaron archivos completos ni renovaron revisiones manuales automáticamente.

La revisión de aceptación señaló trabajo adicional de respaldo: fallos reales de espacio/permisos, comparación completa de tablas centrales y secuencias restauradas, y nueva evidencia de persistencia tras recreación. El corte integral sigue exigiendo que el operador mantenga detenidos todos los productores durante la ventana; la consulta de actividad no certifica por sí sola que no aparecerán nuevas escrituras.

## Cambios implementados

| Frente | Implementación y finalidad |
|---|---|
| Infraestructura | Imagen Supabase `17.11.0.019` fijada por digest, plataforma Intel `linux/amd64`, datos y configuración persistentes, healthcheck de bootstrap/autenticación y puerto PostgreSQL privado. |
| PHP | PHP 8.2.35, `pdo_pgsql`, clientes PostgreSQL 17, AVIF y extensiones de los flujos existentes; runtime sin `pdo_mysql` ni paquete mysqldump. Directorios de escritura preparados para CLI y PHP-FPM. |
| Conexiones | `system`, `central_migrator`, `provisioner`, ledger durable independiente y `tenant` dinámico. UTF-8, Caracas y credenciales separadas. |
| Tenancy | Adaptador Hyn fuera de vendor, propietarios independientes, permisos de esquema/secuencias, saga de alta y registro de recursos con UUID/OID. Compensación limitada a recursos acreditados; cambios de nombre y credenciales reversibles ante fallos recuperables. |
| Contexto | Disponibilidad del tenant antes de atenderlo; liberación de conexión, credenciales y disco entre trabajos; restauración del contexto de un trabajo síncrono. |
| Central | 179 migraciones reales: 161 centrales, incluidos soporte PostgreSQL y ledger, más 18 de Marketplace. Instalador y reanudación con semillas por identidad y secuencias sincronizadas. |
| Tenant | 350 creadoras Schema Builder más FK final: 351 archivos tras integrar develop. El inventario agregado debe cotejarse con la aceptación actual. RIF integrado en la creadora de empresas. |
| Datos | NUMERIC exactos, excepciones de rango central, IDs bigint, límites unsigned de negocio, estados con CHECK, JSON conservado y timestamps locales. Defaults y nulabilidad contrastados con MySQL temporal. |
| Temporales | Regla automática de auditoría fiscal y regla implícita MySQL de `received_retention_uploads.expires_at`; reloj de sentencia dentro de transacciones y respeto a asignaciones explícitas. |
| Semillas | Catálogos venezolanos conservados, carga transaccional con FK diferidas sólo durante la carga y sincronización de secuencias. Las restricciones únicas de ON CONFLICT permanecen inmediatas. |
| SQL y transporte | Fechas, COALESCE, JSON, concatenación, UNION, metadatos y SQLSTATE PostgreSQL. Indicadores públicos 0/1 y CHAR sin relleno; NUMERIC/JSON no pasan por float. |
| Texto | Igualdad/uniquidad ICU; patrones con normalización, unaccent y collation determinista compatible con PostgreSQL 17. Parámetros enlazados. |
| Consistencia | ReadSnapshot abre REPEATABLE READ antes de consultar; rechazo de transacciones ajenas, savepoints y reintentos completos recuperables con límite de tres. |
| Rendimiento | Lectura de saldos racionales y relaciones en lotes acotados; reutilización limitada a la materialización del resultado, sin alterar snapshots históricos. |
| Respaldos | pg_dump/pg_restore 17, formato personalizado, manifiesto/checksums, ZIP privado, central/tenants/archivos y restauración independiente antes de activar. Reversión de bases y archivos ante fallo de activación. |
| Importación histórica | Padrón deja LOAD DATA y usa lector transaccional; los POST de carga requieren CSRF, autenticación y administrador. |
| Automatización | Ejecutor Compose temporal, referencia MySQL congelada, PHPUnit, JavaScript, sintaxis, SQL, auditorías exactas, JUnit, hashes de fuentes y job GitHub Actions. El ejecutor falla ante pruebas omitidas o modificación del código durante la ejecución. |

La adaptación y sus diferencias están en [la matriz Pro6–Pro9](referencia_pro6.md). Los seis parches y el driver histórico quedan identificados por SHA-256; Pro6 se mantuvo en lectura.

## Comprobaciones y evidencia

El sondeo `infraestructura.json` acredita arranque vacío, instalación/reanudación, permisos, persistencia después de recrear PostgreSQL, respuesta HTTP 200 a través de Nginx/PHP-FPM y Redis. La versión real es `PostgreSQL 17.11 on x86_64-pc-linux-gnu, compiled by gcc (GCC) 15.2.0, 64-bit`.

Las pruebas nativas verifican alta por panel y API, dos empresas con IDs internos iguales, autenticación HTTP cruzada rechazada, aislamiento SQL, comandos y contexto de workers. Inyectan fallos de alta, de publicación del renombrado y de activación de archivos. La restauración integral ensaya central y dos empresas, permisos, snapshots, archivos y secuencias.

El contrato completo compara las 4.380 columnas con el mapa de tipos revisado y con la metadata de un MySQL 5.7.33 temporal. La restauración integral crea ambas empresas por el panel, conserva sus esquemas completos y compara los datos de todas sus tablas, además del acceso HTTP posterior. La suite comercial cubre importes/fracciones, cobros, IVA/ISLR/IGTF, notas, conversiones, inventario, reportes, PDF/XLSX y respuestas HKA controladas. Las barreras de concurrencia usan conexiones/procesos independientes y veinte participantes en los casos ampliados.

El rendimiento compara treinta muestras HTTP por caso, después de tres calentamientos, para 1.000 y 10.000 documentos: listado, catálogo POS y reporte de ventas. Ambos motores se limitan a dos CPU, servidor de 768 MiB y PHP de 1.536 MiB, con PHP 8.2.35. La referencia se captura desde la revisión congelada en un servidor MySQL privado que se elimina al terminar. El presupuesto es p95 de referencia más el mayor entre 20 % y 100 ms.

`BankPaymentMysqlTest` usaba herencia de toda la suite fiscal, por lo que repetía sus casos. La versión PostgreSQL compone el fixture y ejecuta su caso propio; la suite fiscal conserva sus casos y añade la comparación integral. El cambio de cantidad total de tests debe contrastarse por nombres únicos y casos, no interpretarse como retirada de cobertura.

## Alcance de las auditorías y del cierre

Las auditorías globales exactas ya fallaban en la revisión congelada: 20.647 candidatos PHP y 21.165 JavaScript pendientes, sin errores de análisis. Un candidato pendiente es una revisión por resolver, no un fallo aritmético confirmado. La implementación conserva el fallo de los auditores estrictos y no atribuye a PostgreSQL el cierre de esos consumidores.

Se revisan individualmente las nuevas declaraciones decimales contra el contrato nativo y los guardas técnicos mediante la prueba AST admitida por el proyecto. Las expresiones retiradas de MySQL archivan su aprobación anterior. Los resultados globales y la comparación con la referencia se registrarán con sus conteos finales; no se reemplazan por una aprobación general por archivo.

El usuario autorizó expresamente «compila tu en la carpeta del worktree». Se ejecutó `npm run build` allí y se verificaron las 33 entradas del manifiesto, sus archivos e imports y la ayuda compilada `pro9:restore-tenant`, sin el comando anterior de MySQL. Se conserva [la evidencia del build](../evidencias/postgresql_17_implementacion/frontend_compilado.json). Las emisiones HKA, pagos y datos reales no se utilizan para validar esta entrega.

La continuación corrige dos consumidores adicionales: `items:fill-price-labels` copia la fracción original y sobrescribe mediante los eventos del modelo, y las partidas de movimientos de almacén conservan cantidades y snapshots sin casts a float. La prueba conjunta dirigida aprobó 236 casos y 2.859 aserciones en una instancia PostgreSQL temporal, retirada al terminar. Los 228 casos de casts comprueban únicamente el caster efectivo, sin conexiones y sin acreditar sus consumidores.

Las nuevas pruebas AST se limitan a cadenas vacías comparadas estrictamente, nombres literales de clase, concatenaciones generadas para texto Vue y formatos decimales exactos enviados directamente a echo/Blade. La resolución de nombres precede a la evaluación de expresiones padre. Los cálculos anidados mantienen sus propios candidatos. Se archivaron 256 identidades de aprobación ausentes del inventario actual y se sustituyeron 124 revisiones caducadas por pruebas AST nuevas, sin transferir autorizaciones a operandos. El motor racional JavaScript recibió 114 revisiones individuales con pruebas de fracciones, magnitudes, signos, límites, reparto y recepción monetaria.

El manifiesto de las pruebas incluye los contratos fijos de esquema, referencia MySQL, rendimiento y casts. Al capturar una referencia nueva se permite cambiar sólo ese archivo antes de fijar la huella; cualquier otra modificación invalida el ensayo. Los resultados generados quedan fuera de esa huella. El presupuesto de rendimiento se compara con los mismos bytes de referencia que se registran en el informe.

El trabajo no debe declararse cerrado mientras la validación final registre fallos o queden criterios de aceptación pendientes. Las bases existentes, Supabase alojado, alta disponibilidad y la actualización general de Laravel permanecen fuera del alcance aprobado.

La referencia funcional original ejecutó 1.425 casos únicos en grupos acotados y el caso bancario independiente. Conservó 31 errores y 14 fallos previos; el único benchmark opcional omitido en la primera pasada se ejecutó después con su bandera y pasó (1 caso, 16 aserciones). La ejecución original sin particionar agotó el límite PHP de 768 MiB; se conserva ese diagnóstico. Sólo esta referencia funcional usa almacenamiento temporal en memoria; la comparación HTTP de rendimiento conserva su captura independiente en disco y sus límites equivalentes de recursos.

Resultado final y límites: [acta de validación](../evidencias/postgresql_17_implementacion/README.md).

La continuación añade precisión central de Marketplace (`NUMERIC(30,12)` con snapshot) y conserva la presentación pública numérica con opt-in. Sus tres contadores correlacionados usan `SET` compatible con PostgreSQL y respetan tiendas ocultas. La validación de lote rechaza entradas excesivas antes de escribir; sincronización, moderación, límites y feed tienen pruebas nativas. También se corrigen las causas observadas de deprecaciones PHP 8.2 en exportadores, fechas y valores opcionales.

La comprobación de tablas utiliza `pg_catalog` con los mismos filtros de visibilidad de PostgreSQL 17 y sin caché de privilegios; se compara contra `information_schema.tables` en 46 aserciones nativas. La documentación de [visibilidad de tablas](https://www.postgresql.org/docs/17/infoschema-tables.html) y las [funciones de privilegios](https://www.postgresql.org/docs/17/functions-info.html) describen ese contrato. Se conserva la referencia original de rendimiento y todas las mediciones fallidas; las mediciones diagnósticas se identifican expresamente.
