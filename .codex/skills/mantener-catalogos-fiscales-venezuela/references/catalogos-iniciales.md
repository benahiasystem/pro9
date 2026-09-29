# Registro histórico de catálogos iniciales venezolanos

Este documento es parte normativa de la skill. Registra el estado efectivo que debe producir `database/seeders/data/tenant_initial_data.php` y el historial retirado el 5 de septiembre de 2026. Todo cambio futuro en una de estas tablas debe actualizar simultáneamente el seeder, este registro y las pruebas contractuales.

## Catálogos conservados

Los identificadores y el orden son contractuales. Salvo indicación contraria, las filas están activas.

### Bancos (`banks`)

1. BANCO DE VENEZUELA
2. BANESCO
3. BANCO MERCANTIL
4. BBVA BANCO PROVINCIAL
5. BANCO NACIONAL DE CRÉDITO
6. BANCO EXTERIOR
7. BANCO DE LA FUERZA ARMADA NACIONAL BOLIVARIANA
8. BANCO BICENTENARIO
9. BANCO CARONÍ
10. BANCO DEL CARIBE
11. BANCO FONDO COMÚN
12. BANCO PLAZA
13. BANCO VENEZOLANO DE CRÉDITO

Fuente histórica: `../pro6/database/migrations/tenant/2018_01_00_000001_tenant_system_table.php`.

### Afectaciones IVA (`cat_affectation_igv_types`)

- `10`: Gravado
- `20`: Exento

Se retiraron las 17 filas que tenían `active = 0`.

### Formas de pago Pro9 y HKA (`payment_method_types`)

La tabla operativa conserva los doce IDs locales `01`–`07`, `09`–`13`; su columna `hka_code char(2) NOT NULL` almacena el código del catálogo 11 de HKA. Los métodos locales sin equivalente específico (`05`, `06`, `07`, `09`, `12`, `13`) usan `99` Otros medios de pago. Las equivalencias específicas son `01→08`, `02→06`, `03→05`, `04→03`, `10→09` y `11→02`.

Los IDs locales `14`–`26` cubren, en orden, los códigos HKA `01`, `04`, `07`, `10`–`18` y `99`. Son trece filas iniciales inactivas de referencia, protegidas contra edición y eliminación. El código `99` se repite en siete filas, por lo que `hka_code` no es único. Los métodos locales que se creen después reciben `99` desde el servidor; el formulario no puede modificar `hka_code`. El catálogo no habilita por sí mismo pagos nuevos ni envío a HKA.

### Tipos de alícuota del manual HKA (`cat_iva_rate_types`)

| Código | Descripción | Porcentaje de referencia | Tributo |
| --- | --- | ---: | --- |
| `R` | Alícuota Reducida | 8,00 % | IVA |
| `G` | Alícuota General | 16,00 % | IVA |
| `A` | Alícuota Adicional (suntuario) | 31,00 % | IVA |
| `E` | Exento, Exonerado o No Gravado | 0,00 % | IVA |
| `P` | Percibido | 0,00 % | IVA |
| `IGTF` | Impuesto a las Grandes Transacciones Financieras | 3,00 % | IGTF |

Esta tabla reproduce el catálogo 10 del manual HKA como referencia histórica; `IGTF` es otro tributo y por eso se distingue en `tax_kind`. Sus porcentajes no configuran el IVA operativo de Pro9 ni habilitan IGTF. Los únicos tratamientos seleccionables continúan en `cat_affectation_igv_types` (`10` Gravado, `20` Exento); el IVA operativo se obtiene de `Localization`.

### Atributos (`cat_attribute_types`)

- `5010` Numero de Placa; `5011` Categoria; `5012` Marca; `5013` Modelo; `5014` Color; `5015` Motor; `5016` Combustible; `5017` Form. Rodante; `5018` VIN; `5019` Serie/Chasis.
- `5020` Año fabricacion; `5021` Año modelo; `5022` Version; `5023` Ejes; `5024` Asientos; `5025` Pasajeros; `5026` Ruedas; `5027` Carroceria; `5028` Potencia; `5029` Cilindros.
- `5030` Ciliindrada; `5031` Peso Bruto; `5032` Peso Neto; `5033` Carga Util; `5034` Longitud; `5035` Altura; `5036` Ancho.

Se retiraron las 68 filas que tenían `active = 0`.

### Descuentos y cargos (`cat_charge_discount_types`)

- `00`: Descuentos que afectan la base imponible del IVA
- `01`: Descuentos que no afectan la base imponible del IVA
- `02`: Descuentos globales que afectan la base imponible del IVA
- `03`: Descuentos globales que no afectan la base imponible del IVA
- `46`: Recargo al consumo y/o propinas
- `62`: Retención del IVA

