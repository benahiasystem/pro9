# Contrato HKA Venezuela utilizado

Copia del Swagger JSON publicado por HKA:
https://demoemisionv2.thefactoryhka.com.ve/swagger/v1/swagger.json

Archivo: `hka-ve-v1.json`. El identificador de versión persistido es
`hka-ve-v1:<SHA-256 del archivo>`, calculado por `HkaPayloadBuilder`.
Los fixtures saneados están en `tests/Fixtures/Hka` y contienen datos fiscales
persistidos de Factura, crédito, débito y débito exclusivo por IGTF.

El constructor valida el resultado contra esta copia local. No requiere acceso
a Internet ni credenciales. Una actualización del Swagger requiere revisar el
adaptador, las reglas condicionales y los fixtures antes de cambiar la copia.
La estructura del Swagger no sustituye la homologación de los casos fiscales.

No se utiliza el nodo `vendedor` como emisor: representa al vendedor comercial.
El RIF del emisor queda en `documents.issuer`; el transporte futuro deberá
verificar que las credenciales del proveedor correspondan a ese emisor.
