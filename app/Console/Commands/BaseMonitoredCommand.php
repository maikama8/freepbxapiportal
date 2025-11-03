<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CronJobManager;
use Illuminate\Support\Facades\Log;

abstract class BaseMonitoredCommand extends Command
{
    protected CronJobManager $cronJobManager;
    protected string $executionId;
    protected array $metadata = [];

    public function __construct()
    {
        parent::__construct();
        $this->cronJobManager = app(CronJobManager::class);
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->executionId = $this->cronJobManager->startJob(
            $this->getName(),
            $this->getJobMetadata()
        );

        try {
            $result = $this->executeJob();
            
            $this->cronJobManager->completeJob(
                $this->executionId,
                true,
                is_array($result) ? $result : ['result' => $result]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->cronJobManager->failJob($this->executionId, $e);
            
            $this->error("Job failed: " . $e->getMessage());
            Log::error("Cron job failed: " . $this->getName(), [
                'execution_id' => $this->executionId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Execute the actual job logic - to be implemented by child classes
     */
    abstract protected function executeJob(): mixed;

    /**
     * Get job metadata - can be overridden by child classes
     */
    protected function getJobMetadata(): array
    {
        return array_merge([
            'command' => $this->getName(),
            'arguments' => $this->arguments(),
            'options' => $this->options(),
        ], $this->metadata);
    }

    /**
     * Set additional metadata for the job
     */
    protected function setMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
    }

    /**
     * Log progress during job execution
     */
    protected function logProgress(string $message, array $context = []): void
    {
        $context['execution_id'] = $this->executionId ?? 'unknown';
        $context['job_name'] = $this->getName();
        
        Log::channel('cron')->info($message, $context);
        
        if ($this->output->isVerbose()) {
            $this->info($message);
        }
    }

    /**
     * Update job progress in real-time
     */
    protected function updateProgress(array $progressData): void
    {
        if (isset($this->executionId)) {
            $cacheKey = 'cron_job_' . $this->executionId;
            $jobData = cache()->get($cacheKey, []);
            $jobData['progress'] = $progressData;
            cache()->put($cacheKey, $jobData, 3600);
        }
    }
}