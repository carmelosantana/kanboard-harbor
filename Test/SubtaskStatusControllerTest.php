<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Http\Request;
use Kanboard\Core\Http\Response;
use Kanboard\Core\Security\Role;
use Kanboard\Model\SubtaskTimeTrackingModel;
use Kanboard\Plugin\Harbor\Controller\SubtaskStatusController;
use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;

class SubtaskStatusControllerTest extends Base
{
    private int $projectId;
    private int $taskId;
    private int $subtaskId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectId = $this->container['projectModel']->create(['name' => 'Harbor']);
        $this->taskId = $this->container['taskCreationModel']->create([
            'title' => 'Task', 'project_id' => $this->projectId,
        ]);
        $this->subtaskId = $this->container['subtaskModel']->create([
            'title' => 'Subtask', 'task_id' => $this->taskId, 'user_id' => 1, 'status' => 1,
        ]);
        $this->container['userSession']->initialize(['id' => 1, 'role' => Role::APP_USER]);
        $this->container['configModel']->save(['subtask_time_tracking' => 1]);
    }

    public function testDoneTwiceWritesAndStopsTheTimerOnce(): void
    {
        $this->addMember();
        $model = $this->container['subtaskModel'];
        $tracker = $this->container['subtaskTimeTrackingModel'];
        $this->assertTrue($this->container['db']->table(SubtaskTimeTrackingModel::TABLE)->insert([
            'subtask_id' => $this->subtaskId, 'user_id' => 1, 'start' => time() - 3600, 'end' => 0,
        ]));

        $subtasks = $this->getMockBuilder(get_class($model))->setConstructorArgs([$this->container])
            ->onlyMethods(['update'])->getMock();
        $subtasks->expects($this->exactly(2))->method('update')
            ->willReturnCallback(function (array $values, bool $fireEvent = true) use ($model) {
                if ($fireEvent) {
                    $this->assertSame(['id' => $this->subtaskId, 'status' => 2], $values);
                } else {
                    $this->assertArrayHasKey('time_spent', $values);
                }

                return $model->update($values, $fireEvent);
            });
        $timers = $this->getMockBuilder(get_class($tracker))->setConstructorArgs([$this->container])
            ->onlyMethods(['logEndTime'])->getMock();
        $timers->expects($this->once())->method('logEndTime')->with($this->subtaskId, 1)
            ->willReturnCallback(function (...$args) use ($tracker, $model) {
                $this->assertSame(2, (int) $model->getById($this->subtaskId)['status']);

                return $tracker->logEndTime(...$args);
            });
        unset($this->container['subtaskModel'], $this->container['subtaskTimeTrackingModel']);
        $this->container['subtaskModel'] = $subtasks;
        $this->container['subtaskTimeTrackingModel'] = $timers;
        $this->container['configModel']->save(['subtask_time_tracking' => 0]);

        [$controller, $response] = $this->request('done');
        $response->expects($this->exactly(2))->method('json')->with(['status' => 'done', 'timer' => false]);
        $controller->set();
        $controller->set();

        $this->assertFalse($tracker->hasTimer($this->subtaskId, 1));
        $this->assertSame(2, (int) $model->getById($this->subtaskId)['status']);
    }

    public function testReturningToTodoClosesTimersThroughRepeatedTransitions(): void
    {
        $this->addMember();
        $tracker = $this->container['subtaskTimeTrackingModel'];
        $timerUserId = $this->container['userModel']->create(['username' => 'timer-owner']);
        $this->assertTrue($tracker->logStartTime($this->subtaskId, $timerUserId));

        $this->setStatus('todo', false);
        $this->assertFalse($tracker->hasTimer($this->subtaskId, $timerUserId));

        $this->setStatus('doing', true);
        $this->assertTrue($tracker->hasTimer($this->subtaskId, 1));

        $this->setStatus('todo', false);
        $this->assertFalse($tracker->hasTimer($this->subtaskId, 1));
    }

    public function testNonMemberIsForbidden(): void
    {
        $this->assertForbidden();
    }

    public function testStartingUnassignedSubtaskAssignsAndTracksTheCurrentUser(): void
    {
        $this->addMember();
        $model = $this->container['subtaskModel'];
        $tracker = $this->container['subtaskTimeTrackingModel'];
        $this->assertTrue($model->update(['id' => $this->subtaskId, 'status' => 0, 'user_id' => 0]));

        $this->setStatus('doing', true);
        $this->assertSame(1, (int) $model->getById($this->subtaskId)['user_id']);
        $this->assertTrue($tracker->hasTimer($this->subtaskId, 1));

        $this->setStatus('done', false);
        $this->assertFalse($tracker->hasTimer($this->subtaskId, 1));
    }

    public function testCompletingUnassignedSubtaskAssignsTheCurrentUser(): void
    {
        $this->addMember();
        $model = $this->container['subtaskModel'];
        $this->assertTrue($model->update(['id' => $this->subtaskId, 'status' => 0, 'user_id' => 0]));

        $this->setStatus('done', false);
        $this->assertSame(1, (int) $model->getById($this->subtaskId)['user_id']);
    }

    public function testViewerIsForbidden(): void
    {
        $this->container['projectUserRoleModel']->addUser($this->projectId, 1, Role::PROJECT_VIEWER);
        $this->assertForbidden();
    }

    public function testApplicationAdminCanSetStatusWithoutProjectMembership(): void
    {
        $this->container['userSession']->initialize(['id' => 1, 'role' => Role::APP_ADMIN]);
        $this->setStatus('done', false);
    }

    public function testMismatchedTaskIsForbidden(): void
    {
        $this->addMember();
        $other = $this->container['taskCreationModel']->create([
            'title' => 'Other task', 'project_id' => $this->projectId,
        ]);
        $this->assertForbidden(taskId: $other);
    }

    public function testMissingCsrfTokenIsForbidden(): void
    {
        $this->addMember();
        $this->assertForbidden(csrf: false);
    }

    public function testInvalidStatusReturnsBadRequest(): void
    {
        $this->addMember();
        [$controller, $response] = $this->request('invalid');
        $response->expects($this->once())->method('json')->with(['error' => 'Invalid subtask status'], 400);
        $controller->set();
        $this->assertUnchanged();
    }

    public function testGetIsRejected(): void
    {
        [$controller, $response] = $this->request('done', method: 'GET');
        $response->expects($this->once())->method('withHeader')->with('Allow', 'POST')->willReturnSelf();
        $response->expects($this->once())->method('status')->with(405);
        $controller->set();
        $this->assertUnchanged();
    }

    public function testCleanRouteDispatchesToHarbor(): void
    {
        $this->container['route']->enable();
        (new Plugin($this->container))->initialize();
        $this->addMember();

        $this->assertSame(
            ['controller' => 'SubtaskStatusController', 'action' => 'set', 'plugin' => 'Harbor'],
            $this->container['route']->findRoute("harbor/subtask/{$this->subtaskId}/status/done")
        );

        $html = $this->container['template']->kit->actions('subtask-row', [
            'status' => 'doing',
            'subtask_id' => $this->subtaskId,
            'task_id' => $this->taskId,
            'project_id' => $this->projectId,
        ]);
        preg_match('~<template>(.*?)</template>~', $html, $matches);
        $menu = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(
            "harbor/subtask/{$this->subtaskId}/status/done?task_id={$this->taskId}&project_id={$this->projectId}",
            $menu[0]['url']
        );
    }

    public function testSubtaskTableRendersWordStatesAndActionRoutes(): void
    {
        $this->addMember();
        (new Plugin($this->container))->initialize();
        $html = $this->container['template']->render('subtask/table', [
            'task' => $this->container['taskFinderModel']->getById($this->taskId),
            'subtasks' => $this->container['subtaskModel']->getAll($this->taskId),
            'editable' => true,
        ]);
        $this->assertStringContainsString('class="js-subtask-toggle-status"', $html);
        $this->assertStringContainsString('data-slot="subtask-row" data-state="doing" data-actions="subtask.done subtask.todo"', $html);
        $this->assertStringContainsString('data-slot="subtask-actions-trigger"', $html);
        $this->assertStringNotContainsString('data-state="1"', $html);
        preg_match('~<template>(.*?)</template>~', $html, $matches);
        $menu = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['Mark done', 'Start working', 'Back to todo'], array_column($menu, 'label'));
        $this->assertSame([false, true, false], array_column($menu, 'disabled'));
        $this->assertSame([['todo', 'doing'], ['todo'], ['doing', 'done']], array_column($menu, 'states'));
        foreach ($menu as $action) {
            parse_str(parse_url($action['url'], PHP_URL_QUERY), $query);
            $this->assertSame('Harbor', $query['plugin']);
            $this->assertSame((string) $this->subtaskId, $query['subtask_id']);
            $this->assertSame((string) $this->taskId, $query['task_id']);
            $this->assertSame((string) $this->projectId, $query['project_id']);
        }
    }

    public function testSubtaskRestrictionDisablesStartInTheRenderedMenu(): void
    {
        $this->restrictSubtasks();
        session_set('hasSubtaskInProgress', true);
        (new Plugin($this->container))->initialize();
        $html = $this->container['template']->render('subtask/table', [
            'task' => $this->container['taskFinderModel']->getById($this->taskId),
            'subtasks' => [$this->container['subtaskModel']->getByIdWithDetails($this->subtaskId)],
            'editable' => true,
        ]);

        $this->assertStringContainsString('controller=SubtaskRestrictionController', $html);
        preg_match('~<template>(.*?)</template>~', $html, $matches);
        $menu = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Start working', $menu[1]['label']);
        $this->assertTrue($menu[1]['disabled']);
        $this->assertSame([], $menu[1]['states']);
        $this->assertStringContainsString('data-state="todo" data-actions="subtask.done"', $html);
    }

    public function testSubtaskRestrictionRejectsStartWhileAnotherIsInProgress(): void
    {
        $otherId = $this->restrictSubtasks();
        [$controller, $response] = $this->request('doing');
        $response->expects($this->once())->method('json')->with(['error' => 'Another subtask is already in progress'], 409);
        $response->expects($this->once())->method('withHeader')->with('X-Harbor-Can-Start-Subtask', '0');
        $controller->set();

        $model = $this->container['subtaskModel'];
        $this->assertSame(0, (int) $model->getById($this->subtaskId)['status']);
        $this->assertFalse($this->container['subtaskTimeTrackingModel']->hasTimer($this->subtaskId, 1));
        $this->assertSame(1, (int) $model->getById($otherId)['status']);

        $targetId = $this->subtaskId;
        $this->subtaskId = $otherId;
        $this->setStatus('doing', false);
        $this->setStatus('todo', false);
        $this->subtaskId = $targetId;
        $this->setStatus('doing', true);
    }

    private function restrictSubtasks(): int
    {
        $this->addMember();
        $this->container['configModel']->save(['subtask_restriction' => 1]);
        $this->container['memoryCache']->flush();
        $model = $this->container['subtaskModel'];
        $this->assertTrue($model->update(['id' => $this->subtaskId, 'status' => 0]));

        return $model->create([
            'title' => 'Already in progress', 'task_id' => $this->taskId, 'user_id' => 1, 'status' => 1,
        ]);
    }

    private function setStatus(string $status, bool $timer): void
    {
        [$controller, $response] = $this->request($status);
        $response->expects($this->once())->method('json')->with(['status' => $status, 'timer' => $timer]);
        $controller->set();
        $this->assertSame(
            SubtaskStatusController::STATUSES[$status],
            (int) $this->container['subtaskModel']->getById($this->subtaskId)['status']
        );
    }

    private function assertForbidden(bool $csrf = true, ?int $taskId = null): void
    {
        [$controller, $response] = $this->request('done', $csrf, $taskId);
        $response->expects($this->never())->method('json');
        $this->expectException(AccessForbiddenException::class);

        try {
            $controller->set();
        } finally {
            $this->assertUnchanged();
        }
    }

    private function assertUnchanged(): void
    {
        $this->assertSame(1, (int) $this->container['subtaskModel']->getById($this->subtaskId)['status']);
    }

    private function addMember(): void
    {
        $this->container['projectUserRoleModel']->addUser($this->projectId, 1, Role::PROJECT_MEMBER);
    }

    private function request(string $status, bool $csrf = true, ?int $taskId = null, string $method = 'POST'): array
    {
        unset($this->container['request'], $this->container['response']);
        $query = ['subtask_id' => $this->subtaskId, 'status' => $status];
        if ($taskId !== null) {
            $query['task_id'] = $taskId;
        }

        $this->container['request'] = new Request($this->container, [
            'REQUEST_METHOD' => $method,
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ], $query, $csrf ? ['csrf_token' => $this->container['token']->getCSRFToken()] : []);
        $response = $this->createMock(Response::class);
        $this->container['response'] = $response;

        return [new SubtaskStatusController($this->container), $response];
    }
}
