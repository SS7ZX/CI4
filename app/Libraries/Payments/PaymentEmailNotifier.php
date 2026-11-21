<?php

declare(strict_types=1);

namespace App\Libraries\Payments;

use CodeIgniter\Email\Email;
use Config\CvPayments;
use Throwable;

final class PaymentEmailNotifier implements PaymentNotifierInterface
{
    public function __construct(
        private readonly CvPayments $config,
        private readonly ?Email $emailService = null,
    ) {
    }

    public function notifyAdminsOfProof(string $cvTitle, int $amountIdr): void
    {
        if ($this->config->adminEmails === []) {
            log_message('warning', 'Email bukti pembayaran tidak dikirim karena penerima admin belum dikonfigurasi.');

            return;
        }

        $this->send(
            $this->config->adminEmails,
            lang('Payments.admin_email_subject'),
            lang('Payments.admin_email_message', [
                $cvTitle,
                number_format($amountIdr, 0, ',', '.'),
            ]),
        );
    }

    public function notifyPayerOfReview(
        string $email,
        string $cvTitle,
        int $amountIdr,
        string $status,
        ?string $reviewNote,
    ): void {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            log_message('warning', 'Email hasil pemeriksaan pembayaran tidak dikirim karena alamat pengguna tidak valid.');

            return;
        }

        $approved = $status === 'approved';
        $message = lang('Payments.payer_email_message', [
            lang($approved ? 'Payments.approved_title' : 'Payments.rejected_title'),
            $cvTitle,
            number_format($amountIdr, 0, ',', '.'),
        ]);
        if (is_string($reviewNote) && trim($reviewNote) !== '') {
            $message .= "\n\n" . lang('Payments.payer_email_note', [trim($reviewNote)]);
        }

        $this->send(
            $email,
            lang($approved ? 'Payments.approved_email_subject' : 'Payments.rejected_email_subject'),
            $message,
        );
    }

    /**
     * @param list<string>|string $recipients
     */
    private function send(array|string $recipients, string $subject, string $message): void
    {
        $email = $this->emailService;
        try {
            $email ??= service('email');
            $email->setTo($recipients);
            $email->setSubject($subject);
            $email->setMessage($message);
            if (! $email->send(false)) {
                log_message('error', 'Email notifikasi pembayaran gagal dikirim.');
            }
        } catch (Throwable $exception) {
            log_message('error', 'Email notifikasi pembayaran gagal diproses: {type}', [
                'type' => $exception::class,
            ]);
        } finally {
            if ($email instanceof Email) {
                $email->clear(true);
            }
        }
    }
}
