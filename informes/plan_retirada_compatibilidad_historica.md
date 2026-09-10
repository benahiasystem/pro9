# Plan de retirada de compatibilidad histórica de otros módulos

Fecha: 10 de septiembre de 2026. Estado: propuesta, **no implementada**. Complementa la consolidación fiscal SOAP/PFX documentada en `adaptacion_modalidad_emision_fiscal.md`.

## Objetivo y límites

Pro9 se instalará desde cero, sin tenants ni documentos anteriores. El resultado debe crear directamente la estructura y los catálogos vigentes, sin convertir registros peruanos ni reproducir versiones anteriores de los módulos. No ejecutar cambios sobre bases reales, desplegar ni crear commits como parte de este plan.

«Compatibilidad histórica» significa código cuya única función es leer, transformar, reparar o presentar datos de versiones retiradas. No incluye la auditoría de operaciones futuras, controles de seguridad, normalización del formato de entrada vigente, inicialización de nuevos establecimientos ni funciones actuales que utilizan nombres técnicos antiguos. No borrar por coincidencias textuales con `legacy`, `histórico`, `igv`, `dispatch` o `guia`.

## Alcance por módulo

| Módulo | Retirada propuesta | Comportamiento que debe conservarse |
| --- | --- | --- |
| Instalación tenant | Comando `MigrateExistingTenantToVenezuela`, reconciliación del historial y `ExistingTenantMigrator`; transformaciones de registros existentes en los seeders | Alta nueva con todas las tablas, relaciones, configuración y catálogos necesarios |
| Moneda | Conversión PEN/VED → VES, migración retrospectiva `000329` y alias de entrada antiguos | VES/USD, símbolos, conversiones entre monedas admitidas y cálculos comerciales |
| Identidad y geografía | `IdentityDocumentCatalogMigrator`, sustitución de códigos antiguos y direcciones PE → VE | Catálogos venezolanos iniciales, RIF/cédula/pasaporte vigentes y validación del formato actual |
| IVA y catálogos | Remapeos retrospectivos, códigos guardados sólo para documentos retirados y datos iniciales incompatibles | Afectaciones vigentes, tasa configurable, exenciones y consistencia de compras/ventas |
| Boletas y series | Consulta, impresión y ramas exclusivas de Boletas antiguas; series y referencias que sólo las sostienen | Facturas, Notas de venta, notas de crédito/débito sobre Facturas y correlativos actuales |
| Fiscalidad retirada | Campos, cálculos y presentación de ISC/bolsas que sólo existan para conservar documentos antiguos | IVA y otros conceptos comerciales vigentes; PDF, cobros y totales correctos |
| Cotizaciones y ecommerce | Numeración COTV/anual, reconstrucción de números y detección del origen por textos antiguos; conversión de cuatro estados de pedidos al conjunto actual | Numeración actual, origen explícito, estados actuales, campañas, variaciones, seguimiento y stock |
| Productos e inventario | Migraciones de reparación de estructuras anteriores y respaldos de precios utilizados exclusivamente por versiones retiradas | Productos/servicios/variaciones, precios actuales, búsqueda, almacenes y movimientos |
| Órdenes de entrega | Alias visibles y conversiones que sólo permitan representar documentos retirados | Órdenes de entrega actuales, rutas, PDF y claves internas que siguen usándose |
| Telefonía e importaciones | Reescritura automática de prefijos de otro país y lectura alternativa de formatos de importación retirados, cuando se confirme su uso exclusivamente histórico | Normalización venezolana y contrato del archivo Excel actual, con errores claros para entradas inválidas |

La lista de archivos a borrar se cerrará después de seguir sus consumidores. Una clase mixta se simplifica: por ejemplo, `VendeyaDocumentPayloadNormalizer` también calcula IVA y no debe eliminarse completa por contener la conversión PEN/VED.

## Orden de ejecución

