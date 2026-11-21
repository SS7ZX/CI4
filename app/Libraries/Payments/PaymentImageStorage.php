<?php

declare(strict_types=1);

namespace App\Libraries\Payments;

use CodeIgniter\HTTP\Files\UploadedFile;

final class PaymentImageStorage
{
    private const MAX_DIMENSION = 6000;
    private const MAX_PIXELS = 20_000_000;
    private const QR_FILENAME = 'merchant-qr.img';
    private const LEGACY_QR_FILENAME = 'merchant-qr.png';

    public function __construct(private readonly string $storageDirectory = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'cv-payments')
    {
    }

    public function hasQr(): bool
    {
        return is_file($this->directory() . DIRECTORY_SEPARATOR . self::QR_FILENAME)
            || is_file($this->directory() . DIRECTORY_SEPARATOR . self::LEGACY_QR_FILENAME);
    }

    public function storeProof(UploadedFile $file, int $maxSize): string
    {
        $image = $this->uploadedImage($file, $maxSize);
        $filename = 'proof-' . bin2hex(random_bytes(20)) . '.' . $this->extension($image['mime']);
        $this->writeImage($image['contents'], $this->directory() . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }

    public function storeQr(UploadedFile $file, int $maxSize): void
    {
        $directory = $this->directory();
        $temporaryPath = $directory . DIRECTORY_SEPARATOR . 'qr-' . bin2hex(random_bytes(20)) . '.tmp';
        $targetPath = $directory . DIRECTORY_SEPARATOR . self::QR_FILENAME;
        $backupPath = $directory . DIRECTORY_SEPARATOR . 'qr-backup-' . bin2hex(random_bytes(20)) . '.tmp';

        $image = $this->uploadedImage($file, $maxSize);
        $this->writeImage($image['contents'], $temporaryPath);
        $hasPrevious = is_file($targetPath);
        if ($hasPrevious && ! rename($targetPath, $backupPath)) {
            if (! unlink($temporaryPath)) {
                log_message('error', 'File QR pembayaran sementara tidak dapat dibersihkan.');
            }

            throw new PaymentException(lang('Payments.qr_save_failed'));
        }
        if (! rename($temporaryPath, $targetPath)) {
            if ($hasPrevious && ! rename($backupPath, $targetPath)) {
                log_message('critical', 'QR pembayaran lama tidak dapat dipulihkan setelah penggantian gagal.');
            }
            if (is_file($temporaryPath)) {
                if (! unlink($temporaryPath)) {
                    log_message('error', 'File QR pembayaran sementara tidak dapat dibersihkan.');
                }
            }

            throw new PaymentException(lang('Payments.qr_save_failed'));
        }
        if ($hasPrevious && is_file($backupPath) && ! unlink($backupPath)) {
            log_message('warning', 'File QR pembayaran lama tidak dapat dibersihkan.');
        }
        $legacyPath = $directory . DIRECTORY_SEPARATOR . self::LEGACY_QR_FILENAME;
        if (is_file($legacyPath) && ! unlink($legacyPath)) {
            log_message('warning', 'File QR pembayaran lama tidak dapat dibersihkan.');
        }
    }

    public function deleteProof(string $filename): void
    {
        $path = $this->proofPath($filename);
        if (is_file($path) && ! unlink($path)) {
            throw new PaymentException(lang('Payments.proof_storage_failed'));
        }
    }

    /**
     * @return array{contents: string, mime: string}
     */
    public function readProof(string $filename): array
    {
        return $this->readImage($this->proofPath($filename));
    }

    /**
     * @return array{contents: string, mime: string}
     */
    public function readQr(): array
    {
        $newPath = $this->directory() . DIRECTORY_SEPARATOR . self::QR_FILENAME;
        $path = is_file($newPath)
            ? $newPath
            : $this->directory() . DIRECTORY_SEPARATOR . self::LEGACY_QR_FILENAME;

        return $this->readImage($path);
    }

    /**
     * @return array{contents: string, mime: string}
     */
    private function uploadedImage(UploadedFile $file, int $maxSize): array
    {
        $size = $file->getSize();
        if ($size === false || ! $file->isValid() || $file->hasMoved()) {
            throw new PaymentException(lang('Payments.image_invalid'));
        }
        if ($size > $maxSize) {
            throw new PaymentException(lang('Payments.image_too_large'));
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new PaymentException(lang('Payments.image_type_invalid'));
        }

        $contents = file_get_contents($file->getTempName());
        if ($contents === false) {
            throw new PaymentException(lang('Payments.image_invalid'));
        }

        $metadata = @getimagesizefromstring($contents);
        if (
            $metadata === false
            || ! isset($metadata[0], $metadata[1], $metadata['mime'])
            || ! in_array($metadata['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
            || $metadata['mime'] !== $mime
            || $metadata[0] < 1
            || $metadata[1] < 1
            || $metadata[0] > self::MAX_DIMENSION
            || $metadata[1] > self::MAX_DIMENSION
            || $metadata[0] * $metadata[1] > self::MAX_PIXELS
        ) {
            throw new PaymentException(lang('Payments.image_invalid'));
        }
        if (strlen($contents) > $maxSize) {
            throw new PaymentException(lang('Payments.image_too_large'));
        }

        return ['contents' => $contents, 'mime' => $metadata['mime']];
    }

    private function writeImage(string $contents, string $path): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new PaymentException(lang('Payments.proof_storage_failed'));
        }

        if (file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) {
            if (is_file($path) && ! unlink($path)) {
                log_message('error', 'File gambar pembayaran yang gagal ditulis tidak dapat dibersihkan.');
            }

            throw new PaymentException(lang('Payments.proof_storage_failed'));
        }

        if (! chmod($path, 0600)) {
            if (is_file($path) && ! unlink($path)) {
                log_message('error', 'File gambar pembayaran dengan izin tidak aman tidak dapat dibersihkan.');
            }

            throw new PaymentException(lang('Payments.proof_storage_failed'));
        }
    }

    /**
     * @return array{contents: string, mime: string}
     */
    private function readImage(string $path): array
    {
        if (! is_file($path)) {
            throw new PaymentException(lang('Payments.image_not_found'));
        }

        $contents = file_get_contents($path);
        $metadata = $contents === false ? false : @getimagesizefromstring($contents);
        if (
            $metadata === false
            || ! isset($metadata['mime'])
            || ! in_array($metadata['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
        ) {
            throw new PaymentException(lang('Payments.image_read_failed'));
        }

        return ['contents' => $contents, 'mime' => $metadata['mime']];
    }

    private function proofPath(string $filename): string
    {
        if (preg_match('/\Aproof-[0-9a-f]{40}\.(?:jpg|png|webp)\z/', $filename) !== 1) {
            throw new PaymentException(lang('Payments.image_invalid'));
        }

        return $this->directory() . DIRECTORY_SEPARATOR . $filename;
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new PaymentException(lang('Payments.image_type_invalid')),
        };
    }

    private function directory(): string
    {
        return $this->storageDirectory;
    }
}