Se retiraron `04`, `05`, `06`, `45`, `47`, `48`, `49`, `50`, `51`, `52` y `53`.

### Tipos de documento (`cat_document_types`)

- Básico SENIAT: `01` FACTURA; `FE` FACTURA DE EXPORTACIÓN; `07` NOTA DE CRÉDITO; `08` NOTA DE DÉBITO.
- Avanzado SENIAT: `20` COMPROBANTE DE RETENCIÓN DE IVA; `ISLR` COMPROBANTE DE RETENCIÓN DE I.S.L.R.; `ARCV` COMPROBANTE DE RETENCIONES VARIAS ARCV; `09` ORDEN DE ENTREGA; `CBU` CERTIFICACIÓN DE COMPRA DE BIENES USADOS.
- Interno: `80` NOTA DE VENTA; `U2` NOTA DE INGRESO ALMACÉN; `U3` NOTA DE SALIDA ALMACÉN; `U4` NOTA DE TRANSFERENCIA ALMACÉN.
- Compras: `NE76` NOTA DE ENTRADA; permanece fuera del administrador de series.

Se retiraron `02` Recibo por honorarios, `03` Boleta de venta electrónica, `04` Liquidación de compra, `14` Servicios públicos, `40` Comprobante de percepción, `71` orden/guía complementaria y `GU75` Guía. Los nombres de almacén con “Guía” pasaron a “Nota”.

`ARCV` es el identificador interno de Pro9 para el tipo `07` del catálogo HKA. El `07` interno continúa reservado para NOTA DE CRÉDITO. Esta fila del catálogo no habilita por sí sola la emisión ARCV.

### Identidad (`cat_identity_document_types`)

- `0` Doc.sin.rif; `1` Venezolano; `6` Juridico; `7` Pasaporte; `E` Extranjero; `C` Comuna; `G` Gubernamental; `R` Firma Personal. Los ocho registros operativos tienen `active = 1` y `external_document_examples = null`.
- `ND` No Domiciliado tiene `active = 0` y `external_document_examples = 'RUT, NIT'`. Su ID es local; el manual HKA sólo presenta esos valores como ejemplos de identificación de terceros compradores. Esta novena fila es referencia y no habilita altas de clientes, ventas ni un código para enviar a HKA.

Ya no forman parte del consolidado Ced. Diplomática, TIN, IN ni TAM.

### Leyendas (`cat_legend_types`)

- `1000`: Monto en Letras.

Se retiraron todas las leyendas peruanas de transferencia gratuita, percepción, Amazonía, detracción, IVAP, Tacna/zona comercial, agencia de viaje, emisor itinerante y restitución arancelaria (`1002`, `2000` a `2010`).

### Notas de crédito (`cat_note_credit_types`)

- `01`: Anulación total de factura
- `07`: Devolución parcial de mercancía
- `04`: Descuento o rebajas concedidas
- `09`: Corrección de precios o cálculos

### Notas de débito (`cat_note_debit_types`)

- `02`: Ajustes por incremento de precios
- `01`: Intereses de mora o financiamiento
- `03`: Gastos de despacho, fletes, seguros o embalaje

### Operaciones (`cat_operation_types`)

- `0101`: Venta interna; activa; `incoterm = null`.
- `0200`: Exportación de Bienes; inactiva; `incoterm = null`.
- `0201`: Exportación FOB; inactiva; `incoterm = FOB`.
- `0202`: Exportación CIF; inactiva; `incoterm = CIF`.
- `0203`: Exportación EXW; inactiva; `incoterm = EXW`.

`0201`–`0203` son identificadores locales de Pro9; el catálogo 5 del manual HKA no publica códigos para estas opciones. Permanecen inactivas y no se pueden habilitar desde la pantalla de tipos de operación hasta implementar y validar el flujo de exportación. No enviarlas como códigos HKA.

### Orígenes de producto HKA (`cat_product_origins`)

1. Nacional
2. Importado
3. Nacional e Importado

Los IDs `1` a `3` son locales y los tres registros nacen activos. El manual HKA enumera estos orígenes, pero no publica códigos; los IDs de Pro9 no deben enviarse al proveedor. Este catálogo no reutiliza `origin_addresses`, que corresponde a direcciones de despacho, y todavía no se relaciona con `items`.

### Tipos de producto HKA (`cat_product_types`)

1. Alcohol
2. Cigarrillos

Los IDs `1` y `2` son locales y ambos registros nacen activos. El manual HKA enumera estos tipos de producto, pero no publica códigos; los IDs de Pro9 no deben enviarse al proveedor. Este catálogo es sólo una referencia fiscal: no sustituye `item_types`, no reutiliza las categorías comerciales y todavía no se relaciona con `items`.

### Tipos de proveedor HKA (`cat_providers_types`)

