<?php

namespace Okay\Modules\Sviat\LogViewer\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\BackendTranslations;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Modules\Sviat\LogViewer\Services\LogParser;
use Okay\Modules\Sviat\LogViewer\Services\LogService;
use Okay\Modules\Sviat\LogViewer\Services\LogSource;

class LogViewerAdmin extends IndexAdmin
{
    private const TEMPLATE = 'log_viewer.tpl';

    private const ALLOWED_LIMITS = [10, 25, 50, 100];
    private const DEFAULT_LIMIT = 25;
    private const MAX_QUERY_LENGTH = 200;

    private const ALLOWED_LEVELS = [
        'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG',
    ];

    private const LEVEL_LABELS = [
        'CRITICAL' => 'sviat_log_viewer__level_critical',
        'ERROR'    => 'sviat_log_viewer__level_error',
        'WARNING'  => 'sviat_log_viewer__level_warning',
        'NOTICE'   => 'sviat_log_viewer__level_notice',
        'INFO'     => 'sviat_log_viewer__level_info',
        'DEBUG'    => 'sviat_log_viewer__level_debug',
    ];

    public function fetch(
        LogService $logService,
        LogParser $logParser,
        BackendTranslations $btr
    ): void {
        $registry = $logService->getRegistry();

        $sourceKey = (string) $this->request->get('source', 'string');
        if (!$registry->has($sourceKey)) {
            $sourceKey = $registry::DEFAULT_SOURCE;
        }
        $source = $registry->getOrDefault($sourceKey);

        $logFiles = $logService->listFiles($source);

        $selectedDate = $this->normalizeDate(
            (string) $this->request->get('date', 'string'),
            $logFiles
        );
        $level = $this->normalizeLevel((string) $this->request->get('level', 'string'));
        $keyword = $this->normalizeKeyword((string) $this->request->get('q', 'string'));
        $limit = $this->normalizeLimit($this->request->get('limit', 'integer'));
        $page = max(1, (int) $this->request->get('page', 'integer'));

        $tooLarge = false;
        $tooLargeSize = 0;
        $entries = [];

        if ($selectedDate !== '') {
            $read = $logService->readEntriesForDate($selectedDate, $logFiles);
            if (is_array($read)) {
                $tooLarge = !empty($read['too_large']);
                $tooLargeSize = (int) ($read['size'] ?? 0);
                $entries = $read['entries'];
            }
        } else {
            $entries = $logService->readAllEntries($source);
        }

        $entries = $logParser->filterByLevel($entries, $level);
        $entries = $logParser->filterByText($entries, $keyword);

        $total = count($entries);
        $pagesCount = $limit > 0 ? (int) ceil($total / $limit) : 1;
        if ($pagesCount < 1) {
            $pagesCount = 1;
        }
        $page = min($page, $pagesCount);
        $offset = ($page - 1) * $limit;
        $paginated = array_slice($entries, $offset, $limit);

        $todayDate = date('Y-m-d');
        $canDeleteSelected = $selectedDate !== '' && $selectedDate !== $todayDate;

        $sourceLabels = [];
        foreach ($registry->all() as $key => $src) {
            $sourceLabels[$key] = (string) $btr->{$src->getLabelKey()};
        }

        $levelLabels = [];
        foreach (self::LEVEL_LABELS as $level_key => $label_key) {
            $levelLabels[$level_key] = (string) $btr->$label_key;
        }

        $flashRaw = $_SESSION['sviat_log_viewer_flash'] ?? null;
        unset($_SESSION['sviat_log_viewer_flash']);
        $flash = null;
        if (is_array($flashRaw) && !empty($flashRaw['key'])) {
            $key = $flashRaw['key'];
            $flash = [
                'type'    => $flashRaw['type'] ?? 'success',
                'text'    => (string) $btr->$key,
                'context' => $flashRaw['context'] ?? [],
            ];
        }

        $this->design->assign('sources', $registry->all());
        $this->design->assign('current_source', $source->getKey());
        $this->design->assign('current_source_label', $sourceLabels[$source->getKey()] ?? '');
        $this->design->assign('source_labels', $sourceLabels);
        $this->design->assign('log_files', $logFiles);
        $this->design->assign('selected_date', $selectedDate);
        $this->design->assign('filter_level', $level);
        $this->design->assign('keyword', $keyword);
        $this->design->assign('current_limit', $limit);
        $this->design->assign('limits', self::ALLOWED_LIMITS);
        $this->design->assign('levels', self::ALLOWED_LEVELS);
        $this->design->assign('level_labels', $levelLabels);
        $this->design->assign('log_entries', $paginated);
        $this->design->assign('total_entries_count', $total);
        $this->design->assign('pages_count', $pagesCount);
        $this->design->assign('current_page', $page);
        $this->design->assign('too_large', $tooLarge);
        $this->design->assign('too_large_size', $tooLargeSize);
        $this->design->assign('max_parse_bytes', LogService::MAX_PARSE_BYTES);
        $this->design->assign('today_date', $todayDate);
        $this->design->assign('can_delete_selected', $canDeleteSelected);
        $this->design->assign('flash', $flash);

        $this->response->setContent($this->design->fetch(self::TEMPLATE));
    }

