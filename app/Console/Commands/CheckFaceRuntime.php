<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Throwable;

class CheckFaceRuntime extends Command
{
    protected $signature = 'identity:check-runtime';

    protected $description = 'Check real face model loading and inference without any personal photos';

    public function handle(): int
    {
        try {
            $result = Process::timeout(45)->env(['OPENCV_LOG_LEVEL' => 'SILENT'])->run([
                config('services.face_match.python'), base_path('scripts/face-match/health_check.py'),
                config('services.face_match.models'),
            ]);
            if ($result->successful()) {
                $this->info('Face runtime healthy: model checksums, detector and recognizer inference passed.');

                return self::SUCCESS;
            }
        } catch (Throwable) {
            // Never expose server paths or environment details in a readiness result.
        }
        $this->error('Face runtime unavailable. Check Python, dependencies, model checksums and process permissions.');

        return self::FAILURE;
    }
}
