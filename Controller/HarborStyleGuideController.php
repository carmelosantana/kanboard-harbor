<?php

namespace Kanboard\Plugin\Harbor\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

class HarborStyleGuideController extends BaseController
{
    public function show(): void
    {
        if (!$this->userSession->isAdmin()) {
            throw new AccessForbiddenException();
        }

        if ($this->request->getStringParam('sample') === 'log') {
            $this->response->withoutCache()
                ->withContentType('text/event-stream; charset=utf-8')
                ->withBody(
                    "data: [14:32:08] Snapshot tank/apps/kanboard created\n\n"
                    . "data: [14:32:10] SQLite dump complete (48 MB)\n\n"
                    . "data: [14:32:35] Backup archived\n\n"
                    . "event: done\ndata: complete\n\n"
                )
                ->send();
            return;
        }

        $query = $this->request->getStringParam('search');
        $query = is_string($query) ? trim($query) : '';
        $examples = [
            'neutral' => t('Draft'),
            'success' => t('Current'),
            'warning' => t('Update available'),
            'error' => t('Unavailable'),
        ];
        $matches = array_filter($examples, static fn (string $label): bool => $query === '' || mb_stripos($label, $query) !== false);
        $from = $this->request->getStringParam('from');
        $to = $this->request->getStringParam('to');

        $this->response->html($this->helper->layout->config('Harbor:style_guide/index', [
            'title' => t('Settings') . ' &gt; Harbor &gt; ' . t('Style guide'),
            'query' => $query,
            'matches' => $matches,
            'count' => count($examples),
            'from' => is_string($from) ? $from : null,
            'to' => is_string($to) ? $to : null,
        ]));
    }
}
