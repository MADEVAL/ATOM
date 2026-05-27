<?php
declare(strict_types=1);
namespace Atom\Support;

final readonly class Encrypt
{
    private const METHOD = 'aes-256-gcm';

    public static function encrypt(string $plain, string $key): string
    {
        $iv = random_bytes(\Atom\Constants::ENCRYPT_IV_BYTES);
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
        if ($data === false || strlen($data) < \Atom\Constants::ENCRYPT_MIN_PAYLOAD) {
            throw new \RuntimeException('Invalid encrypted payload');
        }
        $iv   = substr($data, 0, \Atom\Constants::ENCRYPT_IV_BYTES);
        $tag  = substr($data, \Atom\Constants::ENCRYPT_IV_BYTES, \Atom\Constants::ENCRYPT_GCM_TAG_BYTES);
        $text = substr($data, \Atom\Constants::ENCRYPT_MIN_PAYLOAD);
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
