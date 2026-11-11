El 9 de noviembre de 2026 se completaron las pruebas automatizadas de Pro9 sobre el estado existente del repositorio. La suite principal de PHP registró **98 casos con incidencias: 78 errores de ejecución y 20 fallos de aserción**. Al repetir 22 casos en un proceso aislado, **21 aprobaron y uno mantuvo el error**. Se conservan todos los resultados originales. No se corrigió ningún error.

| Comprobación | Resultado |
| --- | --- |
| PHP: 1.748 casos, 39.984 aserciones | 1.647 aprobados, 78 errores, 20 fallos, 3 omitidos inicialmente |
| JavaScript: 91 archivos, 760 pruebas | 760 aprobadas |
| Rendimiento de caja, activado expresamente | 2 aprobadas, 32 aserciones; 1.000 y 10.000 cobros |
| Entorno HTTP temporal de Forma libre | 1 aprobada, 17 aserciones |
| Navegador Laravel Dusk | 2 errores antes de abrir Chrome; falta Chromedriver |
| Integración API BCV | Aprobada antes y después del reinicio del equipo |
| Aceptación HTTP de rutas y autenticación | Aprobada; incluida en las 1.748 pruebas de PHP |

Las tres omisiones iniciales de PHP corresponden al entorno HTTP temporal y a las dos pruebas de rendimiento de caja. Las tres se ejecutaron expresamente y aprobaron. Las pruebas de navegador se intentaron y quedaron impedidas por la dependencia ausente.

El [anexo de los 98 casos](/home/benahia/benahia/pro/Pro9/informes/evidencias/pruebas_sistema_2026_11_09/detalle-errores-php.md) enumera cada prueba fallida, su mensaje, su archivo y el resultado aislado cuando existe. El [JSON de incidencias](/home/benahia/benahia/pro/Pro9/informes/evidencias/pruebas_sistema_2026_11_09/errores-pruebas-php.json) conserva mensajes íntegros, diferencias y trazas. El [JUnit completo](/home/benahia/benahia/pro/Pro9/informes/evidencias/pruebas_sistema_2026_11_09/phpunit-completo.xml) cubre exactamente el inventario de 1.748 casos, sin casos faltantes ni adicionales.

Los resultados de PHP se agrupan así:

| Incidencia observada | Casos |
| --- | ---: |
| Intermediación incompatible con el método de cobro | 40 errores y 2 aserciones sobre el campo `payments` |
| Conversión de notas de venta sin Kardex global verificable | 12 errores |
| Concurrencia al facturar notas: se esperaba un resultado exitoso y hubo cero | 4 fallos |
| Validación que esperaba el campo `source_sale_note_payment_id` | 2 fallos |
| Resolución de `request` en reportes y listados | 18 errores |
| Validación decimal y comprobación del error de lotes | 4 errores y 1 fallo |
| Método de pago inactivo o sin cuenta bancaria configurada | 2 errores |
| Conexión `tenant` ausente en una prueba de retención IVA | 1 error |
| Objeto `stdClass` donde el correo exige `Document` | 1 error |
| PDF de nota de venta: se exige «OP. GRAVADA» y se genera «IVA» | 2 fallos |
| Contratos estáticos de vistas, catálogos y migraciones | 9 fallos |
| **Total** | **98 casos** |

En los cobros, la validación rechaza `financial_institution` cuando el método seleccionado es efectivo y corresponde `none`. Los datos compartidos de las pruebas de IVA e ISLR aún suministran ese valor incompatible. Dos pruebas que esperaban un error en `payments` reciben antes esa validación de intermediación. Las referencias principales son [Classification.php](/home/benahia/benahia/pro/Pro9/app/Services/Payments/Classification.php:30), [IvaRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IvaRetentionPersistenceCases.php:21) e [IslrRetentionPersistenceCases.php](/home/benahia/benahia/pro/Pro9/tests/Support/IslrRetentionPersistenceCases.php:53).

