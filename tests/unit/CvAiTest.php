<?php

declare(strict_types=1);

use App\Libraries\CvAi\CvAiDraft;
use App\Libraries\CvAi\CvAiHttpClientInterface;
use App\Libraries\CvAi\CvAiGenerationException;
use App\Libraries\CvAi\OllamaCvAiDraftGenerator;
use App\Services\CvAiSanitizer;
use CodeIgniter\HTTP\ResponseInterface;
use Config\CvAi;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CvAiTest extends TestCase
{
    public function testSanitizerRemovesProvidedIdentityAndRecognizableContacts(): void
    {
        $story = 'Dina Pratama, dina@example.test, +62 812 3456 7890 tinggal di Jl. Melati No. 5. Mengelola 3 proyek.';

        $sanitized = (new CvAiSanitizer())->sanitize($story, [
            'full_name' => 'Dina Pratama',
            'email'     => 'dina@example.test',
            'phone'     => '+62 812 3456 7890',
            'city'      => 'Bandung',
        ]);

        $this->assertStringNotContainsString('Dina Pratama', $sanitized);
        $this->assertStringNotContainsString('dina@example.test', $sanitized);
        $this->assertStringNotContainsString('+62 812 3456 7890', $sanitized);
        $this->assertStringNotContainsString('Melati', $sanitized);
        $this->assertStringContainsString('Mengelola 3 proyek.', $sanitized);
    }

    public function testGeneratorSendsOnlySanitizedStoryAndMapsStructuredResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'message' => [
                'content' => json_encode([
                    'headline'   => 'Analis Data',
                    'summary'    => 'Mengolah data untuk mendukung keputusan.',
                    'experience' => ['Menyusun laporan bulanan', 'Menganalisis tren penjualan'],
                    'education'  => ['Sistem Informasi'],
                    'skills'     => ['SQL', 'Analisis data'],
                ], JSON_THROW_ON_ERROR),
            ],
        ], JSON_THROW_ON_ERROR));

        $http = $this->createMock(CvAiHttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                'http://127.0.0.1:11434/api/chat',
                $this->callback(static function (array $payload): bool {
                    $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

                    return $payload['model'] === 'qwen2.5:3b'
                        && $payload['stream'] === false
                        && $payload['messages'][1]['content'] === 'Mengelola proyek dan laporan.'
                        && ! str_contains($encoded, 'Dina Pratama')
                        && ! str_contains($encoded, 'dina@example.test')
                        && ! str_contains($encoded, '+62 812');
                }),
                90,
            )
            ->willReturn($response);

        $config = new CvAi();
        $config->enabled = true;
        $generator = new OllamaCvAiDraftGenerator($config, $http);

        $draft = $generator->generate('Mengelola proyek dan laporan.');

        $this->assertInstanceOf(CvAiDraft::class, $draft);
        $this->assertSame('Analis Data', $draft->headline);
        $this->assertSame('Menyusun laporan bulanan', $draft->experience);
        $this->assertSame('', $draft->skills);
    }

    public function testGeneratorRejectsInvalidOrOversizedOutput(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'message' => ['content' => json_encode([
                'headline'   => 'Analis Data',
                'summary'    => 'Ringkasan',
                'experience' => [5],
                'education'  => [],
                'skills'     => [],
            ], JSON_THROW_ON_ERROR)],
        ], JSON_THROW_ON_ERROR));
        $http = $this->createMock(CvAiHttpClientInterface::class);
        $http->method('post')->willReturn($response);
        $config = new CvAi();
        $config->enabled = true;

        try {
            (new OllamaCvAiDraftGenerator($config, $http))->generate('Pengalaman kerja.');
            $this->fail('Respons dengan isi yang tidak sesuai batas tidak boleh diterima.');
        } catch (CvAiGenerationException $exception) {
            $this->assertSame('invalid_response', $exception->reason);
        }
    }

    public function testGeneratorReportsMissingModelClearly(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);
        $http = $this->createMock(CvAiHttpClientInterface::class);
        $http->method('post')->willReturn($response);
        $config = new CvAi();
        $config->enabled = true;

        try {
            (new OllamaCvAiDraftGenerator($config, $http))->generate('Pengalaman kerja.');
            $this->fail('Model yang tidak tersedia harus menghasilkan error.');
        } catch (CvAiGenerationException $exception) {
            $this->assertSame('model_missing', $exception->reason);
        }
    }

    public function testGeneratorDropsUngroundedAndPlaceholderListItems(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'message' => ['content' => json_encode([
                'headline'   => 'Analis Data',
                'summary'    => 'Menyusun laporan dari data operasional.',
                'experience' => ['Inacoo', 'Menyusun laporan bulanan'],
                'education'  => ['Lorem ipsum'],
                'skills'     => ['SQL', 'Inacoo'],
            ], JSON_THROW_ON_ERROR)],
        ], JSON_THROW_ON_ERROR));
        $http = $this->createMock(CvAiHttpClientInterface::class);
        $http->method('post')->willReturn($response);
        $config = new CvAi();
        $config->enabled = true;

        $draft = (new OllamaCvAiDraftGenerator($config, $http))
            ->generate('Menyusun laporan bulanan menggunakan SQL.');

        $this->assertSame('Menyusun laporan bulanan', $draft->experience);
        $this->assertSame('', $draft->education);
        $this->assertSame('SQL', $draft->skills);
    }

    public function testGeneratorRejectsNonLocalRuntimeEndpoint(): void
    {
        $http = $this->createMock(CvAiHttpClientInterface::class);
        $http->expects($this->never())->method('post');
        $config = new CvAi();
        $config->enabled = true;
        $config->ollamaBaseUrl = 'https://api.example.test';

        try {
            (new OllamaCvAiDraftGenerator($config, $http))->generate('Pengalaman kerja.');
            $this->fail('Endpoint AI nonlokal tidak boleh menerima cerita CV.');
        } catch (CvAiGenerationException $exception) {
            $this->assertSame('disabled', $exception->reason);
        }
    }

    public function testStatusReportsReadyModelAndMissingModel(): void
    {
        $readyResponse = $this->createMock(ResponseInterface::class);
        $readyResponse->method('getStatusCode')->willReturn(200);
        $readyResponse->method('getBody')->willReturn(json_encode([
            'models' => [['name' => 'qwen2.5:3b']],
        ], JSON_THROW_ON_ERROR));
        $http = $this->createMock(CvAiHttpClientInterface::class);
        $http->expects($this->once())
            ->method('get')
            ->with('http://127.0.0.1:11434/api/tags', 3)
            ->willReturn($readyResponse);

        $config = new CvAi();
        $config->enabled = true;
        $generator = new OllamaCvAiDraftGenerator($config, $http);

        $this->assertSame([
            'available'       => true,
            'model_installed' => true,
            'reason'          => 'ready',
        ], $generator->status());

        $missingResponse = $this->createMock(ResponseInterface::class);
        $missingResponse->method('getStatusCode')->willReturn(200);
        $missingResponse->method('getBody')->willReturn(json_encode([
            'models' => [['name' => 'llama3.2:3b']],
        ], JSON_THROW_ON_ERROR));
        $missingHttp = $this->createMock(CvAiHttpClientInterface::class);
        $missingHttp->method('get')->willReturn($missingResponse);
        $this->assertSame([
            'available'       => true,
            'model_installed' => false,
            'reason'          => 'model_missing',
        ], (new OllamaCvAiDraftGenerator($config, $missingHttp))->status());
    }
}
