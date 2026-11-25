<?php

namespace Tests\Unit;

use App\Models\Tenant\FormatTemplate;
use Tests\TestCase;

class FormatTemplatePreviewTest extends TestCase
{
    public function test_watermark_preview_is_versioned_without_changing_other_urls(): void
    {
        $path = 'templates/pdf/marca_de_agua/image.png';
        $template = new FormatTemplate();
        $template->formats = 'marca_de_agua';
        $template->urls = json_encode(['invoice' => $path, 'ticket' => 'templates/pdf/marca_de_agua/ticket.png']);

        $data = $template->getCollectionData();

        self::assertSame($path . '?v=' . hash_file('sha256', public_path($path)), $data['urls']->invoice);
        self::assertSame('templates/pdf/marca_de_agua/ticket.png', $data['urls']->ticket);
        self::assertSame($path, json_decode($template->urls)->invoice);

        foreach (['default', 'modern-2027', 'Plantilla_personalizable'] as $name) {
            $path = "templates/pdf/{$name}/image.png";
            $template->urls = json_encode(['invoice' => $path]);
            self::assertSame($path . '?v=' . hash_file('sha256', public_path($path)), $template->getCollectionData()['urls']->invoice);
            self::assertSame($path, json_decode($template->urls)->invoice);
        }

        $template->urls = json_encode(['invoice' => 'templates/pdf/otra/image.png']);
        self::assertSame('templates/pdf/otra/image.png', $template->getCollectionData()['urls']->invoice);
    }
}
