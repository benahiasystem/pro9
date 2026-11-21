<?php
/** Fictitious evidence only; this script never runs against a live installation. */
require dirname(__DIR__, 3) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Accounting\Services\{CompanyService, EntryService, DocumentService, AttachmentService, AuditService};
use Modules\Accounting\Models\{Account, ThirdParty};
use Modules\Accounting\Domain\CompanyContext;
use Illuminate\Support\Str;

try {
    if (!app()->environment('testing') || !config('accounting.isolated_tests') || getenv('ACCOUNTING_FIXTURE_LOCKED') !== '1' || !in_array($argv[1] ?? '', ['http', 'ui'], true)) {
        throw new RuntimeException('Se requiere el clon aislado y el bloqueo de la suite.');
    }
    $host = App\Tenancy\Models\Hostname::with('website')->where('fqdn', 'contabilidad.localhost')->firstOrFail();
    $environment = app(App\Tenancy\Environment::class);
    $environment->hostname($host); $environment->tenant($host->website);
    $db = Illuminate\Support\Facades\DB::connection('tenant');
    if ($db->getConfig('host') !== 'accounting-db' || $db->getDatabaseName() !== 'tenancy_bbc') { throw new RuntimeException('El destino no es la copia privada autorizada.'); }
    $actors = App\Models\Tenant\User::without('establishment')->whereIn('id', [2, 3])->get();
    if ($actors->count() !== 2 || $actors->contains(fn($actor) => !str_ends_with($actor->email, '.invalid'))) { throw new RuntimeException('Las identidades deben ser ficticias.'); }
    $organization = $db->table('accounting_organization_memberships')->where('user_id', 3)->where('role', 'admin')->where('active', true)->value('organization_id');
    if (!$organization) { throw new RuntimeException('Falta la organización de pruebas.'); }
    $year = now('America/Caracas')->year; $service = app(CompanyService::class);
    $makeCompany = function (string $name, array $roles) use ($organization, $year, $db, $service) {
        $company = $service->create(['name' => $name . ' ' . Str::random(8), 'tax_id' => 'J-' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT) . '-0', 'tax_profile' => 'ordinary', 'approval_mode' => 'self_control'], $organization, 3, $year);
        app(CompanyContext::class)->set($company);
        $db->table('accounting_memberships')->where('company_id', $company->id)->where('user_id', 3)->update(['roles' => json_encode(['admin', 'accountant'])]);
        $db->table('accounting_memberships')->insert(['id' => (string) Str::uuid(), 'organization_id' => $organization, 'company_id' => $company->id, 'user_id' => 2, 'role' => $roles[0], 'roles' => json_encode($roles), 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $service->defaultChart();
        return $company;
    };
    $company = $makeCompany('Soportes ficticios ' . strtoupper($argv[1]), ['auxiliary']);
    $accounts = Account::get()->keyBy('code'); $entries = app(EntryService::class);
    $draft = fn(string $concept) => $entries->create(['accounting_date' => $year . '-11-10', 'concept' => $concept, 'lines' => [['account_id' => $accounts['1.1.02']->id, 'debit' => '1160', 'credit' => '0'], ['account_id' => $accounts['3.1']->id, 'debit' => '0', 'credit' => '1160']]], 3);
    $approve = function ($entry) use ($entries) {
        $entry = $entries->transition($entry->id, $entry->version, 3, 'submit');
        return $entries->transition($entry->id, $entry->version, 3, 'approve', 'Autocontrol explícito del comprobante ficticio y sus soportes.');
    };
    $pending = $approve($draft('Operación ficticia pendiente de soporte'));
    $posted = $draft('Servicio ficticio con soporte conservado');
    $imagePath = sys_get_temp_dir() . '/support-fixture-' . Str::uuid() . '.png';
    $image = imagecreatetruecolor(640, 260); $paper = imagecolorallocate($image, 247, 247, 252); $ink = imagecolorallocate($image, 25, 26, 56); $indigo = imagecolorallocate($image, 79, 70, 186);
    imagefill($image, 0, 0, $paper); imagefilledrectangle($image, 24, 24, 616, 38, $indigo);
    imagestring($image, 5, 32, 68, 'SOPORTE FICTICIO - PRUEBAS AISLADAS', $ink); imagestring($image, 4, 32, 112, 'Servicios de ejemplo / Operacion 0001', $ink); imagestring($image, 4, 32, 148, 'Total de control: 1.160,00 VES', $ink); imagestring($image, 3, 32, 208, 'Sin valor fiscal. Datos inventados.', $ink); imagepng($image, $imagePath); imagedestroy($image);
    $support = app(AttachmentService::class)->upload('journal_entry', $posted->id, new Illuminate\Http\UploadedFile($imagePath, 'soporte-ficticio.png', null, null, true), ['title' => 'Soporte del servicio ficticio', 'reason' => 'Evidencia ficticia revisada antes de contabilizar el servicio.'], $posted->version, 3, 'fixture-support-' . Str::uuid());
    unlink($imagePath); $posted = $approve($posted->fresh()); $entries->post($posted->id, $posted->version, 3, 'fixture-post-' . Str::uuid()); $posted->refresh();
    $party = ThirdParty::create(['id' => (string) Str::uuid(), 'tax_id' => 'J-11111111-1', 'name' => 'Cliente ficticio de soportes', 'kind' => 'customer', 'active' => true]);
    $document = app(DocumentService::class)->create(['kind' => 'sale_invoice', 'number' => 'FV-SOPORTE-FICTICIO', 'issue_date' => $year . '-11-10', 'fiscal_date' => $year . '-11-10', 'third_party_id' => $party->id, 'currency' => 'VES', 'exchange_rate' => '1', 'exchange_date' => $year . '-11-10', 'exchange_source' => 'Moneda del libro', 'amounts' => ['base' => '1000', 'vat' => '160', 'other_taxes' => '0', 'total' => '1160']], 3);
    for ($i = 0; $i < 55; ++$i) { app(AuditService::class)->record(3, 'fixture.support_note', 'journal_entry', $posted->id, ['reason' => 'Nota ficticia de trazabilidad ' . $i, 'version' => $posted->version]); }
    $result = ['company_id' => $company->id, 'company_name' => $company->name, 'year' => $year, 'date' => $year . '-11-10', 'pending_id' => $pending->id, 'pending_version' => $pending->version, 'posted_id' => $posted->id, 'posted_reference' => $posted->reference, 'posted_version' => $posted->version, 'attachment_id' => $support['attachment']['id'], 'attachment_sha256' => $support['attachment']['sha256'], 'document_id' => $document->id, 'document_version' => $document->version, 'source_revision' => $document->source_revision];
    $foreign = $makeCompany('Empresa ajena ficticia de soportes', ['viewer']);
    $result['foreign_company_id'] = $foreign->id;
    $head = $db->table('accounting_audit_heads')->where('company_id', $foreign->id)->first(); $now = now('America/Caracas')->toDateTimeString();
    $legacy = ['id' => (string) Str::uuid(), 'organization_id' => $organization, 'company_id' => $foreign->id, 'sequence' => $head->last_sequence + 1, 'user_id' => 3, 'action' => 'fixture.legacy', 'resource_type' => 'company', 'resource_id' => $foreign->id, 'payload' => json_encode(['reason' => 'Antecedente ficticio versión uno', 'password' => 'FICTICIO_NO_MOSTRAR']), 'previous_hash' => $head->last_hash, 'hash' => hash('sha256', 'fictitious-legacy-original-encoding'), 'hash_version' => 1, 'created_at' => $now, 'updated_at' => $now];
    $db->table('accounting_audit_events')->insert($legacy); $db->table('accounting_audit_heads')->where('id', $head->id)->update(['last_sequence' => $legacy['sequence'], 'last_hash' => $legacy['hash'], 'updated_at' => $now]);
    $result['legacy_event_id'] = $legacy['id'];
    $path = dirname(__DIR__, 3) . '/.accounting-isolated/supports-' . $argv[1] . '-fixture.json'; file_put_contents($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); chmod($path, 0600);
    echo "Fixture de soportes ficticios preparada dentro del clon privado.\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
