<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Version as JoomlaVersion;

class HealthController extends BaseController
{
    public function ping(): void
    {
        $version = new JoomlaVersion();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'joomla_version' => $version->getShortVersion(),
        ]);
    }
}


