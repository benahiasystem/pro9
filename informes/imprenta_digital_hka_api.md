# Referencia técnica de integración con la imprenta digital HKA

**Fuente:** [IT-21-01-01, Referencia técnica API Facturación Digital, V02, 50 páginas](../../implementaciones%20con%20HKA/manuales/%5BIT-21-01-01%5D%5BV02%5DREFERENCIA%20TECNICA%20API-26.pdf). Las páginas citadas son las páginas físicas del PDF, no la numeración desplazada del índice. La portada dice septiembre de 2026; la cabecera indica 14/05/2026 y el control de cambios llega a 2.2 (11/09/2026). Esas versiones deben confirmarse con HKA antes de integrar. El PDF declara propiedad de HKA y restricción de reproducción; esta referencia reorganiza sus datos técnicos para desarrollo y remite al original.

**Fuente complementaria:** [Swagger DEMO, Imprenta Digital VE](https://demoemisionv2.thefactoryhka.com.ve/swagger/index.html?urls.primaryName=Imprenta+Digital+VE), cuya definición es [`/swagger/v1/swagger.json`](https://demoemisionv2.thefactoryhka.com.ve/swagger/v1/swagger.json). Consultado el 23/09/2026: OpenAPI 3.0.4, título `Imprenta Digital VE - Demo`, versión `v1`, 29 rutas, 35 operaciones y 130 esquemas; SHA-256 de la respuesta `b45a33deaa3885a60b54c009b1a1d702943a1aa2416e95d22d993151f977f55b`. El Swagger describe una interfaz publicada, no demuestra que una solicitud con credenciales reales funcione. Las secciones 10–12 separan sus hallazgos de lo que afirma el PDF.

**Estado en Pro9:** documento de preparación. La imprenta digital sigue en desarrollo y no está habilitada para emisión productiva. Elegir `digital` o usar un simulador DEMO no acredita conexión ni emisión fiscal real. Véanse [numeración fiscal](numeracion_fiscal_venezuela.md) y [modalidad fiscal](adaptacion_modalidad_emision_fiscal.md).

El control de cambios de la p. 2 registra: 1.00 (manual original); 1.01 (arquitectura, categorías API y pruebas); 1.02 (campos, catálogos y solicitudes); 1.03 (seguridad de red); 1.04 (identificación); 1.05 (HTTPS, TLS, JWT, Ubigeo y errores); 1.06 (formato); 1.07 (retenciones); 1.08 (ítems y totales); 1.09 (otros impuestos); 02 (ARCV, `EsNegativo`, campos adicionales y serie); 2.1 (guía, OTI, recargos, ND por IGTF y tolerancias); 2.2 (sustraendo). La portada sigue rotulada V02 pese a las entradas 2.1 y 2.2.

## 1. Límite entre Pro9 y HKA

Pro9 conserva sus tipos, números, series, controles, reservas, secuencias, validaciones e identidad fiscal. Los códigos HKA pertenecen **sólo** al adaptador de la imprenta: construir solicitudes, interpretar respuestas y hacer consultas al proveedor. La traducción se efectúa por **significado del documento**, nunca por igualdad numérica, y no renombra `document_type_id` ni cambia `FiscalProfileService::TYPES`.

| Documento | Código Pro9 actual | Código HKA, catálogo 1 | Regla |
|---|---:|---:|---|
| Factura | `01` | `01` | Coincidencia de código, contratos separados. |
| Nota de crédito | `07` | `02` | Traducir sólo al entrar o salir del adaptador HKA. |
| Nota de débito | `08` | `03` | Igual. |
| Orden de entrega | `09` | `04` (nota de entrega/guía) | Igual; conservar el nombre y contrato internos de dispatch. |
| Retención IVA | Sin correspondencia fiscal Pro9 definida | `05` | Documentar; no crear tipo interno por inferencia. |
| Retención ISLR | Sin correspondencia fiscal Pro9 definida | `06` | Igual. |
| Retenciones varias ARCV | Sin correspondencia fiscal Pro9 definida | `07` | `07` HKA **no** significa nota de crédito Pro9. |

La numeración documental local y el número de control asignado por la imprenta son identidades distintas. `AsignarNumeraciones` y la respuesta de emisión deben conciliarse con la reserva fiscal local, respetando idempotencia y estados inciertos. Los códigos monetarios, unidades, IVA, identidad del cliente y medios de pago también necesitan mapeos de borde; no se reemplazan catálogos Pro9 con tablas HKA. [Fuente: pp. 9, 14–15, 37–40; contratos Pro9 citados arriba.]

| Tipo HKA | Nodos que requieren atención por tipo, además de `IdentificacionDocumento` | Evidencia del manual |
|---|---|---|
| `01` factura | `Comprador`, `Totales`, `DetallesItems`, impuestos/pagos; `FacturaGuia`, `Tercero`, `Viajes` u otras monedas según caso. | Tablas pp. 14–29; ejemplo pp. 43–45 incompleto. |
| `02` NC y `03` ND | Referencia a factura afectada, comprador, totales y líneas/ajustes según operación. | Tabla pp. 14–15; ejemplo completo sólo para ND pp. 45–48. |
| `04` guía/nota de entrega | `GuiaDespacho` y datos de conductor, vehículo, transportista y traslado; vínculo `FacturaGuia` si corresponde. | Tablas pp. 15, 29–34; sin ejemplo integral. |
| `05` retención IVA y `06` ISLR | `SujetoRetenido`, `TotalesRetencion`, `DetallesRetencion` y referencias a documentos; concepto para ISLR. | Tablas pp. 17, 22, 31–33; sin ejemplo integral. |
| `07` ARCV/ARC | Banderas, beneficiario, totales y detalles ARCV. Swagger define un modelo ARC y endpoint propio. | Tablas pp. 34–36 y `POST /api/EmisionARC`; sin ejemplo integral del PDF. |

## 2. Alcance y arquitectura de la API (secciones 1–3, pp. 6–7)

El manual se dirige a desarrolladores que conectan un emisor con la plataforma REST de HKA mediante HTTPS y JSON. Describe nodos JSON, solicitud/respuesta, JWT y layout. La autenticación proporciona un token usado como `Authorization: Bearer {token}`. La sección 3.6 afirma TLS 1.3; la sección 9 menciona 1.2 y 1.3. La versión TLS admitida debe probarse en DEMO, sin desactivar la verificación del servidor.

## 3. Operaciones API (sección 4, pp. 8–13)

Todas las rutas indicadas se combinan con la URL del ambiente. El manual indica `Content-Type: application/json`; salvo autenticación, muestra Bearer. Los nombres de campos de este apartado conservan las minúsculas de las solicitudes. No se debe inferir el esquema de una respuesta que el PDF no especifica.

| Operación y página | Método/ruta publicados | Solicitud y finalidad |
|---|---|---|
| Autenticación, p. 8 | `POST /api/Autenticacion` | `usuario`, `clave`; retorna token JWT, sin esquema detallado. Credenciales suministradas por HKA. |
| Emisión, p. 8 y pp. 13–36 | El PDF omite ruta/método; Swagger publica `POST /api/Emision` | Enviar `documentoElectronico`; Swagger limita este esquema a tipos `01`–`06` y define `POST /api/EmisionARC` para `07`. Ver §10. |
| Estado, pp. 8–9 | `POST /api/EstadoDocumento` | `serie`, `tipoDocumento`, `numeroDocumento`; consultar estado antes de reenviar una emisión incierta. |
| Asignación, p. 9 | `POST /api/AsignarNumeraciones` | `detalleAsignacion[]`: `serie`, `tipoDocumento`, `numeroDocumentoInicio`, `numeroDocumentoFin`. Asocia controles a números documentales; luego se completa la emisión. |
| Envío de correo, pp. 9–10 | `POST /api/Correo/Enviar` | `serie`, `tipoDocumento`, `numeroDocumento`, `correos[]`; envía PDF. |
| Rastreo de correo, p. 10 | `POST /api/Correo/Rastreo` | `serie`, `tipoDocumento`, `numeroDocumento`; consulta entrega. |
| Descargar archivo, p. 10 | `POST /api/DescargaArchivo` | `serie`, `tipoDocumento`, `numeroDocumento`; obtiene PDF. No consta tipo/cuerpo de respuesta. |
| Estado de lote, p. 11 | `POST /api/EstadoLote` | `nombreArchivo`; consulta el proceso de un archivo enviado por SFTP. El transporte SFTP y su formato no están especificados aquí. |
| Listado, p. 11 | `POST /api/ListadoDocumentos` | `serie`, `tipoDocumento`, `fechaInicio`, `fechaFin`, `numPagina`; el texto lo describe como documentos anulados/cancelados de un período. Swagger precisa el DTO y los totales de paginación (§10); comprobarlos en DEMO. |
| Anular, pp. 11–12 | `POST /api/Anular` | `serie`, `tipoDocumento`, `numeroDocumento`, `motivoAnulacion`, `fechaAnulacion`, `horaAnulacion`. Conciliar resultado antes de cualquier nueva acción local. |
| Aplicar retención, p. 12 | `POST /api/AplicarRetencion` | El PDF muestra `serie`, `numeroDocumento`, `numeroControl`, `totalRetencion`; Swagger exige además `tipoDocumento` (`01`–`03`) y permite fecha/hora de aplicación. Actualiza datos para el libro de ventas. |
| Ver retención, p. 13 | `GET /api/AplicarRetencion` | `serie`, `tipoDocumento`, `numeroDocumento`, `numeroControl` en un cuerpo JSON tanto en PDF como en Swagger. Verificar interoperabilidad de GET con cuerpo en DEMO. |
| Eliminar retención, p. 13 | `DELETE /api/AplicarRetencion` | `serie`, `tipoDocumento`, `numeroDocumento`, `numeroControl` en cuerpo JSON según Swagger. |

El PDF indica serie vacía si no se usa; en p. 9 llega a sugerir la cadena `"nulo"`, mientras p. 15 ejemplifica `" "`. Esas tres representaciones no son equivalentes: no fijar una hasta probarla con HKA. `tipoDocumento` en todas estas operaciones es el **código HKA**, obtenido desde el tipo Pro9 por la matriz anterior. Fechas: `dd/mm/aaaa`; horas: 12 horas, `hh:mm:ss am/pm`. [Fuente: pp. 8–13, 14–15.]

## 4. Estructura de emisión (sección 5, pp. 13–36)

El nodo raíz es `DocumentoElectronico`/`documentoElectronico`. El manual usa mayúsculas iniciales en las tablas y ejemplos de notas, pero minúsculas iniciales en el ejemplo de factura. Swagger publica propiedades `lowerCamelCase`, por ejemplo `documentoElectronico.encabezado.identificacionDocumento.tipoDocumento`; **JSON distingue mayúsculas**, por lo que los ejemplos del PDF no deben copiarse sin adaptar. Los nombres siguientes reproducen la forma de las tablas; §11 recoge diferencias del esquema Swagger. `O` = obligatorio según tabla, `C` = condicional, `P` = opcional, `—` = la tabla no declara condición. Un nodo opcional no vuelve obligatorios sus campos para todos los documentos.

Mapa conceptual de nodos: `DocumentoElectronico` contiene `Encabezado` (`IdentificacionDocumento`, `FacturaGuia`, `Vendedor`, `Comprador` y `OtrosEnvios`, `SujetoRetenido`, `Tercero`, `Totales` con descuentos/impuestos/pagos, `TotalesRetencion`, `TotalesOtraMoneda`, `Orden`) y nodos de detalle o especialidad (`DetallesItems` con información/OTI, `Viajes`, `InfoAdicional`, `GuiaDespacho` con conductor/vehículo/transportista, `DetallesRetencion`, `Transporte`, banderas y ARCV). El índice y las tablas describen esos elementos, pero no publican un ejemplo de anidamiento integral de todos ellos; este mapa no debe copiarse como JSON ejecutable. [Fuente: pp. 3–4, 13–36.]

### 4.1 Encabezado: `IdentificacionDocumento`, `FacturaGuia`, `Vendedor` (pp. 14–16)

| Campo de `IdentificacionDocumento` | Cond. | Formato o uso HKA |
|---|---|---|
| `TipoDocumento` | O | 2 dígitos; catálogo 1 HKA. |
| `NumeroDocumento` | O | 1–19 dígitos; máximo numérico `9223372036854775807` según nota. El ejemplo de factura contiene guion. |
| `TipoProveedor` | P | 2 caracteres; catálogo 2. |
| `TipoTransaccion` | P | 2 dígitos; catálogo 3. |
| `NumeroPlanillaImportacion`, `NumeroExpedienteImportacion` | P | Hasta 20 caracteres cada uno; importación. |
| `SerieFacturaAfectada`, `NumeroFacturaAfectada` | C | Hasta 20 caracteres; referencia de la factura para NC/ND. La primera identifica la serie si existe. |
| `FechaFacturaAfectada` | C | `DD/MM/AAAA`, hasta 10; NC/ND. |
| `MontoFacturaAfectada` | C | Hasta 10 caracteres, ejemplo con 8 enteros y 2 decimales; NC/ND. |
| `ComentarioFacturaAfectada` | P | Hasta 255 caracteres. |
| `RegimenEspTributacion` | P | Hasta 55 caracteres; catálogo 4. |
| `FechaEmisión`/`FechaEmision` | O | `DD/MM/AAAA`, hasta 10; la tabla y los ejemplos difieren en acento/capitalización. |
| `FechaVencimiento` | P | `DD/MM/AAAA`, hasta 10. |
| `HoraEmision` | O | `hh:mm:ss am/pm`, 11 caracteres. |
| `Anulado` | P | Booleano; p. 15 acepta `false` o `null`. |
| `TipoDePago` | P | Inmediato o crédito; hasta 20 caracteres. |
| `Serie` | O | Hasta 20 caracteres; vacía cuando no se usa, con ambigüedad indicada en §3. |
| `Sucursal` | P | Código de sucursal, hasta 6 caracteres. |
| `TipoDeVenta` | O | Hasta 20 caracteres; catálogo 5. |
| `Moneda` | O | Código de 3 caracteres; catálogo 7, con contradicciones en §8. |
| `transaccionId` | P | Hasta 50 caracteres; sólo modo de asignación automática según p. 15. |

`FacturaGuia` vincula factura y guía/orden: `TipoDocumento` (2 dígitos), `Serie` (hasta 20) y `NumeroDocumento` (hasta 20), todos opcionales en p. 15. Usar códigos HKA en el vínculo externo; conservar referencias locales. `Vendedor` incluye `Codigo` (20), `Nombre` (255), `NumCajero` (20), todos opcionales. [Fuente: pp. 15–16.]

### 4.2 Comprador, envíos, retenido y tercero (pp. 16–18)

| Nodo | Campos y contrato |
|---|---|
| `Comprador` | `TipoIdentificacion` O (catálogo 8; la tabla dice 3 caracteres, pero los códigos comunes son 1), `NumeroIdentificacion` O (máx. 19), `RazonSocial` O (máx. 100), `Direccion` O (máx. 255), `Ubigeo` P (máx. 8), `Pais` O (2, catálogo 9), `Notificar` P (`Si`/`No`, pese a la descripción 1/0), `Telefono[]` y `Correo[]` condicionales al uso (teléfono hasta 20 por valor, correo hasta 50, listas hasta 255). |
| `OtrosEnvios[]` del comprador | `tipo` P (hasta 20, ejemplo WhatsApp/SMS), `destino` P (hasta 14). El servicio puede requerir contratación adicional. |
| `SujetoRetenido` | Sólo comprobantes de retención: `TipoIdentificacion` O (1, catálogo 8), `NumeroIdentificacion` O (máx. 12), `RazonSocial` O (100), `Direccion` O (255), `Ubigeo` P (8), `Pais` O (2), `Notificar` P, `Telefono[]` y `Correo[]` condicionales al uso. |
| `Tercero` | Para facturación por cuenta de terceros: `TipoIdentificacion` (hasta 3), `NumeroIdentificacion` (12), `RazonSocial` (100), `Direccion` (255), `Tipo` (sector, 255), `Correo[]` (50 por dirección). La tabla deja la condición sin definir. |

Antes de mapear identificaciones, ubicaciones y teléfonos, consultar los skills de clientes, geopolítica y telefonía de Pro9. No reutilizar `Ubigeo` como si fuese automáticamente el ID de parroquia interno. [Fuente: pp. 16–18.]

### 4.3 Totales, impuestos, pagos y otras monedas (pp. 18–26)

Importes de estas tablas: cadenas/decimales con punto, por regla general hasta **16 enteros y 2 decimales**. Tipos JSON exactos no están fijados en el texto; los ejemplos usan cadenas. No usar tolerancias HKA para relajar la aritmética fiscal local.

| Campo de `Totales` | Cond. | Significado y formato particular |
|---|---|---|
| `nroItems` | O | Cantidad de líneas, máximo 4 dígitos. |
| `montoGravadoTotal` | O | Bases gravadas, sin impuesto. |
| `montoExentoTotal`, `MontoPercibidoTotal` | P | Bases exentas y percibidas. |
| `subtotal` | O | Bases gravadas + exentas/percibidas, considerando descuentos según descripción. |
| `totalAPagar`, `totalIVA`, `montoTotalConIVA` | O | Total exigible; IVA; total con IVA sin IGTF ni otros impuestos. |
| `montoTotalIVAyOTI`, `MontoTotalOTI` | P | Total con otros impuestos y acumulado OTI. |
| `montoEnLetras` | P | Hasta 255 caracteres; expresa `montoTotalConIVA`. |
| `totalDescuento` | P | Descuento total. |
| `TotalRecargos` | P | Recargos; 6 enteros y 2 decimales. |
| `SubtotalAntesDescuento` | C | Se exige si hay descuento total o recargo, aunque la columna dice `Opcional*`; 6 enteros y 2 decimales. |

`listaDescBonificacion[]`: `descDescuento` (0–255 caracteres) y `montoDescuento` (16+2), opcionales. `impuestosSubtotal[]`: `codigoTotalImp` O (catálogo 10; hasta 255 según tabla), `alicuotaImp` O (2+2), `baseImponibleImp` P (16+2), `valorTotalImp` O (16+2). Un objeto por alícuota. `FormasPago[]`: `descripcion` P (100), `fecha` O (`DD/MM/AAAA`), `forma` O (2, catálogo 11), `monto` O (16+2), `moneda` O (3, catálogo 7), `tipoCambio` C si se paga en divisas (16+4), `descDescuento` P (255). [Fuente: pp. 20–21.]

`TotalesRetencion` (pp. 22–23): `totalBaseImponible`, `numeroCompRetencion` (hasta 50), `fechaEmisionCR` (`DD/MM/AAAA`), `totalIVA`, `totalRetenido`, `totalISRL`, `totalIGTF`, `tipoComprobante` (hasta 2 dígitos) y `Esnegativo` (booleano). La columna de condición está incompleta: indica opcional en factura y obligatorio en comprobante, sin desglosar cada campo. El texto dice `null` para ISLR en `totalRetenido` y `totalISRL`, probablemente un error; confirmar por tipo. `TotalIGTF` y `TotalIGTF_VES` (16+2) aparecen inmediatamente antes de ese nodo sin ubicación inequívoca **en el PDF**; Swagger los ubica en `encabezado.totales` como `totalIGTF` y `totalIGTF_VES`. [Fuente: pp. 21–23; Swagger §11.]

`TotalesOtraMoneda` (pp. 23–24) declara `moneda` (3), `tipoCambio` (16+4), `montoGravadoTotal`, `montoExentoTotal`, `MontoPercibidoTotal`, `subtotal`, `totalAPagar`, `totalIVA`, `montoTotalIVAyOTI`, `MontoTotalOTI`, `montoTotalConIVA`, `montoEnLetras` (255), `totalDescuento` y `TotalRecargos` (6+2). La columna de condición es `—` para casi todos. Si se usa otra moneda, `ImpuestosSubtotal[]` requiere `codigoTotalImp` (hasta 4), `alicuotaImp` (2+2), `baseImponibleImp` (16+2), `valorTotalImp` (16+2). `OtrosImpuestosSubtotal[]` requiere `codigoOTI` (hasta 256), `PorcentajeOTI` (2+2), `MontoOTI` (16+2); `BaseImponibleOTI` es opcional (16+2). [Fuente: pp. 23–25.]

El manual propone `subtotal = montoGravadoTotal + montoExentoTotal`, `totalIVA = Σ valorIVA`, `montoTotalConIVA = subtotal + totalIVA`, `totalAPagar = montoTotalConIVA + IGTF` si lo hay, y sumar OTI en `montoTotalIVAyOTI`. También menciona descuentos, recargos y otros impuestos, pero sus fórmulas abreviadas no los integran de manera consistente. Preparar cálculos desde importes persistidos y validar el contrato con casos DEMO; HKA dice que no valida todos los cálculos de la facturación. `Orden` (p. 26) agrupa documentos para correo consolidado: `numero` P (hasta 20) y `correo[]` O cuando se usa el nodo (hasta 50 cada uno y 255 total).

### 4.4 Líneas, tributos adicionales y campos libres (pp. 26–29)

| Campo de `DetallesItems[]` | Cond. | Formato/uso |
|---|---|---|
| `numeroLinea` | O | Ordinal, 4 dígitos. |
| `codigoCIIU`, `codigoPLU` | P | Hasta 6 y 20 caracteres. |
| `indicadorBienoServicio` | O | `1` bien, `2` servicio. |
| `descripcion` | O | Hasta 255 caracteres. |
| `cantidad` | O | 8 enteros y 2 decimales. |
| `unidadMedida` | O | 3 caracteres, catálogo 12. Requiere traducción desde UND/SERV de Pro9. |
| `precioUnitario` | O | 16+2 decimales. |
| `precioUnitarioDescuento`, `montoBonificación`, `descripBonificación`, `descuentoMonto`, `RecargoMonto` | P | Precio neto, bonificación, causa, descuento de línea y recargo; montos 16+2, descripción 255. |
| `precioItem` | O | Precio de línea antes de impuesto, después de descuento; 16+2. |
| `codigoImpuesto` | O | Hasta 4 caracteres, catálogo 10. |
| `tasaIVA` | O | 2 enteros y 2 decimales. |
| `valorIVA` | O | La tabla dice 2 enteros y 2 decimales, pero el ejemplo excede ese límite; confirmar. |
| `valorTotalItem` | O | Precio + IVA + `valorOTI` si aplica; 16+2. |
| `PrecioAntesDescuento` | P | Precio original, 16+2. |

`DetallesItems` **no aplica** a comprobantes de retención. `InfoAdicionalItem[]`: pares `campo`/`valor`, opcionales, hasta 255 cada uno. `ListaItemOTI[]`: `tasaOTI` (2+2), `codigoOTI` (hasta 4, marcado opcional), `valorOTI` (20+2); la tabla omite condición para tasa y valor. `InfoAdicional[]` del documento: `campo` y `valor`, opcionales, sin longitud indicada. [Fuente: pp. 27–29.]

`Viajes` para agencias (pp. 28–29), todo opcional: `nombreApellidoPasajero` (100), `tipoIdentificación` (1), `numeroIdentificación` (12), `domicilioFiscalPasajero` (255), `numeroTelePasajero` (255), `razonSocialServTransporte` (100), `numeroBoleto` (14), `fechaSalida`/`fechaLlegada` (`DD/MM/AAAA`, 10), `horaSalida`/`horaLlegada` (`hh:mm:ss am/pm`), `nombrePuertoEmbarque` (10), `condicionesEntrega` (255), `puntoSalida` (255), `PuntoDestino` (255), `unidadVolumen` (3). El ejemplo de puerto supera el máximo declarado. [Fuente: pp. 28–29.]

### 4.5 Guía, transporte y banderas (pp. 29–35)

`GuiaDespacho` es obligatorio sólo para guía/orden (`04` HKA): `esGuiaDespacho` (`"1"` guía, `"0"` nota de entrega), `motivoTraslado` (catálogo 14, hasta 55), `descripcionServicio` (255, cuando el motivo es reparación/perfeccionamiento), `tipoProducto` (catálogo 15, 55), `origenProducto` (catálogo 16, 55), `PesoOVolumenTotal` (55) y `destinoProducto` (catálogo 17, 55). La tabla no marca obligatoriedad individual de todos ellos. `Conductor`: `NombreCompleto` (100), `tipoIdentificacion` (1), `numeroIdentificacion` (12), `tipoLicencia` (55), `infoContacto` (255), sin condición por campo. `Vehículo`: `TipoVehiculo` (255) y `numeroTransporte` (55); la columna los presenta obligatorios para guía/orden, aunque está visualmente repartida. `Transportista`: `razonSocial` (255), `numeroIdentificacion` (12, opcional), `domicilioFiscal` (255). [Fuente: pp. 29–31.]

`Transporte` (pp. 33–34), todo opcional: `tipo`, `descripcion` (255), `código`, `origen` (255), `destino` (255), `fechaEntrada` y `fechaSalida` (`DD/MM/AAAA`), `lugarEntrega` (255), `lugarRecepcion` (255), `placa` (12). `Banderas adicionales`: `esLote` y `esMinimo`, booleanos opcionales. No activar `esMinimo` para eludir validaciones locales. [Fuente: pp. 33–34.]

### 4.6 Comprobantes de retención IVA/ISLR y ARCV (pp. 17, 22, 31–36)

Los tipos `05` y `06` HKA usan `SujetoRetenido`, `TotalesRetencion` y `DetallesRetencion[]`. Cada detalle contiene `numeroLinea` O (4 dígitos), `fechaDocumento` O (`DD/MM/AAAA`), `tipoDocumento` O (2, catálogo 1 HKA), `serieDocumento` P (20), `numeroDocumento` O (19), `numeroControl` O (10), `tipoTransaccion` P (2, catálogo 3), `montoTotal` P (16+2), `montoExento` P (16+2), `baseImponible` O (16+2), `porcentajeIVA` O (16+2 según tabla, aun cuando es porcentaje), `Sustraendo` P (16+2), `montoIVA` P (16+2), `retenidoIVA` P (monto, no porcentaje), `percibido` P (16+2), `CodigoConcepto` P (4 dígitos, sólo `06`) y `moneda` (3, sin condición indicada). `InfoAdicionalItem[]` de cada detalle permite `campo` y `valor` (255) para datos de la referencia; ejemplos de clave `numero`, `Fafectada`, `control`. El nodo `DetallesRetencion` se declara obligatorio para comprobantes. [Fuente: pp. 31–33.]

Para `07` HKA (ARCV), el manual define además: `Banderas adicionales (ARCV)` con `fechaCierreEjercicio`, `horaCierreEjercicio`, `observaciones`, `periodoRetencion` (opcionales); `Beneficiario (ARCV)` con `razonSocial`, `tipoIdentificacion`, `numeroIdentificacion`, `constituidaPais`, `domiciliadaPais`, `direccion`, `correos[]`, `fechaPago` obligatorios y `telefonos[]` opcional; `Totales retención (ARCV)` con `totalCantidadPagada`, `totalCantidadObjetoRet`, `totalImpuestoRetenido`, `totalObjetoRetAcumulado`, `totalImpuestoRetAcumulado` obligatorios; `Detalles retención (ARCV)` con `mes`, `codigoRetencion`, `MontoPagado`, `MontoObjetoRet`, `porcentajeOTarifa`, `ImpuestoRetenido`, `ObjetoRetAcumulado`, `ImpuestoRetAcumulado` obligatorios. El PDF no muestra un JSON completo; Swagger define el anidamiento ARC en §11. [Fuente: pp. 34–36.]

## 5. Catálogos HKA (sección 6, pp. 37–40)

Estos valores sirven para el **payload HKA**. No sustituir catálogos de tenant ni códigos de documento de Pro9.

| # | Catálogo y valores documentados |
|---:|---|
| 1 | Tipo fiscal: factura `01`, NC `02`, ND `03`, nota de entrega/guía `04`, retención IVA `05`, retención ISLR `06`, ARCV `07`. |
| 2 | Proveedor: normal `null`, sin RIF `SR`, no residenciado `NR`, no domiciliado `ND`. |
| 3 | Transacción: registro `01`, complemento `02`, anulación `03`, ajuste `04`, ND IGTF `98`, factura a terceros `99`. |
| 4 | Régimen especial: zonas económicas especiales, zona franca/libre de Paraguaná, Puerto Libre Santa Elena de Uairén, Zona Libre de Mérida, Puerto Libre Nueva Esparta, duty free. No se publican códigos. |
| 5 | Venta: interna o exportación con INCOTERM `FOB`, `CIF`, `EXW`; sin códigos adicionales. |
| 6 | Concepto ISLR: remite a `MT_Retenciones ISLR3.0_2014`; no incorpora el catálogo de conceptos. |
| 7 | Moneda: remite a ISO 4217; la tabla ejemplifica `VED` y `USD`, mientras otras páginas usan `VEF`, `VES`, `BsD`. Confirmar código VES aceptado. |
| 8 | Identificación: natural `V`, jurídica `J`, extranjero residenciado `E`, pasaporte `P`, ente gubernamental `G`, comunal `C`; no domiciliado: `RUT`, `NIT`, etc. |
| 9 | País: ISO 3166-1; ejemplo `VE`. |
| 10 | IVA/tributo: reducido `R` 8%, general `G` 16%, adicional `A` 31%, exento/exonerado/no gravado `E` 0%, percibido `P` 0%, IGTF 3%. Son ejemplos del manual, no tasas configurables de Pro9. |
| 11 | Pago: depósito `01`, pago móvil `02`, transferencia `03`, orden de pago `04`, débito `05`, crédito `06`, cheque no negociable `07`, efectivo legal `08`, efectivo divisas `09`, medios exterior `10`, transferencia exterior `11`, cheque exterior `12`, orden de pago simple `13`, documentaria `14`, remesa simple `15`, documentaria `16`, carta de crédito simple `17`, documentaria `18`, otros `99`. |
| 12 | Unidad: remite a UNECE Rec. 20; no hay tabla interna de códigos en el PDF. |
| 13 | Otro impuesto global: IGTF, ejemplo 3% aplicado a la porción pagada en divisas. |
| 14 | Motivo traslado: reparación/perfeccionamiento; traslado entre almacenes propios; almacén ajeno; tránsito aduanero; otras causas. Sin códigos publicados. |
| 15 | Producto: alcohol, cigarrillos. Sin códigos publicados. |
| 16 | Origen: nacional, importado, nacional e importado. Sin códigos publicados. |
| 17 | Tributación/destino: tierra firme, régimen especial. Sin códigos publicados. |

## 6. Respuestas, errores y recuperación (sección 7, pp. 40–43)

El ejemplo de respuesta de p. 40–41 contiene `codigo`, `mensaje`, `validaciones[]` y `resultado` con `imprentaDigital`, `autorizado`, `serie`, `tipoDocumento`, `numeroDocumento`, `numeroControl`, `fechaAsignacion`, `horaAsignacion`, `fechaAsignacionNumeroControl`, `horaAsignacionNumeroControl`, `rangoAsignado` y, si aplica, `transaccionId`. Está rotulado como respuesta no exitosa aun cuando contiene datos de asignación; no asumir que esos campos siempre llegan ni que `codigo` equivale al estado HTTP.

| Capa | Códigos publicados |
|---|---|
| HTTP cliente | `400` petición inválida; `401` credencial/token; `403` prohibido; `404` ausente; `405` método no admitido. |
| HTTP servidor | `500`, `502`, `503`, `504`: fallo temporal o interno. |
| Negocio HKA | `100` fallo de procesamiento; `200` procesado; `201` duplicado; `202` revisar punto de facturación para asignación; `203` rechazado por campos/formato; `204` fallo de control; `205` validaciones mínimas; `210` mínimo registrado; `211` mínimo duplicado; `400` estructura; `401` autorización; `500` error interno. |

El PDF recomienda esperar al menos **30 segundos** antes de reenviar documentos ya enviados con error y dice que el JWT dura **12 horas**. Para Pro9, un timeout o respuesta incierta requiere consultar/conciliar la misma operación antes de reenviar; nunca crear otra venta, numeración local, pago o movimiento de inventario. Registrar códigos y mensajes, omitiendo secretos y datos personales innecesarios. [Fuente: pp. 40–43; skill de numeración fiscal Pro9.]

## 7. Ejemplos del proveedor (sección 8, pp. 43–48)

- **Factura básica, pp. 43–45:** `tipoDocumento: 01`, comprador, un servicio gravado, información adicional. El ejemplo omite `Totales` y `unidadMedida`, que las tablas marcan obligatorios; usa `BsD`, `numeroDocumento` con guion y `valorIVA: 224` para precio 1400 al 16%. Es ilustrativo, no una fixture validada.
- **Nota de débito, pp. 45–48:** `TipoDocumento: 03`, factura afectada, vendedor/comprador, totales, alícuota, forma de pago y una línea. Usa claves con mayúscula inicial, `VES` y `TipoTransaccion: 02`. El epígrafe dice NC/ND, pero sólo hay ejemplo completo de ND.
- **Tipos 04–07:** no tienen payload integral de ejemplo. Sus contratos deben componerse desde las tablas y probarse en DEMO con respuestas reales antes de declarar soporte.

## 8. Red, ambientes y tolerancia (secciones 9–11, pp. 48–50)

DEMO: `https://demoemisionv2.thefactoryhka.com.ve/`; producción: `https://emisionv2.thefactoryhka.com.ve/`. HKA entrega credenciales separadas; las operaciones de producción son reales. La sección 9 menciona SSL/TLS 1.2–1.3 y la 3.6 sólo TLS 1.3; usar HTTPS con validación de certificado y verificar compatibilidad en DEMO. El manual declara tolerancia general de **±10 Bs** para cálculos y **±1 divisa** para suma de formas de pago en otra moneda (validación 1016). Es tolerancia del proveedor, no regla para ajustar saldos o impuestos internos. El contacto de la fuente es `soporte_id_vnzla@thefactoryhka.com.ve`. [Fuente: pp. 7, 48–50.]

## 9. Confirmaciones necesarias antes de programar

1. Aunque Swagger identifica `POST /api/Emision` y `POST /api/EmisionARC`, falta probar con credenciales la respuesta para éxito/rechazo/duplicado, la representación de `serie` vacía y el casing efectivo de casos heredados del PDF. [PDF pp. 8–9, 14–15, 43–48; Swagger §10]
2. Código monetario `VES` aceptado, unidades UNECE aplicables a UND/SERV, formato de importes, IVA/IGTF/OTI y ubicación de campos ND por IGTF. [pp. 18–28, 38–39]
3. Probar payloads DEMO para guía `04` y retenciones `05`–`07`; verificar que el anidamiento ARC del Swagger se acepta y aclarar `Sustraendo`, `Esnegativo`, referencias afectadas y códigos de concepto. [PDF pp. 14–15, 29–36; Swagger §11]
4. Orden real entre asignación de control y emisión, idempotencia y consulta ante timeout, paginación/listado, interoperabilidad de GET con cuerpo y codificación real de `archivo` en `DescargaArchivo`. [PDF pp. 8–13, 40–43; Swagger §10]
5. Versión vigente del documento, TLS admitido y significado de códigos/respuestas. [pp. 1–2, 7, 40–42, 48]

Hasta resolver estas dudas, este informe es una especificación de **lo publicado** y de su relación con Pro9, no una certificación de integración HKA. Ningún contenido del PDF ni del Swagger autoriza a cambiar la numeración propia del sistema.

## 10. Swagger DEMO: operaciones publicadas

Esta sección complementa la sección 3. Todos los esquemas de solicitud usan `application/json` (Swagger también admite `text/json` y `application/*+json`); cada operación publicada define respuesta HTTP `200` JSON, pero eso no garantiza `codigo` de negocio exitoso. El esquema global de seguridad pide `Authorization: Bearer <JWT>` mediante `apiKey` en cabecera. Swagger no publica aquí otros estados HTTP ni la lista de códigos de negocio para cada ruta. `R` indica propiedad requerida en OpenAPI, que puede diferir de una regla de negocio o del PDF. [Fuente: Swagger DEMO v1, definición citada arriba.]

El requisito Bearer global aparece también sobre `Autenticacion` en la definición OpenAPI; el PDF describe esa ruta como la que obtiene el token a partir de usuario y clave. Tratar la exigencia global sobre esa operación como discrepancia documental y probar autenticación sin token previo.

| Método/ruta | Solicitud Swagger | Respuesta 200 / dato útil |
|---|---|---|
| `POST /api/Autenticacion` | `usuario` R, `clave` R | `codigo` numérico, `mensaje`, `token`, `expiracion`. |
| `POST /api/Emision` | `documentoElectronico` R con `encabezado` R; tipos de `identificacionDocumento.tipoDocumento`: `01`–`06` | `codigo`, `mensaje`, `validaciones[]`, `resultado` con identidad y control HKA. |
| `POST /api/EmisionARC` | `documentoElectronico` R de esquema ARC; `tipoDocumento` sólo `07` | Mismo `SendResponseDTO`; no usar el modelo normal `DocumentoElectronico`. |
| `POST /api/EmisionDNF/constanciaRecepcion` | `documentoElectronico` R de esquema DNF; `tipoDocumento` sólo `99` | Mismo `SendResponseDTO`. Es capacidad adicional del Swagger, fuera de los siete tipos del PDF y sin tipo Pro9 asignado. |
| `POST /api/EstadoDocumento` | `tipoDocumento` R (`01`–`07`); `serie`, `numeroDocumento` o `transaccionId` opcionales en esquema | `estado` con estado, tipo, número, control, fecha/hora de asignación, `transaccionId`, `urlConsulta`. Validar identificador suficiente en DEMO. |
| `POST /api/Anular` | `tipoDocumento`, `numeroDocumento`, `motivoAnulacion` R; `serie`, fecha/hora opcionales | `codigo`, `mensaje`, `validaciones[]`. |
| `POST /api/AsignarNumeraciones` | `detalleAsignacion[]` R; cada fila: tipo, número inicial/final R; serie opcional | `fechaAsignacion`, `rangosAsignados[]`, `detallesReserva[]`, códigos/mensajes. |
| `POST /api/ConsultaNumeraciones` | `serie`, `tipoDocumento`, `prefix` opcionales | `numeraciones[]`: título, serie, tipo, prefijo, desde, hasta, correlativo, estado. |
| `POST /api/ConsultaReservaciones` | `tipoDocumento` R, `serie`, `pagina`, `cantidad` opcionales | Reservas paginadas y sus controles/rangos; `cantidadReservas`, `totalPaginas`, `numeroPagina`. |
| `POST /api/ListadoAsignaciones` | Serie, tipo, números inicio/fin, fechas inicio/fin y `numPagina`, todos opcionales | `asignaciones[]` con número/control y fecha/hora; totales y paginación. |
| `POST /api/UltimoDocumento` | `tipoDocumento` R, `serie` opcional | `numeroDocumento` **entero** y códigos/mensajes. Es dato HKA, no origen de la secuencia Pro9. |
| `POST /api/ListadoDocumentos` | Tipo y fechas inicio/fin R; serie y `numPagina` opcionales | `documentos[]` anulados con estado, RIF, identidad, fecha de anulación y motivo; paginación. |
| `POST /api/Correo/Enviar` | Tipo, número y `correos[]` R; serie opcional | `codigo`, `mensaje`, `validaciones[]`. |
| `POST /api/Correo/Rastreo` | Tipo y número R; serie opcional | `rastreos[]`: `messageId`, `correo`, `status`, `fecha`. |
| `POST /api/Correo/EnviaOrden` | `orden` R, `correos[]` R | Resultado de envío; `orden` acepta letras, dígitos, punto, guion y guion bajo, hasta 20. |
| `POST /api/Correo/RastreoOrden` | `orden` R | `rastreos[]` con los mismos campos de rastreo. |
| `POST /api/DescargaArchivo` | Tipo y número R; serie y `tipoArchivo` opcionales (`pdf`, `xml`, `json`, en ambas capitalizaciones) | `archivo` como string; Swagger no declara si es Base64, URL o contenido literal. |
| `POST /api/AplicarRetencion` | Tipo (`01`–`03`), número, control y `totalRetencion` R; serie y fecha/hora de aplicación opcionales | Código, mensaje y validaciones. El tipo **sí es obligatorio** en Swagger aunque falta en ejemplo PDF. |
| `GET /api/AplicarRetencion` | Cuerpo JSON `RetentionRequestDTO`: tipo (`01`–`03`), número y control R; serie opcional | `infoRetencion` con identidad y `totalesRetencion`. GET con cuerpo requiere prueba de interoperabilidad. |
| `DELETE /api/AplicarRetencion` | Mismo cuerpo JSON de consulta | Código, mensaje y validaciones. |
| `POST /api/AplicarRetencionISLR` | Tipo (`01`–`03`), número, control y `detallesRetencionISLR` R; serie y fecha/hora opcionales | Código, mensaje y validaciones. Operación ausente del PDF. |
| `GET /api/AplicarRetencionISLR` | Cuerpo JSON con tipo (`01`–`03`), número y control R | `infoRetencionISLR` con comprobante, fecha, impuesto, fecha/hora de aplicación. |
| `DELETE /api/AplicarRetencionISLR` | Mismo cuerpo JSON | Código, mensaje y validaciones. |
| `GET /api/Documentos/Relacionar` | **Query**: `TipoDocumento` (`01` o `04`) y `NumeroDocumento` R; `Serie` opcional | `relacionados[]` con serie, tipo y número. |
| `POST /api/Documentos/Relacionar` | Tipo (`01` o `04`), número y `relacionados[]` R; serie/comentario opcionales | Código, mensaje y validaciones. El resumen Swagger dice erróneamente «retención»; el DTO es relación factura–guía. |
| `DELETE /api/Documentos/Relacionar` | Cuerpo JSON: tipo (`01` o `04`) y número R; serie opcional | Código, mensaje y validaciones. |
| `POST /api/Contingencia/Registrar` | `documentoContingencia` R con encabezado, totales y archivo R | `resultado` con ID, identidad, archivo y URL. Distinguir del control de contingencia **interno** Pro9. |
| `POST /api/Contingencia/Listado` | `fechaInicio`, `fechaFin` R | `documentos[]`, total y validaciones. |
| `POST /api/Contingencia/Descargar` | `id` R | `nombreArchivo`, `tipoContenido`, `contenidoBase64`. |
| `POST /api/Contingencia/ExportarCSV` | `fechaInicio`, `fechaFin` R | Los mismos campos de archivo Base64. |
| `POST /api/SubirLote` | `nombreArchivo` R con extensión ZIP y `archivoBase64` R | Nombre de archivo, código, mensaje y validaciones. Carga al SFTP según resumen Swagger. |
| `POST /api/EstadoLote` | `nombreArchivo` opcional en el esquema (regex para ZIP/RAR/CSV/JSON) | `estado[]` con estado y fecha de procesamiento. La utilidad real sin nombre debe verificarse. |
| `POST /api/ResultadoLote` | `nombreArchivo` R, ZIP | `estadoLote`, `nombreArchivoResultado`, `archivoBase64` (CSV codificado según resumen). |
| `POST /api/FacturacionLote/Enviar` | `encabezado` R y `compradores[]` R; comparte líneas, retenciones y datos opcionales | `nombreLote`, `loteId`, `estado`. Es lote JSON distinto de `SubirLote`. |
| `GET /api/FacturacionLote/Estado` | **Query** `NombreLote` R (patrón de nombre JSON) | `nombreLote`, `estado`, `totalDocumentos`, `aceptados`, `rechazados`. |

### 10.1 Datos de respuesta que conviene persistir y conciliar

`SendResponseDTO.resultado` publica `imprentaDigital`, `autorizado`, `serie`, `tipoDocumento`, `numeroDocumento`, `numeroControl`, fechas/horas de asignación y del control, `rangoAsignado`, `transaccionId`, `urlConsulta`. `AsignacionResponseDTO` publica además `rangosAsignados[]` y `detallesReserva[]` con números/control inicial y final, serie, fecha de reservación, `counterfoilId` y `rangoMaestro`. Los DTO de respuesta no marcan estas propiedades como `required`: aceptar ausencias y validar los datos indispensables antes de pasar una reserva Pro9 a estado confirmado. [Fuente: esquemas `SendResponseDTO`, `Resultado`, `AsignacionResponseDTO`, `DetalleReserva` del Swagger.]

## 11. Swagger DEMO: estructura JSON y diferencias frente al PDF

### 11.1 Emisión normal, ARC y DNF

- **Normal (`01`–`06`)**: `documentoElectronico.encabezado.identificacionDocumento` es estructuralmente requerido; `encabezado` permite `facturaGuia[]`, `vendedor`, `comprador`, `sujetoRetenido`, `tercero`, `otrosTercero[]`, `totales`, `totalesRetencion`, `totalesOtraMoneda`, `orden`. Fuera de `encabezado`: `detallesItems[]`, `detallesRetencion[]`, `viajes`, `infoAdicional[]`, `guiaDespacho`, `transporte`, `esLote`, `esMinimo`. Sólo algunas propiedades están marcadas `required` en OpenAPI; eso **no** elimina la obligatoriedad fiscal o condicional descrita en el PDF. `facturaGuia` es **lista**, no objeto único. [Esquemas `SendRequestDTO`, `DocumentoElectronico`, `Encabezado`.]
- **ARC (`07`)**: usar `POST /api/EmisionARC` y `documentoElectronico.encabezado` con `identificacionDocumento`, `beneficiario`, `totalesRetencion`; `detallesRetencion[]`, `infoAdicional[]`, `esLote`, `esMinimo` están fuera de encabezado. La identificación admite sólo `07` y contiene `tipoAgente`, `periodoRetencion` como `{desde,hasta}`, `fechaCierreEjercicio`, `horaCierreEjercicio`, `observaciones`, `sucursal`, `moneda`, `transaccionId`. `beneficiario` agrega `nacionalidad`; cada detalle puede llevar `impuestoEnterado` (`fecha`, `banco`). Las tablas ARCV del PDF no muestran estos tres elementos ni ese anidamiento. [Esquemas `SendARCRequest`, `DocumentoARC` y relacionados.]
- **Constancia DNF (`99`)**: `POST /api/EmisionDNF/constanciaRecepcion` usa modelo `DocumentoNF` separado: identificación con `documentoRelacionado[]`, comprador, totales y `detallesItems[]` obligatorios; puede incluir `mermas[]`, `listaMermas` y guía. `99` no figura entre los siete códigos fiscales del PDF ni tiene correspondencia interna Pro9 aprobada. Documentar la interfaz sin incorporarla al flujo de facturas/órdenes por inferencia. [Esquemas `SendDNFRequestDTO`, `DocumentoNF`.]

**Detalle ARC de Swagger.** `encabezado.identificacionDocumento` requiere `tipoDocumento: "07"` y `serie`; admite número, tipo de agente, cierre de ejercicio, `periodoRetencion: {desde,hasta}`, observaciones, sucursal, moneda y `transaccionId`. `encabezado.beneficiario` exige tipo/número de identificación, razón social, `nacionalidad`, constituida/domiciliada en el país (booleanos), dirección y fecha de pago; notificación, teléfonos y correos son opcionales en el esquema, frente al correo obligatorio del PDF. `encabezado.totalesRetencion` exige los cinco importes de total y acumulados. Cada `detallesRetencion[]` exige `mes` (`01`–`12`), `montoPagado`, `montoObjetoRet` e `impuestoRetenido`; admite `codigoRetencion` (máximo 3), porcentaje/tarifa, acumulados, información adicional e `impuestoEnterado` con `fecha` y `banco` requeridos cuando el nodo se usa. Confirmar en DEMO la obligatoriedad de la lista y sus reglas fiscales, pues el esquema no la marca requerida. [Esquemas `Common.Models.DocumentoARC.*`.]

**Detalle DNF de Swagger.** La identificación exige `tipoDocumento: "99"`, `numeroDocumento`, `documentoRelacionado[]` y `fechaEmision`; admite hora, serie, sucursal y moneda. Cada referencia relacionada exige `tipo` y `numero`, con serie, fecha, monto y comentario opcionales. El comprador exige tipo/número de identificación, razón social, dirección y país. Cada ítem exige línea, descripción, cantidad, precio unitario, código de impuesto y total de ítem; puede incluir PLU, unidad, precio de línea, IVA, información adicional y `mermas[]`. `totales` admite cantidad, IVA, total, monto a pagar, mensaje y `listaMermas[]`. Es contrato HKA adicional, sin decisión de producto Pro9 sobre emisión DNF. [Esquemas `Common.Models.DocumentoDNF.*`.]

**Retenciones ISLR recibidas.** `detallesRetencionISLR` del `POST /api/AplicarRetencionISLR` exige `numeroCompRetencion`, `fechaEmisionCR` e `impuestoRetenido`; admite `rifAgenteRetencion`, `porcentaje` y `codigoOperacion`. La respuesta de consulta añade identidad del documento y fecha/hora de aplicación. Esto no reemplaza `TotalesRetencion` usado al **emitir** un comprobante fiscal. [Esquemas `ApplyRetentionISLRRequestDTO`, `DetallesRetencionISLR`, `RetentionISLRResponseDTO`.]

### 11.2 Campos que el Swagger precisa o contradice

| Área | Contrato Swagger DEMO | Comparación con PDF / decisión documental |
|---|---|---|
| Identidad | En emisión normal, `tipoDocumento` admite `01`–`06`; `serie` es string **requerido** con longitud 0–20; `numeroDocumento` es opcional y, si se incluye, 1–19 dígitos. `urlPdf` opcional (250). | El PDF trata número, fecha, hora, tipo de venta y moneda como obligatorios y contempla `07` en catálogo. Distinguir validación estructural de validación de negocio; ARC `07` tiene endpoint propio. No enviar cadena `"nulo"` sin prueba. |
| Cliente/tercero | Comprador y sujeto retenido requieren tipo, identificación, razón social, dirección y país; ID hasta 45 y razón social hasta 255. `notificar`: `Si`/`No`. Hay `otrosTercero[]` y `tipoPerceptor` en retenido. | PDF limita comprador a 19/100 y no detalla esos campos. Confirmar si restricciones ampliadas se aplican a todos los tipos. |
| Ítems | `cantidad` y `precioUnitario` permiten hasta 4 decimales; `descripcion` hasta 1000; `montoBonificacion`/`descripcionBonificacion` sin acento; `codigoImpuesto` admite `R,G,A,E,P,X,F,IGTF`; `valorIVA` usa hasta 16+2. | PDF usa 2 decimales para cantidad y precio, 255 para descripción y nombres con acento. Serializar nombres Swagger y validar casos DEMO. `X/F` no se explican en catálogo PDF. |
| Totales | `totales` añade `listaRecargo[]`, `otrosImpuestosSubtotal[]`, `totalIGTF`, `totalIGTF_VES`; `totalesOtraMoneda.tipoCambio` acepta hasta 8 decimales. | Resuelve la ubicación de IGTF/OTI que el PDF deja ambigua; PDF da 4 decimales para cambio. No modificar la aritmética decimal local. |
| Guía | `guiaDespacho.esGuiaDespacho` es `"0"` o `"1"`; `vehiculo` permite `numeroPlaca`; `facturaGuia[]` vincula sólo tipos `01`/`04`. | `numeroPlaca` no está en la tabla PDF; `facturaGuia` aparece singular. |
| Retención emitida | `DetallesRetencion` usa `porcentaje`, `porcentajeRetencion`, `sustraendo`, `retenido`, `codigoConcepto`; número de referencia acepta hasta 30 caracteres alfanuméricos/guion, control hasta 15; `retenido` y `moneda` son requeridos. | PDF usa `porcentajeIVA`, `Sustraendo`, `retenidoIVA` y límites menores. Comprobar semántica, no renombrar ciegamente los datos persistidos. |
| Archivo | `DescargaArchivo.tipoArchivo` acepta PDF/XML/JSON y `archivo` string; contingencia y resultado de lote sí declaran `contenidoBase64`/`archivoBase64`. | No inferir que `archivo` de descarga sea Base64 ni reintroducir XML fiscal peruano en Pro9. |
| Relación | `/api/Documentos/Relacionar` limita los tipos externos a `01`/`04`. | La relación HKA entre factura y guía no modifica el tipo interno `09` de orden de entrega. |

En los DTO ordinarios, `numeroDocumento` suele restringirse a **1–19 dígitos** y `serie` a **0–20 caracteres**; fechas usan `DD/MM/AAAA` y horas `hh:mm:ss am/pm`. Muchos importes se expresan como string de hasta **16 enteros y 2 decimales**; cantidad/precio unitario de ítem admiten hasta **4** y tipo de cambio hasta **8** decimales en ciertos esquemas. Son patrones de estructura, no validación de fecha real ni licencia para redondear la contabilidad Pro9. Las regex exactas y excepciones por DTO se consultan en la definición Swagger enlazada al inicio.

### 11.3 Lotes y contingencia: estructura publicada

`FacturacionLote/Enviar` recibe `encabezado` (como el de emisión, sin comprador individual) y `compradores[]`; cada entrada tiene `comprador` requerido y puede indicar número, serie y `transaccionId`. También admite líneas, retenciones, viajes, guía, transporte y banderas. `SubirLote` recibe un ZIP Base64 por nombre con patrón que incorpora identificador, fecha `AAAAMMDD`, secuencia y extensión; `EstadoLote` y `ResultadoLote` consultan el mismo nombre. Son **dos mecanismos de lote diferentes**, con respuestas y estados propios. [Esquemas `UploadInvoiceBatchRequest`, `DocumentoComprador`, `UploadBatchRequest`, `StatusBatchRequest`, `ResultBatchRequest`.]

`Contingencia/Registrar` exige `documentoContingencia.encabezado`, `.totales` y `.archivo`. El encabezado admite sólo tipos HKA `01`–`03` y exige número, control, fecha, RIF y razón social del adquirente; permite tercero. El archivo admite `pdf/png/jpg/jpeg` y `contenidoBase64`. Los totales opcionales distinguen ventas gravadas/exentas, bases e IVA G/R/A, exportación, IVA retenido, IGTF y `tasaBCV` (hasta 8 decimales). Se consulta por fechas, se descarga por `id` y se exporta CSV; `Descargar`/`ExportarCSV` sí devuelven Base64. Nada de ello sustituye la cadena de contingencia y reservas de Pro9. [Esquemas `ContingenciaRequestDTO`, `DocumentoContingencia` y DTO relacionados.]

## 12. Decisiones y verificaciones pendientes tras contrastar Swagger

1. Usar Swagger DEMO como contrato **publicado** para rutas, casing y estructuras; conservar el PDF como contexto de reglas y límites. Si divergen, registrar ambos y verificar con credenciales DEMO antes de activar una ruta. El `required` de OpenAPI sólo valida forma, no completa condiciones fiscales.
2. En `GET /api/AplicarRetencion` e ISLR, Swagger publica un cuerpo JSON; probar que cliente, proxy y servidor lo acepten. `GET /api/Documentos/Relacionar` y `GET /api/FacturacionLote/Estado` sí usan query parameters.
3. Confirmar formatos de `serie` vacía, `archivo` de descarga, aceptación de moneda `VES`, códigos IVA `X/F`, estado de duplicado, idempotencia por `transaccionId`, límites de lotes y correspondencia entre asignación y emisión.
4. Los nuevos endpoints ARC, DNF, ISLR, relaciones, lotes y contingencia son capacidad **documentada del proveedor**, no habilitación funcional de Pro9. La futura integración necesita sus propias decisiones de producto, permisos y pruebas; ningún código HKA pasa a `FiscalProfileService::TYPES` ni a secuencias internas.
