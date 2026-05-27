<?php
declare(strict_types=1);
namespace Atom\Tests\Support;

use Atom\Support\Encrypt;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Encrypt::class)]
final class EncryptTest extends TestCase
{
    #[Test]
    public function encrypt_and_decrypt_roundtrip(): void
    {
        $encrypted = Encrypt::encrypt('hello world', 'secret-key');
        $this->assertNotSame('hello world', $encrypted);
        $decrypted = Encrypt::decrypt($encrypted, 'secret-key');
        $this->assertSame('hello world', $decrypted);
    }

    #[Test]
    public function wrong_key_fails_decryption(): void
    {
        $encrypted = Encrypt::encrypt('data', 'key1');
        $this->expectException(\RuntimeException::class);
        Encrypt::decrypt($encrypted, 'key2');
    }

    #[Test]
    public function tampered_payload_fails(): void
    {
        $encrypted = Encrypt::encrypt('data', 'key');
        $this->expectException(\RuntimeException::class);
        Encrypt::decrypt($encrypted . 'x', 'key');
    }

    #[Test]
    public function each_encryption_produces_different_ciphertext(): void
    {
        $a = Encrypt::encrypt('same', 'key');
        $b = Encrypt::encrypt('same', 'key');
        $this->assertNotSame($a, $b);
    }
}
