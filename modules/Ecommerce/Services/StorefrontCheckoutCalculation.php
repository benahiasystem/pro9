<?php

namespace Modules\Ecommerce\Services;

use App\Models\Tenant\ExchangeRate;
use App\Models\Tenant\Item;
use App\Services\ExchangeRates\ExchangeRateMath;
use App\Services\Fiscal\FiscalAmounts;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\LineCalculation;
use Modules\Ecommerce\Models\Tenant\DeliveryZone;
use Modules\Ecommerce\Models\Tenant\DiscountCoupon;

/** Calculates a checkout from tenant records, never from submitted amounts or snapshots. */
final class StorefrontCheckoutCalculation
{
    public function __construct(private CampaignPriceService $campaigns) {}

    /**
     * Call inside the tenant transaction that will persist the order and coupon usage.
     * The caller must use the returned fractions for the order and its future document.
     */
    public function calculate(array $cart, array $delivery, ?string $couponCode, ?int $personId): array
    {
        $connection=(new Item())->getConnection();
        if ($connection->transactionLevel() < 1) throw new \LogicException('El cálculo de compra requiere una transacción tenant.');
        if (!$cart || count($cart)>300) FiscalAmounts::error('items','Seleccione entre 1 y 300 productos.');
        $ids=[];
        foreach ($cart as $entry) {
            $entry=(array)$entry; $id=filter_var($entry['id'] ?? $entry['item_id'] ?? null,FILTER_VALIDATE_INT);
            if (!$id || $id<1 || isset($ids[$id])) FiscalAmounts::error('items','Los productos deben ser válidos y no repetirse.');
            $ids[$id]=true;
        }
        // Todos los pedidos bloquean los productos en el mismo orden.
        $items=Item::withoutEagerLoads()->whereIn('id',array_keys($ids))->where('apply_store',1)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $rows=[]; $subtotal=D::of(0); $rate=null;
        foreach ($cart as $entry) {
            $entry=(array)$entry; $id=filter_var($entry['id'] ?? $entry['item_id'] ?? null,FILTER_VALIDATE_INT);
            $quantity=D::of(D::input($entry['cantidad'] ?? $entry['quantity'] ?? null,'items.quantity'));
            if ($quantity->isLessThanOrEqualTo(0)) FiscalAmounts::error('items.quantity','La cantidad debe ser positiva.');
            $item=$items->get($id);
            if (!$item || $item->variations()->exists()) FiscalAmounts::error('items','Seleccione un producto vigente y vendible de la tienda.');
            if (trim((string)$item->internal_id)==='') FiscalAmounts::error('items','El producto requiere un código interno para su comprobante.');
            \App\Models\Tenant\Catalogs\UnitType::requireActiveCode($item->unit_type_id);
            $affectation=(string)($item->sale_affectation_igv_type_id ?: '10');
            if (!in_array($affectation,['10','20'],true)) FiscalAmounts::error('items','El producto requiere una afectación de IVA vigente.');
            $currency=(string)$item->currency_type_id;
            if (!in_array($currency,['VES','USD'],true)) FiscalAmounts::error('items','El producto requiere una moneda válida.');
            if ($currency==='USD' && $rate===null) $rate=$this->exchangeRate();
            $source=$this->campaigns->exactForItem($item);
            $price=D::convert(D::of($source['final_price']),$currency,'VES',$rate);
            $amount=$price->multipliedBy($quantity);
            $subtotal=$subtotal->plus($amount);
            $rows[]=['item_id'=>(int)$item->id,'internal_id'=>(string)$item->internal_id,
                'description'=>(string)$item->description,'unit_type_id'=>(string)$item->unit_type_id,
                'affectation_igv_type_id'=>$affectation,'quantity'=>D::fraction($quantity),
                'source_currency'=>$currency,'source_price'=>$source['base_price'],'campaign'=>$source,
                'unit_price_ves'=>D::fraction($price),'subtotal_ves'=>D::fraction($amount)];
        }
        $coupon=$this->coupon($couponCode,$personId,$subtotal);
        $discount=$coupon['discount'];
        $shipping=$this->shipping($delivery,$coupon['free_shipping']);
        $total=$subtotal->minus($discount)->plus($shipping['amount']);
        $document=$this->document($rows,$subtotal,$discount,$shipping['amount'],$shipping['snapshot']);
        if (!D::of($document['totals']['total'])->isEqualTo($total)) {
            throw new \LogicException('Los importes del pedido y sus partidas no coinciden.');
        }
        return ['items'=>$rows,'exchange_rate'=>$rate,'coupon'=>$coupon['snapshot'],'shipping'=>$shipping['snapshot'],
            'subtotal'=>D::fraction($subtotal),'discount'=>D::fraction($discount),'shipping_amount'=>D::fraction($shipping['amount']),
            'total'=>D::fraction($total),'total_payable'=>D::represent($total,2,true),'document'=>$document];
    }

