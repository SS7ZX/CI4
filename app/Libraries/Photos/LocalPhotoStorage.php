<?php

declare(strict_types=1);

namespace App\Libraries\Photos;

use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

final class LocalPhotoStorage implements PhotoStorageInterface
{
    private const MAX_SIZE = 2_097_152;
    private const MAX_DIMENSION = 6000;
    private const MAX_PIXELS = 20_000_000;

    public function store(UploadedFile $photo, string $cvId): string
    {
        if (! $this->isUuid($cvId)) {
            throw new RuntimeException(lang('CvBuilder.photo_invalid'));
        }
        if (! $photo->isValid() || $photo->hasMoved()) {
            throw new RuntimeException(lang('CvBuilder.photo_invalid'));
        }
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            throw new RuntimeException(lang('CvBuilder.photo_gd_required'));
        }
        if ($photo->getSize() > self::MAX_SIZE) {
            throw new RuntimeException(lang('CvBuilder.photo_too_large'));
        }

        $mime = $photo->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException(lang('CvBuilder.photo_type_invalid'));
        }

        $contents = file_get_contents($photo->getTempName());
        if ($contents === false) {
            throw new RuntimeException(lang('CvBuilder.photo_invalid'));
        }
        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new RuntimeException(lang('CvBuilder.photo_invalid'));
        }

        $width  = imagesx($image);
        $height = imagesy($image);
        if (
            $width < 1
            || $height < 1
            || $width > self::MAX_DIMENSION
            || $height > self::MAX_DIMENSION
            || $width * $height > self::MAX_PIXELS
        ) {
            imagedestroy($image);

            throw new RuntimeException(lang('CvBuilder.photo_dimensions_invalid'));
        }

        $directory = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'cv-photos' . DIRECTORY_SEPARATOR . strtolower($cvId);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            imagedestroy($image);

            throw new RuntimeException(lang('CvBuilder.photo_storage_failed'));
        }

        $filename = bin2hex(random_bytes(20)) . '.jpg';
        $absolute = $directory . DIRECTORY_SEPARATOR . $filename;
        if (! imagejpeg($image, $absolute, 88)) {
            imagedestroy($image);

            throw new RuntimeException(lang('CvBuilder.photo_storage_failed'));
        }
        imagedestroy($image);
        chmod($absolute, 0600);

        return 'cv-photos/' . strtolower($cvId) . '/' . $filename;
    }

    public function delete(string $photoPath): void
    {
        $absolute = $this->resolve($photoPath);
        if (is_file($absolute) && ! unlink($absolute)) {
            throw new RuntimeException(lang('CvBuilder.photo_delete_failed'));
        }
    }

    public function read(string $photoPath): array
    {
        $absolute = $this->resolve($photoPath);
        if (! is_file($absolute)) {
            throw new RuntimeException(lang('CvBuilder.photo_not_found'));
        }

        $contents = file_get_contents($absolute);
        if ($contents === false) {
            throw new RuntimeException(lang('CvBuilder.photo_read_failed'));
        }

        return ['contents' => $contents, 'mime' => 'image/jpeg'];
    }

    private function resolve(string $photoPath): string
    {
        if (preg_match('/\Acv-photos\/[0-9a-f-]{36}\/[0-9a-f]{40}\.jpg\z/i', $photoPath) !== 1) {
            throw new RuntimeException(lang('CvBuilder.photo_invalid'));
        }

        return WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $photoPath);
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value) === 1;
    }
}
