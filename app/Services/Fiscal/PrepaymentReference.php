<?php
namespace App\Services\Fiscal;

use App\Support\Exact\Decimal as D;

/** Decimal transport and original fractions for an advance reference. */
final class PrepaymentReference
{
    public static function available(\App\Models\Tenant\Document $document, ?string $description=null): array
    {
        $total=$document->exactValue('pending_amount_prepayment');
        $amount=(string)$document->affectation_type_prepayment === '10'
            ? $total->dividedBy(D::of(\App\Support\Venezuela\Localization::exactTaxPercentage())->dividedBy(100)->plus(1))
            : $total;
        return ['id'=>$document->id,'description'=>$description ?? $document->number_full,
            'series'=>$document->series,'number'=>$document->number,
            'document_type_id'=>$document->document_type_id === '01' ? '02' : '03',
            'amount'=>D::represent($amount),'total'=>D::represent($total),
            'calculation_snapshot'=>D::snapshot(['amount'=>$amount,'total'=>$total])];
    }

    public static function normalize(array $row): array
    {
        $values=[];
        foreach (['amount','total'] as $field) {
            $scalar=D::of(D::input($row[$field] ?? null,'prepayments.'.$field));
            try {
                $original=D::readRequired($row,$field);
            } catch (\UnexpectedValueException | \JsonException | \Illuminate\Validation\ValidationException $error) {
                FiscalAmounts::error('prepayments.'.$field,'La fracción original del anticipo no es válida.');
            }
            if (!$scalar->isEqualTo(D::represent($original))) {
                FiscalAmounts::error('prepayments.'.$field,'El importe no coincide con su fracción original.');
            }
            if ($original->isNegative()) FiscalAmounts::error('prepayments.'.$field,'El importe del anticipo no puede ser negativo.');
            $values[$field]=$original;
        }
        return ['number'=>$row['number'],'document_type_id'=>$row['document_type_id']]
            + D::values($values) + ['calculation_snapshot'=>D::snapshot($values)];
    }

    public static function prepare(array $input): array
    {
        if (empty($input['prepayments'])) return $input;
        $rows=array_map([self::class,'normalize'],array_map(static fn($row)=>(array)$row,$input['prepayments']));
        $total=D::sum(array_map(static fn($row)=>D::readRequired($row,'total'),$rows));
        if (array_key_exists('total_prepayment',$input)
            && !D::of(D::input($input['total_prepayment'],'total_prepayment'))->isEqualTo(D::represent($total))) {
            FiscalAmounts::error('total_prepayment','El total de anticipos debe coincidir con su detalle.');
        }
        $input['prepayments']=$rows;
        $input['total_prepayment']=$total;
        return $input;
    }
}
