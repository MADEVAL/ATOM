<?php
declare(strict_types=1);
namespace Atom\Tests\Http;

use Atom\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(UploadedFile::class)]
final class UploadedFileTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/atom_upload_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach ((array) glob($this->tmpDir . '/*') as $f) @unlink($f);
            rmdir($this->tmpDir);
        }
    }

    #[Test]
    public function from_valid_file_array(): void
    {
        $file = UploadedFile::fromFileArray([
            'name' => 'photo.jpg',
            'type' => 'image/jpeg',
            'size' => 1024,
            'tmp_name' => '/tmp/php123',
            'error' => UPLOAD_ERR_OK,
        ]);
        $this->assertTrue($file->ok);
        $this->assertSame(1024, $file->size);
        $this->assertSame('photo.jpg', $file->name);
        $this->assertSame('image/jpeg', $file->type);
        $this->assertSame('/tmp/php123', $file->tmp);
        $this->assertSame('jpg', $file->ext);
    }

    #[Test]
    public function error_file_is_not_ok(): void
    {
        $file = UploadedFile::fromFileArray([
            'name' => 'bad.exe', 'type' => '', 'size' => 0,
            'tmp_name' => '', 'error' => UPLOAD_ERR_FORM_SIZE,
        ]);
        $this->assertFalse($file->ok);
        $this->assertSame(UPLOAD_ERR_FORM_SIZE, $file->error);
    }

    #[Test]
    public function empty_file(): void
    {
        $file = UploadedFile::empty();
        $this->assertFalse($file->ok);
        $this->assertSame(0, $file->size);
        $this->assertSame(UPLOAD_ERR_NO_FILE, $file->error);
        $this->assertSame('', $file->name);
        $this->assertSame('', $file->ext);
    }

    #[Test]
    public function ext_from_filename(): void
    {
        $file = UploadedFile::fromFileArray([
            'name' => 'archive.tar.gz', 'type' => '', 'size' => 0,
            'tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_OK,
        ]);
        $this->assertSame('gz', $file->ext);
    }

    #[Test]
    public function ext_empty_when_no_extension(): void
    {
        $file = UploadedFile::fromFileArray([
            'name' => 'README', 'type' => '', 'size' => 0,
            'tmp_name' => '/tmp/x', 'error' => UPLOAD_ERR_OK,
        ]);
        $this->assertSame('', $file->ext);
    }

    #[Test]
    public function move_returns_false_for_invalid_file(): void
    {
        $file = UploadedFile::empty();
        $this->assertFalse($file->move('/tmp/dest'));
    }

    #[Test]
    public function move_returns_false_for_null_byte_in_path(): void
    {
        $tempFile = $this->tmpDir . '/source.txt';
        file_put_contents($tempFile, 'data');
        $file = UploadedFile::fromFileArray([
            'name' => 'test.txt', 'type' => 'text/plain', 'size' => 4,
            'tmp_name' => $tempFile, 'error' => UPLOAD_ERR_OK,
        ]);
        $this->assertFalse($file->move($this->tmpDir . '/bad' . "\0" . 'path'));
    }

    #[Test]
    public function move_returns_false_for_path_traversal(): void
    {
        $tempFile = $this->tmpDir . '/source.txt';
        file_put_contents($tempFile, 'data');
        $file = UploadedFile::fromFileArray([
            'name' => 'test.txt', 'type' => 'text/plain', 'size' => 4,
            'tmp_name' => $tempFile, 'error' => UPLOAD_ERR_OK,
        ]);
        $this->assertFalse($file->move($this->tmpDir . '/../etc/passwd'));
    }

    #[Test]
    public function move_returns_false_for_path_ending_in_dotdot(): void
    {
        $tempFile = $this->tmpDir . '/source.txt';
        file_put_contents($tempFile, 'data');
        $file = UploadedFile::fromFileArray([
            'name' => 'test.txt', 'type' => 'text/plain', 'size' => 4,
            'tmp_name' => $tempFile, 'error' => UPLOAD_ERR_OK,
        ]);
        $this->assertFalse($file->move($this->tmpDir . '/a/..'));
    }

    #[Test]
    public function move_returns_false_when_parent_is_file(): void
    {
        $blockFile = $this->tmpDir . '/block.txt';
        file_put_contents($blockFile, 'block');
        $tempFile = $this->tmpDir . '/s.txt';
        file_put_contents($tempFile, 'data');
        $file = UploadedFile::fromFileArray([
            'name' => 't.txt', 'type' => 'text/plain', 'size' => 4,
            'tmp_name' => $tempFile, 'error' => UPLOAD_ERR_OK,
        ]);
        try {
            $this->assertFalse($file->move($blockFile . '/sub/dest.txt'));
        } finally {
            unlink($blockFile);
        }
    }
}
