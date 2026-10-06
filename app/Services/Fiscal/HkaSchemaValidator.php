<?php
namespace App\Services\Fiscal;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
/** Validates the subset of OpenAPI constraints used by emission request DTOs. */
final class HkaSchemaValidator
{
    private array $contract;
    public function validate(array $payload): void
    {
        $this->contract=json_decode(file_get_contents(__DIR__.'/contracts/hka-ve-v1.json'), true, 512, JSON_THROW_ON_ERROR);
        $schema=$this->contract['paths']['/api/Emision']['post']['requestBody']['content']['application/json']['schema'];
        $this->check($payload,$schema,'payload');
    }
    private function check($value,array $schema,string $path): void
    {
        if (isset($schema['$ref'])) $schema=$this->contract['components']['schemas'][basename($schema['$ref'])];
        if ($value===null && ($schema['nullable'] ?? false)) return;
        foreach ($schema['allOf'] ?? [] as $child) $this->check($value,$child,$path);
        if (isset($schema['enum']) && !in_array($value,$schema['enum'],true)) FiscalAmounts::error($path,'Valor fuera del contrato HKA.');
        $type=$schema['type'] ?? null;
        if ($type==='object') {
            if (!is_array($value)) FiscalAmounts::error($path,'Se requiere un objeto HKA.');
            foreach ($schema['required'] ?? [] as $key) if (!array_key_exists($key,$value)) FiscalAmounts::error($path.'.'.$key,'Dato HKA requerido.');
            foreach ($value as $key=>$v) {
                if(isset($schema['properties'][$key])) $this->check($v,$schema['properties'][$key],$path.'.'.$key);
                elseif (($schema['additionalProperties'] ?? true) === false) FiscalAmounts::error($path.'.'.$key,'Campo fuera del contrato HKA.');
            }
        } elseif ($type==='array') {
            if (!is_array($value) || !array_is_list($value)) FiscalAmounts::error($path,'Se requiere una lista HKA.');
            if (isset($schema['minItems']) && count($value)<$schema['minItems']) FiscalAmounts::error($path,'Lista HKA incompleta.');
            if (isset($schema['maxItems']) && count($value)>$schema['maxItems']) FiscalAmounts::error($path,'Lista excede el límite HKA.');
            foreach ($value as $i=>$v) $this->check($v,$schema['items'],$path.'.'.$i);
        } elseif ($type==='string') {
            if (!is_string($value)) FiscalAmounts::error($path,'Se requiere texto HKA.');
            if (isset($schema['maxLength']) && mb_strlen($value)>$schema['maxLength']) FiscalAmounts::error($path,'Texto excede el límite HKA.');
            if (isset($schema['minLength']) && mb_strlen($value)<$schema['minLength']) FiscalAmounts::error($path,'Texto HKA incompleto.');
            if (!empty($schema['pattern']) && preg_match('~'.str_replace('~','\\~',$schema['pattern']).'~u',$value)!==1) FiscalAmounts::error($path,'Formato fuera del contrato HKA.');
        } elseif ($type==='boolean' && !is_bool($value)) FiscalAmounts::error($path,'Se requiere booleano HKA.');
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
