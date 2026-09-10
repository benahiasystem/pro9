<?php

namespace App\Http\Controllers\Tenant;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;
use App\CoreFacturalo\Requests\Inputs\Common\PersonInput;
use App\CoreFacturalo\Template;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SearchItemController;
use App\Http\Requests\Tenant\SaleNoteRequest;
use App\Http\Resources\Tenant\SaleNoteCollection;
use App\Http\Resources\Tenant\SaleNoteResource;
use App\Http\Resources\Tenant\SaleNoteResource2;
use App\Mail\Tenant\SaleNoteEmail;
use App\Models\Tenant\BankAccount;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\AttributeType;
use App\Models\Tenant\Catalogs\ChargeDiscountType;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\Cash;
use App\Models\Tenant\Catalogs\DocumentType;
use App\Models\Tenant\Catalogs\OperationType;
use App\Models\Tenant\Catalogs\PriceType;
use App\Models\Tenant\Company;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Dispatch;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\PaymentMethodType;
use App\Models\Tenant\Person;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\SaleNotePayment;
use App\Models\Tenant\Document;
use App\Models\Tenant\Series;
use App\Services\SeriesResolver;
use App\Models\Tenant\User;
use App\Traits\OfflineTrait;
use Carbon\Carbon;
use ErrorException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Modules\Document\Traits\SearchTrait;
use Modules\Finance\Traits\FinanceTrait;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Traits\InventoryTrait;
use Modules\Item\Models\ItemLot;
use Modules\Item\Models\ItemLotsGroup;
use Modules\Sale\Helpers\SaleNoteHelper;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use App\Models\Tenant\DispatchSaleNote;
use App\Http\Resources\Tenant\DispatchSaleNoteCollection;
use Modules\Finance\Traits\FilePaymentTrait;
// use App\Http\Resources\Tenant\SaleNoteGenerateDocumentResource;
// use App\Models\Tenant\Warehouse;
use App\CoreFacturalo\HelperFacturalo;


class SaleNoteController extends Controller
{

    use FinanceTrait;
    use InventoryTrait;
    use SearchTrait;
    use StorageDocument;
    use OfflineTrait;
    use FilePaymentTrait;

    protected $sale_note;
    protected $company;
    protected $apply_change;

    public function index()
    {
        $company = Company::select('fiscal_environment')->first();
        $company_environment  = $company->fiscal_environment;
        $configuration = Configuration::select('ticket_58')->first();

        return view('tenant.sale_notes.index', compact('company_environment', 'configuration'));
    }


    public function create($id = null)
    {
        $cash = Cash::where([['user_id', auth()->user()->id], ['state', true]])->first();

        if (!$cash) {
            return redirect()->route('tenant.cash.index', ['redirect_reason' => 'no_cash_sale_note']);
        }

        return view('tenant.sale_notes.form', compact('id'));
    }






    /**
     * Busca el texto $search en la cadena de caracteres $text
     * @param $search
     * @param $text
     * @return bool
     */
    public function searchInString($search, $text){
        return !(strpos($text, $search) === false);
    }

    public function columns()
    {
        return [
            'date_of_issue' => 'Fecha de emisión',
            'customer' => 'Cliente',
        ];
    }

