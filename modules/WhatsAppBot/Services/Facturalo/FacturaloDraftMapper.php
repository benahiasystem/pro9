<?php

// ######## INICIO ADAPTACIÓN VENEZUELA

namespace Modules\WhatsAppBot\Services\Facturalo;

use App\Models\Tenant\Item;
use App\Models\Tenant\Person;
use App\Models\Tenant\Series;
use App\Services\SeriesResolver;
use App\Models\Tenant\Document;
use App\Models\Tenant\User;
use Carbon\Carbon;
use App\Support\Exact\Decimal as D;

class FacturaloDraftMapper
{
    private const AFFECTATION_TAXED = '10';
    private const PRICE_TYPE_UNIT = '01';

    public function map(array $draft, User $user): array
    {
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        if (($draft['pricing_version'] ?? null) !== 1 || !\Illuminate\Support\Str::isUuid($draft['operation_key'] ?? '')) {
            \App\Services\Payments\Classification::error('items', 'El borrador anterior debe prepararse y confirmarse nuevamente.');
        }
        $pricing = DraftPricing::calculate($draft['items'] ?? [], $draft['exchange_rate_sale'] ?? null, $draft['discount_amount'] ?? '0');
        if (!isset($draft['total']) || !D::of(D::input($draft['total'], 'total'))->isEqualTo(D::of($pricing['total_payable']))) {
            \App\Services\Payments\Classification::error('total', 'El importe confirmado cambió; prepare y confirme nuevamente el borrador.');
        }
        // ######## FIN CALCULOS EXACTOS PRO9 ########
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $cashMethod = \App\Services\Payments\Classification::defaultCashMethod('VES');
        if (!\App\Models\Tenant\Cash::where('user_id', $user->id)->where('state', true)->exists()) {
            \App\Services\Payments\Classification::error('payment_destination_id', 'Aperture una caja antes de emitir el comprobante.');
        }
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        $documentTypeId = $draft['document_type_id'];
        $establishmentId = (int) $user->establishment_id;

        // Emisión automática (bot): siempre se omiten series dedicadas; no hay lectura de grupo/dispositivo.
        $series = app(SeriesResolver::class)->applyContext(Series::query()
            ->where('document_type_id', $documentTypeId)
            ->where('establishment_id', $establishmentId)
            ->where('contingency', false), null)
            ->first();

        if (!$series) {
            throw new \RuntimeException("No hay serie configurada para el establishment {$establishmentId} y document_type {$documentTypeId}.");
        }

        $nextNumber = $this->nextNumber($series);

        $customer = $draft['customer_id']
            ? Person::find($draft['customer_id'])
            : $this->getOrCreateGenericConsumer();

        $today = Carbon::now();

        $mappedItems = [];
        foreach ($pricing['items'] as $line) {
            $item = Item::findOrFail($line['item_id']);
            $mappedItems[] = $this->mapItem($item, $line);
        }
        $totalTaxed = $pricing['total_value'];
        $totalValueRounded = $pricing['total_value'];
        $totalIgv = $pricing['total_igv'];
        $total = $pricing['total'];
        $discountAmount = $pricing['discount_amount'];
        $discounts = [];
        $totalDiscount = $discountAmount;
        if (D::of($discountAmount)->isGreaterThan(0)) {
            $discountBase = $pricing['discount_base'];
            $factor = D::represent(D::of($discountAmount)->dividedBy($discountBase));
            $discounts[] = [
                'discount_type_id' => '02',
                // ########## INICIO CAMBIO IGV A IVA
                'description' => 'Descuentos globales que afectan la base imponible del IVA',
                // ######### FIN CAMBIO IGV A IVA
                'factor' => $factor,
                'amount' => $discountAmount,
                'amount_without_rounded' => $discountAmount,
                'base' => $discountBase,
                'is_amount' => true,
            ];
        }

        $payload = [
            'type' => 'invoice',
            'user_id' => $user->id,
            'external_id' => $draft['operation_key'],
            'establishment_id' => $establishmentId,
            'establishment' => $this->establishmentSnapshot($establishmentId),
            'group_id' => '01',
            'fiscal_environment' => \App\Models\Tenant\Company::active()->fiscal_environment,
            'state_type_id' => '01',
            'document_type_id' => $documentTypeId,
            'series' => $series->number,
            'number' => $nextNumber,
            'date_of_issue' => $today->format('Y-m-d'),
            'time_of_issue' => $today->format('H:i:s'),
            'customer_id' => $customer?->id,
            'customer' => $this->customerSnapshot($customer),
            'currency_type_id' => 'VES',
            'exchange_rate_sale' => $pricing['exchange_rate_sale'],
            'total_prepayment' => 0,
            'total_discount' => $totalDiscount,
            'total_charge' => 0,
            'total_exportation' => 0,
            'total_taxed' => $totalTaxed,
            'total_exonerated' => 0,
            'total_igv' => $totalIgv,
            'total_base_other_taxes' => 0,
            'total_other_taxes' => 0,
            'total_taxes' => $totalIgv,
            'total_value' => $totalValueRounded,
            'total' => $total,
            'subtotal' => $total,
            'operation_type_id' => '0101',
            'items' => $mappedItems,
            'charges' => [],
            'discounts' => $discounts,
            'attributes' => [],
            'guides' => [],
            'related' => [],
            'legends' => [],
            'additional_information' => $draft['observations'] ?? null,
            'payment_method_type_id' => $cashMethod->id,
            'payments' => [
                [
                    'date_of_payment' => $today->format('Y-m-d'),
                    'payment_method_type_id' => $cashMethod->id,
                    'payment_destination_id' => 'cash',
                    'operation_key' => $draft['operation_key'],
                    'reference' => null,
                    'payment' => $pricing['total_payable'],
                ],
            ],
            'fee' => [],
            'invoice' => [
                'date_of_due' => $today->format('Y-m-d'),
                'operation_type_id' => '0101',
                'financial_condition_type_id' => '01',
                'transfer_detail' => null,
                'transport_detail' => null,
                'related_documents' => [],
            ],
            'hotel' => null,
            'transport' => null,
            'has_pdf' => false,
            'actions' => [
                'format_pdf' => 'ticket',
            ],
        ];

        return $payload;
    }

