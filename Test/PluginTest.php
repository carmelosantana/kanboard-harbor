<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;

class PluginTest extends Base
{
    public function testPluginNameIsNotEmpty()
    {
        $plugin = new Plugin($this->container);
        $this->assertNotEmpty($plugin->getPluginName());
    }

    public function testPluginVersionIsNotEmpty()
    {
        $plugin = new Plugin($this->container);
        $this->assertNotEmpty($plugin->getPluginVersion());
    }

    public function testPluginVersionValue()
    {
        $plugin = new Plugin($this->container);
        $this->assertSame('2.0.0-beta.1', $plugin->getPluginVersion());
    }

    public function testPluginNameValue()
    {
        $plugin = new Plugin($this->container);
        $this->assertSame('Harbor', $plugin->getPluginName());
    }

    public function testPluginAuthor()
    {
        $plugin = new Plugin($this->container);
        $this->assertNotEmpty($plugin->getPluginAuthor());
    }

    private function manifest(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../plugin.json'), true);
    }

    public function testVersionMatchesPluginJson()
    {
        $plugin = new Plugin($this->container);
        $this->assertSame($this->manifest()['version'], $plugin->getPluginVersion());
    }

    public function testDeclaresReplacesShadcnTheme()
    {
        $m = $this->manifest();
        $this->assertSame('Harbor', $m['name']);
        $this->assertSame(['ShadcnTheme'], $m['replaces']);
        $this->assertSame(['BattleLobby'], $m['conflicts']);
    }
}
