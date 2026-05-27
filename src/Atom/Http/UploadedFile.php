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

    public function move(string $root, string $relativePath = ''): bool
    {
        if (!$this->ok) return false;
        $dest = $this->destinationWithinRoot(
            $root,
            $relativePath !== '' ? $relativePath : basename(str_replace('\\', '/', $this->name)),
        );
        if ($dest === null) return false;
        return move_uploaded_file($this->tmp, $dest);
    }

    private function destinationWithinRoot(string $root, string $relativePath): ?string
    {
        if ($root === '' || $relativePath === '' || str_contains($root . $relativePath, "\0")) {
            return null;
        }
        $relativePath = str_replace('\\', '/', $relativePath);
        if (str_starts_with($relativePath, '/') || preg_match('#^[A-Za-z]:/#', $relativePath)) {
            return null;
        }
        $segments = explode('/', $relativePath);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }
        if (!is_dir($root) && !@mkdir($root, Constants::DIR_PERMISSIONS, true) && !is_dir($root)) {
            return null;
        }
        $rootReal = realpath($root);
        if ($rootReal === false) {
            return null;
        }
        $dest = $rootReal . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, Constants::DIR_PERMISSIONS, true) && !is_dir($dir)) {
            return null;
        }
        $dirReal = realpath($dir);
        if ($dirReal === false) {
            return null;
        }
        $rootNorm = rtrim(str_replace('\\', '/', $rootReal), '/');
        $dirNorm = rtrim(str_replace('\\', '/', $dirReal), '/');
        if ($dirNorm !== $rootNorm && !str_starts_with($dirNorm, $rootNorm . '/')) {
            return null;
        }
        return $dest;
    }
}
