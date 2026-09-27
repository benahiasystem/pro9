# XML de retenciones de ISLR para declaración mensual SENIAT

## Fuente, alcance y vigencia

Fuente primaria de este informe: **Manual Técnico, Sistema Impuesto Sobre la Renta, Retenciones de ISLR, Declaración Mensual**, N.º 60.40.40.039, **versión 3.1, junio de 2014**, 19 páginas, proporcionado en `/home/benahia/benahia/pro/implementaciones con HKA/manuales/Seniat/seniat-manual-tc3a9cnico-sistema-islr-retenciones-islrv3-0_2014.pdf`. Aunque el nombre del archivo dice `v3-0`, la portada y los encabezados dicen **3.1**. Las páginas citadas son las impresas en el PDF. Este documento describe esa edición histórica; no confirma que sea la especificación ni la tabla de alícuotas vigente. Antes de usarlo en producción, verificar formato y reglas fiscales con el portal SENIAT y la normativa aplicable. [Fuente: portada y pp. 3–4.]

El manual define **archivos XML para informar y enterar retenciones mensuales de ISLR** mediante el Portal Fiscal. Contiene dos declaraciones diferentes: (1) salarios y otras retenciones y (2) dividendos y acciones. No define el comprobante de retención IVA/ISLR emitido por la API HKA ni un transporte automático de Pro9 hacia SENIAT. [Fuente: pp. 3–4, 9–14.]

### Mapa del manual

| Páginas | Contenido |
| --- | --- |
| 3–4 | Introducción, objetivo, alcance y requisitos del portal. |
| 5–8 | Campos y notas de salarios y otras retenciones. |
| 9 | Campos de dividendos y acciones. |
| 10–11 | Diagramas de ambos XML. |
| 11–13 | Esquemas XSD: el primero está impreso como imagen y el segundo como texto. |
| 13–14 | Ejemplos XML de ambos tipos. |
| 14–19 | Catálogos de concepto y tipo de operación. |

Los requisitos de uso indicados en 2014 eran tener credenciales del Portal Fiscal, Firefox o Internet Explorer 6.0 como mínimo y Acrobat Reader. Son requisitos descritos por el manual, **no** recomendaciones de infraestructura actual. [Fuente: p. 4.]

## 1. Declaración de salarios y otras retenciones

La raíz es `RelacionRetencionesISLR`; lleva los atributos obligatorios `RifAgente` y `Periodo`. Contiene uno o más `DetalleRetencion`. Cada detalle tiene **siete elementos obligatorios y ordenados** como sigue. El diagrama de la p. 10 muestra `RifAgente`; el XSD de las pp. 11–12 añade también `Periodo`. [Fuente: pp. 5–6, 10–12.]

| Ubicación / orden | Campo | Tipo indicado / regla de la edición 3.1 |
| --- | --- | --- |
| Raíz, atributo | `RifAgente` | `String(10)`: V, E, J, P o G más nueve dígitos; el XSD admite mayúsculas o minúsculas. |
| Raíz, atributo | `Periodo` | La tabla dice `String(7)` pero describe `AAAAMM`; el ejemplo usa seis caracteres (`200902`). El XSD dibujado restringe año desde 2000 y mes `01`–`12`. |
| 1 | `RifRetenido` | RIF del sujeto retenido, mismo formato que `RifAgente`. |
| 2 | `NumeroFactura` | Hasta diez caracteres en tabla/XSD. Sin factura: `0`. Si supera diez dígitos, el manual indica usar los últimos diez. |
| 3 | `NumeroControl` | Hasta diez caracteres; usar sólo el secuencial numérico. Sin control: `NA`. El patrón del XSD permite más caracteres que la regla narrativa. |
| 4 | `FechaOperacion` | `DD/MM/AAAA`: fecha del pago o abono; debe corresponder al `Periodo`. |
| 5 | `CodigoConcepto` | Cadena de tres dígitos; consultar el catálogo 001–086 de § 4. El XSD sólo comprueba tres dígitos, no enumera el catálogo. |
| 6 | `MontoOperacion` | Decimal con punto y hasta dos decimales; monto de la operación sobre la cual se aplica la retención. El XSD de este formato admite cero (`minInclusive`). |
| 7 | `PorcentajeRetencion` | Decimal entre 0 y 100, con hasta dos decimales según XSD; es el porcentaje, no el monto retenido. |

