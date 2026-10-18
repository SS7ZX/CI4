<?php

declare(strict_types=1);

namespace App\Libraries\Photos;

use CodeIgniter\HTTP\Files\UploadedFile;

interface PhotoStorageInterface
{
    public function store(UploadedFile $photo, string $cvId): string;

    public function delete(string $photoPath): void;

    /**
     * @return array{contents: string, mime: string}
     */
    public function read(string $photoPath): array;
}
