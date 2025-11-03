<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CronPerformanceOptimizer;
use Illuminate\Support\Facades\File;

class CronPerformanceReportCommand extends Command
{
    protected $signature = 'cron:performance-report 
                           {--days=7 : Number of days to analyze}
                           {--output= : Output file path for the report}
                           {--format=json : Output format (json, text, html)}';

    protected $description = 'Generate a comprehensive performance report for cron jobs';

    protected CronPerformanceOptimizer $optimizer;

    public function __construct(CronPerformanceOptimizer $optimizer)
    {
        parent::__construct();
        $this->optimizer = $optimizer;
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $outputPath = $this->option('output');
        $format = $this->option('format');

        $this->info("Generating cron job performance report for the last {$days} days...");

        try {
            $report = $this->optimizer->generateOptimizationReport($days);
            
            $this->displaySummary($report);
            
            if ($outputPath) {
                $this->saveReport($report, $outputPath, $format);
            }

            $this->displayRecommendations($report);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to generate performance report: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function displaySummary(array $report): void
    {
        $summary = $report['summary'];
        
        $this->line('');
        $this->info('Performance Report Summary');
        $this->line('========================');
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Analysis Period', $report['analysis_period_days'] . ' days'],
                ['Jobs Analyzed', $summary['total_jobs_analyzed']],
                ['Overall Score', $summary['overall_score'] . '/100 (Grade: ' . $summary['grade'] . ')'],
                ['Total Recommendations', $summary['total_recommendations']],
                ['Critical Issues', $summary['critical_issues']],
            ]
        );

        // Display score interpretation
        $scoreColor = match (true) {
            $summary['overall_score'] >= 90 => 'info',
            $summary['overall_score'] >= 70 => 'comment',
            default => 'error',
        };

        $this->$scoreColor("Overall Performance: " . $this->getScoreInterpretation($summary['overall_score']));
    }

    protected function displayRecommendations(array $report): void
    {
        $actionItems = $report['action_items'];
        
        if (empty($actionItems)) {
            $this->info('✓ No critical action items found. Your cron jobs are performing well!');
            return;
        }

        $this->line('');
        $this->info('Action Items');
        $this->line('============');

        $highPriority = array_filter($actionItems, fn($item) => $item['priority'] === 'high');
        $mediumPriority = array_filter($actionItems, fn($item) => $item['priority'] === 'medium');

        if (!empty($highPriority)) {
            $this->error('High Priority Items:');
            foreach ($highPriority as $item) {
                $this->error("  🔴 [{$item['job']}] {$item['action']}");
                $this->line("     Impact: {$item['expected_impact']}");
            }
        }

        if (!empty($mediumPriority)) {
            $this->line('');
            $this->comment('Medium Priority Items:');
            foreach ($mediumPriority as $item) {
                $this->comment("  🟡 [{$item['job']}] {$item['action']}");
                $this->line("     Impact: {$item['expected_impact']}");
            }
        }
    }

    protected function saveReport(array $report, string $outputPath, string $format): void
    {
        try {
            $content = match ($format) {
                'json' => $this->formatAsJson($report),
                'text' => $this->formatAsText($report),
                'html' => $this->formatAsHtml($report),
                default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
            };

            File::put($outputPath, $content);
            $this->info("Report saved to: {$outputPath}");
        } catch (\Exception $e) {
            $this->error("Failed to save report: " . $e->getMessage());
        }
    }

