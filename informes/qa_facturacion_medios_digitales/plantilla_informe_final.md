# Informe final QA: Pro 9 Medios digitales

> Referencia `76690125` y cambios locales de órdenes identificados por hashes, 14 de octubre de 2026: 1702 casos/41 grupos; 1121 anteriores conservados +581 nuevos. Plantilla sin ejecutar. Sustituir todos los campos entre corchetes. No conservar cifras de ejemplo como resultados reales.

## 1. Identificación y conclusión

- Corrida: [QA-DIG-AAAA-MM-DD-NN]. Ejecutor: [agente]. Inicio/fin: [hora America/Caracas].
- Resultado global: [Aprobado en alcance ejecutado / Reprobado / Inconcluso].
- Versión: [commit/branch/cambios locales]. Assets: [manifest/hash/fecha del paquete realmente servido].
- URL y tenant QA: [datos]. Modalidad: **Medios digitales**. Ambiente: [DEMO verificable].
- Proveedor y capa: [doble / HTTP integral / navegador / Real DEMO; enumerar ejecuciones reales].

[Explicar en un párrafo qué funciona, los principales fallos y su impacto, y qué cobertura falta. La conclusión debe derivarse de los resultados, no del número de pruebas preparadas.]

| Área | Conclusión | Evidencia y limitación |
|---|---|---|
| Registro comercial y numeración | [resultado] | [casos/evidencia] |
| IVA, totales y tasas | [resultado] | [oráculos/diferencias] |
| Cobros, IGTF, retenciones y fondos | [resultado] | [conciliación] |
| Facturas ordinarias HKA DEMO | [resultado] | [control/consulta/alcance real] |
| Exportaciones FOB/CIF/EXW | [resultado por INCOTERM/moneda] | [restricciones y crédito conocido] |
| Ventas por cuenta de terceros | [resultado] | [contado completo/titular/adaptación] |
| NC/ND e IGTF posterior locales | [resultado] | [guardado/preparación; no presumir emisión remota] |
| Anticipos y conversiones | [resultado] | [saldos/stock/vínculos] |
| PDF, ticket QR e impresión | [resultado] | [hash/páginas/QR; físico si realizado] |
| Correo y WhatsApp | [resultado] | [solicitud vs entrega; receptor QA/doble] |
| Canales, UI/API e importaciones | [resultado] | [módulos y assets efectivamente probados] |
| Aislamiento y permisos | [resultado] | [tenant/sucursal/roles/rutas] |
| Regímenes especiales | [resultado de 28 combinaciones y restricciones] | [RGE/UUID/ticket/original] |
| Perfiles SPE | [resultado por versión/fecha/rol] | [naturaleza/facultades/IGTF] |
| IVA recibida | [previsión/aplicación/HKA/presentación] | [flag false/control/soporte/saldo] |
| ISLR recibida | [declaración/aplicación/HKA/campos verificados] | [flag false/204/independencia] |
| Coexistencia IVA+ISLR | [resultado por canal/moneda] | [deuda 15/abonos/reversiones] |

## 2. Alcance ejecutado y exclusiones

[Enumerar los tipos, operaciones, monedas, afectaciones, condiciones de pago, motivos de notas, canales, roles, sucursales y estados fiscales efectivamente probados.]

| Exclusión / límite | Motivo contractual o decisión de alcance | Casos y evidencia | Efecto en conclusión |
|---|---|---|---|
| Máquina fiscal / Forma libre | Excluidas por selección del usuario | [referencia] | Fuera de ejecución positiva |
| Producción digital | Transporte actual fuera de alcance; sólo rechazo simulado si ejecutado | [caso] | No acreditar producción |
| [otra exclusión real] | [fundamento verificable] | [evidencia] | [impacto] |

No excluir un módulo existente por estar deshabilitado sin registrar esa cobertura como Bloqueada o una exclusión sustentada. Diferenciar “no soportado por el contrato” de “soportado pero no probado”.

## 3. Entorno y preparación

