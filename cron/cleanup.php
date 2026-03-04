#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * Cleanup script for NewsBot.
 * Handles:
 * - Expiring stuck articles
 * - Cleaning old data from log tables
 * - Removing very old failed/expired articles
 * - Cleaning orphaned fingerprints
 * - Running automated alert checks
 *
 * Usage: php cron/cleanup.php
 * Crontab: 0 * * * * php /var/www/html/writer/cron/cleanup.php
 */

require_once __DIR__ . '/../config/app.php';

use NewsBot\Core\{Lock, Logger, AlertManager, Database, Settings, ShutdownHandler};
use NewsBot\Models\Article;

// Initialize shutdown handler
ShutdownHandler::init();

// Acquire lock
$lock = new Lock('cleanup');
if (!$lock->acquire()) {
    Logger::warning('Cleanup script already running, skipping');
    exit(0);
}

try {
    Logger::info('Cleanup started');
    $startTime = microtime(true);

    // =================================================================
    // 1. Expire stuck articles
    // =================================================================
    $maxAge = Settings::getInt('max_article_age_hours', 24);
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$maxAge} hours"));

    // IMPORTANT: 'processed' is NOT included - it's the final successful status.
    // Publishing is managed via article_versions.status, not articles.status.
    // 'publishing'/'published'/'publish_failed' are legacy statuses, not used in normal flow.
    // 'manual_review' is NOT included - these are waiting for admin action.
    $stuckStatuses = ['fetched', 'scraping', 'scraped', 'processing'];

    $totalExpired = 0;
    foreach ($stuckStatuses as $status) {
        if (ShutdownHandler::shouldShutdown()) {
            Logger::info('Shutdown during cleanup');
            break;
        }

        $articles = Article::all(
            "status = ? AND updated_at < ?",
            [$status, $cutoff],
            'updated_at ASC',
            100 // Process in batches to avoid memory issues
        );

        foreach ($articles as $article) {
            $article->changeStatus('expired', [
                'reason' => "Stuck in '{$status}' for over {$maxAge} hours",
                'original_status' => $status,
            ]);
            $totalExpired++;
        }

        if (count($articles) > 0) {
            Logger::info("Expired " . count($articles) . " articles stuck in '{$status}'");
        }
    }

    if ($totalExpired > 0) {
        Logger::info('Total expired articles', ['count' => $totalExpired]);
    }

    // =================================================================
    // 2. Clean old data from log tables
    // =================================================================
    if (!ShutdownHandler::shouldShutdown()) {
        // article_status_log > 30 days
        $deleted = Database::query(
            "DELETE FROM article_status_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->rowCount();
        if ($deleted > 0) {
            Logger::info('Cleaned article_status_log', ['deleted' => $deleted]);
        }
    }

    if (!ShutdownHandler::shouldShutdown()) {
        // ai_usage_log > 90 days
        $deleted = Database::query(
            "DELETE FROM ai_usage_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        )->rowCount();
        if ($deleted > 0) {
            Logger::info('Cleaned ai_usage_log', ['deleted' => $deleted]);
        }
    }

    if (!ShutdownHandler::shouldShutdown()) {
        // admin_login_attempts > 7 days
        $deleted = Database::query(
            "DELETE FROM admin_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->rowCount();
        if ($deleted > 0) {
            Logger::info('Cleaned admin_login_attempts', ['deleted' => $deleted]);
        }
    }

    if (!ShutdownHandler::shouldShutdown()) {
        // pipeline_runs > 30 days
        $deleted = Database::query(
            "DELETE FROM pipeline_runs WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->rowCount();
        if ($deleted > 0) {
            Logger::info('Cleaned pipeline_runs', ['deleted' => $deleted]);
        }
    }

    if (!ShutdownHandler::shouldShutdown()) {
        // ai_errors > 30 days
        $deleted = Database::query(
            "DELETE FROM ai_errors WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->rowCount();
        if ($deleted > 0) {
            Logger::info('Cleaned ai_errors', ['deleted' => $deleted]);
        }
    }

    if (!ShutdownHandler::shouldShutdown()) {
        // parser_runs > 30 days
        $deleted = Database::query(
            "DELETE FROM parser_runs WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->rowCount();
        if ($deleted > 0) {
            Logger::info('Cleaned parser_runs', ['deleted' => $deleted]);
        }
    }

    if (!ShutdownHandler::shouldShutdown()) {
        // domain_rate_limits > 7 days (inactive domains)
        $deleted = Database::query(
            "DELETE FROM domain_rate_limits WHERE last_request_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->rowCount();
        if ($deleted > 0) {
            Logger::info('Cleaned domain_rate_limits', ['deleted' => $deleted]);
        }
    }

    // =================================================================
    // 3. Delete very old scrape_failed and expired articles (>180 days)
    //    Process in batches to avoid long transactions with cascade deletes
    // =================================================================
    if (!ShutdownHandler::shouldShutdown()) {
        $batchSize = 1000;
        $totalOldDeleted = 0;

        do {
            if (ShutdownHandler::shouldShutdown()) {
                break;
            }

            // Get IDs of old articles to delete
            $oldArticles = Database::fetchAll(
                "SELECT id FROM articles
                 WHERE status IN ('scrape_failed', 'expired')
                 AND created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)
                 LIMIT ?",
                [$batchSize]
            );

            if (empty($oldArticles)) {
                break;
            }

            $ids = array_column($oldArticles, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // Delete related data first (for tables without CASCADE)
            Database::query(
                "DELETE FROM article_status_log WHERE article_id IN ({$placeholders})",
                $ids
            );
            Database::query(
                "DELETE FROM article_fingerprints WHERE article_id IN ({$placeholders})",
                $ids
            );
            Database::query(
                "DELETE FROM article_versions WHERE article_id IN ({$placeholders})",
                $ids
            );
            Database::query(
                "DELETE FROM article_cluster_members WHERE article_id IN ({$placeholders})",
                $ids
            );

            // Delete articles
            $deleted = Database::query(
                "DELETE FROM articles WHERE id IN ({$placeholders})",
                $ids
            )->rowCount();

            $totalOldDeleted += $deleted;

            // Small pause to reduce database load
            if (count($oldArticles) === $batchSize) {
                usleep(100000); // 100ms
            }

        } while (count($oldArticles) === $batchSize);

        if ($totalOldDeleted > 0) {
            Logger::info('Deleted very old articles (>180 days)', ['deleted' => $totalOldDeleted]);
        }
    }

    // =================================================================
    // 4. Clean orphaned article_fingerprints (article deleted)
    // =================================================================
    if (!ShutdownHandler::shouldShutdown()) {
        $deleted = Database::query(
            "DELETE af FROM article_fingerprints af
             LEFT JOIN articles a ON a.id = af.article_id
             WHERE a.id IS NULL"
        )->rowCount();

        if ($deleted > 0) {
            Logger::info('Cleaned orphaned article_fingerprints', ['deleted' => $deleted]);
        }
    }

    if (!ShutdownHandler::shouldShutdown()) {
        // Clean orphaned article_cluster_members
        $deleted = Database::query(
            "DELETE acm FROM article_cluster_members acm
             LEFT JOIN articles a ON a.id = acm.article_id
             WHERE a.id IS NULL"
        )->rowCount();

        if ($deleted > 0) {
            Logger::info('Cleaned orphaned article_cluster_members', ['deleted' => $deleted]);
        }
    }

    if (!ShutdownHandler::shouldShutdown()) {
        // Clean empty clusters (no members)
        $deleted = Database::query(
            "DELETE ac FROM article_clusters ac
             LEFT JOIN article_cluster_members acm ON acm.cluster_id = ac.id
             WHERE acm.cluster_id IS NULL"
        )->rowCount();

        if ($deleted > 0) {
            Logger::info('Cleaned empty article_clusters', ['deleted' => $deleted]);
        }
    }

    // =================================================================
    // 5. Run automated alert checks
    // =================================================================
    if (!ShutdownHandler::shouldShutdown()) {
        AlertManager::checkAlerts();
    }

    // =================================================================
    // Done
    // =================================================================
    $duration = round((microtime(true) - $startTime) * 1000);
    Logger::info('Cleanup completed', ['duration_ms' => $duration]);

} catch (\Throwable $e) {
    Logger::error('Cleanup failed: ' . $e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
} finally {
    $lock->release();
}
