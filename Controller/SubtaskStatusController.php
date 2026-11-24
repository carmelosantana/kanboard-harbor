<?php

namespace Kanboard\Plugin\Harbor\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Controller\PageNotFoundException;
use Kanboard\Core\Security\Role;
use Kanboard\Model\SubtaskModel;
use Kanboard\Model\SubtaskTimeTrackingModel;
use Pimple\Container;
use Throwable;

class SubtaskStatusController extends BaseController
{
    public const STATUSES = [
        'todo' => SubtaskModel::STATUS_TODO,
        'doing' => SubtaskModel::STATUS_INPROGRESS,
        'done' => SubtaskModel::STATUS_DONE,
    ];

    private const START_ELIGIBILITY_HEADER = 'X-Harbor-Can-Start-Subtask';

    public static function canSet(Container $container, int $projectId, int $userId): bool
    {
        if ($projectId <= 0 || $userId <= 0) {
            return false;
        }
        if ((int) $container['userSession']->getId() === $userId && $container['userSession']->isAdmin()) {
            return true;
        }

        $role = $container['projectUserRoleModel']->getUserRole($projectId, $userId);
        if ($container['role']->isCustomProjectRole($role)) {
            $role = Role::PROJECT_MEMBER;
        }

        return $container['projectAuthorization']->isAllowed('SubtaskStatusController', 'set', $role);
    }

    public function set(): void
    {
        if (!$this->request->isPost()) {
            $this->response->withHeader('Allow', 'POST')->status(405);
            return;
        }
        $this->checkCSRFForm();

        $state = $this->request->getStringParam('status');
        $status = is_string($state) ? (self::STATUSES[$state] ?? null) : null;
        if ($status === null) {
            $this->response->json(['error' => 'Invalid subtask status'], 400);
            return;
        }

        $id = filter_var($this->request->getStringParam('subtask_id'), FILTER_VALIDATE_INT);
        $subtask = $this->subtaskModel->getById($id);
        if (empty($subtask)) {
            throw new PageNotFoundException();
        }

        $taskId = (int) $subtask['task_id'];
        $projectId = (int) $this->taskFinderModel->getProjectId($taskId);
        $userId = (int) $this->userSession->getId();
        if (filter_var($this->request->getStringParam('task_id', $taskId), FILTER_VALIDATE_INT) !== $taskId
            || filter_var($this->request->getStringParam('project_id', $projectId), FILTER_VALIDATE_INT) !== $projectId
            || !self::canSet($this->container, $projectId, $userId)) {
            throw new AccessForbiddenException();
        }

        $previousStatus = (int) $subtask['status'];
        $startAllowed = !$this->subtaskStatusModel->hasSubtaskInProgress($userId);
        $entity = [
            'status' => array_search($previousStatus, self::STATUSES, true),
            'subtask_id' => $id,
            'task_id' => $taskId,
            'project_id' => $projectId,
            'start_allowed' => (int) $startAllowed,
        ];
        $actionId = match ($state) {
            'todo' => 'subtask.todo',
            'doing' => 'subtask.start',
            'done' => 'subtask.done',
        };
        if ($this->harborActionRegistry->execute($actionId, $entity, $this->userSession->getAll() ?? []) === null) {
            throw new AccessForbiddenException();
        }

        $assigneeId = (int) $subtask['user_id'];
        $timerUserId = $assigneeId ?: $userId;
        if ($previousStatus !== $status) {
            if ($status === SubtaskModel::STATUS_INPROGRESS && !$startAllowed) {
                $this->response->withHeader(self::START_ELIGIBILITY_HEADER, '0');
                $this->response->json(['error' => 'Another subtask is already in progress'], 409);
                return;
            }
            $values = ['id' => $id, 'status' => $status];
            if ($assigneeId === 0 && $status !== SubtaskModel::STATUS_TODO) {
                $values['user_id'] = $userId;
            }

            $this->db->startTransaction();
            try {
                if (!$this->subtaskModel->update($values)) {
                    $this->db->cancelTransaction();
                    $this->response->json(['error' => 'Failed to update subtask status'], 500);
                    return;
                }
                if ($previousStatus === SubtaskModel::STATUS_INPROGRESS) {
                    $this->stopTimers($id);
                } else {
                    $this->subtaskTimeTrackingModel->toggleTimer($id, $timerUserId, $status);
                }
                $this->db->closeTransaction();
            } catch (Throwable $exception) {
                $this->db->cancelTransaction();
                throw $exception;
            }
        }

        if ($this->request->isAjax()) {
            $this->response->withHeader(
                self::START_ELIGIBILITY_HEADER,
                $this->subtaskStatusModel->hasSubtaskInProgress($userId) ? '0' : '1'
            );
            $this->response->json([
                'status' => $state,
                'timer' => $this->subtaskTimeTrackingModel->hasTimer($id, $timerUserId),
            ]);
        } else {
            $this->response->redirect($this->helper->url->to('TaskViewController', 'show', ['task_id' => $taskId]));
        }
    }

    private function stopTimers(int $subtaskId): void
    {
        $timerUsers = $this->db->table(SubtaskTimeTrackingModel::TABLE)
            ->eq('subtask_id', $subtaskId)
            ->eq('end', 0)
            ->findAllByColumn('user_id');
        foreach (array_unique($timerUsers) as $timerUserId) {
            $this->subtaskTimeTrackingModel->logEndTime($subtaskId, (int) $timerUserId);
        }
    }
}
