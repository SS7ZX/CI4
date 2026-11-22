<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class CvAi extends BaseConfig
{
    public bool $enabled = false;
    public string $ollamaBaseUrl = 'http://127.0.0.1:11434';
    public string $model = 'qwen2.5:3b';
    public int $timeoutSeconds = 90;
    public int $healthTimeoutSeconds = 3;
    public int $maximumInputCharacters = 8000;
    public int $maximumOutputBytes = 20000;

    public function __construct()
    {
        parent::__construct();

        $this->enabled = filter_var(env('CV_AI_ENABLED', $this->enabled), FILTER_VALIDATE_BOOLEAN);
        $this->ollamaBaseUrl = rtrim((string) env('CV_AI_OLLAMA_URL', $this->ollamaBaseUrl), '/');
        $this->model = trim((string) env('CV_AI_MODEL', $this->model));
        $this->timeoutSeconds = (int) env('CV_AI_TIMEOUT_SECONDS', $this->timeoutSeconds);
        $this->healthTimeoutSeconds = (int) env('CV_AI_HEALTH_TIMEOUT_SECONDS', $this->healthTimeoutSeconds);
    }
}
