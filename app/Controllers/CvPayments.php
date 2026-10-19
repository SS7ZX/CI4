<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Payments\PaymentException;
use App\Services\CvPaymentService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

final class CvPayments extends BaseController
{
    public function checkout(string $cvId): string
    {
        $user = $this->currentUser();
        $payments = new CvPaymentService();
        $payment = $payments->findForOwner($user->id, $cvId);

        return view('payments/checkout', [
            'title' => lang('Payments.title'),
            'cvId' => $cvId,
            'payment' => $payment,
            'priceIdr' => $payment['amount_idr'] ?? $payments->priceIdr(),
            'qrConfigured' => $payments->hasQr(),
            'qrUrl' => url_to('cv-payment-qr'),
            'uploadUrl' => url_to('cv-payment-submit', $cvId),
            'downloadUrl' => url_to('cv-download', $cvId),
        ]);
    }

    public function submit(string $cvId): ResponseInterface
    {
        $user = $this->currentUser();
        $proof = $this->request->getFile('proof');
        if ($proof === null) {
            return redirect()->to(url_to('cv-payment', $cvId))->with('error', lang('Payments.image_invalid'));
        }
        if (! service('throttler')->check('cv-payment-proof-' . $user->id, 5, 3600)) {
            return redirect()->to(url_to('cv-payment', $cvId))->with('error', lang('Payments.rate_limited'));
        }

        try {
            (new CvPaymentService())->submitProof($user->id, $cvId, $proof);
        } catch (PaymentException $exception) {
            return redirect()->to(url_to('cv-payment', $cvId))->with('error', $exception->getMessage());
        }

        return redirect()->to(url_to('cv-payment', $cvId))->with('success', lang('Payments.proof_submitted'));
    }

    public function qr(): ResponseInterface
    {
        $this->currentUser();
        $image = (new CvPaymentService())->readQr();

        return $this->response
            ->setHeader('Content-Type', $image['mime'])
            ->setHeader('Content-Disposition', 'inline; filename="payment-qr.png"')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($image['contents']);
    }

    public function admin(): string
    {
        $user = $this->currentUser();
        $payments = new CvPaymentService();
        $this->requireAdmin($payments, $user);

        return view('payments/admin', [
            'title' => lang('Payments.admin_title'),
            'qrConfigured' => $payments->hasQr(),
            'qrUrl' => url_to('cv-payment-qr'),
            'qrUploadUrl' => url_to('cv-payment-qr-upload'),
            'pendingPayments' => $payments->pendingPayments(),
            'proofUrl' => static fn (string $paymentId): string => url_to('cv-payment-proof', $paymentId),
            'reviewUrl' => static fn (string $paymentId): string => url_to('cv-payment-review', $paymentId),
        ]);
    }

    public function uploadQr(): ResponseInterface
    {
        $user = $this->currentUser();
        $payments = new CvPaymentService();
        $this->requireAdmin($payments, $user);
        $image = $this->request->getFile('qr_image');
        if ($image === null) {
            return redirect()->to(url_to('cv-payment-admin'))->with('error', lang('Payments.image_invalid'));
        }

        try {
            $payments->storeQr($image);
        } catch (PaymentException $exception) {
            return redirect()->to(url_to('cv-payment-admin'))->with('error', $exception->getMessage());
        }

        return redirect()->to(url_to('cv-payment-admin'))->with('success', lang('Payments.qr_saved'));
    }

    public function proof(string $paymentId): ResponseInterface
    {
        $user = $this->currentUser();
        $payments = new CvPaymentService();
        $this->requireAdmin($payments, $user);
        $payment = $payments->findForReviewWithCv($paymentId);
        if (! is_array($payment)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $image = $payments->readProof((string) $payment['proof_path']);

        return $this->response
            ->setHeader('Content-Type', $image['mime'])
            ->setHeader('Content-Disposition', 'inline; filename="payment-proof.png"')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($image['contents']);
    }

    public function review(string $paymentId): ResponseInterface
    {
        $user = $this->currentUser();
        $payments = new CvPaymentService();
        $this->requireAdmin($payments, $user);
        $status = (string) $this->request->getPost('status');
        $note = trim((string) $this->request->getPost('review_note'));
        if (! $payments->review($paymentId, $user->id, $status, $note)) {
            return redirect()->to(url_to('cv-payment-admin'))->with('error', lang('Payments.review_failed'));
        }

        return redirect()->to(url_to('cv-payment-admin'))->with('success', lang('Payments.review_saved'));
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $user;
    }

    private function requireAdmin(CvPaymentService $payments, User $user): void
    {
        if (! $payments->isAdmin((string) $user->email)) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
