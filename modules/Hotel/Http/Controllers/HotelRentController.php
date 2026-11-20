<?php

namespace Modules\Hotel\Http\Controllers;

use App\Database\IntegerTransport;
use App\Models\Tenant\Person;
use App\Models\Tenant\Series;
use App\Services\SeriesResolver;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Modules\Hotel\Models\HotelRent;
use Modules\Hotel\Models\HotelRoom;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Company;
use App\Models\Tenant\Establishment;
use Modules\Hotel\Models\HotelRentOrder;
use Modules\Hotel\Models\HotelRentItem;
use App\Models\Tenant\PaymentMethodType;
use Modules\Finance\Traits\FinanceTrait;
use App\Models\Tenant\Catalogs\DocumentType;
use Modules\Hotel\Http\Requests\HotelRentRequest;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use Modules\Hotel\Http\Requests\HotelRentItemRequest;
use Modules\Hotel\Models\HotelRentItemPayment;
use Modules\Hotel\Exports\HotelRentExport;
use Carbon\Carbon;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\Document;

class HotelRentController extends Controller
{
    use FinanceTrait;

	public function rent($roomId)
	{
		$room = HotelRoom::with('category', 'rates.rate')
			->findOrFail($roomId);
		$this->authorizeHotelEstablishment($room->establishment_id);

		$affectation_igv_types = AffectationIgvType::whereActive()->get();
		$series = app(SeriesResolver::class)->applyContext(Series::where('establishment_id',  auth()->user()->establishment_id))->get();

		return view('hotel::rooms.rent', compact('room', 'affectation_igv_types','series'));
	}

	public function store(HotelRentRequest $request, $roomId)
	{
		DB::connection('tenant')->beginTransaction();
		try {
			$roomId = IntegerTransport::positiveKey($roomId);
			if ($roomId === null) \App\Services\Payments\Classification::error('room_id', 'Seleccione una habitación válida.');
			$customerId = IntegerTransport::positiveKey($request->input('customer_id'));
			if ($customerId === null) \App\Services\Payments\Classification::error('customer_id', 'Seleccione un cliente válido.');
			$rateInput = $request->input('hotel_rate_id');
			$rateId = IntegerTransport::positiveKey($rateInput);
			if ($rateInput !== null && $rateId === null) \App\Services\Payments\Classification::error('hotel_rate_id', 'Seleccione una tarifa válida.');

			[$operationKey, $operationHash] = \App\Services\Payments\HotelOperation::identify($request, 'rent', $roomId);
			$room = HotelRoom::query()->lockForUpdate()->findOrFail($roomId);
			$this->authorizeHotelEstablishment($room->establishment_id);
			$previous = \App\Services\Payments\HotelOperation::existing($operationKey, $operationHash);
			if ($previous) {
				DB::connection('tenant')->commit();
				return response()->json(['success' => true, 'message' => 'Habitación rentada de forma correcta.', 'sale_note_id' => $previous->sale_note_id], 200);
			}
			if ($room->status !== 'DISPONIBLE') {
				\App\Services\Payments\Classification::error('room', 'La habitación seleccionada no está disponible.');
			}
			$this->createSaleNoteWithinTransaction($request, $customerId, (int)$room->establishment_id,
				$request->payment_status === 'PAID' ? [$request->product] : []);

			$request->merge(['hotel_room_id' => $roomId, 'customer_id' => $customerId]);
			if ($rateId !== null) $request->merge(['hotel_rate_id' => $rateId]);
			$request->merge(['establishment_id' => $room->establishment_id]);
			$now = now();
			$request->merge(['input_date' => $now->format('Y-m-d')]);
			$rent = HotelRent::create($request->only('customer_id', 'customer', 'notes', 'towels', 'hotel_room_id', 'hotel_rate_id', 'duration', 'quantity_persons', 'payment_status', 'output_date', 'output_time', 'input_date', 'input_time','data_persons','establishment_id'));

			$room->status = 'OCUPADO';
			$room->save();

			$order = new HotelRentOrder();
			$order->hotel_rent_id = $rent->id;
			$order->order_number = 1;
			$order->order_status = $request->payment_status;
			$order->sale_note_id = $request->sale_note_id;
			$order->establishment_id = $room->establishment_id;
			$order->operation_key = $operationKey;
			$order->operation_hash = $operationHash;
			$order->save();

			// Agregando la habitación a la lista de productos
			$item = new HotelRentItem();
			$item->type = 'HAB';
			$item->hotel_rent_id = $rent->id;
			$item->item_id = $request->product['item_id'];
			$item->item = $request->product;
			$item->payment_status = $request->payment_status;
			$item->hotel_rent_order_id = $order->id;
			$item->save();

			//registrar pago
			$this->saveHotelRentItemPayment($request->rent_payment, $item);
			$this->assertHotelNotePaid($request->sale_note_id);

			DB::connection('tenant')->commit();

			return response()->json([
				'success' => true,
				'message' => 'Habitación rentada de forma correcta.',
				'sale_note_id' => $order->sale_note_id,
			], 200);
		} catch (\Throwable $th) {
			DB::connection('tenant')->rollBack();
			if ($th instanceof \Illuminate\Auth\Access\AuthorizationException) throw $th;
			if ($th instanceof \Illuminate\Validation\ValidationException) {
				return response()->json(['success' => false, 'message' => $th->getMessage(), 'errors' => $th->errors()], 422);
			}

			return response()->json([
				'success' => false,
				'message' => 'No se puede procesar su transacción. Detalles: ' . $th->getMessage(),
			], 500);
		}
	}

