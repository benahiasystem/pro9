# Adaptación de la modalidad de emisión fiscal de Pro9

Fecha: 10 de septiembre de 2026. Rama: `codex/modalidad-emision-fiscal`, creada desde `develop` (`67208981d1263e85d2d9cc8b7b7dd4405266dfb3`). Sin commits. Consolidación para instalación nueva, sin tenants ni datos anteriores que convertir.

## Resultado funcional

La configuración de empresa y el alta/edición de tenants usan modalidad y ambiente independientes. Las modalidades son `fiscal_machine` (Máquina fiscal), `digital` (Medios digitales) y `free_form` (Forma libre). Los ambientes son `demo` y `production`. Se retiran SOAP Tipo, SOAP Envío, ambiente Interno y carga de certificados PFX de facturación.

La selección es configuración progresiva: los datos adicionales son opcionales. No hay integración real con equipos ni proveedores fiscales. Pro9 continúa registrando operaciones comerciales localmente y generando PDF; seleccionar Producción no significa que se haya transmitido o autorizado una factura.

El administrador tenant puede cambiar la configuración en `/companies/create`, mediante `GET/POST /companies/fiscal-emission`. El superadmin configura los mismos datos de la empresa tenant; no existe otra copia central. Alta ordinaria, autoregistro y API requieren modalidad y ambiente.

## Reglas y protección de datos

- `FiscalEmissionSettings` valida modalidad, ambiente, parámetros por modalidad y rangos. Sólo persiste los campos del contrato actual; se retiraron la lista de campos antiguos, sus validadores y las llamadas desde middleware y Facturalo.
- Se exige identidad administrativa tenant en el endpoint dedicado, independientemente de encabezados AJAX. El tenant proviene del contexto de servidor; los IDs enviados no permiten seleccionar otra empresa.
- El editor general de empresa rechaza modificaciones fiscales para impedir eludir el servicio dedicado.
- Las credenciales se cifran con el cifrador de Laravel y se excluyen de la serialización. Campo vacío conserva el valor; eliminación explícita lo borra; cambiar modalidad descarta parámetros y credenciales anteriores. Conservar la clave de aplicación que permite descifrarlas.
- La auditoría registra la selección inicial y los cambios posteriores, con actor y nombres de campos modificados, sin valores secretos. Existe desde el esquema base y su empresa está protegida por clave foránea.
- La primera operación bloquea permanentemente el ambiente. El guardado de operaciones y la configuración comparten el bloqueo transaccional de la empresa. Eliminar las operaciones no desbloquea el ambiente. Revisar `config/fiscal_emission.php` al añadir escritores o tablas de operaciones.
- Las facturas guardan modalidad y ambiente desde la empresa en servidor. La base exige ambos campos; el alta inserta los dos desde el primer guardado de la empresa y no puede crear un tenant sin modalidad.

## Retirada técnica y esquema

Se sustituyen `soap_type_id`, sus relaciones, filtros y comparaciones por `fiscal_environment`. El catálogo `fiscal_environments` usa claves de texto y contiene Demo/Producción. Desaparecen las restricciones de funciones comerciales ligadas al antiguo modo Interno.

Se retiran controladores/componentes de certificados fiscales, comandos de envío/consulta, clientes SOAP, firmadores y servicios PSE de emisión. Los certificados QZ Tray permanecen, pues corresponden a impresión. Los métodos internos de Facturalo que siguen llamados por flujos comerciales para XML/firma son inertes. La política de registro local es permanente y no se desactiva mediante el antiguo flag.

El correo lee y adjunta exclusivamente PDF. Los webhooks documentales incluyen respuesta local, modalidad y ambiente; no ofrecen enlaces XML/CDR. El archivo técnico `CodeErrors.xml` permanece sin modificaciones.

`companies` contiene directamente modalidad obligatoria, parámetros/credenciales opcionales y bloqueo con valor inicial falso; `documents` contiene modalidad obligatoria. La tabla de auditoría se crea en `2026_08_17_000176_create_fiscal_configuration_audits_table.php` y su FK se añade en la migración final de claves foráneas. Se mantienen 358 migraciones tenant: una creación consolidada de auditoría sustituye a la conversión incremental. Hay 323 archivos de creación de tablas y 72 tablas con datos iniciales, con 850 filas en el inventario principal.

Se eliminó el servicio de conversión fiscal y las dos migraciones finales de retirada central/tenant. También se eliminaron las dos migraciones centrales de 2020 que creaban certificados y campos SOAP: el esquema central nuevo no los crea para borrarlos después. La inicialización fiscal dispone de `up()` y `down()` de creación/eliminación de sus tablas; no hay conversión a Demo, limpieza de registros antiguos ni instrucción de rollback irreversible. Los seis tipos de auditoría SOAP/certificado nunca se siembran.

**No se ejecutan migraciones ni cambios sobre bases reales.** Sólo se crean y eliminan bases temporales de pruebas. Tampoco se ha desplegado esta rama ni creado un commit. La retirada de compatibilidad histórica de otros módulos está documentada en [un plan separado](plan_retirada_compatibilidad_historica.md) y no forma parte de esta implementación.

