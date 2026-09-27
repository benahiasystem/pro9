---
name: generar-xml-retenciones-islr-seniat
description: Documentar, diseñar o revisar el XML SENIAT de declaración mensual de retenciones ISLR de salarios y otras operaciones, dividendos y acciones, incluidos sus códigos de concepto y tipo. No usar para comprobantes de retención HKA ni para retenciones IVA.
---

# XML SENIAT de retenciones mensuales de ISLR

Fuente: [informe detallado y trazabilidad por páginas](../../../informes/xml_retenciones_islr_seniat.md). El manual de origen se titula *Retenciones de ISLR, Declaración Mensual*, N.º 60.40.40.039, versión **3.1 de junio de 2014**. El archivo proporcionado termina en `v3-0`, pero la portada dice 3.1. Es documentación histórica: **no afirmar vigencia del esquema, canal o porcentajes** sin confirmación oficial actual. Para comprobantes de retención ISLR/IVA emitidos por HKA, usar [emitir-guias-retenciones-hka](../emitir-guias-retenciones-hka/SKILL.md); son contratos diferentes.

En instalaciones nuevas, `cat_retention_concept` almacena los códigos `001`–`086` con `description` y `percentage_label` textual; `cat_retention_types` almacena únicamente `01`–`03` con `description` y `abbreviation`. Las semillas reproducen este manual de 2014. El antiguo módulo que calculaba retenciones con tasas 3 % y 6 % tiene la creación bloqueada; estas tablas no activan por sí solas una declaración XML ni un comprobante fiscal.

## Elegir el formato XML

- **Salarios y otras retenciones**: raíz `RelacionRetencionesISLR` con atributos obligatorios `RifAgente` y `Periodo` (`AAAAMM`); uno o más `DetalleRetencion`, cada uno con `RifRetenido`, `NumeroFactura`, `NumeroControl`, `FechaOperacion`, `CodigoConcepto`, `MontoOperacion`, `PorcentajeRetencion`, en ese orden. `CodigoConcepto` es una cadena de tres dígitos del catálogo 001–086. La descripción llama `Periodo` `String(7)`, pero el XSD y ejemplo lo presentan con seis caracteres. El XSD de este formato está impreso como imagen; no tratar esta skill como XSD oficial importable. [Fuente: manual pp. 5–7, 10–13.]
- **Dividendos y acciones**: misma raíz con atributos obligatorios `RifAgente` y `Fecha` (`DD/MM/AAAA`); uno o más `DetalleRetencion`, cada uno con `RifRetenido`, `Tipo`, `MontoOperacion`, `PorcentajeRetencion`, en ese orden. `Tipo` es `01`, `02` o `03`; el XSD exige `MontoOperacion > 0`. No usar `Periodo`, `NumeroFactura`, `NumeroControl` ni `CodigoConcepto` en este formato. [Fuente: manual pp. 9, 11–14.]
- RIF: V/E/J/P/G (mayúscula o minúscula) y nueve dígitos. `MontoOperacion` y `PorcentajeRetencion`: punto decimal y hasta dos decimales; porcentaje entre 0 y 100. `FechaOperacion` debe caer en `Periodo`. Comprobar fechas reales, no sólo el patrón XSD. [Fuente: manual pp. 5–6, 9, 11–13.]
- Conservar los ceros iniciales de códigos y tipos. Cada dato es obligatorio. No agregar monto retenido, sustraendo, moneda, totales ni firma: el manual no los incluye en estos XML. Serializar en la codificación declarada; los ejemplos del manual difieren (`ISO-8859-1` y `utf-8`). [Fuente: manual pp. 5–6, 9, 13–14.]

### Ejemplos estructurales con datos ficticios

El ejemplo de salarios del manual (p. 13) usa un `RifRetenido` con ocho dígitos y por ello **no** valida contra su propio patrón RIF; el siguiente corrige sólo ese dato para mostrar la forma esperada. Los porcentajes y códigos aquí son ilustrativos y no certifican un caso fiscal.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<RelacionRetencionesISLR RifAgente="J123456789" Periodo="202609">
  <DetalleRetencion>
    <RifRetenido>V123456789</RifRetenido>
    <NumeroFactura>4100</NumeroFactura>
    <NumeroControl>2100</NumeroControl>
    <FechaOperacion>24/09/2026</FechaOperacion>
    <CodigoConcepto>002</CodigoConcepto>
    <MontoOperacion>9000.00</MontoOperacion>
    <PorcentajeRetencion>3.00</PorcentajeRetencion>
  </DetalleRetencion>
</RelacionRetencionesISLR>
```

```xml
<?xml version="1.0" encoding="UTF-8"?>
<RelacionRetencionesISLR RifAgente="J123456789" Fecha="24/09/2026">
  <DetalleRetencion>
    <RifRetenido>V123456789</RifRetenido>
    <Tipo>01</Tipo>
    <MontoOperacion>1773.69</MontoOperacion>
    <PorcentajeRetencion>14.00</PorcentajeRetencion>
  </DetalleRetencion>
</RelacionRetencionesISLR>
```

## Reglas del manual que afectan la generación

- Salarios y demás conceptos del catálogo se informan aun si no producen retención: `PorcentajeRetencion = 0`; la excepción indicada son intereses bancarios pagados a personas naturales residentes. [Fuente: p. 6.]
- Para `002, 006, 010, 012, 014, 018, 025, 049, 053, 057, 061, 071, 073, 075, 077, 079, 083`, el manual atribuye el cálculo del sustraendo al portal y permite agrupar montos mensuales por código con una factura del período. No codificar un sustraendo como elemento XML. [Fuente: pp. 6–7.]
- Algunos supuestos requieren un detalle por tramo de porcentaje y acumulación anual por sujeto retenido. Los convenios de doble tributación pueden variar el porcentaje. Confirmar reglas vigentes antes de automatizar cálculo fiscal. [Fuente: pp. 6–7.]
- Verificar formato y canal contra SENIAT antes de producción; no reutilizar la API HKA ni representar este archivo como comprobante emitido. Revisar las [discrepancias y notas históricas](../../../informes/xml_retenciones_islr_seniat.md#3-lectura-de-los-esquemas-y-discrepancias-de-la-fuente).

## Catálogo completo: Código del Concepto de Retención

Catálogo **histórico de la edición 3.1 (2014)**. `PNR`: persona natural residente; `PNNR`: persona natural no residente; `PJD`: persona jurídica domiciliada; `PJND`: persona jurídica no domiciliada; `PJNCD`: persona jurídica no constituida domiciliada (definida, sin fila propia). `Escalonado`: 15 % hasta 2.000 UT, 22 % entre 2.000 y 3.000 UT y 34 % sobre el excedente. Las actividades se presentan con sus saltos de línea tipográficos normalizados. [Fuente: manual pp. 14–18.]

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

## Catálogo completo: Tipo de Operación

Sólo para dividendos y acciones. [Fuente: manual p. 19.]

| Tipo | Descripción | Sigla |
| --- | --- | --- |
| `01` | Dividendo en acciones | DA |
| `02` | Dividendo en efectivo | DE |
| `03` | Venta de acciones | VA |