| Dato | Valor registrado |
|---|---|
| Commit, rama, cambios locales | [valor] |
| PHP/Laravel/navegador/resolución | [valor] |
| Assets cargados | [hash y correspondencia con fuente] |
| Conexiones central y tenant QA | [identificación sin secretos] |
| QA-A / QA-B y S1 / S2 | [datos de aislamiento] |
| Usuarios y permisos | [matriz de roles efectiva] |
| Cajas/bancos/saldos iniciales | [moneda/importe/destino] |
| Tasa documental/de pago y configuración IVA/IGTF | [valores/fuente/fecha] |
| Proveedor, correo, mensajería, pasarela e impresión | [doble/sandbox/QA real] |
| Fixtures y estado restaurado al terminar | [rutas/identificadores] |

## 4. Resumen de resultados reconciliado

| Estado | Cantidad |
|---|---:|
| Total planificado T | [T] |
| Aprobó A | [A] |
| Reprobó R | [R] |
| Bloqueada B | [B] |
| No aplica N | [N] |
| Pendiente P | [P] |
| Ejecutadas A+R | [cantidad] |
| Aplicables T−N | [cantidad] |
| Cobertura (A+R)/(T−N) | [% o No calculable] |
| Tasa de aprobación A/(A+R) | [% o No calculable] |

Verificación: [T = A+R+B+N+P]. Nivel de conteo: [casos padre / subcasos]. Si hay subcasos, mostrar ambos totales por separado. No sumar casos padre con sus variantes como si fueran ejecuciones independientes del mismo nivel.

| Grupo funcional | Planificadas | Aprobó | Reprobó | Bloqueada | No aplica | Pendiente | Limitación principal |
|---|---:|---:|---:|---:|---:|---:|---|
| RGE: 59 planificadas | [T] | [A] | [R] | [B] | [N] | [P] | [limitación] |
| SPE: 64 planificadas | [T] | [A] | [R] | [B] | [N] | [P] | [limitación] |
| RIVA: 63 planificadas | [T] | [A] | [R] | [B] | [N] | [P] | [limitación] |
| RISLR: 135 planificadas | [T] | [A] | [R] | [B] | [N] | [P] | [limitación] |
| INT: 80 planificadas | [T] | [A] | [R] | [B] | [N] | [P] | [limitación] |
| [grupo] | [T] | [A] | [R] | [B] | [N] | [P] | [texto] |

## 5. Resultados detallados de cada prueba

Completar una fila por ID y por variante ejecutada. Adjuntar registro actualizado. Las filas de resultado no pueden limitarse a “funciona”: incluyen esperado y observado comprobables.

| Caso / variante / iteración | Tipo, canal, rol y contexto | Estado | Resultado esperado | Resultado observado | Documento/UUID/intento | Evidencia | Defecto/bloqueo | Fecha/ejecutor |
|---|---|---|---|---|---|---|---|---|
| [ID] | [contexto] | [Aprobó/Reprobó/Bloqueada/No aplica/Pendiente] | [criterios] | [datos efectivos] | [identificadores QA] | [ruta/enlace] | [ID o motivo] | [dato] |

Para Reprobó, incluir reproducción íntegra en sección 6. Para Bloqueada/No aplica, justificar en sección 8. Una variante faltante impide Aprobó del caso padre.

## 6. Defectos reproducibles

### BUG-QA-[NNN]: [título concreto]

- Severidad: [Crítica/Alta/Media/Baja]. Prioridad: [P0/P1/P2]. Estado: [Abierto/Corregido/Retest fallido/Cerrado].
- Casos/variantes: [IDs]. Versión/capa/tenant/usuario: [datos].
- Precondiciones y datos exactos: [fixture/referencia].
- Reproducción:
   1. [acción].
   2. [acción].
   3. [acción].
- Esperado: [criterio y cálculo]. Observado: [valor/estado/efecto].
- Impacto: [venta/saldo/caja/stock/fiscal/permiso/usuario]. Frecuencia: [dato observado].
- Evidencia: [request/response/captura/lectura antes-después].
- Alternativa operativa si existe: [sin inventarla].
- Retest: [commit/iteración/resultado/evidencia; conservar fallo original].

