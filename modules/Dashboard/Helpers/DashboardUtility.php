<?php

namespace Modules\Dashboard\Helpers;

use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\SaleNoteItem;
use Modules\Expense\Models\Expense;

class DashboardUtility
{
    public function data($request)
    {
        $filters = DashboardFilterHelper::resolve($request);

        return [
            'utilities' => $this->calculateUtilityTotals(
                $filters['establishment_id'],
                $filters['date_start'],
                $filters['date_end'],
                filter_var($request['enabled_expense'] ?? true, FILTER_VALIDATE_BOOLEAN),
                $request['item_id'] ?? null
            ),
        ];
    }

    public function calculateUtilityTotals($establishment_id, $d_start, $d_end, $enabled_expense = true, $item_id = null)
    {
        $document_items = $this->getDocumentItems($establishment_id, $d_start, $d_end, $item_id);
        $sale_note_items = $this->getSaleNoteItems($establishment_id, $d_start, $d_end, $item_id);

        $document_totals = $this->getTotalDocumentItems($document_items);
        $sale_note_totals = $this->getTotalSaleNoteItems($sale_note_items);
        $expenses_total = $this->getTotalExpenses(
            $this->getExpenses($establishment_id, $d_start, $d_end, $enabled_expense)
        );

        $total_income = $document_totals['document_sale_total'] + $sale_note_totals['sale_note_sale_total'];
        $total_egress = $document_totals['document_purchase_total']
            + $sale_note_totals['sale_note_purchase_total']
            + $expenses_total;
        $utility = $total_income - $total_egress;

        return [
            'totals' => [
                'total_income' => number_format($total_income, 2, '.', ''),
                'total_egress' => number_format($total_egress, 2, '.', ''),
                'utility' => number_format($utility, 2, '.', ''),
            ],
            'graph' => [
                'labels' => ['Ingreso', 'Egreso'],
                'datasets' => [
                    [
                        'label' => 'Utilidades',
                        'data' => [round($total_income, 2), round($total_egress, 2)],
                        'backgroundColor' => [
                            'rgb(36, 71, 232, .1)',
                            'rgb(254, 0, 108, .1)',
                        ],
                        'borderColor' => [
                            'rgb(36, 71, 232)',
                            'rgb(254, 0, 108)',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getDocumentItems($establishment_id, $d_start, $d_end, $item_id)
    {
        $query = DocumentItem::without(['affectation_igv_type', 'system_isc_type', 'price_type'])
            ->with([
                'document:id,currency_type_id,exchange_rate_sale,document_type_id',
                'relation_item:id,purchase_unit_price,unit_type_id',
            ])
            ->whereHas('document', function ($query) use ($establishment_id, $d_start, $d_end) {
                $query->whereIn('state_type_id', ['01', '03', '05', '07', '13'])
                    ->whereIn('document_type_id', ['01', '03', '07', '08']);

                if ($establishment_id) {
                    $query->where('establishment_id', $establishment_id);
                }

                if ($d_start && $d_end) {
                    $query->whereBetween('date_of_issue', [$d_start, $d_end]);
                }
            });

        if ($item_id) {
            $query->where('item_id', $item_id);
        }

        return $query->get();
    }

    private function getSaleNoteItems($establishment_id, $d_start, $d_end, $item_id)
    {
        $query = SaleNoteItem::without(['affectation_igv_type', 'system_isc_type', 'price_type'])
            ->with([
                'sale_note:id,currency_type_id,exchange_rate_sale',
                'relation_item:id,purchase_unit_price,unit_type_id',
            ])
            ->whereHas('sale_note', function ($query) use ($establishment_id, $d_start, $d_end) {
                $query->where('changed', false)
                    ->whereIn('state_type_id', ['01', '03', '05', '07', '13']);

                if ($establishment_id) {
                    $query->where('establishment_id', $establishment_id);
                }

                if ($d_start && $d_end) {
                    $query->whereBetween('date_of_issue', [$d_start, $d_end]);
                }
            });

        if ($item_id) {
            $query->where('item_id', $item_id);
        }

        return $query->get();
    }

    private function getExpenses($establishment_id, $d_start, $d_end, $enabled_expense)
    {
        if (!$enabled_expense) {
            return null;
        }

        $query = Expense::query()
            ->where('state_type_id', '!=', '11');

        if ($establishment_id) {
            $query->where('establishment_id', $establishment_id);
        }

        if ($d_start && $d_end) {
            $query->whereBetween('date_of_issue', [$d_start, $d_end]);
        }

        return $query->get();
    }

    private function getTotalExpenses($expenses)
    {
        if (!$expenses) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($expenses as $expense) {
            $total += ($expense->currency_type_id == 'USD')
                ? $expense->total * $expense->exchange_rate_sale
                : $expense->total;
        }

        return round($total, 2);
    }

    private function getPurchaseUnitPrice($record)
    {
        $unit_type_id = optional($record->relation_item)->unit_type_id
            ?? ($record->item->unit_type_id ?? null);

        if ($unit_type_id === 'ZZ') {
            return 0.0;
        }

        $relation_item = $record->relation_item;

        if ($relation_item && (float) $relation_item->purchase_unit_price > 0) {
            return (float) $relation_item->purchase_unit_price;
        }

        $purchase_item = PurchaseItem::select('unit_price')
            ->where('item_id', $record->item_id)
            ->latest('id')
            ->first();

        if ($purchase_item && (float) $purchase_item->unit_price > 0) {
            return (float) $purchase_item->unit_price;
        }

        return 0.0;
    }

    private function getTotalSaleNoteItems($sale_note_items)
    {
        $sale_note_sale_total = 0.0;
        $sale_note_purchase_total = 0.0;

        foreach ($sale_note_items as $sale_note_item) {
            $factor = ($sale_note_item->sale_note->currency_type_id === 'USD')
                ? (float) $sale_note_item->sale_note->exchange_rate_sale
                : 1.0;

            $sale_note_sale_total += (float) $sale_note_item->total * $factor;

            $purchase_unit_price = $this->getPurchaseUnitPrice($sale_note_item);
            $presentation_quantity = $this->getQuantityUnitPresentation($sale_note_item);
            $sale_note_purchase_total += $purchase_unit_price * ((float) $sale_note_item->quantity * $presentation_quantity);
        }

        return [
            'sale_note_sale_total' => round($sale_note_sale_total, 2),
            'sale_note_purchase_total' => round($sale_note_purchase_total, 2),
        ];
    }

    private function getTotalDocumentItems($document_items)
    {
        $document_sale_total = 0.0;
        $document_purchase_total = 0.0;

        foreach ($document_items as $document_item) {
            $factor = ($document_item->document->currency_type_id === 'USD')
                ? (float) $document_item->document->exchange_rate_sale
                : 1.0;

            $is_sale = in_array($document_item->document->document_type_id, ['01', '03', '08'], true);
            $sign = $is_sale ? 1 : -1;

            $document_sale_total += (float) $document_item->total * $factor * $sign;

            $purchase_unit_price = $this->getPurchaseUnitPrice($document_item);
            $presentation_quantity = $this->getQuantityUnitPresentation($document_item);
            $document_purchase_total += $purchase_unit_price * ((float) $document_item->quantity * $presentation_quantity) * $sign;
        }

        return [
            'document_sale_total' => round($document_sale_total, 2),
            'document_purchase_total' => round($document_purchase_total, 2),
        ];
    }

    public function getQuantityUnitPresentation($model_item)
    {
        $item = $model_item->item;

        if ($item && !empty($item->presentation)) {
            $presentation = $item->presentation;
            $quantity_unit = is_object($presentation)
                ? ($presentation->quantity_unit ?? null)
                : ($presentation['quantity_unit'] ?? null);

            if ($quantity_unit) {
                return (float) $quantity_unit;
            }
        }

        return 1.0;
    }
}