- `1` Normal: código HKA `NULL`.
- `2` Sin RIF: `SR`.
- `3` No Residenciado: `NR`.
- `4` No Domiciliado: `ND`.

Los IDs numéricos son locales; el campo `code` almacena el valor HKA. Los cuatro registros están activos.

### Tipos de transacción HKA (`cat_transactions_types`)

- `01`: Registro
- `02`: Complemento
- `03`: Anulación
- `04`: Ajuste
- `98`: ND por IGTF
- `99`: Solo cuando la factura es a Terceros

Los seis registros iniciales están activos. Estos códigos corresponden al campo `tipoTransaccion` del catálogo 3 de HKA; no representan el identificador `transaccionId` de solicitudes ni los tipos de transacción de caja de Pro9. La condición del `99` se validará cuando exista el adaptador HKA.

### Régimen especial de tributación HKA (`cat_special_tax_regime`)

1. Zonas económicas especiales
2. Zona franca de Paraguaná
3. Zona libre de Paraguaná
4. Puerto libre Santa Elena de Uairén
5. Zona Libre de Mérida
6. Puerto libre Estado Nueva Esparta
7. Dutty Free

Los IDs `1` a `7` son locales y los siete registros están activos. El manual HKA no publica códigos para estos regímenes; no enviar los IDs como códigos al proveedor.

### Tributación del producto HKA (`cat_taxation_products`)

1. Tierra Firme
2. Régimen Especial

Los IDs `1` y `2` son locales y ambos registros nacen activos. El manual HKA enumera estos destinos tributarios, pero no publica códigos; los IDs de Pro9 no deben enviarse al proveedor. Esta clasificación general no sustituye `cat_special_tax_regime`, que conserva el detalle de los regímenes especiales, y todavía no se relaciona con `items`.

### Motivos de traslado (`cat_transfer_reason_types`)

- `04`: Traslado entre almacenes propios
- `21`: Reparación o perfeccionamiento
- `22`: Almacenes, depósitos o bodegas de otros
- `23`: Tránsito aduanero
- `24`: Otras causas (especifique)

`id` es simultáneamente la clave primaria `varchar(2)` y el código funcional. HKA enumera estos motivos pero no publica códigos; `22`, `23` y `24` son identificadores locales de Pro9 y no deben presentarse como códigos oficiales de HKA o SENIAT. Todos nacen activos y con `discount_stock = 0`.

Los cinco códigos y sus descripciones forman un catálogo cerrado: el tenant sólo puede configurar `discount_stock`. Cada Orden de entrega guarda en `dispatches.discount_stock` el efecto real calculado al emitirse; las órdenes relacionadas con una venta, nota de pedido o documento previo conservan `0` para evitar un segundo descuento. Anulaciones, Kardex y reportes consultan ese snapshot y no el valor vigente del catálogo. El código `24` exige detalle y se presenta como `Otras causas (especifique): {detalle}`.

### Motivos de gasto (`expense_reasons`)

1. Honorarios profesionales
2. Publicidad, propaganda y mercadeo
3. Comisiones de ventas y corretaje
4. Mantenimiento y reparación
5. Vigilancia y seguridad
6. Limpieza y aseo
7. Fletes, transporte y mensajería
8. Arrendamiento de inmuebles
9. Alquiler de bienes muebles y equipos
10. Energía eléctrica
11. Agua potable
12. Telecomunicaciones, Internet y servicios digitales
13. Viáticos, viajes y movilización
14. Gastos de representación
15. Papelería, útiles y suministros de oficina
16. Impuestos, tasas y contribuciones
17. Multas, sanciones e intereses de mora
18. Gastos sin soporte fiscal válido
19. Sueldos, salarios y remuneraciones
20. Beneficios laborales y prestaciones sociales
21. Aportes patronales: IVSS, FAOV e INCES
22. Seguros y pólizas
23. Gastos bancarios, comisiones y servicios financieros
24. Intereses y gastos de financiamiento
25. Depreciación y amortización
26. Combustible, lubricantes y peajes
27. Repuestos y mantenimiento de vehículos
28. Sistemas, software, licencias y suscripciones
29. Servicios profesionales técnicos y consultoría
30. Otros gastos operativos

Este catálogo no conserva datos históricos: los IDs `1` a `30` constituyen el contrato inicial completo. La migración tenant sólo crea la estructura y todas las filas se cargan desde `database/seeders/data/tenant_initial_data.php`. Las descripciones fiscales sobre retenciones son reglas futuras de parametrización; `expense_reasons` sólo posee `id` y `description`, por lo que no se inventan porcentajes ni banderas inexistentes.

### Grupos (`groups`)

- `01`: Facturas. Se retiró `02`: Boletas.

## Catálogos y tablas eliminados

