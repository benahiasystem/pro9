# Envío automático HKA y ticket local — 14 de octubre de 2026

## Comportamiento

La preferencia **Enviar comprobante automáticamente** se encuentra en Configuración → Avanzado → POS → Configuración general POS. Se muestra en Medios digitales y sólo el administrador tenant puede cambiarla. Es global para las facturas y notas elegibles de web, POS, Venta rápida y API, incluidas las notas IGTF. Empieza activada y conserva la restricción de transporte DEMO.

`configurations.auto_send_hka` es un booleano obligatorio con valor inicial verdadero. Los endpoints existentes de configuración lo validan y serializan; omitirlo no cambia la preferencia. Un vendedor puede conservar su valor al guardar otras preferencias, pero no modificarlo. El navegador y el payload comercial no deciden el envío fiscal.

El guardado comercial sigue siendo transaccional. `document_emissions.response.auto_send_requested` registra una sola vez la intención inicial, sin exponerla en recursos ni modificar payloads fiscales. `HkaEmission::sendAutomatic` comprueba esa intención y la configuración actual; vuelve a comprobarlas después de autenticar y al reclamar el intento. El envío ocurre después del commit exterior. Un envío ya reclamado no se cancela al cambiar la preferencia.

Con la opción apagada, se conservan venta, numeración, cobros, inventario y snapshots, sin preparar ni transmitir automáticamente. El resultado indica **Comprobante guardado. Pendiente de envío HKA** y el estado permanece `not_requested`. Reactivar la preferencia no procesa una cola anterior ni permite que reanudaciones de IGTF o solicitudes repetidas envíen documentos creados con intención manual. El administrador conserva **Enviar HKA** y sus validaciones vigentes, independientemente del interruptor. La edición también exige envío posterior explícito.

## Descargas

El contrato `pdf_downloads.ticket` y las rutas autenticadas web/API existentes permiten descargar el ticket local de un documento vigente sin confirmación, incluso con preparación, rechazo, espera o incertidumbre. Se genera desde los datos conservados, en memoria, sin QR HKA, HTTP ni escritura de archivos o estados fiscales. Se mantienen autorización tenant/sucursal, nombres de descarga, cabeceras privadas, bloqueo durante carga/doble clic y la preferencia `show_ticket_80`.

Una emisión confirmada mantiene el ticket con la URL QR HKA original y los controles de identidad/configuración. Si esa URL falta, debe consultarse el estado HKA; no se sustituye un ticket confirmado por otro sin QR. A4/A5 y correo conservan sus requisitos de confirmación y no reciben sustitutos locales. Órdenes de entrega y retenciones conservan sus flujos propios.

## Empresas existentes

El consolidado incluye la columna en la migración creadora de `configurations`. La excepción autorizada para empresas existentes utiliza `hka:upgrade-auto-send-setting`, que añade únicamente esa columna, con valor inicial activado, y no reconstruye bases ni modifica comprobantes. Es idempotente y conserva una preferencia ya desactivada. Exige exactamente una selección: `--tenant=<website_id>` o `--all-tenants`; `--dry-run` sólo inspecciona.

Ejemplo para una empresa seleccionada, sustituyendo `ID` por su Website ID:

```bash
docker exec pro9-php php artisan hka:upgrade-auto-send-setting --tenant=ID --dry-run
docker exec pro9-php php artisan hka:upgrade-auto-send-setting --tenant=ID
```

Preparar primero la ampliación en el entorno donde se desplegará la función. Hasta aplicarla, las lecturas conservan el envío automático existente y guardar preferencias ajenas sigue funcionando; intentar cambiar el nuevo interruptor muestra un error de actualización pendiente. En esta implementación no se ejecuta la ampliación sobre empresas reales. Las fuentes Vue necesitan la compilación del usuario; no se editan bundles.

## Validación

Las pruebas usan HKA simulado, SQLite en memoria y bases MySQL temporales `pro9_fiscal_test_*`. Cubren permisos y booleanos, guardado sin HTTP, reactivación, envío manual, notas ordinarias e IGTF, rollback, apagado durante autenticación, ticket sin confirmar, aislamiento, QR confirmado, render real corto/extenso, selección y restauración de contexto del comando, ampliación idempotente y creación/seeding/rollback/repetición del consolidado.

Resultados finales de las ejecuciones focalizadas:

| Verificación | Resultado |
| --- | --- |
| PDF, permisos, edición, correo, transporte, respuestas HKA, comando y render PHP | 59 pruebas, 852 aserciones correctas |
| Comando ampliado y render real sin QR, corto/extenso | 14 pruebas, 221 aserciones correctas; este grupo comparte casos con el anterior |
| Configuración Vue, emisión, descargas y edición JavaScript | 47 pruebas correctas |
| Guardado diferido, nota ordinaria, reactivación, envío manual, rollback, apagado durante autenticación y notas IGTF en MySQL | 2 pruebas, 56 aserciones correctas |
| Otros escenarios MySQL focalizados | Creación/seeding/rollback/repetición del consolidado, commit exterior/recuperación, permisos y persistencia de la preferencia, ampliación aditiva/idempotencia: correctos |
| Sintaxis y espacios | 19 archivos PHP sin errores; `git diff --check` correcto |

Comandos principales:

```bash
docker exec pro9-php vendor/bin/phpunit tests/Unit --filter 'HkaAutoSendSettingCommandTest|HkaPdfTest|DocumentFiscalAuthorizationTest|DocumentEditPolicyTest|CurrentPdfRenderingTest|HkaMailTest|HkaTransportTest|HkaResponseTest'
docker exec pro9-php vendor/bin/phpunit tests/Unit --filter 'HkaAutoSendSettingCommandTest|CurrentPdfRenderingTest'
node --test --test-reporter=dot tests/js/hka-auto-send-settings.test.cjs tests/js/hka-pdf-ui.test.cjs tests/js/hka-emission-ui.test.cjs tests/js/document-edit-ui.test.cjs
docker exec -e PRO9_FISCAL_MYSQL_TESTS=1 pro9-php vendor/bin/phpunit tests/Unit/FiscalEmissionSchemaTest.php --filter 'test_hka_auto_send_off_preserves_sale|test_hka_auto_send_off_blocks_igtf'
```

Las pruebas MySQL se limitaron a los escenarios afectados, además de la instalación completa con rollback/repetición; no se ejecutó toda la clase de persistencia. Los avisos existentes del esquema XML de PHPUnit y del parámetro opcional de `Item.php` no impidieron estas verificaciones. No se compiló Vue ni se modificaron bundles, bases reales o documentos remotos. Estas pruebas no acreditan emisión real, producción ni un recorrido del navegador con assets compilados.
