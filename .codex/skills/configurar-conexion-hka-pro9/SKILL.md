---
name: configurar-conexion-hka-pro9
description: Configurar y verificar la autenticación HKA desde la modalidad Medios digitales de un tenant Pro9, con credenciales cifradas, estado visible y pruebas sin emisión.
---

# Configurar conexión HKA en Pro9

Aplicar junto con [conectar-api-hka](../conectar-api-hka/SKILL.md) al modificar `/companies/create`, `GET/POST /companies/fiscal-emission` o el cliente de autenticación HKA. El [Swagger HKA](https://demoemisionv2.thefactoryhka.com.ve/swagger/v1/swagger.json) define `POST /api/Autenticacion` con `usuario` y `clave`; responde `token` y `expiracion`. Esta etapa no envía documentos.

- Sólo el administrador tenant puede guardar o consultar la configuración. El tenant se resuelve en servidor; IDs o credenciales de otra petición no eligen empresa. El editor general de empresa no modifica campos fiscales.
- La pantalla tenant permite seleccionar `digital` y muestra Usuario HKA y Clave HKA. El componente compartido mantiene la selección de superadmin y autorregistro como estaba. El cliente elige el host HKA exclusivamente a partir de `fiscal_environment`; las credenciales y tokens de un ambiente no se reutilizan en el otro.
- Usuario y clave viajan sólo en la petición autenticada y se almacenan juntos como JSON bajo el cast cifrado de `companies.fiscal_credentials`. Las respuestas devuelven únicamente indicador de configuración y fecha de última autenticación correcta. No devolver usuario, clave, JWT ni respuesta cruda de HKA.
- Se puede guardar `digital` sin credenciales, con conexión pendiente. Si se proporcionan ambos datos, validar antes de persistir; un rechazo, timeout o respuesta incompleta conserva el estado anterior. Campos vacíos preservan credenciales; borrado explícito las elimina. Reautenticar al activar la conexión o cambiar credenciales, no al guardar otros ajustes.
- `hka_authenticated_at` representa la última autenticación correcta en el ambiente configurado, no una garantía de disponibilidad actual ni autorización de emisión. Limpiarlo al borrar credenciales, cambiar modalidad o cambiar ambiente; al proporcionar credenciales en el nuevo ambiente, autenticarlas de nuevo. Auditar nombres de campos modificados sin valores secretos.
- Probar con `Http::fake` éxito, rechazo, respuesta incompleta, expiración inválida, timeout, elección de ambiente, permisos, aislamiento tenant y ausencia de secretos. La prueba real requiere credenciales del ambiente correspondiente proporcionadas por HKA. Para cambios en Vue seguir [frontend-build](../frontend-build/SKILL.md); no generar bundles sin petición del usuario.