Estas tablas no deben existir en `tenant_initial_data.php`, no deben tener migración de creación consolidada ni claves foráneas hacia ellas:

- `cat_other_tax_concept_types` (12 filas históricas: `1000`–`1005`, `2001`–`2005`, `3001`).
- `cat_perception_types` (3: `01` venta interna, `02` combustible, `03` tasa especial).
- `cat_related_documents_types` (6: `01`–`06`, DAM, guía/orden, SCOP, manifiesto, detracción y otros).
- `cat_related_tax_document_types` (6: `01`–`05`, `99`, anticipos y referencias tributarias peruanas).
- `cat_summary_status_types` (3: `1` Adicionar, `2` Modificar, `3` Anulado).
- `cat_system_isc_types` (3: `01` al valor, `02` monto fijo, `03` precio de venta).
- `pse_providers` (5: ContaWeb, Gior, QPSE, SendFact y Validapse).
- `cat_payment_method_types` (13 filas retiradas: `001`–`006`, `010`, `101`–`105`, `999`). Eliminación completa junto con detracciones, sin históricos ni migración incremental. Los pagos operativos siguen en `payment_method_types`.
- `cat_detraction_types` (12: `001`, `003`, `005`, `008`, `016`, `019`, `020`, `022`, `023`, `025`, `027`, `030`).

## Territorio retirado del consolidado

El consolidado contenía 25 departamentos, 196 provincias y 1.876 distritos de Perú. Las claves `departments`, `provinces` y `districts` deben estar ausentes de `tenant_initial_data.php`. `TenantMigrationDataSeeder` las sustituye por `venezuela_geopolitical_data.php`, cuyo contrato es 25 estados, 335 municipios y 1.138 parroquias.

## Actualización del 10 de septiembre de 2026: modalidad fiscal

`fiscal_environments` sustituye totalmente a `soap_types`:

| ID | Presentación |
| --- | --- |
| `demo` | Demo |
| `production` | Producción |

No existe ambiente Interno ni equivalencia pública con `01`, `02` o `03`. Las modalidades se validan en `FiscalEmissionSettings`: `fiscal_machine`, `digital`, `free_form`; no son ambientes ni implican integración con un proveedor.

El seeder no crea los tipos de auditoría `companies_certificate`, `companies_soap_password`, `companies_soap_send_id`, `companies_soap_type_id`, `companies_soap_url` y `companies_soap_username`. No hay registros anteriores que limpiar. Los cambios nuevos se registran en `fiscal_configuration_audits`, creada directamente por el consolidado y sin filas iniciales ni secretos. El inventario actual de datos iniciales contiene 80 tablas y 955 filas, incluidos los catálogos HKA de referencia; no renumerar otros identificadores.

`cat_unit_types` conserva 28 unidades locales activas y añade `hka_code varchar(3) NOT NULL` con una equivalencia UNECE Rec. 20 por fila. Las unidades de envase usan el prefijo `X` de la nota 2 del Excel (por ejemplo, `BOL` → `XBG`); `BTO` → `XBE` interpreta «bulto» como *bundle*. El mantenimiento queda cerrado: no se pueden crear, editar ni eliminar unidades; las consultas y el cambio de estado de las no reservadas siguen disponibles. Los códigos son referencia y no habilitan envío de unidades a HKA. La tabla íntegra está en la skill `mantener-unidades-medida-venezuela`.

### Retenciones ISLR declaradas ante SENIAT

`cat_retention_concept` contiene los 86 códigos `001`–`086`, su actividad y el porcentaje **textual** del anexo 6.1 del manual técnico 3.1 de junio de 2014. `cat_retention_types` contiene exclusivamente los tres tipos del anexo 6.2: `01` Dividendo en acciones (DA), `02` Dividendo en efectivo (DE) y `03` Venta de acciones (VA). Sustituyen las antiguas filas `01` Tasa 3 % y `02` Tasa 6 %; la creación del módulo que calculaba con esas tasas queda deshabilitada. Las tablas son referencias históricas y no acreditan vigencia de las tasas, generación XML ni emisión fiscal. Ver [el informe fuente](../../../../informes/xml_retenciones_islr_seniat.md).

La instalación nueva define directamente los trece estados actuales de ecommerce en `status_orders`, sin convertir cuatro estados anteriores ni volver a sembrarlos desde el alta del tenant. No incluye el servicio PENALIDAD asociado al motivo retirado de nota de débito 13; conserva DELIVERY-ECOM. Los cambios de conteo incluyen estas decisiones y la configuración inicial consolidada.

Consultar también [mantener-modalidad-emision-fiscal-pro9](../../mantener-modalidad-emision-fiscal-pro9/SKILL.md). Las instalaciones nuevas crean directamente el esquema fiscal final, sin columnas SOAP/PFX/PSE ni migraciones de conversión.
