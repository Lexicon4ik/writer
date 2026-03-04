<?php declare(strict_types=1);

namespace NewsBot\Models;

use NewsBot\Core\Database;

/**
 * Parser run log model - tracks each parser execution.
 */
class ParserRun extends BaseModel
{
    protected static string $table = 'parser_runs';

    /**
     * Start a new parser run.
     *
     * @return int Run ID
     */
    public static function start(int $sourceParserId): int
    {
        return Database::insert('parser_runs', [
            'source_parser_id' => $sourceParserId,
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Finish a parser run with results.
     */
    public static function finish(int $runId, array $stats): void
    {
        $now = date('Y-m-d H:i:s');

        $startedAt = Database::fetchOne(
            "SELECT started_at FROM parser_runs WHERE id = ?",
            [$runId]
        );

        $durationMs = null;
        if ($startedAt && $startedAt['started_at']) {
            $start = strtotime($startedAt['started_at']);
            $durationMs = (int)((microtime(true) - $start) * 1000);
        }

        Database::update('parser_runs', [
            'finished_at' => $now,
            'duration_ms' => $durationMs,
            'pages_parsed' => $stats['pages_parsed'] ?? 0,
            'articles_found' => $stats['articles_found'] ?? 0,
            'articles_new' => $stats['articles_new'] ?? 0,
            'articles_skipped' => $stats['articles_skipped'] ?? 0,
            'error_message' => $stats['error_message'] ?? null,
        ], 'id = ?', [$runId]);
    }

    /**
     * Get recent runs for a parser.
     */
    public static function getRecent(int $sourceParserId, int $limit = 10): array
    {
        return self::all(
            'source_parser_id = ?',
            [$sourceParserId],
            'started_at DESC',
            $limit
        );
    }

    /**
     * Get parser for this run.
     */
    public function getParser(): ?SourceParser
    {
        return SourceParser::find((int)$this->source_parser_id);
    }

    /**
     * Check if run completed successfully.
     */
    public function isSuccess(): bool
    {
        return $this->finished_at !== null && empty($this->error_message);
    }
}
