<?php

namespace Kanboard\Plugin\Harbor\Helper;

use Closure;
use DateTimeImmutable;
use Kanboard\Core\Base;

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

    public function callout(string $title, string $body, string $tone = 'neutral'): string
    {
        return $this->template->render('Harbor:kit/callout', compact('title', 'body', 'tone'));
    }

    /** The stage content is trusted template HTML. Mark fading controls with data-float-controls. */
    public function stage(string $contentHtml, bool $playing = false): string
    {
        return $this->template->render('Harbor:kit/stage', compact('contentHtml', 'playing'));
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
