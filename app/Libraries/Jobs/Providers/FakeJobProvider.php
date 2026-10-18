<?php

declare(strict_types=1);

namespace App\Libraries\Jobs\Providers;

use App\Libraries\Jobs\Dto\ProviderResult;
use App\Libraries\Jobs\Dto\SearchQueryDto;
use App\Libraries\Jobs\JobProviderInterface;
use RuntimeException;

final class FakeJobProvider implements JobProviderInterface
{
    /** @var list<SearchQueryDto> */
    public array $queries = [];

    /**
     * @param list<ProviderResult> $results
     */
    public function __construct(
        private readonly array $results = [],
        private readonly ?RuntimeException $failure = null,
        private readonly string $providerName = 'fake',
    ) {
    }

    public function search(SearchQueryDto $query): ProviderResult
    {
        $this->queries[] = $query;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->results === []) {
            return new ProviderResult([], 0, $query->page);
        }

        return $this->results[min(count($this->queries) - 1, count($this->results) - 1)];
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