**Esquema estructural:** `RelacionRetencionesISLR[@RifAgente,@Periodo] → DetalleRetencion+ → (RifRetenido, NumeroFactura, NumeroControl, FechaOperacion, CodigoConcepto, MontoOperacion, PorcentajeRetencion)`. No aparecen en este XML campos de impuesto calculado, sustraendo, moneda, totales ni firma. El XSD de salarios está impreso como imagen; las restricciones anteriores se cotejaron con las pp. 11–12 y las descripciones de las pp. 5–6. [Fuente: pp. 5–6, 10–12.]

### Ejemplo publicado

Se conserva el contenido del ejemplo de la p. 13, incluida su codificación declarada y sus valores. **Es XML bien formado, pero `RifRetenido` tiene ocho dígitos después de la letra; contradice el RIF de nueve dígitos exigido por el XSD.** No copiarlo como caso que deba validar sin corregir el dato de prueba. [Fuente: p. 13.]

```xml
<?xml version="1.0" encoding="ISO-8859-1"?>
<RelacionRetencionesISLR RifAgente="j123456789" Periodo="200902">
  <DetalleRetencion>
    <RifRetenido>v12123456</RifRetenido>
    <NumeroFactura>4100</NumeroFactura>
    <NumeroControl>2100</NumeroControl>
    <FechaOperacion>25/02/2009</FechaOperacion>
    <CodigoConcepto>002</CodigoConcepto>
    <MontoOperacion>9000</MontoOperacion>
    <PorcentajeRetencion>3</PorcentajeRetencion>
  </DetalleRetencion>
</RelacionRetencionesISLR>
```

### Reglas y notas del manual

- Todos los datos son obligatorios; cada archivo debe incluir la totalidad de pagos o abonos mensuales en los conceptos del anexo 6.1, **incluso cuando no generen retención**: en ese caso, `PorcentajeRetencion` vale `0`. El manual exceptúa intereses pagados por bancos e instituciones financieras a personas naturales residentes en el país. [Fuente: p. 6.]
- Las tasas del anexo remiten al Decreto 1808 de 23/04/1997, G.O. 36.203 de 12/05/1997; un convenio para evitar doble tributación puede establecer otro porcentaje. El XML informa el porcentaje aplicado, por lo que no debe deducirse automáticamente sólo desde el código. [Fuente: p. 6.]
- El portal aplicaba automáticamente el sustraendo del parágrafo segundo del artículo 9 del Decreto 1808 para los códigos `002, 006, 010, 012, 014, 018, 025, 049, 053, 057, 061, 071, 073, 075, 077, 079, 083`. Para esos códigos el manual permite agrupar el monto mensual por código y usar el número de cualquiera de las facturas pagadas o registradas en el mes. No hay campo de sustraendo en el XML. [Fuente: pp. 6–7.]
- En los supuestos del parágrafo primero del mismo artículo, se informa **un detalle separado por tramo y porcentaje**. El ejemplo de la p. 7 divide un pago de 7.000 UT a una persona jurídica no domiciliada en 2.000 UT al 15 %, 1.000 UT al 22 % y 4.000 UT al 34 %. El manual indica considerar pagos del mismo sujeto dentro del ejercicio anual y aplicar 34 % a los que excedan 3.000 UT. [Fuente: p. 7.]
- Para asalariados sin RIF, las pp. 7–8 describen un trámite histórico de intercambio de Excel 2003 o anterior con SENIAT: primera lista de cédulas sin letras ni puntos; para no registrados, alta en el portal y segunda lista con cédula y número de control de la planilla RIF. Para no residentes sin base fija, piden pasaporte, fecha de nacimiento, apellidos, nombres, nacionalidad y correo; para jurídicas no domiciliadas sin establecimiento permanente, razón social, correo y nacionalidad. El manual da `intriint@seniat.gob.ve` como destino. **No activar ese trámite ni remitir datos personales sin confirmar que el canal continúa vigente.** [Fuente: pp. 7–8.]

