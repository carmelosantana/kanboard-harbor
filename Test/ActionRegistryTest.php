<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\Harbor\Action\ActionRegistry;
use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;
use Psr\Log\LoggerInterface;

class ActionRegistryTest extends Base
{
    public function testCollectsValidDescriptorsFromMultipleListenersAndLogsEachRejectionOnce(): void
    {
        $hook = $this->container['hook'];
        $collected = 0;
        $hook->on('kit:actions', function () use (&$collected) {
            $collected++;
            return [
                $this->descriptor(['id' => "subtask.done\n"]),
                $this->descriptor(['risk' => 'unknown']),
                array_diff_key($this->descriptor(), ['stepUp' => true]),
                $this->descriptor(['can' => false]),
                $this->descriptor(['when' => 'status == done or owner_id == 1']),
                $this->descriptor(),
            ];
        });
        $hook->on('kit:actions', fn () => [
            $this->descriptor(['id' => 'subtask.done', 'label' => 'Duplicate']),
            $this->descriptor(['id' => 'subtask.start']),
            $this->descriptor(['id' => 'task.close', 'slot' => 'task-row']),
        ]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(6))->method('warning')->with($this->stringContains('kit:actions'));
        unset($this->container['logger']);
        $this->container['logger'] = $logger;

        $registry = new ActionRegistry($this->container);
        for ($i = 0; $i < 2; $i++) {
            $actions = $registry->forSlot('subtask-row', ['status' => 'todo'], ['id' => 1]);
            $this->assertSame(['subtask.done', 'subtask.start'], array_column($actions, 'id'));
            $this->assertSame('Mark done', $actions[0]['label']);
        }
        $this->assertSame(1, $collected);
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

    public function testThrowingPermissionIsLoggedAndDeniedOnRenderAndExecute(): void
    {
        $this->container['hook']->on('kit:actions', fn () => [$this->descriptor([
            'can' => static fn () => throw new RuntimeException('Permission unavailable'),
        ])]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning')->with($this->stringContains('subtask.done'));
        unset($this->container['logger']);
        $this->container['logger'] = $logger;
        $registry = new ActionRegistry($this->container);

        $this->assertSame([], $registry->forSlot('subtask-row', [], []));
        $this->assertNull($registry->execute('subtask.done', [], []));
    }

    public function testRouteContextComesFromTheEntityAtRenderAndExecute(): void
    {
        $this->container['hook']->on('kit:actions', fn () => [$this->descriptor([
            'route' => ['SubtaskStatus', 'set', 'status' => 'done', 'task_id' => 99],
        ])]);
        $registry = new ActionRegistry($this->container);
        $entity = ['status' => 'todo', 'subtask_id' => 4, 'task_id' => 5, 'project_id' => 6];
        $expected = ['SubtaskStatus', 'set', 'status' => 'done', 'task_id' => 5, 'subtask_id' => 4, 'project_id' => 6];

        $this->assertSame($expected, $registry->forSlot('subtask-row', $entity, [])[0]['route']);
        $this->assertSame($expected, $registry->execute('subtask.done', $entity, []));
    }

    public function testKitExposesOnlyPermittedActionsAsInertJson(): void
    {
        (new Plugin($this->container))->initialize();
        $this->container['userSession']->initialize(['id' => 7]);
        $slot = 'subtask-row" data-unexpected="1';
        $label = '</template><strong>Done &amp; ready</strong>';
        $this->container['hook']->on('kit:actions', fn () => [
            $this->descriptor(['id' => 'test.done', 'slot' => $slot, 'label' => $label, 'can' => static fn ($entity, $user) => $user['id'] === 7]),
            $this->descriptor(['id' => 'test.start', 'slot' => $slot, 'when' => 'status == done']),
            $this->descriptor(['id' => 'test.hidden', 'slot' => $slot, 'can' => static fn () => false]),
        ]);

        $html = $this->container['template']->kit->actions($slot, ['status' => 'todo']);
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $element = $document->getElementsByTagName('div')->item(0);
        $this->assertSame($slot, $element->getAttribute('data-slot'));
        $this->assertSame('todo', $element->getAttribute('data-state'));
        $this->assertSame('test.done', $element->getAttribute('data-actions'));
        $this->assertFalse($element->hasAttribute('data-unexpected'));
        $this->assertSame(0, $document->getElementsByTagName('strong')->length);
        $this->assertSame(0, $document->getElementsByTagName('script')->length);

        $templates = $element->getElementsByTagName('template');
        $this->assertSame(1, $templates->length);
        $menu = json_decode($templates->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['test.done', 'test.start'], array_column($menu, 'id'));
        $this->assertSame([$label, 'Mark done'], array_column($menu, 'label'));
        $this->assertSame([false, true], array_column($menu, 'disabled'));
        foreach ($menu as $item) {
            $this->assertSame('?controller=SubtaskStatus&action=set&status=done', $item['url']);
            $this->assertTrue($this->container['token']->validateCSRFToken($item['csrf_token']));
        }
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
