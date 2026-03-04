<?php declare(strict_types=1);

namespace NewsBot\Web\Controllers;

use NewsBot\Models\Source;
use NewsBot\Models\SourceParser;
use NewsBot\Services\WebParser;
use NewsBot\Services\HtmlAnalyzer;
use NewsBot\Core\Database;
use NewsBot\Core\Logger;

/**
 * Controller for managing custom parsers.
 */
class ParserController extends BaseController
{
    /**
     * List all parsers.
     */
    public function index(): void
    {
        $parsers = Database::fetchAll('
            SELECT sp.*, s.name as source_name, s.site_url
            FROM source_parsers sp
            JOIN sources s ON s.id = sp.source_id
            ORDER BY sp.is_active DESC, s.name ASC
        ');

        // Get sources without parsers for "Add" dropdown
        $sourcesWithoutParsers = Database::fetchAll("
            SELECT s.id, s.name, s.site_url
            FROM sources s
            WHERE s.scrape_strategy = 'custom_parser'
            AND s.status = 'active'
            AND NOT EXISTS (
                SELECT 1 FROM source_parsers sp WHERE sp.source_id = s.id
            )
            ORDER BY s.name ASC
        ");

        $this->render('parsers/index', [
            'pageTitle'              => 'Custom Parsers',
            'parsers'                => $parsers,
            'sourcesWithoutParsers'  => $sourcesWithoutParsers,
        ]);
    }

    /**
     * Edit/create parser form.
     */
    public function edit(): void
    {
        $sourceId = (int)($_GET['source_id'] ?? 0);
        $source = Source::find($sourceId);

        if (!$source) {
            $_SESSION['flash_error'] = 'Source not found';
            header('Location: ?page=parsers');
            exit;
        }

        $parser = SourceParser::findForSource($sourceId);
        $errors = [];
        $success = false;

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->save($source, $parser);
            if ($result['success']) {
                $_SESSION['flash_success'] = 'Parser saved successfully';
                header('Location: ?page=parsers');
                exit;
            }
            $errors = $result['errors'];
            // Reload parser with submitted data for form redisplay
            $parser = $this->createParserFromPost($sourceId);
        }

        // Get recent parser runs for stats
        $recentRuns = [];
        if ($parser) {
            $recentRuns = Database::fetchAll(
                'SELECT * FROM parser_runs WHERE source_parser_id = ? ORDER BY started_at DESC LIMIT 10',
                [(int)$parser->id]
            );
        }

        $this->render('parsers/edit', [
            'pageTitle'  => 'Edit Parser',
            'source'     => $source,
            'parser'     => $parser,
            'errors'     => $errors,
            'success'    => $success,
            'recentRuns' => $recentRuns,
        ]);
    }

    /**
     * Test parser (AJAX endpoint).
     */
    public function test(): void
    {
        header('Content-Type: application/json');

        $sourceId = (int)($_POST['source_id'] ?? 0);

        // Allow testing with unsaved config from form
        if (isset($_POST['test_config'])) {
            $parser = $this->createParserFromPost($sourceId);
        } else {
            $parser = SourceParser::findForSource($sourceId);
        }

        if (!$parser || empty($parser->list_url)) {
            echo json_encode(['error' => 'Parser configuration not found or incomplete']);
            return;
        }

        try {
            $webParser = new WebParser();
            // Parse only first page for testing
            $testParser = $parser->withMaxPages(1);

            $startTime = microtime(true);
            $articles = $webParser->parseList($testParser);
            $duration = round((microtime(true) - $startTime) * 1000);

            echo json_encode([
                'success' => true,
                'count' => count($articles),
                'duration_ms' => $duration,
                'articles' => array_slice($articles, 0, 15), // First 15 for preview
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Parser test failed', [
                'source_id' => $sourceId,
                'error' => $e->getMessage(),
            ]);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Analyze source HTML and suggest parser selectors (AJAX).
     */
    public function analyze(): void
    {
        header('Content-Type: application/json');

        $sourceId = (int)($_POST['source_id'] ?? 0);
        $source = Source::find($sourceId);

        if (!$source) {
            echo json_encode(['success' => false, 'message' => 'Источник не найден']);
            return;
        }

        $url = trim($_POST['url'] ?? '') ?: $source->site_url;

        try {
            $webParser = new WebParser();
            $html = $webParser->fetchPage($url);

            if (!$html) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Не удалось загрузить страницу: ' . $url,
                ]);
                return;
            }

            $analyzer = new HtmlAnalyzer();
            $result = $analyzer->analyze($html, $url);
            echo json_encode($result);
        } catch (\Throwable $e) {
            Logger::warning('Source analysis failed', [
                'source_id' => $sourceId,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            echo json_encode([
                'success' => false,
                'message' => 'Ошибка анализа: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete parser.
     */
    public function delete(): void
    {
        $sourceId = (int)($_POST['source_id'] ?? 0);
        $parser = SourceParser::findForSource($sourceId);

        if ($parser) {
            SourceParser::delete((int)$parser->id);
            $_SESSION['flash_success'] = 'Parser deleted successfully';
        } else {
            $_SESSION['flash_error'] = 'Parser not found';
        }

        header('Location: ?page=parsers');
        exit;
    }

    /**
     * Toggle parser active status.
     */
    public function toggle(): void
    {
        $sourceId = (int)($_POST['source_id'] ?? 0);
        $parser = SourceParser::findForSource($sourceId);

        if ($parser) {
            $newStatus = $parser->is_active ? 0 : 1;
            SourceParser::update((int)$parser->id, ['is_active' => $newStatus]);
            $_SESSION['flash_success'] = $newStatus ? 'Parser enabled' : 'Parser disabled';
        } else {
            $_SESSION['flash_error'] = 'Parser not found';
        }

        header('Location: ?page=parsers');
        exit;
    }

    /**
     * Reset parser errors (reactivate).
     */
    public function resetErrors(): void
    {
        $this->reactivate();
    }

    /**
     * Reactivate parser: reset is_active=1, consecutive_errors=0.
     */
    public function reactivate(?int $id = null): void
    {
        $this->requirePost();

        $sourceId = (int)($_POST['source_id'] ?? $id ?? 0);
        $parser = SourceParser::findForSource($sourceId);

        if ($parser) {
            SourceParser::update((int)$parser->id, [
                'consecutive_errors' => 0,
                'consecutive_zero_articles' => 0,
                'last_error' => null,
                'is_active' => 1,
            ]);
            $this->setFlash('success', 'Parser reactivated');
        } else {
            $this->setFlash('danger', 'Parser not found');
        }

        $this->redirect('?page=parsers');
    }

    /**
     * Save parser configuration.
     *
     * @param Source $source Source model
     * @param SourceParser|null $parser Existing parser or null for create
     * @return array ['success' => bool, 'errors' => array]
     */
    private function save(Source $source, ?SourceParser $parser): array
    {
        $errors = [];

        // Validate required fields
        $listUrl = trim($_POST['list_url'] ?? '');
        $articleSelector = trim($_POST['article_selector'] ?? '');
        $linkSelector = trim($_POST['link_selector'] ?? '');

        if (empty($listUrl)) {
            $errors[] = 'List URL is required';
        } elseif (!filter_var($listUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'List URL must be a valid URL';
        }

        if (empty($articleSelector)) {
            $errors[] = 'Article selector is required';
        }

        if (empty($linkSelector)) {
            $errors[] = 'Link selector is required';
        }

        // Validate exclude patterns (must be valid regex)
        $excludePatterns = trim($_POST['exclude_patterns'] ?? '');
        if (!empty($excludePatterns)) {
            $patterns = array_filter(array_map('trim', explode("\n", $excludePatterns)));
            foreach ($patterns as $pattern) {
                if (@preg_match($pattern, '') === false) {
                    $errors[] = "Invalid regex pattern: {$pattern}";
                }
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Prepare data
        $data = [
            'source_id' => $source->id,
            'list_url' => $listUrl,
            'article_selector' => $articleSelector,
            'link_selector' => $linkSelector,
            'title_selector' => $this->nullIfEmpty($_POST['title_selector'] ?? ''),
            'date_selector' => $this->nullIfEmpty($_POST['date_selector'] ?? ''),
            'image_selector' => $this->nullIfEmpty($_POST['image_selector'] ?? ''),
            'description_selector' => $this->nullIfEmpty($_POST['description_selector'] ?? ''),
            'pagination_type' => $_POST['pagination_type'] ?? 'none',
            'pagination_param' => $this->nullIfEmpty($_POST['pagination_param'] ?? ''),
            'pagination_selector' => $this->nullIfEmpty($_POST['pagination_selector'] ?? ''),
            'pagination_start' => (int)($_POST['pagination_start'] ?? 1),
            'max_pages' => (int)($_POST['max_pages'] ?? 3),
            'request_delay_ms' => (int)($_POST['request_delay_ms'] ?? 2000),
            'offset_increment' => (int)($_POST['offset_increment'] ?? 20),
            'date_format' => $this->nullIfEmpty($_POST['date_format'] ?? ''),
            'link_base_url' => $this->nullIfEmpty($_POST['link_base_url'] ?? ''),
            'exclude_patterns' => !empty($excludePatterns)
                ? json_encode(array_values(array_filter(array_map('trim', explode("\n", $excludePatterns)))))
                : null,
            'min_title_length' => (int)($_POST['min_title_length'] ?? 10),
            'min_articles_threshold' => (int)($_POST['min_articles_threshold'] ?? 0),
            'max_zero_runs' => (int)($_POST['max_zero_runs'] ?? 3),
            'max_errors' => (int)($_POST['max_errors'] ?? 5),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'fetch_interval_min' => (($v = (int)($_POST['fetch_interval_min'] ?? 0)) > 0) ? $v : null,
        ];

        try {
            if ($parser) {
                SourceParser::update((int)$parser->id, $data);
            } else {
                SourceParser::create($data);
            }

            // Update source strategy if needed
            if ($source->scrape_strategy !== 'custom_parser') {
                Source::update((int)$source->id, ['scrape_strategy' => 'custom_parser']);
            }

            return ['success' => true, 'errors' => []];
        } catch (\Throwable $e) {
            Logger::error('Failed to save parser', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'errors' => ['Failed to save: ' . $e->getMessage()]];
        }
    }

    /**
     * Create SourceParser object from POST data (for form redisplay or testing).
     */
    private function createParserFromPost(int $sourceId): SourceParser
    {
        $excludePatterns = trim($_POST['exclude_patterns'] ?? '');
        $patternsJson = !empty($excludePatterns)
            ? json_encode(array_values(array_filter(array_map('trim', explode("\n", $excludePatterns)))))
            : null;

        return new SourceParser([
            'id' => (int)($_POST['parser_id'] ?? 0),
            'source_id' => $sourceId,
            'list_url' => $_POST['list_url'] ?? '',
            'article_selector' => $_POST['article_selector'] ?? '',
            'link_selector' => $_POST['link_selector'] ?? '',
            'title_selector' => $_POST['title_selector'] ?? null,
            'date_selector' => $_POST['date_selector'] ?? null,
            'image_selector' => $_POST['image_selector'] ?? null,
            'description_selector' => $_POST['description_selector'] ?? null,
            'pagination_type' => $_POST['pagination_type'] ?? 'none',
            'pagination_param' => $_POST['pagination_param'] ?? null,
            'pagination_selector' => $_POST['pagination_selector'] ?? null,
            'pagination_start' => (int)($_POST['pagination_start'] ?? 1),
            'max_pages' => (int)($_POST['max_pages'] ?? 3),
            'request_delay_ms' => (int)($_POST['request_delay_ms'] ?? 2000),
            'offset_increment' => (int)($_POST['offset_increment'] ?? 20),
            'date_format' => $_POST['date_format'] ?? null,
            'link_base_url' => $_POST['link_base_url'] ?? null,
            'exclude_patterns' => $patternsJson,
            'min_title_length' => (int)($_POST['min_title_length'] ?? 10),
            'min_articles_threshold' => (int)($_POST['min_articles_threshold'] ?? 0),
            'max_zero_runs' => (int)($_POST['max_zero_runs'] ?? 3),
            'max_errors' => (int)($_POST['max_errors'] ?? 5),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'fetch_interval_min' => (($v = (int)($_POST['fetch_interval_min'] ?? 0)) > 0) ? $v : null,
        ]);
    }

    /**
     * Return null if string is empty, otherwise return trimmed string.
     */
    private function nullIfEmpty(string $value): ?string
    {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
