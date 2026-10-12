# Borrador de consulta HKA — cobro neto y retenciones IVA recibidas

Documento preparado para revisión; no enviado.

En DEMO, facturas VES/USD de total 232 con cobro real 208 y retención prevista 24 son rechazadas con `203 / 1016`, tanto al contado como a crédito: la suma de las formas de pago debe coincidir con Total a Pagar. Crédito sin cobro también había sido rechazado con `1015`.

Solicitamos precisar el contrato admitido para emitir la factura con su saldo pendiente y registrar después el comprobante IVA real del cliente, sin declarar pagos que todavía no se han recibido. No corresponde emitir un tipo `05`: se trata de `AplicarRetencion` sobre una factura `01`.

Preguntas concretas:

1. ¿Cómo se representan en `Emision` los abonos reales y el saldo pendiente por crédito/retención, y qué importes deben tener `formasPago` y `totalAPagar`?
2. ¿Existe una forma documentada de emitir antes de recibir el comprobante, que necesita referir al número/control de la factura ya emitida?
3. ¿Cómo se aplica el contrato en facturas USD expresadas fiscalmente en VES, conservando tasa de emisión y tasa de aplicación al saldo?
4. ¿Qué cuenta DEMO está habilitada para acreditar las condiciones fiscales de emisor y cliente necesarias para esta matriz?

El protocolo POST/GET/DELETE de `AplicarRetencion` sí fue aceptado en facturas de control completamente cobradas, con coincidencia de identidad, comprobante e importes y ausencia posterior a la eliminación. Esa prueba no acredita la emisión con cobro neto. Pro9 mantiene bloqueada esa combinación hasta resolver el contrato.