## 2. Declaración de dividendos y acciones

La misma raíz `RelacionRetencionesISLR` lleva ahora `RifAgente` y **`Fecha`** como atributos obligatorios; **no** lleva `Periodo`. Tiene uno o más `DetalleRetencion`, con cuatro elementos obligatorios y en el orden mostrado. El XSD textual define los tipos `t_Rif`, `t_Tipo`, `t_Porcentaje`, `t_Monto` y `t_Fecha`. [Fuente: pp. 9, 11–13.]

| Ubicación / orden | Campo | Regla de la edición 3.1 |
| --- | --- | --- |
| Raíz, atributo | `RifAgente` | Letra V/E/J/P/G, sin distinción de mayúsculas, y nueve dígitos. |
| Raíz, atributo | `Fecha` | Fecha de la retención `DD/MM/AAAA`. El patrón XSD comprueba la forma y rangos básicos, no la existencia real de la fecha. |
| 1 | `RifRetenido` | Mismo formato RIF. |
| 2 | `Tipo` | Exactamente `01`, `02` o `03`; ver § 5. |
| 3 | `MontoOperacion` | Decimal con punto y hasta dos decimales, **mayor que cero** (`minExclusive` en este XSD). |
| 4 | `PorcentajeRetencion` | Decimal de 0 a 100 con hasta dos decimales. |

**Esquema estructural:** `RelacionRetencionesISLR[@RifAgente,@Fecha] → DetalleRetencion+ → (RifRetenido, Tipo, MontoOperacion, PorcentajeRetencion)`. El XSD no enumera porcentajes distintos por `Tipo`; los valores del ejemplo no son una tabla de tasas. [Fuente: pp. 9, 11–13.]

### Ejemplo publicado

Este ejemplo de la p. 14 incluye las tres operaciones. El `05` de porcentaje es un valor decimal válido, no un código de tipo adicional. [Fuente: p. 14.]

```xml
<?xml version="1.0" encoding="utf-8"?>
<RelacionRetencionesISLR RifAgente="J308554839" Fecha="05/10/2008">
  <DetalleRetencion>
    <RifRetenido>v050341123</RifRetenido>
    <Tipo>01</Tipo>
    <MontoOperacion>1773.69</MontoOperacion>
    <PorcentajeRetencion>14</PorcentajeRetencion>
  </DetalleRetencion>
  <DetalleRetencion>
    <RifRetenido>V050341123</RifRetenido>
    <Tipo>02</Tipo>
    <MontoOperacion>177.25</MontoOperacion>
    <PorcentajeRetencion>05</PorcentajeRetencion>
  </DetalleRetencion>
  <DetalleRetencion>
    <RifRetenido>j000028001</RifRetenido>
    <Tipo>03</Tipo>
    <MontoOperacion>1</MontoOperacion>
    <PorcentajeRetencion>36</PorcentajeRetencion>
  </DetalleRetencion>
</RelacionRetencionesISLR>
```

## 3. Lectura de los esquemas y discrepancias de la fuente

Las pp. 10–11 presentan diagramas con cardinalidad `1..*` para `DetalleRetencion`; las pp. 11–13 muestran XSD. El XSD de salarios está rasterizado y comienza visualmente a mitad de un bloque, por lo que esta documentación **no** lo presenta como archivo XSD directamente importable. Para implementar un validador debe obtenerse un XSD íntegro del emisor o reconstruirlo, identificarlo como derivado y probarlo contra el portal antes de considerar aceptación fiscal. [Fuente: pp. 10–13.]

