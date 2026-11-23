<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Modules\Sale\Http\Controllers\SaleOpportunityFileController;
use Modules\Sale\Services\SaleOpportunityFileService;
use PHPUnit\Framework\TestCase;

/** Real upload validation and private storage, without a kernel or database. */
class SaleOpportunityFileTest extends TestCase
{
    private $previousContainer;
    private $previousFacade;
    private string $diskRoot;
    private array $temporaryUploads = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $app = new Container();
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        if (!class_exists('Validator')) {
            class_alias(Validator::class, 'Validator');
        }

        $this->diskRoot = sys_get_temp_dir() . '/sab-opportunity-files-' . bin2hex(random_bytes(8));
        $app->instance('config', new Repository(['filesystems' => ['disks' => ['tenant' => [
            'driver' => 'local', 'root' => $this->diskRoot,
        ]]]]));
        $app->instance('filesystem', new FilesystemManager($app));
        $app->instance('validator', new Factory(new Translator(new ArrayLoader(), 'es'), $app));
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryUploads as $path) {
            if (is_file($path)) unlink($path);
        }
        if (isset($this->diskRoot)) {
            (new Filesystem())->deleteDirectory($this->diskRoot);
        }
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacade);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function opportunity()
    {
        return new class {
            public int $id = 27;
            public array $attachments = [];

            public function files() { return $this; }
            public function create(array $attributes) { $this->attachments[] = $attributes; }
        };
    }

    private function upload(string $name, string $kind): array
    {
        $file = $kind === 'png'
            ? UploadedFile::fake()->image($name, 2, 2)
            : UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        $request = Request::create('/sale-opportunities/uploads', 'POST', ['type' => 'sab-opportunity-'], [], ['file' => $file]);
        $response = (new SaleOpportunityFileController())->uploadFile($request);
        self::assertTrue($response['success']);
        $this->temporaryUploads[] = $response['data']['temp_path'];

        return ['response' => $response];
    }

    public static function validNames(): array
    {
        return [
            'ordinary PNG' => ['foto.png', 'png', 'foto-27.png'],
            'ChatGPT PNG with time dots' => ['Imagen de ChatGPT 23 nov 2026, 10_57_33 a.m.png', 'png', 'imagen-de-chatgpt-23-nov-2026-10-57-33-am-27.png'],
            'uppercase extension' => ['Foto.version.final.PNG', 'png', 'fotoversionfinal-27.png'],
            'versioned PDF' => ['propuesta.version.2.pdf', 'pdf', 'propuestaversion2-27.pdf'],
        ];
    }

    /** @dataProvider validNames */
    public function test_uploaded_attachments_keep_the_last_extension_and_full_base_name(string $name, string $kind, string $expected): void
    {
        $row = $this->upload($name, $kind);
        $bytes = file_get_contents($row['response']['data']['temp_path']);
        $opportunity = $this->opportunity();

        (new SaleOpportunityFileController())->saveFiles($opportunity, [$row]);

        self::assertSame([['filename' => $expected]], $opportunity->attachments);
        self::assertSame($bytes, Storage::disk('tenant')->get('sale_opportunity_files/' . $expected));
        $service = new SaleOpportunityFileService();
        self::assertSame($kind === 'png', $service->isImage($expected));
    }

    public function test_names_that_differ_after_a_dot_do_not_overwrite_each_other(): void
    {
        $rows = [$this->upload('foto.png.primera.png', 'png'), $this->upload('foto.png.segunda.png', 'png')];
        $opportunity = $this->opportunity();

        (new SaleOpportunityFileController())->saveFiles($opportunity, $rows);

        self::assertSame([
            ['filename' => 'fotopngprimera-27.png'], ['filename' => 'fotopngsegunda-27.png'],
        ], $opportunity->attachments);
        self::assertCount(2, Storage::disk('tenant')->allFiles('sale_opportunity_files'));
    }

    public function test_existing_attachments_are_preserved_without_uploading_again(): void
    {
        $opportunity = $this->opportunity();
        (new SaleOpportunityFileController())->saveFiles($opportunity, [
            ['filename' => 'original-27.png', 'response' => ['success' => true]],
        ]);

        self::assertSame([['filename' => 'original-27.png']], $opportunity->attachments);
        self::assertSame([], Storage::disk('tenant')->allFiles());
    }

    public static function invalidFiles(): array
    {
        return [
            'forbidden final extension' => ['foto.png.php', 'png', 'Extensión del archivo no permitida.'],
            'missing extension' => ['png', 'png', 'Extensión del archivo no permitida.'],
            'invalid content' => ['foto.version.png', 'text', 'Tipo de archivo no permitido'],
        ];
    }

    /** @dataProvider invalidFiles */
    public function test_invalid_files_are_rejected_before_writing_or_registering(string $name, string $kind, string $message): void
    {
        $row = $this->upload('valid.png', 'png');
        $row['response']['data']['filename'] = $name;
        if ($kind === 'text') {
            file_put_contents($row['response']['data']['temp_path'], 'This is not an image.');
        }
        $opportunity = $this->opportunity();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);
        try {
            (new SaleOpportunityFileController())->saveFiles($opportunity, [$row]);
        } finally {
            self::assertSame([], $opportunity->attachments);
            self::assertSame([], Storage::disk('tenant')->allFiles());
        }
    }
}
