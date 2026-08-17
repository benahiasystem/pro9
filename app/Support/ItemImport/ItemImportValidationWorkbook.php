<?php

// ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
namespace App\Support\ItemImport;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Comment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use RuntimeException;

class ItemImportValidationWorkbook
{
    public const DIRECTORY = 'item-import-validation';
    public const COMMENT_AUTHOR = 'Benahia';
    public const COMMENT_PREFIX = "A corregir:\n";
    public const ERROR_FILL_ARGB = 'FFFFA6A6';

    public function store(UploadedFile $file, array $errors, int $userId): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'items_validation_');

        if ($temporaryPath === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal de validación.');
        }

        try {
            $this->build($file->getRealPath(), $temporaryPath, $errors);
            $token = (string) Str::uuid();
            $path = self::pathFor($userId, $token);

            if (!Storage::disk('local')->put($path, file_get_contents($temporaryPath))) {
                throw new RuntimeException('No se pudo guardar el archivo de validación.');
            }

            return $token;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    public function build(string $sourcePath, string $targetPath, array $errors): void
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($sourcePath);
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $this->removePreviousValidationMarks($worksheet);

            foreach ($worksheet->getCellCollection()->getCoordinates() as $coordinate) {
                $cell = $worksheet->getCell($coordinate);

                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    $cell->setValueExplicit((string) $cell->getValue(), DataType::TYPE_STRING);
                }
            }
        }

        $sheet = $spreadsheet->getSheet(0);
        $comments = $sheet->getComments();

        foreach ($errors as $error) {
            $coordinate = Coordinate::stringFromColumnIndex((int) $error['column']) . (int) $error['row'];
            $sheet->getStyle($coordinate)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB(self::ERROR_FILL_ARGB);

            $richText = new RichText();
            $richText->createText($this->commentText($error['messages']));
            $comment = new Comment();
            $comment->setAuthor(self::COMMENT_AUTHOR);
            $comment->setText($richText);
            $comment->setHeight('150px');
            $comment->setWidth('300px');
            $comments[$coordinate] = $comment;
        }

        $sheet->setComments($comments);
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($targetPath);
        $spreadsheet->disconnectWorksheets();
    }

    private function removePreviousValidationMarks($sheet): void
    {
        $comments = $sheet->getComments();

        foreach ($comments as $coordinate => $comment) {
            $text = str_replace(
                ["\r\n", "\r"],
                "\n",
                ltrim($comment->getText()->getPlainText(), "\xEF\xBB\xBF")
            );

            if (strpos($text, self::COMMENT_PREFIX . '- ') !== 0) {
                continue;
            }

            $fill = $sheet->getStyle($coordinate)->getFill();

            if (
                $fill->getFillType() === Fill::FILL_SOLID
                && $fill->getStartColor()->getARGB() === self::ERROR_FILL_ARGB
            ) {
                $fill->setFillType(Fill::FILL_NONE);
            }

            unset($comments[$coordinate]);
        }

        $sheet->setComments($comments);
    }

    public static function pathFor(int $userId, string $token): string
    {
        return sprintf('%s/%d/%s.xlsx', self::DIRECTORY, $userId, $token);
    }

    private function commentText(array $messages): string
    {
        return self::COMMENT_PREFIX . '- ' . implode("\n- ", $messages);
    }
}
// ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
