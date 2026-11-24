<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Http\Request;
use Kanboard\Core\Http\Response;
use Kanboard\Core\Security\Role;
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
        $this->assertTrue($tracker->logStartTime($this->subtaskId, 1));

        $subtasks = $this->getMockBuilder(get_class($model))->setConstructorArgs([$this->container])
            ->onlyMethods(['update'])->getMock();
        $subtasks->expects($this->once())->method('update')
            ->with(['id' => $this->subtaskId, 'status' => 2])
            ->willReturnCallback($model->update(...));
        $timers = $this->getMockBuilder(get_class($tracker))->setConstructorArgs([$this->container])
            ->onlyMethods(['toggleTimer'])->getMock();
        $timers->expects($this->once())->method('toggleTimer')->with($this->subtaskId, 1, 2)
            ->willReturnCallback(function (...$args) use ($tracker, $model) {
                $this->assertSame(2, (int) $model->getById($this->subtaskId)['status']);

                return $tracker->toggleTimer(...$args);
            });
        unset($this->container['subtaskModel'], $this->container['subtaskTimeTrackingModel']);
        $this->container['subtaskModel'] = $subtasks;
        $this->container['subtaskTimeTrackingModel'] = $timers;

        [$controller, $response] = $this->request('done');
        $response->expects($this->exactly(2))->method('json')->with(['status' => 'done', 'timer' => false]);
        $controller->set();
        $controller->set();

        $this->assertFalse($tracker->hasTimer($this->subtaskId, 1));
        $this->assertSame(2, (int) $model->getById($this->subtaskId)['status']);
    }

    public function testNonMemberIsForbidden(): void
    {
        $this->assertForbidden();
    }

    public function testViewerIsForbidden(): void
    {
        $this->container['projectUserRoleModel']->addUser($this->projectId, 1, Role::PROJECT_VIEWER);
        $this->assertForbidden();
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

        $this->assertSame(
            ['controller' => 'SubtaskStatusController', 'action' => 'set', 'plugin' => 'Harbor'],
            $this->container['route']->findRoute("harbor/subtask/{$this->subtaskId}/status/done")
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
