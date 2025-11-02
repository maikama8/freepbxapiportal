<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class BackupSystemCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:system 
                            {--database : Backup database only}
                            {--files : Backup files only}
                            {--compress : Compress backup files}
                            {--retention=30 : Number of days to retain backups}';

    /**
     * The console command description.
     */
    protected $description = 'Create system backups for database and files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting system backup...');

        try {
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $backupPath = storage_path("backups/{$timestamp}");
            
            // Create backup directory
            if (!is_dir($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $backupFiles = [];

            // Backup database
            if (!$this->option('files')) {
                $this->info('Backing up database...');
                $dbBackupFile = $this->backupDatabase($backupPath, $timestamp);
                if ($dbBackupFile) {
                    $backupFiles[] = $dbBackupFile;
                    $this->info("Database backup created: {$dbBackupFile}");
                }
            }

            // Backup files
            if (!$this->option('database')) {
                $this->info('Backing up application files...');
                $fileBackupFile = $this->backupFiles($backupPath, $timestamp);
                if ($fileBackupFile) {
                    $backupFiles[] = $fileBackupFile;
                    $this->info("Files backup created: {$fileBackupFile}");
                }
            }

            // Compress backups if requested
            if ($this->option('compress') && !empty($backupFiles)) {
                $this->info('Compressing backup files...');
                $compressedFile = $this->compressBackups($backupPath, $timestamp, $backupFiles);
                if ($compressedFile) {
                    $this->info("Compressed backup created: {$compressedFile}");
                    
                    // Remove individual backup files after compression
                    foreach ($backupFiles as $file) {
                        if (file_exists($file)) {
                            unlink($file);
                        }
                    }
                }
            }

            // Clean up old backups
            $this->cleanupOldBackups((int) $this->option('retention'));

            // Log backup completion
            Log::channel('monitoring')->info('System backup completed', [
                'timestamp' => $timestamp,
                'files_created' => count($backupFiles),
                'compressed' => $this->option('compress'),
            ]);

            $this->info('System backup completed successfully.');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Backup failed: {$e->getMessage()}");
            Log::error('System backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }

    /**
     * Backup database
     */
    protected function backupDatabase(string $backupPath, string $timestamp): ?string
    {
        $config = config('database.connections.' . config('database.default'));
        $driver = $config['driver'];
        $filename = "database_{$timestamp}." . ($driver === 'sqlite' ? 'sqlite' : 'sql');
        $filepath = "{$backupPath}/{$filename}";

        try {
            if ($driver === 'sqlite') {
                // For SQLite, just copy the database file
                $sourcePath = $config['database'];
                if (!file_exists($sourcePath)) {
                    throw new \Exception("SQLite database file not found: {$sourcePath}");
                }
                
                if (!copy($sourcePath, $filepath)) {
                    throw new \Exception("Failed to copy SQLite database file");
                }
                
                return $filepath;
            }

            // For MySQL/MariaDB
            $command = [
                'mysqldump',
                '--host=' . $config['host'],
                '--port=' . $config['port'],
                '--user=' . $config['username'],
                '--password=' . $config['password'],
                '--single-transaction',
                '--routines',
                '--triggers',
                $config['database']
            ];

            $process = new Process($command);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception('mysqldump failed: ' . $process->getErrorOutput());
            }

            file_put_contents($filepath, $process->getOutput());
            
            return $filepath;
        } catch (\Exception $e) {
            $this->error("Database backup failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Backup application files
     */
    protected function backupFiles(string $backupPath, string $timestamp): ?string
    {
        $filename = "files_{$timestamp}.tar";
        $filepath = "{$backupPath}/{$filename}";
        
        $appPath = base_path();
        $excludePatterns = [
            '--exclude=node_modules',
            '--exclude=vendor',
            '--exclude=storage/logs',
            '--exclude=storage/framework/cache',
            '--exclude=storage/framework/sessions',
            '--exclude=storage/framework/views',
            '--exclude=storage/backups',
            '--exclude=.git',
            '--exclude=.env',
        ];

        $command = array_merge(
            ['tar', '-cf', $filepath],
            $excludePatterns,
            ['-C', dirname($appPath), basename($appPath)]
        );

        try {
            $process = new Process($command);
            $process->setTimeout(3600); // 1 hour timeout
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception('tar failed: ' . $process->getErrorOutput());
            }

            return $filepath;
        } catch (\Exception $e) {
            $this->error("Files backup failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Compress backup files
     */
    protected function compressBackups(string $backupPath, string $timestamp, array $backupFiles): ?string
    {
        $filename = "backup_{$timestamp}.tar.gz";
        $filepath = "{$backupPath}/{$filename}";

        $filenames = array_map('basename', $backupFiles);
        
        $command = array_merge(
            ['tar', '-czf', $filepath, '-C', $backupPath],
            $filenames
        );

        try {
            $process = new Process($command);
            $process->setTimeout(1800); // 30 minutes timeout
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception('tar compression failed: ' . $process->getErrorOutput());
            }

            return $filepath;
        } catch (\Exception $e) {
            $this->error("Backup compression failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Clean up old backup files
     */
    protected function cleanupOldBackups(int $retentionDays): void
    {
        $backupsPath = storage_path('backups');
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        if (!is_dir($backupsPath)) {
            return;
        }

        $directories = glob($backupsPath . '/*', GLOB_ONLYDIR);
        $deletedCount = 0;

        foreach ($directories as $directory) {
            $dirName = basename($directory);
            
            // Parse directory name as timestamp (Y-m-d_H-i-s format)
            try {
                $dirDate = Carbon::createFromFormat('Y-m-d_H-i-s', $dirName);
                
                if ($dirDate->lt($cutoffDate)) {
                    $this->deleteDirectory($directory);
                    $deletedCount++;
                    $this->line("Deleted old backup: {$dirName}");
                }
            } catch (\Exception $e) {
                // Skip directories that don't match the expected format
                continue;
            }
        }

        if ($deletedCount > 0) {
            $this->info("Cleaned up {$deletedCount} old backup(s).");
            Log::channel('monitoring')->info('Old backups cleaned up', [
                'deleted_count' => $deletedCount,
                'retention_days' => $retentionDays,
            ]);
        }
    }

    /**
     * Recursively delete a directory
     */
    protected function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($directory);
    }
}