    /** Create the note and the hotel operation in the same tenant transaction. */
    private function createSaleNoteWithinTransaction(Request $request, int $customerId, int $establishmentId, array $paidItems): void
    {
        $data = $request->input('sale_note');
        $existingId = $request->input('sale_note_id');
        if ($existingId === '') $existingId = null;
        if ($existingId !== null) {
            $existingId = IntegerTransport::positiveKey($existingId);
            if ($existingId === null) \App\Services\Payments\Classification::error('sale_note_id', 'Seleccione una nota de venta válida.');
        }
        if ($data === null && $existingId === null) return;
        if ($data !== null && (!is_array($data) || $existingId !== null)) {
            \App\Services\Payments\Classification::error('sale_note', 'La nota de venta no es válida.');
        }
        if ($data !== null) {
            unset($data['id'], $data['hotel_rent_id']);
            $data['source_module'] = 'HOTEL';
            validator($data, (new \App\Http\Requests\Tenant\SaleNoteRequest())->rules())->validate();
            $result = app(\App\Http\Controllers\Tenant\SaleNoteController::class)->storeWithData($data, true);
            if (empty($result['success']) || empty($result['data']['id'])) {
                \App\Services\Payments\Classification::error('sale_note', $result['message'] ?? 'No se pudo registrar la nota de venta.');
            }
            $existingId=$result['data']['id'];
        }
        $note = SaleNote::withoutEagerLoads()->lockForUpdate()->findOrFail($existingId);
        if ($note->isVoidedOrRejected() || $note->document_id || $note->hotel_rent_id || $note->source_module !== 'HOTEL'
            || HotelRentOrder::where('sale_note_id',$note->id)->lockForUpdate()->exists()) {
            \App\Services\Payments\Classification::error('sale_note', 'La nota no está disponible para esta operación de Hotel.');
        }
        if (!$paidItems || (int)$note->customer_id !== $customerId || (int)$note->establishment_id !== $establishmentId
            || $note->currency_type_id !== 'VES') {
            \App\Services\Payments\Classification::error('sale_note', 'La nota no corresponde al cliente, sucursal, moneda o partidas pagadas de Hotel.');
        }
        \Modules\Hotel\Services\PaidNoteLines::assertMatch($paidItems, $note->items()->withoutEagerLoads()->lockForUpdate()->get(),
            \App\Support\Exact\Decimal::readRequired($note,'total'));
        $request->merge(['sale_note_id' => $note->id]);
    }

    private function assertHotelNotePaid($noteId): void
    {
        if (!$noteId) return;
        $note=SaleNote::withoutEagerLoads()->lockForUpdate()->findOrFail($noteId);
        if (\App\Support\Exact\SaleNoteBalance::values($note,true)['total_difference']->isGreaterThan(0)) {
            \App\Services\Payments\Classification::error('sale_note', 'Los cobros no cubren el total de la nota de Hotel.');
        }
    }

	private function getOrderNumberHotelRent($establishment_id)
	{
		
	}

