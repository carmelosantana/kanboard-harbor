<?php

namespace Kanboard\Plugin\Harbor\Action;

use Kanboard\Core\Base;

class ActionRegistry extends Base
{
    private const HOOK = 'kit:actions';

    private array $loggedRejections = [];

    public function forSlot(string $slot, array $entity, array $user): array
    {
        $actions = [];
        foreach ($this->descriptors() as $descriptor) {
            if ($descriptor['slot'] !== $slot) {
                continue;
            }

            $descriptor['disabled'] = !$this->matchesWhen($descriptor['when'], $entity);
            if ($descriptor['can']($entity, $user) === true) {
                $actions[] = $descriptor;
            }
        }

        return $actions;
    }

    public function execute(string $id, array $entity, array $user): array|string|null
    {
        $descriptor = $this->descriptors()[$id] ?? null;
        if ($descriptor === null || $descriptor['can']($entity, $user) !== true) {
            return null;
        }

        return $descriptor['route'];
    }

    private function matchesWhen(string $when, array $entity): bool
    {
        $when = trim($when);
        if ($when === '') {
            return true;
        }

        foreach (preg_split('/\s+and\s+/', $when) as $condition) {
            if (!preg_match('/\A([a-zA-Z_][a-zA-Z0-9_]*)\s*(==|!=)\s*([a-zA-Z0-9_.-]+)\z/', trim($condition), $parts)) {
                return false;
            }

            [, $field, $operator, $expected] = $parts;
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
        $descriptors = [];
        foreach ($this->hook->getListeners(self::HOOK) as $listenerIndex => $listener) {
            $entries = is_callable($listener) ? $listener() : null;
            if (!is_array($entries)) {
                $this->logRejection($listenerIndex, 'result');
                continue;
            }

            foreach ($entries as $entryIndex => $entry) {
                if (!$this->isDescriptor($entry) || isset($descriptors[$entry['id']])) {
                    $this->logRejection($listenerIndex, $entryIndex);
                    continue;
                }
                $descriptors[$entry['id']] = $entry;
            }
        }

        return $descriptors;
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
            && (is_array($entry['route'] ?? null) || is_string($entry['route'] ?? null))
            && in_array($entry['risk'] ?? null, ['read', 'reversible', 'consequential'], true)
            && is_bool($entry['stepUp'] ?? null)
            && is_callable($entry['can'] ?? null);
    }

    private function logRejection(int $listener, int|string $entry): void
    {
        if (!isset($this->loggedRejections[$listener][$entry])) {
            $this->loggedRejections[$listener][$entry] = true;
            $this->logger->warning('Harbor dropped an invalid or duplicate kit:actions descriptor.', [
                'listener' => $listener,
                'entry' => $entry,
            ]);
        }
    }
}
