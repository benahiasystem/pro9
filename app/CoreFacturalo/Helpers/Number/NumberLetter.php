<?php
namespace App\CoreFacturalo\Helpers\Number;

use App\Support\Exact\Decimal;

class NumberLetter
{
    private static $UNIDADES = [
        '',
        'un ',
        'dos ',
        'tres ',
        'cuatro ',
        'cinco ',
        'seis ',
        'siete ',
        'ocho ',
        'nueve ',
        'diez ',
        'once ',
        'doce ',
        'trece ',
        'catorce ',
        'quince ',
        'dieciseis ',
        'diecisiete ',
        'dieciocho ',
        'diecinueve ',
        'veinte '
    ];

    private static $DECENAS = [
        'veinti',
        'treinta ',
        'cuarenta ',
        'cincuenta ',
        'sesenta ',
        'setenta ',
        'ochenta ',
        'noventa ',
        'cien '
    ];

    private static $CENTENAS = [
        'ciento ',
        'doscientos ',
        'trescientos ',
        'cuatrocientos ',
        'quinientos ',
        'seiscientos ',
        'setecientos ',
        'ochocientos ',
        'novecientos '
    ];

    public static function convertToLetter($number, $currency = '', $format = false, $decimals = '')
    {
        try {
            if (Decimal::of($number)->isNegative()) {
                return 'No es posible convertir el numero en letras';
            }
            $rounded = Decimal::represent($number, 2, true);
        } catch (\Throwable $exception) {
            return 'No es posible convertir el numero en letras';
        }

        [$base_number, $decNumberStr] = explode('.', $rounded);
        if (strlen($base_number) > 18) {
            return 'No es posible convertir el numero en letras';
        }
        $converted = '';
        $groups = str_split(str_pad($base_number, 18, '0', STR_PAD_LEFT), 6);
        foreach (['billon', 'millon'] as $index => $name) {
            if ($groups[$index] === '000001') {
                $converted .= 'un ' . $name . ' ';
            } elseif ($groups[$index] !== '000000') {
                $converted .= rtrim(self::convertSixDigits($groups[$index])) . ' ' . $name . 'es ';
            }
        }
        $converted .= self::convertSixDigits($groups[2]);

        if ($base_number === '0') {
            $converted = 'Cero ';
        }

        if($format){
            $valor_convertido = Decimal::format($rounded, 2, ',', '.') . ' (' . ucfirst($converted) . $decNumberStr . '/100 '.$currency.')';
        }else{
            $valor_convertido = ucfirst($converted) . 'con ' . $decNumberStr . '/100 ' . $currency;
        }

        return $valor_convertido;
    }

    private static function convertSixDigits(string $digits): string
    {
        $thousands = substr($digits, 0, 3);
        $units = substr($digits, 3, 3);
        $text = '';
        if ($thousands === '001') {
            $text .= 'mil ';
        } elseif ($thousands !== '000') {
            $text .= self::convertGroup($thousands) . 'mil ';
        }
        if ($units === '001') {
            $text .= 'un ';
        } elseif ($units !== '000') {
            $text .= self::convertGroup($units) . ' ';
        }
        return $text;
    }

    private static function convertGroup($n)
    {
        $output = '';

        if ($n == '100') {
            $output = "cien ";
        } else if ($n[0] !== '0') {
            $output = self::$CENTENAS[$n[0] - 1];
        }

        $k = intval(substr($n,1));

        if ($k <= 20) {
            $output .= self::$UNIDADES[$k];
        } else {
            if(($k > 30) && ($n[2] !== '0')) {
                $output .= sprintf('%sy %s', self::$DECENAS[intval($n[1]) - 2], self::$UNIDADES[intval($n[2])]);
            } else {
                $output .= sprintf('%s%s', self::$DECENAS[intval($n[1]) - 2], self::$UNIDADES[intval($n[2])]);
            }
        }

        return $output;
    }
}