Repetir para cada defecto. Agrupar un fallo raíz compartido sin perder los IDs afectados. No agrupar problemas diferentes sólo porque ocurran en la misma pantalla.

## 7. Conciliación independiente de dinero e inventario

| Documento/pago | Moneda y tasa | Base/IVA/OTI/IGTF/total esperados | Aplicado/recibido/vuelto | Retenciones/fondo | Saldo esperado/observado | Caja/banco esperado/observado | Stock/lote/serial antes-después | Diferencia y caso |
|---|---|---|---|---|---|---|---|---|
| [identidad] | [dato] | [importes] | [importes] | [importes] | [importes] | [importes] | [cantidades] | [diferencia] |

[Explicar dirección de conversiones, redondeo y tolerancias específicas. Separar monedas. Aclarar recibos derivados y pagos revertidos. Registrar los cálculos sin tomar el total del sistema como oráculo.]

## 8. Bloqueos, no aplicables y límites conocidos

| Caso/variante | Clasificación | Motivo concreto | Evidencia | Dependencia o acción para completar | Impacto en cobertura |
|---|---|---|---|---|---|
| [ID] | [Bloqueada/No aplica/Límite conocido] | [motivo] | [ruta] | [acción] | [texto] |

[Enumerar assets pendientes, módulos no habilitados, falta de entorno aislado, credenciales DEMO, receptores QA, referencias/control o capacidad aún no implementada. No escribir “todo probado” si cualquiera sigue pendiente.]

## 9. Cobertura de opciones y matrices

- Inventario: [ruta actualizada]. Total de opciones efectivas: [cantidad]. Opciones mapeadas a casos: [cantidad]. Opciones ejecutadas con evidencia: [cantidad]. Opciones sin ejecutar: [lista].
- Identidades×canales × tipos: [208 casos base; ejecutados/aprobados/fallidos/bloqueados/no aplicables; resultados remotos por separado].
- Moneda×IVA×condición: [18 Factura +12 NC/ND; cobertura].
- Exportación: [FOB/CIF/EXW×VES/USD, restricciones/crédito].
- Terceros, estados×acciones, roles×rutas: [cobertura y vacíos].
- Extensiones QA-EXT y subcasos: [lista y conteos sin duplicar padres].

## 10. Suites automatizadas y capa de evidencia

| Suite/comando | Versión/entorno | Resultado | Casos apoyados | Qué no acredita |
|---|---|---|---|---|
| [comando real] | [datos] | [resultado y log] | [IDs] | [HTTP/navegador/DEMO/físico que no se probó] |

## 11. Registro de emisiones y distribución DEMO reales

| Documento de la corrida | Categoría/moneda | UUID/serie/número originales | Estado/resultado HKA | Control y consulta atribuibles | A4 hash / A5 páginas / ticket QR | Correo solicitado/entregado | Evidencia |
|---|---|---|---|---|---|---|---|
| [ID] | [dato] | [identidad QA] | [dato] | [dato] | [dato] | [distinguir estados; receptor QA sin secretos] | [ruta] |

Si no hubo ejecuciones reales, indicar **No ejecutado: sólo proveedor simulado** y su causa. No copiar números de los informes históricos como pruebas de esta corrida. No acreditar notas/producción por una factura DEMO.

## 12. Retest y recomendaciones

| Defecto/caso | Iteración anterior | Versión corregida | Resultado retest | Regresión ejecutada | Evidencia/pendiente |
|---|---|---|---|---|---|
| [ID] | [Reprobó y fecha] | [commit] | [resultado] | [casos] | [ruta] |

[Priorizar acciones concretas por impacto. No pedir cambios generales sin relacionarlos con un fallo o límite verificado.]

## 13. Regímenes especiales: resultados independientes

Completar 28 filas de matriz RGE-001..028 y el resto de casos RGE, sin agrupar resultados. Registrar guardar/editar/enviar/presentar por capa; todos los valores y monedas tienen conclusión propia.

