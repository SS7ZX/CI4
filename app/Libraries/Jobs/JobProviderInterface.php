<?php

declare(strict_types=1);

namespace App\Libraries\Jobs;

use App\Libraries\Jobs\Dto\ProviderResult;
use App\Libraries\Jobs\Dto\SearchQueryDto;

interface JobProviderInterface
{
    public function search(SearchQueryDto $query): ProviderResult;

    public function name(): string;

    public function isAvailable(): bool;
}