    protected function formatAsJson(array $report): string
    {
        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function formatAsText(array $report): string
    {
        $text = "FreePBX VoIP Platform - Cron Job Performance Report\n";
        $text .= "Generated: " . $report['generated_at'] . "\n";
        $text .= "Analysis Period: " . $report['analysis_period_days'] . " days\n\n";

        // Summary
        $summary = $report['summary'];
        $text .= "SUMMARY\n";
        $text .= "=======\n";
        $text .= "Jobs Analyzed: " . $summary['total_jobs_analyzed'] . "\n";
        $text .= "Overall Score: " . $summary['overall_score'] . "/100 (Grade: " . $summary['grade'] . ")\n";
        $text .= "Total Recommendations: " . $summary['total_recommendations'] . "\n";
        $text .= "Critical Issues: " . $summary['critical_issues'] . "\n\n";

        // Job Performance
        $text .= "JOB PERFORMANCE\n";
        $text .= "===============\n";
        foreach ($report['detailed_analysis']['optimization_score']['job_scores'] as $job => $score) {
            $text .= sprintf("%-40s %6.1f/100\n", $job, $score);
        }
        $text .= "\n";

        // Action Items
        if (!empty($report['action_items'])) {
            $text .= "ACTION ITEMS\n";
            $text .= "============\n";
            foreach ($report['action_items'] as $item) {
                $priority = strtoupper($item['priority']);
                $text .= "[{$priority}] {$item['job']}: {$item['action']}\n";
                $text .= "  Impact: {$item['expected_impact']}\n\n";
            }
        }

        return $text;
    }

    protected function formatAsHtml(array $report): string
    {
        $summary = $report['summary'];
        $scoreClass = $summary['overall_score'] >= 80 ? 'success' : ($summary['overall_score'] >= 60 ? 'warning' : 'danger');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Cron Job Performance Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .metric { background: white; padding: 15px; border: 1px solid #ddd; border-radius: 5px; text-align: center; }
        .metric h3 { margin: 0 0 10px 0; color: #666; }
        .metric .value { font-size: 24px; font-weight: bold; }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .danger { color: #dc3545; }
        .jobs-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .jobs-table th, .jobs-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .jobs-table th { background: #f8f9fa; }
        .action-item { margin-bottom: 15px; padding: 15px; border-left: 4px solid #ddd; background: #f8f9fa; }
        .action-item.high { border-left-color: #dc3545; }
        .action-item.medium { border-left-color: #ffc107; }
        .action-item h4 { margin: 0 0 5px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Cron Job Performance Report</h1>
        <p>Generated: {$report['generated_at']}</p>
        <p>Analysis Period: {$report['analysis_period_days']} days</p>
    </div>

    <div class="summary">
        <div class="metric">
            <h3>Jobs Analyzed</h3>
            <div class="value">{$summary['total_jobs_analyzed']}</div>
        </div>
        <div class="metric">
            <h3>Overall Score</h3>
            <div class="value {$scoreClass}">{$summary['overall_score']}/100</div>
            <div>Grade: {$summary['grade']}</div>
        </div>
        <div class="metric">
            <h3>Recommendations</h3>
            <div class="value">{$summary['total_recommendations']}</div>
        </div>
        <div class="metric">
            <h3>Critical Issues</h3>
            <div class="value danger">{$summary['critical_issues']}</div>
        </div>
    </div>

    <h2>Job Performance Scores</h2>
    <table class="jobs-table">
        <thead>
            <tr>
                <th>Job Name</th>
                <th>Score</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
HTML;

        foreach ($report['detailed_analysis']['optimization_score']['job_scores'] as $job => $score) {
            $grade = $this->getScoreGrade($score);
            $scoreClass = $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger');
            $html .= "<tr><td>{$job}</td><td class=\"{$scoreClass}\">{$score}/100</td><td>{$grade}</td></tr>";
        }

        $html .= <<<HTML
        </tbody>
    </table>

    <h2>Action Items</h2>
HTML;

        if (empty($report['action_items'])) {
            $html .= "<p>No action items found. Your cron jobs are performing well!</p>";
        } else {
            foreach ($report['action_items'] as $item) {
                $html .= <<<HTML
    <div class="action-item {$item['priority']}">
        <h4>[{$item['job']}] {$item['action']}</h4>
        <p><strong>Impact:</strong> {$item['expected_impact']}</p>
        <p><strong>Priority:</strong> {$item['priority']}</p>
    </div>
HTML;
            }
        }

        $html .= "</body></html>";

        return $html;
    }

    protected function getScoreInterpretation(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent - Your cron jobs are performing optimally',
            $score >= 80 => 'Good - Minor optimizations recommended',
            $score >= 70 => 'Fair - Some improvements needed',
            $score >= 60 => 'Poor - Significant optimizations required',
            default => 'Critical - Immediate attention required',
        };
    }

    protected function getScoreGrade(float $score): string
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
}