En las conversiones de notas, [SaleNoteInvoiceInventory.php](/home/benahia/benahia/pro/Pro9/app/Services/Fiscal/SaleNoteInvoiceInventory.php:53) no encuentra los movimientos globales que exige. La preparación de los casos crea notas e ítems directamente y registra proveedores de inventario de almacén, pero omite el proveedor de Kardex global que carga la aplicación. El fallo de instalación y persistencia comercial también se reprodujo por separado. Los conteos de concurrencia y las aserciones del abono de origen se conservan como incidencias específicas en el anexo.

Los **18 errores de `request` aprobaron al repetir el grupo aislado**. Sus trazas pasan por [PaginationState.php](/home/benahia/benahia/pro/Pro9/vendor/laravel/framework/src/Illuminate/Pagination/PaginationState.php:20), cuyo resolvedor de paginación utiliza un contenedor capturado. También aprobaron en aislamiento dos casos de lotes que habían producido errores decimales y una aserción que esperaba un mensaje sobre «lote». Estos resultados muestran dependencia del estado u orden de ejecución de las pruebas.

La actualización de compra conserva el error decimal en aislamiento: el caso envía una solicitud sin `exchange_rate_sale` y [PurchaseController.php](/home/benahia/benahia/pro/Pro9/app/Http/Controllers/Tenant/PurchaseController.php:685) intenta convertir ese campo a decimal. Ocurre en ambas clases fiscales. El contraste completo está en [aislamiento-estado.xml](/home/benahia/benahia/pro/Pro9/informes/evidencias/pruebas_sistema_2026_11_09/aislamiento-estado.xml): 22 pruebas, 866 aserciones, 21 aprobadas y un error.

La prueba de correo construye un doble `stdClass`; [DocumentEmail.php](/home/benahia/benahia/pro/Pro9/app/Mail/Tenant/DocumentEmail.php:34) lo pasa a un método que exige `App\Models\Tenant\Document` y se produce un `TypeError`. Otras pruebas se detienen por un medio bancario inactivo, una cuenta bancaria sin configurar o una dependencia de base `tenant` que el caso no prepara.

Los nueve contratos estáticos fallidos tienen estos resultados concretos:

| Contrato | Diferencia encontrada |
| --- | --- |
| Totales de productos de Hotel | No encuentra el fragmento de subtotal `this.form.subtotal \| toDecimals` que exige la prueba. |
| Totales y cobros de factura | Busca `invoice-totals-payments` en el archivo padre; el marcador está en el componente [invoice_collection_section.vue](/home/benahia/benahia/pro/Pro9/resources/js/views/tenant/documents/partials/invoice_collection_section.vue:3). |
| Efectivo y vuelto del POS | Exige un `v-text` literal con `isMissingAmount`; el componente usa `settlementDifferenceLabel`. |
| Plantillas PDF de cotización | Espera 12 y encuentra 13; la aserción de conteo impide continuar con las comprobaciones de importes de ese caso. |
| Conteo de migraciones tenant | Espera 349 archivos y encuentra 350. |
| Última migración tenant | Espera `2026_08_17_000999_add_tenant_foreign_keys.php`; encuentra al final `2026_11_03_000001_add_rif_document_to_companies_table.php`. |
| Catálogo inicial de medios de pago | Espera activos los códigos `02`, `03`, `04`, `05` y `12`; el catálogo los deja inactivos. |
| Contrato de IVA | No encuentra el literal `Localization::taxPercentage()` en `QuotationController.php`. |
| Símbolos monetarios | No encuentra el literal `Localization::currencySymbol($currencyTypeId)` en `DocumentController.php`. |

Estos contratos comprueban textos, estructura y ubicación del código. En los casos indicados, el resultado registrado es la diferencia de contrato; el navegador no llegó a ejecutarse para comprobar su presentación visual.