    public function columns2()
    {
        return [
            // Series filtradas por contexto (oculta dedicadas / restringe al grupo activo). Ver SeriesResolver.
            'series' => app(SeriesResolver::class)->applyContext(Series::whereIn('document_type_id', ['80']))->get(),

        ];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \App\Http\Resources\Tenant\SaleNoteCollection
     */
    public function records(Request $request)
    {

        $records = $this->getRecords($request);

        /* $records = new SaleNoteCollection($records->paginate(config('tenant.items_per_page')));
        dd($records); */
        return new SaleNoteCollection($records->paginate(config('tenant.items_per_page')));

    }


    /**
     * @param $request
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getRecords($request){
        $records = SaleNote::whereTypeUser();
        // Solo devuelve matriculas
        if($request != null && $request->has('onlySuscription') && (bool)$request->onlySuscription == true){
            $records->whereNotNull('grade')->whereNotNull('section') ;
        }
        // Solo devuelve Suscripciones que tengan relacion en user_rel_suscription_plans.
        if($request != null && $request->has('onlyFullSuscription') && (bool)$request->onlyFullSuscription == true){
            $records->whereNotNull('user_rel_suscription_plan_id')
                ->whereNull('grade')->whereNull('section')
            ;
        }
        if($request->column == 'customer'){
            $records->whereHas('person', function($query) use($request){
                                    $query
                                        ->where('name', 'like', "%{$request->value}%")
                                        ->orWhere('number', 'like', "%{$request->value}%");
                                })
                                ->latest();

        }else{
            $records->where($request->column, 'like', "%{$request->value}%")
                    ->latest('id');
        }
        if($request->series) {
            $records->where('series', 'like', '%' . $request->series . '%');
        }
        if($request->number) {
            $records->where('number', 'like', '%' . $request->number . '%');
        }
        if($request->total_canceled != null) {
            $records->where('total_canceled', $request->total_canceled);
        }

        if($request->purchase_order) {
            $records->where('purchase_order', $request->purchase_order);
        }
        if($request->license_plate) {
            $records->where('license_plate', $request->license_plate);
        }
        if($request->observations) {
            $records->where('observation', 'like', '%' . $request->observations . '%');
        }
        return $records;
    }


    public function searchCustomers(Request $request)
    {

        $customers = Person::where('number','like', "%{$request->input}%")
                            ->orWhere('name','like', "%{$request->input}%")
                            ->whereType('customers')->orderBy('name')
                            ->whereIsEnabled()
                            ->get()->transform(function(Person $row) {
                                return $row->getCollectionData();
                                return [
                                    'id' => $row->id,
                                    'description' => $row->number.' - '.$row->name,
                                    'seller_id' => $row->seller_id,
                                    'seller' => $row->seller,
                                    'name' => $row->name,
                                    'number' => $row->number,
                                    'identity_document_type_id' => $row->identity_document_type_id,
                                    'identity_document_type_code' => $row->identity_document_type->code
                                ];
                            });

        return compact('customers');
    }

    public function tables()
    {
        $user = new User();
        if(\Auth::user()){
            $user = \Auth::user();
        }
        $establishment_id =  $user->establishment_id;
        $userId =  $user->id;
        $customers = $this->table('customers');
        $establishments = Establishment::where('id', auth()->user()->establishment_id)->get();
        $currency_types = CurrencyType::whereActive()->get();
        $discount_types = ChargeDiscountType::whereType('discount')->whereLevel('item')->get();
        $charge_types = ChargeDiscountType::whereType('charge')->whereLevel('item')->get();
        $global_charge_types = ChargeDiscountType::whereIn('id', ['50'])->get();
        $company = Company::active();
        $payment_method_types = PaymentMethodType::all();
        $series = collect(app(SeriesResolver::class)->applyContext(Series::query())->get())->transform(function($row) {
            return [
                'id' => $row->id,
                'contingency' => (bool) $row->contingency,
                'document_type_id' => $row->document_type_id,
                'establishment_id' => $row->establishment_id,
                'number' => $row->number
            ];
        });
        $payment_destinations = $this->getPaymentDestinations();
        $configuration = Configuration::select('destination_sale','ticket_58')->first();
        // $sellers = User::GetSellers(false)->get();
        $sellers = User::getSellersToNvCpe($establishment_id,$userId);
        $global_discount_types = ChargeDiscountType::getGlobalDiscounts();

        return compact('customers', 'establishments','currency_types', 'discount_types', 'configuration',
                         'charge_types','company','payment_method_types', 'series', 'payment_destinations','sellers', 'global_charge_types', 'global_discount_types');
    }

    public function changed($id)
    {
        $sale_note = SaleNote::find($id);
        $sale_note->changed = true;
        $sale_note->save();
    }


    public function item_tables()
    {
        // $items = $this->table('items');
        $items = SearchItemController::getItemsToSaleNote();
        $categories = [];
        $affectation_igv_types = AffectationIgvType::whereActive()->get();
        $price_types = PriceType::whereActive()->get();
        $discount_types = ChargeDiscountType::whereType('discount')->whereLevel('item')->get();
        $charge_types = ChargeDiscountType::whereType('charge')->whereLevel('item')->get();
        $attribute_types = AttributeType::whereActive()->orderByDescription()->get();

        $operation_types = OperationType::whereActive()->get();
        $is_client = $this->getIsClient();

        return compact('items',
        'categories',
        'affectation_igv_types',
        'price_types',
        'discount_types',
        'charge_types',
        'attribute_types',
        'operation_types',
        'is_client'
        );
    }

    public function record($id)
    {
        $record = new SaleNoteResource(SaleNote::findOrFail($id));

        return $record; //record
    }

    public function record2($id)
    {
        $record = new SaleNoteResource2(SaleNote::findOrFail($id));

        return $record;
    }

    public function updateCustomFields(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'custom_fields_data' => 'nullable|array'
        ]);

        $saleNote = SaleNote::findOrFail($request->input('id'));
        $saleNote->custom_fields_data = $request->input('custom_fields_data', []);
        $saleNote->save();

        return [
            'success' => true,
            'data' => $saleNote->custom_fields_data
        ];
    }

    public function store(SaleNoteRequest $request)
    {
        $data = $request->all();
        if (empty($data['source_module'])) {
            $data['source_module'] = 'WEB';
        }

        return $this->storeWithData($data);
    }


    public function storeWithData($inputs)
    {
        DB::connection('tenant')->beginTransaction();
        try {
            $isUpdate = true;
            if (!isset($inputs['id'])) {
                $inputs['id'] = false;
                $isUpdate = false;
            }
            $data = $this->mergeData($inputs, $isUpdate);

            $this->sale_note =  SaleNote::query()->updateOrCreate(['id' => $inputs['id']], $data);

            $this->deleteAllPayments($this->sale_note->payments);

            //se elimina los items para activar el evento deleted del modelo y controlar el inventario
            $this->deleteAllItems($this->sale_note->items);


            foreach($data['items'] as $row)
            {

                // $item_id = isset($row['id']) ? $row['id'] : null;
                $item_id = isset($row['record_id']) ? $row['record_id'] : null;
                $sale_note_item = SaleNoteItem::query()->firstOrNew(['id' => $item_id]);

                if(isset($row['item']['lots'])){
                    $row['item']['lots'] = isset($row['lots']) ? $row['lots']:$row['item']['lots'];
                }

                $this->setIdLoteSelectedToItem($row);
                $sale_note_item->fill($row);
                $sale_note_item->sale_note_id = $this->sale_note->id;
                $sale_note_item->save();

                if(isset($row['lots'])){

                    foreach($row['lots'] as $lot) {
                        $record_lot = ItemLot::query()->findOrFail($lot['id']);
                        $record_lot->has_sale = true;
                        $record_lot->update();
                    }
                }

                // control de lotes

                $id_lote_selected = $this->getIdLoteSelectedItem($row);

                // si tiene lotes y no fue generado a partir de otro documento (pedido...)
                if($id_lote_selected && !$this->sale_note->isGeneratedFromExternalRecord())
                {
                    if(is_array($id_lote_selected))
                    {
                        // presentacion - factor de lista de precios
                        $quantity_unit = isset($sale_note_item->item->presentation->quantity_unit) ? $sale_note_item->item->presentation->quantity_unit : 1;

                        foreach ($id_lote_selected as $item)
                        {
                            $lot = ItemLotsGroup::query()->find($item['id']);
                            $lot->quantity = $lot->quantity - ($quantity_unit * $item['compromise_quantity']);
                            $this->validateStockLotGroup($lot, $sale_note_item);
                            $lot->save();
                        }

                    }
                    else {

                        $quantity_unit = 1;
                        if(isset($row['item']) && isset($row['item']['presentation'])&&isset($row['item']['presentation']['quantity_unit'])){
                            $quantity_unit = $row['item']['presentation']['quantity_unit'];
                        }
                        $lot = ItemLotsGroup::find($id_lote_selected);
                        $lot->quantity = ($lot->quantity - ($row['quantity'] * $quantity_unit));
                        $lot->save();
                    }

                }
                // control de lotes

            }

            //pagos
            $this->savePayments($this->sale_note, $data['payments'], $isUpdate);
            $this->saveFee($this->sale_note, $data['fee'] ?? []);

            $this->setFilename();
            $this->createPdf($this->sale_note,"a4", $this->sale_note->filename);
            $this->regularizePayments($data['payments']);
            DB::connection('tenant')->commit();

            return [
                'success' => true,
                'data' => [
                    'id' => $this->sale_note->id,
                    'number_full' => $this->sale_note->number_full,
                ],
                'links' => [
                    'print_ticket' => url('')."/sale-notes/print/{$this->sale_note->external_id}/ticket",
                ]
            ];

        }
        catch(Exception $e)
        {
            $this->generalWriteErrorLog($e);

            DB::connection('tenant')->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function saveFee($document, $fee)
    {
        // Al editar, reemplazar cuotas (evita acumular historial de crédito en el PDF).
        $document->fee()->delete();

        foreach ($fee as $row) {
            $document->fee()->create($row);
        }

        $document->unsetRelation('fee');
    }

    /**
     *
     * Obtener lote seleccionado
     *
     * @todo regularizar lots_group, no se debe guardar en bd, ya que tiene todos los lotes y no los seleccionados, reemplazar por IdLoteSelected
     *
     * @param  array $row
     * @return array
     */
    private function getIdLoteSelectedItem($row)
    {
        $id_lote_selected = null;

        if(isset($row['IdLoteSelected']))
        {
            $id_lote_selected = $row['IdLoteSelected'];
        }
        else
        {
            if(isset($row['item']['lots_group']))
            {
                $id_lote_selected = collect($row['item']['lots_group'])->where('compromise_quantity', '>', 0)->toArray();
            }
        }

        return $id_lote_selected;
    }


    /**
     *
     * Asignar lote a item (regularizar propiedad en json item)
     *
     * @param  array $row
     * @return void
     */
    private function setIdLoteSelectedToItem(&$row)
    {
        if(isset($row['IdLoteSelected']))
        {
            $row['item']['IdLoteSelected'] = $row['IdLoteSelected'];
        }
        else
        {
            $row['item']['IdLoteSelected'] = isset($row['item']['IdLoteSelected']) ? $row['item']['IdLoteSelected'] : null;
        }
    }


    private function regularizePayments($payments){

        $total_payments = collect($payments)->sum('payment');

        $balance = $this->sale_note->total - $total_payments;

        if($balance <= 0){

            $this->sale_note->total_canceled = true;
            $this->sale_note->save();

        }else{

            $this->sale_note->total_canceled = false;
            $this->sale_note->save();
        }

    }


    public function destroy_sale_note_item($id)
    {
        $item = SaleNoteItem::findOrFail($id);

        if(isset($item->item->lots)){

            foreach($item->item->lots as $lot) {
                // dd($lot->id);
                $record_lot = ItemLot::findOrFail($lot->id);
                $record_lot->has_sale = false;
                $record_lot->update();
            }

        }

        $item->delete();

        return [
            'success' => true,
            'message' => 'eliminado'
        ];
    }

    public function mergeData($inputs, $isUpdate = false)
    {

        $this->company = Company::active();

        // Para matricula, se busca el hijo en atributos
        $attributes = $inputs['attributes']??[];
        $children = $attributes['children_customer_id']??null;
        $type_period = isset($inputs['type_period']) ? $inputs['type_period'] : null;
        $quantity_period = isset($inputs['quantity_period']) ? $inputs['quantity_period'] : null;
        $d_of_issue = new Carbon($inputs['date_of_issue']);
        $automatic_date_of_issue = null;

        if($type_period && $quantity_period > 0){

            $add_period_date = ($type_period == 'month') ? $d_of_issue->addMonths($quantity_period): $d_of_issue->addYears($quantity_period);
            $automatic_date_of_issue = $add_period_date->format('Y-m-d');

        }

        if (key_exists('series_id', $inputs)) {
            $series = Series::query()->find($inputs['series_id'])->number;
        } else {
            $series = $inputs['series'];
        }

        $number = null;

        if($inputs['id'])
        {
            $number = $inputs['number'];
            $sale_note = SaleNote::find($inputs['id']);
        }
        else{

            $document = SaleNote::query()
                                ->select('number')->where('fiscal_environment', $this->company->fiscal_environment)
                                ->where('series', $series)
                                ->orderBy('number', 'desc')
                                ->first();

            $number = ($document) ? $document->number + 1 : 1;

            // Marca la serie (NV) como en uso al emitir (§4.7).
            Series::markInUse('80', $series);

        }
        $seller_id = isset($inputs['seller_id'])?(int)$inputs['seller_id']:0;
        if($seller_id == 0){
            $seller_id = auth()->id();
        }
        $additional_information = isset($inputs['additional_information'])?$inputs['additional_information']:'';


        $values = [
            'additional_information' => $additional_information,
            'automatic_date_of_issue' => $automatic_date_of_issue,
            'user_id' => $isUpdate ? $sale_note->user_id : auth()->id(),
            'seller_id' => $seller_id,
            'external_id' => Str::uuid()->toString(),
            'customer' => PersonInput::set($inputs['customer_id']),
            'establishment' => EstablishmentInput::set($inputs['establishment_id']),
            'fiscal_environment' => $this->company->fiscal_environment,
            'state_type_id' => '01',
            'series' => $series,
            'number' => $number
        ];
        if(!empty($children)){
            $customer = PersonInput::set($inputs['customer_id']);
            $customer['children'] = PersonInput::set($children);
            $values['customer'] = $customer;
        }

        $this->setDataPointSystemToValues($values, $inputs);


        unset($inputs['series_id']);

//        $inputs->merge($values);
        $inputs = array_merge($inputs, $values);

        if (trim(strip_tags(html_entity_decode($inputs['terms_condition'] ?? ''))) === '') {
            if (!array_key_exists('show_terms_condition', $inputs)
                || filter_var($inputs['show_terms_condition'], FILTER_VALIDATE_BOOLEAN)) {
                $configuration = Configuration::select('terms_condition_sale')->first();
                $inputs['terms_condition'] = $configuration->terms_condition_sale ?? '';
            }
        }

        return $inputs;
    }


    /**
     * Configuración de sistema por puntos
     *
     * @param  array $values
     * @param  array $inputs
     * @return void
     */
    private function setDataPointSystemToValues(&$values, $inputs)
    {
        $configuration = Configuration::getDataPointSystem();

        $created_from_pos = $inputs['created_from_pos'] ?? false;

        if($created_from_pos && $configuration->enabled_point_system)
        {
            $values['point_system'] = $configuration->enabled_point_system;
            $values['point_system_data'] = [
                'point_system_sale_amount' => $configuration->point_system_sale_amount,
                'quantity_of_points' => $configuration->quantity_of_points,
                'round_points_of_sale' => $configuration->round_points_of_sale,
            ];
        }
    }


//    public function recreatePdf($sale_note_id)
//    {
//        $this->sale_note = SaleNote::find($sale_note_id);
//        $this->createPdf();
//    }

    private function setFilename()
    {
        $name = [$this->sale_note->series,$this->sale_note->number,date('Ymd')];
        $this->sale_note->filename = join('-', $name);

        $this->sale_note->unique_filename = $this->sale_note->filename; //campo único para evitar duplicados

        $this->sale_note->save();
    }

    public function toPrint($external_id, $format) {


        $sale_note = SaleNote::where('external_id', $external_id)->first();

        if (!$sale_note) throw new Exception("El código {$external_id} es inválido, no se encontro la nota de venta relacionada");

        $this->reloadPDF($sale_note, $format, $sale_note->filename);
        $temp = tempnam(sys_get_temp_dir(), 'sale_note');

        file_put_contents($temp, $this->getStorage($sale_note->filename, 'sale_note'));

        /*
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$sale_note->filename.'.pdf'.'"'
        ];
        */

        return response()->file($temp, $this->generalPdfResponseFileHeaders($sale_note->filename));
    }

    private function reloadPDF($sale_note, $format, $filename) {
        $this->createPdf($sale_note, $format, $filename);
    }


    /**
     *
     * Obtener el ancho del ticket dependiendo del formato
     *
     * @param  string $format_pdf
     * @return int
     */
    public function getWidthTicket($format_pdf)
    {
        $width = 0;

        if(config('tenant.enabled_template_ticket_80'))
        {
            $width = 76;
        }
        else
        {
            switch ($format_pdf)
            {
                case 'ticket_58':
                    $width = 56;
                    break;
                default:
                    $width = 78;
                    break;
            }
        }

        return $width;
    }


    public function createPdf($sale_note = null, $format_pdf = null, $filename = null, $output = 'pdf')
    {
        ini_set("pcre.backtrack_limit", "5000000");
        $template = new Template();
        $pdf = new Mpdf();

        $this->company = ($this->company != null) ? $this->company : Company::active();
        $this->document = ($sale_note != null) ? $sale_note : $this->sale_note;

        $this->configuration = Configuration::first();
        // $configuration = $this->configuration->formats;
        $base_template = Establishment::find($this->document->establishment_id)->template_pdf;
        if (in_array($format_pdf, ['ticket', 'ticket_58', 'ticket_80'])) {
            $base_template = Establishment::find($this->document->establishment_id)->template_ticket_pdf;
        }

        $html = $template->pdf($base_template, "sale_note", $this->company, $this->document, $format_pdf);

        $pdf_margin_top = 2;
        $pdf_margin_right = 5;
        $pdf_margin_bottom = 0;
        $pdf_margin_left = 5;

        // if (($format_pdf === 'ticket') OR ($format_pdf === 'ticket_58'))
        if(in_array($format_pdf, ['ticket', 'ticket_58']))
        {
            // $width = ($format_pdf === 'ticket_58') ? 56 : 78 ;
            // if(config('tenant.enabled_template_ticket_80')) $width = 76;
            $width = $this->getWidthTicket($format_pdf);

            $company_logo      = ($this->company->logo) ? 40 : 0;
            $company_name      = (strlen($this->company->name) / 20) * 10;
            $company_address   = (strlen($this->document->establishment->address) / 30) * 10;
            $company_number    = $this->document->establishment->telephone != '' ? '10' : '0';
            $customer_name     = strlen($this->document->customer->name) > '25' ? '10' : '0';
            $customer_address  = (strlen($this->document->customer->address) / 200) * 10;
            $p_order           = $this->document->purchase_order != '' ? '10' : '0';

            $total_exportation = $this->document->total_exportation != '' ? '10' : '0';
            $total_free        = $this->document->total_free != '' ? '10' : '0';
            $total_unaffected  = $this->document->total_unaffected != '' ? '10' : '0';
            $total_exonerated  = $this->document->total_exonerated != '' ? '10' : '0';
            $total_taxed       = $this->document->total_taxed != '' ? '10' : '0';
            $quantity_rows     = count($this->document->items);
            $payments     = $this->document->payments()->count() * 2;
            $discount_global = 0;
            $extra_by_item_description = 0;
            foreach ($this->document->items as $it) {
                if(strlen($it->item->description)>100){
                    $extra_by_item_description +=24;
                }
                if ($it->discounts) {
                    $discount_global = $discount_global + 1;
                }
            }
            $legends = $this->document->legends != '' ? '10' : '0';
            $bank_accounts = BankAccount::count() * 6;
            $base_height = 120;

            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [
                    $width,
                    $base_height +
                    ($quantity_rows * 8)+
                    ($discount_global * 3) +
                    $company_logo +
                    $payments +
                    $company_name +
                    $company_address +
                    $company_number +
                    $customer_name +
                    $customer_address +
                    $p_order +
                    $legends +
                    $bank_accounts +
                    $total_exportation +
                    $total_free +
                    $total_unaffected +
                    $total_exonerated +
                    $extra_by_item_description +
                    $total_taxed],
                'margin_top' => $pdf_margin_top,
                'margin_right' => $pdf_margin_right,
                'margin_bottom' => $pdf_margin_bottom,
                'margin_left' => $pdf_margin_left,
                'default_font' => 'monospace'
            ]);
        } else if($format_pdf === 'a5'){

            $company_name      = (strlen($this->company->name) / 20) * 10;
            $company_address   = (strlen($this->document->establishment->address) / 30) * 10;
            $company_number    = $this->document->establishment->telephone != '' ? '10' : '0';
            $customer_name     = strlen($this->document->customer->name) > '25' ? '10' : '0';
            $customer_address  = (strlen($this->document->customer->address) / 200) * 10;
            $p_order           = $this->document->purchase_order != '' ? '10' : '0';

            $total_exportation = $this->document->total_exportation != '' ? '10' : '0';
            $total_free        = $this->document->total_free != '' ? '10' : '0';
            $total_unaffected  = $this->document->total_unaffected != '' ? '10' : '0';
            $total_exonerated  = $this->document->total_exonerated != '' ? '10' : '0';
            $total_taxed       = $this->document->total_taxed != '' ? '10' : '0';
            $quantity_rows     = count($this->document->items);
            $discount_global = 0;
            foreach ($this->document->items as $it) {
                if ($it->discounts) {
                    $discount_global = $discount_global + 1;
                }
            }
            $legends           = $this->document->legends != '' ? '10' : '0';


            $alto = ($quantity_rows * 8) +
                    ($discount_global * 3) +
                    $company_name +
                    $company_address +
                    $company_number +
                    $customer_name +
                    $customer_address +
                    $p_order +
                    $legends +
                    $total_exportation +
                    $total_free +
                    $total_unaffected +
                    $total_exonerated +
                    $total_taxed;
            $diferencia = 148 - (float)$alto;

            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [
                    210,
                    $diferencia + $alto
                    ],
                'margin_top' => 2,
                'margin_right' => 5,
                'margin_bottom' => 0,
                'margin_left' => 5,
                'default_font' => 'arial'
            ]);


       } else {

            $pdf_font_regular = config('tenant.pdf_name_regular');
            $pdf_font_bold = config('tenant.pdf_name_bold');

            if ($pdf_font_regular != false) {
                $defaultConfig = (new ConfigVariables())->getDefaults();
                $fontDirs = $defaultConfig['fontDir'];

                $defaultFontConfig = (new FontVariables())->getDefaults();
                $fontData = $defaultFontConfig['fontdata'];

                $pdf = new Mpdf([
                    'fontDir' => array_merge($fontDirs, [
                        app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                                DIRECTORY_SEPARATOR.'pdf'.
                                                DIRECTORY_SEPARATOR.$base_template.
                                                DIRECTORY_SEPARATOR.'font')
                    ]),
                    'fontdata' => $fontData + [
                        'custom_bold' => [
                            'R' => $pdf_font_bold.'.ttf',
                        ],
                        'custom_regular' => [
                            'R' => $pdf_font_regular.'.ttf',
                        ],
                    ],
                    'default_font' => 'arial'
                ]);
            }
            else {
                $pdf = new Mpdf([
                    'default_font' => 'arial'
                ]);
            }

        }

        $path_css = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                             DIRECTORY_SEPARATOR.'pdf'.
                                             DIRECTORY_SEPARATOR.$base_template.
                                             DIRECTORY_SEPARATOR.'style.css');

        $stylesheet = file_get_contents($path_css);

        // para impresion automatica
        if($output == 'html') return $this->getHtmlDirectPrint($pdf, $stylesheet, $html);

        $pdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

        if(config('tenant.pdf_template_footer')) {
            /* if (($format_pdf != 'ticket') AND ($format_pdf != 'ticket_58')) */
                if ($base_template != 'full_height') {
                    $html_footer = $template->pdfFooter($base_template,$this->document);
                } else {
                    $html_footer = $template->pdfFooter('default',$this->document);
                }
                if (($format_pdf === 'ticket') || ($format_pdf === 'ticket_58')) {
                    $pdf->WriteHTML($html_footer, HTMLParserMode::HTML_BODY);
                }else{
                    $pdf->SetHTMLFooter($html_footer);
                }
        }

        if ($base_template === 'brand') {

            if (($format_pdf === 'ticket') || ($format_pdf === 'ticket_58')) {
                $pdf->SetHTMLHeader("");
                $pdf->SetHTMLFooter("");
            }
        }

        $helper_facturalo = new HelperFacturalo();

        if($helper_facturalo->isAllowedAddDispatchTicket($format_pdf, 'sale-note', $this->document))
        {
            $helper_facturalo->addDocumentDispatchTicket($pdf, $this->company, $this->document, [
                $template,
                $base_template,
                $width,
                ($quantity_rows * 8) + $extra_by_item_description +200
            ]);
        }
        if($helper_facturalo->isAllowedAddDispatchTicketIndividual($format_pdf, 'sale-note'))
        {
            $helper_facturalo->addDocumentDispatchTicket($pdf, $this->company, $this->document, [
                $template,
                $base_template,
                $width,
                ($quantity_rows * 8) + $extra_by_item_description +200
            ], true);
        }


        $this->uploadFile($this->document->filename, $pdf->output('', 'S'), 'sale_note');
    }


    /**
     *
     * Html para impresion directa
     *
     * @param  Mpdf $pdf
     * @param  string $stylesheet
     * @param  string $html
     * @return string
     */
    public function getHtmlDirectPrint(&$pdf, $stylesheet, $html)
    {
        $path_html = app_path('CoreFacturalo' . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR . 'ticket_html.css');
        $ticket_html = file_get_contents($path_html);
        $pdf->WriteHTML($ticket_html, HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

        return "<style>".$ticket_html.$stylesheet."</style>".$html;
    }


    /**
     *
     * Impresión directa en pos
     *
     * @param  int $id
     * @param  string $format
     * @return string
     */
    public function toTicket($id, $format = 'ticket')
    {
        $document = SaleNote::find($id);

        if (!$document) throw new Exception("El código {$id} es inválido, no se encontro documento relacionado");

        return $this->createPdf($document, $format, $document->filename, 'html');
    }


    public function uploadFile($filename, $file_content, $file_type)
    {
        $this->uploadStorage($filename, $file_content, $file_type);
    }



    public function table($table)
    {
        switch ($table) {
            case 'customers':

                $customers = Person::whereType('customers')
                    ->whereIsEnabled()->orderBy('name')->take(20)->get()->transform(function(Person$row) {
                    return $row->getCollectionData();
                    return [
                        'id' => $row->id,
                        'description' => $row->number.' - '.$row->name,
                        'seller' => $row->seller,
                        'seller_id' => $row->seller_id,
                        'name' => $row->name,
                        'number' => $row->number,
                        'identity_document_type_id' => $row->identity_document_type_id,
                        'identity_document_type_code' => $row->identity_document_type->code
                    ];
                });
                return $customers;

                break;

            case 'items':

                return SearchItemController::getItemsToSaleNote();
                $establishment_id = auth()->user()->establishment_id;
                $warehouse = Warehouse::where('establishment_id', $establishment_id)->first();
                // $warehouse_id = ($warehouse) ? $warehouse->id:null;

                $items_u = Item::whereWarehouse()->whereIsActive()->whereNotIsSet()->orderBy('description')->take(20)->get();

                $items_s = Item::where('unit_type_id','ZZ')->whereIsActive()->orderBy('description')->take(10)->get();

                $items = $items_u->merge($items_s);

                return collect($items)->transform(function($row) use($warehouse){

                    /** @var Item $row */
                    return $row->getDataToItemModal($warehouse);
                    /* Movido al modelo */
                    $detail = $this->getFullDescription($row, $warehouse);
                    return [
                        'id' => $row->id,
                        'full_description' => $detail['full_description'],
                        'brand' => $detail['brand'],
                        'category' => $detail['category'],
                        'stock' => $detail['stock'],
                        'description' => $row->description,
                        'currency_type_id' => $row->currency_type_id,
                        'currency_type_symbol' => $row->currency_type->symbol,
                        'sale_unit_price' => round($row->sale_unit_price, 2),
                        'purchase_unit_price' => $row->purchase_unit_price,
                        'unit_type_id' => $row->unit_type_id,
                        'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                        'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                        'has_igv' => (bool) $row->has_igv,
                        'lots_enabled' => (bool) $row->lots_enabled,
                        'series_enabled' => (bool) $row->series_enabled,
                        'is_set' => (bool) $row->is_set,
                        'warehouses' => collect($row->warehouses)->transform(function($row) use($warehouse_id){
                            return [
                                'warehouse_id' => $row->warehouse->id,
                                'warehouse_description' => $row->warehouse->description,
                                'stock' => $row->stock,
                                'checked' => ($row->warehouse_id == $warehouse_id) ? true : false,
                            ];
                        }),
                        'item_unit_types' => $row->item_unit_types,
                        'lots' => [],
                        // 'lots' => $row->item_lots->where('has_sale', false)->where('warehouse_id', $warehouse_id)->transform(function($row) {
                        //     return [
                        //         'id' => $row->id,
                        //         'series' => $row->series,
                        //         'date' => $row->date,
                        //         'item_id' => $row->item_id,
                        //         'warehouse_id' => $row->warehouse_id,
                        //         'has_sale' => (bool)$row->has_sale,
                        //         'lot_code' => ($row->item_loteable_type) ? (isset($row->item_loteable->lot_code) ? $row->item_loteable->lot_code:null):null
                        //     ];
                        // }),
                        'lots_group' => collect($row->lots_group)->transform(function($row){
                            return [
                                'id'  => $row->id,
                                'code' => $row->code,
                                'quantity' => $row->quantity,
                                'date_of_due' => $row->date_of_due,
                                'checked'  => false
                            ];
                        }),
                        'lot_code' => $row->lot_code,
                        'date_of_due' => $row->date_of_due
                    ];
                });


                break;
            default:

                return [];

                break;
        }
    }


    public function searchItems(Request $request)
    {

        // dd($request->all());
        $establishment_id = auth()->user()->establishment_id;
        $warehouse = Warehouse::where('establishment_id', $establishment_id)->first();
        $warehouse_id = ($warehouse) ? $warehouse->id : null;
        /*
        $items_not_services = $this->getItemsNotServices($request);
        $items_services = $this->getItemsServices($request);
        $all_items = $items_not_services->merge($items_services);

        $items = collect($all_items)->transform(function($row) use($warehouse_id, $warehouse){
        */
        $items = SearchItemController::getItemsToSaleNote($request);

        /*
        $items = SearchItemController::getItemsToSaleNote($request)->transform(function ($row) use ($warehouse_id, $warehouse) {
            $detail = $this->getFullDescription($row, $warehouse);

            return [
                'id' => $row->id,
                'full_description' => $detail['full_description'],
                'brand' => $detail['brand'],
                'category' => $detail['category'],
                'stock' => $detail['stock'],
                'description' => $row->description,
                'currency_type_id' => $row->currency_type_id,
                'currency_type_symbol' => $row->currency_type->symbol,
                'sale_unit_price' => round($row->sale_unit_price, 2),
                'purchase_unit_price' => $row->purchase_unit_price,
                'unit_type_id' => $row->unit_type_id,
                'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                'has_igv' => (bool)$row->has_igv,
                'lots_enabled' => (bool)$row->lots_enabled,
                'series_enabled' => (bool)$row->series_enabled,
                'is_set' => (bool)$row->is_set,
                'warehouses' => collect($row->warehouses)->transform(function ($row) use ($warehouse_id) {
                    return [
                        'warehouse_id' => $row->warehouse->id,
                        'warehouse_description' => $row->warehouse->description,
                        'stock' => $row->stock,
                        'checked' => ($row->warehouse_id == $warehouse_id) ? true : false,
                    ];
                }),
                'item_unit_types' => $row->item_unit_types,
                'lots' => [],
                'lots_group' => collect($row->lots_group)->transform(function ($row) {
                    return [
                        'id' => $row->id,
                        'code' => $row->code,
                        'quantity' => $row->quantity,
                        'date_of_due' => $row->date_of_due,
                        'checked' => false
                    ];
                }),
                'lot_code' => $row->lot_code,
                'date_of_due' => $row->date_of_due
            ];
        });
*/
        return compact('items');

    }


    public function searchItemById($id)
    {
        return  SearchItemController::getItemsToSaleNote(null, $id);
        $establishment_id = auth()->user()->establishment_id;
        $warehouse = Warehouse::where('establishment_id', $establishment_id)->first();
        $search_item = $this->getItemsNotServicesById($id);

        if(count($search_item) == 0){
            $search_item = $this->getItemsServicesById($id);
        }

        $items = collect($search_item)->transform(function($row) use($warehouse){
            $detail = $this->getFullDescription($row, $warehouse);
            return [
                'id' => $row->id,
                'full_description' => $detail['full_description'],
                'brand' => $detail['brand'],
                'category' => $detail['category'],
                'stock' => $detail['stock'],
                'description' => $row->description,
                'currency_type_id' => $row->currency_type_id,
                'currency_type_symbol' => $row->currency_type->symbol,
                'sale_unit_price' => round($row->sale_unit_price, 2),
                'purchase_unit_price' => $row->purchase_unit_price,
                'unit_type_id' => $row->unit_type_id,
                'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                'has_igv' => (bool)$row->has_igv,
                'lots_enabled' => (bool)$row->lots_enabled,
                'series_enabled' => (bool)$row->series_enabled,
                'is_set' => (bool)$row->is_set,
                'warehouses' => collect($row->warehouses)->transform(function ($row) use ($warehouse) {
                    return [
                        'warehouse_id' => $row->warehouse->id,
                        'warehouse_description' => $row->warehouse->description,
                        'stock' => $row->stock,
                        'checked' => ($row->warehouse_id == $warehouse->id) ? true : false,
                    ];
                }),
                'item_unit_types' => $row->item_unit_types,
                'lots' => [],
                'lots_group' => collect($row->lots_group)->transform(function ($row) {
                    return [
                        'id' => $row->id,
                        'code' => $row->code,
                        'quantity' => $row->quantity,
                        'date_of_due' => $row->date_of_due,
                        'checked' => false
                    ];
                }),
                'lot_code' => $row->lot_code,
                'date_of_due' => $row->date_of_due
            ];
        });

        return compact('items');
    }


    public function getFullDescription($row, $warehouse){

        $desc = ($row->internal_id)?$row->internal_id.' - '.$row->description : $row->description;
        $category = ($row->category) ? "{$row->category->name}" : "";
        $brand = ($row->brand) ? "{$row->brand->name}" : "";

        if($row->unit_type_id != 'ZZ')
        {
            $warehouse_stock = ($row->warehouses && $warehouse) ? number_format($row->warehouses->where('warehouse_id', $warehouse->id)->first() != null ? $row->warehouses->where('warehouse_id', $warehouse->id)->first()->stock : 0 ,2) : 0;
            $stock = ($row->warehouses && $warehouse) ? "{$warehouse_stock}" : "";
        }
        else{
            $stock = '';
        }


        $desc = "{$desc} - {$brand}";

        return [
            'full_description' => $desc,
            'brand' => $brand,
            'category' => $category,
            'stock' => $stock,
        ];
    }


    public function searchCustomerById($id)
    {
        return $this->searchClientById($id);

    }

    public function option_tables()
    {
        $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
        // Series filtradas por contexto (oculta dedicadas / restringe al grupo activo). Ver SeriesResolver.
        $series = app(SeriesResolver::class)->applyContext(Series::where('establishment_id', $establishment->id))->get();
        // ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        $document_types_invoice = DocumentType::whereIn('id', ['01'])->get();
        // ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
        $payment_method_types = PaymentMethodType::all();
        $payment_destinations = $this->getPaymentDestinations();
        $sellers = User::GetSellers(false)->get();
        $configuration = Configuration::select(['restrict_sale_items_cpe', 'global_discount_type_id','restrict_receipt_date', 'shipping_time_days'])->first();
        $global_discount_types = ChargeDiscountType::getGlobalDiscounts();

        return compact('series', 'document_types_invoice', 'payment_method_types', 'payment_destinations','sellers', 'configuration', 'global_discount_types');
    }

    public function email(Request $request)
    {
        $company = Company::active();
        $record = SaleNote::find($request->input('id'));
        $customer_email = $request->input('customer_email');

        $email = $customer_email;
        $mailable = new SaleNoteEmail($company, $record);
        $id = (int) $request->id;
        $sendIt = EmailController::SendMail($email, $mailable, $id, 2);
        /*
        Configuration::setConfigSmtpMail();
        $array_email = explode(',', $customer_email);
        if (count($array_email) > 1) {
            foreach ($array_email as $email_to) {
                $email_to = trim($email_to);
                if(!empty($email_to)) {
                    Mail::to($email_to)->send(new SaleNoteEmail($company, $record));
                }
            }
        } else {
            Mail::to($customer_email)->send(new SaleNoteEmail($company, $record));
        }*/

        return [
            'success' => true
        ];
    }


    public function dispatches()
    {
        $dispatches = Dispatch::latest()->get(['id','series','number'])->transform(function($row) {
            return [
                'id' => $row->id,
                'series' => $row->series,
                'number' => $row->number,
                'number_full' => "{$row->series}-{$row->number}",
            ];
        }); ;

        return $dispatches;
    }

    public function enabledConcurrency(Request $request)
    {

        $sale_note = SaleNote::findOrFail($request->id);
        $sale_note->enabled_concurrency = $request->enabled_concurrency;
        $sale_note->update();

        return [
            'success' => true,
            'message' => ($sale_note->enabled_concurrency) ? 'Recurrencia activada':'Recurrencia desactivada'
        ];

    }

    public function anulate($id)
    {

        DB::connection('tenant')->transaction(function () use ($id) {

            $obj =  SaleNote::find($id);
            $obj->state_type_id = 11;
            $obj->save();

            // $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
            $warehouse = Warehouse::where('establishment_id',$obj->establishment_id)->first();

            foreach ($obj->items as $sale_note_item) {

                // voided sets
                $this->voidedSaleNoteItem($sale_note_item, $warehouse);
                // voided sets

                //habilito las series
                // ItemLot::where('item_id', $item->item_id )->where('warehouse_id', $warehouse->id)->update(['has_sale' => false]);
                $this->voidedLots($sale_note_item);

            }

        });

        return [
            'success' => true,
            'message' => 'N. Venta anulada con éxito'
        ];


    }

    public function voidedSaleNoteItem($sale_note_item, $warehouse)
    {

        $warehouse_id = ($sale_note_item->warehouse_id) ? $sale_note_item->warehouse_id : $warehouse->id;

        if(!$sale_note_item->item->is_set){

            $presentationQuantity = (!empty($sale_note_item->item->presentation)) ? $sale_note_item->item->presentation->quantity_unit : 1;

            $sale_note_item->sale_note->inventory_kardex()->create([
                'date_of_issue' => date('Y-m-d'),
                'item_id' => $sale_note_item->item_id,
                'warehouse_id' => $warehouse_id,
                'quantity' => $sale_note_item->quantity * $presentationQuantity,
            ]);

            $wr = ItemWarehouse::where([['item_id', $sale_note_item->item_id],['warehouse_id', $warehouse_id]])->first();

            if($wr)
            {
                $wr->stock =  $wr->stock + ($sale_note_item->quantity * $presentationQuantity);
                $wr->save();
            }

        }else{

            $item = Item::findOrFail($sale_note_item->item_id);

            foreach ($item->sets as $it) {

                $ind_item  = $it->individual_item;
                $item_set_quantity  = ($it->quantity) ? $it->quantity : 1;
                $presentationQuantity = 1;
                $warehouse = $this->findWarehouse($sale_note_item->sale_note->establishment_id);
                $this->createInventoryKardexSaleNote($sale_note_item->sale_note, $ind_item->id , (1 * ($sale_note_item->quantity * $presentationQuantity * $item_set_quantity)), $warehouse->id, $sale_note_item->id);
                if(!$sale_note_item->sale_note->order_note_id) $this->updateStock($ind_item->id , (1 * ($sale_note_item->quantity * $presentationQuantity * $item_set_quantity)), $warehouse->id);

            }

        }

    }


    /**
     *
     * Totales de nota venta, se visualiza en el listado
     *
     * @param  Request $request
     * @return array
     */
    public function totals(Request $request)
    {
        $query = $this->getRecords($request)->whereStateTypeAccepted()->whereFilterWithOutRelations()->filterCurrencyPen();

        $total_pen = $query->sum('total');

        $sale_notes_id = $query->select('id')->get()->pluck('id')->toArray();

        $total_paid_pen = SaleNotePayment::sumPaymentsBySaleNote($sale_notes_id);

        $total_pending_paid_pen = $total_pen - $total_paid_pen;

        return [
            'total_pen' => number_format($total_pen, 2, ".", ""),
            'total_paid_pen' => number_format($total_paid_pen, 2, ".", ""),
            'total_pending_paid_pen' => number_format($total_pending_paid_pen, 2, ".", "")
        ];
    }


    public function downloadExternal($external_id, $format = 'a4')
    {
        $document = SaleNote::where('external_id', $external_id)->first();
        $this->reloadPDF($document, $format, null);
        return $this->downloadStorage($document->filename, 'sale_note');

    }


    public function savePayments($sale_note, $payments, $isUpdate = false){

        $total = $sale_note->total;
        $balance = $total - collect($payments)->sum('payment');

        $search_cash = ($balance < 0) ? collect($payments)->firstWhere('payment_method_type_id', '01') : null;

        $this->apply_change = false;

        if($balance < 0 && $search_cash){

            $payments = collect($payments)->map(function($row) use($balance){

                $change = null;
                $payment = $row['payment'];

                if($row['payment_method_type_id'] == '01' && !$this->apply_change){

                    $change = abs($balance);
                    $payment = $row['payment'] - abs($balance);
                    $this->apply_change = true;

                }

                return [
                    "id" => null,
                    "document_id" => null,
                    "sale_note_id" => null,
                    "date_of_payment" => $row['date_of_payment'],
                    "payment_method_type_id" => $row['payment_method_type_id'],
                    "reference" => $row['reference'],
                    "payment_destination_id" => isset($row['payment_destination_id']) ? $row['payment_destination_id'] : null,
                    "payment_filename" => isset($row['payment_filename']) ? $row['payment_filename'] : null,
                    "change" => $change,
                    "payment" => $payment
                ];

            });
        }

        // dd($payments, $balance, $this->apply_change);

        foreach ($payments as $row) {

            if($balance < 0 && !$this->apply_change){
                $row['change'] = abs($balance);
                $row['payment'] = $row['payment'] - abs($balance);
                $this->apply_change = true;
            }

            $record_payment = $sale_note->payments()->create($row);

            if(isset($row['payment_destination_id'])){
                $this->createGlobalPayment($record_payment, $row);
                if($isUpdate){
                    $this->createCashDocumentPayment($record_payment,false);
                }
            }

            if(isset($row['payment_filename'])){
                $record_payment->payment_file()->create([
                    'filename' => $row['payment_filename']
                ]);
            }

            // para carga de voucher
            $this->saveFilesFromPayments($row, $record_payment, 'sale_notes');
        }
    }


    private function voidedLots($item){

        $i_lots_group = isset($item->item->lots_group) ? $item->item->lots_group:[];
        $lot_group_selecteds_filter = collect($i_lots_group)->where('compromise_quantity', '>', 0);
        $lot_group_selecteds =  $lot_group_selecteds_filter->all();

        if(count($lot_group_selecteds) > 0){

            foreach ($lot_group_selecteds as $lt) {
                $lot = ItemLotsGroup::find($lt->id);
                $lot->quantity = $lot->quantity + $lt->compromise_quantity;
                $lot->save();
            }

        }

        if(isset($item->item->lots)){
            foreach ($item->item->lots as $it) {
                if($it->has_sale == true){
                    $ilt = ItemLot::find($it->id);
                    $ilt->has_sale = false;
                    $ilt->save();
                }
            }
        }
    }

    public function saleNotesByClient(Request $request)
    {
        $request->validate([
            'client_id' => 'required|numeric|min:1',
        ]);
        $clientId = $request->client_id;
        $records = SaleNote::without(['user', 'fiscal_environment_type', 'state_type', 'currency_type', 'payments'])
                            ->select('series', 'number', 'id', 'date_of_issue', 'total')
                            ->where('customer_id', $clientId)
                            ->whereNull('document_id')
                            ->whereIn('state_type_id', ['01', '03', '05'])
                            ->orderBy('number', 'desc');

        $dateOfIssue = $request->date_of_issue;
        $dateOfDue = $request->date_of_due;
        if ($dateOfIssue&&!$dateOfDue) {
            $records = $records->where('date_of_issue', $dateOfIssue);
        }

        if ($dateOfIssue&&$dateOfDue) {
            $records = $records->whereBetween('date_of_issue', [$dateOfIssue,$dateOfDue]);
        }
        $sum_total=0;
        $records = $records->take(20)
            ->get();
        $sum_total=number_format($records->sum('total'),2);
        return response()->json([
            'success' => true,
            'data' => $records,
            'sum_total' => $sum_total,
        ], 200);
    }

    public function getItemsFromNotes(Request $request)
    {
        $request->validate([
            'notes_id' => 'required|array',
        ]);

        if($request->select_all){

            $items = SaleNoteItem::whereIn('sale_note_id', $request->notes_id)->get();

        }else{

            $items = SaleNoteItem::whereIn('sale_note_id', $request->notes_id)
                    ->select('item_id', 'quantity','unit_price','item')
                    ->get();
        }


        return response()->json([
            'success' => true,
            'data' => $items,
        ], 200);
    }


    public function getConfigGroupItems()
    {
        return [
            'group_items_generate_document' => Configuration::select('group_items_generate_document')->first()->group_items_generate_document
        ];
    }

    /**
     * Proceso de duplicar una nota de venta por post
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function duplicate(Request $request)
    {
        // return $request->id;
        $obj = SaleNote::find($request->id);
        $this->sale_note = $obj->replicate();
        $this->sale_note->external_id = Str::uuid()->toString();
        $this->sale_note->state_type_id = '01' ;
        $this->sale_note->number = SaleNote::getLastNumberByModel($obj) ;
        $this->sale_note->unique_filename = null;
        $this->sale_note->date_of_issue = now()->toDateTimeString();
        $this->sale_note->due_date = now()->toDateTimeString();
        $this->sale_note->time_of_issue = now()->toTimeString();

        $this->sale_note->changed = false;
        $this->sale_note->document_id = null;

        $this->sale_note->save();

        foreach($obj->items as $row)
        {
            $new = $row->replicate();
            $new->sale_note_id = $this->sale_note->id;
            $new->save();
        }

        $this->setFilename();

        return [
            'success' => true,
            'data' => [
                'id' => $this->sale_note->id,
            ],
        ];

    }




    /**
     * Retorna arreglo para generar nota de venta desde ecommerce
     *
     * @param Request $request
     * @return array
     */
    public function transformDataOrder(Request $request){

        $data = SaleNoteHelper::transformForOrder($request->all());

        return [
            'data' => $data
        ];

    }


    /**
     * Retorna items para generar json en checkout de hoteles
     *
     * @param Request $request
     * @return array
     */
    public function getItemsByIds(Request $request)
    {
        return SearchItemController::TransformToModalSaleNote(Item::whereIn('id', $request->ids)->get());
    }

    /**
     * Despachos de la nv
     *
     * @param  int $sale_note_id
     * @return array
     */
    public function recordsDispatch($sale_note_id)
    {
        $sale_note = SaleNote::whereFilterWithOutRelations()
                                ->with(['dispatch_sale'])
                                ->select([
                                    'id',
                                    'number',
                                    'series'
                                ])
                                ->findOrFail($sale_note_id);

        return [
            'id' => $sale_note->id,
            'number_full' => $sale_note->number_full,
            'status_dispatch' => $sale_note->getStatusDispatch(),
            'records' => new DispatchSaleNoteCollection($sale_note->dispatch_sale),
        ];

        /*
        $records = DispatchSaleNote::where('sale_note_id', $sale_note_id)->get();

        return new DispatchSaleNoteCollection($records);
        */

    }

    public function recordDispatch(Request $request)
    {
        $id = $request->input('id');

        $record = DispatchSaleNote::firstOrNew(['id' => $id]);
        $record->fill($request->all());
        $record->save();
        return [
            'success' => true,
            'message' => ($id)?'Despacho editado con éxito':'Despacho registrado con éxito'
        ];
    }

    public function recordsDispatchNote($dispatch_id)
    {
        $records = DispatchSaleNote::where('id',$dispatch_id)->get();

        return new DispatchSaleNoteCollection($records);
    }

    public function statusUpdate(Request $request){
        //dd($request->all());
        $id = $request->input('dispatch_id');

        $records = DispatchSaleNote::find($id);

        $records = $records->update([
            'status' => $request->input('status_display'),
        ]);

        return [
            'success' => true,
            'message' => 'Se actualizo el estado de despacho con éxito'
        ];

    }

    public function destroyStatus($id)
    {
        $records = DispatchSaleNote::find($id);
        $records = $records->delete();
        return [
            'success' => true,
            'message' => 'Despacho eliminado con exito'
        ];
    }
    /**
     * Elimina la relación con factura (problema antiguo respecto un nuevo campo en notas de venta que se envía de forma incorrecta a la factura siendo esta rechazada)
     * No se previene el error en este metodo
     *
     *
     */
    public function deleteRelationInvoice(Request $request) {
        // dd($request->all());
        try {
            $sale_note = SaleNote::find($request->id);

            $document = Document::find($sale_note->document_id);
            $document->sale_note_id = null;
            $document->save();

            $sale_note->changed = 0;
            $sale_note->document_id = null;
            $sale_note->save();
        }catch(RequestException $e){
            return ['success' => false];
        }

        return ['success' => true];
    }


    /**
     *
     * Data para generar cpe desde nv
     *
     * @param  int $id
     * @return SaleNoteGenerateDocumentResource
     */
    // public function recordGenerateDocument($id)
    // {
    //     return new SaleNoteGenerateDocumentResource(SaleNote::findOrFail($id));
    // }


}
