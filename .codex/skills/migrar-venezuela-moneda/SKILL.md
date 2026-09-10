---
name: migrar-venezuela-moneda
description: Reemplazar PEN o VED por VES y adaptar símbolos, POS, caja, finanzas y reportes de pro9 a bolívares. Usar al modificar VES, VED, PEN, USD, currency_type_id, Bs., bolívares, conversiones, medios de pago o integraciones monetarias.
---

# Migrar moneda venezolana

## Contrato

- Usar `VES` como código interno nacional, `Bs.` como símbolo y `Bolívares` como descripción.
- Mantener `USD` como moneda secundaria y eliminar `PEN` y `VED` de los registros y del catálogo.
- Alternar POS entre `VES` y `USD`.
- Mantener nombres internos antiguos como `_pen` cuando renombrarlos implique una migración de esquema o API.
- No aceptar ni convertir códigos monetarios retirados. La instalación nace con VES/USD.
- Obtener el símbolo desde el catálogo o `Localization::currencySymbol`; no mostrar el código VES como símbolo de importe.

## Flujo

1. Auditar el catálogo inicial, defaults y consumidores de `currency_type_id` y sus variantes origen/destino.
2. Sembrar directamente VES/USD; no existe tenant fuente ni datos anteriores que convertir.
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

## Validación

- Probar VES y USD, y auditar que no queden registros PEN ni VED.
- Probar alternancia POS, cálculos con tipo de cambio, cero, reportes y exportaciones.
- Confirmar que compras y su ventana de agregar producto muestran VES como código y Bs. como símbolo de importes.
- Confirmar que el catálogo de cada tenant contiene `VES / Bs. / Bolívares`, conserva USD y no contiene PEN ni VED.
- Auditar fuentes activas y bundle generado por separado, excluyendo respaldos, vendor y catálogos canónicos de errores.
- Verificar que Culqi responda con un error legible antes de intentar un cobro en VES.
- Confirmar datos iniciales reproducibles y rechazo de monedas retiradas.
- Comprobar en una instalación temporal que el POS muestra `Bs.`/Bolívares y alterna únicamente entre VES y USD.
