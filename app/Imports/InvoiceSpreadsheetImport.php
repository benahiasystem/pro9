<?php

namespace App\Imports;

use App\Services\{SalesDocumentTypePolicy, Fiscal\FiscalIdentity};
use App\Support\Exact\{Decimal as D, LineCalculation, XlsxDecimals};
use Carbon\Carbon;
use GuzzleHttp\{Client, ClientInterface};
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\{Importable, ToCollection, WithEvents};
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Cell\{Coordinate, DataType};
use PhpOffice\PhpSpreadsheet\Shared\Date;

// ######## INICIO CALCULOS EXACTOS SAB ########
/** The three invoice layouts share the original XLSX boundary and the existing API. */
abstract class InvoiceSpreadsheetImport implements ToCollection, WithEvents
{
    use Importable;

    protected const FORMAT = '';
    protected array $data = [];
    private bool $originalRestored = false;

    public function __construct(private ?string $sourcePath = null, private ?ClientInterface $client = null) {}

    public function registerEvents(): array
    {
        return [BeforeSheet::class => function ($event): void {
            if ($this->sourcePath === null) $this->invalid('Falta el XLSX original para conservar los decimales.');
            $sheet = $event->sheet->getDelegate();
            $book = $sheet->getParent();
            if ($book->getSheetCount() !== 1) $this->invalid('La importación de facturas requiere un XLSX con una sola hoja.');
            $columns = match (static::FORMAT) {
                'excel' => [11,12,13,14],
                'first' => [12,13,15,24,25,26,27,29,30,31,32],
                'second' => [6,7,8,13,14,15,16,19,20,21,22],
            };
            XlsxDecimals::restore($book, $this->sourcePath, $columns, $book->getIndex($sheet));
            foreach ($sheet->getRowIterator(2) as $row) foreach ($columns as $column) {
                $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column + 1).$row->getRowIndex());
                if ($cell->getDataType() === DataType::TYPE_FORMULA) $this->invalid('No se admiten fórmulas en cantidades e importes de facturas.');
            }
            $this->originalRestored = true;
        }];
    }

    public function collection(Collection $rows)
    {
        if (!$this->originalRestored) $this->invalid('Lea el XLSX original antes de importar facturas.');
        $records = [];
        foreach ($rows->slice(1) as $index => $row) {
            $values = $row instanceof Collection ? $row->all() : (array) $row;
            if (!array_filter($values, static fn ($value) => $value !== null && $value !== '')) continue;
            $records[] = $this->record($values, $index + 1);
        }
        // Validate the entire workbook before the first API request (including retired types).
        $payloads = [];
        $groups = static::FORMAT === 'excel' ? collect($records)->groupBy('folio')->all() : array_map(static fn ($row) => [$row], $records);
        foreach ($groups as $group) $payloads[] = $this->payload(collect($group)->all());
        $registered = 0; $results = [];
        foreach ($payloads as $payload) {
            try {
                $response = ($this->client ?? new Client())->request('POST', url('/api/documents'), [
                    'headers' => ['Accept'=>'application/json','Authorization'=>'Bearer '.auth()->user()->api_token],
                    'http_errors'=>false,
                    'json'=>$payload,
                ]);
                $answer = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $ok = $response->getStatusCode()>=200 && $response->getStatusCode()<300 && ($answer['success'] ?? false) === true;
                if ($ok) $registered++;
                $message = $ok ? 'Registrado' : $this->failureMessage($answer['message'] ?? $answer['errors'] ?? null);
            } catch (\Exception $error) {
                $message = $error->getMessage();
            }
            $results[] = ['folio'=>$payload['folio'] ?? $payload['serie_documento'].'-'.$payload['numero_documento'],'message'=>$message];
        }
        $total = count($payloads);
        $this->data = ['total'=>$total,'total_records'=>$total,'registered'=>$registered,'data'=>$results];
    }

    private function failureMessage($value): string
    {
        $pending=[[$value,0]];
        for ($visited=0;$pending && $visited<100;$visited++) {
            [$current,$depth]=array_shift($pending);
            if (is_string($current) && trim($current)!=='') return $current;
            if (is_array($current) && $depth<8) foreach ($current as $child) $pending[]=[$child,$depth+1];
        }
        return 'No se registró la factura.';
    }

    private function record(array $row, int $rowNumber): array
    {
        $excel = static::FORMAT === 'excel'; $first = static::FORMAT === 'first';
        $type = trim((string) ($excel ? request('document_type_id') : ($row[$first ? 2 : 0] ?? '')));
        if (in_array($type, ['1','0001'], true)) $type = '01';
        SalesDocumentTypePolicy::assertAllowedForFlow($type, ['01']);
        $reference = $excel ? [trim((string) request('series')), '#'] : FiscalIdentity::parseNumberFull((string) ($row[$first ? 3 : 1] ?? ''));
        if (!$reference || $reference[0] === '') $this->invalid("Fila $rowNumber: indique una serie y número válidos.");
        $date = $row[$excel ? 2 : ($first ? 5 : 2)] ?? '';
        $date = static::FORMAT === 'second' ? Carbon::createFromFormat('!d/m/Y', trim((string) $date)) : Carbon::instance(Date::excelToDateTimeObject($date));
        $currency = strtoupper(trim((string) ($row[$excel ? 8 : ($first ? 11 : 5)] ?? '')));
        if ($currency === 'S') $currency = 'VES';
        if (!in_array($currency, ['VES','USD'], true)) $this->invalid("Fila $rowNumber: use VES o USD.");
        $identity = trim((string) ($row[$excel ? 3 : ($first ? 9 : 3)] ?? ''));
        $identityType = match (strlen($identity)) {11=>'6',8=>'1',default=>'0'};
        foreach (\App\Support\Venezuela\IdentityDocument::TYPES as $definition) {
            $prefix = $definition['code'];
            if ($prefix && preg_match('/^'.preg_quote($prefix,'/').'\s*-?\s*\S+$/i',$identity)) {
                $identityType = $definition['id'];
                $identity = \App\Support\Venezuela\IdentityDocument::normalizeNumber($identityType,$identity);
                break;
            }
        }
        if ($identityType === '0') {
            if ($identity !== '' && !preg_match('/^0+$/D', $identity)) $this->invalid("Fila $rowNumber: identificación de cliente inválida.");
            $identity = '00000000';
        }
        $record = [
            'folio'=>$excel ? trim((string) ($row[0] ?? '')) : null,
            'series'=>$reference[0], 'number'=>$reference[1], 'date'=>$date->format('Y-m-d'), 'currency'=>$currency,
            'purchase_order'=>$excel ? trim((string) ($row[1] ?? '')) : '-',
            'customer'=>['codigo_tipo_documento_identidad'=>$identityType,'numero_documento'=>$identity,
                'apellidos_y_nombres_o_razon_social'=>trim((string) ($row[$excel ? 4 : ($first ? 10 : 4)] ?? 'SIN NOMBRE')),
                'codigo_pais'=>'VE','ubigeo'=>config('tenant.ubigeo_default_invoice_import'),
                'direccion'=>trim((string) ($row[$excel ? 5 : ($first ? 20 : 9)] ?? '')),
                'correo_electronico'=>$excel ? trim((string) ($row[6] ?? '')) : '', 'telefono'=>''],
        ];
        if ($excel && $record['folio'] === '') $this->invalid("Fila $rowNumber: el folio es obligatorio.");
        $plate = trim((string) ($row[$excel ? 7 : ($first ? 21 : 10)] ?? ''));
        $start = $excel ? 9 : ($first ? 22 : 11);
        $items = [$this->item($row, $start, $currency, $plate, $rowNumber)];
        if (!$excel && trim((string) ($row[$first ? 28 : 17] ?? '')) !== '') {
            // First layout shares the unit with the first line; the second layout has its own.
            $second = $first ? [$row[28], $row[23], $row[29] ?? null, $row[30] ?? null, $row[31] ?? null, $row[32] ?? null] : array_slice($row,17,6);
            $items[] = $this->item($second,0,$currency,'',$rowNumber);
        }
        $record['items'] = $items;
        if (!$excel) {
            $totals = [$row[$first ? 12 : 6] ?? null,$row[$first ? 13 : 7] ?? null,$row[$first ? 15 : 8] ?? null];
            foreach (['_base','_tax','_total'] as $index=>$key) $this->assertDeclared($totals[$index],D::sum(array_column($items,$key)),"Fila $rowNumber: cabecera $key");
        }
        return $record;
    }

    private function item(array $row, int $start, string $currency, string $plate, int $rowNumber): array
    {
        $description = trim((string) ($row[$start] ?? ''));
        if ($description === '') $this->invalid("Fila $rowNumber: la descripción es obligatoria.");
        $unit = strtoupper(trim((string) ($row[$start + 1] ?? '')));
        $unit = match ($unit) {'GLNS','GLN'=>'GLL','LT'=>'LTR',default=>$unit === '' ? 'UND' : $unit};
        $quantity = D::input(trim((string) ($row[$start + 2] ?? '')), 'quantity');
        $price = D::input(trim((string) ($row[$start + 3] ?? '')), 'unit_price');
        if (D::of($quantity)->isLessThanOrEqualTo(0) || D::of($price)->isNegative()) $this->invalid("Fila $rowNumber: cantidad o precio inválido.");
        $percentage = (string) \App\Support\Venezuela\Localization::taxPercentage();
        $line = LineCalculation::calculate(['quantity'=>$quantity,'unit_price'=>$price,'currency_type_id'=>$currency],$percentage);
        $this->assertDeclared($row[$start + 4] ?? null,D::read($line,'total_base_igv'),"Fila $rowNumber: base");
        $this->assertDeclared($row[$start + 5] ?? null,D::read($line,'total_igv'),"Fila $rowNumber: IVA");
        // The layouts have no product-code column. A full-description/unit fingerprint
        // avoids silently reusing a different item that shares its first ten characters.
        return ['codigo_interno'=>'XLSX-'.substr(hash('sha256',$description.'|'.$unit),0,24),'descripcion'=>$description,'codigo_producto_sunat'=>'','unidad_de_medida'=>$unit,
            'cantidad'=>D::represent($quantity),'valor_unitario'=>$line['unit_value'],'precio_unitario'=>D::represent($price),
            'price_input'=>['amount'=>D::represent($price),'currency_type_id'=>$currency,'includes_iva'=>true],
            'codigo_tipo_precio'=>'01','codigo_tipo_afectacion_igv'=>'10','porcentaje_igv'=>$percentage,
            'total_base_igv'=>$line['total_base_igv'],'total_valor_item'=>$line['total_value'],'total_igv'=>$line['total_igv'],
            'total_impuestos'=>$line['total_taxes'],'total_item'=>$line['total'],
            '_base'=>D::fraction(D::read($line,'total_base_igv')),'_tax'=>D::fraction(D::read($line,'total_igv')),'_total'=>D::fraction(D::read($line,'total')),
            'datos_adicionales'=>$plate === '' ? [] : [['codigo'=>'5010','descripcion'=>'Número de Placa','valor'=>$plate,'fecha_inicio'=>'','fecha_fin'=>'','duracion'=>'']]];
    }

    private function payload(array $group): array
    {
        $first = $group[0]; $items = [];
        foreach ($group as $record) {
            foreach (['series','number','date','currency','purchase_order','customer'] as $key) {
                if ($first[$key] !== $record[$key]) $this->invalid('Las filas de un folio deben tener la misma cabecera.');
            }
            array_push($items,...$record['items']);
        }
        $base = D::sum(array_column($items,'_base')); $tax = D::sum(array_column($items,'_tax')); $total = D::sum(array_column($items,'_total'));
        $items = array_map(static function ($item) { unset($item['_base'],$item['_tax'],$item['_total']); return $item; },$items);
        return ['serie_documento'=>$first['series'],'numero_documento'=>$first['number'],'fecha_de_emision'=>$first['date'],'hora_de_emision'=>'11:00:00',
            'codigo_tipo_operacion'=>'0101','codigo_tipo_documento'=>'01','codigo_tipo_moneda'=>$first['currency'],'fecha_de_vencimiento'=>$first['date'],
            'numero_orden_de_compra'=>$first['purchase_order'],'folio'=>$first['folio'],
            'totales'=>['total_exportacion'=>'0','total_operaciones_gravadas'=>D::represent($base),'total_operaciones_inafectas'=>'0','total_operaciones_exoneradas'=>'0',
                'total_operaciones_gratuitas'=>'0','total_igv'=>D::represent($tax),'total_impuestos'=>D::represent($tax),'total_valor'=>D::represent($base),
                'total_venta'=>D::represent($total)],
            'datos_del_emisor'=>['codigo_del_domicilio_fiscal'=>'0000'],'datos_del_cliente_o_receptor'=>$first['customer'],'items'=>$items,
            'acciones'=>['enviar_email'=>$first['customer']['correo_electronico'] !== '']];
    }

    private function assertDeclared($value, $expected, string $label): void
    {
        if ($value === null || $value === '') return; // Optional totals are recalculated by the API.
        $declared = D::of(D::input(trim((string) $value),$label));
        // Existing spreadsheets may transport cents; the original price and quantity remain exact.
        if (!in_array(D::represent($declared),[D::represent($expected),D::represent($expected,2)],true)) $this->invalid($label.' no coincide con cantidad, precio e IVA.');
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file'=>$message]);
    }

    public function getData(): array { return $this->data; }
}
// ######## FIN CALCULOS EXACTOS SAB ########