	/**
	 *
	 * Registrar pago si la habitacion/producto fueron pagados
	 *
	 * @param  array $rent_payment
	 * @param  HotelRentItem $item
	 * @return void
	 */
	public function saveHotelRentItemPayment($rent_payment, HotelRentItem $item)
	{
		if($item->isPaid())
		{
            // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
            $input = array_replace($rent_payment, ['date_of_payment' => date('Y-m-d')]);
            unset($input['source_sale_note_payment_id'], $input['payment_method_snapshot'], $input['calculation_snapshot']);
            $order = $item->hotel_rent_order_id ? HotelRentOrder::findOrFail($item->hotel_rent_order_id) : null;
            $source = $order && $order->sale_note_id ? \App\Services\Payments\HotelReceipt::findSource((int) $order->sale_note_id, $item->hotel_rent, $input) : null;
            if ($source) {
                $amounts = $source->only(['currency_type_id', 'original_amount', 'payment', 'exchange_rate', 'exchange_rate_source', 'exchange_rate_date', 'payment_method_snapshot', 'calculation_snapshot']);
                $amounts['source_sale_note_payment_id'] = $source->id;
                $input['date_of_payment'] = $source->date_of_payment;
            } else {
                $amounts = \App\Services\Payments\ReceiptAmounts::forMethod($input['payment_method_type_id'] ?? null, $item->hotel_rent->getDefaultCurrency(), '1', $input);
            }
            $rawItem = $item->getRawOriginal('item');
            $itemData = $rawItem ? \App\Support\Exact\JsonDecimals::decode($rawItem) : [];
            $itemTotal = isset($itemData['total']) || isset($itemData['calculation_snapshot']['values']['total'])
                ? \App\Support\Exact\Decimal::readRequired($itemData, 'total') : null;
            $applied=\App\Support\Exact\Decimal::readRequired($amounts, 'payment');
            $matches=$itemTotal !== null && $applied->isEqualTo(\App\Support\Exact\Decimal::represent($itemTotal, 2));
            if (!$matches && $source && $itemTotal !== null && !$itemTotal->isNegative()) {
                // Individual lines can take either adjacent cent; the note as a whole
                // must still be fully paid before the outer transaction is committed.
                $floor=$itemTotal->toScale(2, \Brick\Math\RoundingMode::DOWN);
                $ceiling=$itemTotal->toScale(2, \Brick\Math\RoundingMode::UP);
                $matches=$applied->isEqualTo($floor) || $applied->isEqualTo($ceiling);
            }
            if (!$matches) {
                \App\Services\Payments\Classification::error('payment', 'El importe aplicado debe coincidir con el total del servicio pagado.');
            }
            $record = $item->payments()->make(array_replace($input, $amounts));
            $record->calculation_snapshot = $amounts['calculation_snapshot'];
            $record->save();
            // ######## FIN MONEDA DEL MEDIO DE COBRO ########
            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            if (!$source) \App\Services\Payments\ReceiptDestination::attach($record,$rent_payment);
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
		}
	}


	/**
	 *
	 * Eliminar pago
	 *
	 * @param  HotelRentItem $item
	 * @return void
	 */
	public function deleteHotelRentItemPayment(HotelRentItem $item)
	{
		if(!is_null($item->payments))
		{
			$item->payments->delete();
		}
	}

  public function extendTime(Request $request, $rentId)
  {
    return DB::connection('tenant')->transaction(function () use ($request, $rentId) {
    $rent = HotelRent::query()->lockForUpdate()->findOrFail($rentId);
    $this->authorizeCheckout($rent);
    if ($rent->status === 'FINALIZADO') {
      \App\Services\Payments\Classification::error('rent', 'No se puede ampliar una estancia finalizada.');
    }

    $item = $rent->items->where('type', 'HAB')->where('payment_status', 'DEBT')->first();

	if($item){
		$item->item = $request->item["item"];
		$item->save();
	}else{
		$item = new HotelRentItem();
		$item->type = 'HAB';
		$item->hotel_rent_id = $rent->id;
		$item->item_id = $request->item["item_id"];
		$item->item = $request->item["item"];
		$item->payment_status = 'DEBT';
		$item->hotel_rent_order_id = $request->item["hotel_rent_order_id"];
		$item->save();
	}
   
    $rent->duration = $request->duration;
    $rent->output_date = $request->output_date;
    $rent->output_time = $request->output_time;
    $rent->save();

    return response()->json([
      'success' => true,
      'message' => 'Habitación actualizada de forma correcta.',
    ], 200);
    });
  }


	public function searchCustomers()
	{
		$customers = $this->customers();

		return response()->json([
			'customers' => $customers,
		], 200);
	}

	public function showFormAddProduct($rentId){
		$rent = HotelRent::with('room')
			->findOrFail($rentId);
		$this->authorizeCheckout($rent);

		$establishment = Establishment::query()->find(auth()->user()->establishment_id);
		$configuration = Configuration::first();

		$products = HotelRentItem::select(
				'hotel_rent_items.*', 
				DB::raw("COALESCE((sale_notes.series || '-' || sale_notes.number::text), '') as document")
			)
			->leftJoin('hotel_rent_orders', 'hotel_rent_items.hotel_rent_order_id', '=', 'hotel_rent_orders.id')
			->leftJoin('sale_notes', 'hotel_rent_orders.sale_note_id', '=', 'sale_notes.id')
			->where('hotel_rent_items.hotel_rent_id', $rentId)
			->where('hotel_rent_items.type', 'PRO')
			->get();

		$series = app(SeriesResolver::class)->applyContext(Series::where('establishment_id',  auth()->user()->establishment_id))->get();

		return view('hotel::rooms.add-product-to-room', compact('rent', 'configuration', 'products', 'establishment','series'));
	}


