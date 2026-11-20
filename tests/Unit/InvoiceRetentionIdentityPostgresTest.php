<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\CashDocumentCredit;
use App\Services\Fiscal\HkaIvaRetention;
use App\Services\Fiscal\IvaRetentionService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceRetentionIdentityPostgresTest extends TestCase
{
    public function test_iva_retention_uses_invoice_operation_and_branch_identity_on_postgresql(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , $input, $transport] = (new ReflectionMethod($fixture, 'bootIvaFixture'))->invoke($fixture);
            $first = (new Facturalo())->save($input)->getDocument()->fresh();
            $second = (new Facturalo())->save(array_replace($input, ['external_id' => (string) Str::uuid()]))->getDocument()->fresh();
            $cashId = $db->table('cash')->where('user_id', auth()->id())->where('state', true)->value('id');
            $credit = CashDocumentCredit::create(['cash_id' => $cashId, 'document_id' => $first->id, 'status' => 'PENDING']);
            $voucher = new ReflectionMethod($fixture, 'ivaVoucher');
            $firstInput = $voucher->invoke($fixture, $first);
            $firstRetention = IvaRetentionService::store($first, $firstInput);
            $operation = $firstRetention->operations()->sole();
            self::assertSame('confirmed', $operation->status);
            self::assertSame('PROCESSED', $credit->fresh()->status);
            self::assertNull($credit->fresh()->cash_id_processed);
            self::assertSame($firstRetention->id, IvaRetentionService::store($first, $firstInput)->id);

            try {
                IvaRetentionService::store($second, $voucher->invoke($fixture, $second, ['operation_key' => $firstInput['operation_key']]));
                self::fail('La clave de retención debe conservar la factura de origen.');
            } catch (ValidationException $error) {
                self::assertSame(409, $error->status);
            }
            self::assertSame(0, $second->received_retentions()->count());

            $secondRetention = IvaRetentionService::store($second, $voucher->invoke($fixture, $second, ['voucher_number' => '20260900000002']));
            HkaIvaRetention::enqueue($first, $firstRetention, 'apply', $operation->operation_key);
            self::assertSame($operation->id, $firstRetention->operations()->sole()->id);
            try {
                HkaIvaRetention::enqueue($second, $secondRetention, 'apply', $operation->operation_key);
                self::fail('La clave HKA debe conservar su retención de origen.');
            } catch (ValidationException $error) {
                self::assertSame(409, $error->status);
            }
            self::assertSame(1, $secondRetention->operations()->count());
            self::assertCount(2, array_filter($transport->calls, static fn ($call) => $call[0] === 'POST' && str_ends_with($call[1], '/AplicarRetencion')));

            $user = auth()->user();
            $user->forceFill(['type' => 'seller', 'create_payment' => true, 'establishment_id' => $first->establishment_id]);
            $view = IvaRetentionService::view($first->fresh());
            self::assertTrue($view['enabled']);
            self::assertTrue($view['can_create']);
            self::assertSame('24', $view['applied_amount']);
            self::assertSame('2026-09-10', $view['records'][0]['voucher_date']);
            self::assertSame('confirmed', $view['records'][0]['hka_status']);

            $user->establishment_id = $first->establishment_id + 1;
            self::assertSame(['enabled' => false, 'prevision' => null, 'records' => []], IvaRetentionService::view($first->fresh()));
            self::assertSame(0, $db->transactionLevel());
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
