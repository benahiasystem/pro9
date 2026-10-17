---
name: migrar-venezuela-moneda
description: Mantener VES/USD, símbolos y conversiones de Pro9, distinguiendo moneda documental, recibida y aplicada. Usar al modificar currency_type_id, tipos de cambio, POS, caja, cobros, finanzas o reportes monetarios, incluida la retirada de PEN/VED.
---

# Migrar moneda venezolana

## Precisión exacta — contrato del 16/10/2026

Aplicar [cálculos exactos](../mantener-calculos-exactos-pro9/SKILL.md). Para instalaciones nuevas esta instrucción sustituye las precisiones de dos/cuatro/seis decimales y redondeos intermedios descritos anteriormente: operar con fracciones exactas, conservar snapshots y representar hasta 12 posiciones; tasas con ocho exactas. Dos decimales sólo en liquidación/presentación que lo requiera. Precio unitario PDF configurable de 2 a 12, con dos por defecto. No convertir tenants ni recalcular históricos. Consultar el informe de cálculos para el estado real de adopción por consumidor.


## Contrato

- Usar `VES` como código interno nacional, `Bs.` como símbolo y `Bolívares` como descripción.
- Mantener `USD` como moneda secundaria y eliminar `PEN` y `VED` de los registros y del catálogo.
- Alternar POS entre `VES` y `USD`.
- Mantener nombres internos antiguos como `_pen` cuando renombrarlos implique una migración de esquema o API.
- No aceptar ni convertir códigos monetarios retirados. La instalación nace con VES/USD.
- Obtener el símbolo desde el catálogo o `Localization::currencySymbol`; no mostrar el código VES como símbolo de importe.

## Flujo

1. Auditar el catálogo inicial, defaults y consumidores de `currency_type_id` y sus variantes origen/destino.
2. Sembrar directamente VES/USD en instalaciones nuevas; este flujo no convierte tenants existentes ni usa una base fuente.
3. Mantener las referencias e índices desde las migraciones de creación. No restablecer la conversión incremental `000329` ni el comando para tenants existentes.
4. Obtener símbolos desde la moneda del registro. Mostrar Bs. para VES y $ para USD.
   - En listados de documentos, exponer `currency_type_symbol` desde el resource y renderizarlo directamente.
   - No usar condiciones heredadas que asignen el símbolo monetario según `PEN` o `VED`; convierten VES erróneamente en dólares.
5. Revisar la dirección de cada conversión base/USD, incluidos POS, caja, compras, hotel y finanzas.
6. Bloquear proveedores que no acepten VES. Nunca enviar VES como PEN o VED ni falsificar la moneda.
7. Actualizar valores iniciales, semillas, ejemplos, migraciones base y pruebas para que toda nueva escritura use VES.
8. Revisar también contabilidad, dashboard, ecommerce, restaurante, inventario, artículos, producción, ventas, suscripciones, enlaces de pago, plantillas PDF/XML y reportes.
9. Conservar nombres internos como `total_pen` sólo cuando renombrarlos rompa esquemas o APIs; el valor y la etiqueta deben corresponder a VES.
10. Conservar la estructura final de `cat_currency_types` y todas las columnas monetarias en las migraciones consolidadas; sembrar VES/Bs./Bolívares y USD mediante `TenantMigrationDataSeeder`.
11. No restaurar `app/CoreFacturalo/WS/Validator/data/CodeErrors.xml`: se retiró junto con el transporte XML/CDR y no forma parte del contrato monetario actual.
12. Delimitar toda modificación de código con comentarios válidos que contengan `######## INICIO` y `######## FIN`; no agregar comentarios a JSON ni binarios.
13. `VendeyaDocumentPayloadNormalizer` valida VES/USD y conserva el cálculo vigente de IVA; no transforma monedas retiradas.

## Moneda documental, recibida y aplicada

Al modificar cobros o saldos, aplicar [persistencia fiscal y cobros](../mantener-persistencia-fiscal-venezuela-pro9/SKILL.md). El contrato del pago no se obtiene sólo de la moneda de la factura.

