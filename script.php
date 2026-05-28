<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

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


