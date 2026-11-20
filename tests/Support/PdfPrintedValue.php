<?php
namespace Tests\Support;

use PHPUnit\Framework\Assert;

/** Assertions for the existing PDF cells, including values wrapped by mPDF. */
final class PdfPrintedValue
{
    /** Retain original cell wrapping; never concatenate values from different PDF blocks. */
    public static function assertNumber(string $expected,string $text,string $message,string $path): void
    {
        $characters=array_map(static fn($character)=>preg_quote($character,'~'),str_split($expected));
        $pattern='~(?<![0-9.,])'.implode('\\s*',$characters).'(?![0-9.,])~u';
        if (preg_match($pattern,$text)===1) {
            Assert::assertTrue(true,$message);
            return;
        }
        $process=new \Symfony\Component\Process\Process(['pdftotext','-bbox-layout',$path,'-']);
        $process->setTimeout(30);$process->mustRun();
        $dom=new \DOMDocument();Assert::assertTrue($dom->loadXML($process->getOutput(),LIBXML_NONET));
        $xpath=new \DOMXPath($dom);$found=false;
        $headerPattern=str_contains($message,'precio') ? '/^P\.?UNIT(?:ARIO)?$/iu' : '/^CANT(?:IDAD)?\.?$/iu';
        foreach ($xpath->query('//*[local-name()="page"]') as $page) {
            $headers=[];$words=$xpath->query('.//*[local-name()="word"]',$page);
            foreach ($words as $word) {
                if (preg_match($headerPattern,trim($word->textContent))) $headers[]=$word;
            }
            foreach ($headers as $header) {
                $center=((float)$header->getAttribute('xMin')+(float)$header->getAttribute('xMax'))/2;
                $left=0;$right=(float)$page->getAttribute('width');
                foreach ($words as $word) {
                    if ($word===$header || abs((float)$word->getAttribute('yMin')-(float)$header->getAttribute('yMin'))>2) continue;
                    $neighbor=((float)$word->getAttribute('xMin')+(float)$word->getAttribute('xMax'))/2;
                    if ($neighbor<$center) $left=max($left,($neighbor+$center)/2);
                    if ($neighbor>$center) $right=min($right,($neighbor+$center)/2);
                }
                foreach ($xpath->query('.//*[local-name()="block"]',$page) as $block) {
                    $value='';
                    foreach ($xpath->query('.//*[local-name()="word"]',$block) as $word) {
                        $position=((float)$word->getAttribute('xMin')+(float)$word->getAttribute('xMax'))/2;
                        if ($position>$left && $position<$right
                            && (float)$word->getAttribute('yMin')>(float)$header->getAttribute('yMax')) $value.=$word->textContent;
                    }
                    if ($value===$expected) $found=true;
                }
            }
        }
        Assert::assertTrue($found,$message.': los fragmentos deben pertenecer a la columna original dentro del mismo bloque PDF');
    }
}
