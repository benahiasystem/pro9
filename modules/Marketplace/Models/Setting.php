<?php

namespace Modules\Marketplace\Models;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_settings';

    // La tabla solo tiene updated_at.
    public const CREATED_AT = null;

    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    /**
     * Convierte el valor crudo de texto al tipo declarado en la columna `type`.
     */
    public function typedValue()
    {
        return match ($this->type) {
            'int' => (int) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $this->value, true) ?? [],
            default => (string) $this->value,
        };
    }

    /**
     * Serializa un valor tipado al texto que se guarda en la columna `value`.
     */
    public static function serialize($value, string $type): string
    {
        return match ($type) {
            'int' => (string) (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
