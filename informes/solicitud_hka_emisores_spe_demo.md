# Borrador para soporte HKA — no enviado

Asunto: Habilitación de emisores SPE en DEMO y revisión de crédito/leyenda fiscal

Solicitamos un ambiente de prueba habilitado para emisores Sujetos Pasivos Especiales: persona natural con actividad comercial, firma personal y persona jurídica, con sus numeraciones de factura y nota de débito. La empresa disponible está clasificada como jurídica FORMAL; necesitamos probar IGTF inicial y nota de débito HKA tipo 03 / transacción 98 de cobros posteriores a una factura ya confirmada.

También solicitamos precisar:

1. Cómo emitir una factura a crédito sin dinero recibido ni `formasPago` ficticias. La API DEMO devuelve código 203 / validación 1015 cuando `tipoDePago` es Crédito y la lista de cobros está vacía.
2. El mecanismo admitido para imprimir «Sujeto Pasivo Especial» del emisor/comprador en A4/A5. `InfoAdicional` conserva la condición en el JSON descargado, pero no aparece en el PDF original.
3. El formato de identificación esperado para evitar duplicar el prefijo en el PDF cuando `TipoIdentificacion` y `NumeroIdentificacion` contienen el tipo y el RIF fiscal completo con guiones.
4. La disponibilidad y reglas de PDF/consulta de la nota exclusiva IGTF sin líneas ni IVA, vinculada al control de la factura y al cobro real.

Disponemos de ejemplos, respuestas y documentos DEMO preservados para revisión privada. Los identificadores reales, originales y credenciales se excluyen de este borrador. Antes de enviarlo debe completarse el destinatario y seleccionarse únicamente la evidencia necesaria.