    /** Replace all monetary fields received from the browser with the calculated document. */
    public function purchase(array $purchase, array $calculation): array
    {
        $totals=$calculation['document']['totals'];
        $purchase['items']=$calculation['document']['items'];
        $purchase['codigo_tipo_moneda']='VES';
        $purchase['factor_tipo_de_cambio']=$calculation['exchange_rate'] ?? '1';
        $purchase['descuentos']=[];
        $purchase['cargos']=[];
        // Un pedido en efectivo todavía no acredita cobros, anticipos ni retenciones.
        // Tampoco puede asociar documentos o módulos fiscales enviados por el cliente.
        foreach (['pagos','anticipos','cuotas','received_retentions','taxes','fiscal_data',
            'guias','relacionados'] as $key) $purchase[$key]=[];
        foreach (['islr_retention_prevision','iva_retention_prevision','guarantee_fund',
            'codigo_nota_venta','hotel_rent_id','source_module'] as $key) unset($purchase[$key]);
        $purchase['pago_anticipado']=false;
        // El pedido manual permanece pendiente hasta registrar el cobro.
        $purchase['codigo_condicion_de_pago']='02';
        $purchase['totales']=[
            'total_anticipos'=>'0','total_descuentos'=>'0','total_cargos'=>'0','total_exportacion'=>'0',
            'total_operaciones_gratuitas'=>'0','total_operaciones_inafectas'=>'0',
            'total_igv_operaciones_gratuitas'=>'0','total_base_otros_impuestos'=>'0','total_otros_impuestos'=>'0',
            'total_operaciones_gravadas'=>D::represent($totals['total_taxed']),
            'total_operaciones_exoneradas'=>D::represent($totals['total_exonerated']),
            'total_igv'=>D::represent($totals['total_igv']),'total_impuestos'=>D::represent($totals['total_igv']),
            'total_valor'=>D::represent($totals['total_value']),'subtotal_venta'=>D::represent($totals['total']),
            'total_venta'=>D::represent($totals['total']),
            'total_pendiente_pago'=>D::represent($totals['total']),
        ];
        $purchase['checkout']=(array)($purchase['checkout'] ?? []);
        $purchase['checkout']['calculation']=$calculation;
        return $purchase;
    }

    public function orderItems(array $calculation): array
    {
        return array_map(function ($row) {
            return ['id'=>$row['item_id'],'internal_id'=>$row['internal_id'],'description'=>$row['description'],
                'unit_type_id'=>$row['unit_type_id'],'currency_type_id'=>'VES',
                'sale_affectation_igv_type_id'=>$row['affectation_igv_type_id'],
                'cantidad'=>D::represent($row['quantity']),'sale_unit_price'=>D::represent($row['unit_price_ves']),
                'sub_total'=>D::represent($row['subtotal_ves']),
                'campaign_discount_embedded'=>!D::of($row['campaign']['discount'])->isZero(),
                'discount_campaign_id'=>$row['campaign']['campaign_id'],
                'discount_campaign_name'=>$row['campaign']['campaign_name'],
                'campaign_discount_percent'=>D::represent($row['campaign']['percentage'])];
        },$calculation['items']);
    }

