---
name: rif-super-admin-pro9
description: "Mantener y extender en Pro9 el contrato RIF de clientes del super admin: formato, normalización, validación, consulta configurable y textos relacionados. Usar al tocar alta, edición, listado, eliminación, estado de cuenta, reportes o servicios fiscales de clientes del sistema; no usar para personas o catálogos tenant."
---

# Mantener el RIF del super admin en Pro9

## Contrato funcional

- El identificador canónico es un prefijo `V`, `E`, `J`, `P` o `G` seguido por nueve dígitos, por ejemplo `J123456789`.
- Normalizar a mayúsculas y retirar espacios, puntos y guiones antes de validar, consultar y persistir.
- El backend exige el RIF, valida el formato y aplica unicidad sobre `system.clients`; una actualización ignora únicamente al cliente editado.
- Mostrar `RIF` en alta, edición, listado, eliminación, estado de cuenta y reportes donde `clients.number` sea el identificador de la empresa administrada.
- La creación y edición manual deben funcionar aunque la consulta externa esté deshabilitada o falle.

## Consulta externa aislada

La búsqueda vive exclusivamente en el super admin mediante `GET /services/rif/{rif}`, dentro de `auth:admin` y `reseller.system.admin`. Nunca registrar esta ruta en tenant, autoregistro o API pública.

La configuración se lee sólo en backend desde `services.super_admin_rif_lookup` y las variables `SUPER_ADMIN_RIF_LOOKUP_*`. La integración permanece deshabilitada por defecto y sólo está disponible con URL válida que contenga exactamente `{rif}`, token no vacío y HTTPS fuera de local/testing.

No exponer URL, token, cabeceras, cuerpo remoto ni mensajes crudos. No reutilizar `url_apiruc`, `token_apiruc`, ApiPeru ni componentes compartidos con tenants. El frontend recibe únicamente la bandera `rif_lookup_available`.

## Límites de alcance

- Conservar sin cambios formularios, catálogos, rutas y servicios tenant.
- Conservar SOAP, certificados, credenciales de envío y referencias SUNAT/RUC que describan contratos fiscales peruanos ajenos al identificador del cliente del sistema.
- Mantener fuera de esta migración el autoregistro RUC/SUNAT, la búsqueda pública por RUC emisor, facturación masiva y `System\\Api\\TenantController`; son flujos públicos o documentales distintos del mantenimiento autenticado de clientes.
- No copiar URLs, tokens, `dd()`, logs de respuestas ni bundles históricos de Pro6.
- No editar bundles compilados; verificar mediante el build desde fuentes.
- La columna `system.clients.number` ya admite diez caracteres, por lo que este contrato no requiere migración de esquema.

## Marcadores obligatorios

Cada unidad modificada debe quedar delimitada con comentarios válidos que contengan exactamente:

```text
########## INICIO CAMBIO RIF SUPER ADMIN
######### FIN CAMBIO RIF SUPER ADMIN
```

No envolver funciones preexistentes completas ni insertar marcadores en JSON, archivos minificados o bundles.

## Verificación obligatoria

1. Ejecutar `SystemRifTest`, `SystemRifLookupServiceTest` y `SystemRifContractTest`.
2. Cubrir normalización, cinco prefijos, rechazos de formato, unicidad de requests, proveedor exitoso y fallos controlados.
3. Confirmar que la ruta RIF aparece una sola vez dentro del grupo autenticado del sistema y no existe en rutas tenant/API/autoregistro.
4. Auditar textos RUC/RIF del sistema y justificar las exclusiones SOAP, certificados, autoregistro y documentos peruanos.
5. Confirmar que ningún archivo tenant fue modificado por este contrato.
6. Ejecutar `git diff --check`, análisis PHP, PHPUnit completo y `npm run build`; retirar del diff los artefactos generados.
