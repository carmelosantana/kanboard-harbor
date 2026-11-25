<?php

namespace Kanboard\Plugin\Harbor\Assets;

use Kanboard\Core\Plugin\Hook;
use Psr\Log\LoggerInterface;

final class ModuleLoader
{
    private const MODULES = ['board', 'activity', 'float', 'chat', 'charts', 'ops', 'nav'];

    public function __construct(
        private readonly Hook $hook,
        private readonly LoggerInterface $logger,
        private readonly string $assetDirectory = __DIR__
    ) {
    }

    public function register(string $controller, ?string $plugin): void
    {
        $requested = [];
        $this->hook->merge('kit:modules', $requested, [$controller, $plugin]);
        if (in_array('chat', $requested, true)) {
            array_unshift($requested, 'float');
        }

        $registered = [];
        foreach ($requested as $module) {
            if (!in_array($module, self::MODULES, true)) {
                $this->logger->warning('Harbor skipped an unknown kit:modules name.', ['module' => $module]);
                continue;
            }
            if (isset($registered[$module])) {
                continue;
            }

            $registered[$module] = true;
            foreach (['css', 'js'] as $type) {
                $path = "$type/kit-$module.$type";
                if (is_file($this->assetDirectory . '/' . $path)) {
                    $this->hook->on('template:layout:' . $type, ['template' => 'plugins/Harbor/Assets/' . $path]);
                }
            }
        }
    }
}