    private function document(array $rows, \Brick\Math\BigRational $subtotal, \Brick\Math\BigRational $discount,
        \Brick\Math\BigRational $shipping, array $shippingSnapshot): array
    {
        $factor=$subtotal->isZero() ? D::of(1) : $subtotal->minus($discount)->dividedBy($subtotal);
        $rate=\App\Support\Venezuela\Localization::exactTaxPercentage();
        $items=[]; $totals=['total_taxed'=>D::of(0),'total_exonerated'=>D::of(0),
            'total_value'=>D::of(0),'total_igv'=>D::of(0),'total'=>D::of(0)];
        foreach ($rows as $row) {
            $price=D::of($row['unit_price_ves'])->multipliedBy($factor);
            $items[]=$this->documentLine($row['internal_id'],$row['description'],$row['unit_type_id'],
                $row['affectation_igv_type_id'],D::of($row['quantity']),$price,$rate);
        }
        if (!$shipping->isZero()) {
            $items[]=$this->documentLine('DELIVERY-ECOM','Costo de envío - '.($shippingSnapshot['name'] ?? ''),
                'SERV','10',D::of(1),$shipping,$rate);
        }
        foreach ($items as $item) {
            $values=$item['calculation_snapshot']['values'];
            $field=$item['codigo_tipo_afectacion_igv']==='10' ? 'total_taxed' : 'total_exonerated';
            $totals[$field]=$totals[$field]->plus(D::of($values['total_base_igv']));
            foreach (['total_value','total_igv','total'] as $key) $totals[$key]=$totals[$key]->plus(D::of($values[$key]));
        }
        return ['items'=>$items,'totals'=>array_map([D::class,'fraction'],$totals)];
    }

    private function documentLine(string $code, string $description, string $unit, string $affectation,
        \Brick\Math\BigRational $quantity, \Brick\Math\BigRational $price, string $rate): array
    {
        $input=['quantity'=>D::represent($quantity),'unit_price'=>D::represent($price),
            'price_input'=>['amount'=>D::fraction($price),'currency_type_id'=>'VES','includes_iva'=>true],
            'currency_type_id'=>'VES','exchange_rate_sale'=>'1','discounts'=>[],'charges'=>[]];
        $line=LineCalculation::calculate($input,$affectation==='10' ? $rate : '0');
        return ['codigo_interno'=>$code,'descripcion'=>$description,'unidad_de_medida'=>$unit,
            'cantidad'=>$line['quantity'],'valor_unitario'=>$line['unit_value'],'codigo_tipo_precio'=>'01',
            'precio_unitario'=>$line['unit_price'],'price_input'=>$input['price_input'],
            'codigo_tipo_afectacion_igv'=>$affectation,'total_base_igv'=>$line['total_base_igv'],
            'porcentaje_igv'=>$line['percentage_igv'],'total_igv'=>$line['total_igv'],
            'total_impuestos'=>$line['total_taxes'],'total_valor_item'=>$line['total_value'],
            'total_item'=>$line['total'],'actualizar_descripcion'=>false,
            'calculation_snapshot'=>$line['calculation_snapshot']];
    }

    private function exchangeRate(): string
    {
        $record=ExchangeRate::where('date','<=',date('Y-m-d'))->orderByDesc('date')->first();
        $rate=$record ? ExchangeRateMath::optionalRate($record->getRawOriginal('sale')) : null;
        if ($rate===null) FiscalAmounts::error('exchange_rate_sale','Configure una tasa de cambio válida para productos en USD.');
        return $rate;
    }

