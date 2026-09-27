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

### Motivos de traslado (`cat_transfer_reason_types`)

- `01`: Venta
- `04`: Traslado entre almacenes
- `06`: Devolución a proveedor
- `05`: Demostración, evento o consignación
- `20`: Demostración o evento (código venezolano nuevo)
- `21`: Reparación, servicio técnico o mantenimiento (código venezolano nuevo)

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

El seeder no crea los tipos de auditoría `companies_certificate`, `companies_soap_password`, `companies_soap_send_id`, `companies_soap_type_id`, `companies_soap_url` y `companies_soap_username`. No hay registros anteriores que limpiar. Los cambios nuevos se registran en `fiscal_configuration_audits`, creada directamente por el consolidado y sin filas iniciales ni secretos. El inventario actual de datos iniciales contiene 76 tablas y 926 filas, incluidos ARCV y los catálogos HKA de proveedores, transacciones y regímenes especiales; no renumerar otros identificadores.

### Retenciones ISLR declaradas ante SENIAT

`cat_retention_concept` contiene los 86 códigos `001`–`086`, su actividad y el porcentaje **textual** del anexo 6.1 del manual técnico 3.1 de junio de 2014. `cat_retention_types` contiene exclusivamente los tres tipos del anexo 6.2: `01` Dividendo en acciones (DA), `02` Dividendo en efectivo (DE) y `03` Venta de acciones (VA). Sustituyen las antiguas filas `01` Tasa 3 % y `02` Tasa 6 %; la creación del módulo que calculaba con esas tasas queda deshabilitada. Las tablas son referencias históricas y no acreditan vigencia de las tasas, generación XML ni emisión fiscal. Ver [el informe fuente](../../../../informes/xml_retenciones_islr_seniat.md).

La instalación nueva define directamente los trece estados actuales de ecommerce en `status_orders`, sin convertir cuatro estados anteriores ni volver a sembrarlos desde el alta del tenant. No incluye el servicio PENALIDAD asociado al motivo retirado de nota de débito 13; conserva DELIVERY-ECOM. Los cambios de conteo incluyen estas decisiones y la configuración inicial consolidada.

Consultar también [mantener-modalidad-emision-fiscal-pro9](../../mantener-modalidad-emision-fiscal-pro9/SKILL.md). Las instalaciones nuevas crean directamente el esquema fiscal final, sin columnas SOAP/PFX/PSE ni migraciones de conversión.
