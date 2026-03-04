<?php declare(strict_types=1);

namespace NewsBot\Web\Controllers;

use NewsBot\Core\Database;
use NewsBot\Core\Settings;

/**
 * Dashboard controller showing system overview.
 */
class DashboardController extends BaseController
{
    /**
     * Show dashboard with statistics.
     */
    public function index(?int $id = null): void
    {
        $stats = $this->getStats();
        $todayArticles = $this->getTodayArticlesByStatus();
        $recentPublished = $this->getRecentPublished();
        $manualReviewCount = $this->getManualReviewCount();
        $aiUsage = $this->getAiUsage();
        $pipelineRuns = $this->getLastPipelineRuns();
        $recentParserRuns = $this->getRecentParserRuns();

        $this->render('dashboard', [
            'pageTitle' => 'Dashboard',
            'stats' => $stats,
            'todayArticles' => $todayArticles,
            'recentPublished' => $recentPublished,
            'manualReviewCount' => $manualReviewCount,
            'aiUsage' => $aiUsage,
            'pipelineRuns' => $pipelineRuns,
            'recentParserRuns' => $recentParserRuns,
        ]);
    }

    /**
     * Get counts of active entities.
     */
    private function getStats(): array
    {
        try {
            return [
                'bots' => (int)(Database::fetchOne(
                    "SELECT COUNT(*) as cnt FROM bots WHERE status = 'active'"
                )['cnt'] ?? 0),

                'channels' => (int)(Database::fetchOne(
                    "SELECT COUNT(*) as cnt FROM channels WHERE status = 'active'"
                )['cnt'] ?? 0),

                'sources' => (int)(Database::fetchOne(
                    "SELECT COUNT(*) as cnt FROM sources WHERE status = 'active'"
                )['cnt'] ?? 0),

                'feeds' => (int)(Database::fetchOne(
                    "SELECT COUNT(*) as cnt FROM feeds WHERE status = 'active'"
                )['cnt'] ?? 0),

                'parsers' => (int)(Database::fetchOne(
                    "SELECT COUNT(*) as cnt FROM source_parsers WHERE is_active = 1"
                )['cnt'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['bots' => 0, 'channels' => 0, 'sources' => 0, 'feeds' => 0, 'parsers' => 0];
        }
    }

    /**
     * Get article counts for today by status.
     */
    private function getTodayArticlesByStatus(): array
    {
        try {
            $rows = Database::fetchAll(
                "SELECT status, COUNT(*) as cnt
                 FROM articles
                 WHERE DATE(created_at) = CURDATE()
                 GROUP BY status
                 ORDER BY cnt DESC"
            );

            $result = [];
            foreach ($rows as $row) {
                $result[$row['status']] = (int)$row['cnt'];
            }

            // Get total
            $result['total'] = array_sum($result);

            return $result;
        } catch (\Throwable $e) {
            return ['total' => 0];
        }
    }

    /**
     * Get last 10 published articles (from article_versions).
     */
    private function getRecentPublished(): array
    {
        try {
            return Database::fetchAll(
                "SELECT av.id, a.id as article_id, av.title as processed_title, av.status, av.published_at,
                        a.url, a.rss_title,
                        c.name as channel_name,
                        s.name as source_name
                 FROM article_versions av
                 JOIN articles a ON a.id = av.article_id
                 JOIN channels c ON c.id = av.channel_id
                 JOIN sources s ON s.id = a.source_id
                 WHERE av.status = 'published'
                 ORDER BY av.published_at DESC
                 LIMIT 10"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get count of articles in manual_review.
     */
    private function getManualReviewCount(): int
    {
        try {
            $result = Database::fetchOne(
                "SELECT COUNT(*) as cnt FROM article_versions WHERE status = 'manual_review'"
            );
            return (int)($result['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get AI usage for today vs budget.
     */
    private function getAiUsage(): array
    {
        try {
            $result = Database::fetchOne(
                "SELECT COALESCE(SUM(estimated_cost), 0) as cost,
                        COUNT(*) as calls,
                        COALESCE(SUM(input_tokens), 0) as input_tokens,
                        COALESCE(SUM(output_tokens), 0) as output_tokens
                 FROM ai_usage_log
                 WHERE DATE(created_at) = CURDATE()"
            );

            $budget = Settings::getFloat('ai_daily_budget', 10.0);
            $cost = (float)($result['cost'] ?? 0);

            return [
                'cost' => $cost,
                'budget' => $budget,
                'percent' => $budget > 0 ? round(($cost / $budget) * 100, 1) : 0,
                'calls' => (int)($result['calls'] ?? 0),
                'input_tokens' => (int)($result['input_tokens'] ?? 0),
                'output_tokens' => (int)($result['output_tokens'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return [
                'cost' => 0,
                'budget' => 10.0,
                'percent' => 0,
                'calls' => 0,
                'input_tokens' => 0,
                'output_tokens' => 0,
            ];
        }
    }

    /**
     * Get last pipeline run times for each step.
     */
    private function getLastPipelineRuns(): array
    {
        try {
            $rows = Database::fetchAll(
                "SELECT step, MAX(finished_at) as last_run,
                        SUM(articles_total) as processed,
                        SUM(articles_ok) as success,
                        SUM(articles_failed) as errors
                 FROM pipeline_runs
                 WHERE DATE(started_at) = CURDATE()
                 GROUP BY step"
            );

            $result = [];
            foreach ($rows as $row) {
                $result[$row['step']] = [
                    'last_run' => $row['last_run'],
                    'processed' => (int)$row['processed'],
                    'success' => (int)$row['success'],
                    'errors' => (int)$row['errors'],
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get recent parser runs.
     */
    private function getRecentParserRuns(): array
    {
        try {
            return Database::fetchAll(
                "SELECT pr.*, sp.list_url, s.name as source_name
                 FROM parser_runs pr
                 JOIN source_parsers sp ON sp.id = pr.source_parser_id
                 JOIN sources s ON s.id = sp.source_id
                 ORDER BY pr.started_at DESC
                 LIMIT 5"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }
}
