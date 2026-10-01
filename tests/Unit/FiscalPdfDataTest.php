<?php
namespace Tests\Unit;

use App\Services\Fiscal\FiscalPdfData;
use PHPUnit\Framework\TestCase;

class FiscalPdfDataTest extends TestCase
{
    public function test_printing_reads_own_identifiers_without_provider_queries_or_number_consumption(): void
    {
        foreach ([\App\Models\Tenant\Document::class, \App\Models\Tenant\Dispatch::class] as $model) {
            $document = new $model();
            $document->setRawAttributes(['id' => 1, 'series' => 'FC01', 'number' => 16, 'control_number' => '00-00000021', 'fiscal_environment' => 'demo']);
            $document->exists = true;
            $data = FiscalPdfData::forDocument($document);
            self::assertSame('16', $data['document_number']);
            self::assertSame('00-00000021', $data['control_number']);
            self::assertNull($data['printer']);
            self::assertSame($data, FiscalPdfData::forDocument($document));
            self::assertSame(16, $document->number);
        }
    }
}
