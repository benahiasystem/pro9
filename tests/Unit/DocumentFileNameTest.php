<?php

namespace Tests\Unit;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Services\Fiscal\{DocumentFileName, FiscalIdentity};
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentFileNameTest extends TestCase
{
    /** @dataProvider names */
    public function test_visible_names_omit_private_branch_marker_and_pad_numbers($stored, $visible): void
    {
        self::assertSame($visible, DocumentFileName::visible($stored));
    }

    public static function names(): array
    {
        return [
            ['J123456789-01-SIN_SERIE_S1-25', 'J123456789-01-00000025'],
            ['J123456789-01-SIN_SERIE_S2-25.pdf', 'J123456789-01-00000025.pdf'],
            ['J123456789-01-FF-AB-01-25', 'J123456789-01-FF-AB-01-00000025'],
            ['SIN_SERIE_S10-25-20261009.pdf', '00000025-20261009.pdf'],
            ['J123456789-01-FF01-123456789.pdf', 'J123456789-01-FF01-123456789.pdf'],
            ['J123456789-01-FF01-00000025', 'J123456789-01-FF01-00000025'],
            ['file.pdf', 'file.pdf'], ['', ''],
        ];
    }

    public function test_download_reads_original_path_and_only_changes_response_name(): void
    {
        Storage::fake('tenant');
        $files = new class { use StorageDocument; };
        foreach ([1, 2] as $branch) {
            $name = 'J123456789-01-SIN_SERIE_S'.$branch.'-25';
            $files->uploadStorage($name, 'PDF branch '.$branch, 'pdf');
            $response = $files->downloadStorage($name, 'pdf');
            self::assertStringContainsString('J123456789-01-00000025.pdf', $response->headers->get('Content-Disposition'));
            self::assertStringNotContainsString('SIN_SERIE', $response->headers->get('Content-Disposition'));
            ob_start(); $response->sendContent(); $bytes = ob_get_clean();
            self::assertSame('PDF branch '.$branch, $bytes);
            self::assertSame('PDF branch '.$branch, $files->getStorage($name, 'pdf'));
        }
        self::assertCount(2, Storage::disk('tenant')->allFiles('pdf'));
    }

    public function test_archive_entries_preserve_every_branch_without_exposing_internal_keys(): void
    {
        $used = [];
        self::assertSame('00000025.pdf', DocumentFileName::archiveEntry('SIN_SERIE_S1-25.pdf', $used));
        self::assertSame('00000025 (2).pdf', DocumentFileName::archiveEntry('SIN_SERIE_S2-25.pdf', $used));
        self::assertSame('00000025 (2) (2).pdf', DocumentFileName::archiveEntry('00000025 (2).pdf', $used));
        self::assertSame('00000025', FiscalIdentity::displayReference('SIN_SERIE_S1-25-20261009'));
    }

    public function test_commercial_mail_attachments_keep_bytes_and_hide_private_keys(): void
    {
        Storage::fake('tenant');
        config(['tenant.template_document_mail' => 'default', 'mail.username' => 'billing@example.test']);
        $files = new class { use StorageDocument; };
        foreach ([
            [\App\Mail\Tenant\DocumentEmail::class, \App\Models\Tenant\Document::class, 'pdf'],
            [\App\Mail\Tenant\SaleNoteEmail::class, \App\Models\Tenant\SaleNote::class, 'sale_note'],
            [\App\Mail\Tenant\QuotationEmail::class, \App\Models\Tenant\Quotation::class, 'quotation'],
        ] as [$mailClass, $modelClass, $folder]) {
            $stored = 'J123456789-01-SIN_SERIE_S1-25';
            $document = new $modelClass();
            $document->setRawAttributes(['filename' => $stored]);
            $files->uploadStorage($stored, 'unchanged PDF', $folder);
            $mail = (new $mailClass((object) ['name' => 'Test'], $document))->build();
            self::assertSame('J123456789-01-00000025.pdf', $mail->rawAttachments[0]['name']);
            self::assertSame('unchanged PDF', $mail->rawAttachments[0]['data']);
            self::assertSame($stored, $document->filename);
        }
    }

    public function test_print_headers_use_visible_filename(): void
    {
        $headers = \App\CoreFacturalo\Helpers\Functions\GeneralPdfHelper::pdfResponseFileHeaders('J123456789-01-SIN_SERIE_S1-25');
        self::assertSame('application/pdf', $headers['Content-Type']);
        self::assertStringContainsString('J123456789-01-00000025.pdf', $headers['Content-Disposition']);
    }
}