    // ######## INICIO CALCULOS EXACTOS PRO9 ########
    private function mapItem(Item $item, array $line): array
    {
        return array_replace($line, [
            'item_id' => $item->id,
            'item' => array_merge($item->toArray(), [
                'description' => $line['item_name'] ?? $item->name,
                'unit_type_id' => $item->unit_type_id ?: 'UND',
                'currency_type_id' => $line['price_currency'],
                'sale_unit_price' => $line['price'],
                'has_igv' => (bool) $line['includes_iva'],
                'calculate_quantity' => false,
                'percentage_igv' => 16,
                'is_set' => (bool) $item->is_set,
                'IdLoteSelected' => null, 'lots' => [], 'presentation' => null,
                'used_points_for_exchange' => null, 'exchanged_for_points' => false,
            ]),
            'price_type_id' => self::PRICE_TYPE_UNIT,
            'affectation_igv_type_id' => self::AFFECTATION_TAXED,
            'attributes' => [], 'charges' => [], 'discounts' => [],
            'discount' => null, 'attribute' => null, 'name_product_pdf' => null,
        ]);
    }
    // ######## FIN CALCULOS EXACTOS PRO9 ########

    private function nextNumber(Series $series): int
    {
        $last = Document::query()
            ->where('series', $series->number)
            ->where('establishment_id', $series->establishment_id)
            ->where('fiscal_environment', \App\Models\Tenant\Company::active()->fiscal_environment)
            ->where('document_type_id', $series->document_type_id)
            ->max('number');

        return $last === null ? (int) (\Modules\Document\Models\SeriesConfiguration::where('series_id', $series->id)->value('number') ?? 1) : (int) $last + 1;
    }

    private function establishmentSnapshot(int $establishmentId): array
    {
        $establishment = \App\Models\Tenant\Establishment::with(['province', 'department', 'district', 'country'])->find($establishmentId);
        if (!$establishment) {
            return [];
        }
        $data = $establishment->toArray();
        $data['urbanization'] = $establishment->urbanization ?? null;
        $data['email'] = $establishment->email ?? null;
        $data['telephone'] = $establishment->telephone ?? null;
        $data['address'] = $establishment->address ?? '-';
        return $data;
    }

    private function getOrCreateGenericConsumer(): Person
    {
        $person = Person::where('type', 'customers')
            ->where('number', '00000000')
            ->first();

        if (!$person) {
            $person = Person::create([
                'type' => 'customers',
                'identity_document_type_id' => '0',
                'number' => '00000000',
                'name' => 'CLIENTES VARIOS',
                'trade_name' => 'CLIENTES VARIOS',
                'country_id' => 'VE',
                'address' => '-',
                'enabled' => true,
            ]);
        }

        return $person;
    }

    private function customerSnapshot(?Person $customer): array
    {
        if (!$customer) {
            return [
                'identity_document_type_id' => '0',
                'identity_document_type_code' => '0',
                'identity_document_type' => ['id' => '0', 'description' => 'OTROS', 'code' => '0'],
                'number' => '00000000',
                'name' => 'CLIENTES VARIOS',
                'trade_name' => 'CLIENTES VARIOS',
                'address' => '-',
            ];
        }

        $customer->load(['identity_document_type', 'country', 'department', 'province', 'district']);
        $data = $customer->toArray();
        $data['identity_document_type_code'] = $customer->identity_document_type_id;
        $data['trade_name'] = $customer->trade_name ?: $customer->name;
        $data['address'] = $customer->address ?: '-';
        $data['department_id'] = $customer->department_id ?: '-';
        $data['province_id'] = $customer->province_id ?: '-';
        $data['district_id'] = $customer->district_id ?: '-';
        return $data;
    }
}
// ######## FIN ADAPTACIÓN VENEZUELA
