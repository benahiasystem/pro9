<?php

namespace App\Support;

final class ConsoleRuntimeUser
{
    public static function apply(): void
    {
        $name = getenv('SAB_ARTISAN_USER');
        if ($name === false || $name === '') {
            return;
        }

        if (!function_exists('posix_geteuid')) {
            throw new \RuntimeException('Se requiere POSIX para configurar el usuario de Artisan.');
        }
        $user = posix_getpwnam($name);
        if (!$user) {
            throw new \RuntimeException('No existe el usuario configurado para Artisan.');
        }
        if (posix_geteuid() === $user['uid']) {
            return;
        }
        if (posix_geteuid() !== 0) {
            throw new \RuntimeException('Artisan debe ejecutarse como el usuario configurado.');
        }

        if (!posix_initgroups($name, $user['gid'])
            || !posix_setgid($user['gid']) || !posix_setuid($user['uid'])
            || posix_geteuid() !== $user['uid']) {
            throw new \RuntimeException('No se pudo cambiar al usuario configurado para Artisan.');
        }
    }
}
