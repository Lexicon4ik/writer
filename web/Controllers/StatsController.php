<?php declare(strict_types=1);

namespace NewsBot\Web\Controllers;

use NewsBot\Core\Database;

/**
 * Controller for statistics and reports.
 */
class StatsController extends BaseController
{
    /**
     * Statistics dashboard.
     */
    public function index(?int $id = null): void
    {
        // Get period filter
        $period = $_GET['period'] ?? '7days';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';

        // Calculate date range
        $dates = $this->getDateRange($period, $dateFrom, $dateTo);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        // Articles by status
        $articlesByStatus = $this->getArticlesByStatus($startDate, $endDate);

        // Articles by channel
        $articlesByChannel = $this->getArticlesByChannel($startDate, $endDate);

        // AI usage by day
        $aiUsageByDay = $this->getAiUsageByDay($startDate, $endDate);

        // AI usage by channel
        $aiUsageByChannel = $this->getAiUsageByChannel($startDate, $endDate);

        // AI totals
        $aiTotals = $this->getAiTotals($startDate, $endDate);

        // Pipeline performance
        $pipelineStats = $this->getPipelineStats($startDate, $endDate);

        // Top sources
        $topSources = $this->getTopSources($startDate, $endDate, 10);

        // Top clusters (most duplicates)
        $topClusters = $this->getTopClusters($startDate, $endDate, 10);

        $this->render('stats/index', [
            'pageTitle' => 'Статистика',
            'period' => $period,
            'dateFrom' => $dates['start'],
            'dateTo' => $dates['end'],
            'articlesByStatus' => $articlesByStatus,
            'articlesByChannel' => $articlesByChannel,
            'aiUsageByDay' => $aiUsageByDay,
            'aiUsageByChannel' => $aiUsageByChannel,
            'aiTotals' => $aiTotals,
            'pipelineStats' => $pipelineStats,
            'topSources' => $topSources,
            'topClusters' => $topClusters,
        ]);
    }

    /**
     * Export statistics as CSV.
     */
    public function export(?int $id = null): void
    {
        $report = $_GET['report'] ?? 'articles';
        $period = $_GET['period'] ?? '7days';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';

        $dates = $this->getDateRange($period, $dateFrom, $dateTo);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        switch ($report) {
            case 'articles':
                $this->exportArticles($startDate, $endDate);
                break;
            case 'ai_usage':
                $this->exportAiUsage($startDate, $endDate);
                break;
            case 'publications':
                $this->exportPublications($startDate, $endDate);
                break;
            default:
                $this->setFlash('danger', 'Неизвестный тип отчёта');
                $this->redirect('?page=stats');
        }
    }

    /**
     * Get date range from period or custom dates.
     */
    private function getDateRange(string $period, string $dateFrom, string $dateTo): array
    {
        $today = date('Y-m-d');

        if ($period === 'custom' && !empty($dateFrom) && !empty($dateTo)) {
            return [
                'start' => $dateFrom,
                'end' => $dateTo,
            ];
        }

        switch ($period) {
            case 'today':
                return ['start' => $today, 'end' => $today];
            case '7days':
                return ['start' => date('Y-m-d', strtotime('-6 days')), 'end' => $today];
            case '30days':
                return ['start' => date('Y-m-d', strtotime('-29 days')), 'end' => $today];
            case 'alltime':
                return ['start' => '2000-01-01', 'end' => $today];
            default:
                return ['start' => date('Y-m-d', strtotime('-6 days')), 'end' => $today];
        }
    }

