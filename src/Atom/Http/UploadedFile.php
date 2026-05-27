<?php
declare(strict_types=1);
namespace Atom\Http;

use Atom\Constants;
use Atom\Support\Regex;

final class UploadedFile
{
    /** @param ?array{name:string,type:string,tmp_name:string,error:int,size:int} $meta */
    private function __construct(
        private ?array $meta,
    ) {}

    public static function fromFileArray(array $file): self
    {
        return new self($file);
    }

    public static function empty(): self
    {
        return new self(null);
    }

    public bool $ok     { get => $this->meta !== null && $this->meta['error'] === UPLOAD_ERR_OK; }
    public int $size    { get => (int) ($this->meta['size'] ?? 0); }
    public int $error   { get => (int) ($this->meta['error'] ?? UPLOAD_ERR_NO_FILE); }
    public string $name { get => (string) ($this->meta['name'] ?? ''); }
    public string $type { get => (string) ($this->meta['type'] ?? ''); }
    public string $tmp  { get => (string) ($this->meta['tmp_name'] ?? ''); }
    public string $ext  { get {
        $m = Regex::match('#\.(\w+)$#', $this->name);
        return $m !== null ? strtolower($m[1]) : '';
    }}

    public function move(string $dest): bool
    {
        if (!$this->ok) return false;
        if (str_contains($dest, "\0")) return false;
        $normalized = str_replace('\\', '/', $dest);
        if (str_contains($normalized, '../') || str_ends_with($normalized, '/..')) return false;
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, Constants::DIR_PERMISSIONS, true) && !is_dir($dir)) {
            return false;
        }
        return move_uploaded_file($this->tmp, $dest);
    }
}
