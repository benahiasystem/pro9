<?php

namespace Modules\Accounting\Support;

use Modules\Accounting\Domain\ApiProblem;

/** Human-facing headers are also the stable, versioned interchange contract. */
final class ImportSchema
{
    public const VERSION = 1;
    public const MAX_ROWS = 2000;
    public const MAX_UNITS = 200;
    public const MAX_BYTES = 2097152;
    public const ERROR_COLUMNS = ['error_fila_original', 'error_campos', 'error_detalle'];

    public static function kinds(): array
    {
        return [
            'third-parties' => ['label' => 'Terceros', 'permission' => 'catalogs', 'help' => 'Crea fichas nuevas. Un RIF ya registrado se revisa en Catálogos; la importación no modifica fichas existentes.'],
            'entries' => ['label' => 'Comprobantes', 'permission' => 'draft', 'help' => 'Todas las líneas de una referencia forman un comprobante indivisible. Los importes del libro se expresan en VES con dos decimales. Se crean borradores para revisión.'],
            'opening' => ['label' => 'Saldos iniciales', 'permission' => 'opening', 'help' => 'Una referencia agrupa los saldos y sus partidas iniciales. Los controles de clientes y proveedores deben conciliar con el detalle. Prepara una apertura completa; no reemplaza otra apertura existente.'],
            'documents' => ['label' => 'Documentos fiscales', 'permission' => 'documents', 'help' => 'Crea borradores pendientes de revisión fiscal. Las notas requieren una factura original ya registrada en esta empresa. No valida impuestos ni contabiliza automáticamente.'],
        ];
    }

    public static function definition(string $kind): array
    {
        return self::kinds()[$kind] ?? throw new ApiProblem('IMPORT_KIND', 'Selecciona una plantilla de importación disponible.');
    }

    public static function columns(string $kind): array
    {
        self::definition($kind);
        // type: text (including all identifiers), date, decimal. Required values
        // depending on tipo_fila are checked by the domain adapter, not Excel.
        $columns = ['empresa_rif' => ['text', true], 'referencia_importacion' => ['text', true]];
        return $columns + match ($kind) {
            'third-parties' => ['rif' => ['text', true], 'nombre' => ['text', true], 'tipo' => ['text', true], 'correo' => ['text', false], 'telefono' => ['text', false], 'direccion' => ['text', false]],
            'entries' => ['fecha_contable' => ['date', true], 'concepto' => ['text', true], 'tipo_asiento' => ['text', false], 'cuenta' => ['text', true], 'rif_tercero' => ['text', false], 'centro_costo' => ['text', false], 'descripcion' => ['text', false], 'referencia_documental' => ['text', false], 'debe_ves' => ['decimal', true], 'haber_ves' => ['decimal', true], 'moneda_original' => ['text', false], 'importe_original' => ['decimal', false], 'tasa_cambio' => ['decimal', false]],
            'opening' => ['tipo_fila' => ['text', true], 'fecha_corte' => ['date', true], 'motivo' => ['text', true], 'soporte_general' => ['text', true], 'cuenta' => ['text', true], 'rif_tercero' => ['text', false], 'debe_ves' => ['decimal', false], 'haber_ves' => ['decimal', false], 'referencia_partida' => ['text', false], 'fecha_emision' => ['date', false], 'fecha_vencimiento' => ['date', false], 'moneda' => ['text', false], 'importe_original' => ['decimal', false], 'valor_libro_ves' => ['decimal', false], 'tasa_cambio' => ['decimal', false], 'fecha_tasa' => ['date', false], 'fuente_tasa' => ['text', false], 'soporte_partida' => ['text', false]],
            'documents' => ['tipo_documento' => ['text', true], 'numero' => ['text', true], 'control_fiscal' => ['text', false], 'fecha_emision' => ['date', true], 'fecha_fiscal' => ['date', true], 'fecha_recepcion' => ['date', false], 'vencimiento' => ['date', false], 'rif_tercero' => ['text', true], 'moneda' => ['text', true], 'tasa_cambio' => ['decimal', true], 'fecha_tasa' => ['date', true], 'fuente_tasa' => ['text', true], 'soporte_tasa' => ['text', false], 'motivo_tasa' => ['text', false], 'base' => ['decimal', true], 'iva' => ['decimal', true], 'otros_impuestos' => ['decimal', true], 'total' => ['decimal', true], 'original_numero' => ['text', false], 'original_control' => ['text', false], 'original_fecha' => ['date', false]],
        };
    }

    public static function identity(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($value)));
    }

    public static function issue(int $row, string $field, string $code, string $message): array
    {
        return ['row' => $row, 'field' => $field, 'code' => $code, 'message' => $message];
    }
}
