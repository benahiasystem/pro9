<?php

// ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
namespace Tests\Unit;

use App\Http\Controllers\Tenant\ItemController;
use App\Support\ItemImport\ItemImportValidationWorkbook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ItemImportDownloadResponseTest extends TestCase
{
    /** @test */
    public function it_downloads_the_authenticated_users_error_workbook_and_removes_it_after_sending(): void
    {
        Storage::fake('local');
        $token = (string) Str::uuid();
        $path = ItemImportValidationWorkbook::pathFor(17, $token);
        $contents = file_get_contents(base_path('public/formats/items.xlsx'));
        self::assertNotFalse($contents);
        Storage::disk('local')->put($path, $contents);

        $request = Request::create('/items/import/validation/' . $token, 'GET');
        $request->setUserResolver(function () {
            return (object) ['id' => 17];
        });

        $response = (new ItemController())->downloadImportValidation($request, $token);
        $response->prepare($request);

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
        self::assertStringContainsString(
            'attachment; filename=VALIDACION_ITEMS_' . $token . '.xlsx',
            (string) $response->headers->get('content-disposition')
        );

        ob_start();
        $response->sendContent();
        $downloadedContents = ob_get_clean();

        self::assertSame($contents, $downloadedContents);
        self::assertStringStartsWith('PK', $downloadedContents);
        Storage::disk('local')->assertMissing($path);
    }

    /** @test */
    public function it_does_not_expose_another_users_error_workbook(): void
    {
        Storage::fake('local');
        $token = (string) Str::uuid();
        $otherUserPath = ItemImportValidationWorkbook::pathFor(18, $token);
        Storage::disk('local')->put($otherUserPath, 'private-workbook');

        $request = Request::create('/items/import/validation/' . $token, 'GET');
        $request->setUserResolver(function () {
            return (object) ['id' => 17];
        });

        try {
            (new ItemController())->downloadImportValidation($request, $token);
            self::fail('La descarga de otro usuario debía responder 404.');
        } catch (NotFoundHttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
        }

        Storage::disk('local')->assertExists($otherUserPath);
    }
}
// ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
