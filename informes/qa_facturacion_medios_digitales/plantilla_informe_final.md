# Informe final QA: Pro9 Medios digitales

> Plantilla sin ejecutar. Sustituir todos los campos entre corchetes. No conservar cifras de ejemplo como resultados reales.

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
| [grupo] | [T] | [A] | [R] | [B] | [N] | [P] | [texto] |

## 5. Resultados detallados de cada prueba

Completar una fila por ID y por variante ejecutada. Adjuntar registro actualizado. Las filas de resultado no pueden limitarse a “funciona”: incluyen esperado y observado comprobables.

| Caso / variante / iteración | Tipo, canal, rol y contexto | Estado | Resultado esperado | Resultado observado | Documento/UUID/intento | Evidencia | Defecto/bloqueo | Fecha/ejecutor |
|---|---|---|---|---|---|---|---|---|
| [ID] | [contexto] | [Aprobó/Reprobó/Bloqueada/No aplica/Pendiente] | [criterios] | [datos efectivos] | [identificadores QA] | [ruta/enlace] | [ID o motivo] | [dato] |

Para Reprobó, incluir reproducción íntegra en sección6. Para Bloqueada/No aplica, justificar en sección8. Una variante faltante impide Aprobó del caso padre.

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
| [ID] | [dato] | [identidad QA] | [dato] | [dato] | [dato] | [distinguir estados; receptorQA sin secretos] | [ruta] |

Si no hubo ejecuciones reales, indicar **No ejecutado: sólo proveedor simulado** y su causa. No copiar números de los informes históricos como pruebas de esta corrida. No acreditar notas/producción por una factura DEMO.

## 12. Retest y recomendaciones

| Defecto/caso | Iteración anterior | Versión corregida | Resultado retest | Regresión ejecutada | Evidencia/pendiente |
|---|---|---|---|---|---|
| [ID] | [Reprobó y fecha] | [commit] | [resultado] | [casos] | [ruta] |

[Priorizar acciones concretas por impacto. No pedir cambios generales sin relacionarlos con un fallo o límite verificado.]

## 13. Cierre y archivos entregados

[Repetir conclusión con alcance preciso y las condiciones que faltan. Confirmar que conteos y veredictos están reconciliados.]

- Registro actualizado: [ruta].
- Casos/subcasos adicionales: [ruta].
- Evidencias saneadas: [índice/carpeta].
- Cálculos independientes: [ruta].
- Defectos y retest: [ruta].
- Estado final de datos/configuracionesQA: [restaurado/conservado y motivo].

**No declarar aprobado un sistema con P0 fallido/bloqueado/pendiente, defectos críticos/altos abiertos o cobertura esencial sin demostrar. Entregar el informe también si el resultado es Inconcluso.**

