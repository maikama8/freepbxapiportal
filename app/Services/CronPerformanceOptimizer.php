<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CronPerformanceOptimizer
{
    protected array $performanceMetrics = [];
    protected array $optimizationRules = [];

    public function __construct()
    {
        $this->initializeOptimizationRules();
    }

    /**
     * Analyze cron job performance and suggest optimizations
     */
    public function analyzePerformance(int $days = 7): array
    {
        $this->collectPerformanceMetrics($days);
        
        $analysis = [
            'metrics' => $this->performanceMetrics,
            'recommendations' => $this->generateRecommendations(),
            'resource_usage' => $this->analyzeResourceUsage(),
            'scheduling_conflicts' => $this->detectSchedulingConflicts(),
            'optimization_score' => $this->calculateOptimizationScore(),
        ];

        return $analysis;
    }

    /**
     * Collect performance metrics for analysis
     */
    protected function collectPerformanceMetrics(int $days): void
    {
        $cutoffDate = Carbon::now()->subDays($days);

        // Get job execution statistics
        $jobStats = DB::table('cron_job_executions')
            ->where('started_at', '>', $cutoffDate)
            ->selectRaw('
                job_name,
                COUNT(*) as total_executions,
                AVG(duration_seconds) as avg_duration,
                MAX(duration_seconds) as max_duration,
                MIN(duration_seconds) as min_duration,
                AVG(memory_peak) as avg_memory,
                MAX(memory_peak) as max_memory,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failure_count,
                AVG(CASE WHEN status = "completed" THEN duration_seconds ELSE NULL END) as avg_success_duration
            ')
            ->groupBy('job_name')
            ->get();

        foreach ($jobStats as $stat) {
            // Calculate variance manually for SQLite compatibility
            $durations = DB::table('cron_job_executions')
                ->where('job_name', $stat->job_name)
                ->where('started_at', '>', $cutoffDate)
                ->whereNotNull('duration_seconds')
                ->pluck('duration_seconds');
            
            $variance = $durations->count() > 1 ? 
                sqrt($durations->map(fn($d) => pow($d - $stat->avg_duration, 2))->sum() / $durations->count()) : 0;

            $this->performanceMetrics[$stat->job_name] = [
                'total_executions' => $stat->total_executions,
                'avg_duration' => round($stat->avg_duration ?? 0, 2),
                'max_duration' => $stat->max_duration ?? 0,
                'min_duration' => $stat->min_duration ?? 0,
                'duration_variance' => round($variance, 2),
                'avg_memory_mb' => round(($stat->avg_memory ?? 0) / 1024 / 1024, 2),
                'max_memory_mb' => round(($stat->max_memory ?? 0) / 1024 / 1024, 2),
                'failure_rate' => round(($stat->failure_count / $stat->total_executions) * 100, 2),
                'reliability_score' => $this->calculateReliabilityScore($stat),
                'performance_score' => $this->calculatePerformanceScore($stat),
            ];
        }
    }

    /**
     * Generate optimization recommendations
     */
    protected function generateRecommendations(): array
    {
        $recommendations = [];

        foreach ($this->performanceMetrics as $jobName => $metrics) {
            $jobRecommendations = [];

            // Check for high duration variance
            if ($metrics['duration_variance'] > ($metrics['avg_duration'] * 0.5)) {
                $jobRecommendations[] = [
                    'type' => 'performance',
                    'priority' => 'medium',
                    'issue' => 'High execution time variance',
                    'recommendation' => 'Consider implementing batch size optimization or resource pooling',
                    'impact' => 'Improved consistency and predictability',
                ];
            }

            // Check for high memory usage
            if ($metrics['max_memory_mb'] > 512) {
                $jobRecommendations[] = [
                    'type' => 'resource',
                    'priority' => 'high',
                    'issue' => 'High memory usage',
                    'recommendation' => 'Implement memory-efficient processing or chunking',
                    'impact' => 'Reduced server resource consumption',
                ];
            }

            // Check for high failure rate
            if ($metrics['failure_rate'] > 5) {
                $jobRecommendations[] = [
                    'type' => 'reliability',
                    'priority' => 'high',
                    'issue' => 'High failure rate',
                    'recommendation' => 'Add better error handling and retry logic',
                    'impact' => 'Improved system reliability',
                ];
            }

            // Check for long execution times
            if ($metrics['avg_duration'] > 300) { // 5 minutes
                $jobRecommendations[] = [
                    'type' => 'performance',
                    'priority' => 'medium',
                    'issue' => 'Long execution time',
                    'recommendation' => 'Consider breaking into smaller tasks or optimizing queries',
                    'impact' => 'Faster execution and reduced resource blocking',
                ];
            }

            if (!empty($jobRecommendations)) {
                $recommendations[$jobName] = $jobRecommendations;
            }
        }

        return $recommendations;
    }

    /**
     * Analyze resource usage patterns
     */
    protected function analyzeResourceUsage(): array
    {
        $totalMemoryUsage = array_sum(array_column($this->performanceMetrics, 'avg_memory_mb'));
        $totalExecutionTime = array_sum(array_column($this->performanceMetrics, 'avg_duration'));

        // Get hourly execution patterns (SQLite compatible)
        $hourlyPatterns = DB::table('cron_job_executions')
            ->where('started_at', '>', Carbon::now()->subDays(7))
            ->selectRaw("
                CAST(strftime('%H', started_at) AS INTEGER) as hour,
                COUNT(*) as execution_count,
                AVG(duration_seconds) as avg_duration,
                AVG(memory_peak) as avg_memory
            ")
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return [
            'total_memory_usage_mb' => round($totalMemoryUsage, 2),
            'total_execution_time_seconds' => round($totalExecutionTime, 2),
            'hourly_patterns' => $hourlyPatterns->toArray(),
            'peak_hours' => $this->identifyPeakHours($hourlyPatterns),
            'resource_efficiency' => $this->calculateResourceEfficiency(),
        ];
    }

    /**
     * Detect potential scheduling conflicts
     */
    protected function detectSchedulingConflicts(): array
    {
        $conflicts = [];

        // Check for overlapping executions
        $overlappingJobs = DB::table('cron_job_executions as e1')
            ->join('cron_job_executions as e2', function($join) {
                $join->on('e1.job_name', '!=', 'e2.job_name')
                     ->whereRaw('e1.started_at <= e2.completed_at')
                     ->whereRaw('e1.completed_at >= e2.started_at');
            })
            ->where('e1.started_at', '>', Carbon::now()->subDays(1))
            ->select('e1.job_name as job1', 'e2.job_name as job2', 'e1.started_at', 'e1.completed_at')
            ->get();

        $conflictGroups = $overlappingJobs->groupBy(function($item) {
            return $item->job1 . '|' . $item->job2;
        });

        foreach ($conflictGroups as $key => $group) {
            if ($group->count() > 5) { // Frequent conflicts
                [$job1, $job2] = explode('|', $key);
                $conflicts[] = [
                    'type' => 'scheduling_conflict',
                    'jobs' => [$job1, $job2],
                    'frequency' => $group->count(),
                    'recommendation' => 'Consider staggering execution times or using job queues',
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Calculate overall optimization score
     */
    protected function calculateOptimizationScore(): array
    {
        $scores = [];
        $totalScore = 0;
        $jobCount = count($this->performanceMetrics);

        foreach ($this->performanceMetrics as $jobName => $metrics) {
            $reliabilityScore = $metrics['reliability_score'];
            $performanceScore = $metrics['performance_score'];
            $jobScore = ($reliabilityScore + $performanceScore) / 2;
            
            $scores[$jobName] = round($jobScore, 2);
            $totalScore += $jobScore;
        }

        $overallScore = $jobCount > 0 ? round($totalScore / $jobCount, 2) : 0;

        return [
            'overall_score' => $overallScore,
            'job_scores' => $scores,
            'grade' => $this->getScoreGrade($overallScore),
            'improvement_potential' => max(0, 100 - $overallScore),
        ];
    }

    /**
     * Initialize optimization rules
     */
    protected function initializeOptimizationRules(): void
    {
        $this->optimizationRules = [
            'memory_threshold' => 256, // MB
            'duration_threshold' => 180, // seconds
            'failure_rate_threshold' => 2, // percent
            'variance_threshold' => 0.3, // 30% of average
        ];
    }

    /**
     * Calculate reliability score for a job
     */
    protected function calculateReliabilityScore($stat): float
    {
        $failureRate = ($stat->failure_count / $stat->total_executions) * 100;
        $reliabilityScore = max(0, 100 - ($failureRate * 10)); // Penalize failures heavily
        
        return round($reliabilityScore, 2);
    }

    /**
     * Calculate performance score for a job
     */
    protected function calculatePerformanceScore($stat): float
    {
        $avgDuration = $stat->avg_duration ?? 0;
        $avgMemory = $stat->avg_memory ?? 0;
        
        $durationScore = max(0, 100 - ($avgDuration / 10)); // Penalize long durations
        $memoryScore = max(0, 100 - (($avgMemory / 1024 / 1024) / 5)); // Penalize high memory usage
        $varianceScore = 100; // Default to perfect score if no variance data
        
        $performanceScore = ($durationScore + $memoryScore + $varianceScore) / 3;
        
        return round($performanceScore, 2);
    }

    /**
     * Identify peak usage hours
     */
    protected function identifyPeakHours($hourlyPatterns): array
    {
        $patterns = $hourlyPatterns->toArray();
        
        if (empty($patterns)) {
            return [];
        }

        $avgExecutions = array_sum(array_column($patterns, 'execution_count')) / count($patterns);
        
        $peakHours = array_filter($patterns, function($pattern) use ($avgExecutions) {
            return $pattern->execution_count > ($avgExecutions * 1.5);
        });

        return array_values($peakHours);
    }

    /**
     * Calculate resource efficiency
     */
    protected function calculateResourceEfficiency(): float
    {
        if (empty($this->performanceMetrics)) {
            return 0;
        }

        $totalJobs = count($this->performanceMetrics);
        $efficientJobs = 0;

        foreach ($this->performanceMetrics as $metrics) {
            if ($metrics['avg_memory_mb'] < 128 && 
                $metrics['avg_duration'] < 60 && 
                $metrics['failure_rate'] < 2) {
                $efficientJobs++;
            }
        }

        return round(($efficientJobs / $totalJobs) * 100, 2);
    }

    /**
     * Get grade based on score
     */
    protected function getScoreGrade(float $score): string
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }

    /**
     * Generate optimization report
     */
    public function generateOptimizationReport(int $days = 7): array
    {
        $analysis = $this->analyzePerformance($days);
        
        $report = [
            'generated_at' => Carbon::now()->toISOString(),
            'analysis_period_days' => $days,
            'summary' => [
                'total_jobs_analyzed' => count($this->performanceMetrics),
                'overall_score' => $analysis['optimization_score']['overall_score'],
                'grade' => $analysis['optimization_score']['grade'],
                'total_recommendations' => array_sum(array_map('count', $analysis['recommendations'])),
                'critical_issues' => $this->countCriticalIssues($analysis['recommendations']),
            ],
            'detailed_analysis' => $analysis,
            'action_items' => $this->generateActionItems($analysis),
        ];

        // Log the report
        Log::channel('performance')->info('Cron job optimization report generated', $report['summary']);

        return $report;
    }

    /**
     * Count critical issues in recommendations
     */
    protected function countCriticalIssues(array $recommendations): int
    {
        $criticalCount = 0;
        
        foreach ($recommendations as $jobRecommendations) {
            foreach ($jobRecommendations as $recommendation) {
                if ($recommendation['priority'] === 'high') {
                    $criticalCount++;
                }
            }
        }
        
        return $criticalCount;
    }

    /**
     * Generate actionable items from analysis
     */
    protected function generateActionItems(array $analysis): array
    {
        $actionItems = [];
        
        // High priority items from recommendations
        foreach ($analysis['recommendations'] as $jobName => $recommendations) {
            foreach ($recommendations as $recommendation) {
                if ($recommendation['priority'] === 'high') {
                    $actionItems[] = [
                        'priority' => 'high',
                        'job' => $jobName,
                        'action' => $recommendation['recommendation'],
                        'expected_impact' => $recommendation['impact'],
                        'category' => $recommendation['type'],
                    ];
                }
            }
        }

        // System-wide optimizations
        if ($analysis['optimization_score']['overall_score'] < 70) {
            $actionItems[] = [
                'priority' => 'medium',
                'job' => 'system-wide',
                'action' => 'Review and optimize overall cron job scheduling',
                'expected_impact' => 'Improved system performance and reliability',
                'category' => 'system',
            ];
        }

        // Resource usage optimizations
        if ($analysis['resource_usage']['resource_efficiency'] < 60) {
            $actionItems[] = [
                'priority' => 'medium',
                'job' => 'system-wide',
                'action' => 'Implement resource usage optimization strategies',
                'expected_impact' => 'Reduced server resource consumption',
                'category' => 'resource',
            ];
        }

        return $actionItems;
    }
}