---
name: emitir-facturas-notas-hka
description: Mantener la preparación de payloads HKA de facturas y notas de Pro9 desde snapshots, con Swagger versionado, validación y operación congelada. Usar al modificar HkaPayloadBuilder, HkaEmissionPreparation, referencias de notas o su futura emisión; el transporte y la autenticación tienen habilidades propias.
---

# Facturas y notas HKA

## Estado y fuentes

`HkaPayloadBuilder`, `HkaSchemaValidator` y `HkaEmissionPreparation` implementan preparación local, sin llamadas HTTP. Leer el [informe de persistencia/preparación](../../../informes/persistencia_fiscal_venezuela_hka.md) y aplicar [persistencia fiscal y cobros](../mantener-persistencia-fiscal-venezuela-pro9/SKILL.md) cuando cambien sus datos de entrada. Envío, consultas, conciliación, descarga y homologación siguen pendientes.

Para condiciones HKA consultar [referencia HKA, secciones 1, 4.1–4.4, 5, 7 y 10–12](../../../informes/imprenta_digital_hka_api.md). La copia contractual utilizada está en `app/Services/Fiscal/contracts/hka-ve-v1.json`; `HkaPayloadBuilder::contractVersion()` identifica versión y hash. Al actualizar el contrato, contrastar con el [Swagger publicado](https://demoemisionv2.thefactoryhka.com.ve/swagger/v1/swagger.json) y revisar reglas condicionales, adaptador y fixtures juntos. No consultar el proveedor durante la construcción ni sustituir silenciosamente la versión de una operación preparada.

## Construcción y equivalencias

- **Conservar tipos y numeración internos:** Pro9 factura `01`, crédito `07`, débito `08`; HKA recibe `01`, `02`, `03` respectivamente. Traducir por tipo semántico sólo en el adaptador HKA y al interpretar sus respuestas. Jamás reemplazar `series`, `series_configurations`, validaciones o códigos persistidos por los del proveedor. HKA `07` designa ARCV, no crédito.
- Mantener el constructor puro: recibe un snapshot completo, sin HTTP ni consultas a modelos/catálogos. Construir comprador, líneas, impuestos, pagos y totales VES desde lo conservado. Una equivalencia ausente de identidad, unidad, moneda, pago o alícuota produce error explícito. Aplicar [factura/nota sin boleta](../mantener-facturas-notas-venta-sin-boleta/SKILL.md), [clientes](../gestionar-clientes-venezuela/SKILL.md) e [IVA](../migrar-iva-venezuela/SKILL.md) cuando cambien sus contratos.
- Mapear moneda, unidades y pagos en el borde HKA; consultar [moneda](../migrar-venezuela-moneda/SKILL.md), [unidades](../mantener-unidades-medida-venezuela/SKILL.md) y catálogos fiscales. `VED`, `VEF` o `BsD` del manual no cambian el contrato VES de Pro9. No usar la tolerancia HKA para alterar importes locales.
- Swagger publica `POST /api/Emision` con raíz `documentoElectronico` y propiedades `lowerCamelCase`; el `tipoDocumento` normal admite `01`–`06`. `facturaGuia` es lista; `totales` incorpora recargos, OTI e IGTF. Validar contra el contrato local con `HkaSchemaValidator` y comprobar condiciones de negocio aunque el esquema deje propiedades opcionales. El formato HKA no cambia el cálculo ni las precisiones locales.
- Las notas conservan serie, número, fecha, total y control de la factura afectada. Exigir control real para preparar; si llegó después de crear la nota, `HkaEmissionPreparation` puede completar ese control desde la factura vinculada bajo transacción. No rehacer el resto de la referencia desde datos mutables ni inventar un control.
- La nota exclusiva IGTF permanece `08` Pro9 → `03` HKA, transacción `98`: sin líneas, IVA ni inventario; total igual al cargo IGTF. Los pagos usan importes/moneda recibidos e impuesto cobrado; excluir revertidos y evitar duplicar recibos derivados en la factura.
- La presencia de catálogos no habilita exportaciones, regímenes especiales, terceros ni tributos adicionales sin adaptación. Conservar sus datos condicionales para evolución posterior, pero rechazar combinaciones aún no soportadas.

## Operación preparada y control

- `HkaEmissionPreparation::prepare` bloquea empresa/documento en la conexión tenant, exige documento vigente en modalidad digital y mantiene una única relación de emisión. Preparar nuevamente devuelve el mismo payload y UUID; no recalcularlo con una empresa, catálogo o tasa vigente diferente.
- Estados previstos: `not_requested`, `prepared`, `pending`, `confirmed`, `rejected`, `uncertain`, `cancelled`. La preparación actual sólo produce los dos primeros; no usar `prepared` como sinónimo de enviado o aceptado ni alterar el estado comercial por él.
- `control_number` es texto nullable independiente de serie/número. Asignarlo únicamente mediante el servicio interno validado `setControl`, que comprueba formato, inmutabilidad y duplicidad; no exponer asignación por endpoints comerciales. Aplicar [numeración fiscal](../mantener-numeracion-fiscal-venezuela-pro9/SKILL.md).
- No guardar JWT/credenciales en payloads, snapshots, respuestas ni emisión. El Swagger no tiene nodo de identidad del emisor; mantener su RIF en `documents.issuer`. El transporte futuro deberá verificar que sus credenciales correspondan al emisor conservado; aplicar [conectar-api-hka](../conectar-api-hka/SKILL.md) cuando se implemente ese transporte.

## Validación

- Mantener fixtures saneados de Factura, crédito, débito e IGTF en `tests/Fixtures/Hka`; ejecutar `HkaPayloadBuilderTest` para esquema, traducciones y rechazos.
- Para idempotencia, referencias conservadas, control, autorización y congelación, ejecutar las pruebas afectadas de `FiscalEmissionSchemaTest` en MySQL temporal y `DocumentFiscalAuthorizationTest`.
- Una etapa futura de envío debe probar en DEMO serie vacía, respuesta, NC/ND y resultados inciertos. El validador de esquema y los simuladores locales sólo acreditan preparación contractual.

La configuración usa series originales y sólo fija el inicio documental. La emisión HKA con asignación automática de control aún no está implementada; requiere una tarea posterior explícita. No recrear el módulo retirado de perfiles y asignaciones anticipadas.
