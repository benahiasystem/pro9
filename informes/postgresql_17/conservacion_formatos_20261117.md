# Conservación de formatos — 17 de noviembre de 2026

La instrucción del usuario de no modificar los formatos de reportes prevalece sobre las propuestas de maquetación anteriores. La referencia actual es `develop`, revisión `d13c5dfa238bd5274340d236d015915be169bf69`. Las comprobaciones anteriores sobre `b7a57bb20fcb26081f36e9cce0a4a3300c1aaaba` se conservan como antecedentes, no como acreditación de la revisión actual.

Se recuperaron tablas, celdas, atributos, fuentes, tamaños y CSS originales de las 32 plantillas de facturas/notas que habían recibido ajustes de presentación. La comparación final verifica el HTML y CSS de las 65 plantillas con diferencias restantes. Las 12 plantillas por cobrar conservan únicamente guardas de relaciones geográficas ausentes. `createPdf` y `previewPdf` recuperaron los formatos, dimensiones, márgenes, altura y paginación de develop; sólo conservan la guarda de términos nulos. `PdfQuantities` y el exportador de caja son idénticos a develop. Se retiraron las filas adicionales de crédito pendiente en la hoja auxiliar XLSX.

Las sustituciones numéricas autorizadas permanecen en las celdas originales: snapshots y fracciones de facturación, porcentajes, cargos, descuentos y totales. No se añadieron requisitos de crédito ni pagos a los tres formatos de importación XLSX. El rendimiento POS continúa aplazado.

## Verificación ejecutada

- PostgreSQL 17.11 temporal: `pdf_formatos_originales_20261117_13`, 26 pruebas y 1.605 aserciones, sin fallos, errores ni omisiones. Se generaron 112 PDF ficticios, 126 páginas. Fuente fija y limpieza confirmadas en `pro9-pgtest-bb626b97ba63.json`.
- Se cotejaron 4.279 fuentes PHP con esa ejecución: conservan los mismos bytes. El renderizado y los dos revisores inspeccionaron los 23 contactos de los cuatro temas y ampliaron páginas de cantidades largas y documentos con dos páginas.
- Se ajustaron las pruebas que exigían un diseño nuevo: una sola página, filas propias, ancho nuevo de ticket50 y valores siempre en una línea. Continúan las comprobaciones de datos exactos, moneda, referencias, formato original, integridad del PDF e inmutabilidad de registros. Los valores partidos se verifican dentro de su columna y bloque, sin concatenar dígitos de columnas diferentes.
- `npm run build` autorizado en el worktree: ejecución `compilacion_worktree_20261117_03`, resultado 0 y fuentes sin cambios durante la compilación.
- Chrome compilado: `navegador_facturacion_20261117_17`, resultado `ok:true`, sin errores ni peticiones comerciales. Se corrigió un caso real donde Element UI omitía la edición explícita de cero sobre una fracción menor a doce decimales; foco/desenfoque sin edición conserva esa fracción.
- JavaScript: `javascript_facturacion_20261117_18`, 1063 pruebas aprobadas, sin fallos ni omisiones.

La revisión visual conserva las limitaciones observadas del formato original: algunos campos se dividen entre líneas y 14 comprobantes usan dos páginas. En el fixture sin logo se observó un icono de imagen ausente en algunos tickets de notas; existe el fallback original y la causa de resolución del recurso no se determinó. No se declara equivalencia píxel a píxel con develop para estos datos nuevos ni legibilidad completa desde las miniaturas. No se rediseñaron estos formatos.

La corrección de formatos está aplicada. El cierre general de PostgreSQL sigue abierto: las auditorías estrictas de facturación tienen 2442 candidatos PHP y 1977 JavaScript pendientes, con 381/153 diagnósticos de registro. No hay errores de análisis ni cobertura. Un candidato no equivale a un fallo financiero demostrado. La batería final completa posterior a develop todavía debe ejecutarse sobre fuente fija.

Evidencias: `informes/evidencias/postgresql_17_implementacion/resultados/conservacion_formatos_20261117_03.json`, los JUnit y manifiestos citados y `auditorias_facturacion_20261117_08-resumen.json`. Las evidencias anteriores se conservan como historia y no acreditan esta revisión completa.

## Reconfirmación del alcance solicitada por el usuario

No se autoriza modificar los formatos de los reportes. Se conservan los diseños de `develop`: columnas, filas, etiquetas, fuentes, márgenes, dimensiones y distribución. Las correcciones de exactitud se limitan a facturación y a los valores dentro de sus celdas existentes. No se amplía el alcance al rendimiento del POS ni se modifican los tres formatos XLSX de importación.

La comparación actual de 84 archivos Blade modificados está registrada en `formatos_develop_d13c5dfa_20261117_02.json`. Todas las plantillas documentales y vistas de reportes comparadas conservan las etiquetas, atributos y CSS; las cinco diferencias de etiquetas identificadas corresponden a vistas de Ecommerce, fuera de los formatos de reportes. Esta comprobación estática no demuestra identidad de condiciones Blade ni equivalencia visual píxel a píxel.

El ajuste posterior del resumen A5 sólo sustituye el lector de cantidades para evitar mostrar cero cuando existe una cantidad positiva menor a doce decimales. No cambia el HTML ni CSS. El logo de los fixtures se resolvió con una ruta local en la prueba, sin modificar la plantilla. La última generación temporal de PDF aprobó cuatro pruebas y 1.062 aserciones (`pdf_recursos_locales_20261117_02`); el renderizado `render_pdf_20261117_16` completó 112 PDF y 120 páginas. Estos resultados no equivalen al cierre de la batería final de PostgreSQL, que sigue pendiente.

La revisión paralela de sólo lectura también examinó controles Blade y 26 exportadores: no identificó columnas, filas ni etiquetas estáticas nuevas en los bloques revisados. Los exportadores inspeccionados sólo declaran propiedades por compatibilidad con PHP 8.2. Las condiciones basadas en importes exactos sí pueden mostrar una fila existente que antes se omitía; cifras más largas pueden afectar el salto de línea o la paginación. No se confunde esa variación con identidad visual garantizada ni se autoriza rediseñar para corregirla.
