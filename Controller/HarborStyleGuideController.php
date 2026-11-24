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

        $query = $this->request->getStringParam('search');
        $query = is_string($query) ? trim($query) : '';
        $examples = [
            'neutral' => t('Draft'),
            'success' => t('Current'),
            'warning' => t('Update available'),
            'error' => t('Unavailable'),
        ];
        $matches = array_filter($examples, static fn (string $label): bool => $query === '' || mb_stripos($label, $query) !== false);

        $this->response->html($this->helper->layout->config('Harbor:config/style_guide', [
            'title' => t('Settings') . ' &gt; Harbor &gt; ' . t('Style guide'),
            'query' => $query,
            'matches' => $matches,
            'count' => count($examples),
        ]));
    }
}
