<?php

namespace Kanboard\Plugin\Harbor\Action;

use Kanboard\Core\Base;
use Throwable;

/**
 * Collects action descriptors from the kit:actions hook.
 *
 * Every action route must call execute() and reject a null result, or run the
 * same descriptor's can callback itself before performing the action.
 */
class ActionRegistry extends Base
{
    private const HOOK = 'kit:actions';

    private const CONDITION = '/\A([a-zA-Z_][a-zA-Z0-9_]*)\s*(==|!=)\s*([a-zA-Z0-9_.-]+)\z/';
    private const CONTEXT_PARAMS = ['subtask_id' => true, 'task_id' => true, 'project_id' => true];

    private ?array $descriptors = null;

    public function forSlot(string $slot, array $entity, array $user): array
    {
        $actions = [];
        foreach ($this->descriptors() as $descriptor) {
            if ($descriptor['slot'] !== $slot) {
                continue;
            }

            if (!$this->can($descriptor, $entity, $user)) {
                continue;
            }

            $conditions = $descriptor['conditions'];
            unset($descriptor['conditions']);
            $descriptor['disabled'] = !$this->matchesWhen($conditions, $entity);
            if (isset($descriptor['states_from'])) {
                $descriptor['states'] = array_values(array_filter(
                    array_keys($descriptor['states_from']),
                    fn (string $state): bool => $this->matchesWhen(
                        $conditions,
                        ['state' => $state, 'status' => $state] + $entity
                    )
                ));
            }
            $descriptor['route'] = $this->fillRoute($descriptor['route'], $entity);
            $actions[] = $descriptor;
        }

        return $actions;
    }

    public function execute(string $id, array $entity, array $user): array|string|null
    {
        $descriptor = $this->descriptors()[$id] ?? null;
        if ($descriptor === null || !$this->can($descriptor, $entity, $user)) {
            return null;
        }

        return $this->fillRoute($descriptor['route'], $entity);
    }

    private function fillRoute(array|string $route, array $entity): array|string
    {
        return is_array($route) ? array_replace($route, array_intersect_key($entity, self::CONTEXT_PARAMS)) : $route;
    }

    private function can(array $descriptor, array $entity, array $user): bool
    {
        try {
            return $descriptor['can']($entity, $user) === true;
        } catch (Throwable $exception) {
            $this->logger->warning('Harbor denied kit:actions permission for ' . $descriptor['id'] . '.', [
                'exception' => $exception,
            ]);
            return false;
        }
    }

    private function parseWhen(string $when): ?array
    {
        $conditions = [];
        if (trim($when) !== '') {
            foreach (preg_split('/\s+and\s+/', trim($when)) as $condition) {
                if (!preg_match(self::CONDITION, trim($condition), $parts)) {
                    return null;
                }
                $conditions[] = array_slice($parts, 1);
            }
        }

        return $conditions;
    }

    private function matchesWhen(array $conditions, array $entity): bool
    {
        foreach ($conditions as [$field, $operator, $expected]) {
            $actual = $entity[$field] ?? null;
            if (!is_scalar($actual)) {
                return false;
            }

            $equal = (string) $actual === $expected;
            if ($equal !== ($operator === '==')) {
                return false;
            }
        }

        return true;
    }

    private function descriptors(): array
    {
        if ($this->descriptors !== null) {
            return $this->descriptors;
        }

        $descriptors = [];
        foreach ($this->hook->getListeners(self::HOOK) as $listenerIndex => $listener) {
            $entries = is_callable($listener) ? $listener() : null;
            if (!is_array($entries)) {
                $this->logRejection($listenerIndex, 'result');
                continue;
            }

            foreach ($entries as $entryIndex => $entry) {
                if (!$this->isDescriptor($entry) || isset($descriptors[$entry['id']])
                    || ($conditions = $this->parseWhen($entry['when'])) === null) {
                    $this->logRejection($listenerIndex, $entryIndex);
                    continue;
                }
                $entry['conditions'] = $conditions;
                $descriptors[$entry['id']] = $entry;
            }
        }

        return $this->descriptors = $descriptors;
    }

    private function isDescriptor(mixed $entry): bool
    {
        return is_array($entry)
            && is_string($entry['id'] ?? null)
            && preg_match('/\A[a-z][a-z0-9]*(\.[a-z0-9-]+)+\z/', $entry['id']) === 1
            && is_string($entry['label'] ?? null)
            && is_string($entry['slot'] ?? null)
            && array_key_exists('key', $entry)
            && ($entry['key'] === null || is_string($entry['key']))
            && is_string($entry['when'] ?? null)
            && (!array_key_exists('states_from', $entry) || is_array($entry['states_from']))
            && (is_array($entry['route'] ?? null) || is_string($entry['route'] ?? null))
            && in_array($entry['risk'] ?? null, ['read', 'reversible', 'consequential'], true)
            && is_bool($entry['stepUp'] ?? null)
            && is_callable($entry['can'] ?? null);
    }

    private function logRejection(int $listener, int|string $entry): void
    {
        $this->logger->warning('Harbor dropped an invalid or duplicate kit:actions descriptor.', [
            'listener' => $listener,
            'entry' => $entry,
        ]);
    }
}
