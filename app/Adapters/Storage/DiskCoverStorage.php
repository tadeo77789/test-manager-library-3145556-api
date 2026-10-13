<?php

declare(strict_types=1);

namespace App\Adapters\Storage;

use App\Application\Dto\StoredCover;
use App\Application\Ports\CoverStorage;
use App\Application\Ports\IdGenerator;
use App\Domain\Errors\ValidationFailed;

/** Covers on local disk. The type is decided by the file content (magic bytes + image decode), never by name. */
final class DiskCoverStorage implements CoverStorage
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    private const KEY_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(png|jpg)$/';

    private ?string $resolved = null;

    public function __construct(
        private readonly string $directory,
        private readonly string $fallbackDirectory,
        private readonly IdGenerator $ids,
    ) {
    }

    public function store(string $bytes): string
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw new ValidationFailed('La portada debe pesar como máximo 2 MB.', [['field' => 'file', 'issue' => 'tamaño máximo 2 MB']]);
        }
        $ext = $this->detect($bytes);
        if ($ext === null) {
            throw new ValidationFailed('La portada debe ser una imagen JPEG o PNG.', [['field' => 'file', 'issue' => 'solo image/jpeg o image/png']]);
        }
        $key = $this->ids->next() . '.' . $ext;
        file_put_contents($this->dir() . DIRECTORY_SEPARATOR . $key, $bytes);

        return $key;
    }

    public function read(string $key): ?StoredCover
    {
        $path = $this->path($key);
        if ($path === null || ! is_file($path)) {
            return null;
        }
        $bytes = file_get_contents($path);

        return $bytes === false ? null : new StoredCover($bytes, str_ends_with($key, '.png') ? 'image/png' : 'image/jpeg');
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    private function detect(string $bytes): ?string
    {
        $ext = null;
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            $ext = 'png';
        } elseif (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            $ext = 'jpg';
        }
        if ($ext === null) {
            return null;
        }
        $info = @getimagesizefromstring($bytes);
        $expected = $ext === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;

        return ($info !== false && $info[2] === $expected) ? $ext : null;
    }

    private function path(string $key): ?string
    {
        return preg_match(self::KEY_PATTERN, $key) === 1 ? $this->dir() . DIRECTORY_SEPARATOR . $key : null;
    }

    /** Uses COVERS_DIR when it can be created and written; otherwise falls back to storage/covers. */
    private function dir(): string
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }
        foreach ([$this->directory, $this->fallbackDirectory] as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if (! is_dir($candidate)) {
                @mkdir($candidate, 0775, true);
            }
            if (is_dir($candidate) && is_writable($candidate)) {
                return $this->resolved = $candidate;
            }
        }
        throw new \RuntimeException('No writable covers directory.');
    }
}