| Tipo/faceta XSD publicada | Salarios y otras | Dividendos y acciones |
| --- | --- | --- |
| `t_Rif` | Patrón `[VvEeJjPpGg][0-9]{9}` | Igual. |
| `t_Porcentaje` | `xs:decimal`, `minInclusive=0`, `maxInclusive=100`, `fractionDigits=2` | Igual. |
| `t_Monto` | `xs:decimal`, `minInclusive=0`, `fractionDigits=2` | `xs:decimal`, `minExclusive=0`, `fractionDigits=2`. |
| `t_Numero_Factura` | Cadena alfanumérica, `minLength=1`, `maxLength=10` | No existe. |
| `t_Numero_Control` | Patrón que admite `na`, `NA` o caracteres alfanuméricos, `minLength=1`, `maxLength=10` | No existe. |
| `t_Periodo` | Año que comienza con `2` y mes `01`–`12` (`AAAAMM`). | No existe. |
| `t_Fecha` | Patrón de fecha impreso en la imagen; verificar su transcripción antes de usarlo como regex ejecutable. | Patrón de `DD/MM/AAAA`: día `01`–`31`, mes `01`–`12` y año `2xxx`. |
| `t_Codigo` / `t_Tipo` | `t_Codigo`: tres dígitos, sin enumeración de conceptos. | `t_Tipo`: patrón `0[1-3]`, longitud fija `2`. |

Los dos esquemas usan `xs:sequence` para los elementos de cada detalle; cada elemento aparece una vez, y `DetalleRetencion` tiene `maxOccurs="unbounded"` con mínimo implícito de uno. En salarios el XSD exige los atributos `RifAgente` y `Periodo`; en dividendos exige `RifAgente` y `Fecha`. [Fuente: pp. 11–13.]

| Hallazgo | Consecuencia de implementación |
| --- | --- |
| `Periodo` figura `String(7)` en p. 5, mientras `AAAAMM`, XSD y ejemplo indican seis dígitos. | No aceptar siete caracteres por la tabla aislada; registrar la contradicción y verificar con el portal. |
| El diagrama de salarios de p. 10 no dibuja `Periodo`, pero el XSD y ejemplo sí. | Mantener `Periodo` como atributo del formato de salarios. |
| El ejemplo de salarios tiene RIF retenido de longitud distinta a la regla de pp. 5 y 11. | Marcarlo como muestra literal, no como vector positivo de validación XSD. |
| La narrativa exige secuencial numérico o `NA` para `NumeroControl`; el patrón del XSD dibujado admite caracteres alfanuméricos. | Conservar la regla textual y no ampliar datos aceptados por inferencia. |
| El XSD de salarios permite `MontoOperacion = 0`; el de dividendos exige `> 0`. | Aplicar la restricción del formato seleccionado. |
| Los ejemplos declaran `ISO-8859-1` y `utf-8`, respectivamente. | Serializar los bytes con la codificación declarada; verificar la exigida por el portal antes de fijar una política única. |
| Patrones XSD de fecha validan forma/rangos parciales, pero no fechas de calendario ni coincidencia de `FechaOperacion` con el período. | Añadir esas comprobaciones de negocio antes de generar el archivo. |

## 4. Catálogo completo: Código del Concepto de Retención

Transcripción normalizada de la **actividad y el porcentaje publicados** en el anexo 6.1 (pp. 14–18). Se eliminan únicamente saltos de línea tipográficos; se conservan códigos, categorías y tasas. Las tasas son las del manual de **2014**, no un catálogo vigente verificado. `PNR`: persona natural residente; `PNNR`: persona natural no residente; `PJD`: persona jurídica domiciliada; `PJND`: persona jurídica no domiciliada; `PJNCD`: persona jurídica no constituida domiciliada (definida en el anexo, sin código asociado en esta tabla). `Escalonado` significa 15 % hasta 2.000 UT, 22 % entre 2.000 y 3.000 UT y 34 % sobre el excedente de 3.000 UT. [Fuente: pp. 14–18.]

