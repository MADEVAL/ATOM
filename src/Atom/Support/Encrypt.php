<?php
declare(strict_types=1);
namespace Atom\Support;

final readonly class Encrypt
{
    private const METHOD = 'aes-256-gcm';

    public static function encrypt(string $plain, string $key): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::METHOD, self::deriveKey($key), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload, string $key): string
    {
        $data = base64_decode($payload, true);
        if ($data === false || strlen($data) < 28) {
            throw new \RuntimeException('Invalid encrypted payload');
        }
        $iv   = substr($data, 0, 12);
        $tag  = substr($data, 12, 16);
        $text = substr($data, 28);
        $plain = openssl_decrypt($text, self::METHOD, self::deriveKey($key), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed');
        }
        return $plain;
    }

    private static function deriveKey(string $key): string
    {
        return hash('sha256', $key, true);
    }
}
