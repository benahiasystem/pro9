---
name: mantener-retenciones-islr-recibidas-pro9
description: Mantener previsión, comprobantes ISLR recibidos de clientes, saldos, soportes privados, AplicarRetencionISLR HKA y reversión en Pro9. Usar para IslrRetentionService, HkaIslrRetention, su outbox, endpoints o componentes; comprobantes emitidos 06, salarios, ARC y XML SENIAT tienen contratos separados.
---

# Retenciones ISLR recibidas

Contrato de instalaciones nuevas del 12/10/2026. Leer [puntos de intervención](references/contratos.md) al cambiar consumidores o estados y [documentación API/evidencia](../../../informes/retenciones_islr_recibidas_hka.md) para ejemplos, cobertura y resultados reales. No convertir tenants existentes ni habilitar producción por pruebas DEMO.

- `FiscalDocumentPersistence` delega IVA e ISLR a políticas distintas. `ReceivedRetentionService` comparte autorización, soporte, UUID/hash, bloqueo empresa → factura → comprobante, activación, saldo/crédito, reversión y DTO público. No duplicar el ciclo en módulos.
- Un comprobante ISLR pendiente/aplicado por factura, independiente de IVA; un concepto no salarial por comprobante. Índice generado único, históricos de reemplazos y UUID de registro/reversión. Repetición idéntica devuelve resultado; datos/acción diferentes con la misma clave producen 409, validación ordinaria 422.
- Resolver perfiles versionados por fecha del comprobante: RIF y facultad ISLR con fundamento propio. SPE no determina esa facultad, concepto ni porcentaje. Excluir `001`, exigir fundamento y evidencia. El catálogo ayuda a seleccionar; sus etiquetas históricas no constituyen un motor de tasas.
- Fórmula monetaria `max(0, base × porcentaje / 100 − sustraendo)`, con importe positivo, base respaldada por valor neto fiscal conservado en VES y aplicación dentro del saldo acumulado con IVA y cobros. Número obligatorio de texto hasta 50 caracteres; no imponer patrón IVA.
- `islr_retention_prevision` se recalcula en servidor, fuera de pagos. Prever IVA 12 e ISLR 3 sobre total 116 sugiere cobro 101 y conserva deuda 15 hasta validar. Conservar abonos/borradores, evitar previsiones duplicadas de aplicaciones vigentes y descartar ambos datos al cambiar a Nota de venta.
- Guardar moneda/tasa de ocho decimales, importe retenido y aplicado separadamente. Payload HKA convierte directamente desde moneda del comprobante a moneda fiscal congelada; no usar tasa del catálogo ni pasar por liquidación documental.
- Soporte privado PDF/JPG/PNG ≤5 MB, tokens tenant/usuario de 24 horas y consumo único. Copia bajo bloqueo preserva temporal ante rollback. Descarga autenticada, limpieza compartida IVA; no eliminar soporte consumido.
- Esperar control/confirmación real antes de aplicar. Referencia discrepante → inválida, aplicada cero; revalidar bajo savepoint para que una preparación fallida no deje saldo parcial. Aplicación/reversión actualiza crédito y reportes sin efectivo, IGTF adicional, cambio del IVA causado o originales HKA.

## Integración y recuperación

`HkaReceivedRetention` comparte reclamo/relectura/conciliación; conservar adaptadores/outboxes IVA e ISLR independientes. `HkaIslrRetentionPayload` usa tipo `01`, identidad congelada y `detallesRetencionISLR`; omitir `codigoOperacion` porque no hay equivalencia documentada con `concept_id`.

- POST/GET/DELETE `/api/AplicarRetencionISLR`, DEMO, JSON incluido GET/DELETE. Leer antes de mutar y después; timeout consulta antes de retransmitir. No llamar HTTP en transacciones comerciales.
- GET sólo verifica serie/tipo/número/control, comprobante, fecha e importe. No afirmar verificados remotamente concepto, sustraendo, RIF o porcentaje; publicar `verified_fields` preciso.
- Ausencia ISLR verificada en VES/USD: HTTP 200, código `204`, mensaje exacto «No se ha registrado aún ninguna Retención de ISLR a este documento», sin validaciones. Un 203 IVA no acredita ausencia ISLR. Respuestas incompletas → incierto; otro comprobante → conflicto, sin sobrescritura automática.
- Reclamo con UUID/intento bajo bloqueo, ventana de 90 s, tiempos HTTP 20/10/20 s, respuestas tardías protegidas y espera progresiva. Scheduler tenant `islr-retentions:recover`; rechazos definitivos requieren corrección. Publicar diagnósticos saneados, sin payload/JWT/respuestas crudas.
- Reversión exige permiso `delete_payment`/admin, motivo y UUID; conservar efecto local ante presencia posible. Sólo ausencia acreditada restituye saldo/libera reemplazo. Nunca transmitida puede revertirse localmente; un comprobante adoptado con cero intentos requiere conciliación remota.
- `config/islr_retention.php` inicia `demo_net_collection_verified=false`. Probar protocolo sobre controles cobrados y circuito con cobro neto separadamente; combinación IVA+ISLR requiere aceptación de ambas capacidades. No inventar formasPago. Ver resultados actuales en evidencia; aceptación parcial no habilita producción.

## Verificación

Usar `HkaIslrRetentionTest`, casos `test_islr_*` de `FiscalEmissionSchemaTest`, regresiones IVA/pagos, `TenantMigrationDataSeederTest`, instalación/seeding/FKs/rollback/repetición y `tests/js/iva-retention-ui.test.cjs`. Incluir coexistencia/restitución única/caja intacta, monedas/tasas distintas, fallo después de guardar, rollback exterior, exclusiones/perfiles, referencias/soporte, conflicto/concurrencia y aislamiento.

Aplicar [esquema inicial](../reconstruir-migraciones-tenant/SKILL.md), [conexión HKA](../conectar-api-hka/SKILL.md), [perfiles fiscales](../mantener-sujetos-pasivos-especiales-pro9/SKILL.md) y [seguridad](../mantener-seguridad-pro9/SKILL.md) según cambios. [frontend-build](../frontend-build/SKILL.md): compila el usuario; plantillas/pruebas no acreditan aceptación visual del bundle.