<!-- INICIO CATALOGO CONCEPTOS -->
| Código | Actividad | Porcentaje del manual |
| --- | --- | --- |
| `001` | Sueldos y salarios | Variable |
| `002` | Honorarios profesionales no mercantiles (PNR) | 3 % |
| `003` | Honorarios profesionales no mercantiles (PNNR) | 34 % |
| `004` | Honorarios profesionales no mercantiles (PJD) | 5 % |
| `005` | Honorarios profesionales no mercantiles (PJND) | Escalonado |
| `006` | Honorarios profesionales mancomunados no mercantiles (PNR) | 3 % |
| `007` | Honorarios profesionales mancomunados no mercantiles (PNNR) | 34 % |
| `008` | Honorarios profesionales mancomunados no mercantiles (PJD) | 5 % |
| `009` | Honorarios profesionales mancomunados no mercantiles (PJND) | Escalonado |
| `010` | Honorarios profesionales pagados a jinetes, veterinarios, preparadores o entrenadores (PNR) | 3 % |
| `011` | Honorarios profesionales pagados a jinetes, veterinarios, preparadores o entrenadores (PNNR) | 34 % |
| `012` | Honorarios profesionales pagados por clínicas, hospitales, centros de salud, bufetes, escritorios, oficinas, colegios profesionales u otra institución a profesionales no mercantiles sin relación de dependencia (PNR) | 3 % |
| `013` | Honorarios profesionales pagados por clínicas, hospitales, centros de salud, bufetes, escritorios, oficinas, colegios profesionales u otra institución a profesionales no mercantiles sin relación de dependencia (PNNR) | 34 % |
| `014` | Comisiones por venta de inmuebles (PNR) | 3 % |
| `015` | Comisiones por venta de inmuebles (PNNR) | 34 % |
| `016` | Comisiones por venta de inmuebles (PJD) | 5 % |
| `017` | Comisiones por venta de inmuebles (PJND) | 5 % |
| `018` | Otras comisiones distintas de remuneraciones accesorias de sueldos y similares (PNR) | 3 % |
| `019` | Otras comisiones distintas de remuneraciones accesorias de sueldos y similares (PNNR) | 34 % |
| `020` | Otras comisiones distintas de remuneraciones accesorias de sueldos y similares (PJD) | 5 % |
| `021` | Otras comisiones distintas de remuneraciones accesorias de sueldos y similares (PJND) | 5 % |
| `022` | Intereses de capitales tomados en préstamo e invertidos en producción de renta (PNNR) | 34 % |
| `023` | Intereses de capitales tomados en préstamo e invertidos en producción de renta (PJND) | Escalonado |
| `024` | Intereses de préstamos y otros créditos pagaderos a instituciones financieras constituidas en el exterior y no domiciliadas (PJND) | 4,95 % |
| `025` | Intereses pagados por personas jurídicas o comunidades a otras personas o comunidades (PNR) | 3 % |
| `026` | Intereses pagados por personas jurídicas o comunidades a otras personas o comunidades (PNNR) | 34 % |
| `027` | Intereses pagados por personas jurídicas o comunidades a otras personas o comunidades (PJD) | 5 % |
| `028` | Intereses pagados por personas jurídicas o comunidades a otras personas o comunidades (PJND) | Escalonado |
| `029` | Enriquecimientos netos de agencias internacionales cuando paga una persona jurídica o comunidad domiciliada (PJND) | Escalonado |
| `030` | Enriquecimientos netos de fletes pagados a agencias o empresas de transporte internacional constituidas y domiciliadas en el exterior (PNNR) | Escalonado |
| `031` | Enriquecimientos netos de fletes pagados a agencias o empresas de transporte internacional constituidas y domiciliadas en el exterior (PJND) | Escalonado |
| `032` | Enriquecimientos netos por exhibición de películas, cine o televisión (PNNR) | 34 % |
| `033` | Enriquecimientos netos por exhibición de películas, cine o televisión (PJND) | Escalonado |
| `034` | Enriquecimientos por regalías y participaciones análogas (PNNR) | 34 % |
| `035` | Enriquecimientos por regalías y participaciones análogas (PJND) | Escalonado |
| `036` | Enriquecimientos por remuneraciones, honorarios y pagos análogos de asistencia técnica (PNNR) | 34 % |
| `037` | Enriquecimientos por remuneraciones, honorarios y pagos análogos de asistencia técnica (PJND) | Escalonado |
| `038` | Enriquecimientos por servicios tecnológicos utilizados en el país o cedidos a terceros (PNNR) | 34 % |
| `039` | Enriquecimientos por servicios tecnológicos utilizados en el país o cedidos a terceros (PJND) | Escalonado |
| `040` | Enriquecimientos netos por primas de seguros y reaseguros (PJND) | 10 % |
| `041` | Ganancias por juegos y apuestas (PNR) | 34 % |
| `042` | Ganancias por juegos y apuestas (PNNR) | 34 % |
| `043` | Ganancias por juegos y apuestas (PJD) | 34 % |
| `044` | Ganancias por juegos y apuestas (PJND) | 34 % |
| `045` | Ganancias por premios de loterías e hipódromos (PNR) | 16 % |
| `046` | Ganancias por premios de loterías e hipódromos (PNNR) | 16 % |
| `047` | Ganancias por premios de loterías e hipódromos (PJD) | 16 % |
| `048` | Ganancias por premios de loterías e hipódromos (PJND) | 16 % |
| `049` | Premios pagados a propietarios de animales de carrera (PNR) | 3 % |
| `050` | Premios pagados a propietarios de animales de carrera (PNNR) | 34 % |
| `051` | Premios pagados a propietarios de animales de carrera (PJD) | 5 % |
| `052` | Premios pagados a propietarios de animales de carrera (PJND) | 5 % |
| `053` | Pagos a contratistas o subcontratistas por ejecución de obras o servicios mediante valuaciones y órdenes de pago (PNR) | 1 % |
| `054` | Pagos a contratistas o subcontratistas por ejecución de obras o servicios mediante valuaciones y órdenes de pago (PNNR) | 34 % |
| `055` | Pagos a contratistas o subcontratistas por ejecución de obras o servicios mediante valuaciones y órdenes de pago (PJD) | 2 % |
| `056` | Pagos a contratistas o subcontratistas por ejecución de obras o servicios mediante valuaciones y órdenes de pago (PJND) | Escalonado |
| `057` | Pagos a arrendadores de inmuebles situados en el país (PNR) | 3 % |
| `058` | Pagos a arrendadores de inmuebles situados en el país (PNNR) | 34 % |
| `059` | Pagos a arrendadores de inmuebles situados en el país (PJD) | 5 % |
| `060` | Pagos a arrendadores de inmuebles situados en el país (PJND) | Escalonado |
| `061` | Cánones de arrendamiento de muebles situados en el país (PNR) | 3 % |
| `062` | Cánones de arrendamiento de muebles situados en el país (PNNR) | 34 % |
| `063` | Cánones de arrendamiento de muebles situados en el país (PJD) | 5 % |
| `064` | Cánones de arrendamiento de muebles situados en el país (PJND) | 5 % |
| `065` | Pagos de emisoras de tarjetas de crédito o consumo por venta de bienes y servicios u otro concepto (PNR) | 3 % |
| `066` | Pagos de emisoras de tarjetas de crédito o consumo por venta de bienes y servicios u otro concepto (PNNR) | 34 % |
| `067` | Pagos de emisoras de tarjetas de crédito o consumo por venta de bienes y servicios u otro concepto (PJD) | 5 % |
| `068` | Pagos de emisoras de tarjetas de crédito o consumo por venta de bienes y servicios u otro concepto (PJND) | 5 % |
| `069` | Pagos de emisoras de tarjetas por venta de gasolina en estaciones de servicio (PNR) | 1 % |
| `070` | Pagos de emisoras de tarjetas por venta de gasolina en estaciones de servicio (PJD) | 1 % |
| `071` | Pagos por gastos de transporte conformados por fletes (PNR) | 1 % |
| `072` | Pagos por gastos de transporte conformados por fletes (PJD) | 3 % |
| `073` | Pagos de empresas de seguro, corretaje de seguros y reaseguros por sus servicios propios (PNR) | 3 % |
| `074` | Pagos de empresas de seguro, corretaje de seguros y reaseguros por sus servicios propios (PJD) | 5 % |
| `075` | Pagos de aseguradoras a contratistas por reparación de daños sufridos por asegurados (PNR) | 3 % |
| `076` | Pagos de aseguradoras a contratistas por reparación de daños sufridos por asegurados (PJD) | 5 % |
| `077` | Pagos de aseguradoras a clínicas, hospitales y centros de salud por atención médica a asegurados (PNR) | 3 % |
| `078` | Pagos de aseguradoras a clínicas, hospitales y centros de salud por atención médica a asegurados (PJD) | 5 % |
| `079` | Pagos por adquisición de fondos de comercio situados en el país (PNR) | 3 % |
| `080` | Pagos por adquisición de fondos de comercio situados en el país (PNNR) | 34 % |
| `081` | Pagos por adquisición de fondos de comercio situados en el país (PJD) | 5 % |
| `082` | Pagos por adquisición de fondos de comercio situados en el país (PJND) | 5 % |
| `083` | Pagos por publicidad, propaganda y cesión de espacios para esos fines (PNR) | 3 % |
| `084` | Pagos por publicidad, propaganda y cesión de espacios para esos fines (PJD) | 5 % |
| `085` | Pagos por publicidad, propaganda y cesión de espacios para esos fines (PJND) | 5 % |
| `086` | Pagos por publicidad, propaganda y cesión de espacios a emisoras de radio (PJD) | 3 % |
<!-- FIN CATALOGO CONCEPTOS -->

