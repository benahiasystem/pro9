# Evidencia de la integración del 19 de octubre de 2026

Estado: implementación parcial; 36.306 candidatos pendientes. Ningún recorrido persistente nuevo se acredita como completo.

- `resultados.json` y `auditoria_resumen.json`: estado vigente, cobertura, pruebas y límites. Ambos auditores fallan por pendientes.
- `nuevas_clasificaciones.json`: 295 aprobaciones individuales nuevas; 279 son pruebas AST de presencia JavaScript, no errores monetarios corregidos. `revisiones_manuales.json` conserva la revisión histórica.
- `php_integral_antes_fixture.log/.xml`: ejecución real conjunta de 949 casos; las 948 pruebas Unit pasan y Feature falla por su base de desarrollo ausente.
- `php_feature_corregida.log/.xml`: repetición de la única prueba Feature tras aislar su consulta en memoria. Pasa con tres aserciones. No se repitió el resto de Unit, cuyos fuentes permanecen iguales.
- `javascript.log`: 229 pruebas finales sin fallos ni omisiones. Las regresiones de lectura/agrupación y HTTP tienen sus registros separados.
- `http_aceptacion.json/.log`: última repetición HTTP satisfactoria de selección de partidas. `http_json_presupuesto_fallo.json/.log` conserva un fallo de rendimiento sobre el mismo PHP; no se certifica estabilidad ni rendimiento global.
- `referencia.json`, fuentes y diferencias comprimidas: árbol inicial con cambios sin confirmar. `fuentes_ejecucion_integral.json` y `fuentes_aceptacion.json` distinguen la versión antes y después de corregir sólo el fixture Feature.
- `cambios_integracion.patch.gz` y `verificacion_artefactos.json`: 19 archivos reproducidos contra el árbol inicial, con hashes finales verificados; no es un parche contra HEAD.
- `nota_conversion_recorrido.json`: 14 referencias verificadas del recorrido de preparación y su guardado todavía pendiente.
- `limpieza_mysql_temporal.json`: no quedaron servidores de pruebas. Los contenedores existentes de la aplicación no se modificaron.

Los demás registros exploratorios o interrumpidos se conservan para trazabilidad y no sustituyen la evidencia vigente. Frontend compilado/aceptación visual y clientes externos permanecen separados.
