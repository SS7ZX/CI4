<?php

declare(strict_types=1);

namespace App\Libraries\Payments;

use CodeIgniter\HTTP\Files\UploadedFile;

final class PaymentImageStorage
{
    private const MAX_DIMENSION = 6000;
    private const MAX_PIXELS = 20_000_000;

    public function __construct(private readonly string $storageDirectory = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'cv-payments')
    {
    }

    public function hasQr(): bool
    {
        return is_file($this->directory() . DIRECTORY_SEPARATOR . 'merchant-qr.png');
    }

    public function storeProof(UploadedFile $file, int $maxSize): string
    {
        $filename = 'proof-' . bin2hex(random_bytes(20)) . '.png';
        $this->writeImage($file, $maxSize, $this->directory() . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }

    public function storeQr(UploadedFile $file, int $maxSize): void
    {
        $directory = $this->directory();
        $temporaryPath = $directory . DIRECTORY_SEPARATOR . 'qr-' . bin2hex(random_bytes(20)) . '.tmp';
        $targetPath = $directory . DIRECTORY_SEPARATOR . 'merchant-qr.png';
        $backupPath = $directory . DIRECTORY_SEPARATOR . 'qr-backup-' . bin2hex(random_bytes(20)) . '.tmp';

        $this->writeImage($file, $maxSize, $temporaryPath);
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
        return $this->readImage($this->directory() . DIRECTORY_SEPARATOR . 'merchant-qr.png');
    }

    private function writeImage(UploadedFile $file, int $maxSize, string $path): void
    {
        $size = $file->getSize();
        if ($size === false || ! $file->isValid() || $file->hasMoved()) {
            throw new PaymentException(lang('Payments.image_invalid'));
        }
        if ($size > $maxSize) {
            throw new PaymentException(lang('Payments.image_too_large'));
        }

        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
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
            || $metadata[0] < 1
            || $metadata[1] < 1
            || $metadata[0] > self::MAX_DIMENSION
            || $metadata[1] > self::MAX_DIMENSION
            || $metadata[0] * $metadata[1] > self::MAX_PIXELS
        ) {
            throw new PaymentException(lang('Payments.image_invalid'));
        }

        if (! extension_loaded('gd')) {
            throw new PaymentException(lang('Payments.image_processor_unavailable'));
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new PaymentException(lang('Payments.image_invalid'));
        }

        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            imagedestroy($image);

            throw new PaymentException(lang('Payments.proof_storage_failed'));
        }

        if (! imagepng($image, $path, 6)) {
            imagedestroy($image);
            if (is_file($path) && ! unlink($path)) {
                log_message('error', 'File gambar pembayaran yang gagal ditulis tidak dapat dibersihkan.');
            }

            throw new PaymentException(lang('Payments.proof_storage_failed'));
        }
        imagedestroy($image);
        $storedSize = filesize($path);
        if ($storedSize === false) {
            if (is_file($path) && ! unlink($path)) {
                log_message('error', 'File gambar pembayaran yang ukurannya tidak dapat dibaca tidak dapat dibersihkan.');
            }

            throw new PaymentException(lang('Payments.proof_storage_failed'));
        }
        if ($storedSize > $maxSize) {
            if (! unlink($path)) {
                log_message('error', 'File gambar pembayaran yang melampaui batas tidak dapat dibersihkan.');
            }

            throw new PaymentException(lang('Payments.image_too_large'));
        }
        if (! chmod($path, 0600)) {
            if (! unlink($path)) {
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
        if ($contents === false || @getimagesizefromstring($contents) === false) {
            throw new PaymentException(lang('Payments.image_read_failed'));
        }

        return ['contents' => $contents, 'mime' => 'image/png'];
    }

    private function proofPath(string $filename): string
    {
        if (preg_match('/\Aproof-[0-9a-f]{40}\.png\z/', $filename) !== 1) {
            throw new PaymentException(lang('Payments.image_invalid'));
        }

        return $this->directory() . DIRECTORY_SEPARATOR . $filename;
    }

    private function directory(): string
    {
        return $this->storageDirectory;
    }
}