	/**
	 *
	 * Agregar productos al rentar habitacion
	 *
	 * @param  HotelRentItemRequest $request
	 * @param  int $rentId
	 * @return array
	 */
	public function addProductsToRoom(HotelRentItemRequest $request, $rentId)
	{
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $rentId = $this->checkoutRentKey($rentId);
        return DB::connection('tenant')->transaction(function () use ($request, $rentId) {
		[$operationKey, $operationHash] = \App\Services\Payments\HotelOperation::identify($request, 'products', $rentId);
		$rent = HotelRent::query()->lockForUpdate()->findOrFail($rentId);
		$this->authorizeCheckout($rent);
		$previous = \App\Services\Payments\HotelOperation::existing($operationKey, $operationHash);
		if ($previous) return response()->json(['success' => true, 'message' => 'Información actualizada.', 'sale_note_id' => $previous->sale_note_id], 200);
		if ($rent->status === 'FINALIZADO') {
			\App\Services\Payments\Classification::error('rent', 'No se pueden agregar productos a una estancia finalizada.');
		}
		if (!is_array($request->products) || !$request->products) {
			\App\Services\Payments\Classification::error('products', 'Indique los productos nuevos.');
		}
		foreach ($request->products as $product) {
			if (!is_array($product) || (array_key_exists('id', $product) && $product['id'] !== null && $product['id'] !== '') || !in_array($product['payment_status'] ?? null, ['PAID', 'DEBT'], true)) {
				\App\Services\Payments\Classification::error('products', 'Sólo se pueden agregar productos nuevos con un estado de pago válido.');
			}
		}
		$this->createSaleNoteWithinTransaction($request, (int)$rent->customer_id, (int)$rent->establishment_id,
			array_values(array_filter($request->products, fn ($product) => $product['payment_status'] === 'PAID')));

		if ($request->sale_note_id || $operationKey) {
			$order = new HotelRentOrder();
			$order->hotel_rent_id = $rentId;
			$order->order_number = 1;
			$order->order_status = collect($request->products)->contains(fn($product) => ($product['payment_status'] ?? null) === 'PAID') ? 'PAID' : 'DEBT';
			$order->sale_note_id = $request->sale_note_id;
			$order->establishment_id = $rent->establishment_id;
			$order->operation_key = $operationKey;
			$order->operation_hash = $operationHash;
			$order->save();
		}
		
		foreach ($request->products as $product) {
			$item = new HotelRentItem();
			$item->type = 'PRO';
			$item->hotel_rent_id = $rentId;
			$item->item_id = $product['item_id'];
			$item->item = $product;
			$item->payment_status = $product['payment_status'];
			$item->hotel_rent_order_id = ($product['payment_status']==='PAID' && isset($order)) ? $order->id : null;
			$item->save();
			$this->saveHotelRentItemPayment($product['rent_payment'] ?? [], $item);

		}
		$this->assertHotelNotePaid($request->sale_note_id);

		return response()->json([
			'success' => true,
			'message' => 'Información actualizada.',
			'sale_note_id' => $request->sale_note_id,
		], 200);
        });
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
	}

  public function showFormChekout($rentId)
  {
    $rent = HotelRent::with('room', 'room.category', 'items')
      ->findOrFail($rentId);
    $this->authorizeCheckout($rent);

	$items = HotelRentItem::select(
			'hotel_rent_items.*', 
			DB::raw("COALESCE((sale_notes.series || '-' || sale_notes.number::text), '') as document"),
			DB::raw("COALESCE(sale_notes.total, 0) as sale_note_total")
		)
		->leftJoin('hotel_rent_orders', 'hotel_rent_items.hotel_rent_order_id', '=', 'hotel_rent_orders.id')
		->leftJoin('sale_notes', 'hotel_rent_orders.sale_note_id', '=', 'sale_notes.id')
		->where('hotel_rent_items.hotel_rent_id', $rent->id)
		->get();
	
	$room = $items->firstWhere('type', 'HAB');

    $customer = Person::withOut('department', 'province', 'district')
      ->findOrFail($rent->customer_id);

        $payment_method_types = PaymentMethodType::getPaymentMethodTypes();
        $payment_destinations = $this->getPaymentDestinations();
        $series = app(SeriesResolver::class)->applyContext(Series::where('establishment_id',  auth()->user()->establishment_id))->get();
        // ########## INICIO CAMBIO FACTURAS Y NOTAS DE VENTA HOTEL
        // Orden explícito: Nota de venta primero, luego Factura.
        $document_types_invoice = DocumentType::whereIn('id', ['01', '80'])
          ->get()
          ->sortBy(fn ($type) => array_search($type->id, ['80', '01'], true))
          ->values();
        // ######### FIN CAMBIO FACTURAS Y NOTAS DE VENTA HOTEL
    	$affectation_igv_types = AffectationIgvType::whereActive()->get();
		$payments = HotelRentItemPayment::whereHas('associated_record_payment', function ($query) use($rentId) {
			$query->whereHas('hotel_rent', function ($query) use ($rentId) {
				$query->where('id', $rentId);
			});
		})->get();

    return view('hotel::rooms.checkout', compact(
            'rent', 'room',
            'customer',
            'payment_method_types',
            'payment_destinations',
            'series',
            'document_types_invoice',
      		'affectation_igv_types',
			'payments',
			'items'
        ));
  }