    /**
     * Articles grouped by status.
     */
    private function getArticlesByStatus(string $startDate, string $endDate): array
    {
        return Database::fetchAll("
            SELECT
                status,
                COUNT(*) as count
            FROM articles
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY status
            ORDER BY count DESC
        ", [$startDate, $endDate]);
    }

    /**
     * Articles by channel with stats.
     */
    private function getArticlesByChannel(string $startDate, string $endDate): array
    {
        return Database::fetchAll("
            SELECT
                c.id,
                c.name,
                COUNT(av.id) as total_versions,
                SUM(CASE WHEN av.status = 'published' THEN 1 ELSE 0 END) as published,
                ROUND(AVG(av.validation_score), 2) as avg_validation_score,
                ROUND(SUM(CASE WHEN av.status = 'published' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(av.id), 0), 1) as publish_rate
            FROM channels c
            LEFT JOIN article_versions av ON av.channel_id = c.id
                AND DATE(av.created_at) BETWEEN ? AND ?
            GROUP BY c.id, c.name
            ORDER BY published DESC
        ", [$startDate, $endDate]);
    }

    /**
     * AI usage by day.
     */
    private function getAiUsageByDay(string $startDate, string $endDate): array
    {
        return Database::fetchAll("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as calls,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                ROUND(SUM(estimated_cost), 4) as cost
            FROM ai_usage_log
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ", [$startDate, $endDate]);
    }

    /**
     * AI usage by channel.
     */
    private function getAiUsageByChannel(string $startDate, string $endDate): array
    {
        return Database::fetchAll("
            SELECT
                COALESCE(c.name, 'System') as channel_name,
                COUNT(*) as calls,
                SUM(aul.input_tokens) as input_tokens,
                SUM(aul.output_tokens) as output_tokens,
                ROUND(SUM(aul.estimated_cost), 4) as cost
            FROM ai_usage_log aul
            LEFT JOIN channels c ON c.id = aul.channel_id
            WHERE DATE(aul.created_at) BETWEEN ? AND ?
            GROUP BY c.id, c.name
            ORDER BY cost DESC
        ", [$startDate, $endDate]);
    }

    /**
     * AI totals for period.
     */
    private function getAiTotals(string $startDate, string $endDate): array
    {
        $result = Database::fetchOne("
            SELECT
                COUNT(*) as total_calls,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                ROUND(SUM(estimated_cost), 4) as total_cost
            FROM ai_usage_log
            WHERE DATE(created_at) BETWEEN ? AND ?
        ", [$startDate, $endDate]);

        return $result ?: [
            'total_calls' => 0,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'total_cost' => 0,
        ];
    }

    /**
     * Pipeline performance stats.
     */
    private function getPipelineStats(string $startDate, string $endDate): array
    {
        // Average duration by step (duration_ms converted to seconds)
        $byStep = Database::fetchAll("
            SELECT
                step,
                COUNT(*) as runs,
                ROUND(AVG(duration_ms) / 1000, 2) as avg_duration,
                ROUND(MIN(duration_ms) / 1000, 2) as min_duration,
                ROUND(MAX(duration_ms) / 1000, 2) as max_duration,
                SUM(articles_ok) as total_items,
                SUM(articles_failed) as total_failed
            FROM pipeline_runs
            WHERE DATE(started_at) BETWEEN ? AND ?
              AND finished_at IS NOT NULL
            GROUP BY step
            ORDER BY step
        ", [$startDate, $endDate]);

        // Daily trend
        $trend = Database::fetchAll("
            SELECT
                DATE(started_at) as date,
                step,
                ROUND(AVG(duration_ms) / 1000, 2) as avg_duration
            FROM pipeline_runs
            WHERE DATE(started_at) BETWEEN ? AND ?
              AND finished_at IS NOT NULL
            GROUP BY DATE(started_at), step
            ORDER BY date DESC, step
        ", [$startDate, $endDate]);

        return [
            'byStep' => $byStep,
            'trend' => $trend,
        ];
    }

    /**
     * Top sources by article count.
     */
    private function getTopSources(string $startDate, string $endDate, int $limit): array
    {
        return Database::fetchAll("
            SELECT
                s.id,
                s.name,
                s.site_url,
                COUNT(a.id) as article_count,
                SUM(CASE WHEN a.status = 'published' THEN 1 ELSE 0 END) as published_count
            FROM sources s
            LEFT JOIN articles a ON a.source_id = s.id
                AND DATE(a.created_at) BETWEEN ? AND ?
            GROUP BY s.id, s.name, s.site_url
            ORDER BY article_count DESC
            LIMIT ?
        ", [$startDate, $endDate, $limit]);
    }

    /**
     * Top clusters by member count (most duplicates).
     */
    private function getTopClusters(string $startDate, string $endDate, int $limit): array
    {
        return Database::fetchAll("
            SELECT
                ac.id,
                COUNT(acm.article_id) as member_count,
                COALESCE(pa.scraped_title, pa.rss_title) as primary_title,
                ac.created_at
            FROM article_clusters ac
            JOIN article_cluster_members acm ON acm.cluster_id = ac.id
            LEFT JOIN articles pa ON pa.id = ac.primary_article_id
            WHERE DATE(ac.created_at) BETWEEN ? AND ?
            GROUP BY ac.id, ac.created_at, pa.scraped_title, pa.rss_title
            HAVING member_count > 1
            ORDER BY member_count DESC
            LIMIT ?
        ", [$startDate, $endDate, $limit]);
    }

    /**
     * Export articles to CSV.
     */
    private function exportArticles(string $startDate, string $endDate): void
    {
        $articles = Database::fetchAll("
            SELECT
                a.id,
                COALESCE(a.scraped_title, a.rss_title) as title,
                a.url,
                s.name as source_name,
                a.status,
                a.importance_score,
                a.created_at
            FROM articles a
            LEFT JOIN sources s ON s.id = a.source_id
            WHERE DATE(a.created_at) BETWEEN ? AND ?
            ORDER BY a.created_at DESC
        ", [$startDate, $endDate]);

        $this->sendCsv("articles_{$startDate}_{$endDate}.csv", [
            ['ID', 'Title', 'URL', 'Source', 'Status', 'Importance', 'Created'],
        ], $articles, ['id', 'title', 'url', 'source_name', 'status', 'importance_score', 'created_at']);
    }

    /**
     * Export AI usage to CSV.
     */
    private function exportAiUsage(string $startDate, string $endDate): void
    {
        $usage = Database::fetchAll("
            SELECT
                DATE(created_at) as date,
                operation,
                model,
                COUNT(*) as calls,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                ROUND(SUM(estimated_cost), 4) as cost
            FROM ai_usage_log
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at), operation, model
            ORDER BY date DESC, operation
        ", [$startDate, $endDate]);

        $this->sendCsv("ai_usage_{$startDate}_{$endDate}.csv", [
            ['Date', 'Operation', 'Model', 'Calls', 'Input Tokens', 'Output Tokens', 'Cost USD'],
        ], $usage, ['date', 'operation', 'model', 'calls', 'input_tokens', 'output_tokens', 'cost']);
    }

    /**
     * Export publications to CSV.
     */
    private function exportPublications(string $startDate, string $endDate): void
    {
        $publications = Database::fetchAll("
            SELECT
                av.id,
                av.article_id,
                av.title,
                c.name as channel_name,
                av.validation_score,
                av.telegram_message_id,
                av.published_at
            FROM article_versions av
            LEFT JOIN channels c ON c.id = av.channel_id
            WHERE av.status = 'published'
              AND DATE(av.published_at) BETWEEN ? AND ?
            ORDER BY av.published_at DESC
        ", [$startDate, $endDate]);

        $this->sendCsv("publications_{$startDate}_{$endDate}.csv", [
            ['Version ID', 'Article ID', 'Title', 'Channel', 'Validation Score', 'Message ID', 'Published At'],
        ], $publications, ['id', 'article_id', 'title', 'channel_name', 'validation_score', 'telegram_message_id', 'published_at']);
    }

    /**
     * Send CSV response.
     */
    private function sendCsv(string $filename, array $headers, array $data, array $fields): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM for Excel
        fwrite($output, "\xEF\xBB\xBF");

        // Headers
        foreach ($headers as $row) {
            fputcsv($output, $row, ';');
        }

        // Data
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($fields as $field) {
                $csvRow[] = $row[$field] ?? '';
            }
            fputcsv($output, $csvRow, ';');
        }

        fclose($output);
        exit;
    }
}
