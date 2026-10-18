<?php

declare(strict_types=1);

namespace App\Commands;

use App\Repositories\JobSearchRepository;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class JobsCleanup extends BaseCommand
{
    protected $group = 'Jobs';
    protected $name = 'jobs:cleanup';
    protected $description = 'Menghapus lowongan kedaluwarsa dan cache pencarian lama.';
    protected $usage = 'jobs:cleanup';

    public function run(array $params): void
    {
        $config = config('JobMatching');
        $repository = new JobSearchRepository();
        $expired = $repository->deleteExpired();
        $oldCache = $repository->deleteOldCache($config->queryCacheTtlSeconds);

        CLI::write('Lowongan dihapus: ' . $expired['postings'], 'green');
        CLI::write('Cache kedaluwarsa dihapus: ' . $expired['cache'], 'green');
        CLI::write('Cache lama dihapus: ' . $oldCache, 'green');
    }
}
