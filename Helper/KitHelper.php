<?php

namespace Kanboard\Plugin\Harbor\Helper;

use Closure;
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
            $menu[] = array_intersect_key($action, array_flip(['id', 'label', 'key', 'disabled']));
            if (!$action['disabled']) {
                $enabled[] = $action['id'];
            }
        }

        $state = $entity['state'] ?? $entity['status'] ?? '';
        $text = $this->helper->text;
        $json = json_encode($menu, JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

        return '<div data-slot="' . $text->e($slot)
            . '" data-state="' . $text->e(is_scalar($state) ? $state : '')
            . '" data-actions="' . implode(' ', $enabled) . '">'
            . '<template>' . $json . '</template></div>';
    }

    public function pill(string $label, string $tone = 'neutral'): string
    {
        return $this->template->render('Harbor:kit/pill', compact('label', 'tone'));
    }

    public function callout(string $title, string $body, string $tone = 'neutral'): string
    {
        return $this->template->render('Harbor:kit/callout', compact('title', 'body', 'tone'));
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
