<?php

namespace Tests\Unit;

use App\Imports\{DocumentImportExcelFormat, DocumentsImport, DocumentsImportTwoFormat};
use App\Support\Exact\{Decimal as D, LineCalculation};
use GuzzleHttp\{Client, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\{Spreadsheet, Writer\Xlsx};
use PhpOffice\PhpSpreadsheet\Cell\{Coordinate, DataType};

/** Real Laravel Excel/XLSX path, original OOXML numbers, controlled API transport, no database. */
final class InvoiceSpreadsheetImportTest extends \Tests\TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) if (is_file($file)) unlink($file);
        parent::tearDown();
    }

    private function workbook(array $rows, array $raw = [], bool $multipleSheets = false): string
    {
        $file = tempnam(sys_get_temp_dir(),'pro9-invoice-xlsx-'); $this->files[] = $file;
        $book = new Spreadsheet(); $sheet = $book->getActiveSheet();
        $sheet->fromArray(array_fill(0,33,'Encabezado'),null,'A1');
        foreach ($rows as $index=>$row) foreach ($row as $column=>$value) {
            if ($value === null) continue;
            $sheet->getCell(Coordinate::stringFromColumnIndex($column + 1).($index + 2))->setValueExplicit((string) $value,
                str_starts_with((string) $value,'=') ? DataType::TYPE_FORMULA : DataType::TYPE_STRING);
        }
        if ($multipleSheets) $book->createSheet()->setCellValue('A2','03')->setCellValue('N2','=1/3');
        (new Xlsx($book))->save($file); $book->disconnectWorksheets();
        if ($raw) {
            $zip = new \ZipArchive(); self::assertTrue($zip->open($file)); $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            foreach ($raw as $coordinate=>$number) {
                $pattern = '~<c\b[^>]*\br="'.preg_quote($coordinate,'~').'"[^>]*>.*?</c>~s';
                $xml = preg_replace_callback($pattern,static fn () => '<c r="'.$coordinate.'" t="n"><v>'.$number.'</v></c>',$xml,1,$count);
                self::assertSame(1,$count,$coordinate);
            }
            self::assertTrue($zip->addFromString('xl/worksheets/sheet1.xml',$xml)); self::assertTrue($zip->close());
        }
        return $file;
    }

    private function transport(array &$history, array $responses = []): Client
    {
        Auth::shouldReceive('user')->andReturn((object) ['api_token'=>'private-test-token']);
        $stack = HandlerStack::create(new MockHandler($responses ?: [new Response(200,[],json_encode(['success'=>true]))]));
        $stack->push(Middleware::history($history));
        $this->app['request']->merge(['document_type_id'=>'01','series'=>'FF01']);
        return new Client(['handler'=>$stack]);
    }

    private function row(string $layout, string $currency='VES'): array
    {
        return match ($layout) {
            'excel'=>[0=>'folio-1',1=>'orden-1',2=>'46000',3=>'12345678',4=>'Cliente ficticio',5=>'Dirección ficticia',6=>'',7=>'',8=>$currency,
                9=>'Servicio ficticio',10=>'SERV',11=>'1',12=>'0.116',13=>'0.1',14=>'0.016'],
            'first'=>[2=>'0001',3=>'FF01-123',5=>'46000',9=>'12345678',10=>'Cliente ficticio',11=>$currency,12=>'0.1',13=>'0.016',15=>'0.116',20=>'Dirección ficticia',21=>'',
                22=>'Servicio ficticio',23=>'SERV',24=>'1',25=>'0.116',26=>'0.1',27=>'0.016'],
            'second'=>[0=>'01',1=>'FF01-123',2=>'17/11/2026',3=>'12345678',4=>'Cliente ficticio',5=>$currency,6=>'0.1',7=>'0.016',8=>'0.116',9=>'Dirección ficticia',10=>'',
                11=>'Servicio ficticio',12=>'SERV',13=>'1',14=>'0.116',15=>'0.1',16=>'0.016'],
        };
    }

    public static function layouts(): array
    {
        $result=[];
        foreach (['excel'=>DocumentImportExcelFormat::class,'first'=>DocumentsImport::class,'second'=>DocumentsImportTwoFormat::class] as $layout=>$class) {
            foreach (['VES','USD'] as $currency) $result[$layout.'-'.$currency]=[$layout,$class,$currency];
        }
        return $result;
    }

    /** @dataProvider layouts */
    public function test_actual_xlsx_imports_preserve_large_numeric_tokens_and_public_payloads(string $layout, string $class, string $currency): void
    {
        $row=$this->row($layout,$currency); $columns=match ($layout) {'excel'=>[11,12,13,14],'first'=>[24,25,26,27],'second'=>[13,14,15,16]};
        $quantity='9007199254740993.01'; $price='0.116';
        $line=LineCalculation::calculate(['quantity'=>$quantity,'unit_price'=>$price,'currency_type_id'=>$currency],'16');
        foreach ($columns as $index=>$column) $row[$column]=[$quantity,$price,$line['total_base_igv'],$line['total_igv']][$index];
        if ($layout !== 'excel') foreach (match ($layout) {'first'=>[12,13,15],'second'=>[6,7,8]} as $index=>$column) $row[$column]=[$line['total_base_igv'],$line['total_igv'],$line['total']][$index];
        $raw=[]; foreach ($columns as $column) $raw[Coordinate::stringFromColumnIndex($column+1).'2']=$row[$column];
        $path=$this->workbook([$row],$raw); $history=[]; $import=new $class($path,$this->transport($history));
        $import->import($path,null,\Maatwebsite\Excel\Excel::XLSX);
        self::assertCount(1,$history); $payload=json_decode((string) $history[0]['request']->getBody(),true,512,JSON_THROW_ON_ERROR);
        self::assertSame('01',$payload['codigo_tipo_documento']); self::assertSame($currency,$payload['codigo_tipo_moneda']);
        self::assertArrayNotHasKey('codigo_condicion_de_pago',$payload);self::assertArrayNotHasKey('pagos',$payload);
        self::assertSame($payload['fecha_de_emision'],$payload['fecha_de_vencimiento']);
        self::assertSame($quantity,$payload['items'][0]['cantidad']); self::assertSame($price,$payload['items'][0]['price_input']['amount']);
        self::assertSame($line['total'],$payload['totales']['total_venta']); self::assertSame('SERV',$payload['items'][0]['unidad_de_medida']);
        self::assertSame(1,$import->getData()['registered']); self::assertArrayNotHasKey('_total',$payload['items'][0]);
    }

    /** @dataProvider layouts */
    public function test_api_validation_maps_are_shown_without_warnings_and_http_errors_are_never_registered(string $layout,string $class,string $currency): void
    {
        $path=$this->workbook([$this->row($layout,$currency)]);
        foreach ([[422,false,['payments'=>['Una factura al contado requiere cobrar el total']],'Una factura al contado requiere cobrar el total'],
            [500,true,[],'No se registró la factura.']] as [$status,$success,$message,$expected]) {
            $history=[];$import=new $class($path,$this->transport($history,[new Response($status,[],json_encode(['success'=>$success,'message'=>$message]))]));
            set_error_handler(static function ($severity,$text,$file,$line) {throw new \ErrorException($text,0,$severity,$file,$line);});
            try {$import->import($path,null,\Maatwebsite\Excel\Excel::XLSX);} finally {restore_error_handler();}
            self::assertCount(1,$history);self::assertSame(0,$import->getData()['registered']);
            self::assertSame($expected,$import->getData()['data'][0]['message']);
            $payload=json_decode((string)$history[0]['request']->getBody(),true,512,JSON_THROW_ON_ERROR);
            self::assertArrayNotHasKey('pagos',$payload);self::assertArrayNotHasKey('codigo_condicion_de_pago',$payload);
        }
    }

    public function test_second_line_uses_its_own_price_and_quantity_and_api_rejection_is_not_registered(): void
    {
        $row=$this->row('second'); $row=array_replace($row,[6=>'0.5',7=>'0.08',8=>'0.58',11=>'Factura servicio A',17=>'Factura servicio B',18=>'SERV',19=>'2',20=>'0.232',21=>'0.4',22=>'0.064']);
        $path=$this->workbook([$row]);$history=[];
        $import=new DocumentsImportTwoFormat($path,$this->transport($history,[new Response(200,[],'{"success":false,"message":"Rechazo controlado"}')]));
        $import->import($path,null,\Maatwebsite\Excel\Excel::XLSX);
        $payload=json_decode((string) $history[0]['request']->getBody(),true);
        self::assertSame('0.1',$payload['items'][0]['valor_unitario']); self::assertSame('0.2',$payload['items'][1]['valor_unitario']);
        self::assertNotSame($payload['items'][0]['codigo_interno'],$payload['items'][1]['codigo_interno']);
        self::assertLessThanOrEqual(30,strlen($payload['items'][1]['codigo_interno'])); self::assertSame('0.5',$payload['totales']['total_operaciones_gravadas']);
        self::assertSame('0.58',$payload['totales']['total_venta']); self::assertSame(0,$import->getData()['registered']);
        self::assertSame('Rechazo controlado',$import->getData()['data'][0]['message']);
    }

    public function test_grouped_folio_sums_exactly_and_rejects_mismatched_headers_before_http(): void
    {
        $one=$this->row('excel'); $two=$one; $two[11]='2';$two[13]='0.2';$two[14]='0.032';
        $path=$this->workbook([$one,$two]);$history=[];$import=new DocumentImportExcelFormat($path,$this->transport($history));
        $import->import($path,null,\Maatwebsite\Excel\Excel::XLSX);
        $payload=json_decode((string)$history[0]['request']->getBody(),true);self::assertCount(2,$payload['items']);self::assertSame('0.3',$payload['totales']['total_valor']);
        $two[8]='USD';$path=$this->workbook([$one,$two]);$history=[];$import=new DocumentImportExcelFormat($path,$this->transport($history));
        try {$import->import($path,null,\Maatwebsite\Excel\Excel::XLSX);self::fail('Cabeceras distintas no se combinan.');}
        catch (ValidationException $error) {self::assertStringContainsString('cabecera',json_encode($error->errors()));}
        self::assertSame([],$history);
    }

    public static function invalidInputs(): array
    {
        $cases=[];
        foreach (['excel'=>DocumentImportExcelFormat::class,'first'=>DocumentsImport::class,'second'=>DocumentsImportTwoFormat::class] as $layout=>$class) {
            foreach (['retired','zero','formula','invalid'] as $kind) $cases[$layout.'-'.$kind]=[$layout,$class,$kind];
        }
        return $cases;
    }

    /** @dataProvider invalidInputs */
    public function test_invalid_workbooks_never_send_an_api_request(string $layout, string $class, string $kind): void
    {
        $row=$this->row($layout);$quantity=match($layout){'excel'=>11,'first'=>24,'second'=>13};
        if ($kind==='retired' && $layout!=='excel') $row[$layout==='first'?2:0]='03';
        elseif ($kind==='zero') $row[$quantity]='0';
        elseif ($kind==='formula') $row[$quantity]='=1/3';
        elseif ($kind==='invalid') $row[$quantity]='12bad';
        $path=$this->workbook([$row]);$history=[];$client=$this->transport($history);
        if ($kind==='retired' && $layout==='excel') $this->app['request']->merge(['document_type_id'=>'03']);
        try {(new $class($path,$client))->import($path,null,\Maatwebsite\Excel\Excel::XLSX);self::fail('Entrada inválida aceptada.');}
        catch (ValidationException $error) {self::assertNotEmpty($error->errors());}
        self::assertSame([],$history);
    }

    public function test_grouped_periodic_bases_are_summed_before_decimal_representation(): void
    {
        $row=$this->row('excel');$row[12]='1';$row[13]='0.862068965517';$row[14]='0.137931034483';
        $path=$this->workbook(array_fill(0,29,$row));$history=[];$import=new DocumentImportExcelFormat($path,$this->transport($history));
        $import->import($path,null,\Maatwebsite\Excel\Excel::XLSX);
        $payload=json_decode((string)$history[0]['request']->getBody(),true);
        self::assertCount(29,$payload['items']);self::assertSame('25',$payload['totales']['total_valor']);
        self::assertSame('4',$payload['totales']['total_igv']);self::assertSame('29',$payload['totales']['total_venta']);
    }

    /** @dataProvider prefixedIdentities */
    public function test_imported_identity_prefixes_use_the_shared_normalizer(string $number,string $type,string $normalized): void
    {
        $row=$this->row('excel');$row[3]=$number;$path=$this->workbook([$row]);$history=[];$import=new DocumentImportExcelFormat($path,$this->transport($history));
        $import->import($path,null,\Maatwebsite\Excel\Excel::XLSX);
        $person=json_decode((string)$history[0]['request']->getBody(),true)['datos_del_cliente_o_receptor'];
        self::assertSame($type,$person['codigo_tipo_documento_identidad']);self::assertSame($normalized,$person['numero_documento']);
    }
    public static function prefixedIdentities(): array
    {
        return [['V-01234567','1','01234567'],['E-1234567','E','1234567'],['J-123456789','6','123456789'],['G-123456789','G','123456789'],
            ['E1234567','E','1234567'],['V1234567','1','1234567'],['J123456789','6','123456789']];
    }

    public function test_multiple_sheets_are_rejected_before_creating_any_invoice(): void
    {
        $path=$this->workbook([$this->row('second')],[],true);$history=[];$import=new DocumentsImportTwoFormat($path,$this->transport($history));
        try {$import->import($path,null,\Maatwebsite\Excel\Excel::XLSX);self::fail('Una segunda hoja no puede crear un lote parcial.');}
        catch (ValidationException $error) {self::assertStringContainsString('una sola hoja',json_encode($error->errors()));}
        self::assertSame([],$history);
    }
}