## 5. Catálogo completo: Tipo de Operación

El anexo 6.2 define **sólo** estos tres tipos para el XML de dividendos y acciones; no son códigos HKA ni tipos de comprobante Pro9. [Fuente: p. 19.]

<!-- INICIO CATALOGO OPERACIONES -->
| Tipo | Descripción | Sigla |
| --- | --- | --- |
| `01` | Dividendo en acciones | DA |
| `02` | Dividendo en efectivo | DE |
| `03` | Venta de acciones | VA |
<!-- FIN CATALOGO OPERACIONES -->

## 6. Criterios para la implementación posterior

**Catálogos tenant preparados:** `cat_retention_concept(id, description, percentage_label)` almacena las 86 filas del § 4; `cat_retention_types(id, description, abbreviation)` almacena las tres del § 5. `percentage_label` es texto histórico, no una tasa calculable. La creación del módulo anterior de retenciones, que dependía de tasas 3 % y 6 %, permanece deshabilitada hasta definir un flujo fiscal compatible. Las tablas no generan XML ni acreditan vigencia del manual.

1. Elegir explícitamente el tipo de declaración. Comparten raíz, pero atributos, elementos y regla de `MontoOperacion` difieren; no mezclarlos en un archivo. [Fuente: pp. 9–13.]
2. Construir un `DetalleRetencion` por operación o tramo exigido, en el orden del XSD. Preservar los códigos con ceros a la izquierda como **cadenas**. No generar `MontoRetenido` ni atribuir al XML el cálculo que el manual asigna al portal. [Fuente: pp. 5–7, 9–13.]
3. Validar estructura XML, RIF, fechas reales, período, decimales, catálogo y reglas de inclusión antes de entregar el archivo. Separar errores del XML de las reglas tributarias que dependen de normativa o de la respuesta del portal. [Fuente: pp. 5–9, 11–19.]
4. Confirmar con SENIAT el XSD, catálogo, alícuotas, canal y comportamiento vigentes antes de habilitar uso fiscal. La edición 3.1 no constituye prueba de vigencia en 2026.