    public function deleteFile(LogService $logService): void
    {
        $this->ensurePostCsrf();

        $registry = $logService->getRegistry();
        $sourceKey = (string) $this->request->post('source', 'string');
        $date = (string) $this->request->post('date', 'string');

        $source = $registry->get($sourceKey);
        if ($source === null) {
            $this->setFlash('danger', 'sviat_log_viewer__flash_invalid_source');
            $this->redirectBack($sourceKey);
        }

        $result = $logService->deleteFile($source, $date);
        if ($result['ok']) {
            $this->setFlash('success', 'sviat_log_viewer__flash_deleted', ['date' => $date]);
        } else {
            $reason = $result['reason'] ?? '';
            $this->setFlash(
                'danger',
                $this->mapDeleteReason($reason),
                ['date' => $date]
            );
        }

        $this->redirectBack($source->getKey());
    }

    public function downloadFile(LogService $logService): void
    {
        $registry = $logService->getRegistry();
        $sourceKey = (string) $this->request->get('source', 'string');
        $date = (string) $this->request->get('date', 'string');

        $source = $registry->get($sourceKey);
        if ($source === null) {
            $this->redirectBack($registry::DEFAULT_SOURCE);
        }

        $path = $logService->resolveFilePath($source, $date);
        if ($path === null) {
            $this->setFlash('danger', 'sviat_log_viewer__flash_not_found', ['date' => $date]);
            $this->redirectBack($source->getKey());
        }

        $size = @filesize($path);
        if ($size === false) {
            $this->setFlash('danger', 'sviat_log_viewer__flash_not_found', ['date' => $date]);
            $this->redirectBack($source->getKey());
        }

        $filename = basename($path);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $size);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($path);
        exit;
    }

    private function ensurePostCsrf(): void
    {
        if (!$this->request->method('post')) {
            $this->redirectBack('');
        }
        if (empty($_POST['session_id']) || $_POST['session_id'] !== session_id()) {
            $this->setFlash('danger', 'sviat_log_viewer__flash_csrf');
            $this->redirectBack('');
        }
    }

    private function redirectBack(string $sourceKey): void
    {
        $params = ['controller' => 'Sviat.LogViewer.LogViewerAdmin'];
        if ($sourceKey !== '') {
            $params['source'] = $sourceKey;
        }
        Response::redirectTo(Request::getDomainWithProtocol() . $this->request->url($params));
    }

    private function setFlash(string $type, string $messageKey, array $context = []): void
    {
        $_SESSION['sviat_log_viewer_flash'] = [
            'type'    => $type,
            'key'     => $messageKey,
            'context' => $context,
        ];
    }

    private function normalizeDate(string $date, array $logFiles): string
    {
        if ($date === '') {
            return '';
        }
        if (!preg_match(LogService::DATE_REGEX, $date)) {
            return '';
        }
        return isset($logFiles[$date]) ? $date : '';
    }

    private function normalizeLevel(string $level): string
    {
        if ($level === '') {
            return '';
        }
        $level = strtoupper($level);
        return in_array($level, self::ALLOWED_LEVELS, true) ? $level : '';
    }

    private function normalizeKeyword(string $keyword): string
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return '';
        }
        if (mb_strlen($keyword) > self::MAX_QUERY_LENGTH) {
            $keyword = mb_substr($keyword, 0, self::MAX_QUERY_LENGTH);
        }
        return $keyword;
    }

    private function normalizeLimit($value): int
    {
        $value = (int) $value;
        if (in_array($value, self::ALLOWED_LIMITS, true)) {
            return $value;
        }
        return self::DEFAULT_LIMIT;
    }

    private function mapDeleteReason(string $reason): string
    {
        switch ($reason) {
            case 'today_protected':
                return 'sviat_log_viewer__flash_today_protected';
            case 'invalid_date':
                return 'sviat_log_viewer__flash_invalid_date';
            case 'not_found':
                return 'sviat_log_viewer__flash_not_found';
            default:
                return 'sviat_log_viewer__flash_delete_failed';
        }
    }
}
