<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\View\Credentials;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Mcpserver\Administrator\Extension\McpserverComponent;
use Joomla\Component\Mcpserver\Administrator\Service\CredentialLifecycleService;
use Joomla\Component\Mcpserver\Administrator\Service\GovernanceSetupService;

/**
 * Self-service credential lifecycle view: create, list, and revoke the
 * signed-in user's own MCP credentials. Never renders a stored/previously
 * issued token; only the token returned by the create action, held in user
 * state for exactly one redirect, is ever shown in plaintext.
 */
class HtmlView extends BaseHtmlView
{
    /** @var list<array{id:string,owner_id:int,owner_name:string,selector:string,expires_at:int,created_at:int,revoked:bool}> */
    public $credentials = [];

    /** @var array{id:string,bearer_token:string}|null */
    public $justIssued = null;

    /** @var bool */
    public $governedConfigured = false;

    /** @var bool */
    public $isAdmin = false;

    /** @var bool */
    public $isCoreAdmin = false;

    /** @var array{configured:bool,salt_valid:bool,governed_active:bool,recovery_key_fingerprint:?string} */
    public array $governanceStatus = [
        'configured' => false,
        'salt_valid' => false,
        'governed_active' => false,
        'recovery_key_fingerprint' => null,
    ];

    public function display($tpl = null)
    {
        $app = $this->getApplication();
        $user = $app->getIdentity();

        // Defense in depth: Joomla resolves a view by its `view=` GET
        // parameter independent of which controller/task was invoked, so
        // this view is reachable directly (e.g. via DisplayController)
        // even when a request bypasses CredentialsController's own ACL
        // gate. The ACL invariant is only actually guaranteed if it is
        // also enforced here.
        if (
            $user === null
            || (
                !$user->authorise('mcpserver.credential.self', 'com_mcpserver')
                && !$user->authorise('core.manage', 'com_mcpserver')
            )
        ) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->isAdmin = $user->authorise('core.manage', 'com_mcpserver');
        $this->isCoreAdmin = $user->authorise('core.admin', 'com_mcpserver');

        $setupService = $this->getGovernanceSetupService();
        $this->governanceStatus = $setupService->status();
        $this->governedConfigured = $this->governanceStatus['configured'];

        if ($this->governedConfigured) {
            $this->credentials = $this->getCredentialService()->listForOwner((int) $user->id);
        }

        // Shown exactly once: consumed here so a page refresh never re-displays it.
        $this->justIssued = $app->getUserState('com_mcpserver.credentials.issued');
        $app->setUserState('com_mcpserver.credentials.issued', null);

        ToolbarHelper::title(Text::_('COM_MCPSERVER_CREDENTIALS_TITLE'), 'key');

        parent::display($tpl);
    }

    private function getCredentialService(): CredentialLifecycleService
    {
        $container = McpserverComponent::getServiceContainer();
        if ($container === null || !$container->has(CredentialLifecycleService::class)) {
            throw new \RuntimeException(Text::_('COM_MCPSERVER_CREDENTIALS_NOT_CONFIGURED'));
        }

        return $container->get(CredentialLifecycleService::class);
    }

    private function getGovernanceSetupService(): GovernanceSetupService
    {
        $container = McpserverComponent::getServiceContainer();
        if ($container !== null && $container->has(GovernanceSetupService::class)) {
            return $container->get(GovernanceSetupService::class);
        }

        $params = ComponentHelper::getParams('com_mcpserver');

        return new GovernanceSetupService(
            static fn (): array => $params->toArray(),
            static function (array $values): void {
                // No-op fallback: the view never persists configuration itself.
            },
            static fn (): string => (string) \Joomla\CMS\Factory::getApplication()->get('secret', '')
        );
    }
}
