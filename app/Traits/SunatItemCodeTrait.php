<?php

namespace App\Traits;

/**
 * Validación del código de producto SUNAT (items.item_code).
 *
 * Cuando se informa, debe tener exactamente 8 dígitos numéricos:
 * sin letras, sin espacios y sin caracteres especiales (guiones, puntos o barras).
 * Es opcional, por lo que un valor vacío se normaliza a null.
 */
trait SunatItemCodeTrait
{
    /**
     * @return string
     */
    public static function getSunatItemCodeMessage()
    {
        return 'El código SUNAT debe tener exactamente 8 dígitos numéricos, sin letras, espacios ni caracteres especiales.';
    }

    /**
     * Devuelve el código sin espacios alrededor, o null si viene vacío.
     *
     * @param  mixed $item_code
     * @return string|null
     */
    public static function cleanSunatItemCode($item_code)
    {
        $item_code = trim((string) $item_code);

        return ($item_code === '') ? null : $item_code;
    }

    /**
     * @param  mixed $item_code
     * @return bool
     */
    public static function isValidSunatItemCode($item_code)
    {
        return preg_match('/^[0-9]{8}$/', (string) $item_code) === 1;
    }

    /**
     * Reglas para el form request.
     *
     * @return array
     */
    protected function getSunatItemCodeRules()
    {
        return [
            'nullable',
            'digits:8',
        ];
    }

    /**
     * Mensaje para el form request.
     *
     * @return array
     */
    protected function getSunatItemCodeMessages()
    {
        return [
            'item_code.digits' => self::getSunatItemCodeMessage(),
        ];
    }

    /**
     * Normaliza item_code antes de validar, para no rechazar cadenas vacías
     * cuando el campo no se informa. Se usa desde prepareForValidation.
     *
     * @return void
     */
    protected function prepareSunatItemCode()
    {
        if ($this->has('item_code')) {
            $this->merge([
                'item_code' => self::cleanSunatItemCode($this->input('item_code')),
            ]);
        }
    }
}
