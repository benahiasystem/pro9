<?php

namespace Modules\CashReport\Services;

use App\Exports\GeneralFormatExport;
use App\Support\Exact\{Decimal as D, FractionSheet};
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
class ClassifiedCashExport extends GeneralFormatExport implements WithEvents
{
    public $data;
    public $view_name;

    public function registerEvents(): array
    {
        return [AfterSheet::class=>function(AfterSheet $event) {
            $data=$this->data['classified']; $records=[];
            foreach (['balances_by_currency'=>['beginning_balance','income','egress','final_balance'],
                'groups'=>['income','egress','balance'],'rows'=>['principal','tax','amount']] as $section=>$fields) {
                foreach ($data[$section] as $id=>$row) foreach ($fields as $field) {
                    $value=D::readRequired($row,$field);
                    $records[]=['id'=>$section.':'.$id.':'.$field,'value'=>D::represent($value),'calculation_snapshot'=>D::snapshot(['value'=>$value])];
                }
            }
            FractionSheet::append($event->sheet->getDelegate(),$records,['value']);
        }];
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