| Caso/variante | Régimen ID/descripcion | VES/USD y propia/terceros | Base/IVA/exento/desc/cargo/IGTF/total/pago/saldo | UUID antes/después y edición | Registro comercial | HKA/consulta/control | Ticket/snapshot/original hash y omisiones | Veredicto/evidencia/defecto |
|---|---|---|---|---|---|---|---|---|
| [ID] | [dato exacto] | [contexto] | [esperado/observado] | [dato] | [resultado] | [resultado/capa] | [dato] | [Aprobó/Reprobó/etc.] |

Conclusión RGE: [matriz ejecutada/faltante, restricciones crédito/exportación/notas/canales, UUID y envío explícito, límites de PDF original].

## 14. SPE: versiones, facultades y clasificación IGTF

| Caso/variante | Empresa/comprador yRIF/cédula separados | Naturaleza/firma/clase pública | version_id/vigencia/notificación/revocación | Fundamento IVA/ISLR independientes | Fecha de factura/pago/comprobante | Decisión IGTF y moneda recibida/intermediación/excepción admin | Snapshot/payload/ticket/original | Veredicto/evidencia |
|---|---|---|---|---|---|---|---|---|
| [ID] | [identidad QA] | [datos] | [historial] | [datos] | [fechas/versión] | [esperado/observado] | [datos/hash] | [estado/ruta] |

Detallar 12 combinaciones IGTF, seis clases públicas, permisos y aislamiento, casos 100 %/evidencia y preservación histórica. Si se probó sólo comprador SPE con emisor FORMAL DEMO, indicarlo; no acreditar emisor SPE desde un doble o esa cuenta.

## 15. IVA recibida: cuatro capas y ciclo financiero

| Caso/variante | Factura/control y comprobante/número/fecha/RIF | Porcentaje/fundamento/soporte privado | IVA/base imponible/tasa/cap fiscal VES | Previsión y saldo antes | Estado local/ applied_amount y saldo después | Operación HKA/UUID/intento/estado/GET previo-posterior | Reversión/ausencia/recuperación 90 s | Presentación/diagnóstico/ can_send | Veredicto/evidencia/defecto |
|---|---|---|---|---|---|---|---|---|---|
| [ID] | [datos QA] | [75/100 y causa] | [esperado/observado] | [importes] | [datos] | [datos/contadores] | [firma 203 o 200 null] | [datos] | [estado/ruta] |

Describir uno activo por impuesto, reemplazos/historial, alícuotas mixtas, límites, conflictos, adopción sin POST y reversión incierta sin liberar saldo. Registrar flag false y bloqueos netos VES/USD con diagnóstico/ can_send =false. Controles protocolarios pagados y evidencias históricas figuran aparte; no acreditan emisión neta real.

## 16. ISLR recibida: declaración y límites de verificación remota

| Caso/variante/concepto | Fundamento independiente y evidencia | Número/fecha/RIF/soporte | Base/porcentaje/sustraendo/importe/tasa/cap neto fiscal | Previsión/aplicación/saldo | Operación ISLR/UUID/intento/estado | verified_fields realmente comparados | Ausencia 204/200 null, recuperación y reversión | Capacidad false y diagnóstico | Veredicto/evidencia |
|---|---|---|---|---|---|---|---|---|---|
| [ID/concepto] | [datos] | [datos QA] | [esperado/observado] | [datos] | [outbox propia] | [sólo 7 campos del contrato] | [prueba propia] | [ can_send false] | [estado/ruta] |

Reportar 85 conceptos no salariales iniciales por ID, exclusión 001, ausencia de mapping codigoOperacion y de tasas/sustraendo automáticos. Precisar qué GET verificó:serie, tipoDocumento, numeroDocumento, numeroControl, numeroCompRetencion, fecha Emision CR, impuestoRetenido; no afirmar RIF/porcentaje/concepto/sustraendo verificados remotos. La aceptación histórica parcial VES no habilita capacidad; USD/coexistencia tienen resultados independientes.

