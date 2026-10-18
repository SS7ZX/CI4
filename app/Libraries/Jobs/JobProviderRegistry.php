<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

use App\Libraries\Jobs\Providers\JoobleProvider;
use Config\JobMatching;
use RuntimeException;

final class JobProviderRegistry
{
    /** @var array<string, JobProviderInterface> */
    private array $providers = [];

    /**
     * @param list<JobProviderInterface>|null $providers
     */
    public function __construct(?array $providers = null, ?JobMatching $config = null)
    {
        if ($providers !== null) {
            foreach ($providers as $provider) {
                $this->providers[$provider->name()] = $provider;
            }

            return;
        }

        $config ??= config('JobMatching');
        foreach ($config->activeProviders as $providerClass) {
            if ($providerClass === JoobleProvider::class) {
                $provider = new JoobleProvider($config);
            } else {
                if (! class_exists($providerClass)) {
                    throw new RuntimeException('Kelas penyedia lowongan tidak ditemukan.');
                }
                $provider = new $providerClass();
                if (! $provider instanceof JobProviderInterface) {
                    throw new RuntimeException('Kelas penyedia lowongan tidak memenuhi kontrak.');
                }
            }
            $this->providers[$provider->name()] = $provider;
        }
    }

    public function default(): JobProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                return $provider;
            }
        }

        throw new JobProviderException('Tidak ada penyedia lowongan yang tersedia.');
    }
}
