<?php

declare(strict_types=1);

use App\Libraries\Payments\PaymentImageStorage;
use App\Services\CvBuilderService;
use App\Services\CvPaymentService;
use App\Services\CvService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\CvPayments;

/**
 * @internal
 */
final class CvPaymentTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = ['CodeIgniter\\Settings', 'CodeIgniter\\Shield', 'App'];

    public function testUnpaidCvDownloadOpensPaymentPageAndShowsConfiguredPrice(): void
    {
        $userId = $this->createUser('payment-user@example.test', 'payment-user');
        $cvId = (new CvService())->create($userId, 'CV untuk pembayaran');
        auth()->loginById($userId);
        $this->withSession($_SESSION);

        $download = $this->get('/dashboard/cv/' . $cvId . '/download');
        $download->assertRedirect();
        $this->assertStringEndsWith('/dashboard/cv/' . $cvId . '/payment', $download->response()->getHeaderLine('Location'));

        $this->withSession($_SESSION);
        $payment = $this->get('/dashboard/cv/' . $cvId . '/payment');
        $payment->assertOK();
        $payment->assertSee('Rp 15.000');
        $payment->assertSee('QR pembayaran belum tersedia');
        $this->assertFalse((new CvPaymentService())->hasPaid($userId, $cvId));

        $otherId = $this->createUser('payment-other@example.test', 'payment-other');
        auth()->logout();
        auth()->loginById($otherId);
        $this->withSession($_SESSION);
        try {
            $this->get('/dashboard/cv/' . $cvId . '/payment');
            $this->fail('Pengguna lain tidak boleh membuka halaman pembayaran CV.');
        } catch (PageNotFoundException $exception) {
            $this->assertSame(404, $exception->getCode());
        }
        try {
            $this->get('/dashboard/payments/admin');
            $this->fail('Pengguna yang tidak diizinkan tidak boleh membuka panel admin.');
        } catch (PageNotFoundException $exception) {
            $this->assertSame(404, $exception->getCode());
        }

        auth()->logout();
    }

    public function testPrivateQrProofRequiresAdminApprovalBeforeDownload(): void
    {
        $userId = $this->createUser('payment-owner@example.test', 'payment-owner');
        $adminId = $this->createUser('payment-admin@example.test', 'payment-admin');
        $cvId = (new CvService())->create($userId, 'CV berbayar');
        (new CvBuilderService())->saveStep($userId, $cvId, 1, [
            'title' => 'CV berbayar',
            'profile' => ['full_name' => 'Nama Pembayaran'],
            'links' => [],
        ]);
        $storagePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cv-payment-' . bin2hex(random_bytes(10));
        $storage = new PaymentImageStorage($storagePath);
        $config = new CvPayments();
        $config->adminEmails = ['payment-admin@example.test'];
        $service = new CvPaymentService($config, $storage);
        $qrUpload = $this->createPngUpload('merchant-qr.png');
        $proofUpload = $this->createPngUpload('payment-proof.png');
        $proofPath = null;

        try {
            $service->storeQr($qrUpload);
            $payment = $service->submitProof($userId, $cvId, $proofUpload);
            $proofPath = (string) $payment['proof_path'];
            $this->assertSame('pending', $payment['status']);
            $this->assertSame(15000, (int) $payment['amount_idr']);
            $this->assertFalse($service->hasPaid($userId, $cvId));
            $this->assertTrue($service->isAdmin('PAYMENT-ADMIN@example.test'));
            $this->assertSame('CV berbayar', $service->findForReviewWithCv((string) $payment['id'])['cv_title']);

            auth()->loginById($userId);
            $this->withSession($_SESSION);
            $blocked = $this->get('/dashboard/cv/' . $cvId . '/download');
            $blocked->assertRedirect();

            $this->assertTrue($service->review((string) $payment['id'], $adminId, 'approved', ''));
            $this->assertTrue($service->hasPaid($userId, $cvId));
            $this->assertFalse($service->review((string) $payment['id'], $adminId, 'approved', ''));

            $this->withSession($_SESSION);
            $checkout = $this->get('/dashboard/cv/' . $cvId . '/payment');
            $checkout->assertOK();
            $checkout->assertSee('Pembayaran disetujui');
            $this->withSession($_SESSION);
            $download = $this->get('/dashboard/cv/' . $cvId . '/download');
            $download->assertOK();
            $this->assertStringStartsWith('%PDF-', $download->response()->getBody());
            $this->assertStringContainsString('image/png', $service->readQr()['mime']);

            auth()->logout();
        } finally {
            if (is_string($proofPath)) {
                $storage->deleteProof($proofPath);
            }
            foreach ([$qrUpload->getTempName(), $proofUpload->getTempName()] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            $qrPath = $storagePath . DIRECTORY_SEPARATOR . 'merchant-qr.png';
            if (is_file($qrPath)) {
                unlink($qrPath);
            }
            if (is_dir($storagePath)) {
                rmdir($storagePath);
            }
        }
    }

    private function createPngUpload(string $filename): UploadedFile
    {
        $image = imagecreatetruecolor(48, 48);
        $background = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $background);
        $path = tempnam(sys_get_temp_dir(), 'cv-payment-image-');
        if ($path === false || ! imagepng($image, $path)) {
            imagedestroy($image);

            throw new RuntimeException('File test untuk pembayaran tidak dapat dibuat.');
        }
        imagedestroy($image);

        return new class($path, $filename, 'image/png', filesize($path), UPLOAD_ERR_OK) extends UploadedFile {
            public function isValid(): bool
            {
                return $this->getError() === UPLOAD_ERR_OK && is_file($this->getTempName());
            }
        };
    }

    private function createUser(string $email, string $username): int
    {
        $users = model(UserModel::class);
        $user = $users->createNewUser([
            'username' => $username,
            'email' => $email,
            'password' => 'AmanSekali!123',
        ]);
        $users->save($user);

        return $users->getInsertID();
    }
}