## 17. Coexistencia, abonos y regresión por canal

| Caso/variante/canal | Perfil/fechas/versiones | Total/pagos reales y monedas | IVA prevista/aplicada | ISLR prevista/aplicada | Sugerencia neta/deuda esperada-observada | Reabrir Pagos/borrador y UUID s conservados | HKA IVA/ISLR independientes | Caja/CxC/reportes/stock/aislamiento | Veredicto/evidencia |
|---|---|---|---|---|---|---|---|---|---|
| [ID] | [datos] | [importes] | [datos/estado] | [datos/estado] | [valores] | [antes/después] | [estados/bloqueos] | [conciliación] | [estado/ruta] |

Reconciliar ejemplo 116−previsión 12−previsión 3=sugerencia 101, pago 101/deuda 15 antes de validar; sólo IVA aplicada→deuda 3, sólo ISLR→deuda 12, ambas→0. Cash 101 inmutable. Incluir casos crédito/abonos 30+20, remanente 51 por acción explícita, monedas propias, conversiones, respuestas propuestas obsoletas, concurrencia y dos entornos tenant/sucursal. Registrar todos los 39 casos canal×impuesto y cualquier módulo bloqueado con causa, más los saldos posteriores a reversión.

Separar las cuatro conclusiones: registro comercial, aplicación local, sincronización HKA y presentación. Evidencia histórica/simulada debe figurar en apartado de soporte con capa explícita; no en tabla de emisiones reales de la corrida. No cerrar Aprobó si falta variante aplicable o caso P0.

## 18. Notas ordinarias y emisión HKA: resultados de NHD

| Caso / variante | Contexto y fixture | Comercial / saldo | Cobros / egresos / ajustes | Stock / lote / serial | Emisión / control / intento | Distribución / relación | Esperado / observado | Estado / evidencia / defecto | Agente / fecha / iteración |
|---|---|---|---|---|---|---|---|---|---|
| [ID] | [datos concretos] | [antes/después] | [moneda/tasa/UUID] | [antes/después] | [estado/identidad] | [capas] | [cálculo independiente] | [veredicto/rutas] | [datos] |

Reconciliar las 224 combinaciones sin cobro propio y 96NDalcontado por ID. Mostrar motivos/tipo/transacción, moneda, tasa de nota/referencia, tercero/régimen, derechos pendientes y centavos. FormasPago 99 no son cash. Presentar aceptación Real DEMO actual y evidencia de A4/A5/ticket/correo por separado; no importar 120 casos históricos como resultados.

## 19. Devoluciones de dinero y ajustes: resultados de NRF

| Caso / variante | Contexto y fixture | Comercial / saldo | Cobros / egresos / ajustes | Stock / lote / serial | Emisión / control / intento | Distribución / relación | Esperado / observado | Estado / evidencia / defecto | Agente / fecha / iteración |
|---|---|---|---|---|---|---|---|---|---|
| [ID] | [datos concretos] | [antes/después] | [moneda/tasa/UUID] | [antes/después] | [estado/identidad] | [capas] | [cálculo independiente] | [veredicto/rutas] | [datos] |

Detallar origenreal/límites/moneda/tasa/destino y egresos activos/revertidos; no convertir NC en salida automática. Informar ajustesIVA/ISLR, reemplazos con soporte, uncertain que conserva aplicación, fondo ajustado e IGTF separado/bloqueado; conciliar saldos y cash.

## 20. Anulación de notas: resultados de NAN

| Caso / variante | Contexto y fixture | Comercial / saldo | Cobros / egresos / ajustes | Stock / lote / serial | Emisión / control / intento | Distribución / relación | Esperado / observado | Estado / evidencia / defecto | Agente / fecha / iteración |
|---|---|---|---|---|---|---|---|---|---|
| [ID] | [datos concretos] | [antes/después] | [moneda/tasa/UUID] | [antes/después] | [estado/identidad] | [capas] | [cálculo independiente] | [veredicto/rutas] | [datos] |

