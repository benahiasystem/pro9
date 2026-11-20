<?php

namespace Modules\Accounting\Tests;

use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Support\AttachmentFormat;
use PHPUnit\Framework\TestCase;

/** @group accounting-attachments */
final class AttachmentFormatTest extends TestCase
{
    private string $directory;
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/attachment-format-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }
    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') as $path) { unlink($path); }
        rmdir($this->directory);
    }
    private function inspect(string $bytes, string $extension): array
    {
        $path = $this->directory . '/original.bin'; file_put_contents($path, $bytes);
        return (new AttachmentFormat())->inspect($path, $extension, $this->directory);
    }
    private function workbook(array $extra): array
    {
        $path = $this->directory . '/original.bin'; $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach (['[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>', 'xl/workbook.xml' => '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>', ...$extra] as $name => $bytes) { $zip->addFromString($name, $bytes); }
        $zip->close();
        return (new AttachmentFormat())->inspect($path, 'xlsx', $this->directory);
    }
    public function test_xml_is_validated_without_resolving_entities(): void
    {
        $valid = '<?xml version="1.0"?><soporte><total moneda="VES">1160.00</total></soporte>';
        $this->assertSame('application/xml', $this->inspect($valid, 'xml')['mime']);
        $this->expectException(ApiProblem::class);
        $this->expectExceptionMessage('entidades');
        $this->inspect('<?xml version="1.0"?><!DOCTYPE soporte [<!ENTITY dato SYSTEM "file:///etc/hostname">]><soporte>&dato;</soporte>', 'xml');
    }
    public function test_malformed_xml_and_html_disguised_as_text_are_rejected(): void
    {
        foreach ([['<?xml version="1.0"?><a><b></a>', 'xml'], ['<!DOCTYPE html><html><script>alert(1)</script></html>', 'txt']] as [$bytes, $extension]) {
            try { $this->inspect($bytes, $extension); $this->fail('El contenido no corresponde a un soporte válido.'); }
            catch (ApiProblem $error) { $this->assertContains($error->errorCode, ['ATTACHMENT_FORMAT_XML', 'ATTACHMENT_TYPE_MISMATCH']); }
        }
    }
    public function test_truncated_pdf_is_not_accepted_from_its_header_alone(): void
    {
        $this->expectException(ApiProblem::class);
        $this->expectExceptionMessage('estructura básica');
        $this->inspect("%PDF-1.7\n1 0 obj\n<<>>", 'pdf');
    }
    public function test_xlsx_preserves_non_executed_formulas_and_permits_only_web_hyperlinks(): void
    {
        $rels = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="r1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" TargetMode="External" Target="https://example.invalid/soporte"/></Relationships>';
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $this->workbook(['xl/_rels/workbook.xml.rels' => $rels, 'xl/worksheets/sheet1.xml' => '<?xml version="1.0"?><worksheet><c><f>SUM(A1:A2)</f><v>0</v></c></worksheet>'])['mime']);
        $this->expectException(ApiProblem::class);
        $this->expectExceptionMessage('relación externa');
        $this->workbook(['xl/_rels/workbook.xml.rels' => str_replace('https://example.invalid/soporte', 'file:///etc/hostname', $rels)]);
    }
    public function test_xlsx_macros_embedded_objects_and_archive_traversal_are_rejected(): void
    {
        foreach (['xl/vbaProject.bin', 'xl/embeddings/object.bin', '../escape.xml', 'xl/externalLinks/link.xml'] as $name) {
            try { $this->workbook([$name => 'contenido ficticio']); $this->fail('El libro no admite ese contenido.'); }
            catch (ApiProblem $error) { $this->assertSame('ATTACHMENT_FORMAT_XLSX', $error->errorCode); }
        }
    }
}
