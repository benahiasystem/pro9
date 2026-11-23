<?php

namespace Tests\Unit;

use App\CoreFacturalo\Helpers\Number\NumberLetter;
use PHPUnit\Framework\TestCase;

class NumberLetterExactTest extends TestCase
{
    public function test_large_amounts_and_cent_rounding_are_rendered_without_floats(): void
    {
        $cases = [
            '0.00' => 'Cero con 00/100 ',
            '1.00' => 'Un con 00/100 ',
            '999999999.01' => 'Novecientos noventa y nueve millones novecientos noventa y nueve mil novecientos noventa y nueve  con 01/100 ',
            '999999999.99' => 'Novecientos noventa y nueve millones novecientos noventa y nueve mil novecientos noventa y nueve  con 99/100 ',
            '1000000000.00' => 'Mil millones con 00/100 ',
            '1000000000000.01' => 'Un billon con 01/100 ',
            '2000000000000.01' => 'Dos billones con 01/100 ',
            '1000000000000000.00' => 'Mil billones con 00/100 ',
            '9007199254740993.01' => 'Nueve mil siete billones ciento noventa y nueve mil doscientos cincuenta y cuatro millones setecientos cuarenta mil novecientos noventa y tres  con 01/100 ',
            '999999999999999999.99' => 'Novecientos noventa y nueve mil novecientos noventa y nueve billones novecientos noventa y nueve mil novecientos noventa y nueve millones novecientos noventa y nueve mil novecientos noventa y nueve  con 99/100 ',
            '1.995' => 'Dos  con 00/100 ',
        ];
        foreach ($cases as $number => $expected) {
            self::assertSame($expected, NumberLetter::convertToLetter($number), $number);
        }

        self::assertSame('9.007.199.254.740.993,01 (Nueve mil siete billones ciento noventa y nueve mil doscientos cincuenta y cuatro millones setecientos cuarenta mil novecientos noventa y tres  01/100 USD)', NumberLetter::convertToLetter('9007199254740993.01', 'USD', true));
        self::assertSame('Un con 00/100 ', NumberLetter::convertToLetter(['n'=>'2512499999999', 'd'=>'2500000000000']));
        self::assertSame('Un con 01/100 ', NumberLetter::convertToLetter('1.005'));
        self::assertStringEndsWith('con 99/100 ', NumberLetter::convertToLetter('999999999999999999.994'));
        self::assertSame('No es posible convertir el numero en letras', NumberLetter::convertToLetter('999999999999999999.995'));
        self::assertSame('No es posible convertir el numero en letras', NumberLetter::convertToLetter('-0.001'));
    }
}
