<?php

namespace App\Services;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
/** Identificación de control de imprenta; independiente del número documental. */
final class FiscalControlNumber
{
    private string $identifier;
    private int $sequence;

    public function __construct(string $value)
    {
        if (!preg_match('/\A([0-9]{2})-([0-9]{1,8})\z/', $value, $parts) || (int) $parts[2] < 1) {
            throw new \InvalidArgumentException('El número de control requiere dos dígitos, guion y un secuencial positivo de hasta ocho dígitos.');
        }
        $this->identifier = $parts[1];
        $this->sequence = (int) $parts[2];
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function __toString(): string
    {
        return $this->identifier . '-' . str_pad((string) $this->sequence, 8, '0', STR_PAD_LEFT);
    }

    /** Orden canónico para detectar solapamientos sin perder el identificador. */
    public function ordinal(): int
    {
        return (int) $this->identifier * 100000000 + $this->sequence;
    }

    public function isWithin(self $start, self $end): bool
    {
        if ($start->ordinal() > $end->ordinal()) {
            throw new \InvalidArgumentException('El final del rango debe ser mayor o igual al inicio.');
        }
        return $this->ordinal() >= $start->ordinal() && $this->ordinal() <= $end->ordinal();
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
