<?php

declare(strict_types=1);

namespace App\Libraries\Payments;

interface PaymentNotifierInterface
{
    public function notifyAdminsOfProof(string $cvTitle, int $amountIdr): void;

    public function notifyPayerOfReview(
        string $email,
        string $cvTitle,
        int $amountIdr,
        string $status,
        ?string $reviewNote,
    ): void;
}
