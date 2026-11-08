<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;

class GeneralItemExport implements FromView, ShouldAutoSize, \Maatwebsite\Excel\Concerns\WithEvents
{
    use Exportable;
    private array $costs = [];
    private ?string $frozenHtml = null;
    private ?array $frozenFractions = null;

    public function records($records) {
        $this->records = $records;

        return $this;
    }

    public function type($type) {
        $this->type = $type;

        return $this;
    }

    public function document_type_id($type) {
        $this->document_type_id = $type;
        return $this;
    }

    public function request_apply_conversion_to_pen($request_apply_conversion_to_pen) 
    {
        $this->request_apply_conversion_to_pen = $request_apply_conversion_to_pen;
        return $this;
    }

    public function view(): View {
        if ($this->frozenHtml !== null) return view('report::general_items.prepared_excel', ['html'=>$this->frozenHtml]);
        $this->costs = $this->type === 'sale' ? \Modules\Report\Services\EstimatedPurchaseCost::forLines(
            $this->records, (string)$this->document_type_id === '80', filter_var($this->request_apply_conversion_to_pen, FILTER_VALIDATE_BOOLEAN)) : [];
        return view('report::general_items.report_excel', [
            'records'=> $this->records,
            'type'=> $this->type,
            'document_type_id' => $this->document_type_id,
            'request_apply_conversion_to_pen' => $this->request_apply_conversion_to_pen,
            'costs' => $this->costs,
        ]);
    }

    /** Materialize report values and HTML inside the caller's consistent read. */
    public function freeze(): self
    {
        $this->frozenHtml = $this->view()->render();
        $this->frozenFractions = $this->fractionData();
        return $this;
    }

    public function registerEvents(): array
    {
        return [\Maatwebsite\Excel\Events\AfterSheet::class => function ($event) {
            [$fields,$rows,$components]=$this->frozenFractions ?? $this->fractionData();
            \App\Support\Exact\FractionSheet::append($event->sheet->getDelegate(),$rows,$fields);
            \App\Support\Exact\FractionSheet::appendEntries($event->sheet->getDelegate(),$components);
        }];
    }

    private function fractionData(): array
    {
        $fields=['quantity','unit_value','unit_price','total_value','total_discount','total_igv','total'];
        if ($this->type === 'sale') $fields=array_merge($fields,['total_item_purchase','utility_item','catalog_unit_cost']);
        $parent=$this->type === 'purchase' ? 'purchase' : ((string)$this->document_type_id === '80' ? 'sale_note' : 'document');
        $lines=new \Illuminate\Database\Eloquent\Collection(collect($this->records)->all());
        $lines->loadMissing($parent);
        $rows=[]; $components=[];
        foreach ($lines as $line) {
            $document=$line->$parent;
            if (!$document) throw new \UnexpectedValueException('Falta el documento del reporte de artículos.');
            $values=[];
            foreach (array_slice($fields,0,7) as $field) {
                $value=\App\Support\Exact\Decimal::readRequired($line,$field);
                if ($field !== 'quantity' && filter_var($this->request_apply_conversion_to_pen, FILTER_VALIDATE_BOOLEAN) && $document->currency_type_id === 'USD') {
                    $value=\App\Support\Exact\Decimal::convert($value,'USD','VES',$document->exchange_rate_sale);
                }
                $values[$field]=$value;
            }
            if ($this->type === 'sale') {
                $cost=$this->costs[spl_object_id($line)];
                $target=filter_var($this->request_apply_conversion_to_pen, FILTER_VALIDATE_BOOLEAN) && $document->currency_type_id === 'USD'
                    ? 'VES' : $document->currency_type_id;
                $values['total_item_purchase']=$cost['amount'];
                $values['utility_item']=$values['total']->minus($cost['amount']);
                $values['catalog_unit_cost']=\Modules\Report\Services\GeneralItemAmounts::catalogPrice($line->relation_item, 'purchase_unit_price', $document, $target);
                if ($line->relation_item->is_set) foreach ($line->relation_item->sets as $component) {
                    $child=$component->relation_item;
                    if (!$child) throw new \UnexpectedValueException('Falta un componente del producto compuesto.');
                    $quantity=\App\Support\Exact\Decimal::readRequired($component, 'quantity');
                    $unitCost=\Modules\Report\Services\GeneralItemAmounts::catalogPrice($child, 'purchase_unit_price', $document, $target);
                    $unitPrice=\Modules\Report\Services\GeneralItemAmounts::catalogPrice($child, 'sale_unit_price', $document, $target);
                    $componentId=$parent.'_item:'.$line->id.':component:'.$component->id;
                    foreach (['quantity'=>$quantity,'catalog_unit_cost'=>$unitCost,'unit_price'=>$unitPrice,
                        'total'=>$unitCost->multipliedBy($quantity)] as $field=>$amount) {
                        $components[]=['id'=>$componentId,'field'=>$field,'value'=>\App\Support\Exact\Decimal::fraction($amount)];
                    }
                }
            }
            $rows[]=['id'=>$parent.'_item:'.$line->id,'calculation_snapshot'=>\App\Support\Exact\Decimal::snapshot($values)];
        }
        return [$fields,$rows,$components];
    }
}
