<?php

namespace Tests\Unit;

use App\Models\Tenant\Catalogs\DocumentType;
use App\Models\Tenant\Document;
use App\Models\Tenant\SaleNote;
use Illuminate\Validation\ValidationException;
use Modules\WhatsAppBot\Services\Tools\QueryDocumentStatusTool;
use Tests\TestCase;

class CurrentDocumentResolutionTest extends TestCase
{
    public function test_current_sales_types_resolve_their_actual_model(): void
    {
        foreach (['01' => Document::class, '07' => Document::class, '08' => Document::class, '80' => SaleNote::class] as $id => $model) {
            $type = new DocumentType(['id' => (string) $id]);
            self::assertSame($model, $type->getCurrentRelatiomClass());
            self::assertSame($id === '01', $type->isInvoice());
        }
    }

    /** @dataProvider unsupportedTypes */
    public function test_unknown_or_retired_types_do_not_fall_back_to_invoice(?string $id): void
    {
        $this->expectException(ValidationException::class);
        (new DocumentType(['id' => $id]))->getCurrentRelatiomClass();
    }

    public static function unsupportedTypes(): array
    {
        return [['03'], ['31'], ['99'], [null]];
    }

    public function test_bot_states_do_not_claim_external_fiscal_acceptance(): void
    {
        $tool = new QueryDocumentStatusTool();
        $method = new \ReflectionMethod($tool, 'stateDescription');
        $method->setAccessible(true);
        self::assertSame('registrado localmente', $method->invoke($tool, '01'));
        self::assertSame('anulado', $method->invoke($tool, '11'));
        self::assertSame('por anular', $method->invoke($tool, '13'));
        foreach (['03', '05', '07', '09'] as $retiredState) {
            self::assertSame('estado desconocido', $method->invoke($tool, $retiredState));
        }
        self::assertStringContainsString('estado local', $tool->definition()['function']['description']);
    }
}