Ambas auditorías de cálculos exactos terminaron con código **1**:

| Auditoría | Operaciones pendientes de revisión | Incidencias en el registro de justificaciones |
| --- | ---: | ---: |
| PHP | 20.647 | 3.739 |
| JavaScript y plantillas | 21.231 | 1.992 |
| **Total** | **41.878** | **5.731** |

Las incidencias del registro se desglosan en **5.482 revisiones inválidas u obsoletas** y **249 revisiones huérfanas**. Los pendientes incluyen operaciones técnicas, comparaciones de texto y contadores, además de operaciones monetarias. Hay solapamiento entre pendientes y diagnósticos del registro. Se obtuvieron cero errores de análisis sintáctico y cero archivos sin cobertura dentro del alcance definido por los auditores. El [listado completo](/home/benahia/benahia/pro/Pro9/informes/evidencias/pruebas_sistema_2026_11_09/hallazgos-auditorias.json) conserva todos los identificadores, archivos, líneas y expresiones.

Las dos pruebas Dusk fallan en [DuskTestCase.php](/home/benahia/benahia/pro/Pro9/tests/DuskTestCase.php:21), antes de ejecutar sus aserciones, porque no existe `vendor/laravel/dusk/bin/chromedriver-linux`. Los casos afectados son `Tests\Browser\DocumentTest::testDocument` y `Tests\Browser\ExampleTest::testBasicExample`; sus resultados están en [browser.xml](/home/benahia/benahia/pro/Pro9/informes/evidencias/pruebas_sistema_2026_11_09/browser.xml). También se registró la advertencia de que [phpunit.xml](/home/benahia/benahia/pro/Pro9/phpunit.xml) utiliza un esquema obsoleto de PHPUnit.

Una ejecución anterior de cuatro procesos se interrumpió por un reinicio del equipo. Se conservaron 28 incidencias observadas en esa fase: 24 corresponden a casos que volvieron a fallar en la ejecución principal y **4 fallos de arranque o timeout no se reprodujeron**. La [comparación histórica](/home/benahia/benahia/pro/Pro9/informes/evidencias/pruebas_sistema_2026_11_09/comparacion-incidencias-previas.json) identifica cada caso. La ejecución definitiva utilizó dos procesos, servidores MySQL temporales propios y registros persistentes por prueba; las mediciones de rendimiento y los contrastes se hicieron después, sin otros tests en paralelo.

Se probaron los 176 archivos Unit, los 7 archivos Feature, la prueba del módulo Item, los 91 archivos JavaScript y los dos casos Dusk encontrados. El inventario principal incluye 321 casos fiscales repetidos por herencia en `BankPaymentMysqlTest`. Se mantuvo esa configuración. Las pruebas HKA utilizaron sus transportes simulados; las utilidades manuales de emisión real DEMO y los benchmarks comparativos históricos externos a la suite no forman parte de esta ejecución.

La verificación final confirmó **cero cambios de contenido en los 8.915 archivos preexistentes** y el mismo inventario de pruebas al principio y al final. Los documentos nuevos de otras tareas se conservaron. No se modificaron el código de aplicación, las pruebas originales, las migraciones ni los assets para remediar los resultados. La [verificación de fuentes](/home/benahia/benahia/pro/Pro9/informes/evidencias/pruebas_sistema_2026_11_09/verificacion-fuentes-final.json) y los [metadatos de ejecución](/home/benahia/benahia/pro/Pro9/informes/evidencias/pruebas_sistema_2026_11_09/ejecucion.json) permiten revisar el alcance y los comandos utilizados.

Entorno: PHP 8.2.35, PHPUnit 9.6.34, Node.js 22.19.0 y MySQL 5.7.33 temporal en Docker. Se evaluó el árbol de trabajo existente, con sus cambios previos, sobre el commit `f4179b3722a4bfebc62a8101ed5e53aa80e46c56`.
