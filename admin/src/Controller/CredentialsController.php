<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Component\Mcpserver\Administrator\Extension\McpserverComponent;
use Joomla\Component\Mcpserver\Administrator\Service\CredentialLifecycleService;

/**
 * Bounded credential lifecycle UI controller.
 *
 * Every action requires the `mcpserver.credential.self` or `core.manage`
 * component ACL action before it is reached. `core.manage` additionally
 * lets an administrator revoke any credential by id (the credential
 * lifecycle store only exposes per-owner listing, so an administrator's
 * own list still shows only their own credentials).
 */
class CredentialsController extends BaseController
{
    protected $default_view = 'credentials';

    private const MIN_EXPIRES_DAYS = 1;
    private const MAX_EXPIRES_DAYS = 3650;
    private const DEFAULT_EXPIRES_DAYS = 30;

    public function display($cachable = false, $urlparams = array())
    {
        if (!$this->isAuthorised()) {
            $this->setRedirect('index.php?option=com_mcpserver', Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            return false;
        }

        if (!$this->input->getCmd('view')) {
            $this->input->set('view', $this->default_view);
        }

        return parent::display($cachable, $urlparams);
    }

    /**
     * Issue a new credential owned by the acting user.
     */
    public function create(): void
    {
        if (!$this->isAuthorisedAndTokenValid()) {
            return;
        }

        $user = $this->app->getIdentity();
        $apiToken = $this->input->post->getString('api_token', '');
        $days = $this->input->post->getInt('expires_days', self::DEFAULT_EXPIRES_DAYS);
        $days = max(self::MIN_EXPIRES_DAYS, min(self::MAX_EXPIRES_DAYS, $days));

        try {
            $result = $this->getCredentialService()->issue(
                (int) $user->id,
                (string) $user->username,
                $apiToken,
                time() + ($days * 86400)
            );

            $this->app->setUserState('com_mcpserver.credentials.issued', $result);
        } catch (\Throwable $e) {
            $this->app->enqueueMessage(Text::sprintf('COM_MCPSERVER_CREDENTIALS_CREATE_ERROR', $e->getMessage()), 'error');
        }

        $this->setRedirect('index.php?option=com_mcpserver&view=credentials');
    }

    /**
     * Revoke a credential. Owners may revoke their own; `core.manage`
     * holders may revoke any credential by id.
     */
    public function revoke(): void
    {
        if (!$this->isAuthorisedAndTokenValid()) {
            return;
        }

        $user = $this->app->getIdentity();
        $id = $this->input->post->getString('id', '');
        $isAdmin = $user->authorise('core.manage', 'com_mcpserver');

        try {
            $this->getCredentialService()->revoke($id, (int) $user->id, $isAdmin);
            $this->app->enqueueMessage(Text::_('COM_MCPSERVER_CREDENTIALS_REVOKE_SUCCESS'));
        } catch (\Throwable $e) {
            $this->app->enqueueMessage(Text::sprintf('COM_MCPSERVER_CREDENTIALS_REVOKE_ERROR', $e->getMessage()), 'error');
        }

        $this->setRedirect('index.php?option=com_mcpserver&view=credentials');
    }

    private function isAuthorised(): bool
    {
        $user = $this->app->getIdentity();

        return $user !== null
            && (
                $user->authorise('mcpserver.credential.self', 'com_mcpserver')
                || $user->authorise('core.manage', 'com_mcpserver')
            );
    }

    private function isAuthorisedAndTokenValid(): bool
    {
        if (!$this->isAuthorised()) {
            $this->setRedirect('index.php?option=com_mcpserver', Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            return false;
        }

        if (!Session::checkToken('post')) {
            $this->setRedirect('index.php?option=com_mcpserver&view=credentials', Text::_('JINVALID_TOKEN'), 'error');
            return false;
        }

        return true;
    }

    /**
     * Resolve CredentialLifecycleService from the DI container, mirroring
     * the pattern used by the dashboard and MCPB controllers. This service
     * cannot be safely constructed directly here: its cipher dependency
     * derives key material from the Joomla application secret and the
     * component's governed-mode salt, which is provider.php's
     * responsibility alone.
     */
    private function getCredentialService(): CredentialLifecycleService
    {
        $container = McpserverComponent::getServiceContainer();
        if ($container === null || !$container->has(CredentialLifecycleService::class)) {
            throw new \RuntimeException(Text::_('COM_MCPSERVER_CREDENTIALS_NOT_CONFIGURED'));
        }

        return $container->get(CredentialLifecycleService::class);
    }
}