    private function coupon(?string $code, ?int $personId, \Brick\Math\BigRational $subtotal): array
    {
        if ($code===null || trim($code)==='') return ['discount'=>D::of(0),'free_shipping'=>false,'snapshot'=>null];
        $coupon=DiscountCoupon::where('code',mb_strtoupper(trim($code)))->lockForUpdate()->first();
        if (!$coupon || !$coupon->active || $coupon->is_expired) FiscalAmounts::error('coupon','El cupón no está disponible.');
        if ($coupon->has_usage_limits && (($coupon->max_total_uses!==null && $coupon->totalUses()>=$coupon->max_total_uses)
            || ($personId && $coupon->max_uses_per_customer!==null && $coupon->usages()->where('person_id',$personId)
                ->lockForUpdate()->get(['id'])->count()>=$coupon->max_uses_per_customer))) {
            FiscalAmounts::error('coupon','El cupón alcanzó su límite de usos.');
        }
        $raw=$coupon->getAttributes();
        $min=isset($raw['min_amount']) ? D::readRequired($coupon,'min_amount') : null;
        $max=isset($raw['max_amount']) ? D::readRequired($coupon,'max_amount') : null;
        if ($coupon->has_purchase_limits && (($min && $subtotal->isLessThan($min)) || ($max && $subtotal->isGreaterThan($max)))) {
            FiscalAmounts::error('coupon','El monto no cumple los límites del cupón.');
        }
        $value=D::readRequired($coupon,'amount');
        if ($value->isNegative() || ($coupon->type==='percentage' && $value->isGreaterThan(100))) FiscalAmounts::error('coupon','El cupón tiene un importe inválido.');
        $discount=$coupon->type==='percentage' ? $subtotal->multipliedBy($value)->dividedBy(100) : $value;
        if ($max && $max->isGreaterThan(0) && $discount->isGreaterThan($max)) $discount=$max;
        if ($discount->isGreaterThan($subtotal)) $discount=$subtotal;
        return ['discount'=>$discount,'free_shipping'=>(bool)$coupon->free_shipping,
            'snapshot'=>['id'=>$coupon->id,'code'=>$coupon->code,'type'=>$coupon->type,'value'=>D::fraction($value),
                'discount'=>D::fraction($discount),'free_shipping'=>(bool)$coupon->free_shipping]];
    }

    private function shipping(array $delivery, bool $free): array
    {
        $mode=$delivery['mode'] ?? null;
        if ($mode==='pickup') return ['amount'=>D::of(0),'snapshot'=>['mode'=>'pickup','amount'=>D::fraction(0)]];
        if ($mode!=='delivery') FiscalAmounts::error('delivery','Seleccione entrega o retiro.');
        $id=filter_var($delivery['zone_id'] ?? null,FILTER_VALIDATE_INT);
        if (!$id && !DeliveryZone::active()->exists()) return ['amount'=>D::of(0),'snapshot'=>['mode'=>'delivery','zone_id'=>null,'amount'=>D::fraction(0)]];
        $zone=$id ? DeliveryZone::active()->whereKey($id)->lockForUpdate()->first() : null;
        $department=(string)($delivery['department'] ?? '');
        $province=(string)($delivery['province'] ?? '');
        $district=(string)($delivery['district'] ?? '');
        $covered=$zone && $department!=='' && $zone->locations()->where('department_id',$department)
            ->where(function ($q) use ($province,$district) {
                $q->where(fn($q)=>$q->whereNull('province_id')->whereNull('district_id'));
                if ($province!=='') $q->orWhere(function ($q) use ($province,$district) {
                    $q->where('province_id',$province)->where(function ($q) use ($district) {
                        $q->whereNull('district_id');
                        if ($district!=='') $q->orWhere('district_id',$district);
                    });
                });
            })->exists();
        if (!$covered) FiscalAmounts::error('delivery','La zona de entrega no corresponde a la dirección seleccionada.');
        $price=D::readRequired($zone,'price');
        if ($price->isNegative()) FiscalAmounts::error('delivery','El costo de entrega es inválido.');
        $amount=$free ? D::of(0) : $price;
        return ['amount'=>$amount,'snapshot'=>['mode'=>'delivery','zone_id'=>$zone->id,'name'=>$zone->name,
            'original_amount'=>D::fraction($price),'amount'=>D::fraction($amount),'free_shipping'=>$free]];
    }
}
