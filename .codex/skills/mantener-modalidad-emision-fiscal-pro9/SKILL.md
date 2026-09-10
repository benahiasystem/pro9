---
name: mantener-modalidad-emision-fiscal-pro9
description: Mantener la modalidad de emisión fiscal y el ambiente de Pro9, el alta de tenants, los parámetros y credenciales de proveedores y la retirada total de SOAP/PFX de facturación. Usar al modificar configuración fiscal, fiscal_environment, fiscal_emission_mode, correlativos o la separación demo/producción.
---

# Modalidad de emisión fiscal de Pro9

## Decisiones del producto

- Hay una modalidad por tenant: `fiscal_machine` (Máquina fiscal), `digital` (Medios digitales), `free_form` (Forma libre).
- El ambiente es independiente: `demo` o `production`. No existen SOAP Tipo, SOAP Envío ni ambiente Interno; no restablecer sus campos como alias.
- El alta exige modalidad y ambiente en superadmin, autoregistro y API. Los parámetros se completan progresivamente; elegir una modalidad no conecta equipos ni acredita emisión fiscal efectiva.
- Este contrato parte de una instalación nueva sin tenants ni datos anteriores. No crear conversiones, backfills ni migraciones incrementales SOAP/PFX. Cada tenant nace con modalidad y ambiente obligatorios.
- El administrador tenant y el superadmin pueden configurar modalidad y ambiente. Después de cualquier operación el ambiente queda bloqueado, incluso si posteriormente se eliminan los movimientos. Producción requiere otro tenant limpio; no copiar operaciones ni correlativos demo.
- La primera etapa conserva el registro comercial local y PDF. No transmite, firma XML ni asigna números de control fiscales. Una integración futura requiere alcance y proveedor concretos.

## Puntos de implementación

- `App\Services\FiscalEmissionSettings` centraliza valores válidos, parámetros, validación y actualización. `companies` del tenant es la fuente; el superadmin consulta ese registro y no mantiene otra copia.
- El endpoint tenant dedicado es `GET/POST /companies/fiscal-emission`; exige un usuario tenant de tipo `admin`, también en AJAX. El editor general de empresa rechaza campos fiscales para impedir saltarse las reglas.
- Máquina fiscal: modelo, serial, puerto y proveedor/controlador. Digital: proveedor, autorización y credenciales. Forma libre: imprenta, número de control, inicio y fin de rango. No aceptar parámetros de otra modalidad ni rangos negativos/invertidos.
- Las credenciales se cifran en el modelo, se ocultan de toda serialización y no se incluyen en logs. Campo vacío conserva el secreto; borrado explícito lo elimina; cambiar modalidad descarta parámetros y credenciales anteriores.
- `fiscal_configuration_audits` registra actor, campos modificados, modalidad y ambiente; nunca valores secretos.
- El guardado de operaciones en `ModelTenant` comparte el bloqueo transaccional de la empresa con la actualización del ambiente. Revisar `config/fiscal_emission.php` al incorporar nuevas tablas o escritores que omitan Eloquent.
- Las facturas nuevas reciben modalidad y ambiente desde el servidor. No admitir esos valores desde el payload como sustitución de la configuración ni modificar la modalidad registrada en facturas anteriores.
- El alta inserta modalidad y ambiente en `companies` antes de guardar parámetros y auditoría; la base no acepta modalidad nula. Los endpoints fiscales usan sólo los campos del contrato actual; campos adicionales no se persisten ni requieren validadores de formatos retirados.
- Conservar QZ Tray: sus certificados sirven a impresión y no son los PFX/PEM de envío fiscal retirados.

## Migraciones y pruebas

- Actualizar directamente el esquema consolidado y los seeders. `companies` contiene modalidad obligatoria, configuración/credenciales opcionales y bloqueo inicialmente falso; `documents` contiene modalidad obligatoria.
- `2026_08_17_000176_create_fiscal_configuration_audits_table.php` crea la auditoría. Su FK de empresa pertenece a la migración final de claves foráneas. La auditoría registra cambios futuros y no es compatibilidad histórica.
- La cadena de creación central no debe añadir certificados ni campos de transporte fiscal para borrarlos al final. El catálogo inicial tampoco contiene tipos de auditoría SOAP/certificado.
- Ejecutar `FiscalEmissionSettingsTest` y `FiscalEmissionSchemaTest`. La segunda requiere `PRO9_FISCAL_MYSQL_TESTS=1` y crea/elimina exclusivamente bases aleatorias con prefijo `pro9_fiscal_test_`; verifica creación limpia, seeding, rollback y segunda creación con esquema idéntico.
- Verificar también inicialización tenant, RIF, política fiscal local, catálogos y flujos de documentos/inventario afectados. Probar permisos, aislamiento de tenants, intentos de cambiar ambiente mediante API y ausencia de credenciales en respuestas.
- Usar `frontend-build` para assets. No generar bundles sin petición explícita del usuario. La validación de sintaxis en memoria no sustituye la comprobación visual del bundle desplegado.
- Delimitar los cambios propios con comentarios `######## INICIO MODALIDAD DE EMISIÓN FISCAL ########` y `######## FIN MODALIDAD DE EMISIÓN FISCAL ########`.

Esta decisión sustituye las instrucciones de conservar o convertir SOAP/PFX. Las compatibilidades de otros módulos se revisan en un plan separado; no ampliar automáticamente esta retirada a sus contratos ni a certificados de impresión.

## Informe y revisión

La implementación, comandos de prueba y límites de verificación se documentan en [el informe de adaptación](../../../informes/adaptacion_modalidad_emision_fiscal.md). Actualizarlo cuando cambie el alcance o la evidencia. No declarar probada una integración fiscal real, una aplicación en producción o el navegador basándose sólo en pruebas de servicio y esquema.
