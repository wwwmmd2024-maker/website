<?php

declare(strict_types=1);

namespace IRJalali\App\Services;

use IRJalali\App\Repositories\MediaRepository;
use IRJalali\Core\Config\Config;
use IRJalali\Core\Filesystem\Filesystem;
use IRJalali\Core\Security\Sanitize;

/**
 * Secure upload pipeline: extension + MIME + content validation,
 * filename sanitization, traversal-safe storage, thumbnails.
 */
final class MediaService
{
    /** @var array<string, list<string>> */
    private const MIME_MAP = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'], 'webp' => ['image/webp'],
        'avif' => ['image/avif'], 'gif' => ['image/gif'],
        'svg' => ['image/svg+xml', 'text/xml', 'application/xml'],
        'mp4' => ['video/mp4'], 'webm' => ['video/webm'],
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'zip' => ['application/zip'],
    ];

    public function __construct(
        private readonly MediaRepository $media,
        private readonly Filesystem $fs,
        private readonly Config $config,
    ) {
    }

    /**
     * @param array<string, mixed> $file normalized $_FILES entry
     * @return array{ok: bool, error?: string, media?: array<string, mixed>}
     */
    public function upload(array $file, ?int $userId = null, string $folder = '/'): array
    {
        $original = (string) ($file['name'] ?? '');
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($original === '' || $tmp === '' || !is_file($tmp)) {
            return ['ok' => false, 'error' => 'فایل معتبر نیست.'];
        }

        $maxKb = (int) $this->config->get('security.upload_max_kb', 10240);
        if ($size <= 0 || $size > $maxKb * 1024) {
            return ['ok' => false, 'error' => "حجم فایل بیش از حد مجاز است (حداکثر {$maxKb} کیلوبایت)."];
        }

        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = $this->config->get('security.allowed_upload_extensions', array_keys(self::MIME_MAP));
        if (!in_array($extension, $allowed, true) || !isset(self::MIME_MAP[$extension])) {
            return ['ok' => false, 'error' => 'پسوند فایل مجاز نیست.'];
        }

        // Real MIME sniffing (never trust the client-provided type).
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if (!in_array($mime, self::MIME_MAP[$extension], true)) {
            return ['ok' => false, 'error' => 'نوع واقعی فایل با پسوند آن مطابقت ندارد.'];
        }

        // Content validation for raster images.
        $width = 0;
        $height = 0;
        if (str_starts_with($mime, 'image/') && $extension !== 'svg') {
            $info = @getimagesize($tmp);
            if ($info === false) {
                return ['ok' => false, 'error' => 'فایل تصویری خراب است.'];
            }
            [$width, $height] = $info;
        }
        if ($extension === 'svg' && !$this->isSafeSvg($tmp)) {
            return ['ok' => false, 'error' => 'فایل SVG حاوی کد غیرمجاز است.'];
        }

        $folder = '/' . trim($folder, '/');
        if ($folder !== '/') {
            $folder = '/' . trim((string) preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $folder), '-');
        }
        $safeBase = pathinfo(Sanitize::filename($original), PATHINFO_FILENAME);
        $stored = date('Y/m') . '/' . substr($safeBase, 0, 60) . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $relative = 'storage/uploads/' . ltrim($stored, '/');

        if (!$this->fs->moveUploadedFile($tmp, $relative)) {
            return ['ok' => false, 'error' => 'خطا در ذخیره فایل.'];
        }
        @chmod($this->fs->path($relative), 0644);

        // Thumbnails for raster images (GD when available).
        $this->makeThumbnails($relative, $extension, $mime);

        $record = $this->media->create([
            'uuid' => $this->uuid(),
            'filename' => $stored,
            'original_name' => mb_substr($original, 0, 180),
            'mime' => $mime,
            'extension' => $extension,
            'size_bytes' => $size,
            'folder' => $folder,
            'width' => $width,
            'height' => $height,
            'alt' => $safeBase,
            'uploaded_by' => $userId,
        ]);

        return ['ok' => true, 'media' => $record];
    }

    public function delete(int $id): bool
    {
        $record = $this->media->find($id);
        if ($record === null) {
            return false;
        }
        $this->media->softDelete($id);
        $base = 'storage/uploads/' . ltrim((string) $record['filename'], '/');
        $this->fs->delete($base);
        $this->fs->delete($this->thumbPath($base, 'thumb'));
        $this->fs->delete($this->thumbPath($base, 'medium'));

        return true;
    }

    public function publicUrl(array $record, string $size = 'full'): string
    {
        $base = 'storage/uploads/' . ltrim((string) $record['filename'], '/');
        if ($size !== 'full') {
            $thumb = $this->thumbPath($base, $size);
            if ($this->fs->exists($thumb)) {
                return '/' . $thumb;
            }
        }

        return '/' . $base;
    }

    private function isSafeSvg(string $path): bool
    {
        $contents = file_get_contents($path);
        if ($contents === false || strlen($contents) > 512 * 1024) {
            return false;
        }
        // Block scripts, event handlers, foreign objects and external refs.
        if (preg_match('/<\s*script|on\w+\s*=|<\s*foreignObject|<\s*iframe|javascript\s*:/i', $contents)) {
            return false;
        }
        $xml = @simplexml_load_string($contents);

        return $xml !== false;
    }

    private function makeThumbnails(string $relative, string $extension, string $mime): void
    {
        if (!extension_loaded('gd') || !str_starts_with($mime, 'image/')) {
            return;
        }
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return;
        }
        try {
            $src = $this->fs->path($relative);
            $image = match ($extension) {
                'jpg', 'jpeg' => @imagecreatefromjpeg($src),
                'png' => @imagecreatefrompng($src),
                'webp' => @imagecreatefromwebp($src),
                'gif' => @imagecreatefromgif($src),
                default => false,
            };
            if ($image === false) {
                return;
            }
            $width = imagesx($image);
            $height = imagesy($image);
            if ($width <= 0 || $height <= 0) {
                return;
            }
            foreach (['thumb' => 300, 'medium' => 800] as $label => $max) {
                if ($width <= $max) {
                    continue;
                }
                $ratio = $max / $width;
                $newWidth = $max;
                $newHeight = (int) round($height * $ratio);
                $canvas = imagecreatetruecolor($newWidth, $newHeight);
                if (in_array($extension, ['png', 'webp', 'gif'], true)) {
                    imagealphablending($canvas, false);
                    imagesavealpha($canvas, true);
                }
                imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                $dest = $this->fs->path($this->thumbPath($relative, $label));
                match ($extension) {
                    'jpg', 'jpeg' => imagejpeg($canvas, $dest, 82),
                    'png' => imagepng($canvas, $dest, 8),
                    'webp' => imagewebp($canvas, $dest, 82),
                    'gif' => imagegif($canvas, $dest),
                    default => null,
                };
                // GD objects are reference-counted on PHP 8+; imagedestroy()
                // is a deprecated no-op since PHP 8.0/8.5.
                unset($canvas);
                @chmod($dest, 0644);
            }
            unset($image);
        } catch (\Throwable) {
            // Thumbnails are best-effort; the original is already stored safely.
        }
    }

    private function thumbPath(string $relative, string $label): string
    {
        $info = pathinfo($relative);

        return $info['dirname'] . '/' . $info['filename'] . '-' . $label . '.' . ($info['extension'] ?? 'jpg');
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
