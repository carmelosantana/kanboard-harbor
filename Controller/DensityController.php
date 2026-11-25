<?php

namespace Kanboard\Plugin\Harbor\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\Harbor\Theme\ThemeResolver;

class DensityController extends BaseController
{
    public function set(): void
    {
        if (!$this->request->isPost()) {
            $this->response->withHeader('Allow', 'POST')->status(405);
            return;
        }
        $this->checkCSRFForm();

        $userId = $this->userSession->getId();
        if ($userId <= 0) {
            throw new AccessForbiddenException();
        }

        $mode = $this->request->getStringParam('mode');
        if (!in_array($mode, ['comfortable', 'compact'], true)) {
            $this->response->json(['error' => 'Invalid density mode'], 400);
            return;
        }

        if (!$this->userMetadataModel->save($userId, [ThemeResolver::DENSITY_KEY => $mode])) {
            $this->response->json(['error' => 'Failed to save density preference'], 500);
            return;
        }

        $returnTo = $this->request->getRawValue('return_to');
        // Accept only an origin-relative URL, as in the theme form.
        if (!is_string($returnTo) || !preg_match('~^/(?!/)~', $returnTo)
            || str_contains($returnTo, "\\") || preg_match('/[[:cntrl:]]/', $returnTo)) {
            $returnTo = $this->helper->url->to('DashboardController', 'show');
        }
        $this->response->redirect($returnTo);
    }
}
