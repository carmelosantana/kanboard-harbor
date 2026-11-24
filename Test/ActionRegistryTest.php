<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\Harbor\Action\ActionRegistry;
use KanboardTests\units\Base;
use Psr\Log\LoggerInterface;

class ActionRegistryTest extends Base
{
    public function testCollectsValidDescriptorsFromMultipleListenersAndLogsEachRejectionOnce(): void
    {
        $hook = $this->container['hook'];
        $hook->on('kit:actions', fn () => [
            $this->descriptor(['id' => "subtask.done\n"]),
            $this->descriptor(['risk' => 'unknown']),
            array_diff_key($this->descriptor(), ['stepUp' => true]),
            $this->descriptor(['can' => false]),
            $this->descriptor(),
        ]);
        $hook->on('kit:actions', fn () => [
            $this->descriptor(['id' => 'subtask.done', 'label' => 'Duplicate']),
            $this->descriptor(['id' => 'subtask.start']),
            $this->descriptor(['id' => 'task.close', 'slot' => 'task-row']),
        ]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(5))->method('warning')->with($this->stringContains('kit:actions'));
        unset($this->container['logger']);
        $this->container['logger'] = $logger;

        $registry = new ActionRegistry($this->container);
        for ($i = 0; $i < 2; $i++) {
            $actions = $registry->forSlot('subtask-row', ['status' => 'todo'], ['id' => 1]);
            $this->assertSame(['subtask.done', 'subtask.start'], array_column($actions, 'id'));
            $this->assertSame('Mark done', $actions[0]['label']);
        }
    }

    public function testStateGuardsDisableActionsAndPermissionsHideThem(): void
    {
        $this->container['hook']->on('kit:actions', fn () => [
            $this->descriptor(),
            $this->descriptor([
                'id' => 'subtask.start',
                'when' => 'status == done and owner_id != 0',
                'can' => static fn (array $entity, array $user): bool => $entity['owner_id'] === $user['id'],
            ]),
            $this->descriptor(['id' => 'subtask.todo', 'when' => 'status == done and owner_id == 0']),
            $this->descriptor(['id' => 'subtask.unknown', 'when' => 'missing != done']),
            $this->descriptor(['id' => 'subtask.malformed', 'when' => 'status == done or owner_id == 1']),
            $this->descriptor(['id' => 'subtask.view', 'when' => '']),
        ]);
        $registry = new ActionRegistry($this->container);
        $entity = ['status' => 'done', 'owner_id' => 1];

        $this->assertSame([
            'subtask.done' => true,
            'subtask.start' => false,
            'subtask.todo' => true,
            'subtask.unknown' => true,
            'subtask.malformed' => true,
            'subtask.view' => false,
        ], array_column($registry->forSlot('subtask-row', $entity, ['id' => 1]), 'disabled', 'id'));

        $this->assertNotContains('subtask.start', array_column(
            $registry->forSlot('subtask-row', $entity, ['id' => 2]),
            'id'
        ));
    }

    public function testExecuteChecksCurrentPermissionEvenAfterAnAllowedRender(): void
    {
        $allowed = true;
        $can = static function (array $entity, array $user) use (&$allowed): bool {
            return $allowed && $entity['owner_id'] === $user['id'];
        };
        $this->container['hook']->on('kit:actions', fn () => [$this->descriptor(['can' => $can])]);
        $registry = new ActionRegistry($this->container);
        $entity = ['status' => 'todo', 'owner_id' => 7];
        $user = ['id' => 7];

        $this->assertFalse($registry->forSlot('subtask-row', $entity, $user)[0]['disabled']);
        $allowed = false;
        $this->assertNull($registry->execute('subtask.done', $entity, $user));

        $allowed = true;
        $this->assertNull($registry->execute('subtask.done', $entity, ['id' => 8]));
        $this->assertNull($registry->execute('subtask.missing', $entity, $user));
        // Repeating an authorized action can reach its idempotent controller.
        $entity['status'] = 'done';
        $this->assertSame(
            ['SubtaskStatus', 'set', 'status' => 'done'],
            $registry->execute('subtask.done', $entity, $user)
        );
    }

    private function descriptor(array $overrides = []): array
    {
        return array_replace([
            'id' => 'subtask.done',
            'label' => 'Mark done',
            'slot' => 'subtask-row',
            'key' => 'd',
            'when' => 'status != done',
            'route' => ['SubtaskStatus', 'set', 'status' => 'done'],
            'risk' => 'reversible',
            'stepUp' => false,
            'can' => static fn (array $entity, array $user): bool => true,
        ], $overrides);
    }
}
