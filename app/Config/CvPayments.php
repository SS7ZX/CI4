<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;
use InvalidArgumentException;

final class CvPayments extends BaseConfig
{
    public int $priceIdr = 15000;

    public int $maxImageSizeBytes = 5_242_880;

    /**
     * @var list<string>
     */
    public array $adminEmails = [];

    public function __construct()
    {
        parent::__construct();

        $configuredPrice = env('CV_DOWNLOAD_PRICE_IDR', null);
        if ($configuredPrice !== null) {
            $price = filter_var($configuredPrice, FILTER_VALIDATE_INT);
            if ($price === false || $price < 1) {
                throw new InvalidArgumentException('CV_DOWNLOAD_PRICE_IDR harus berupa bilangan bulat positif.');
            }
            $this->priceIdr = $price;
        }

        $emails = (string) env('CV_PAYMENT_ADMIN_EMAILS', '');
        $configuredEmails = array_values(array_filter(array_map('trim', explode(',', $emails))));
        foreach ($configuredEmails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('CV_PAYMENT_ADMIN_EMAILS harus berisi alamat email yang valid.');
            }
        }
        $this->adminEmails = array_values(array_unique(array_filter(array_map(
            static fn (string $email): string => strtolower(trim($email)),
            explode(',', $emails),
        ), static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }
}
