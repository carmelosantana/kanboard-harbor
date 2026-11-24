<?php

namespace Kanboard\Plugin\Harbor\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

class ThemeController extends BaseController
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
        if (!in_array($mode, ['light', 'dark', 'auto'], true)) {
            $this->response->json(['error' => 'Invalid theme mode'], 400);
            return;
        }

        // Core's update refreshes the session and clears its 2FA validation flag.
        $postAuthValidated = $this->userSession->isPostAuthenticationValidated();
        $saved = $this->userModel->update(['id' => $userId, 'theme' => $mode]);
        if ($postAuthValidated) {
            $this->userSession->setPostAuthenticationAsValidated();
        }

        if (!$saved) {
            $this->response->json(['error' => 'Failed to save theme preference'], 500);
        } elseif ($this->request->isAjax()) {
            $this->response->json(['success' => true, 'mode' => $mode]);
        } else {
            $returnTo = $this->request->getRawValue('return_to');
            // Accept only an origin-relative URL. Core sets a no-referrer policy.
            if (!is_string($returnTo) || !preg_match('~^/(?!/)~', $returnTo)
                || str_contains($returnTo, "\\") || preg_match('/[[:cntrl:]]/', $returnTo)) {
                $returnTo = $this->helper->url->to('DashboardController', 'show');
            }
            $this->response->redirect($returnTo);
        }
    }
}
