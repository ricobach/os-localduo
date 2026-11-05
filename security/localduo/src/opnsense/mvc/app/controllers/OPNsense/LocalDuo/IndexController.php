<?php
namespace OPNsense\LocalDuo;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        // Explicitly select this plugin's Volt view.
        $this->view->pick('OPNsense/LocalDuo/index');
    }
}