  public function finalizeRent($rentId)
  {
    return $this->finalizeRentWithData($rentId, request()->only(['arrears','checkout_document_type','checkout_document_id']));
  }

  private function finalizeRentWithData($rentId, array $input)
  {
    return DB::connection('tenant')->transaction(function () use ($rentId, $input) {
    $rent = HotelRent::query()->lockForUpdate()->findOrFail($rentId);
    $this->authorizeCheckout($rent);
    $arrears = \App\Support\Exact\Decimal::input($input['arrears'] ?? '0', 'arrears');
    if (\App\Support\Exact\Decimal::of($arrears)->isLessThan(0)) {
      \App\Services\Payments\Classification::error('arrears', 'El recargo no puede ser negativo.');
    }
    // Older tenants may still have an integer column; never silently discard cents.
    if (!\App\Support\Exact\Persistence::enabled($rent)) {
      $column = $rent->getConnection()->selectOne('SELECT DATA_TYPE AS data_type FROM information_schema.COLUMNS WHERE table_schema=current_schema() AND TABLE_NAME = ? AND COLUMN_NAME = ?', ['hotel_rents', 'arrears']);
      if (!$column || $column->data_type !== 'integer') {
        \App\Services\Payments\Classification::error('arrears', 'No se puede verificar la precisión del recargo en esta instalación.');
      }
      $arrears = \App\Support\Exact\Decimal::input($arrears, 'arrears', 0, 10);
      if (\App\Support\Exact\Decimal::of($arrears)->isGreaterThan('2147483647')) {
        \App\Services\Payments\Classification::error('arrears', 'El recargo excede el rango disponible en esta instalación.');
      }
    }
    if ($rent->status === 'FINALIZADO') {
      if (!\App\Support\Exact\Decimal::readRequired($rent, 'arrears')->isEqualTo($arrears)) {
        \App\Services\Payments\Classification::error('arrears', 'La estancia ya fue finalizada con otro recargo.');
      }
      \Modules\Hotel\Services\CheckoutSettlement::reconcileRent($rent);
      return response()->json(['success' => true, 'message' => 'La estancia ya estaba finalizada.',
        'currentRent' => HotelRent::with('room', 'room.category', 'items')->findOrFail($rentId)], 200);
    }
    $items = HotelRentItem::where('hotel_rent_id', $rentId)->lockForUpdate()->get();
    $expected = \App\Support\Exact\Decimal::of($arrears);
    $expectedItems = [];
    foreach ($items as $item) {
      if (!in_array($item->payment_status, ['PAID', 'DEBT'], true)) {
        \App\Services\Payments\Classification::error('items', 'La estancia contiene una partida con estado de pago inválido.');
      }
      if ($item->payment_status !== 'DEBT') continue;
      $data = \App\Support\Exact\JsonDecimals::decode($item->getRawOriginal('item'));
      $amount = \App\Support\Exact\Decimal::readRequired($data, 'total');
      if ($amount->isNegative()) \App\Services\Payments\Classification::error('items', 'La estancia contiene un importe pendiente inválido.');
      $expected = $expected->plus($amount);
      $key = (string)$item->item_id;
      $expectedItems[$key] = ($expectedItems[$key] ?? \App\Support\Exact\Decimal::of(0))->plus($amount);
    }
    $paid = true;
    if ($expected->isGreaterThan(0)) {
      $type = $input['checkout_document_type'] ?? null;
      $id = \App\Database\IntegerTransport::positiveKey($input['checkout_document_id'] ?? null);
      if (!in_array($type, ['01', '80'], true) || $id === null) {
        \App\Services\Payments\Classification::error('checkout_document', 'Indique el comprobante de la estancia para finalizar el cobro pendiente.');
      }
      $query = $type === '80' ? SaleNote::query() : Document::query();
      $document = $query->withoutEagerLoads()->lockForUpdate()->find($id);
      if (!$document || $document->isVoidedOrRejected() || ($type === '01' && $document->document_type_id !== '01')
          || $document->source_module !== 'HOTEL' || (int)$document->hotel_rent_id !== (int)$rent->id
          || (int)$document->customer_id !== (int)$rent->customer_id || (int)$document->establishment_id !== (int)$rent->establishment_id
          || $document->currency_type_id !== $rent->getDefaultCurrency()) {
        \App\Services\Payments\Classification::error('checkout_document', 'El comprobante no corresponde a esta estancia.');
      }
      $documentAmount = \App\Support\Exact\Decimal::readRequired($document, $type === '01' ? 'subtotal' : 'total');
      if (!$documentAmount->isEqualTo($expected)) {
        \App\Services\Payments\Classification::error('checkout_document', 'El importe del comprobante no coincide con la deuda y el recargo de la estancia.');
      }
      if (\App\Support\Exact\Decimal::of($arrears)->isGreaterThan(0)) {
        $roomItem = (string)HotelRoom::query()->lockForUpdate()->findOrFail($rent->hotel_room_id)->item_id;
        if (!$items->contains(fn ($item) => $item->type === 'HAB' && $item->payment_status === 'DEBT')) {
          $roomService = \App\Models\Tenant\Item::withoutGlobalScopes()->whereKey($roomItem)->lockForUpdate()->first(['id', 'unit_type_id', 'is_set']);
          if (!$roomService || $roomService->unit_type_id !== 'SERV' || $roomService->is_set) {
            \App\Services\Payments\Classification::error('arrears', 'La habitación pagada debe tener un servicio individual asociado para registrar el recargo.');
          }
        }
        $expectedItems[$roomItem] = ($expectedItems[$roomItem] ?? \App\Support\Exact\Decimal::of(0))->plus($arrears);
      }
      $documentItems = [];
      foreach ($document->items()->withoutEagerLoads()->lockForUpdate()->get() as $line) {
        $key = (string)$line->item_id;
        $documentItems[$key] = ($documentItems[$key] ?? \App\Support\Exact\Decimal::of(0))->plus(\App\Support\Exact\Decimal::readRequired($line, 'total'));
      }
      if (count($expectedItems) !== count($documentItems)) {
        \App\Services\Payments\Classification::error('checkout_document', 'Las partidas del comprobante no coinciden con la estancia.');
      }
      foreach ($expectedItems as $key => $amount) {
        if (!isset($documentItems[$key]) || !$amount->isEqualTo($documentItems[$key])) {
          \App\Services\Payments\Classification::error('checkout_document', 'Las partidas del comprobante no coinciden con la estancia.');
        }
      }
      $paid = \Modules\Hotel\Services\CheckoutSettlement::paid($document);
      \Modules\Hotel\Services\CheckoutSettlement::bind($rent, $document, $type, $items, $expected, $expectedItems);
    }
    $rent->update([
      'arrears' => $arrears,
      'payment_status' => $paid ? 'PAID' : 'DEBT',
      'status'  => 'FINALIZADO'
    ]);
    if ($paid) foreach ($items as $item) $item->update(['payment_status' => 'PAID']);
    HotelRoom::where('id', $rent->hotel_room_id)
      ->update([
        'status' => 'LIMPIEZA'
      ]);
        $rent = HotelRent::with('room', 'room.category', 'items')->findOrFail($rentId);
    return response()->json([
      'success' => true,
      'message' => 'Información procesada de forma correcta.',
            'currentRent' => $rent
		], 200);
    });
	}

