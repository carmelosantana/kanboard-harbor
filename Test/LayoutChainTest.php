<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\Harbor\Theme\LayoutChain;
use KanboardTests\units\Base;

class LayoutChainTest extends Base
{
    public function testRegistrationCapturesTheCurrentLayoutBeforeOverridingIt(): void
    {
        $template = $this->container['template'];
        $template->setTemplateOverride('layout', 'Harbor:../Test/fixtures/steward-layout');
        $previous = $template->getTemplateFile('layout');

        LayoutChain::register($template, 'Harbor:layout');

        $this->assertSame($previous, LayoutChain::previous($template));
        $this->assertSame($template->getTemplateFile('Harbor:layout'), $template->getTemplateFile('layout'));
    }
}
