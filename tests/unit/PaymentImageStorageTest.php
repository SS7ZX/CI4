<?php

declare(strict_types=1);

use App\Libraries\Payments\PaymentImageStorage;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PaymentImageStorageTest extends CIUnitTestCase
{
    public function testStoresAndReadsJpegWithoutChangingItsMimeType(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cv-payment-image-' . bin2hex(random_bytes(10));
        $storage = new PaymentImageStorage($directory);
        $upload = $this->createJpegUpload();
        $proof = null;

        try {
            $storage->storeQr($upload, 5_242_880);
            $qr = $storage->readQr();
            $this->assertSame('image/jpeg', $qr['mime']);
            $this->assertSame(file_get_contents($upload->getTempName()), $qr['contents']);

            $proof = $storage->storeProof($upload, 5_242_880);
            $this->assertStringEndsWith('.jpg', $proof);
            $this->assertSame('image/jpeg', $storage->readProof($proof)['mime']);
        } finally {
            if (is_string($proof)) {
                $storage->deleteProof($proof);
            }
            if (is_file($directory . DIRECTORY_SEPARATOR . 'merchant-qr.img')) {
                unlink($directory . DIRECTORY_SEPARATOR . 'merchant-qr.img');
            }
            if (is_file($upload->getTempName())) {
                unlink($upload->getTempName());
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    private function createJpegUpload(): UploadedFile
    {
        $contents = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Al//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IT//2Q==',
            true,
        );
        $path = tempnam(sys_get_temp_dir(), 'cv-payment-jpeg-');
        if (
            $contents === false
            || $path === false
            || file_put_contents($path, $contents) !== strlen($contents)
        ) {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }

            throw new RuntimeException('File JPEG untuk test tidak dapat dibuat.');
        }

        return new class($path, 'proof.jpeg', 'image/jpeg', strlen($contents), UPLOAD_ERR_OK) extends UploadedFile {
            public function isValid(): bool
            {
                return $this->getError() === UPLOAD_ERR_OK && is_file($this->getTempName());
            }
        };
    }
}