    /** Create the checkout document and finalize the rent in one tenant transaction. */
    public function atomicCheckout(Request $request, $rentId)
    {
        $rentId = $this->checkoutRentKey($rentId);
        [$key,$hash]=\App\Services\Payments\HotelOperation::identify($request,'checkout',$rentId);
        if (!$key) \App\Services\Payments\Classification::error('operation_key','Indique un identificador de operación.');
        $data=$request->input('document');
        if (!is_array($data) || !in_array($data['document_type_id'] ?? null,['01','80'],true)) {
            \App\Services\Payments\Classification::error('document','Seleccione una factura o nota de venta válida.');
        }
        return DB::connection('tenant')->transaction(function () use ($request,$rentId,$key,$hash,$data) {
            $rent=HotelRent::query()->lockForUpdate()->findOrFail($rentId);
            $this->authorizeCheckout($rent);
            if (!\App\Support\Exact\Persistence::enabled($rent)) {
                \App\Services\Payments\Classification::error('checkout','Esta instalación no dispone del registro exacto requerido para el cierre transaccional.');
            }
            $previous=$rent->calculation_snapshot['inputs']['hotel_checkout_operation'] ?? null;
            if ($previous) {
                if (($previous['key'] ?? null) !== $key || !hash_equals((string)($previous['hash'] ?? ''),$hash)) {
                    \App\Services\Payments\Classification::error('operation_key','La estancia ya tiene otra operación de cierre registrada.');
                }
                return $this->atomicCheckoutResponse($rent,$previous);
            }
            if ($rent->status === 'FINALIZADO') \App\Services\Payments\Classification::error('checkout','La estancia ya fue finalizada.');
            if (IntegerTransport::positiveKey($data['customer_id'] ?? null) !== (int) $rent->customer_id
                || IntegerTransport::positiveKey($data['establishment_id'] ?? null) !== (int) $rent->establishment_id) {
                \App\Services\Payments\Classification::error('document','El cliente y la sucursal deben coincidir con la estancia.');
            }
            $type=$data['document_type_id'];
            $candidates=SaleNote::withoutEagerLoads()->where('source_module','HOTEL')->where('hotel_rent_id',$rent->id)
                ->whereNotIn('state_type_id',SaleNote::VOIDED_REJECTED_IDS)->lockForUpdate()->get()->map(fn ($row)=>['type'=>'80','id'=>$row->id]);
            $candidates=$candidates->concat(Document::withoutEagerLoads()->where('source_module','HOTEL')->where('hotel_rent_id',$rent->id)
                ->where('document_type_id','01')->whereNotIn('state_type_id',Document::VOIDED_REJECTED_IDS)->lockForUpdate()->get()->map(fn ($row)=>['type'=>'01','id'=>$row->id]));
            if ($candidates->isNotEmpty()) {
                \App\Services\Payments\Classification::error('checkout','Ya existe un comprobante vigente para esta estancia; revíselo antes de crear otro.');
            }
            unset($data['id']);
            $data['source_module']='HOTEL'; $data['hotel_rent_id']=$rent->id;
            if ($type === '80') {
                $rules=new \App\Http\Requests\Tenant\SaleNoteRequest();
                validator($data,$rules->rules(),$rules->messages())->validate();
                $result=app(\App\Http\Controllers\Tenant\SaleNoteController::class)->storeWithData($data, true);
            } else {
                $data['type']='invoice';
                $data['user_id']=auth()->id();
                $data['external_id']=(string) \Illuminate\Support\Str::uuid();
                $data['group_id']=$data['group_id'] ?? '01';
                $data['state_type_id']=$data['state_type_id'] ?? '01';
                $data['invoice']=$data['invoice'] ?? ['date_of_due'=>$data['date_of_due'] ?? $data['date_of_issue'],
                    'operation_type_id'=>$data['operation_type_id'] ?? '0101'];
                $data['transport']=$data['transport'] ?? null;
                if (empty($data['series']) && !empty($data['series_id'])) {
                    $seriesId = \App\Database\IntegerTransport::positiveKey($data['series_id']);
                    if ($seriesId === null) {
                        \App\Services\Payments\Classification::error('series_id', 'Seleccione una serie de factura válida.');
                    }
                    $data['series']=Series::query()->whereKey($seriesId)->where('document_type_id','01')
                        ->where('establishment_id',$rent->establishment_id)->value('number');
                }
                if (!isset($data['series']) || is_string($data['series'])) $data['series']=\App\Services\SeriesNumbering::normalizeCode($data['series'] ?? null);
                $documentRequest=\App\Http\Requests\Tenant\DocumentRequest::create('/documents','POST',$data);
                $validator=validator($data,$documentRequest->rules(),$documentRequest->messages());
                $documentRequest->withValidator($validator);
                $validator->validate();
                $result=app(\App\Http\Controllers\Tenant\DocumentController::class)->storeFromHotelCheckout($documentRequest);
            }
            if (empty($result['success']) || empty($result['data']['id'])) {
                $message=$result['message'] ?? '';
                if (!is_string($message) || str_contains($message,'/') || str_contains($message,'\\')) $message='No se pudo registrar el comprobante de la estancia.';
                \App\Services\Payments\Classification::error('document',$message ?: 'No se pudo registrar el comprobante de la estancia.');
            }
            $documentId=$result['data']['id'];
            $this->finalizeRentWithData($rent->id,['arrears'=>$request->input('arrears','0'),
                'checkout_document_type'=>$type,'checkout_document_id'=>$documentId]);
            $rent->refresh();
            $snapshot=$rent->calculation_snapshot ?? \App\Support\Exact\Decimal::snapshot([]);
            $operation=['key'=>$key,'hash'=>$hash,'type'=>$type,'document_id'=>(int)$documentId];
            $snapshot['inputs']['hotel_checkout_operation']=$operation;
            $rent->calculation_snapshot=$snapshot; $rent->save();
            return $this->atomicCheckoutResponse($rent,$operation);
        },3);
    }

