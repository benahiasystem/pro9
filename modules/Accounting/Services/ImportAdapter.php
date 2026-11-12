<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\Validator;
use Modules\Accounting\Domain\{ApiProblem, Exact};
use Modules\Accounting\Models\{Account, CostCenter, ThirdParty};
use Modules\Accounting\Support\{ImportSchema, EntryInput, OpeningInput, DocumentInput, ThirdPartyInput};

/** Resolves human identifiers in the active book, then uses its existing domain. */
final class ImportAdapter
{
    public function execute(string $kind, array $rows, int $user): array
    {
        return match ($kind) {
            'third-parties' => $this->party($rows, $user),
            'entries' => $this->entry($rows, $user),
            'opening' => $this->opening($rows, $user),
            'documents' => $this->document($rows, $user),
        };
    }

    private function fail(array $row, string $field, string $code, string $message): never
    {
        throw new ApiProblem($code, $message, 422, ['errors' => [ImportSchema::issue($row['row'], $field, $code, $message)]]);
    }

    private function required(array $row, string $field): string
    {
        $value = $row['data'][$field];
        if ($value === '') {
            $this->fail($row, $field, 'REQUIRED', 'Este campo es obligatorio para esta fila.');
        }
        return $value;
    }

    private function validate(array $input, array $rules, array $map, array $rows): array
    {
        $validator = Validator::make($input, $rules, ['required' => 'El campo es obligatorio.', 'present' => 'El campo debe estar presente.', 'in' => 'Selecciona uno de los valores indicados en Instrucciones.', 'max' => 'El campo excede la longitud o cantidad permitida.', 'min' => 'El campo no alcanza la longitud o cantidad mínima.', 'date_format' => 'Usa una fecha válida AAAA-MM-DD.', 'email' => 'Revisa el formato del correo.', 'uuid' => 'La referencia no pertenece al catálogo esperado.']);
        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $field => $messages) {
                $position = 0;
                if (preg_match('/^(lines|balances|open_items)\.([0-9]+)\.(.+)$/D', $field, $match)) {
                    $position = (int) $match[2];
                    $field = $match[1] . '.*.' . $match[3];
                }
                foreach ($messages as $message) {
                    $errors[] = ImportSchema::issue($rows[$position]['row'] ?? $rows[0]['row'], $map[$field] ?? 'unidad', 'INVALID_FIELD', $message);
                }
            }
            throw new ApiProblem('IMPORT_VALIDATION', 'Revisa los campos de esta unidad.', 422, ['errors' => $errors]);
        }
        return $validator->validated();
    }

    private function same(array $rows, array $fields): void
    {
        foreach ($rows as $row) {
            foreach ($fields as $field) {
                if ($row['data'][$field] !== $rows[0]['data'][$field]) {
                    $this->fail($row, $field, 'INCONSISTENT_HEADER', 'Todas las filas de esta referencia deben conservar el mismo ' . $field . '.');
                }
            }
        }
    }

    private function account(array $row): Account
    {
        $account = Account::where('code', $this->required($row, 'cuenta'))->first();
        if (!$account || !$account->posting || !$account->active) {
            $this->fail($row, 'cuenta', 'NON_POSTING_ACCOUNT', 'Selecciona el código de una cuenta activa de movimiento de esta empresa.');
        }
        return $account;
    }

    private function findParty(array $row, bool $required = false): ?ThirdParty
    {
        $rif = $row['data']['rif_tercero'];
        if ($rif === '' && !$required) {
            return null;
        }
        $key = ImportSchema::identity($rif);
        $matches = ThirdParty::get()->filter(fn($party) => ImportSchema::identity($party->tax_id) === $key);
        if ($key === '' || $matches->count() !== 1 || !$matches->first()->active) {
            $this->fail($row, 'rif_tercero', 'IMPORT_PARTY', 'El RIF debe identificar un único tercero activo en esta empresa. Regístralo o resuelve las fichas duplicadas en Catálogos.');
        }
        return $matches->first();
    }

    private function party(array $rows, int $user): array
    {
        if (count($rows) !== 1) {
            $this->fail($rows[0], 'referencia_importacion', 'IMPORT_SINGLE_ROW', 'Usa una referencia distinta para cada tercero.');
        }
        $r = $rows[0];
        $d = $r['data'];
        $data = $this->validate(['tax_id' => $d['rif'], 'name' => $d['nombre'], 'kind' => $d['tipo'], 'email' => $d['correo'] ?: null, 'phone' => $d['telefono'] ?: null, 'address' => $d['direccion'] ?: null, 'active' => true], ThirdPartyInput::rules(), ['tax_id' => 'rif', 'name' => 'nombre', 'kind' => 'tipo', 'email' => 'correo', 'phone' => 'telefono', 'address' => 'direccion'], $rows);
        $key = ImportSchema::identity($data['tax_id']);
        if ($key === '' || ThirdParty::get()->contains(fn($party) => ImportSchema::identity($party->tax_id) === $key)) {
            $this->fail($r, 'rif', 'DUPLICATE_PARTY', 'Este RIF ya existe o está vacío. Revisa la ficha en Catálogos; no se sobrescribe desde una importación.');
        }
        $party = ThirdParty::create($data);
        app(AuditService::class)->record($user, 'third-parties.created', 'third-parties', $party->id, ['data' => $data, 'origin' => 'import']);
        return ['resource' => ['type' => 'third-party', 'id' => $party->id, 'version' => $party->version], 'summary' => ['name' => $party->name, 'tax_id' => $party->tax_id]];
    }

    private function entry(array $rows, int $user): array
    {
        $this->same($rows, ['fecha_contable', 'concepto', 'tipo_asiento']);
        $d = $rows[0]['data'];
        $lines = [];
        $issues = [];
        foreach ($rows as $row) {
            try {
                $r = $row['data'];
                $account = $this->account($row);
                $party = $this->findParty($row, $account->requires_party);
                $center = $r['centro_costo'] === '' ? null : CostCenter::where('code', $r['centro_costo'])->where('active', true)->first();
                if ($r['centro_costo'] !== '' && !$center) {
                    $this->fail($row, 'centro_costo', 'IMPORT_COST_CENTER', 'El centro de costo debe estar activo y pertenecer a esta empresa.');
                }
                foreach (['debe_ves', 'haber_ves'] as $field) {
                    try {
                        Exact::of($r[$field], 2);
                    } catch (ApiProblem $e) {
                        $this->fail($row, $field, $e->errorCode, 'El importe del libro debe ser un decimal exacto con un máximo de dos decimales.');
                    }
                }
                $debit = Exact::of($r['debe_ves'], 2);
                $credit = Exact::of($r['haber_ves'], 2);
                if ($debit->isNegative() || $credit->isNegative() || $debit->isPositive() && $credit->isPositive()) {
                    $this->fail($row, $credit->isNegative() ? 'haber_ves' : 'debe_ves', 'INVALID_LINE', 'La fila debe tener un importe positivo en un solo lado: Debe o Haber.');
                }
                if ($debit->isZero() && $credit->isZero()) {
                    $this->fail($row, 'debe_ves', 'ZERO_LINE', 'La fila no puede tener Debe y Haber en cero.');
                }
                $lines[] = ['account_id' => $account->id, 'third_party_id' => $party?->id, 'cost_center_id' => $center?->id, 'description' => $r['descripcion'] ?: null, 'document_reference' => $r['referencia_documental'] ?: null, 'debit' => $r['debe_ves'], 'credit' => $r['haber_ves'], 'original_currency' => $r['moneda_original'] ?: 'VES', 'original_amount' => $r['importe_original'] ?: null, 'exchange_rate' => $r['tasa_cambio'] ?: null];
            } catch (ApiProblem $e) {
                $issues = [...$issues, ...($e->details['errors'] ?? [ImportSchema::issue($row['row'], 'unidad', $e->errorCode, $e->getMessage())])];
            }
        }
        if ($issues) {
            throw new ApiProblem('IMPORT_VALIDATION', 'El comprobante completo queda rechazado.', 422, ['errors' => $issues]);
        }
        $data = $this->validate(['accounting_date' => $d['fecha_contable'], 'concept' => $d['concepto'], 'entry_type' => $d['tipo_asiento'] ?: 'regular', 'lines' => $lines], EntryInput::rules(), ['accounting_date' => 'fecha_contable', 'concept' => 'concepto', 'entry_type' => 'tipo_asiento', 'lines.*.description' => 'descripcion', 'lines.*.document_reference' => 'referencia_documental', 'lines.*.original_currency' => 'moneda_original', 'lines.*.original_amount' => 'importe_original', 'lines.*.exchange_rate' => 'tasa_cambio'], $rows);
        $entry = app(EntryService::class)->create($data, $user);
        app(EntryService::class)->validatePosting($entry, false);
        return $this->entryResult($entry);
    }

    private function entryResult($entry): array
    {
        return ['resource' => ['type' => 'entry', 'id' => $entry->id, 'version' => $entry->version, 'status' => $entry->status], 'summary' => ['date' => $entry->accounting_date->format('Y-m-d'), 'concept' => $entry->concept, 'debit' => $entry->total_debit, 'credit' => $entry->total_credit, 'lines' => $entry->lines()->count()]];
    }

    private function opening(array $rows, int $user): array
    {
        if (\Modules\Accounting\Models\OpeningBatch::where('active_key', 'migration')->exists()) {
            $this->fail($rows[0], 'referencia_importacion', 'OPENING_EXISTS', 'Ya existe una propuesta o apertura contabilizada. Revísala en Auxiliares; la importación no la reemplaza ni adopta con otra referencia.');
        }
        $this->same($rows, ['fecha_corte', 'motivo', 'soporte_general']);
        $d = $rows[0]['data'];
        $balances = [];
        $items = [];
        $balanceRows = [];
        $itemRows = [];
        foreach ($rows as $row) {
            $r = $row['data'];
            $account = $this->account($row);
            if ($r['tipo_fila'] === 'saldo') {
                $party = $this->findParty($row, false);
                $balances[] = ['account_id' => $account->id, 'third_party_id' => $party?->id, 'debit' => $r['debe_ves'] ?: '0', 'credit' => $r['haber_ves'] ?: '0'];
                $balanceRows[] = $row;
            } elseif ($r['tipo_fila'] === 'partida') {
                $party = $this->findParty($row, true);
                $items[] = ['account_id' => $account->id, 'third_party_id' => $party->id, 'reference' => $r['referencia_partida'], 'issue_date' => $r['fecha_emision'], 'due_date' => $r['fecha_vencimiento'] ?: null, 'currency' => $r['moneda'], 'original_amount' => $r['importe_original'], 'book_amount' => $r['valor_libro_ves'], 'exchange_rate' => $r['tasa_cambio'], 'exchange_date' => $r['fecha_tasa'], 'exchange_source' => $r['fuente_tasa'], 'evidence_reference' => $r['soporte_partida']];
                $itemRows[] = $row;
            } else {
                $this->fail($row, 'tipo_fila', 'IMPORT_OPENING_ROW', 'Usa saldo para el mayor o partida para el detalle de clientes y proveedores.');
            }
        }
        $data = ['cutoff_date' => $d['fecha_corte'], 'reason' => $d['motivo'], 'evidence_reference' => $d['soporte_general'], 'balances' => $balances, 'open_items' => $items];
        // Validate each array with its original row positions for actionable errors.
        $rules = OpeningInput::rules();
        $map = ['cutoff_date' => 'fecha_corte', 'reason' => 'motivo', 'evidence_reference' => 'soporte_general', 'balances.*.debit' => 'debe_ves', 'balances.*.credit' => 'haber_ves', 'open_items.*.reference' => 'referencia_partida', 'open_items.*.issue_date' => 'fecha_emision', 'open_items.*.due_date' => 'fecha_vencimiento', 'open_items.*.currency' => 'moneda', 'open_items.*.original_amount' => 'importe_original', 'open_items.*.book_amount' => 'valor_libro_ves', 'open_items.*.exchange_rate' => 'tasa_cambio', 'open_items.*.exchange_date' => 'fecha_tasa', 'open_items.*.exchange_source' => 'fuente_tasa', 'open_items.*.evidence_reference' => 'soporte_partida'];
        foreach (['balances' => $balanceRows, 'open_items' => $itemRows] as $key => $originalRows) {
            $this->validate($data, array_filter($rules, fn($k) => str_starts_with($k, $key . '.'), ARRAY_FILTER_USE_KEY), $map, $originalRows ?: $rows);
        }
        $data = $this->validate($data, $rules, $map, $rows);
        $entry = app(OpeningService::class)->prepare($data, $user);
        app(EntryService::class)->validatePosting($entry, false);
        return $this->entryResult($entry);
    }

    private function document(array $rows, int $user): array
    {
        if (count($rows) !== 1) {
            $this->fail($rows[0], 'referencia_importacion', 'IMPORT_SINGLE_ROW', 'Usa una referencia distinta por documento.');
        }
        $row = $rows[0];
        $d = $row['data'];
        $party = $this->findParty($row, true);
        $data = ['kind' => $d['tipo_documento'], 'number' => $d['numero'], 'fiscal_control' => $d['control_fiscal'] ?: null, 'issue_date' => $d['fecha_emision'], 'fiscal_date' => $d['fecha_fiscal'], 'received_on' => $d['fecha_recepcion'] ?: null, 'due_date' => $d['vencimiento'] ?: null, 'third_party_id' => $party->id, 'currency' => $d['moneda'], 'exchange_rate' => $d['tasa_cambio'], 'exchange_date' => $d['fecha_tasa'], 'exchange_source' => $d['fuente_tasa'], 'rate_evidence_reference' => $d['soporte_tasa'] ?: null, 'rate_reason' => $d['motivo_tasa'] ?: null, 'amounts' => ['base' => $d['base'], 'vat' => $d['iva'], 'other_taxes' => $d['otros_impuestos'], 'total' => $d['total']], 'tax_components' => []];
        $data = $this->validate($data, DocumentInput::rules(), ['kind' => 'tipo_documento', 'number' => 'numero', 'fiscal_control' => 'control_fiscal', 'currency' => 'moneda', 'issue_date' => 'fecha_emision', 'fiscal_date' => 'fecha_fiscal', 'received_on' => 'fecha_recepcion', 'due_date' => 'vencimiento', 'exchange_rate' => 'tasa_cambio', 'exchange_date' => 'fecha_tasa', 'exchange_source' => 'fuente_tasa', 'rate_evidence_reference' => 'soporte_tasa', 'rate_reason' => 'motivo_tasa'], $rows);
        foreach (['base', 'iva', 'otros_impuestos', 'total'] as $field) {
            if (Exact::of($d[$field], 12)->isNegative()) {
                $this->fail($row, $field, 'NEGATIVE_DOCUMENT_AMOUNT', 'Los importes documentales se expresan positivos; el tipo de nota determina su efecto.');
            }
        }
        $sum = Exact::of($d['base'], 12)->plus(Exact::of($d['iva'], 12))->plus(Exact::of($d['otros_impuestos'], 12));
        if (!$sum->isEqualTo(Exact::of($d['total'], 12)) || !$sum->isPositive()) {
            $this->fail($row, 'total', 'IMPORT_DOCUMENT_TOTAL', 'El total positivo debe coincidir exactamente con base + IVA + otros impuestos.');
        }
        if (!Exact::of($d['tasa_cambio'], 12)->isPositive() || $d['moneda'] === 'VES' && !Exact::of($d['tasa_cambio'], 12)->isEqualTo(1)) {
            $this->fail($row, 'tasa_cambio', 'INVALID_RATE', 'Usa una tasa positiva; en VES la tasa es 1.');
        }
        if ($d['fecha_tasa'] > $d['fecha_emision']) {
            $this->fail($row, 'fecha_tasa', 'FUTURE_DOCUMENT_RATE', 'La tasa histórica no puede ser posterior a la emisión.');
        }
        $isNote = str_contains($d['tipo_documento'], '_note');
        if ($isNote) {
            $number = $this->required($row, 'original_numero');
            $date = $this->required($row, 'original_fecha');
            $service = app(DocumentService::class);
            $kind = str_starts_with($d['tipo_documento'], 'sale_') ? 'sale_invoice' : 'purchase_invoice';
            $key = $service->identity($kind, $number, $party->tax_id, $d['original_control'] ?: null, $date);
            $original = $service->findIdentity($key);
            if (!$original || $original->third_party_id !== $party->id || $original->issue_date->format('Y-m-d') !== $date || $date > $d['fecha_emision']) {
                $this->fail($row, 'original_numero', 'IMPORT_ORIGINAL_DOCUMENT', 'Registra primero la factura original en esta empresa y revisa su tercero, control y fecha.');
            }
            $data['related_document_id'] = $original->id;
        } elseif ($d['original_numero'] !== '' || $d['original_control'] !== '' || $d['original_fecha'] !== '') {
            $this->fail($row, 'original_numero', 'IMPORT_ORIGINAL_DOCUMENT', 'La referencia a una factura original corresponde únicamente a notas de crédito o débito.');
        }
        $document = app(DocumentService::class)->create($data, $user);
        return ['resource' => ['type' => 'document', 'id' => $document->id, 'version' => $document->version, 'status' => $document->status], 'summary' => ['date' => $document->issue_date->format('Y-m-d'), 'number' => $document->number, 'currency' => $document->currency, 'total' => $document->amounts['total'], 'fiscal_review_required' => true]];
    }
}
