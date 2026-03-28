<?php

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$autoload = JPATH_ADMINISTRATOR . '/components/com_mcpserver/vendor/autoload.php';

if (is_file($autoload)) {
    require_once $autoload;
}