    /** A locking read distinguishes a completed request from one that never committed. */
    public function checkoutOperationStatus($rentId, string $key)
    {
        $rentId = $this->checkoutRentKey($rentId);
        if (!\Illuminate\Support\Str::isUuid($key)) \App\Services\Payments\Classification::error('operation_key','Identificador de operación inválido.');
        return DB::connection('tenant')->transaction(function () use ($rentId,$key) {
            $rent=HotelRent::query()->lockForUpdate()->findOrFail($rentId);
            $this->authorizeCheckout($rent);
            $operation=$rent->calculation_snapshot['inputs']['hotel_checkout_operation'] ?? null;
            if ($operation && ($operation['key'] ?? null)===$key) return $this->atomicCheckoutResponse($rent,$operation);
            if ($rent->status==='FINALIZADO') \App\Services\Payments\Classification::error('checkout','La estancia ya fue finalizada por otra operación.');
            return response()->json(['success'=>true,'found'=>false]);
        });
    }

    private function checkoutRentKey($rentId): int
    {
        $key = IntegerTransport::positiveKey($rentId);
        if ($key === null) {
            \App\Services\Payments\Classification::error('rent_id', 'Seleccione una estancia válida.');
        }
        return $key;
    }

    private function atomicCheckoutResponse(HotelRent $rent, array $operation)
    {
        return response()->json(['success'=>true,'found'=>true,'data'=>['id'=>$operation['document_id'],
            'document_type_id'=>$operation['type']], 'currentRent'=>$rent->fresh(['room','room.category','items'])]);
    }

