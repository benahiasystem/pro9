# Instrucciones para Codex en Pro9

## Objetivo y contexto

Trabaja como colaborador de desarrollo en Pro9, una aplicación Laravel 9 y Vue 2 con módulos, Vite y arquitectura multitenant. Antes de cambiar una funcionalidad, revisa el código y las convenciones existentes en el área afectada. Las instrucciones específicas de un directorio y las indicaciones explícitas del usuario prevalecen cuando corresponda.

## Coordinación del equipo

El agente principal dirige el trabajo, mantiene la visión completa y es el único que modifica archivos. Para tareas medianas o grandes con partes independientes, inicia hasta tres subagentes simultáneos, según el límite disponible, para investigar y revisar en paralelo. Asigna a cada uno un objetivo delimitado, los archivos o área de interés y el resultado esperado. Si no hay capacidad o herramientas disponibles, continúa directamente e informa de la limitación cuando sea relevante.

Para cambios pequeños, tareas secuenciales o cuando la coordinación añada más tiempo que beneficio, trabaja directamente. No crees subagentes por obligación ni dividas artificialmente una tarea.

### Modelos y niveles de razonamiento

- **Principal y coordinador:** `gpt-6.1-sol`, razonamiento `max`.
- **Explorador:** `gpt-6.1-terra`, razonamiento `high`.
- **Implementador:** `gpt-6.1-sol`, razonamiento `max`; este rol lo ejerce el agente principal.
- **Revisor:** `gpt-6.1-terra`, razonamiento `high`.
- **Verificador:** `gpt-6.1-terra`, razonamiento `medium`.

Para delegar, selecciona los agentes personalizados `explorador`, `revisor` y `verificador` definidos en `.codex/agents/` para los roles correspondientes. Cada archivo fija su modelo y nivel de razonamiento. El rol de implementador pertenece al agente principal y no requiere un subagente. La configuración de proyecto `.codex/config.toml` establece el modelo y nivel predeterminados del principal; la selección explícita de una sesión puede prevalecer. Si un modelo o nivel no está disponible o una instrucción de mayor prioridad impide seleccionarlo, utiliza la mejor alternativa disponible e informa de la limitación.

### Roles

- **Principal y coordinador:** interpreta el objetivo, organiza el trabajo, evita duplicaciones, decide cómo integrar los hallazgos, implementa los cambios y entrega el resultado. También asume el rol de implementador.
- **Explorador:** investiga la arquitectura y el flujo afectado; identifica rutas, controladores, servicios, modelos, componentes, pruebas y convenciones pertinentes. Devuelve hallazgos y referencias a archivos.
- **Implementador:** es el agente principal. Diseña y aplica los cambios necesarios, respetando el alcance y el código existente. Los subagentes pueden proponer soluciones, pero no editar archivos.
- **Revisor:** examina el código y los cambios en busca de errores, regresiones, problemas de seguridad y mantenimiento. Prioriza hallazgos concretos con ubicación y consecuencias. Puede revisar el diseño mientras se implementan partes independientes y revisar el resultado final cuando esté listo.
- **Verificador:** identifica las pruebas adecuadas y, cuando sea seguro ejecutarlas en el entorno, las ejecuta y comunica comandos, resultados y limitaciones. No modifica código ni archivos del proyecto de forma intencional; coordina con el principal las pruebas que puedan alterar datos o generar artefactos.

Los subagentes son de investigación y revisión: no deben editar código, crear commits ni modificar configuración o datos del proyecto. El principal define sus tareas de modo que sus actividades no interfieran entre sí. Espera los resultados necesarios antes de cerrar el trabajo y evalúa sus recomendaciones; no las apliques sin revisarlas.

## Criterios para Pro9

- Sigue las versiones y patrones existentes de Laravel 9, Vue 2 y Vite; no introduzcas convenciones de versiones nuevas sin necesidad.
- Identifica si el cambio pertenece a la aplicación principal o a `modules/` y respeta la separación existente. Revisa las rutas, permisos, validaciones y consumidores relacionados cuando el cambio lo requiera.
- Considera el aislamiento entre tenants y las conexiones de base de datos. No asumas que una operación de datos afecta a un único entorno; evita usar datos reales o ejecutar operaciones destructivas para verificar.
- En funcionalidades de facturación, pagos, impuestos, moneda o redondeo, revisa los flujos y pruebas existentes antes de cambiar cálculos o persistencia. Preserva la precisión y el comportamiento esperado, y verifica los casos límite afectados.
- Para Vue, revisa el componente y sus consumidores, así como los patrones existentes de formularios, validación y comunicación con Laravel. Verifica la interfaz cuando el cambio visual o de interacción lo justifique y haya un entorno disponible.
- Conserva los cambios previos del usuario. No reviertas, sobrescribas ni limpies archivos ajenos a la tarea. Antes de ejecutar comandos que generan archivos, revisa sus efectos y el estado del árbol de trabajo.

## Verificación y entrega

Selecciona verificaciones proporcionales al cambio y a los riesgos. Utiliza las pruebas existentes cercanas al código afectado; añade o ajusta pruebas cuando aporten cobertura significativa. Según corresponda y cuando el entorno lo permita, ejecuta pruebas PHP con `php artisan test` o PHPUnit, pruebas JavaScript existentes con `node --test`, y `npm run build` para cambios que requieran verificar la compilación de Vite. Revisa primero la configuración y los efectos de cada comando: algunas pruebas pueden necesitar bases de datos o servicios, y la compilación puede modificar `public/build`.

No declares que una comprobación pasó si no se ejecutó. Si una prueba falla o no puede ejecutarse, comunica el motivo y el alcance de lo que sí se verificó. Al finalizar, explica de forma concisa qué cambió, por qué, cómo se verificó y qué riesgos o limitaciones relevantes quedan pendientes.
