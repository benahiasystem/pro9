<?php
namespace Tests\Unit;

use App\Services\Fiscal\HkaCreditNumbering;
use PHPUnit\Framework\TestCase;

// ######## INICIO CONFLICTO NUMERACION NC HKA ########
class HkaCreditNumberingTest extends TestCase
{
    private function identity(): array
    {
        return ['tipoDocumento'=>'02','numeroDocumento'=>'2','serie'=>'','transaccionId'=>'current-operation'];
    }
    private function occupied(string $status='Anulada'): array
    {
        return ['codigo'=>'200','validaciones'=>[], 'estado'=>['tipoDocumento'=>'02','numeroDocumento'=>'2',
            'transaccionId'=>'another-operation','numeroControl'=>'00-00000712','estadoDocumento'=>$status]];
    }
    public function test_occupied_note_number_is_a_conflict_even_when_annulled_and_without_returned_series(): void
    {
        foreach (['Anulada','Procesado','Enviada'] as $status) {
            $body=$this->occupied($status);$body['mensaje']='token-private-value';
            $result=HkaCreditNumbering::interpret(200,$body,$this->identity());
            self::assertSame('uncertain',$result['status']); self::assertFalse($result['retry_allowed']);
            self::assertSame('00-00000712',$result['numbering_conflict']['control_number']);
            self::assertStringContainsString('nota de crédito 00000002',$result['diagnostic']);
            self::assertStringContainsString('estado '.$status,$result['diagnostic']);
            self::assertArrayNotHasKey('control_number',$result);
            self::assertStringNotContainsString('token-private-value',json_encode($result));
            self::assertStringNotContainsString('another-operation',json_encode($result));
        }
    }
    public function test_incomplete_or_contradictory_lookups_never_allow_a_retry_or_establish_a_conflict(): void
    {
        $bodies=[null,[],['codigo'=>['invalid']],['codigo'=>'200'],['codigo'=>'203'],['codigo'=>'201']];
        foreach (['tipoDocumento'=>'03','numeroDocumento'=>'3','transaccionId'=>'','numeroControl'=>'','serie'=>'OTHER'] as $field=>$value) {
            $body=$this->occupied();$body['estado'][$field]=$value;$bodies[]=$body;
        }
        $body=$this->occupied();unset($body['estado']['transaccionId']);$bodies[]=$body;
        $body=$this->occupied();$body['validaciones']=['private-value'];$bodies[]=$body;
        foreach ($bodies as $body) {
            $result=HkaCreditNumbering::interpret(200,$body,$this->identity());
            self::assertFalse($result['retry_allowed']); self::assertSame('uncertain',$result['status']);
            self::assertArrayNotHasKey('numbering_conflict',$result);
            self::assertStringNotContainsString('private-value',json_encode($result));
        }
        self::assertFalse(HkaCreditNumbering::interpret(500,$this->occupied(),$this->identity())['retry_allowed']);
    }
    public function test_only_verified_absence_allows_retry_and_unknown_remote_states_are_sanitized(): void
    {
        $absent=['codigo'=>'203','mensaje'=>'Consulta no procesada','estado'=>null,'validaciones'=>['Documento no encontrado en nuestra base de datos']];
        self::assertTrue(HkaCreditNumbering::interpret(200,$absent,$this->identity())['retry_allowed']);
        $result=HkaCreditNumbering::interpret(200,$this->occupied('private-token-value'),$this->identity());
        self::assertSame('Registrada',$result['numbering_conflict']['remote_status']);
        self::assertStringNotContainsString('private-token-value',json_encode($result));
    }
    public function test_same_operation_is_confirmed_only_with_a_known_processed_state_and_matching_series(): void
    {
        $body=$this->occupied('Procesado');$body['estado']['transaccionId']='current-operation';
        $result=HkaCreditNumbering::interpret(200,$body,$this->identity());
        self::assertSame('confirmed',$result['status']);self::assertArrayNotHasKey('numbering_conflict',$result);
        $body['estado']['serie']='OTHER';
        self::assertSame('uncertain',HkaCreditNumbering::interpret(200,$body,$this->identity())['status']);
        unset($body['estado']['serie']);$body['estado']['estadoDocumento']='Anulada';
        $result=HkaCreditNumbering::interpret(200,$body,$this->identity());
        self::assertSame('uncertain',$result['status']);self::assertFalse($result['retry_allowed']);
        self::assertArrayNotHasKey('numbering_conflict',$result);
    }
}
// ######## FIN CONFLICTO NUMERACION NC HKA ########
