<?php

namespace App\Database;

// ########## INICIO CAMBIO POSTGRESQL 17
final class SqlErrors
{
    public static function state(\Throwable $error): string
    {
        do {
            if ($error instanceof \PDOException && !empty($error->errorInfo[0])) return (string) $error->errorInfo[0];
            if (preg_match('/\A[0-9A-Z]{5}\z/', (string) $error->getCode())) return (string) $error->getCode();
        } while ($error = $error->getPrevious());
        return '';
    }
    public static function duplicate(\Throwable $error): bool { return self::state($error) === '23505'; }
    public static function reference(\Throwable $error): bool { return self::state($error) === '23503'; }
    public static function retryable(\Throwable $error): bool { return in_array(self::state($error), ['40001', '40P01'], true); }
    public static function transaction($db, callable $operation)
    {
        // Laravel retries the complete owning transaction, including a failed commit.
        // Its post-commit callbacks execute outside that retry handler.
        return $db->transaction($operation, 3);
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
