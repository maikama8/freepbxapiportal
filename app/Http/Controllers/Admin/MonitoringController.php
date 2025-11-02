<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    protected MonitoringService $monitoring;

    public function __construct(MonitoringService $monitoring)
    {
        $this->monitoring = $monitoring;
        $this->middleware(['auth', 'role:admin']);
    }

    /**
     * Display the monitoring dashboard
     */
    public function index(): View
    {
        $health = $this->monitoring->getSystemHealth();
        $performance = $this->monitoring->getPerformanceMetrics();

        return view('admin.monitoring.index', compact('health', 'performance'));
    }

    /**
     * Get system health data via API
     */
    public function health(): JsonResponse
    {
        try {
            $health = $this->monitoring->getSystemHealth();
            
            return response()->json([
                'success' => true,
                'data' => $health,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve system health data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get performance metrics via API
     */
    public function performance(): JsonResponse
    {
        try {
            $performance = $this->monitoring->getPerformanceMetrics();
            
            return response()->json([
                'success' => true,
                'data' => $performance,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve performance metrics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system logs
     */
    public function logs(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => 'sometimes|string|in:laravel,audit,payment,calls,monitoring,alerts,security,performance',
            'level' => 'sometimes|string|in:debug,info,notice,warning,error,critical,alert,emergency',
            'lines' => 'sometimes|integer|min:1|max:1000',
            'date' => 'sometimes|date_format:Y-m-d',
        ]);

        try {
            $channel = $request->get('channel', 'laravel');
            $lines = $request->get('lines', 100);
            $date = $request->get('date', now()->format('Y-m-d'));
            
            $logFile = $this->getLogFilePath($channel, $date);
            
            if (!file_exists($logFile)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Log file not found',
                ], 404);
            }

            $logs = $this->readLogFile($logFile, $lines);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'channel' => $channel,
                    'date' => $date,
                    'lines' => count($logs),
                    'logs' => $logs,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve logs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get log file path for a channel and date
     */
    protected function getLogFilePath(string $channel, string $date): string
    {
        $logsPath = storage_path('logs');
        
        switch ($channel) {
            case 'laravel':
                return "{$logsPath}/laravel-{$date}.log";
            case 'audit':
                return "{$logsPath}/audit-{$date}.log";
            case 'payment':
                return "{$logsPath}/payment-{$date}.log";
            case 'calls':
                return "{$logsPath}/calls-{$date}.log";
            case 'monitoring':
                return "{$logsPath}/monitoring-{$date}.log";
            case 'alerts':
                return "{$logsPath}/alerts-{$date}.log";
            case 'security':
                return "{$logsPath}/security-{$date}.log";
            case 'performance':
                return "{$logsPath}/performance-{$date}.log";
            default:
                return "{$logsPath}/laravel-{$date}.log";
        }
    }

    /**
     * Read log file and return last N lines
     */
    protected function readLogFile(string $filePath, int $lines): array
    {
        $command = "tail -n {$lines} " . escapeshellarg($filePath);
        $output = shell_exec($command);
        
        if ($output === null) {
            return [];
        }
        
        $logLines = explode("\n", trim($output));
        $parsedLogs = [];
        
        foreach ($logLines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            $parsedLog = $this->parseLogLine($line);
            if ($parsedLog) {
                $parsedLogs[] = $parsedLog;
            }
        }
        
        return array_reverse($parsedLogs); // Most recent first
    }

    /**
     * Parse a log line into structured data
     */
    protected function parseLogLine(string $line): ?array
    {
        // Laravel log format: [2023-11-02 10:30:45] local.INFO: Message {"context":"data"}
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)$/';
        
        if (preg_match($pattern, $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'environment' => $matches[2],
                'level' => $matches[3],
                'message' => $matches[4],
                'raw' => $line,
            ];
        }
        
        // If pattern doesn't match, return raw line
        return [
            'timestamp' => null,
            'environment' => null,
            'level' => 'unknown',
            'message' => $line,
            'raw' => $line,
        ];
    }

    /**
     * Clear logs for a specific channel
     */
    public function clearLogs(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => 'required|string|in:laravel,audit,payment,calls,monitoring,alerts,security,performance',
            'confirm' => 'required|boolean|accepted',
        ]);

        try {
            $channel = $request->get('channel');
            $logsPath = storage_path('logs');
            
            // Get all log files for the channel
            $pattern = "{$logsPath}/{$channel}-*.log";
            $files = glob($pattern);
            
            $deletedCount = 0;
            foreach ($files as $file) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Cleared {$deletedCount} log file(s) for channel: {$channel}",
                'deleted_count' => $deletedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to clear logs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}