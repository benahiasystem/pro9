<?php

namespace Modules\Accounting\Domain;

/** TOTP interoperable con RFC 6238; primitivas HMAC del runtime PHP. */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public function secret(): string
    {
        $out = '';
        $buffer = 0;
        $bits = 0;
        foreach (str_split(random_bytes(20)) as $c) {
            $buffer = $buffer << 8 | ord($c);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= self::ALPHABET[$buffer >> $bits & 31];
            }
            $buffer &= (1 << $bits) - 1;
        }
        return $out;
    }
    private function decode(string $secret): string
    {
        $buffer = 0;
        $bits = 0;
        $out = '';
        foreach (str_split(strtoupper(rtrim($secret, '='))) as $c) {
            $v = strpos(self::ALPHABET, $c);
            if ($v === false) {
                throw new \InvalidArgumentException('Clave TOTP inválida.');
            }
            $buffer = $buffer << 5 | $v;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr($buffer >> $bits & 255);
                $buffer &= (1 << $bits) - 1;
            }
        }
        return $out;
    }
    public function code(string $secret, int $timestamp, int $digits = 6): string
    {
        $counter = intdiv($timestamp, 30);
        $message = pack('N2', $counter >> 32 & 0xffffffff, $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $message, $this->decode($secret), true);
        $offset = ord($hash[strlen($hash) - 1]) & 15;
        $part = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
        return str_pad((string) ($part % 10 ** $digits), $digits, '0', STR_PAD_LEFT);
    }
    public function counter(string $secret, string $code, int $timestamp, int $last = -1): ?int
    {
        if (!preg_match('/^[0-9]{6}$/D', $code)) {
            return null;
        }
        $now = intdiv($timestamp, 30);
        foreach ([$now, $now - 1, $now + 1] as $step) {
            if ($step > $last && hash_equals($this->code($secret, $step * 30), $code)) {
                return $step;
            }
        }
        return null;
    }
}
