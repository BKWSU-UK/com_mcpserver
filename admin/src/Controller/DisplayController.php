<?php

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


