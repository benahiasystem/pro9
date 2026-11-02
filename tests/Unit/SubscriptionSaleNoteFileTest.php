<?php

namespace Tests\Unit;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Modules\Suscription\Services\SubscriptionSaleNoteController;
use Tests\TestCase;

class SubscriptionSaleNoteFileTest extends TestCase
{
    public function test_failed_pdf_write_is_reported_and_the_partial_file_is_removed(): void
    {
        $filesystem = app('filesystem');
        $disk = $this->createMock(Filesystem::class);
        $disk->expects(self::once())->method('exists')->with('sale_note/test.pdf')->willReturn(false);
        $disk->expects(self::once())->method('put')->with('sale_note/test.pdf', 'contenido')->willReturn(false);
        $disk->expects(self::once())->method('delete')->with('sale_note/test.pdf')->willReturn(true);
        Storage::shouldReceive('disk')->twice()->with('tenant')->andReturn($disk);
        try {
            $controller = new SubscriptionSaleNoteController();
            try {
                $controller->uploadFile('test', 'contenido', 'sale_note');
                self::fail('No debe aceptarse una escritura fallida.');
            } catch (\RuntimeException $error) {
                self::assertSame('No se pudo guardar el PDF de la nota de suscripción.', $error->getMessage());
            }
            $controller->restoreFiles();
        } finally {
            Storage::swap($filesystem);
        }
    }

    public function test_failed_pdf_write_restores_the_previous_file(): void
    {
        $filesystem = app('filesystem');
        $disk = $this->createMock(Filesystem::class);
        $disk->expects(self::once())->method('exists')->with('sale_note/test.pdf')->willReturn(true);
        $disk->expects(self::once())->method('get')->with('sale_note/test.pdf')->willReturn('anterior');
        $disk->expects(self::exactly(2))->method('put')->willReturnCallback(function ($path, $content) {
            self::assertSame('sale_note/test.pdf', $path);
            if ($content === 'nuevo') return false;
            self::assertSame('anterior', $content);
            return true;
        });
        Storage::shouldReceive('disk')->twice()->with('tenant')->andReturn($disk);
        try {
            $controller = new SubscriptionSaleNoteController();
            try {
                $controller->uploadFile('test', 'nuevo', 'sale_note');
                self::fail('No debe aceptarse una escritura fallida.');
            } catch (\RuntimeException $error) {
                self::assertSame('No se pudo guardar el PDF de la nota de suscripción.', $error->getMessage());
            }
            $controller->restoreFiles();
        } finally {
            Storage::swap($filesystem);
        }
    }
}
