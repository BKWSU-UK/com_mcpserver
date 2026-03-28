<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Version as JoomlaVersion;

class HealthController extends BaseController
{
    public function ping(): void
    {
        $version = new JoomlaVersion();
        echo new JsonResponse([
            'status' => 'ok',
            'joomla_version' => $version->getShortVersion(),
        ]);
    }
}


