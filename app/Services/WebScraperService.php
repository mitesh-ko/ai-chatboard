<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ScrapeResultDTO;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class WebScraperService
{
    /**
     * Scrape a website using the Python scraper script.
     *
     * @throws RuntimeException
     */
    public function scrape(string $url): ScrapeResultDTO
    {
        $pythonBinary = (string) config('services.chatboard.python_path', 'python3');
        $scriptPath = base_path('python/scraper.py');

        if (! file_exists($scriptPath)) {
            throw new RuntimeException("Scraper script not found at {$scriptPath}");
        }

        $process = new Process([$pythonBinary, $scriptPath, $url]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput() ?: $process->getOutput();
            Log::error('Web scraper process failed', [
                'url' => $url,
                'exit_code' => $process->getExitCode(),
                'error' => $errorOutput,
            ]);

            throw new RuntimeException("Scraper execution failed: {$errorOutput}");
        }

        $output = trim($process->getOutput());
        /** @var array<string, mixed>|null $data */
        $data = json_decode($output, true);

        if (! is_array($data)) {
            Log::error('Web scraper returned non-JSON output', [
                'url' => $url,
                'output' => $output,
            ]);

            throw new RuntimeException('Scraper output could not be parsed as valid JSON');
        }

        if (($data['status'] ?? null) === 'error') {
            $msg = (string) ($data['message'] ?? 'Unknown scraping error');
            throw new RuntimeException("Scraping error: {$msg}");
        }

        return ScrapeResultDTO::fromArray($data);
    }
}
