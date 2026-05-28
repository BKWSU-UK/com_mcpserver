<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
    protected $default_view = 'mcpcomponent';

    public function display($cachable = false, $urlparams = array())
    {
        if (!$this->input->getCmd('view')) {
            $this->input->set('view', $this->default_view);
        }
        return parent::display($cachable, $urlparams);
    }
}


