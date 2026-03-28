<?php

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerAdapter;

class com_mcpserverInstallerScript
{
    public function install(InstallerAdapter $parent): void {}
    public function uninstall(InstallerAdapter $parent): void {}
    public function update(InstallerAdapter $parent): void {}
    public function preflight(string $type, InstallerAdapter $parent): void {}
    public function postflight(string $type, InstallerAdapter $parent): void {}
}


