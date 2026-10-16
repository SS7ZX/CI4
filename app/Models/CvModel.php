<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use JsonException;

abstract class CvModel extends Model
{
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $skipValidation = false;
    protected $fieldRules = [];
    protected $jsonFields = [];
    protected $beforeInsert = ['prepareInsert'];
    protected $beforeUpdate = ['encodeJsonFields'];
    private ?string $pendingInsertId = null;

    protected function initialize(): void
    {
        parent::initialize();

        foreach ($this->fieldRules as $field => $rules) {
            $this->validationRules[$field] = $rules;
            foreach (explode('|', $rules) as $rule) {
                $ruleName = strstr($rule, '[', true) ?: $rule;
                $this->validationMessages[$field][$ruleName] = $ruleName === 'required'
                    ? 'Kolom ' . $field . ' wajib diisi.'
                    : 'Nilai kolom ' . $field . ' tidak valid.';
            }
        }
    }

    /**
     * @param array{data: array<string, mixed>} $eventData
     *
     * @return array{data: array<string, mixed>}
     */
    protected function encodeJsonFields(array $eventData): array
    {
        foreach ($this->jsonFields as $field) {
            if (! array_key_exists($field, $eventData['data'])) {
                continue;
            }

            $value = $eventData['data'][$field];
            if (is_string($value)) {
                try {
                    $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new \InvalidArgumentException('Nilai JSON pada ' . $field . ' tidak valid.', 0, $exception);
                }
            }

            if (! is_array($value)) {
                throw new \InvalidArgumentException('Nilai ' . $field . ' harus berupa array.');
            }

            $eventData['data'][$field] = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $eventData;
    }

    public function insert($row = null, bool $returnID = true)
    {
        $this->pendingInsertId = $this->createUuid();

        $result = parent::insert($row, $returnID);
        if ($result === false) {
            return false;
        }

        return $returnID ? $this->pendingInsertId : $result;
    }

    /**
     * @param array{data: array<string, mixed>} $eventData
     *
     * @return array{data: array<string, mixed>}
     */
    protected function prepareInsert(array $eventData): array
    {
        $eventData['data']['id'] = $this->pendingInsertId ?? $this->createUuid();

        return $this->encodeJsonFields($eventData);
    }

    private function createUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
