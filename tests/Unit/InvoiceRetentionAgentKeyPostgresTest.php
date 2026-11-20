<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Services\Fiscal\FreeFormControl;
use App\Services\Fiscal\IvaRetentionService;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceRetentionAgentKeyPostgresTest extends TestCase
{
    public function test_invoice_retention_validates_the_agent_key_before_writing(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootIvaFixture'))->invoke($fixture);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            $invoice = (new Facturalo())->save($input)->getDocument();
            FreeFormControl::register($invoice, ['control_number' => '00-1', 'evidence_reference' => 'Factura física sintética']);
            $invoice = $invoice->fresh();
            $payload = (new ReflectionMethod($fixture, 'ivaVoucher'))->invoke($fixture, $invoice);
            $customerId = (string) $invoice->customer_id;
            $balance = $invoice->exactBalance();

            foreach ([$customerId.'x', '0'.$customerId, $customerId.'.0', true, [$customerId], '9223372036854775808'] as $invalid) {
                try {
                    IvaRetentionService::store($invoice, array_replace($payload, ['agent_id' => $invalid]));
                    self::fail('La retención no debe guardar una clave de agente inválida.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('agent_id', $error->errors());
                }
            }
            self::assertSame(0, $invoice->received_retentions()->count());
            self::assertTrue($invoice->fresh()->exactBalance()->isEqualTo($balance));

            $retention = IvaRetentionService::store($invoice, array_replace($payload, ['agent_id' => $customerId]));
            self::assertSame($customerId, (string) $retention->agent_id);
            self::assertSame(1, $invoice->received_retentions()->count());
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
