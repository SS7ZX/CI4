<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Payments\PaymentException;
use App\Libraries\Payments\PaymentImageStorage;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\CvPayments;
use Throwable;

final class CvPaymentService
{
    private CvPayments $config;

    private PaymentImageStorage $storage;

    private CvService $cvs;

    public function __construct(
        ?CvPayments $config = null,
        ?PaymentImageStorage $storage = null,
        ?CvService $cvs = null,
    ) {
        $this->config = $config ?? config('CvPayments');
        $this->storage = $storage ?? new PaymentImageStorage();
        $this->cvs = $cvs ?? new CvService();
    }

    public function priceIdr(): int
    {
        return $this->config->priceIdr;
    }

    public function isAdmin(string $email): bool
    {
        return in_array(strtolower(trim($email)), $this->config->adminEmails, true);
    }

    public function hasQr(): bool
    {
        return $this->storage->hasQr();
    }

    public function hasPaid(int $userId, string $cvId): bool
    {
        return db_connect()->table('cv_download_payments')
            ->where('user_id', $userId)
            ->where('cv_id', $cvId)
            ->where('status', 'approved')
            ->countAllResults() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForOwner(int $userId, string $cvId): ?array
    {
        if ($this->cvs->findOwned($userId, $cvId) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return db_connect()->table('cv_download_payments')
            ->where('user_id', $userId)
            ->where('cv_id', $cvId)
            ->get()
            ->getRowArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function submitProof(int $userId, string $cvId, UploadedFile $file): array
    {
        if ($this->cvs->findOwned($userId, $cvId) === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        if (! $this->storage->hasQr()) {
            throw new PaymentException(lang('Payments.qr_not_configured'));
        }

        $existing = $this->findForOwner($userId, $cvId);
        if (($existing['status'] ?? null) === 'approved') {
            throw new PaymentException(lang('Payments.already_approved'));
        }
        if (($existing['status'] ?? null) === 'pending') {
            throw new PaymentException(lang('Payments.already_pending'));
        }

        $proofPath = $this->storage->storeProof($file, $this->config->maxImageSizeBytes);
        $database = db_connect();
        $database->transBegin();

        try {
            $existing = $database->table('cv_download_payments')
                ->where('user_id', $userId)
                ->where('cv_id', $cvId)
                ->get()
                ->getRowArray();

            if (($existing['status'] ?? null) === 'approved') {
                throw new PaymentException(lang('Payments.already_approved'));
            }
            if (($existing['status'] ?? null) === 'pending') {
                throw new PaymentException(lang('Payments.already_pending'));
            }

            $timestamp = date('Y-m-d H:i:s');
            $payment = [
                'user_id'           => $userId,
                'cv_id'             => $cvId,
                'amount_idr'        => $this->config->priceIdr,
                'status'            => 'pending',
                'proof_path'        => $proofPath,
                'reviewed_by'       => null,
                'review_note'       => null,
                'proof_uploaded_at' => $timestamp,
                'reviewed_at'       => null,
                'updated_at'        => $timestamp,
            ];

            if (is_array($existing)) {
                $database->table('cv_download_payments')
                    ->where('id', $existing['id'])
                    ->where('user_id', $userId)
                    ->where('cv_id', $cvId)
                    ->update($payment);
            } else {
                $database->table('cv_download_payments')->insert([
                    'id'         => $this->uuidV4(),
                    ...$payment,
                    'created_at' => $timestamp,
                ]);
            }

            if (! $database->transStatus()) {
                throw new PaymentException(lang('Payments.proof_submit_failed'));
            }
            $database->transCommit();
        } catch (Throwable $exception) {
            $database->transRollback();
            try {
                $this->storage->deleteProof($proofPath);
            } catch (Throwable $cleanupException) {
                log_message('error', 'Bukti pembayaran gagal dibersihkan setelah transaksi gagal: {message}', [
                    'message' => $cleanupException->getMessage(),
                ]);
            }
            throw $exception;
        }

        if (is_array($existing) && is_string($existing['proof_path'] ?? null)) {
            try {
                $this->storage->deleteProof($existing['proof_path']);
            } catch (Throwable $exception) {
                log_message('warning', 'Bukti pembayaran lama tidak dapat dibersihkan: {message}', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $this->findForOwner($userId, $cvId) ?? throw new PaymentException(lang('Payments.proof_submit_failed'));
    }

    public function storeQr(UploadedFile $file): void
    {
        $this->storage->storeQr($file, $this->config->maxImageSizeBytes);
    }

    /**
     * @return array{contents: string, mime: string}
     */
    public function readQr(): array
    {
        return $this->storage->readQr();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingPayments(): array
    {
        return db_connect()->table('cv_download_payments')
            ->select('cv_download_payments.*, cvs.title AS cv_title')
            ->join('cvs', 'cvs.id = cv_download_payments.cv_id')
            ->where('cv_download_payments.status', 'pending')
            ->orderBy('cv_download_payments.created_at', 'ASC')
            ->limit(50)
            ->get()
            ->getResultArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function findForReviewWithCv(string $paymentId): ?array
    {
        return db_connect()->table('cv_download_payments')
            ->select('cv_download_payments.*, cvs.title AS cv_title')
            ->join('cvs', 'cvs.id = cv_download_payments.cv_id')
            ->where('cv_download_payments.id', $paymentId)
            ->get()
            ->getRowArray();
    }

    /**
     * @return array{contents: string, mime: string}
     */
    public function readProof(string $filename): array
    {
        return $this->storage->readProof($filename);
    }

    public function review(string $paymentId, int $reviewerId, string $status, string $note): bool
    {
        if (! in_array($status, ['approved', 'rejected'], true) || mb_strlen($note) > 500) {
            return false;
        }

        $database = db_connect();
        $database->table('cv_download_payments')
            ->where('id', $paymentId)
            ->where('status', 'pending')
            ->update([
                'status'      => $status,
                'reviewed_by' => $reviewerId,
                'review_note' => $note === '' ? null : $note,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

        return $database->affectedRows() === 1;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
