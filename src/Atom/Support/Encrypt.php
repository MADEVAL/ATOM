<?php
declare(strict_types=1);
namespace Atom\Support;

use Atom\Constants;

final readonly class Encrypt
{
    private const METHOD = 'aes-256-gcm';

    public static function encrypt(string $plain, string $key): string
    {
        $iv = random_bytes(Constants::ENCRYPT_IV_BYTES);
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
        if ($data === false || strlen($data) < Constants::ENCRYPT_MIN_PAYLOAD) {
            throw new \RuntimeException('Invalid encrypted payload');
        }
        $iv   = substr($data, 0, Constants::ENCRYPT_IV_BYTES);
        $tag  = substr($data, Constants::ENCRYPT_IV_BYTES, Constants::ENCRYPT_GCM_TAG_BYTES);
        $text = substr($data, Constants::ENCRYPT_MIN_PAYLOAD);
        $plain = openssl_decrypt($text, self::METHOD, self::deriveKey($key), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed');
        }
        return $plain;
    }

    /** Derives a 256-bit encryption key via SHA-256 */
    private static function deriveKey(string $key): string
    {
        return hash('sha256', $key, true);
    }
}