    private function authorizeCheckout(HotelRent $rent): void
    {
        $this->authorizeHotelEstablishment($rent->establishment_id);
    }

    private function authorizeHotelEstablishment($establishmentId): void
    {
        $user=auth()->user();
        if (!$user instanceof \App\Models\Tenant\User || ($user->type!=='admin' && (int)$user->establishment_id!==(int)$establishmentId)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('No posee permisos para operar en esta sucursal.');
        }
    }

	private function customers()
	{
		$customers = Person::with('addresses')
			->whereType('customers')
			->whereSalesIdentityActive()
			->whereIsEnabled()
			->orderBy('name');

		$query = request('input');
		$search_by_barcode = (bool)request('search_by_barcode');
		if ($query && $search_by_barcode) {

			$customers = $customers->where('barcode', 'like', "%{$query}%");
		}else{
			if (is_numeric($query)) {
				$customers = $customers->where('number', 'like', "%{$query}%");
			}else {
				$customers = $customers->where('name', 'like', "%{$query}%");
			}
		}

		$customers = $customers->take(20)
			->get()
			->transform(function ($row) {
				return [
					'id'                          => $row->id,
					'description'                 => $row->number . ' - ' . $row->name,
					'name'                        => $row->name,
					'number'                      => $row->number,
					'identity_document_type_id'   => $row->identity_document_type_id,
					'identity_document_type_code' => $row->identity_document_type->code,
					'addresses'                   => $row->addresses,
					'address'                     => $row->address,
					'internal_code'               => $row->internal_code,
					'barcode'					  => $row->barcode
				];
			});

		return $customers;
	}

	public function tables()
	{
		$customers = $this->customers();
		$configuration = Configuration::select('affectation_igv_type_id')->first();

        $payment_method_types = PaymentMethodType::getTableCashPaymentMethodTypes();
        $payment_destinations = $this->getPaymentDestinations();

		return response()->json([
			'customers' => $customers,
			'configuration' => $configuration,
			'payment_method_types' => $payment_method_types,
			'payment_destinations' => $payment_destinations
		], 200);
	}


	/**
	 *
	 * Datos relacionados para agregar productos al rentar habitacion
	 *
	 * @return array
	 */
	public function rentProductsTables()
	{
        $payment_method_types = PaymentMethodType::getTableCashPaymentMethodTypes();
        $payment_destinations = $this->getPaymentDestinations();

		return [
			'payment_method_types' => $payment_method_types,
			'payment_destinations' => $payment_destinations
		];
	}

    public function report($start, $end, $establishment_id)
    {
		$user = auth()->user();
		$establishment = $user->establishment;
		$query = HotelRent::whereBetween('input_date', [$start, $end]);

		if ($establishment_id && $user->type === 'admin') {
			$query->where('establishment_id', $establishment_id);
			$establishment = Establishment::findOrFail($establishment_id);
		}

		if ($user->type != 'admin') {
			$query->where('establishment_id', $user->establishment_id);
		}
		
        $documents = $query->get();

        $records = collect($documents)->transform(function ($row) {

			$data_persons = collect((array) $row->data_persons)
				->map(function ($person) {
					$name = isset($person->name) ? $person->name : '';
					$number = isset($person->number) ? $person->number : '';
					return trim("{$name} {$number}", " ");
				})
				->implode('; ');

			$document_number = "";
			$document_date = "";
			$total = "";

			$document = Document::where('hotel_rent_id',$row->id)->first();

			if($document){
				$document_number = $document->series.'-'.$document->number;
				$document_date = $document->date_of_issue;
				$total = $document->total;
			}

			$sale_note = SaleNote::where('hotel_rent_id',$row->id)->first();

			if($sale_note){
				$document_number = $sale_note->series.'-'.$sale_note->number;
				$document_date = $sale_note->date_of_issue;
				$total = $sale_note->total;
			}

            return [
                'id' => $row->id,
                'customer' => $row->customer->description,
                'document_number' => $document_number,
                'document_date' => $document_date,
                'total' => $total,
                'input_date' => $row->input_date,
                'input_time' => $row->input_time,
				'output_date' => $row->output_date,
                'output_time' => $row->output_time,
                'duration' => $row->duration,
                'quantity_persons' => $row->quantity_persons,
                'category' => $row->room->category->description,
				'data_persons' => $data_persons,
            ];
        });

        $filename = "Reporte_Recepción";
		$company = Company::first();

		return (new HotelRentExport)
			->records($records)
			->company($company)
            ->establishment($establishment)
			->download($filename . Carbon::now() . '.xlsx');
		
    }

}
