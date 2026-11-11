# Módulo contable de PRO9

El usuario exige que esta API forme parte de PRO9. No crear otra aplicación
Laravel, servidor de API, tabla de usuarios ni autenticación paralela.

- Namespace: `Modules\Accounting`; rutas: `/api/accounting/v1`.
- Identidad: `App\Models\Tenant\User`, guard existente `auth:api` y Bearer de
  `/api/login`. La verificación de operaciones críticas es adicional al login.
- Persistencia: conexión `tenant` de PRO9; tablas propias `accounting_*`.
  Cada libro se delimita por organización y empresa, con claves compuestas.
- Compatibilidad actual: Laravel 9, PHP 8.2 y MySQL 5.7 de PRO9. No actualizar
  las dependencias globales como consecuencia de añadir este módulo.
- Trabajo autorizado en `codex/contabilidad-api-v1`, worktree aislado de la
  rama de origen. No cambiar, limpiar ni guardar los cambios ajenos del original.
- Todas las pruebas deben usar bases, red, caché y almacenamiento aislados.
  Los datos reales de PRO9 se prueban solo en una copia privada en esta PC.
  No subir datos reales, claves ni credenciales a AWS.
- Frontend: TypeScript, Vue, Router, Pinia, Axios, Bulma/SCSS, Workbox,
  versiones estables actuales. Editarlo y compilarlo en AWS, proyecto
  `/home/ubuntu/contabilidad/application/web`; descargar el compilado a esta PC.
- La PWA ocupa `/contabilidad/`; su service worker no debe controlar PRO9.
- Estilo aprobado: Índigo preciso. Datos ficticios en AWS. Sin conexión solo
  consultas autorizadas y borradores; operaciones definitivas requieren servidor.

El prototipo independiente `application/api` de AWS quedó sustituido por esta
decisión explícita del usuario. Sus pruebas no validan este módulo.
