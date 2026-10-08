<?php

namespace App\Services\Fiscal;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\Math\Matrix;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\Type\{PdfArray, PdfDictionary, PdfIndirectObjectReference, PdfName, PdfNumeric, PdfStream, PdfType};

/** Keep the wide layout, normal glyphs and logo proportions, and the original fiscal QR size. */
final class HkaA5Pdf extends Fpdi
{
    private array $formReferences = [];
    private int $formCount = 0;

    public function normalizeText(string $template, float $width, float $height): void
    {
        $page = $this->importedPages[$template];
        // Write nested forms before their parent, so all indirect references resolve.
        unset($this->importedPages[$template]);
        $parser = $this->getPdfReader($page['readerId'])->getParser();
        $page['stream'] = $this->normalizeStream($page['stream'], $width / $page['width'],
            $height / $page['height'], new Matrix(), $parser, $page['readerId'], 0);
        $this->importedPages[$template] = $page;
    }

    private function normalizeStream(PdfStream $stream, float $scaleX, float $scaleY, Matrix $parent,
        PdfParser $parser, string $readerId, int $depth): PdfStream
    {
        if ($depth > 16) throw new \RuntimeException('Recursive PDF form.');
        $dictionary = clone $stream->value;
        $matrix = PdfType::resolve(PdfDictionary::get($dictionary, 'Matrix'), $parser);
        $transform = $matrix instanceof PdfArray
            ? new Matrix(...array_map(fn ($value) => $value->value, $matrix->value))
            : new Matrix();
        $resources = PdfType::resolve(PdfDictionary::get($dictionary, 'Resources'), $parser);
        $objects = $resources instanceof PdfDictionary ? PdfType::resolve(PdfDictionary::get($resources, 'XObject'), $parser) : null;
        $resources = $resources instanceof PdfDictionary ? clone $resources : new PdfDictionary();
        $originals = $objects instanceof PdfDictionary ? $objects->value : [];
        $objects = $objects instanceof PdfDictionary ? clone $objects : new PdfDictionary();
        $source = $stream->getUnfilteredStream();
        $box = PdfType::flatten(PdfDictionary::get($dictionary, 'BBox'), $parser);
        $bounds = array_map(fn ($value) => $value->value, $box->value);
        $images = [];
        foreach ($originals as $name => $reference) {
            $object = PdfType::resolve($reference, $parser);
            if ($object instanceof PdfStream && PdfDictionary::get($object->value, 'Subtype')->value === 'Image') {
                $images[$name] = [PdfType::resolve(PdfDictionary::get($object->value, 'Width'), $parser)->value,
                    PdfType::resolve(PdfDictionary::get($object->value, 'Height'), $parser)->value];
            }
        }
        $layout = new HkaA5Layout(new HkaA5Fonts($resources, $parser), $transform->multiply($parent), $scaleX, $scaleY, $bounds, $images);
        HkaA5Text::normalize($source, $scaleX, $scaleY, $transform->multiply($parent), null, null, [$layout, 'observe']);
        $layout->finish();
        $content = HkaA5Text::normalize($source, $scaleX, $scaleY, $transform->multiply($parent),
            function (string $name, Matrix $ctm) use ($originals, $objects, $scaleX, $scaleY, $parser, $readerId, $depth, $layout) {
                if (!isset($originals[$name])) throw new \RuntimeException('Missing PDF XObject.');
                $object = PdfType::resolve($originals[$name], $parser);
                if (!$object instanceof PdfStream) throw new \RuntimeException('Invalid PDF XObject.');
                $type = PdfType::resolve(PdfDictionary::get($object->value, 'Subtype'), $parser);
                if (!$type instanceof PdfName || $type->value !== 'Form') return $layout->imagePlacement($name, $ctm);
                $normalized = $this->normalizeStream($object, $scaleX, $scaleY, $ctm, $parser, $readerId, $depth + 1);
                do { $id = 'A5Form'.(++$this->formCount); }
                while (isset($originals[$id]) || isset($objects->value[$id]));
                $key = 'a5-form-'.$this->formCount;
                $this->importedPages[$key] = ['objectNumber' => null, 'readerId' => $readerId, 'id' => $id, 'stream' => $normalized];
                $reference = PdfIndirectObjectReference::create(0, 0);
                $this->formReferences[spl_object_id($reference)] = $key;
                unset($objects->value[$name]);
                $objects->value[$id] = $reference;
                return $id;
            }, $layout);
        if ($originals) $resources->value['XObject'] = $objects;
        $dictionary->value['Resources'] = $resources;
        $content = gzcompress($content);
        unset($dictionary->value['DecodeParms'], $dictionary->value['DL']);
        $dictionary->value['Filter'] = PdfName::create('FlateDecode');
        $dictionary->value['Length'] = PdfNumeric::create(strlen($content));
        return PdfStream::create($dictionary, $content);
    }

    protected function writePdfType(PdfType $value)
    {
        if ($value instanceof PdfIndirectObjectReference && isset($this->formReferences[spl_object_id($value)])) {
            $key = $this->formReferences[spl_object_id($value)];
            $this->_put($this->importedPages[$key]['objectNumber'].' 0 R ', false);
            return;
        }
        parent::writePdfType($value);
    }
}
