<?php

declare(strict_types=1);

use App\Libraries\Payments\PaymentEmailNotifier;
use CodeIgniter\Email\Email;
use CodeIgniter\Test\CIUnitTestCase;
use Config\CvPayments;

/**
 * @internal
 */
final class PaymentEmailNotifierTest extends CIUnitTestCase
{
    public function testProofNotificationGoesToConfiguredAdmins(): void
    {
        $config = new CvPayments();
        $config->adminEmails = ['admin@example.test', 'owner@example.test'];
        $email = $this->createMock(Email::class);
        $email->expects($this->once())
            ->method('setTo')
            ->with($config->adminEmails)
            ->willReturnSelf();
        $email->expects($this->once())
            ->method('setSubject')
            ->with('Bukti pembayaran baru perlu diperiksa')
            ->willReturnSelf();
        $email->expects($this->once())
            ->method('setMessage')
            ->with($this->stringContains('CV "CV untuk email"'))
            ->willReturnSelf();
        $email->expects($this->once())->method('send')->with(false)->willReturn(true);
        $email->expects($this->once())->method('clear')->with(true);

        (new PaymentEmailNotifier($config, $email))->notifyAdminsOfProof('CV untuk email', 15000);
    }

    public function testReviewNotificationTellsPayerTheDecisionAndAdminNote(): void
    {
        $email = $this->createMock(Email::class);
        $email->expects($this->once())
            ->method('setTo')
            ->with('payer@example.test')
            ->willReturnSelf();
        $email->expects($this->once())
            ->method('setSubject')
            ->with('Pembayaran CV perlu diperbaiki')
            ->willReturnSelf();
        $email->expects($this->once())
            ->method('setMessage')
            ->with($this->logicalAnd(
                $this->stringContains('Pembayaran perlu diperbaiki'),
                $this->stringContains('Catatan admin: Bukti kurang jelas.'),
            ))
            ->willReturnSelf();
        $email->expects($this->once())->method('send')->with(false)->willReturn(true);
        $email->expects($this->once())->method('clear')->with(true);

        (new PaymentEmailNotifier(new CvPayments(), $email))->notifyPayerOfReview(
            'payer@example.test',
            'CV untuk email',
            15000,
            'rejected',
            'Bukti kurang jelas.',
        );
    }

    public function testEmailTransportFailureDoesNotEscapeThePaymentFlow(): void
    {
        $email = $this->createMock(Email::class);
        $email->method('setTo')->willReturnSelf();
        $email->method('setSubject')->willReturnSelf();
        $email->method('setMessage')->willReturnSelf();
        $email->expects($this->once())->method('send')->with(false)->willReturn(false);
        $email->expects($this->once())->method('clear')->with(true);
        $config = new CvPayments();
        $config->adminEmails = ['admin@example.test'];

        (new PaymentEmailNotifier($config, $email))->notifyAdminsOfProof('CV untuk email', 15000);
    }
}