1. **Inventario verificable.** Buscar consumidores en PHP, Vue, API, PDF, reportes, comandos y pruebas. Clasificar cada caso como conversión antigua, función vigente o estructura necesaria para instalaciones nuevas. Registrar archivo, cambio y prueba correspondiente. Capturar esquema y datos iniciales de la rama en una base temporal, sin recurrir a un tenant real.
2. **Corregir el estado inicial.** Preparar monedas, identidad, geografía, afectaciones y catálogos vigentes directamente en los datos semilla. Resolver referencias inválidas antes de retirar conversores. Caso ya detectado: el producto inicial con afectación de compra/venta `30` queda sin catálogo correspondiente; determinar si ese producto debe retirarse o recibir una afectación vigente según su finalidad, sin remapearlo a ciegas.
3. **Consolidar la estructura.** Trasladar columnas, índices y tablas todavía necesarios de las migraciones posteriores a las creaciones base y al archivo final de claves foráneas. Incluir las estructuras vigentes de ecommerce, productos e inventario. Después retirar backfills, migraciones transitorias y el comando para tenants existentes. Eliminar una migración sólo cuando su efecto vigente ya esté representado en el esquema inicial.
4. **Simplificar entradas y servicios.** Retirar conversiones antiguas de moneda, identidad, dirección y teléfono. Validar directamente contra el contrato actual. Conservar correcciones de formato legítimas, como espacios o guiones en un RIF. Revisar por separado los formatos de importación antes de retirar una lectura alternativa.
5. **Simplificar documentos.** Retirar Boletas y campos fiscales exclusivamente históricos desde modelos, formularios, cálculos, consultas, reportes y PDF hasta el esquema. Mantener los flujos de Factura, Nota de venta, notas de crédito/débito y Órdenes de entrega. No retirar módulos comerciales completos por contener una rama de Boletas.
6. **Simplificar ecommerce e inventario.** Retirar `Quotation::SERIES_ECOMMERCE` y las búsquedas alternativas basadas en referencias antiguas cuando no queden consumidores vigentes; consolidar los estados actuales desde una única definición. Preservar campañas, precios, variaciones y la creación de series para nuevos establecimientos.
7. **Alinear pruebas y skills.** Sustituir escenarios de conversión histórica por pruebas de instalación nueva y rechazo de contratos retirados. Actualizar las skills de adaptación, reconstrucción, catálogos, moneda, identidad, geografía, IVA, ventas, fiscalidad local, órdenes, telefonía, productos e importación según los archivos realmente modificados. Conservar trazabilidad documental y controles de seguridad.

## Verificación y aceptación

- Creación completa, seeding, rollback y segunda creación en bases temporales: mismo esquema y mismos datos salvo marcas temporales generadas. Comprobar **todas** las claves foráneas, incluidos datos semilla.
- Alta de tenant con modalidad/ambiente explícitos, establecimiento y usuario; ninguna conversión de datos antiguos durante el alta.
- Crear producto y variación; importar el formato Excel vigente; crear cliente venezolano; comprobar ventas, compras, stock, POS, cobros, descuentos e IVA.
- Emitir localmente Factura y Nota de venta, generar sus PDF y notas de crédito/débito permitidas; comprobar Órdenes de entrega y reportes. Mantener aislamiento entre tenants y secretos fuera de respuestas/logs.
- Probar cotización ecommerce, cambios de estado, numeración, campaña y descuento sin reconstrucciones de datos antiguos. Usar correo y servicios externos simulados.
- Actualizar los contratos de pruebas que hoy esperan compatibilidad retirada; resolver los fallos previos relevantes con evidencia, sin borrar pruebas sólo para obtener verde.
- Validar PHP, las skills modificadas y los componentes afectados. Aplicar la skill `frontend-build`; la compilación requiere la petición explícita prevista por esa skill. Registrar cualquier comprobación visual pendiente.
- Búsqueda final sin referencias ejecutables huérfanas a conversores eliminados. Las afirmaciones de ausencia en pruebas y la documentación de decisiones pueden mencionar nombres retirados.

## Entrega

Entregar cambios revisables sin commits, inventario de piezas retiradas/conservadas, resultados de pruebas y limitaciones. No desarrollar integraciones con proveedores fiscales ni cambiar bases reales. Esta propuesta no autoriza por sí sola la ejecución de la segunda fase.
