<?php

declare(strict_types=1);

use App\Libraries\CvAi\CvAiDraft;
use App\Libraries\CvAi\CvAiHttpClientInterface;
use App\Libraries\CvAi\CvAiGenerationException;
use App\Libraries\CvAi\CvAiStreamTransportInterface;
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
                    'summary'    => 'Mengelola proyek dan menyusun laporan berkala, mengatur jadwal kerja, memantau progres, serta mendokumentasikan hasil. Selain itu, mengoordinasikan rapat, menyiapkan materi, dan mengarsipkan dokumen yang terkait dengan kegiatan proyek.',
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
                        && $payload['messages'][1]['content'] === 'Mengelola proyek, menyusun laporan berkala, mengatur jadwal kerja, memantau progres, mendokumentasikan hasil, mengoordinasikan rapat, menyiapkan materi, dan mengarsipkan dokumen.'
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

        $draft = $generator->generate('Mengelola proyek, menyusun laporan berkala, mengatur jadwal kerja, memantau progres, mendokumentasikan hasil, mengoordinasikan rapat, menyiapkan materi, dan mengarsipkan dokumen.');

        $this->assertInstanceOf(CvAiDraft::class, $draft);
        $this->assertSame('Analis Data', $draft->headline);
        $this->assertSame('Mengelola proyek dan menyusun laporan berkala, mengatur jadwal kerja, memantau progres, serta mendokumentasikan hasil. Selain itu, mengoordinasikan rapat, menyiapkan materi, dan mengarsipkan dokumen yang terkait dengan kegiatan proyek.', $draft->summary);
        $this->assertSame('Menyusun laporan bulanan', $draft->experience);
        $this->assertSame('', $draft->skills);
    }

    public function testStreamingGeneratorParsesChunkedNdjsonAndValidatesBeforeReturningDraft(): void
    {
        $story = 'Mengelola proyek, menyusun laporan berkala, mengatur jadwal kerja, memantau progres, mendokumentasikan hasil, dan menerapkan manajemen proyek.';
        $draftData = [
            'headline' => 'Koordinator Proyek',
            'summary' => 'Mengelola proyek, menyusun laporan berkala, mengatur jadwal kerja, memantau progres, serta mendokumentasikan hasil. Mengoordinasikan rapat, menyiapkan materi, dan mengarsipkan dokumen untuk mendukung kegiatan proyek.',
            'experience' => ['Mengelola proyek dan menyusun laporan'],
            'education' => [],
            'skills' => ['Manajemen proyek'],
        ];
        $content = json_encode($draftData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $ndjson = '';
        foreach (str_split($content, 18) as $fragment) {
            $ndjson .= json_encode([
                'message' => ['content' => $fragment],
                'done' => false,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        }
        $ndjson .= json_encode([
            'message' => ['content' => ''],
            'done' => true,
        ], JSON_THROW_ON_ERROR) . "\n";

        $transport = $this->createMock(CvAiStreamTransportInterface::class);
        $transport->expects($this->once())
            ->method('post')
            ->with(
                'http://127.0.0.1:11434/api/chat',
                $this->callback(static function (array $payload) use ($story): bool {
                    $prompt = json_decode($payload['messages'][1]['content'], true);

                    return $payload['stream'] === true
                        && $prompt['cerita_pengalaman'] === $story
                        && $prompt['target_pekerjaan'] === 'Koordinator Proyek'
                        && ! str_contains($payload['messages'][1]['content'], 'Dina Pratama')
                        && ! str_contains($payload['messages'][1]['content'], 'dina@example.test');
                }),
                90,
                $this->callback(static fn (callable $onChunk): bool => is_callable($onChunk)),
            )
            ->willReturnCallback(static function (
                string $url,
                array $payload,
                int $timeout,
                callable $onChunk,
            ) use ($ndjson): int {
                foreach (str_split($ndjson, 23) as $chunk) {
                    $onChunk($chunk);
                }

                return 200;
            });
        $config = new CvAi();
        $config->enabled = true;
        $visiblePartial = '';
        $draft = (new OllamaCvAiDraftGenerator($config, $this->createMock(CvAiHttpClientInterface::class), $transport))
            ->generateStream($story, 'Koordinator Proyek', static function (string $text) use (&$visiblePartial): void {
                $visiblePartial .= $text;
            });

        $this->assertSame($content, $visiblePartial);
        $this->assertSame('Koordinator Proyek', $draft->headline);
        $this->assertSame('Mengelola proyek dan menyusun laporan', $draft->experience);
        $this->assertSame('Manajemen proyek', $draft->skills);
    }

    public function testStreamingGeneratorRejectsAValidLookingPartialWithoutOllamaDoneMarker(): void
    {
        $draft = json_encode([
            'headline' => 'Koordinator Proyek',
            'summary' => '',
            'experience' => ['Mengelola proyek'],
            'education' => [],
            'skills' => [],
        ], JSON_THROW_ON_ERROR);
        $transport = $this->createMock(CvAiStreamTransportInterface::class);
        $transport->method('post')->willReturnCallback(
            static function (string $url, array $payload, int $timeout, callable $onChunk) use ($draft): int {
                $onChunk(json_encode([
                    'message' => ['content' => $draft],
                    'done' => false,
                ], JSON_THROW_ON_ERROR) . "\n");

                return 200;
            },
        );
        $config = new CvAi();
        $config->enabled = true;

        try {
            (new OllamaCvAiDraftGenerator($config, $this->createMock(CvAiHttpClientInterface::class), $transport))
                ->generateStream('Mengelola proyek.', 'Koordinator Proyek', static function (): void {
                });
            $this->fail('Respons parsial tidak boleh dianggap sebagai draf selesai.');
        } catch (CvAiGenerationException $exception) {
            $this->assertSame('invalid_response', $exception->reason);
        }
    }

    public function testGeneratorRefinesSummaryFromExistingFactsAndUserDirection(): void
    {
        $expectedSummary = 'Mengelola proyek secara teratur, menyusun laporan berkala, dan mengatur jadwal kerja. Memantau progres, mendokumentasikan hasil, mengoordinasikan rapat, serta mengarsipkan dokumen yang berkaitan dengan kegiatan proyek.';
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'message' => ['content' => json_encode([
                'summary' => $expectedSummary,
            ], JSON_THROW_ON_ERROR)],
        ], JSON_THROW_ON_ERROR));

        $http = $this->createMock(CvAiHttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                'http://127.0.0.1:11434/api/chat',
                $this->callback(static function (array $payload): bool {
                    $context = json_decode($payload['messages'][1]['content'], true);

                    return $payload['stream'] === false
                        && $payload['options']['temperature'] === 0
                        && $context['ringkasan_sekarang'] === ''
                        && $context['fakta_tambahan_dari_pengguna'] === 'Mengoordinasikan rapat dan mengarsipkan dokumen.'
                        && $context['arahan_gaya'] === 'Buat ringkasan lebih lengkap dan profesional.';
                }),
                90,
            )
            ->willReturn($response);

        $config = new CvAi();
        $config->enabled = true;
        $generator = new OllamaCvAiDraftGenerator($config, $http);

        $summary = $generator->refineSummary(
            'Mengelola proyek, menyusun laporan, mengatur jadwal, dan memantau progres.',
            [
                'summary' => '',
                'headline' => 'Koordinator Proyek',
                'experience' => 'Mengelola proyek dan laporan.',
                'education' => 'Administrasi Bisnis',
                'skills' => 'Manajemen proyek, Pelaporan',
                'additional_facts' => 'Mengoordinasikan rapat dan mengarsipkan dokumen.',
            ],
            'Buat ringkasan lebih lengkap dan profesional.',
        );

        $this->assertSame($expectedSummary, $summary);
    }

    public function testGeneratorDoesNotSendOversizedSummaryRefinementContext(): void
    {
        $http = $this->createMock(CvAiHttpClientInterface::class);
        $http->expects($this->never())->method('post');
        $config = new CvAi();
        $config->enabled = true;
        $config->maximumInputCharacters = 100;
        $generator = new OllamaCvAiDraftGenerator($config, $http);

        try {
            $generator->refineSummary(
                'Cerita pengalaman kerja.',
                [
                    'summary' => '',
                    'headline' => 'Koordinator Proyek',
                    'experience' => str_repeat('Mengelola proyek. ', 20),
                    'education' => '',
                    'skills' => '',
                    'additional_facts' => '',
                ],
                'Buat lebih lengkap.',
            );
            $this->fail('Konteks refinement yang terlalu panjang tidak boleh dikirim ke runtime AI.');
        } catch (CvAiGenerationException $exception) {
            $this->assertSame('input_too_long', $exception->reason);
        }
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
                'summary'    => 'Lmaopisang',
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
        $this->assertSame('', $draft->summary);
        $this->assertSame('', $draft->education);
        $this->assertSame('SQL', $draft->skills);
    }

    public function testGeneratorClearsGroundedButTooShortSummary(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'message' => ['content' => json_encode([
                'headline'   => 'Koordinator Proyek',
                'summary'    => 'Mengelola proyek dan laporan berkala.',
                'experience' => ['Mengelola proyek dan laporan berkala'],
                'education'  => [],
                'skills'     => [],
            ], JSON_THROW_ON_ERROR)],
        ], JSON_THROW_ON_ERROR));
        $http = $this->createMock(CvAiHttpClientInterface::class);
        $http->method('post')->willReturn($response);
        $config = new CvAi();
        $config->enabled = true;

        $draft = (new OllamaCvAiDraftGenerator($config, $http))
            ->generate('Mengelola proyek dan laporan berkala.');

        $this->assertSame('', $draft->summary);
        $this->assertSame('Mengelola proyek dan laporan berkala', $draft->experience);
    }

    public function testGeneratorClearsProfessionalLookingSummaryThatIsNotGroundedInStory(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'message' => ['content' => json_encode([
                'headline'   => 'Analis Data',
                'summary'    => 'Mengembangkan strategi pemasaran digital untuk memperluas pasar dan meningkatkan pendapatan secara terukur melalui kampanye, analisis, riset, segmentasi, promosi, konten, dan evaluasi rutin.',
                'experience' => [],
                'education'  => [],
                'skills'     => [],
            ], JSON_THROW_ON_ERROR)],
        ], JSON_THROW_ON_ERROR));
        $http = $this->createMock(CvAiHttpClientInterface::class);
        $http->method('post')->willReturn($response);
        $config = new CvAi();
        $config->enabled = true;

        $draft = (new OllamaCvAiDraftGenerator($config, $http))
            ->generate('Menyusun laporan bulanan menggunakan SQL.');

        $this->assertSame('', $draft->summary);
    }

    public function testGeneratorKeepsDraftWhenSummaryExceedsItsLengthLimit(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            'message' => ['content' => json_encode([
                'headline'   => 'Analis Data',
                'summary'    => str_repeat('Menyusun laporan berkala. ', 50),
                'experience' => ['Menyusun laporan berkala'],
                'education'  => [],
                'skills'     => [],
            ], JSON_THROW_ON_ERROR)],
        ], JSON_THROW_ON_ERROR));
        $http = $this->createMock(CvAiHttpClientInterface::class);
        $http->method('post')->willReturn($response);
        $config = new CvAi();
        $config->enabled = true;

        $draft = (new OllamaCvAiDraftGenerator($config, $http))
            ->generate('Menyusun laporan berkala setiap bulan.');

        $this->assertSame('', $draft->summary);
        $this->assertSame('Menyusun laporan berkala', $draft->experience);
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
