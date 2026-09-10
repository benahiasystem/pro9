<?php

return [

    /*
     * Ruta al binario mysqldump. No se confia en el PATH: el entorno del worker de
     * colas (supervisor/systemd) no es el mismo que el de una shell interactiva, y
     * en contenedores el binario suele estar fuera del PATH por defecto.
     */
    'mysqldump' => env('MYSQLDUMP_PATH', '/usr/bin/mysqldump'),

    /*
     * Banderas del dump.
     *
     * --single-transaction  consistencia en InnoDB sin lockear tablas (no frena al cliente)
     * --quick               no carga la tabla entera en memoria
     * --no-tablespaces      evita el error de privilegio PROCESS con cliente 8.0
     * --routines --events   sin esto el restore queda sin procedimientos ni triggers
     *
     * Las banderas que el binario instalado no reconozca se descartan solas: el
     * cliente de MariaDB y el de MySQL no comparten todo el juego de opciones
     * (--set-gtid-purged solo existe en MySQL). Ver GenerateClientBackup::supportedDumpOptions.
     */
    'dump_options' => [
        '--single-transaction',
        '--quick',
        '--no-tablespaces',
        '--routines',
        '--events',
        '--set-gtid-purged=OFF',
        '--default-character-set=utf8mb4',
    ],

    /*
     * Cola dedicada: un backup de 40 minutos no puede bloquear los correos de
     * comprobantes ni el resto de jobs del sistema.
     */
    'queue' => env('BACKUP_QUEUE', 'backups'),

    /*
     * Dias que se conservan los archivos antes de la limpieza automatica.
     */
    'retain_days' => env('BACKUP_RETAIN_DAYS', 30),

    /*
     * Espacio libre minimo en MB exigido antes de arrancar un dump. Llenar el disco
     * del servidor es peor que no tener el backup: se cae la aplicacion entera.
     */
    'min_free_space_mb' => env('BACKUP_MIN_FREE_SPACE_MB', 5120),

    /*
     * Horas tras las cuales un backup que sigue en curso se considera colgado.
     *
     * Si el worker muere a mitad del proceso la fila queda en IN_PROCESS para
     * siempre y el control de concurrencia no deja volver a encolar ese cliente.
     * Subir este valor si algun cliente tarda legitimamente mas que esto.
     */
    'stale_hours' => env('BACKUP_STALE_HOURS', 24),

    /*
     * Horas tras las cuales un zip parcial de un backup global se da por huerfano.
     * En condiciones normales AssembleGlobalBackup los borra al armar el zip final.
     */
    'orphan_partials_hours' => env('BACKUP_ORPHAN_PARTIALS_HOURS', 48),

];