Distinguir NC01 de anulación propia. Mostrar flujo local/remoto, dependencias, journal/UUID/control congelados, estados y reservas hasta confirmación. Documentar 201 vacío, EstadoDocumento Anulada, reversión única y casosremote_confirmed/comercialpendiente completa dos sin otro Anular.

## 21. Órdenes de entrega: guías de despacho: resultados de ODE

| Caso / variante | Contexto y fixture | Comercial / saldo | Cobros / egresos / ajustes | Stock / lote / serial | Emisión / control / intento | Distribución / relación | Esperado / observado | Estado / evidencia / defecto | Agente / fecha / iteración |
|---|---|---|---|---|---|---|---|---|---|
| [ID] | [datos concretos] | [antes/después] | [moneda/tasa/UUID] | [antes/después] | [estado/identidad] | [capas] | [cálculo independiente] | [veredicto/rutas] | [datos] |

Mostrar 40 combinaciones y controles, siempre 09→04/indicador1. Señalar flagstock/valorización, datos autorizados, VES, transporte/motivo/fechas/peso y emisión después commit. Reportar límites del originalA4 sinretocar y excluir indicador0/correo/anulaciónHKAorden.

## 22. Inventario, conversiones y relaciones: resultados de ODI

| Caso / variante | Contexto y fixture | Comercial / saldo | Cobros / egresos / ajustes | Stock / lote / serial | Emisión / control / intento | Distribución / relación | Esperado / observado | Estado / evidencia / defecto | Agente / fecha / iteración |
|---|---|---|---|---|---|---|---|---|---|
| [ID] | [datos concretos] | [antes/después] | [moneda/tasa/UUID] | [antes/después] | [estado/identidad] | [capas] | [cálculo independiente] | [veredicto/rutas] | [datos] |

Conciliar Kardex/almacén/lotes/seriales antes después y conversiones individuales/múltiples:yatrasladado/nuevopendiente. Reportar referencias locales frente fiscales, facturaGuia, consulta-POST-consulta, ambas direcciones, ausencia válida, timeout/concurrencia y guards de trazabilidad.

## 23. Integración y regresión de notas y órdenes: resultados de NRI

| Caso / variante | Contexto y fixture | Comercial / saldo | Cobros / egresos / ajustes | Stock / lote / serial | Emisión / control / intento | Distribución / relación | Esperado / observado | Estado / evidencia / defecto | Agente / fecha / iteración |
|---|---|---|---|---|---|---|---|---|---|
| [ID] | [datos concretos] | [antes/después] | [moneda/tasa/UUID] | [antes/después] | [estado/identidad] | [capas] | [cálculo independiente] | [veredicto/rutas] | [datos] |

Comparar modeloSQL/listado/pagos/cxc/dashboard/reportes/exportaciones por moneda. Confirmar netoIVA/ISLRsiguenfalse, aislamiento tenant/sucursal/permisos, respuestasobsoletas/configuración y esquemalegado. FallosPDF/correono degradanconfirmed.

## 24. Cierre y archivos entregados

[Repetir conclusión con alcance preciso y las condiciones que faltan. Confirmar que conteos y veredictos están reconciliados.]

- Registro actualizado: [ruta].
- Casos/subcasos adicionales: [ruta].
- Evidencias saneadas: [índice/carpeta].
- Cálculos independientes: [ruta].
- Defectos y retest: [ruta].
- Estado final de datos/configuraciones QA: [restaurado/conservado y motivo].

**No declarar aprobado un sistema con P0 fallido/bloqueado/pendiente, defectos críticos/altos abiertos o cobertura esencial sin demostrar. Entregar el informe también si el resultado es Inconcluso.**


Catálogo de esta entrega: **1702 casos / 41 grupos**, con 581 nuevos Pendiente. Mantener casos y variantes separados. Justificar Bloqueada/No aplica; informe final obligatorio aunque sea Inconcluso. Producción, indicador 0, anulación remota facturas, correo/anulaciónHKAórdenes y nueva API pública fuera de positivas.
