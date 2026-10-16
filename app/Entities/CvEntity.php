<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;
use JsonException;
use UnexpectedValueException;

abstract class CvEntity extends Entity
{
    /**
     * @var list<string>
     */
    protected array $jsonArrayFields = [];

    protected $dates = ['created_at', 'updated_at'];

    public function __set(string $key, $value = null)
    {
        $dbColumn = $this->mapProperty($key);

        if (in_array($dbColumn, $this->jsonArrayFields, true)) {
            $this->attributes[$dbColumn] = $this->decodeArray($value, $dbColumn);

            return;
        }

        parent::__set($key, $value);
    }

    public function __get(string $key)
    {
        $dbColumn = $this->mapProperty($key);

        if (in_array($dbColumn, $this->jsonArrayFields, true)) {
            return $this->decodeArray($this->attributes[$dbColumn] ?? [], $dbColumn);
        }

        return parent::__get($key);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodeArray(mixed $value, string $field): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new UnexpectedValueException('Nilai JSON pada ' . $field . ' tidak valid.', 0, $exception);
            }
        }

        if (! is_array($value)) {
            throw new UnexpectedValueException('Nilai ' . $field . ' harus berupa array.');
        }

        return $value;
    }
}
