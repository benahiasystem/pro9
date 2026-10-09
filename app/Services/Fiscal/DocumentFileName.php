<?php

namespace App\Services\Fiscal;

/** Download/attachment names only. Stored paths and provider identifiers stay unchanged. */
final class DocumentFileName
{
    public static function archiveEntry($storedName, array &$used): string
    {
        $name = self::visible($storedName);
        $candidate = $name;
        $index = 1;
        while (isset($used[$candidate])) {
            ++$index;
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $candidate = pathinfo($name, PATHINFO_FILENAME).' ('.$index.')'.($extension === '' ? '' : '.'.$extension);
        }
        $used[$candidate] = true;
        return $candidate;
    }

    public static function visible($storedName): string
    {
        $name = (string) $storedName;
        // Legacy branch discriminator belongs only to the private storage key.
        $name = preg_replace_callback('/(?:^|-)SIN_SERIE_S[0-9]+-([0-9]+)/', function ($parts) {
            return ($parts[0][0] === '-' ? '-' : '') . FiscalIdentity::displayNumber($parts[1]);
        }, $name);
        return preg_replace_callback('/(?:^|-)([0-9]+)(?=(?:\.pdf)?$)/', function ($parts) {
            return ($parts[0][0] === '-' ? '-' : '') . FiscalIdentity::displayNumber($parts[1]);
        }, $name);
    }
}
