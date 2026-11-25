<?php

namespace Kanboard\Plugin\Harbor\Helper;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Kanboard\Core\Base;
use Kanboard\Core\Translator;
use Throwable;

class KitHelper extends Base
{
    public function actions(string $slot, array $entity): string
    {
        $actions = $this->container['harborActionRegistry']->forSlot(
            $slot,
            $entity,
            $this->userSession->getAll() ?? []
        );
        $menu = [];
        $enabled = [];
        foreach ($actions as $action) {
            $url = $this->actionUrl($action['route']);
            if ($url === null) {
                continue;
            }
            $menu[] = array_intersect_key($action, array_flip(['id', 'label', 'key', 'disabled', 'states'])) + [
                'url' => $url,
                'csrf_token' => $this->token->getCSRFToken(),
            ];
            if (!$action['disabled']) {
                $enabled[] = $action['id'];
            }
        }

        $state = $entity['state'] ?? $entity['status'] ?? '';
        $text = $this->helper->text;
        $json = json_encode($menu, JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

        return '<div data-slot="' . $text->e($slot)
            . '" data-state="' . $text->e(is_scalar($state) ? $state : '')
            . '" data-actions="' . $text->e(implode(' ', $enabled)) . '">'
            . '<template>' . $json . '</template></div>';
    }

    private function actionUrl(array|string $route): ?string
    {
        if (is_array($route)) {
            [$controller, $action] = [$route[0] ?? null, $route[1] ?? null];
            if (!is_string($controller) || !is_string($action)) {
                return null;
            }
            $params = array_diff_key($route, [0 => true, 1 => true]);

            // The clean subtask route needs only its path parameters. Keep the
            // task and project IDs as query params for the controller to verify.
            $context = array_intersect_key($params, ['task_id' => true, 'project_id' => true]);
            if ($context && $this->route->findUrl($controller, $action, $params) === '') {
                $path = $this->route->findUrl($controller, $action, array_diff_key($params, $context));
                if ($path !== '') {
                    return $this->helper->url->dir() . $path . '?' . http_build_query($context);
                }
            }

            return $this->helper->url->to($controller, $action, $params);
        }

        return preg_match('~\A(?:/(?!/)|\?)[^\\\\[:cntrl:]]*\z~', $route) ? $route : null;
    }

    public function pill(string $label, string $tone = 'neutral'): string
    {
        return $this->template->render('Harbor:kit/pill', compact('label', 'tone'));
    }

    public function avatar(string $name, ?string $presence = null): string
    {
        $initials = $this->helper->user->getInitials(trim($name));
        $presenceLabel = match ($presence) {
            'on', 'online' => t('Online'),
            'away' => t('Away'),
            'off', 'offline' => t('Offline'),
            default => $presence,
        };

        return $this->template->render('Harbor:kit/avatar', compact('name', 'initials', 'presence', 'presenceLabel'));
    }

    public function copyButton(string $value): string
    {
        $copy = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $copiedLabel = $this->helper->text->e(e('Copied'));

        return '<button type="button" data-slot="copy-button" data-copy="' . $copy
            . '" data-copied-label="' . $copiedLabel . '" aria-live="polite">' . t('Copy') . '</button>';
    }

    public function kbd(string $key): string
    {
        return '<kbd data-slot="kbd">' . $this->helper->text->e($key) . '</kbd>';
    }

    public function shortcutSheet(): string
    {
        // This reference describes registered keys without selecting or running actions for an entity.
        $groups = [];
        foreach ($this->hook->getListeners('kit:actions') as $listenerIndex => $listener) {
            try {
                $descriptors = is_callable($listener) ? $listener() : null;
            } catch (Throwable $exception) {
                $this->logger->warning('Harbor skipped a kit:actions listener while rendering keyboard shortcuts.', [
                    'listener' => $listenerIndex,
                    'exception' => $exception,
                ]);
                continue;
            }
            if (!is_array($descriptors)) {
                continue;
            }
            foreach ($descriptors as $descriptor) {
                if (is_string($descriptor['key'] ?? null) && is_string($descriptor['slot'] ?? null)
                    && is_string($descriptor['label'] ?? null)) {
                    $groups[$descriptor['slot']][] = ['label' => $descriptor['label'], 'key' => $descriptor['key']];
                }
            }
        }

        return $this->template->render('Harbor:kit/shortcut_sheet', compact('groups'));
    }

    public function visuallyHidden(string $text): string
    {
        return '<span data-slot="visually-hidden">' . $this->helper->text->e($text) . '</span>';
    }

    public function callout(string $title, string $body, string $tone = 'neutral'): string
    {
        return $this->template->render('Harbor:kit/callout', compact('title', 'body', 'tone'));
    }

    /** The stage content is trusted template HTML. Mark fading controls with data-float-controls. */
    public function stage(string $contentHtml, bool $playing = false): string
    {
        return $this->template->render('Harbor:kit/stage', compact('contentHtml', 'playing'));
    }

    /**
     * The body is trusted HTML and must already escape any user content.
     *
     * @param list<string> $toolChips Plain-text tool call labels.
     */
    public function chatMessage(string $author, string $bodyHtml, string $time, bool $self, array $toolChips = []): string
    {
        return $this->template->render('Harbor:kit/chat_message', compact('author', 'bodyHtml', 'time', 'self', 'toolChips'));
    }

    public function composer(string $placeholder, string $formAction): string
    {
        $formAction = $this->actionUrl($formAction);
        if ($formAction === null) {
            throw new \InvalidArgumentException('A composer requires a local form action.');
        }

        return $this->template->render('Harbor:kit/composer', compact('placeholder', 'formAction'));
    }

    /** The body is trusted template HTML. Open the dialog with Kit.typedConfirm(dialog, trigger). */
    public function typedConfirm(string $title, string $bodyHtml, string $phrase, string $actionLabel, string $formAction): string
    {
        $formAction = $this->actionUrl($formAction);
        if ($formAction === null) {
            throw new \InvalidArgumentException('A typed confirm requires a local form action.');
        }

        return $this->template->render('Harbor:kit/typed_confirm', compact('title', 'bodyHtml', 'phrase', 'actionLabel', 'formAction'));
    }

    /**
     * The sentence is trusted HTML and must already escape any user content.
     */
    public function activityRow(string $actorName, string $sentenceHtml, int $timestamp, string $id = ''): string
    {
        $initials = $this->helper->user->getInitials(trim($actorName));

        return $this->template->render('Harbor:kit/activity_row', compact('initials', 'sentenceHtml', 'timestamp', 'id'));
    }

    /**
     * @param array<string, int> $countsByDay Event counts keyed by local YYYY-MM-DD.
     */
    public function heatmap(array $countsByDay, int $weeks = 26): string
    {
        $days = [];
        $dayCount = max(0, $weeks) * 7;
        if ($dayCount > 0) {
            $day = new DateTimeImmutable('today -' . ($dayCount - 1) . ' days');
            for ($index = 0; $index < $dayCount; ++$index, $day = $day->modify('+1 day')) {
                $date = $day->format('Y-m-d');
                $days[$date] = max(0, (int) ($countsByDay[$date] ?? 0));
            }
        }
        $peak = $days === [] ? 1 : max(1, max($days));
        $levels = array_map(static fn (int $count): int => (int) ceil(4 * ($count / $peak)), $days);

        return $this->template->render('Harbor:kit/heatmap', compact('days', 'levels'));
    }

    /** @param list<int|float>|null $spark */
    public function statTile(string $label, string|int|float $value, ?array $spark = null): string
    {
        $points = $spark === null ? null : self::chartJson(array_values($spark));

        return $this->template->render('Harbor:kit/stat_tile', compact('label', 'value', 'points'));
    }

    /**
     * Each value is indexed by its position in $labels. A null or missing value leaves a gap.
     *
     * @param list<array{name: string, values: list<int|float|null>}> $series
     * @param list<string> $labels
     */
    public function lineChart(array $series, array $labels): string
    {
        if (count($series) > 5) {
            throw new InvalidArgumentException('A line chart supports up to five series.');
        }

        $labels = array_values($labels);
        $labelCount = count($labels);
        $series = array_values($series);
        foreach ($series as $index => $entry) {
            $series[$index] = [
                'name' => $entry['name'],
                'values' => array_slice($entry['values'], 0, $labelCount),
                'color' => '--chart-' . ($index + 1),
            ];
        }
        $seriesJson = self::chartJson($series);
        $labelsJson = self::chartJson($labels);

        return $this->template->render('Harbor:kit/line_chart', compact('series', 'labels', 'seriesJson', 'labelsJson'));
    }

    public function usageBar(string $label, float $used, float $total, string $unit): string
    {
        $ratio = $total > 0 ? $used / $total : 0;
        $level = match (true) {
            $ratio > 0.9 => 'danger',
            $ratio > 0.8 => 'warning',
            default => 'normal',
        };
        $pct = 100 * max(0, min(1, $ratio));

        return $this->template->render('Harbor:kit/usage_bar', compact('label', 'used', 'total', 'unit', 'level', 'pct'));
    }

    public function status(string $state): string
    {
        [$icon, $label] = match ($state) {
            'running' => ['●', t('Running')],
            'stopped' => ['■', t('Stopped')],
            'starting' => ['◐', t('Starting')],
            'failed' => ['✕', t('Failed')],
            'degraded' => ['▲', t('Degraded')],
            'pending' => ['⏱', t('Pending approval')],
            default => throw new InvalidArgumentException('Unknown status state.'),
        };

        return $this->template->render('Harbor:kit/status', compact('state', 'icon', 'label'));
    }

    /**
     * Column keys select plain-text cells from each row. Sort and pager links are local URLs.
     *
     * @param array<array-key, string|array{label: string, sortUrl?: string, sort?: 'ascending'|'descending'}> $columns
     * @param list<array<array-key, scalar|null>> $rows
     * @param array{start: int, end: int, total: int, previous?: string, next?: string, toolbar?: array} $pager
     */
    public function dataTable(array $columns, array $rows, array $pager): string
    {
        foreach ($columns as $key => $column) {
            $column = is_string($column) ? ['label' => $column] : $column;
            $sort = $column['sort'] ?? null;
            $columns[$key] = [
                'label' => $column['label'],
                'sortUrl' => $this->actionUrl($column['sortUrl'] ?? ''),
                'sort' => in_array($sort, ['ascending', 'descending'], true) ? $sort : null,
            ];
        }
        $previous = $this->actionUrl($pager['previous'] ?? '');
        $next = $this->actionUrl($pager['next'] ?? '');
        $range = t('%s–%s of %s', number_format($pager['start']), number_format($pager['end']), number_format($pager['total']));

        return $this->template->render('Harbor:kit/data_table', compact('columns', 'rows', 'pager', 'range', 'previous', 'next'));
    }

    /**
     * Retry callbacks render trusted controls supplied by the caller.
     *
     * @param list<array{label: string, state: 'done'|'active'|'waiting'|'failed', duration?: string, detail?: string, retry?: Closure(): string}> $steps
     */
    public function stepList(array $steps): string
    {
        foreach ($steps as $index => $step) {
            [$icon, $stateLabel] = match ($step['state']) {
                'done' => ['✓', t('Done')],
                'active' => ['◐', t('Active')],
                'waiting' => ['○', t('Waiting')],
                'failed' => ['✕', t('Failed')],
                default => throw new InvalidArgumentException('Unknown step state.'),
            };
            $details = array_filter([$step['duration'] ?? '', $step['detail'] ?? ''], static fn (string $part): bool => $part !== '');
            $steps[$index] = ['icon' => $icon, 'stateLabel' => $stateLabel, 'summary' => implode(' · ', $details)] + $step;
        }

        return $this->template->render('Harbor:kit/step_list', compact('steps'));
    }

    public function serviceTile(string $name, string $state, string $detail): string
    {
        return $this->template->render('Harbor:kit/service_tile', compact('name', 'state', 'detail'));
    }

    public function logViewer(string $title, string $sseUrl): string
    {
        $lineCount = Translator::getInstance()->get('%d lines', '%d lines');

        return $this->template->render('Harbor:kit/log_viewer', compact('title', 'sseUrl', 'lineCount'));
    }

    /**
     * @param array<string, string> $details Labels and values describing the request.
     * @param string $approvalUrl Local or HTTPS URL for a separate human approval flow.
     */
    public function approvalCard(int $expiresAt, array $details, string $approvalUrl, string $actionLabel): string
    {
        $remaining = max(0, $expiresAt - time());
        $countdown = $remaining > 0 ? sprintf('%d:%02d', intdiv($remaining, 60), $remaining % 60) : t('Expired');
        $href = $this->actionUrl($approvalUrl);
        if ($href === null && str_starts_with($approvalUrl, 'https://') && filter_var($approvalUrl, FILTER_VALIDATE_URL)) {
            $href = $approvalUrl;
        }

        return $this->template->render('Harbor:kit/approval_card', compact('expiresAt', 'details', 'countdown', 'remaining', 'href', 'actionLabel'));
    }

    /**
     * @param array<array{label: string, href?: array|string}> $items In order from root to the current page.
     */
    public function breadcrumbs(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $items = array_values($items);
        $last = count($items) - 1;
        foreach ($items as $index => &$item) {
            $item['href'] = $index < $last ? $this->actionUrl($item['href'] ?? '') : null;
        }
        unset($item);

        return $this->template->render('Harbor:kit/breadcrumbs', compact('items', 'last'));
    }

    /**
     * @param string[] $presets Any subset of the supported relative ranges.
     * Date bounds are ISO 8601 strings.
     */
    public function dateRange(?string $from, ?string $to, array $presets = ['15m', '1h', '24h', '7d']): string
    {
        $presets = array_intersect_key(['15m' => 900, '1h' => 3600, '24h' => 86400, '7d' => 604800], array_flip($presets));
        $id = uniqid('harbor-date-range-');

        return $this->template->render('Harbor:kit/date_range', compact('from', 'to', 'presets', 'id'));
    }

    private static function chartJson(array $data): string
    {
        return json_encode($data, JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    public function progress(int $done, ?int $total, float $spentH, float $estH): string
    {
        $total = $total === null ? null : max(0, $total);
        $done = max(0, $total === null ? $done : min($done, $total));
        $over = $total !== null && $estH > 0 && $spentH > $estH;

        $state = match (true) {
            $total === null => 'indeterminate',
            $over => 'over',
            $total > 0 && $done === $total => 'done',
            default => 'measured',
        };
        $pct = $over ? 100 : ($total > 0 ? (int) round(100 * ($done / $total)) : 0);

        return $this->template->render('Harbor:kit/progress', compact('done', 'total', 'spentH', 'estH', 'state', 'pct'));
    }

    public function skeleton(string $kind): string
    {
        $kind = in_array($kind, ['row', 'card', 'tile'], true) ? $kind : 'row';

        return $this->template->render('Harbor:kit/skeleton', compact('kind'));
    }

    /**
     * Plain action text is escaped. A closure may render trusted template markup.
     *
     * @param string|Closure(): string $actionHtml
     */
    public function empty(string $title, string $body, string|Closure $actionHtml = ''): string
    {
        return $this->template->render('Harbor:kit/empty', compact('title', 'body', 'actionHtml'));
    }

    /**
     * @param array{
     *     query?: string,
     *     searchName?: string,
     *     searchLabel?: string,
     *     submitLabel?: string,
     *     params?: array<string, scalar>,
     *     selects?: list<array{name: string, label: string, value: scalar, options: array<array-key, string>}>,
     *     chips?: list<array{name: string, value: string, label: string, checked?: bool}>,
     *     resultCount?: string
     * } $opts
     */
    public function toolbar(array $opts): string
    {
        return $this->template->render('Harbor:kit/toolbar', ['opts' => $opts]);
    }
}