- `documents.currency_type_id` identifica la moneda documental; `document_payments.currency_type_id`, la recibida. `payment` es el principal neto aplicado en la primera; `original_amount`, el principal recibido en la segunda, sin vuelto ni IGTF. `tax_amount` conserva IGTF recibido en moneda del pago.
- La tasa representa VES por USD: USD → VES multiplica, VES → USD divide. Usar `FiscalAmounts` y las tasas conservadas de documento/pago; no sustituirlas por la tasa vigente al reimprimir, reportar o preparar HKA.
- Mantener tasas en `DECIMAL(18,8)` y cadenas de ocho posiciones. En instalaciones nuevas, importes/bases/porcentajes usan `DECIMAL(30,12)` y snapshots racionales. Conservar la fracción durante conversiones encadenadas; adaptar una sola vez la salida de presentación o liquidación. Rechazar tasas que requieran redondeo para caber en ocho posiciones.
- Una moneda propia por API exige UUID de operación; cuando difiere de la documental, exigir importe original y tasa explícitos. Omitir moneda propia conserva el comportamiento anterior. Mantener fuente y fecha de la tasa del pago.
- Saldos usan importes aplicados en moneda documental, pagos activos, retenciones y fondos. Caja/bancos/movimientos usan recepción efectiva e IGTF en la moneda recibida; excluir recibos derivados `receipt_parent_id` de una nueva entrada de efectivo. No sumar USD y VES sin conversión identificable.
- `document_currency_totals` conserva equivalentes VES y desglose fiscal. Adaptar PDFs/reportes para presentar esos equivalentes y distinguir recibido/aplicado sin recalcular el histórico desde catálogos mutables.
- La interfaz actual no incluye selectores de moneda propia/cobro mixto; ese contrato existe por API. No describirlo como disponible en las pantallas por la sola presencia de los campos en base de datos.

## Validación

- Probar VES y USD, y auditar que no queden registros PEN ni VED.
- Probar alternancia POS, cálculos con tipo de cambio, cero, reportes y exportaciones.
- Confirmar que compras y su ventana de agregar producto muestran VES como código y Bs. como símbolo de importes.
- Confirmar que el catálogo de cada tenant contiene `VES / Bs. / Bolívares`, conserva USD y no contiene PEN ni VED.
- Auditar fuentes activas y bundle generado por separado, excluyendo respaldos, vendor y catálogos canónicos de errores.
- Verificar que Culqi responda con un error legible antes de intentar un cobro en VES.
- Confirmar datos iniciales reproducibles y rechazo de monedas retiradas.
- Comprobar en una instalación temporal que el POS muestra `Bs.`/Bolívares y alterna únicamente entre VES y USD.
- Para cobros en otra moneda, verificar `FiscalApiPaymentTransformTest` y los escenarios de `FiscalEmissionSchemaTest` en MySQL temporal: pago parcial, tasa propia, vuelto, redondeo, IGTF, reversión y ausencia de doble ingreso por recibos derivados.

## Precisión de tasas BCV — instrucción de 7 de octubre de 2026

- Pro9 conserva tasas como cadenas decimales de ocho posiciones en `DECIMAL(18,8)`, incluidos ceros finales. La API BCV devuelve cadenas y mantiene token fijo.
- Calcular conversiones con `Brick\Math\BigRational` en PHP y `ExactAmount`/`BigInt` en JavaScript. Conservar la fracción durante toda la operación y al recuperarla de la base. HALF_UP sólo para representaciones finales autorizadas, presentación o liquidación; nunca reutilizar esas representaciones para calcular.
- Excepción autorizada para tenants existentes: `exchange-rates:upgrade-precision --all-tenants --dry-run` y luego sin `--dry-run`, tras pruebas en MySQL temporal. El comando amplía únicamente las columnas inventariadas en `config/exchange_rate_precision.php`; no reconstruye bases ni recupera decimales históricos.

## Cobro SPE y presentación doméstica HKA

Seguir [perfiles SPE](../mantener-sujetos-pasivos-especiales-pro9/SKILL.md): la clasificación usa perfil vigente del emisor, moneda realmente recibida e intermediación explícita; no inferir IGTF de moneda documental o comprador. Mantener `original_amount`/`payment`/`tax_amount` y tasa exacta en el contexto congelado del cobro. Crédito y recuperación de borradores sólo conservan abonos introducidos, sin proponer/distribuir el total.

`HkaDomesticMoney` adapta una copia para factura doméstica USD o con IGTF y nota exclusiva 98: principales VES/OtraMoneda USD sin modificar dinero local o payloads preparados. La nota utiliza tasa/fecha del cobro y conserva moneda/tasa original del padre para `montoFacturaAfectada`. Comprobar conversiones, saldos, caja/recibos derivados y regresiones de terceros/regímenes/exportación.

## Dinero de comprobantes IVA recibidos

Aplicar [retenciones IVA recibidas](../mantener-retenciones-iva-recibidas-pro9/SKILL.md). `base` es IVA sujeto, `taxable_base` es base imponible y `amount` importe retenido; `applied_amount` reduce saldo documental con tasa del comprobante. Comparar límites fiscales en VES con los importes/tasa de emisión conservados, sin confundirlos con equivalencia de liquidación.

`HkaIvaRetentionPayload` convierte directamente desde moneda del comprobante a moneda fiscal confirmada; VES→VES permanece exacto aun con factura USD y tasas distintas. No convertir primero al importe aplicado USD y luego usar tasa de emisión. Ejemplo probado: IVA fiscal 320 VES, retención 240 VES, factura USD con tasa 10 y comprobante con tasa 20: aplicar 12 USD y enviar 320/240 VES. El comprobante no crea efectivo ni base IGTF; previsión y estados inactivos aplican cero. Verificar `HkaIvaRetentionPayloadTest` y `test_iva_fiscal_ves*` en MySQL temporal.