## Verificación

Las pruebas MySQL crean bases aleatorias `pro9_fiscal_test_<hex>` y las eliminan al terminar. No usan datos de tenants reales. Las pruebas de servicio utilizan SQLite en memoria. La suite general usa nombre de base aislado y caché/sesión/correo en memoria.

Comandos reproducibles en el entorno Docker del repositorio:

```bash
docker compose exec -T php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/FiscalEmissionSettingsTest.php

docker compose exec -T -e PRO9_FISCAL_MYSQL_TESTS=1 php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/FiscalEmissionSchemaTest.php

docker compose exec -T -e DB_DATABASE=pro9_fiscal_suite_no_live -e CACHE_DRIVER=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync -e MAIL_MAILER=array php vendor/bin/phpunit --testsuite Unit
```

Resultados:

| Verificación | Resultado |
| --- | --- |
| `FiscalEmissionSettingsTest` | 22 pruebas, 58 aserciones, todas aprobadas |
| `FiscalEmissionSchemaTest` (MySQL) | 2 pruebas, 192 aserciones, aprobadas: creación/seeding/rollback/segunda creación tenant con esquema y datos reproducibles, y creación central |
| Suite Unit | 298 pruebas, 6653 aserciones; 293 aprobadas, 3 fallos preexistentes y 2 omitidas |
| Sintaxis PHP 8.2 | 222 archivos, sin errores |
| Sintaxis Vue/script y plantillas en memoria | 40 componentes, sin errores |
| Skills modificadas/nueva | Las seis superan `quick_validate.py` |
| `git diff --check` | Sin errores |

Las dos omitidas de la suite Unit son las pruebas MySQL optativas, ejecutadas por separado. Los tres fallos generales se contrastaron con el commit base de `develop`:

1. `Pro8SkillContractParityTest` espera «Cédula de Identidad», mientras la skill base ya establece «Venezolano».
2. `SunatSeniatMigrationContractTest` espera `resources/js/views/tenant/dispatches/Carrier/Form.vue`, ruta que tampoco existe en el commit base.
3. `VenezuelaCurrencyTest` detecta `PEN`/`VED` en `VendeyaDocumentPayloadNormalizer.php`; esa normalización ya existe en el commit base.

No se alteraron esos contratos ajenos para obtener un resultado artificialmente verde.

Se verifican permisos (administrador, vendedor, visitante e identidad ecommerce), aislamiento de empresas, validación de las modalidades, cifrado/ocultación/borrado de secretos, auditoría inicial/posterior, bloqueo del ambiente, snapshot de facturas y rollback de un guardado fallido. Las pruebas MySQL usan exclusivamente esquemas nuevos y verifican ausencia de transporte fiscal, columnas obligatorias y opcionales, default de bloqueo, catálogo, FK de auditoría, seeding, rollback y repetición. La suite general cubre también los contratos existentes de RIF, tipos de venta, numeración, productos y política fiscal local.

## Límites de verificación

No se ha realizado prueba HTTP integral del alta de tenant, recorrido visual del formulario ni emisión real. Las pruebas de endpoint invocan su controlador con identidad/contexto aislados; no sustituyen una prueba del navegador contra una instalación migrada de prueba.

Una comprobación exploratoria de todas las claves foráneas del seeding detectó un producto inicial con `purchase_affectation_igv_type_id = 30` sin fila correspondiente en el catálogo. Ese valor también está en `develop`. Se registra para el plan de IVA/catálogos; no se modifica dentro de SOAP/PFX. La prueba fiscal final comprueba la FK de auditoría y la reproducción del esquema/datos iniciales, pero no certifica la integridad referencial de los demás módulos. Se retiró de esa prueba la comprobación global añadida durante la exploración, conservando este hallazgo explícito.

No se ejecutó Vite ni se editaron bundles: [frontend-build](../.codex/skills/frontend-build/SKILL.md) indica «No ejecutar comandos de build por iniciativa propia: la compilación la ejecuta el usuario». Los cambios de `public/build/` que aparecen en el workspace son externos a esta implementación. La sintaxis Vue se verifica en memoria; queda comprobar el bundle y el flujo visual cuando el usuario disponga de una instalación de prueba con el esquema nuevo.

## Documentación mantenida

La skill [mantener-modalidad-emision-fiscal-pro9](../.codex/skills/mantener-modalidad-emision-fiscal-pro9/SKILL.md) define el contrato para instalaciones nuevas. Se actualizan `adaptar-sistema-venezuela`, `reconstruir-migraciones-tenant`, `mantener-operacion-local-fiscal-pro9`, `mantener-catalogos-fiscales-venezuela`, `rif-super-admin-pro9`, el inventario de catálogos iniciales y la trazabilidad fiscal local. Sus instrucciones generales sobre tenants históricos quedan explícitamente excluidas para SOAP/PFX.
